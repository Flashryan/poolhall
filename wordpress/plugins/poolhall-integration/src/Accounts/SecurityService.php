<?php
/**
 * Candidate security-page flows.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Support\Logger;

/**
 * Portal spec §5 (security page) under hard rule 17: password and email
 * changes require current-password reauthentication. Reauth failures feed
 * the same account/network rate limits as login, so an attacker holding a
 * stolen session cannot brute-force the password from inside it. Email
 * changes are enumeration-safe end to end: the in-browser outcome is the
 * same whether the new address is free, taken or rate-limited — only the
 * new inbox learns which. Every change mails a security notice through the
 * Mailer seam and writes a redacted audit event.
 */
final class SecurityService {

	public function __construct(
		private CandidateRepository $candidates,
		private PasswordPolicy $passwords,
		private TokenPolicy $tokens,
		private ResendPolicy $resends,
		private LoginRateLimiter $limiter,
		private FailedLoginStore $failures,
		private Mailer $mailer,
		private Logger $logger,
	) {}

	/**
	 * Change the password after reauthentication. Revokes every session and
	 * any outstanding reset link; the HTTP layer re-establishes the caller's
	 * own cookie so this device stays signed in.
	 *
	 * @return array{status:'changed'|'reauth_failed'|'rate_limited'|'weak_password',errors:string[]}
	 */
	public function change_password( int $user_id, string $current_password, string $new_password, string $network_signal, \DateTimeImmutable $now ): array {
		$user = $this->candidate( $user_id );
		if ( null === $user ) {
			return self::result( 'reauth_failed' );
		}

		$reauth = $this->reauthenticate( $user, $current_password, $network_signal, $now );
		if ( '' !== $reauth ) {
			return self::result( $reauth );
		}

		$errors = $this->passwords->validate( $new_password, (string) $user->user_email );
		if ( array() !== $errors ) {
			return self::result( 'weak_password', $errors );
		}

		// A live reset link must not survive a manual change (spec §5).
		$this->candidates->clear_reset_token( $user_id );
		wp_set_password( $new_password, $user_id );
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();

		$this->mailer->send(
			(string) $user->user_email,
			__( 'Your password was changed — Poolhall Recruitment', 'poolhall-integration' ),
			sprintf(
				/* translators: %s: login URL. */
				__( "The password for your Poolhall Recruitment account was just changed from your security page, and every other device has been signed out.\n\nIf this was you, no action is needed.\n\nIf this was not you, reset your password immediately:\n%s", 'poolhall-integration' ),
				home_url( '/candidate/forgot-password/' )
			)
		);
		$this->logger->log( 'candidate_password_changed', array( 'user_id' => $user_id ) );

		return self::result( 'changed' );
	}

	/**
	 * Start an email change after reauthentication: store the pending
	 * address with a hashed single-use token and mail a confirmation link
	 * to the *new* inbox. Generic 'pending' covers taken addresses and
	 * resend limits alike — the browser never learns whether an address
	 * holds an account (spec §4 enumeration rules applied to §5).
	 *
	 * @return array{status:'pending'|'reauth_failed'|'rate_limited'|'email_invalid'|'email_unchanged',errors:string[]}
	 */
	public function request_email_change( int $user_id, string $current_password, string $new_email, string $network_signal, \DateTimeImmutable $now ): array {
		$user = $this->candidate( $user_id );
		if ( null === $user ) {
			return self::result( 'reauth_failed' );
		}

		$email = EmailAddress::normalize( $new_email );
		if ( ! EmailAddress::is_valid( $email ) ) {
			return self::result( 'email_invalid' );
		}
		if ( EmailAddress::normalize( (string) $user->user_email ) === $email ) {
			return self::result( 'email_unchanged' );
		}

		$reauth = $this->reauthenticate( $user, $current_password, $network_signal, $now );
		if ( '' !== $reauth ) {
			return self::result( $reauth );
		}

		$reason = $this->resends->denial_reason( $this->candidates->email_change_sends( $user_id ), $now );
		if ( '' !== $reason ) {
			$this->logger->log(
				'candidate_email_change_limited',
				array(
					'user_id' => $user_id,
					'reason'  => $reason,
				)
			);
			return self::result( 'pending' );
		}

		if ( null !== $this->candidates->find_by_email( $email ) ) {
			// Same outward outcome, nothing stored, nothing sent: only the
			// (absent) confirmation email distinguishes a taken address.
			$this->logger->log( 'candidate_email_change_unavailable', array( 'user_id' => $user_id ) );
			return self::result( 'pending' );
		}

		$token = $this->tokens->generate();
		$this->candidates->store_pending_email( $user_id, $email, $this->tokens->hash_token( $token ), $now );

		$sends   = $this->resends->prune( $this->candidates->email_change_sends( $user_id ), $now );
		$sends[] = $now;
		$this->candidates->record_email_change_sends( $user_id, $sends );

		$this->mailer->send(
			$email,
			__( 'Confirm your new email address — Poolhall Recruitment', 'poolhall-integration' ),
			sprintf(
				/* translators: %s: single-use confirmation link. */
				__( "Confirm this address to make it the sign-in email for your Poolhall Recruitment account:\n\n%s\n\nThis link can be used once and expires in 24 hours. If you did not request this change, you can ignore this email — your account is unchanged.", 'poolhall-integration' ),
				home_url( '/candidate/security/?token=' . $token )
			)
		);
		$this->logger->log( 'candidate_email_change_requested', array( 'user_id' => $user_id ) );

		return self::result( 'pending' );
	}

