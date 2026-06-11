<?php
/**
 * Candidate auth HTTP handlers.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * admin-post handlers behind the auth forms (portal spec §5/§15): classic
 * POST + redirect so every flow works without JavaScript (spec §16). Each
 * handler verifies a nonce, checks the honeypot, runs the
 * `poolhall_human_check` seam (Turnstile attaches there once keys exist),
 * delegates to the services and redirects with status codes only — no
 * emails or other personal data ever appear in a URL.
 */
final class AuthEndpoints {

	public const HONEYPOT_FIELD = 'ph_website';

	private const ACTIONS = array(
		'poolhall_login'               => 'handle_login',
		'poolhall_register'            => 'handle_register',
		'poolhall_resend_verification' => 'handle_resend_verification',
		'poolhall_forgot_password'     => 'handle_forgot_password',
		'poolhall_reset_password'      => 'handle_reset_password',
		'poolhall_logout'              => 'handle_logout',
	);

	public function __construct(
		private LoginService $login,
		private RegistrationService $registration,
		private VerificationService $verification,
		private PasswordRecoveryService $recovery,
		private ReturnUrlPolicy $returns,
	) {}

	public function register(): void {
		foreach ( self::ACTIONS as $action => $method ) {
			// Both hooks: auth pages are public, but a signed-in user
			// submitting a stale form must land somewhere sensible too.
			add_action( 'admin_post_nopriv_' . $action, array( $this, $method ) );
			add_action( 'admin_post_' . $action, array( $this, $method ) );
		}
	}

	public function handle_login(): never {
		$return = $this->returns->sanitize( $this->field( 'return' ) );
		if ( ! $this->request_passes( 'poolhall_login' ) ) {
			$this->redirect( PortalGuard::LOGIN_PATH . '?error=invalid&return=' . rawurlencode( $return ) );
		}
		if ( is_user_logged_in() ) {
			$this->redirect( $return );
		}

		$result = $this->login->attempt(
			array(
				'email'    => $this->field( 'email' ),
				'password' => $this->raw_password( 'password' ),
			),
			$this->network_signal(),
			$this->now()
		);

		if ( 'ok' === $result['status'] ) {
			wp_set_auth_cookie( $result['user_id'], '1' === $this->field( 'remember' ) );
			$this->redirect( $return );
		}
		if ( 'verify_required' === $result['status'] ) {
			$this->redirect( PortalGuard::VERIFY_PATH . '?state=required' );
		}
		if ( 'rate_limited' === $result['status'] ) {
			$this->redirect( PortalGuard::LOGIN_PATH . '?error=rate_limited&return=' . rawurlencode( $return ) );
		}
		$this->redirect( PortalGuard::LOGIN_PATH . '?error=invalid&return=' . rawurlencode( $return ) );
	}

	public function handle_register(): never {
		if ( ! $this->request_passes( 'poolhall_register' ) ) {
			// Honeypot/expired form: the generic outcome, nothing created.
			$this->redirect( PortalGuard::VERIFY_PATH . '?state=sent' );
		}

		$result = $this->registration->register(
			array(
				'first_name'      => $this->field( 'first_name' ),
				'last_name'       => $this->field( 'last_name' ),
				'email'           => $this->field( 'email' ),
				'password'        => $this->raw_password( 'password' ),
				'accept_terms'    => '1' === $this->field( 'accept_terms' ),
				'alert_consent'   => '1' === $this->field( 'alert_consent' ),
				'terms_version'   => $this->field( 'terms_version' ),
				'privacy_version' => $this->field( 'privacy_version' ),
			),
			$this->now()
		);

		if ( 'invalid' === $result['status'] ) {
			$this->redirect( '/candidate/register/?errors=' . rawurlencode( implode( ',', $result['errors'] ) ) );
		}
		$this->redirect( PortalGuard::VERIFY_PATH . '?state=sent' );
	}

	public function handle_resend_verification(): never {
		if ( $this->request_passes( 'poolhall_resend_verification' ) ) {
			$this->verification->resend( $this->field( 'email' ), $this->now() );
		}
		// Identical outcome whether sent, limited, unknown or a bot.
		$this->redirect( PortalGuard::VERIFY_PATH . '?state=sent' );
	}

	public function handle_forgot_password(): never {
		if ( $this->request_passes( 'poolhall_forgot_password' ) ) {
			$this->recovery->request( $this->field( 'email' ), $this->now() );
		}
		$this->redirect( '/candidate/forgot-password/?status=sent' );
	}

	public function handle_reset_password(): never {
		$token = $this->field( 'token' );
		if ( ! $this->request_passes( 'poolhall_reset_password' ) ) {
			$this->redirect( '/candidate/forgot-password/?error=link_invalid' );
		}

		$result = $this->recovery->reset( $token, $this->raw_password( 'password' ), $this->now() );

		if ( 'reset' === $result['status'] ) {
			$this->redirect( PortalGuard::LOGIN_PATH . '?status=password_reset' );
		}
		if ( 'weak_password' === $result['status'] ) {
			// The token already reached this user in their email link, so
			// echoing it back keeps the form usable for another attempt.
			$this->redirect( '/candidate/reset-password/?token=' . rawurlencode( $token ) . '&errors=' . rawurlencode( implode( ',', $result['errors'] ) ) );
		}
		$this->redirect( '/candidate/forgot-password/?error=link_invalid' );
	}

	public function handle_logout(): never {
		if ( false !== wp_verify_nonce( $this->field( '_wpnonce' ), 'poolhall_logout' ) ) {
			wp_logout();
		}
		$this->redirect( PortalGuard::LOGIN_PATH . '?status=signed_out' );
	}

	/** Nonce, honeypot and the pluggable human check, in that order. */
	private function request_passes( string $action ): bool {
		if ( false === wp_verify_nonce( $this->field( '_wpnonce' ), $action ) ) {
			return false;
		}
		if ( '' !== $this->field( self::HONEYPOT_FIELD ) ) {
			return false;
		}
		/**
		 * Human-verification seam (portal spec §4). Turnstile validation hooks
		 * here when site keys are configured; default allows the request.
		 *
		 * @param bool   $passes Whether the request looks human.
		 * @param string $action The auth action being attempted.
		 */
		return (bool) apply_filters( 'poolhall_human_check', true, $action );
	}

	private function field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in request_passes() before any state change.
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
