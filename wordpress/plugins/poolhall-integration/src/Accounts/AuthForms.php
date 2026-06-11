<?php
/**
 * Server-rendered candidate auth forms.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * `[poolhall_candidate_auth form="…"]` renders the login, register,
 * verify-email, forgot/reset-password and account screens entirely
 * server-side with the shared design-system classes, so every flow works
 * with JavaScript disabled (portal spec §16) and without Elementor Pro.
 * The future Candidate Auth Elementor widget wraps this same renderer —
 * presentation may move, handlers and markup contracts stay here.
 *
 * Error feedback follows spec §16: a summary alert at the top linking to
 * the fields, plus per-field messages. Status codes arrive as query args
 * from AuthEndpoints; no personal data is ever read from the URL.
 */
final class AuthForms {

	public const SHORTCODE = 'poolhall_candidate_auth';

	private const ERROR_MESSAGES = array(
		'first_name_required' => 'Enter your first name.',
		'last_name_required'  => 'Enter your last name.',
		'email_invalid'       => 'Enter an email address in the right format.',
		'email_unchanged'     => 'That is already the email address on this account.',
		'reauth_failed'       => 'Your current password is not right.',
		'terms_required'      => 'You need to accept the Terms and Privacy Notice to create an account.',
		'too_short'           => 'Use a password of at least 12 characters — a few words work well.',
		'too_long'            => 'Use a password of 128 characters or fewer.',
		'common_password'     => 'That password is too commonly used. Pick something more personal.',
		'low_variety'         => 'Add more variety — that password repeats too few characters.',
		'contains_email'      => 'Your password should not contain your email address.',
	);

