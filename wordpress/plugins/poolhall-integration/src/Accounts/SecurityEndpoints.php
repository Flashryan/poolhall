<?php
/**
 * Candidate security-page HTTP handlers.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * admin-post handlers behind the security page (portal spec §5): classic
 * POST + redirect, no JavaScript required (spec §16). Every action demands
 * a signed-in verified candidate plus a nonce, then the service applies
 * hard rule 17's current-password reauthentication where it matters.
 * Redirects carry status codes only — no addresses, no tokens it minted,
 * no personal data in URLs.
 */
final class SecurityEndpoints {

	public const SECURITY_PATH = '/candidate/security/';

	private const ACTIONS = array(
		'poolhall_change_password'     => 'handle_change_password',
		'poolhall_change_email'        => 'handle_change_email',
		'poolhall_revoke_session'      => 'handle_revoke_session',
		'poolhall_revoke_all_sessions' => 'handle_revoke_all_sessions',
	);

	public function __construct(
		private SecurityService $security,
		private SessionRegistry $sessions,
		private CandidateRepository $candidates,
	) {}

	public function register(): void {
		foreach ( self::ACTIONS as $action => $method ) {
			// No nopriv twin: these forms only exist behind the portal guard,
			// and a signed-out POST has no candidate to act on.
			add_action( 'admin_post_' . $action, array( $this, $method ) );
		}
	}

	public function handle_change_password(): never {
		$user_id = $this->require_candidate( 'poolhall_change_password' );

		$result = $this->security->change_password(
			$user_id,
			$this->raw_password( 'current_password' ),
			$this->raw_password( 'password' ),
			$this->network_signal(),
			$this->now()
		);

		if ( 'changed' === $result['status'] ) {
			// Password rotation invalidated every auth cookie; renew this
			// device's so the candidate stays signed in where they acted.
			wp_set_auth_cookie( $user_id, false );
			$this->redirect( self::SECURITY_PATH . '?status=password_changed' );
		}
		if ( 'rate_limited' === $result['status'] ) {
			$this->redirect( self::SECURITY_PATH . '?error=rate_limited' );
		}
		if ( 'weak_password' === $result['status'] ) {
			$this->redirect( self::SECURITY_PATH . '?pw=' . rawurlencode( implode( ',', $result['errors'] ) ) );
		}
		$this->redirect( self::SECURITY_PATH . '?pw=reauth_failed' );
	}

	public function handle_change_email(): never {
		$user_id = $this->require_candidate( 'poolhall_change_email' );

		$result = $this->security->request_email_change(
			$user_id,
			$this->raw_password( 'current_password' ),
			$this->field( 'email' ),
			$this->network_signal(),
			$this->now()
		);

		if ( 'pending' === $result['status'] ) {
			$this->redirect( self::SECURITY_PATH . '?status=email_pending' );
		}
		if ( 'rate_limited' === $result['status'] ) {
			$this->redirect( self::SECURITY_PATH . '?error=rate_limited' );
		}
		// reauth_failed / email_invalid / email_unchanged annotate the email card.
		$this->redirect( self::SECURITY_PATH . '?em=' . rawurlencode( $result['status'] ) );
	}

	public function handle_revoke_session(): never {
		$user_id = $this->require_candidate( 'poolhall_revoke_session' );

		$this->sessions->revoke( $user_id, $this->field( 'session' ), (string) wp_get_session_token() );
		// Same outcome whether the verifier was live, stale or not theirs:
		// the listed state on return is the truth that matters.
		$this->redirect( self::SECURITY_PATH . '?status=session_revoked' );
	}

	public function handle_revoke_all_sessions(): never {
		$user_id = $this->require_candidate( 'poolhall_revoke_all_sessions' );

		$this->sessions->revoke_others( $user_id, (string) wp_get_session_token() );
		$this->redirect( self::SECURITY_PATH . '?status=sessions_revoked' );
	}

	/**
	 * Signed-in verified candidate with a valid nonce and a clean honeypot,
	 * or a redirect out — mirroring PortalGuard's destinations for GETs.
	 */
	private function require_candidate( string $action ): int {
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			$this->redirect( PortalGuard::LOGIN_PATH . '?return=' . rawurlencode( self::SECURITY_PATH ) );
		}
		if ( ! CandidateRole::is_candidate( $user_id ) ) {
			$this->redirect( '/' );
		}
		if ( ! $this->candidates->is_verified( $user_id ) ) {
			$this->redirect( PortalGuard::VERIFY_PATH . '?state=required' );
		}
		if ( false === wp_verify_nonce( $this->field( '_wpnonce' ), $action )
			|| '' !== $this->field( AuthEndpoints::HONEYPOT_FIELD ) ) {
			$this->redirect( self::SECURITY_PATH );
		}
		return $user_id;
	}

	private function field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in require_candidate() before any state change.
	}

	/** Passwords may legitimately contain anything; sanitizing would corrupt them. */
	private function raw_password( string $name ): string {
		return isset( $_POST[ $name ] ) ? (string) wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- never stored or echoed raw; hashed by WordPress.
	}

	private function network_signal(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	/** @param string $destination Site-relative destination. */
	private function redirect( string $destination ): never {
		wp_safe_redirect( home_url( $destination ) );
		exit;
	}
}
