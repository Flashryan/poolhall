<?php
/**
 * Candidate password recovery.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Support\Logger;

/**
 * Portal spec §5: the forgot-password response is identical for known and
 * unknown emails; reset links are single-use, stored hash-only and expire
 * after 60 minutes; a successful reset invalidates every session and sends
 * a security email. A password is never emailed. Reset emails share the
 * verification ResendPolicy limits so the flow cannot be used to flood an
 * inbox.
 */
final class PasswordRecoveryService {

	public function __construct(
		private CandidateRepository $candidates,
		private PasswordPolicy $passwords,
		private TokenPolicy $tokens,
		private ResendPolicy $resends,
		private Mailer $mailer,
		private Logger $logger,
	) {}

	/**
	 * Generic by design: the response is identical whether the address is
	 * unknown, not a candidate or rate-limited.
	 *
	 * @return array{status:'check_email'}
	 */
	public function request( string $email, \DateTimeImmutable $now ): array {
		$user = $this->candidates->find_by_email( $email );

		if ( $user instanceof \WP_User && CandidateRole::is_candidate( $user ) ) {
			$user_id = (int) $user->ID;
			$reason  = $this->resends->denial_reason( $this->candidates->reset_sends( $user_id ), $now );
			if ( '' === $reason ) {
				$this->send_reset_link( $user_id, (string) $user->user_email, $now );
			} else {
				$this->logger->log(
					'candidate_reset_limited',
					array(
						'user_id' => $user_id,
						'reason'  => $reason,
					)
				);
			}
		}

		return array( 'status' => 'check_email' );
	}

	/**
	 * @return array{status:'reset'|'invalid'|'expired'|'weak_password',errors:string[]}
	 */
	public function reset( string $token, string $new_password, \DateTimeImmutable $now ): array {
		if ( ! $this->tokens->is_well_formed( $token ) ) {
			return self::result( 'invalid' );
		}

		$user_id = $this->candidates->find_by_reset_hash( $this->tokens->hash_token( $token ) );
		if ( null === $user_id ) {
			return self::result( 'invalid' );
		}

		$issued_at = $this->candidates->reset_token_issued_at( $user_id );
		if ( null === $issued_at || $this->tokens->is_expired( $issued_at, $now, TokenPolicy::RESET_TTL_SECONDS ) ) {
			return self::result( 'expired' );
		}

		$user   = get_user_by( 'id', $user_id );
		$errors = $this->passwords->validate( $new_password, $user instanceof \WP_User ? (string) $user->user_email : '' );
		if ( array() !== $errors ) {
			return self::result( 'weak_password', $errors );
		}

		// Single use before the write so a failure can never leave a live link.
		$this->candidates->clear_reset_token( $user_id );
		wp_set_password( $new_password, $user_id );
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();

		if ( $user instanceof \WP_User ) {
			$this->mailer->send(
				(string) $user->user_email,
				__( 'Your password was changed — Poolhall Recruitment', 'poolhall-integration' ),
				sprintf(
					/* translators: %s: login URL. */
					__( "The password for your Poolhall Recruitment account was just changed and you have been signed out everywhere.\n\nIf this was you, no action is needed — sign in again here:\n%s\n\nIf this was not you, reset your password immediately and contact us.", 'poolhall-integration' ),
					home_url( '/candidate/login/' )
				)
			);
		}
		$this->logger->log( 'candidate_password_reset', array( 'user_id' => $user_id ) );

		return self::result( 'reset' );
	}

	private function send_reset_link( int $user_id, string $email, \DateTimeImmutable $now ): void {
		$token = $this->tokens->generate();
		$this->candidates->store_reset_token( $user_id, $this->tokens->hash_token( $token ), $now );

		$sends   = $this->resends->prune( $this->candidates->reset_sends( $user_id ), $now );
		$sends[] = $now;
		$this->candidates->record_reset_sends( $user_id, $sends );

		$this->mailer->send(
			$email,
			__( 'Reset your password — Poolhall Recruitment', 'poolhall-integration' ),
			sprintf(
				/* translators: %s: single-use reset link. */
				__( "Choose a new password for your Poolhall Recruitment account:\n\n%s\n\nThis link can be used once and expires in 60 minutes. If you did not request this, you can ignore this email — your password has not changed.", 'poolhall-integration' ),
				home_url( '/candidate/reset-password/?token=' . $token )
			)
		);
		$this->logger->log( 'candidate_reset_requested', array( 'user_id' => $user_id ) );
	}

	/**
	 * @param string[] $errors Password-policy error codes.
	 * @return array{status:'reset'|'invalid'|'expired'|'weak_password',errors:string[]}
	 */
	private static function result( string $status, array $errors = array() ): array {
		return array(
			'status' => $status,
			'errors' => $errors,
		);
	}
}