	/**
	 * Redeem an email-change token for the signed-in candidate. Tokens are
	 * matched constant-time against this user's pending hash only — a token
	 * minted for another account can never move this one. The old address is
	 * notified after the change (spec §5).
	 *
	 * @return array{status:'changed'|'invalid'|'expired',errors:string[]}
	 */
	public function confirm_email_change( int $user_id, string $token, \DateTimeImmutable $now ): array {
		$user = $this->candidate( $user_id );
		if ( null === $user || ! $this->tokens->matches( $token, $this->candidates->pending_email_hash( $user_id ) ) ) {
			return self::result( 'invalid' );
		}

		$issued_at = $this->candidates->pending_email_issued_at( $user_id );
		if ( null === $issued_at || $this->tokens->is_expired( $issued_at, $now, TokenPolicy::EMAIL_CHANGE_TTL_SECONDS ) ) {
			return self::result( 'expired' );
		}

		$new_email = $this->candidates->pending_email( $user_id );
		$old_email = (string) $user->user_email;

		// Single use before the write so a failure can never leave a live link.
		$this->candidates->clear_pending_email( $user_id );

		if ( ! EmailAddress::is_valid( $new_email ) || null !== $this->candidates->find_by_email( $new_email ) ) {
			// Taken (or corrupted) since the request: fail generically.
			return self::result( 'invalid' );
		}

		// Core would mail its own change notice; the Mailer seam owns email.
		add_filter( 'send_email_change_email', '__return_false' );
		$result = wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $new_email,
			)
		);
		remove_filter( 'send_email_change_email', '__return_false' );

		if ( is_wp_error( $result ) ) {
			return self::result( 'invalid' );
		}

		$this->mailer->send(
			$old_email,
			__( 'Your sign-in email was changed — Poolhall Recruitment', 'poolhall-integration' ),
			sprintf(
				/* translators: %s: password reset URL. */
				__( "The sign-in email address for your Poolhall Recruitment account was just changed after a confirmed request.\n\nIf this was you, no action is needed.\n\nIf this was not you, reset your password immediately and contact us:\n%s", 'poolhall-integration' ),
				home_url( '/candidate/forgot-password/' )
			)
		);
		$this->logger->log( 'candidate_email_changed', array( 'user_id' => $user_id ) );

		return self::result( 'changed' );
	}

	/** The pending new address awaiting confirmation, '' when none. */
	public function pending_email( int $user_id ): string {
		return $this->candidates->pending_email( $user_id );
	}

	/**
	 * Hard rule 17's gate, sharing login's limiter keys so reauth guesses
	 * and login guesses draw down the same allowance.
	 *
	 * @return ''|'reauth_failed'|'rate_limited'
	 */
	private function reauthenticate( \WP_User $user, string $current_password, string $network_signal, \DateTimeImmutable $now ): string {
		$account_key = 'acct_' . hash( 'sha256', EmailAddress::normalize( (string) $user->user_email ) );
		$network_key = 'net_' . hash( 'sha256', $network_signal );

		foreach ( array( $account_key, $network_key ) as $key ) {
			if ( '' !== $this->limiter->denial_reason( $this->failures->failures( $key ), $now ) ) {
				$this->logger->log( 'candidate_reauth_rate_limited', array( 'user_id' => (int) $user->ID ) );
				return 'rate_limited';
			}
		}

		if ( '' === $current_password || ! wp_check_password( $current_password, (string) $user->user_pass, (int) $user->ID ) ) {
			$this->failures->record_failure( $account_key, $now );
			$this->failures->record_failure( $network_key, $now );
			$this->logger->log( 'candidate_reauth_failed', array( 'user_id' => (int) $user->ID ) );
			return 'reauth_failed';
		}

		$this->failures->clear( $account_key );
		return '';
	}

	private function candidate( int $user_id ): ?\WP_User {
		$user = get_user_by( 'id', $user_id );
		return $user instanceof \WP_User && CandidateRole::is_candidate( $user ) ? $user : null;
	}

	/**
	 * @param string[] $errors Password-policy error codes.
	 * @return array{status:string,errors:string[]}
	 */
	private static function result( string $status, array $errors = array() ): array {
		return array(
			'status' => $status,
			'errors' => $errors,
		);
	}
}
