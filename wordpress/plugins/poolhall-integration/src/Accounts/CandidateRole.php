<?php
/**
 * Candidate role and access enforcement.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * `poolhall_candidate` (portal spec §3): only the capabilities required for
 * its own frontend records — no admin area, no admin bar, never listed in
 * author archives, user sitemaps or public REST user responses. Candidate
 * identity belongs to WordPress (hard rule 14); Giig credentials are never
 * used for site login.
 */
final class CandidateRole {

	public const ROLE = 'poolhall_candidate';

	/** Idempotent; called from activation and migrations. */
	public static function install(): void {
		if ( null === get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Candidate', 'poolhall-integration' ), array( 'read' => true ) );
		}
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'block_admin_access' ) );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
		add_action( 'template_redirect', array( $this, 'block_author_archive' ) );
		add_filter( 'rest_user_query', array( $this, 'exclude_from_rest_users' ) );
		add_filter( 'wp_sitemaps_users_query_args', array( $this, 'exclude_from_rest_users' ) );
	}

	public static function is_candidate( \WP_User|int $user ): bool {
		$user = $user instanceof \WP_User ? $user : get_user_by( 'id', $user );
		return $user instanceof \WP_User && in_array( self::ROLE, $user->roles, true );
	}

	/** Candidates never see wp-admin; admin-ajax stays available to frontend code. */
	public function block_admin_access(): void {
		if ( wp_doing_ajax() || ! self::is_candidate( get_current_user_id() ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/candidate/' ) );
		exit;
	}

	public function hide_admin_bar( bool $show ): bool {
		return self::is_candidate( get_current_user_id() ) ? false : $show;
	}

	/** Candidate accounts must not resolve to public author archives. */
	public function block_author_archive(): void {
		if ( ! is_author() ) {
			return;
		}
		$author = get_queried_object();
		if ( $author instanceof \WP_User && in_array( self::ROLE, $author->roles, true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Keep candidates out of REST user listings and the users sitemap.
	 *
	 * @param array<string,mixed> $args WP_User_Query arguments.
	 * @return array<string,mixed>
	 */
	public function exclude_from_rest_users( array $args ): array {
		$excluded             = (array) ( $args['role__not_in'] ?? array() );
		$excluded[]           = self::ROLE;
		$args['role__not_in'] = array_values( array_unique( $excluded ) );
		return $args;
	}
}
