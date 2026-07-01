<?php
/**
 * Server-rendered header controls.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\DesignSystem;

use Poolhall\Integration\Accounts\PortalGuard;

/**
 * Header controls the theme shell cannot compute statically (§8):
 *
 * `[poolhall_audience_switch]` — the segmented Candidates/Employers
 * control. The active side reflects the page being viewed (employer
 * journey pages activate Employers; everything else is candidate-first)
 * and the sliding pill is pure CSS, so it works without JavaScript.
 *
 * `[poolhall_account_link]` — the candidate account entry. Signed-out
 * visitors get “Sign in” to the portal login; signed-in users get
 * “My account” to the dashboard. `PortalGuard` already bounces
 * signed-out visits to `/candidate/` through login with a return URL,
 * so both destinations are safe at any auth state.
 */
final class HeaderControls {

	public const SHORTCODE_AUDIENCE = 'poolhall_audience_switch';
	public const SHORTCODE_ACCOUNT  = 'poolhall_account_link';

	/** Pages that belong to the employer journey (spec 01 §2). */
	private const EMPLOYER_SLUGS = array( 'employers', 'services', 'better-job-adverts' );

	public function register(): void {
		add_shortcode( self::SHORTCODE_AUDIENCE, array( $this, 'render_audience_switch' ) );
		add_shortcode( self::SHORTCODE_ACCOUNT, array( $this, 'render_account_link' ) );
	}

	public function render_audience_switch(): string {
		$employers_page = get_page_by_path( 'employers' );
		if ( ! $employers_page instanceof \WP_Post ) {
			return '';
		}

		$employer_active = is_page( self::EMPLOYER_SLUGS );

		$candidate = '<a href="' . esc_url( home_url( '/' ) ) . '"'
			. ( $employer_active ? '' : ' class="is-active" aria-current="true"' )
			. '>' . esc_html__( 'Candidates', 'poolhall-integration' ) . '</a>';
		$employer  = '<a href="' . esc_url( (string) get_permalink( $employers_page ) ) . '"'
			. ( $employer_active ? ' class="is-active" aria-current="true"' : '' )
			. '>' . esc_html__( 'Employers', 'poolhall-integration' ) . '</a>';

		return '<nav class="ph-audience-switch' . ( $employer_active ? ' ph-audience-switch--employers' : '' ) . '"'
			. ' aria-label="' . esc_attr__( 'Choose audience', 'poolhall-integration' ) . '">'
			. '<span class="ph-audience-switch__pill" aria-hidden="true"></span>'
			. $candidate . $employer
			. '</nav>';
	}

	public function render_account_link(): string {
		$icon = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

		if ( is_user_logged_in() ) {
			return '<a class="ph-account-link" href="' . esc_url( home_url( PortalGuard::PATH_PREFIX ) ) . '">'
				. $icon . esc_html__( 'My account', 'poolhall-integration' ) . '</a>';
		}

		return '<a class="ph-account-link" href="' . esc_url( home_url( PortalGuard::LOGIN_PATH ) ) . '">'
			. $icon . esc_html__( 'Sign in', 'poolhall-integration' ) . '</a>';
	}
}