	public function __construct(
		private VerificationService $verification,
		private SecurityService $security,
		private SessionRegistry $sessions,
		private SessionDescriber $describer,
	) {}

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * @param array<string,string>|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'form' => 'login' ), is_array( $atts ) ? $atts : array(), self::SHORTCODE );

		$body = match ( $atts['form'] ) {
			'login'    => $this->login(),
			'register' => $this->register_form(),
			'verify'   => $this->verify(),
			'forgot'   => $this->forgot(),
			'reset'    => $this->reset(),
			'account'  => $this->account(),
			'security' => $this->security_page(),
			default    => '',
		};

		return '' === $body ? '' : '<div class="ph-container ph-container--narrow ph-section"><div class="ph-stack-md">' . $body . '</div></div>';
	}

	private function login(): string {
		$status = $this->query_arg( 'status' );
		$error  = $this->query_arg( 'error' );
		$return = $this->query_arg( 'return' );

		$notice = '';
		if ( 'password_reset' === $status ) {
			$notice = $this->alert( 'success', __( 'Your password was changed. Sign in with your new password.', 'poolhall-integration' ) );
		} elseif ( 'signed_out' === $status ) {
			$notice = $this->alert( 'success', __( 'You have signed out.', 'poolhall-integration' ) );
		} elseif ( 'rate_limited' === $error ) {
			$notice = $this->alert( 'error', __( 'Too many sign-in attempts. Wait a few minutes, then try again.', 'poolhall-integration' ) );
		} elseif ( 'invalid' === $error ) {
			$notice = $this->alert( 'error', __( 'That email address and password combination is not right.', 'poolhall-integration' ) );
		}

		return $this->heading( __( 'Sign in', 'poolhall-integration' ), __( 'Save jobs, manage alerts and track your applications.', 'poolhall-integration' ) )
			. $notice
			. '<div class="ph-card"><form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_login' )
			. '<input type="hidden" name="return" value="' . esc_attr( $return ) . '" />'
			. $this->text_field( 'email', __( 'Email address', 'poolhall-integration' ), 'email', 'email' )
			. $this->password_field( 'password', __( 'Password', 'poolhall-integration' ), 'current-password' )
			. '<label class="ph-checkbox"><input type="checkbox" name="remember" value="1" /> <span class="ph-body">' . esc_html__( 'Keep me signed in on this device', 'poolhall-integration' ) . '</span></label>'
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Sign in', 'poolhall-integration' ) . '</button></div>'
			. '</form></div>'
			. '<p class="ph-body"><a class="ph-link" href="' . esc_url( home_url( '/candidate/forgot-password/' ) ) . '">' . esc_html__( 'Forgotten your password?', 'poolhall-integration' ) . '</a></p>'
			. '<p class="ph-body">' . esc_html__( 'New to Poolhall?', 'poolhall-integration' ) . ' <a class="ph-link" href="' . esc_url( home_url( '/candidate/register/' ) ) . '">' . esc_html__( 'Create an account', 'poolhall-integration' ) . '</a></p>';
	}

	private function register_form(): string {
		$codes   = array_filter( explode( ',', $this->query_arg( 'errors' ) ) );
		$summary = $this->error_summary( $codes );

		return $this->heading( __( 'Create your account', 'poolhall-integration' ), __( 'One account for saved jobs, alerts and applications.', 'poolhall-integration' ) )
			. $summary
			. '<div class="ph-card"><form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_register' )
			. '<input type="hidden" name="terms_version" value="2026-06" /><input type="hidden" name="privacy_version" value="2026-06" />'
			. '<div class="ph-form-grid">'
			. $this->text_field( 'first_name', __( 'First name', 'poolhall-integration' ), 'text', 'given-name', $codes, array( 'first_name_required' ) )
			. $this->text_field( 'last_name', __( 'Last name', 'poolhall-integration' ), 'text', 'family-name', $codes, array( 'last_name_required' ) )
			. '</div>'
			. $this->text_field( 'email', __( 'Email address', 'poolhall-integration' ), 'email', 'email', $codes, array( 'email_invalid' ) )
			. $this->password_field( 'password', __( 'Choose a password', 'poolhall-integration' ), 'new-password', __( 'At least 12 characters. A few unrelated words make a strong, memorable password.', 'poolhall-integration' ), $codes, array( 'too_short', 'too_long', 'common_password', 'low_variety', 'contains_email' ) )
			. $this->checkbox_field( 'accept_terms', __( 'I accept the Terms of Use and Privacy Notice.', 'poolhall-integration' ), $codes, 'terms_required' )
			. '<label class="ph-checkbox"><input type="checkbox" name="alert_consent" value="1" /> <span class="ph-body">' . esc_html__( 'Email me job alerts I set up. Optional — you can change this anytime.', 'poolhall-integration' ) . '</span></label>'
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Create account', 'poolhall-integration' ) . '</button></div>'
			. '</form></div>'
			. '<p class="ph-body">' . esc_html__( 'Already have an account?', 'poolhall-integration' ) . ' <a class="ph-link" href="' . esc_url( home_url( PortalGuard::LOGIN_PATH ) ) . '">' . esc_html__( 'Sign in', 'poolhall-integration' ) . '</a></p>';
	}

	private function verify(): string {
		$token = $this->query_arg( 'token' );
		$state = $this->query_arg( 'state' );

		if ( '' !== $token ) {
			$result = $this->verification->verify( $token, new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
			if ( 'verified' === $result['status'] ) {
				return $this->heading( __( 'Email confirmed', 'poolhall-integration' ), '' )
					. $this->alert( 'success', __( 'Your email address is confirmed and your account is active.', 'poolhall-integration' ) )
					. '<p class="ph-body"><a class="ph-button ph-button--primary" href="' . esc_url( home_url( PortalGuard::LOGIN_PATH ) ) . '">' . esc_html__( 'Sign in', 'poolhall-integration' ) . '</a></p>';
			}
			$message = 'expired' === $result['status']
				? __( 'That confirmation link has expired. Enter your email below and we will send a fresh one.', 'poolhall-integration' )
				: __( 'That confirmation link is not valid. Enter your email below and we will send a fresh one.', 'poolhall-integration' );
			return $this->heading( __( 'Confirm your email', 'poolhall-integration' ), '' )
				. $this->alert( 'error', $message )
				. $this->resend_form();
		}

		if ( 'required' === $state ) {
			return $this->heading( __( 'Confirm your email to continue', 'poolhall-integration' ), '' )
				. $this->alert( 'warning', __( 'Your account is nearly ready — follow the link in your confirmation email to unlock saved jobs, alerts and your dashboard.', 'poolhall-integration' ) )
				. $this->resend_form()
				. ( is_user_logged_in() ? $this->signout_form() : '' );
		}

		return $this->heading( __( 'Check your email', 'poolhall-integration' ), '' )
			. $this->alert( 'success', __( 'If that address can be registered, an email is on its way. Follow the link inside to continue — it works once and expires in 24 hours.', 'poolhall-integration' ) )
			. $this->resend_form();
	}

	private function forgot(): string {
		$notice = '';
		if ( 'sent' === $this->query_arg( 'status' ) ) {
			$notice = $this->alert( 'success', __( 'If an account exists for that address, a reset link is on its way. It works once and expires in 60 minutes.', 'poolhall-integration' ) );
		} elseif ( 'link_invalid' === $this->query_arg( 'error' ) ) {
			$notice = $this->alert( 'error', __( 'That reset link is no longer valid. Request a new one below.', 'poolhall-integration' ) );
		}

		return $this->heading( __( 'Reset your password', 'poolhall-integration' ), __( 'Enter your email address and we will send a single-use reset link.', 'poolhall-integration' ) )
			. $notice
			. '<div class="ph-card"><form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_forgot_password' )
			. $this->text_field( 'email', __( 'Email address', 'poolhall-integration' ), 'email', 'email' )
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Send reset link', 'poolhall-integration' ) . '</button></div>'
			. '</form></div>'
			. '<p class="ph-body"><a class="ph-link" href="' . esc_url( home_url( PortalGuard::LOGIN_PATH ) ) . '">' . esc_html__( 'Back to sign in', 'poolhall-integration' ) . '</a></p>';
	}

	private function reset(): string {
		$token = $this->query_arg( 'token' );
		if ( '' === $token ) {
			return $this->heading( __( 'Choose a new password', 'poolhall-integration' ), '' )
				. $this->alert( 'error', __( 'This page needs the link from your reset email.', 'poolhall-integration' ) )
				. '<p class="ph-body"><a class="ph-link" href="' . esc_url( home_url( '/candidate/forgot-password/' ) ) . '">' . esc_html__( 'Request a reset link', 'poolhall-integration' ) . '</a></p>';
		}

		$codes = array_filter( explode( ',', $this->query_arg( 'errors' ) ) );

		return $this->heading( __( 'Choose a new password', 'poolhall-integration' ), '' )
			. $this->error_summary( $codes )
			. '<div class="ph-card"><form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_reset_password' )
			. '<input type="hidden" name="token" value="' . esc_attr( $token ) . '" />'
			. $this->password_field( 'password', __( 'New password', 'poolhall-integration' ), 'new-password', __( 'At least 12 characters. You will be signed out everywhere once it changes.', 'poolhall-integration' ), $codes, array( 'too_short', 'too_long', 'common_password', 'low_variety', 'contains_email' ) )
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Change password', 'poolhall-integration' ) . '</button></div>'
			. '</form></div>';
	}

	/** Minimal signed-in shell; the dashboard modules arrive with their own widgets. */
	private function account(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user     = wp_get_current_user();
		$greeting = '' !== $user->first_name ? $user->first_name : $user->display_name;

		return $this->heading(
			sprintf(
				/* translators: %s: candidate first name. */
				__( 'Hello, %s', 'poolhall-integration' ),
				$greeting
			),
			__( 'Saved jobs, alerts and applications will appear here as they go live.', 'poolhall-integration' )
		)
			. '<p class="ph-body"><a class="ph-link ph-link--arrow" href="' . esc_url( home_url( '/jobs/' ) ) . '">' . esc_html__( 'Browse live jobs', 'poolhall-integration' ) . '</a></p>'
			. '<p class="ph-body"><a class="ph-link ph-link--arrow" href="' . esc_url( home_url( SecurityEndpoints::SECURITY_PATH ) ) . '">' . esc_html__( 'Sign-in & security', 'poolhall-integration' ) . '</a></p>'
			. $this->signout_form();
	}

	/** Portal spec §5 security page: password, email and session controls. */
	private function security_page(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user_id = get_current_user_id();

		$out = $this->heading( __( 'Sign-in & security', 'poolhall-integration' ), __( 'Change your password or email address and see where you are signed in.', 'poolhall-integration' ) );

		$token = $this->query_arg( 'token' );
		if ( '' !== $token ) {
			$result = $this->security->confirm_email_change( $user_id, $token, new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
			$out   .= match ( $result['status'] ) {
				'changed' => $this->alert( 'success', __( 'Your sign-in email address is updated. Use it next time you sign in.', 'poolhall-integration' ) ),
				'expired' => $this->alert( 'error', __( 'That confirmation link has expired. Request the change again below.', 'poolhall-integration' ) ),
				default   => $this->alert( 'error', __( 'That confirmation link is not valid. Request the change again below.', 'poolhall-integration' ) ),
			};
		}

		$out .= match ( $this->query_arg( 'status' ) ) {
			'password_changed' => $this->alert( 'success', __( 'Your password is changed and every other device has been signed out.', 'poolhall-integration' ) ),
			'email_pending'    => $this->alert( 'success', __( 'If that address can be used, a confirmation link is on its way to it. The change happens when you follow the link — it works once and expires in 24 hours.', 'poolhall-integration' ) ),
			'session_revoked'  => $this->alert( 'success', __( 'That device has been signed out.', 'poolhall-integration' ) ),
			'sessions_revoked' => $this->alert( 'success', __( 'Every other device has been signed out.', 'poolhall-integration' ) ),
			default            => '',
		};
		if ( 'rate_limited' === $this->query_arg( 'error' ) ) {
			$out .= $this->alert( 'error', __( 'Too many password attempts. Wait a few minutes, then try again.', 'poolhall-integration' ) );
		}

		return $out
			. $this->change_password_card( array_filter( explode( ',', $this->query_arg( 'pw' ) ) ) )
			. $this->change_email_card( $user_id, array_filter( explode( ',', $this->query_arg( 'em' ) ) ) )
			. $this->sessions_card( $user_id )
			. '<p class="ph-body"><a class="ph-link" href="' . esc_url( home_url( PortalGuard::PATH_PREFIX ) ) . '">' . esc_html__( 'Back to your account', 'poolhall-integration' ) . '</a></p>';
	}

	/**
	 * @param string[] $codes Active error codes for this card.
	 */
	private function change_password_card( array $codes ): string {
		return '<div class="ph-card"><form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. '<h2 class="ph-h4" style="margin: 0;">' . esc_html__( 'Change your password', 'poolhall-integration' ) . '</h2>'
			. $this->error_summary( $codes )
			. $this->common_fields( 'poolhall_change_password' )
			. $this->password_field( 'current_password', __( 'Current password', 'poolhall-integration' ), 'current-password', '', $codes, array( 'reauth_failed' ) )
			. $this->password_field( 'password', __( 'New password', 'poolhall-integration' ), 'new-password', __( 'At least 12 characters. Every other device is signed out when it changes.', 'poolhall-integration' ), $codes, array( 'too_short', 'too_long', 'common_password', 'low_variety', 'contains_email' ) )
			. '<div><button class="ph-button ph-button--primary" type="submit">' . esc_html__( 'Change password', 'poolhall-integration' ) . '</button></div>'
			. '</form></div>';
	}

	/**
	 * @param string[] $codes Active error codes for this card.
	 */
	private function change_email_card( int $user_id, array $codes ): string {
		$pending = $this->security->pending_email( $user_id );
		$notice  = '' === $pending ? '' : $this->alert(
			'warning',
			sprintf(
				/* translators: %s: pending new email address. */
				__( 'A confirmation link is waiting at %s. The change happens when you follow it; submitting again replaces it.', 'poolhall-integration' ),
				$pending
			)
		);

		return '<div class="ph-card"><form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. '<h2 class="ph-h4" style="margin: 0;">' . esc_html__( 'Change your email address', 'poolhall-integration' ) . '</h2>'
			. $notice
			. $this->error_summary( $codes )
			. $this->common_fields( 'poolhall_change_email' )
			. $this->text_field( 'email', __( 'New email address', 'poolhall-integration' ), 'email', 'email', $codes, array( 'email_invalid', 'email_unchanged' ) )
			. $this->password_field( 'current_password', __( 'Current password', 'poolhall-integration' ), 'current-password', __( 'We confirm the new address by email and notify your current one.', 'poolhall-integration' ), $codes, array( 'reauth_failed' ) )
			. '<div><button class="ph-button ph-button--primary" type="submit">' . esc_html__( 'Send confirmation link', 'poolhall-integration' ) . '</button></div>'
			. '</form></div>';
	}

	private function sessions_card( int $user_id ): string {
		$rows = '';
		foreach ( $this->sessions->list_sessions( $user_id, (string) wp_get_session_token() ) as $session ) {
			$described = $this->describer->describe( $session['ua'] );
			$browser   = '' === $described['browser'] ? __( 'Unknown browser', 'poolhall-integration' ) : $described['browser'];
			$platform  = '' === $described['platform'] ? __( 'Unknown device', 'poolhall-integration' ) : $described['platform'];
			$label     = sprintf(
				/* translators: 1: browser family, 2: device/platform family. */
				__( '%1$s on %2$s', 'poolhall-integration' ),
				$browser,
				$platform
			);
			$signed_in = sprintf(
				/* translators: %s: localized date and time. */
				__( 'Signed in %s', 'poolhall-integration' ),
				wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $session['login'] )
			);

			$action = $session['current']
				? '<span class="ph-badge ph-badge--success">' . esc_html__( 'This device', 'poolhall-integration' ) . '</span>'
				: '<form method="post" action="' . $this->action_url() . '" style="margin: 0;">'
					. $this->common_fields( 'poolhall_revoke_session' )
					. '<input type="hidden" name="session" value="' . esc_attr( $session['verifier'] ) . '" />'
					. '<button class="ph-button ph-button--ghost" type="submit">' . esc_html__( 'Sign out', 'poolhall-integration' ) . '</button>'
					. '</form>';

			$rows .= '<li class="ph-cluster" style="justify-content: space-between; align-items: center;">'
				. '<div><p class="ph-body" style="margin: 0;"><strong>' . esc_html( $label ) . '</strong></p>'
				. '<p class="ph-caption" style="margin: 0;">' . esc_html( $signed_in ) . ( '' === $session['ip'] ? '' : ' · ' . esc_html( $session['ip'] ) ) . '</p></div>'
				. $action . '</li>';
		}

		return '<div class="ph-card"><div class="ph-stack-sm">'
			. '<h2 class="ph-h4" style="margin: 0;">' . esc_html__( 'Where you are signed in', 'poolhall-integration' ) . '</h2>'
			. '<ul class="ph-stack-sm" style="list-style: none; margin: 0; padding: 0;">' . $rows . '</ul>'
			. '<form method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_revoke_all_sessions' )
			. '<button class="ph-button ph-button--secondary" type="submit">' . esc_html__( 'Sign out everywhere else', 'poolhall-integration' ) . '</button>'
			. '</form></div></div>';
	}

	private function resend_form(): string {
		return '<div class="ph-card"><form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_resend_verification' )
			. $this->text_field( 'email', __( 'Email address', 'poolhall-integration' ), 'email', 'email' )
			. '<div><button class="ph-button ph-button--secondary" type="submit">' . esc_html__( 'Resend confirmation email', 'poolhall-integration' ) . '</button></div>'
			. '</form></div>';
	}

	private function signout_form(): string {
		return '<form method="post" action="' . $this->action_url() . '">'
			. '<input type="hidden" name="action" value="poolhall_logout" />'
			. wp_nonce_field( 'poolhall_logout', '_wpnonce', false, false )
			. '<button class="ph-button ph-button--ghost" type="submit">' . esc_html__( 'Sign out', 'poolhall-integration' ) . '</button>'
			. '</form>';
	}

	/** Action marker, nonce and honeypot shared by every auth form. */
	private function common_fields( string $action ): string {
		return '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />'
			. wp_nonce_field( $action, '_wpnonce', false, false )
			. '<div class="ph-visually-hidden" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'poolhall-integration' )
			. '<input type="text" name="' . esc_attr( AuthEndpoints::HONEYPOT_FIELD ) . '" tabindex="-1" autocomplete="off" value="" /></label></div>';
	}

	/**
	 * @param string[] $codes        Active error codes from the query string.
	 * @param string[] $field_errors Codes that belong to this field.
	 */
	private function text_field( string $name, string $label, string $type, string $autocomplete, array $codes = array(), array $field_errors = array() ): string {
		$active = array_values( array_intersect( $codes, $field_errors ) );
		$id     = 'ph-auth-' . $name;
		$html   = '<div class="ph-field' . ( array() !== $active ? ' ph-field--invalid' : '' ) . '">'
			. '<label class="ph-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<input class="ph-field__control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" autocomplete="' . esc_attr( $autocomplete ) . '" required' . ( array() !== $active ? ' aria-invalid="true"' : '' ) . ' />';
		foreach ( $active as $code ) {
			$html .= '<p class="ph-field__error">' . esc_html( $this->message( $code ) ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * @param string[] $codes        Active error codes from the query string.
	 * @param string[] $field_errors Codes that belong to this field.
	 */
	private function password_field( string $name, string $label, string $autocomplete, string $help = '', array $codes = array(), array $field_errors = array() ): string {
		$active = array_values( array_intersect( $codes, $field_errors ) );
		$id     = 'ph-auth-' . $name;
		$html   = '<div class="ph-field' . ( array() !== $active ? ' ph-field--invalid' : '' ) . '">'
			. '<label class="ph-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<input class="ph-field__control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="password" autocomplete="' . esc_attr( $autocomplete ) . '" required' . ( array() !== $active ? ' aria-invalid="true"' : '' ) . ' />';
		if ( '' !== $help ) {
			$html .= '<p class="ph-field__help">' . esc_html( $help ) . '</p>';
		}
		foreach ( $active as $code ) {
			$html .= '<p class="ph-field__error">' . esc_html( $this->message( $code ) ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * @param string[] $codes Active error codes from the query string.
	 */
	private function checkbox_field( string $name, string $label, array $codes, string $error_code ): string {
		$invalid = in_array( $error_code, $codes, true );
		$html    = '<div class="ph-field' . ( $invalid ? ' ph-field--invalid' : '' ) . '">'
			. '<label class="ph-checkbox"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . ( $invalid ? ' aria-invalid="true"' : '' ) . ' /> <span class="ph-body">' . esc_html( $label ) . '</span></label>';
		if ( $invalid ) {
			$html .= '<p class="ph-field__error">' . esc_html( $this->message( $error_code ) ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * @param string[] $codes Active error codes from the query string.
	 */
	private function error_summary( array $codes ): string {
		$known = array_values( array_intersect( $codes, array_keys( self::ERROR_MESSAGES ) ) );
		if ( array() === $known ) {
			return '';
		}
		$items = '';
		foreach ( $known as $code ) {
			$items .= '<li class="ph-body">' . esc_html( $this->message( $code ) ) . '</li>';
		}
		return '<div class="ph-alert ph-alert--error" role="alert"><p class="ph-body"><strong>' . esc_html__( 'Check the form and try again:', 'poolhall-integration' ) . '</strong></p><ul>' . $items . '</ul></div>';
	}

	private function message( string $code ): string {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- table of fixed literals translated at lookup.
		return isset( self::ERROR_MESSAGES[ $code ] ) ? __( self::ERROR_MESSAGES[ $code ], 'poolhall-integration' ) : $code;
	}

	private function heading( string $title, string $lede ): string {
		$html = '<header class="ph-stack-xs"><h1 class="ph-h2" style="color: var(--ph-color-navy-700); margin: 0;">' . esc_html( $title ) . '</h1>';
		if ( '' !== $lede ) {
			$html .= '<p class="ph-lede" style="margin: 0;">' . esc_html( $lede ) . '</p>';
		}
		return $html . '</header>';
	}

	private function alert( string $kind, string $message ): string {
		return '<div class="ph-alert ph-alert--' . esc_attr( $kind ) . '" role="status"><p class="ph-body" style="margin: 0;">' . esc_html( $message ) . '</p></div>';
	}

	private function action_url(): string {
		return esc_url( admin_url( 'admin-post.php' ) );
	}

	/** Read-only rendering hints; no state changes happen at render time. */
	private function query_arg( string $name ): string {
		return isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
