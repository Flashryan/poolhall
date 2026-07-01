<?php
/**
 * Candidate portal route protection.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Portal spec §2 for every `/candidate/…` request: private routes redirect
 * signed-out visitors to login with a same-origin return URL and unverified
 * candidates to the verification-required screen; authenticated verified
 * candidates skip the auth pages; all portal routes are noindexed, absent
 * from sitemaps and sent with no-cache headers so they can never sit in a
 * shared cache. Logged-in staff may view the page shells (widgets only ever
 * query the current user's records), but candidates remain the audience.
 */
final class PortalGuard {

	public const PATH_PREFIX = '/candidate/';
	public const LOGIN_PATH  = '/candidate/login/';
	public const VERIFY_PATH = '/candidate/verify-email/';

	private const PUBLIC_SLUGS = array( 'login', 'register', 'verify-email', 'forgot-password', 'reset-password' );

	public function __construct(
		private CandidateRepository $candidates,
		private ReturnUrlPolicy $returns,
	) {}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'guard' ), 1 );
		add_filter( 'wp_robots', array( $this, 'noindex' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_from_sitemap' ), 10, 2 );
	}

	public function guard(): void {
		$path = $this->request_path();
		if ( ! $this->is_portal_path( $path ) ) {
			return;
		}

		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}

		$user_id = get_current_user_id();

		if ( $this->is_public_route( $path ) ) {
			// Signed-in verified candidates have no business on auth pages.
			if ( $user_id > 0 && CandidateRole::is_candidate( $user_id ) && $this->candidates->is_verified( $user_id ) ) {
				$requested = isset( $_GET['return'] ) ? sanitize_text_field( wp_unslash( $_GET['return'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect hint, allowlisted below.
				$this->redirect( $this->returns->sanitize( $requested ) );
			}
			return;
		}

		if ( 0 === $user_id ) {
			// Keep the query string: emailed links (e.g. the security page's
			// email-change confirmation token) survive the sign-in round trip.
			$this->redirect( self::LOGIN_PATH . '?return=' . rawurlencode( $path . $this->request_query() ) );
		}
		if ( CandidateRole::is_candidate( $user_id ) && ! $this->candidates->is_verified( $user_id ) ) {
			$this->redirect( self::VERIFY_PATH . '?state=required' );
		}
	}

	/**
	 * @param array<string,bool> $robots Robots directives.
	 * @return array<string,bool>
	 */
	public function noindex( array $robots ): array {
		if ( $this->is_portal_path( $this->request_path() ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * Drop the portal page tree from the pages sitemap.
	 *
	 * @param array<string,mixed> $args WP_Query arguments.
	 * @return array<string,mixed>
	 */
	public function exclude_from_sitemap( array $args, string $post_type ): array {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		$root = get_page_by_path( 'candidate' );
		if ( ! $root instanceof \WP_Post ) {
			return $args;
		}
		$args['post__not_in']   = (array) ( $args['post__not_in'] ?? array() );
		$args['post__not_in'][] = $root->ID;
		foreach ( (array) get_pages( array( 'child_of' => $root->ID ) ) as $child ) {
			$args['post__not_in'][] = (int) $child->ID;
		}
		return $args;
	}

	private function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) ( wp_parse_url( $uri, PHP_URL_PATH ) ?? '' );
		return trailingslashit( $path );
	}

	/** '?…' when the request carried a query string, '' otherwise. */
	private function request_query(): string {
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$query = (string) ( wp_parse_url( $uri, PHP_URL_QUERY ) ?? '' );
		return '' === $query ? '' : '?' . $query;
	}

	private function is_portal_path( string $path ): bool {
		return str_starts_with( $path, self::PATH_PREFIX );
	}

	private function is_public_route( string $path ): bool {
		$slug = explode( '/', substr( $path, strlen( self::PATH_PREFIX ) ) )[0];
		return in_array( $slug, self::PUBLIC_SLUGS, true );
	}

	/** @param string $destination Site-relative destination. */
	private function redirect( string $destination ): never {
		wp_safe_redirect( home_url( $destination ) );
		exit;
	}
}
