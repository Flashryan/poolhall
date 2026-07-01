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
		'too_short'           => 'Use a password of at least 12 characters. A few words work well.',
		'too_long'            => 'Use a password of 128 characters or fewer.',
		'common_password'     => 'That password is too commonly used. Pick something more personal.',
		'low_variety'         => 'Add more variety. That password repeats too few characters.',
		'contains_email'      => 'Your password should not contain your email address.',
	);

	public function __construct(
		private VerificationService $verification,
		private SecurityService $security,
		private SessionRegistry $sessions,
		private SessionDescriber $describer,
		private SavedJobsRepository $saved,
		private \Poolhall\Integration\Jobs\JobRepository $jobs,
	) {}

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * @param array<string,string>|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'form' => 'login' ), is_array( $atts ) ? $atts : array(), self::SHORTCODE );

		// Auth screens render as a centred 460px card (spec §3.9); the
		// signed-in account dashboard and security page use the wider
		// portal shell. Each method returns its own complete wrapper.
		return match ( $atts['form'] ) {
			'login'    => $this->login(),
			'register' => $this->register_form(),
			'verify'   => $this->verify(),
			'forgot'   => $this->forgot(),
			'reset'    => $this->reset(),
			'account'  => $this->account(),
			'security' => $this->security_page(),
			default    => '',
		};
	}

	/**
	 * Centred auth card: logo, serif-500 title, helper sub, the form body,
	 * and an optional cross-link row beneath the card (spec §3.9).
	 */
	private function auth_card( string $title, string $sub, string $inner, string $foot = '' ): string {
		$logo_url = POOLHALL_INTEGRATION_URL . 'assets/img/content/poolhall-logo.png';
		$head     = '<div class="ph-auth-card__head">'
			. '<a class="ph-auth-card__logo" href="' . esc_url( home_url( '/' ) ) . '">'
			. '<img src="' . esc_url( $logo_url ) . '" alt="" width="40" height="40" /><span>Poolhall</span></a>'
			. '<h1 class="ph-auth-card__title">' . esc_html( $title ) . '</h1>'
			. ( '' !== $sub ? '<p class="ph-auth-card__sub">' . esc_html( $sub ) . '</p>' : '' )
			. '</div>';

		return '<div class="ph-auth"><div class="ph-auth__inner">'
			. '<div class="ph-auth-card"><div class="ph-stack-sm">' . $head . $inner . '</div></div>'
			. ( '' === $foot ? '' : '<div class="ph-auth__alt">' . $foot . '</div>' )
			. '</div></div>';
	}

	/** Signed-in portal shell: 760px column for the dashboard and security. */
	private function portal_shell( string $inner ): string {
		return '' === $inner ? '' : '<div class="ph-portal"><div class="ph-stack-md">' . $inner . '</div></div>';
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

		$inner = $notice
			. '<form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_login' )
			. '<input type="hidden" name="return" value="' . esc_attr( $return ) . '" />'
			. $this->text_field( 'email', __( 'Email address', 'poolhall-integration' ), 'email', 'email' )
			. $this->password_field( 'password', __( 'Password', 'poolhall-integration' ), 'current-password' )
			. '<div class="ph-form__row"><label class="ph-checkbox"><input type="checkbox" name="remember" value="1" /> <span class="ph-body">' . esc_html__( 'Keep me signed in', 'poolhall-integration' ) . '</span></label>'
			. '<a class="ph-link" href="' . esc_url( home_url( '/candidate/forgot-password/' ) ) . '">' . esc_html__( 'Forgotten password?', 'poolhall-integration' ) . '</a></div>'
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Sign in', 'poolhall-integration' ) . '</button></div>'
			. '</form>';

		$foot = '<p class="ph-body">' . esc_html__( 'New to Poolhall?', 'poolhall-integration' ) . ' <a class="ph-link" href="' . esc_url( home_url( '/candidate/register/' ) ) . '">' . esc_html__( 'Create an account', 'poolhall-integration' ) . '</a></p>';

		return $this->auth_card( __( 'Welcome back', 'poolhall-integration' ), __( 'Sign in to save jobs, manage alerts and track your applications.', 'poolhall-integration' ), $inner, $foot );
	}

	private function register_form(): string {
		$codes   = array_filter( explode( ',', $this->query_arg( 'errors' ) ) );
		$summary = $this->error_summary( $codes );

		$inner = $summary
			. '<form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_register' )
			. '<input type="hidden" name="terms_version" value="2026-06" /><input type="hidden" name="privacy_version" value="2026-06" />'
			. '<div class="ph-form-grid">'
			. $this->text_field( 'first_name', __( 'First name', 'poolhall-integration' ), 'text', 'given-name', $codes, array( 'first_name_required' ) )
			. $this->text_field( 'last_name', __( 'Last name', 'poolhall-integration' ), 'text', 'family-name', $codes, array( 'last_name_required' ) )
			. '</div>'
			. $this->text_field( 'email', __( 'Email address', 'poolhall-integration' ), 'email', 'email', $codes, array( 'email_invalid' ) )
			. '<p class="ph-eyebrow" style="margin-top:0.5rem;">' . esc_html__( 'Help us match you', 'poolhall-integration' ) . '</p>'
			. '<p class="ph-small ph-text-muted" style="margin-top:-0.25rem;">' . esc_html__( 'These are optional and go to our team to match you to roles. You can add them later from your profile.', 'poolhall-integration' ) . '</p>'
			. '<div class="ph-form-grid">'
			. $this->optional_field( 'phone', __( 'Phone number', 'poolhall-integration' ), 'tel', 'tel' )
			. $this->optional_field( 'location', __( 'Location', 'poolhall-integration' ), 'text', 'address-level2' )
			. '</div>'
			. '<div class="ph-form-grid">'
			. $this->optional_field( 'role_title', __( 'Desired role or job title', 'poolhall-integration' ), 'text', 'organization-title' )
			. $this->optional_field( 'salary_expectations', __( 'Salary expectations', 'poolhall-integration' ), 'text', 'off' )
			. '</div>'
			. $this->optional_field( 'linkedin', __( 'LinkedIn profile', 'poolhall-integration' ), 'url', 'url' )
			. $this->optional_textarea( 'summary', __( 'About you', 'poolhall-integration' ), __( 'A short summary of your experience and the roles you are looking for', 'poolhall-integration' ) )
			. $this->password_field( 'password', __( 'Choose a password', 'poolhall-integration' ), 'new-password', __( 'At least 12 characters. A few unrelated words make a strong, memorable password.', 'poolhall-integration' ), $codes, array( 'too_short', 'too_long', 'common_password', 'low_variety', 'contains_email' ) )
			. $this->checkbox_field( 'accept_terms', __( 'I accept the Terms of Use and Privacy Notice.', 'poolhall-integration' ), $codes, 'terms_required' )
			. '<label class="ph-checkbox"><input type="checkbox" name="alert_consent" value="1" /> <span class="ph-body">' . esc_html__( 'Email me job alerts I set up. Optional, and you can change this anytime.', 'poolhall-integration' ) . '</span></label>'
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Create account', 'poolhall-integration' ) . '</button></div>'
			. '</form>';

		$foot = '<p class="ph-body">' . esc_html__( 'Already have an account?', 'poolhall-integration' ) . ' <a class="ph-link" href="' . esc_url( home_url( PortalGuard::LOGIN_PATH ) ) . '">' . esc_html__( 'Sign in', 'poolhall-integration' ) . '</a></p>';

		return $this->auth_card( __( 'Create your account', 'poolhall-integration' ), __( 'One account for saved jobs, alerts and applications.', 'poolhall-integration' ), $inner, $foot );
	}

	private function verify(): string {
		$token = $this->query_arg( 'token' );
		$state = $this->query_arg( 'state' );

		if ( '' !== $token ) {
			$result = $this->verification->verify( $token, new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
			if ( 'verified' === $result['status'] ) {
				return $this->auth_card(
					__( 'Email confirmed', 'poolhall-integration' ),
					'',
					$this->alert( 'success', __( 'Your email address is confirmed and your account is active.', 'poolhall-integration' ) )
					. '<div><a class="ph-button ph-button--primary ph-button--full" href="' . esc_url( home_url( PortalGuard::LOGIN_PATH ) ) . '">' . esc_html__( 'Sign in', 'poolhall-integration' ) . '</a></div>'
				);
			}
			$message = 'expired' === $result['status']
				? __( 'That confirmation link has expired. Enter your email below and we will send a fresh one.', 'poolhall-integration' )
				: __( 'That confirmation link is not valid. Enter your email below and we will send a fresh one.', 'poolhall-integration' );
			return $this->auth_card(
				__( 'Confirm your email', 'poolhall-integration' ),
				'',
				$this->alert( 'error', $message ) . $this->resend_form()
			);
		}

		if ( 'required' === $state ) {
			return $this->auth_card(
				__( 'Confirm your email to continue', 'poolhall-integration' ),
				'',
				$this->alert( 'warning', __( 'Your account is nearly ready. Follow the link in your confirmation email to unlock saved jobs, alerts and your dashboard.', 'poolhall-integration' ) )
				. $this->resend_form()
				. ( is_user_logged_in() ? $this->signout_form() : '' )
			);
		}

		return $this->auth_card(
			__( 'Check your email', 'poolhall-integration' ),
			'',
			$this->alert( 'success', __( 'If that address can be registered, an email is on its way. Follow the link inside to continue. It works once and expires in 24 hours.', 'poolhall-integration' ) )
			. $this->resend_form()
		);
	}

	private function forgot(): string {
		$notice = '';
		if ( 'sent' === $this->query_arg( 'status' ) ) {
			$notice = $this->alert( 'success', __( 'If an account exists for that address, a reset link is on its way. It works once and expires in 60 minutes.', 'poolhall-integration' ) );
		} elseif ( 'link_invalid' === $this->query_arg( 'error' ) ) {
			$notice = $this->alert( 'error', __( 'That reset link is no longer valid. Request a new one below.', 'poolhall-integration' ) );
		}

		$inner = $notice
			. '<form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_forgot_password' )
			. $this->text_field( 'email', __( 'Email address', 'poolhall-integration' ), 'email', 'email' )
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Send reset link', 'poolhall-integration' ) . '</button></div>'
			. '</form>';

		$foot = '<p class="ph-body"><a class="ph-link" href="' . esc_url( home_url( PortalGuard::LOGIN_PATH ) ) . '">' . esc_html__( 'Back to sign in', 'poolhall-integration' ) . '</a></p>';

		return $this->auth_card( __( 'Reset your password', 'poolhall-integration' ), __( 'Enter your email address and we will send a single-use reset link.', 'poolhall-integration' ), $inner, $foot );
	}

	private function reset(): string {
		$token = $this->query_arg( 'token' );
		if ( '' === $token ) {
			return $this->auth_card(
				__( 'Choose a new password', 'poolhall-integration' ),
				'',
				$this->alert( 'error', __( 'This page needs the link from your reset email.', 'poolhall-integration' ) ),
				'<p class="ph-body"><a class="ph-link" href="' . esc_url( home_url( '/candidate/forgot-password/' ) ) . '">' . esc_html__( 'Request a reset link', 'poolhall-integration' ) . '</a></p>'
			);
		}

		$codes = array_filter( explode( ',', $this->query_arg( 'errors' ) ) );

		$inner = $this->error_summary( $codes )
			. '<form class="ph-form" method="post" action="' . $this->action_url() . '">'
			. $this->common_fields( 'poolhall_reset_password' )
			. '<input type="hidden" name="token" value="' . esc_attr( $token ) . '" />'
			. $this->password_field( 'password', __( 'New password', 'poolhall-integration' ), 'new-password', __( 'At least 12 characters. You will be signed out everywhere once it changes.', 'poolhall-integration' ), $codes, array( 'too_short', 'too_long', 'common_password', 'low_variety', 'contains_email' ) )
			. '<div><button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Change password', 'poolhall-integration' ) . '</button></div>'
			. '</form>';

		return $this->auth_card( __( 'Choose a new password', 'poolhall-integration' ), '', $inner );
	}

	/**
	 * Signed-in candidate dashboard (portal spec §3). Saved jobs are real
	 * (the saved-jobs service); alerts, applications and CV are surfaced as
	 * honest "coming soon" tiles until their features land, never as fake
	 * data. The page is gated and noindexed by PortalGuard.
	 */
	private function account(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user     = wp_get_current_user();
		$greeting = '' !== $user->first_name ? $user->first_name : $user->display_name;
		$user_id  = (int) $user->ID;

		$saved_count = $this->saved->count_for_user( $user_id );

		$head = '<header class="ph-portal__head">'
			. '<div class="ph-stack-xs">'
			. '<p class="ph-eyebrow" style="color: var(--ph-color-orange-700);">' . esc_html__( 'Your account', 'poolhall-integration' ) . '</p>'
			. '<h1 class="ph-h2" style="color: var(--ph-color-navy-700); margin: 0;">'
			. esc_html(
				sprintf(
					/* translators: %s: candidate first name. */
					__( 'Hello, %s', 'poolhall-integration' ),
					$greeting
				)
			) . '</h1>'
			. '<p class="ph-lede" style="margin: 0;">' . esc_html__( 'Your saved jobs, alerts and applications in one place.', 'poolhall-integration' ) . '</p>'
			. '</div>'
			. $this->signout_form()
			. '</header>';

		// Quick tiles: saved jobs is live; the rest announce themselves.
		$tiles = '<div class="ph-portal-grid">'
			. $this->portal_tile(
				$this->icon_bookmark(),
				(string) $saved_count,
				_n( 'Saved job', 'Saved jobs', $saved_count, 'poolhall-integration' ),
				home_url( '/jobs/' ),
				$saved_count > 0 ? __( 'View saved jobs', 'poolhall-integration' ) : __( 'Browse live jobs', 'poolhall-integration' )
			)
			. $this->portal_tile( $this->icon_bell(), __( 'Job alerts', 'poolhall-integration' ), __( 'Coming soon', 'poolhall-integration' ), '', '' )
			. $this->portal_tile( $this->icon_doc(), __( 'Applications', 'poolhall-integration' ), __( 'Coming soon', 'poolhall-integration' ), '', '' )
			. '</div>';

		$saved_panel = $this->saved_jobs_panel( $user_id );

		$security = '<a class="ph-card ph-portal-link" href="' . esc_url( home_url( SecurityEndpoints::SECURITY_PATH ) ) . '">'
			. '<span class="ph-portal-link__text"><strong>' . esc_html__( 'Sign-in & security', 'poolhall-integration' ) . '</strong>'
			. '<span class="ph-small ph-text-muted">' . esc_html__( 'Password, email address and where you are signed in.', 'poolhall-integration' ) . '</span></span>'
			. '<span class="ph-portal-link__arrow" aria-hidden="true">&rarr;</span></a>';

		return $this->portal_shell( $head . $tiles . $saved_panel . $security );
	}

	/** One dashboard quick tile: icon, big value, label, optional action link. */
	private function portal_tile( string $icon, string $value, string $label, string $href, string $cta ): string {
		$action = '' === $href
			? '<span class="ph-small ph-text-muted">' . esc_html( $cta ) . '</span>'
			: '<a class="ph-link ph-link--arrow" href="' . esc_url( $href ) . '">' . esc_html( $cta ) . '</a>';
		return '<div class="ph-card ph-portal-tile">'
			. '<span class="ph-portal-tile__icon" aria-hidden="true">' . $icon . '</span>'
			. '<span class="ph-portal-tile__value">' . esc_html( $value ) . '</span>'
			. '<span class="ph-portal-tile__label">' . esc_html( $label ) . '</span>'
			. $action
			. '</div>';
	}

	/** Saved-jobs preview: up to three live saved roles, or an empty state. */
	private function saved_jobs_panel( int $user_id ): string {
		$items = $this->saved->list_for_user( $user_id, $this->jobs );

		if ( array() === $items ) {
			return '<div class="ph-card ph-empty-state">'
				. '<h2 class="ph-h4" style="margin: 0;">' . esc_html__( 'No saved jobs yet', 'poolhall-integration' ) . '</h2>'
				. '<p class="ph-body">' . esc_html__( 'Tap the save control on any role and it will be waiting here for you.', 'poolhall-integration' ) . '</p>'
				. '<a class="ph-button ph-button--primary" href="' . esc_url( home_url( '/jobs/' ) ) . '">' . esc_html__( 'Browse live jobs', 'poolhall-integration' ) . '</a>'
				. '</div>';
		}

		$rows = '';
		foreach ( array_slice( $items, 0, 3 ) as $item ) {
			$post_id = $item['post_id'];
			$title   = ( null !== $post_id ) ? get_the_title( $post_id ) : __( 'This role is no longer listed', 'poolhall-integration' );
			$link    = ( null !== $post_id && 'live' === $item['status'] ) ? (string) get_permalink( $post_id ) : '';
			$status  = 'live' === $item['status']
				? ''
				: '<span class="ph-badge ph-badge--warning">' . esc_html__( 'No longer live', 'poolhall-integration' ) . '</span>';
			$name    = '' === $link
				? '<span class="ph-body"><strong>' . esc_html( $title ) . '</strong></span>'
				: '<a class="ph-body ph-link" href="' . esc_url( $link ) . '"><strong>' . esc_html( $title ) . '</strong></a>';
			$rows   .= '<li class="ph-session">' . $name . $status . '</li>';
		}

		return '<div class="ph-card"><div class="ph-stack-sm">'
			. '<div class="ph-portal__panel-head"><h2 class="ph-h4" style="margin: 0;">' . esc_html__( 'Recently saved', 'poolhall-integration' ) . '</h2>'
			. '<a class="ph-link" href="' . esc_url( home_url( '/jobs/' ) ) . '">' . esc_html__( 'All jobs', 'poolhall-integration' ) . '</a></div>'
			. '<ul class="ph-stack-sm" style="list-style: none; margin: 0; padding: 0;">' . $rows . '</ul>'
			. '</div></div>';
	}

	/** Portal spec §5 security page: password, email and session controls. */
	private function security_page(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user_id = get_current_user_id();

		$out = '<header class="ph-portal__head"><div class="ph-stack-xs">'
			. '<p class="ph-eyebrow" style="color: var(--ph-color-orange-700);">' . esc_html__( 'Your account', 'poolhall-integration' ) . '</p>'
			. '<h1 class="ph-h2" style="color: var(--ph-color-navy-700); margin: 0;">' . esc_html__( 'Sign-in & security', 'poolhall-integration' ) . '</h1>'
			. '<p class="ph-lede" style="margin: 0;">' . esc_html__( 'Change your password or email address and see where you are signed in.', 'poolhall-integration' ) . '</p>'
			. '</div></header>';

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
			'email_pending'    => $this->alert( 'success', __( 'If that address can be used, a confirmation link is on its way to it. The change happens when you follow the link, which works once and expires in 24 hours.', 'poolhall-integration' ) ),
			'session_revoked'  => $this->alert( 'success', __( 'That device has been signed out.', 'poolhall-integration' ) ),
			'sessions_revoked' => $this->alert( 'success', __( 'Every other device has been signed out.', 'poolhall-integration' ) ),
			default            => '',
		};
		if ( 'rate_limited' === $this->query_arg( 'error' ) ) {
			$out .= $this->alert( 'error', __( 'Too many password attempts. Wait a few minutes, then try again.', 'poolhall-integration' ) );
		}

		return $this->portal_shell(
			$out
			. $this->change_password_card( array_filter( explode( ',', $this->query_arg( 'pw' ) ) ) )
			. $this->change_email_card( $user_id, array_filter( explode( ',', $this->query_arg( 'em' ) ) ) )
			. $this->sessions_card( $user_id )
			. '<p class="ph-body"><a class="ph-link" href="' . esc_url( home_url( PortalGuard::PATH_PREFIX ) ) . '">' . esc_html__( 'Back to your account', 'poolhall-integration' ) . '</a></p>'
		);
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
				? '<span class="ph-badge ph-badge--success">' . esc_html__( 'Current', 'poolhall-integration' ) . '</span>'
				: '<form method="post" action="' . $this->action_url() . '" style="margin: 0;">'
					. $this->common_fields( 'poolhall_revoke_session' )
					. '<input type="hidden" name="session" value="' . esc_attr( $session['verifier'] ) . '" />'
					. '<button class="ph-button ph-button--ghost" type="submit">' . esc_html__( 'Sign out', 'poolhall-integration' ) . '</button>'
					. '</form>';

			$rows .= '<li class="ph-session">'
				. '<span class="ph-session__icon" aria-hidden="true">' . $this->icon_device() . '</span>'
				. '<div class="ph-session__meta"><p class="ph-body" style="margin: 0;"><strong>' . esc_html( $label ) . '</strong></p>'
				. '<p class="ph-small ph-text-muted" style="margin: 0;">' . esc_html( $signed_in ) . ( '' === $session['ip'] ? '' : ' &middot; ' . esc_html( $session['ip'] ) ) . '</p></div>'
				. '<span class="ph-session__action">' . $action . '</span></li>';
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
	/** An optional (not HTML-required) text input, no validation highlighting. */
	private function optional_field( string $name, string $label, string $type, string $autocomplete, string $placeholder = '' ): string {
		$id = 'ph-auth-' . $name;
		return '<div class="ph-field">'
			. '<label class="ph-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<input class="ph-field__control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" autocomplete="' . esc_attr( $autocomplete ) . '"'
			. ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . ' />'
			. '</div>';
	}

	/** An optional multi-line input for the candidate's summary. */
	private function optional_textarea( string $name, string $label, string $placeholder = '' ): string {
		$id = 'ph-auth-' . $name;
		return '<div class="ph-field">'
			. '<label class="ph-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<textarea class="ph-field__control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3"'
			. ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' ) . '></textarea>'
			. '</div>';
	}

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

	private function alert( string $kind, string $message ): string {
		return '<div class="ph-alert ph-alert--' . esc_attr( $kind ) . '" role="status"><p class="ph-body" style="margin: 0;">' . esc_html( $message ) . '</p></div>';
	}

	private function icon_bookmark(): string {
		return '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
	}

	private function icon_bell(): string {
		return '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>';
	}

	private function icon_doc(): string {
		return '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>';
	}

	private function icon_device(): string {
		return '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
	}

	private function action_url(): string {
		return esc_url( admin_url( 'admin-post.php' ) );
	}

	/** Read-only rendering hints; no state changes happen at render time. */
	private function query_arg( string $name ): string {
		return isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
