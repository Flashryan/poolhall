<?php
/**
 * Candidate portal page tree.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Creates (or updates) the `/candidate/` page tree carrying the
 * server-rendered auth shortcodes (portal spec §2). Idempotent: pages are
 * matched by path and updated in place, so re-running never duplicates.
 * Shared by the wp-cli dev script and the admin Site-setup action — hosts
 * without shell access (managed staging) run the same code path. Page IDs
 * are recorded in the `poolhall_portal_page_ids` option.
 */
final class PortalPages {

	public const OPTION = 'poolhall_portal_page_ids';

	private const SPECS = array(
		'candidate'       => array(
			'title'   => 'Your account',
			'content' => '[poolhall_candidate_auth form="account"]',
			'parent'  => null,
		),
		'login'           => array(
			'title'   => 'Sign in',
			'content' => '[poolhall_candidate_auth form="login"]',
			'parent'  => 'candidate',
		),
		'register'        => array(
			'title'   => 'Create your account',
			'content' => '[poolhall_candidate_auth form="register"]',
			'parent'  => 'candidate',
		),
		'verify-email'    => array(
			'title'   => 'Confirm your email',
			'content' => '[poolhall_candidate_auth form="verify"]',
			'parent'  => 'candidate',
		),
		'forgot-password' => array(
			'title'   => 'Reset your password',
			'content' => '[poolhall_candidate_auth form="forgot"]',
			'parent'  => 'candidate',
		),
		'reset-password'  => array(
			'title'   => 'Choose a new password',
			'content' => '[poolhall_candidate_auth form="reset"]',
			'parent'  => 'candidate',
		),
		'security'        => array(
			'title'   => 'Sign-in & security',
			'content' => '[poolhall_candidate_auth form="security"]',
			'parent'  => 'candidate',
		),
	);

	/**
	 * Create or update the whole tree, parents before children.
	 *
	 * @return array<string,int> Slug => page ID, in creation order.
	 * @throws \RuntimeException When WordPress refuses an insert/update.
	 */
	public static function ensure(): array {
		$ids = array();

		foreach ( self::SPECS as $slug => $spec ) {
			$parent_id = null === $spec['parent'] ? 0 : (int) ( $ids[ $spec['parent'] ] ?? 0 );
			$existing  = get_page_by_path( self::path( $slug ) );

			$args = array(
				'post_title'   => $spec['title'],
				'post_name'    => $slug,
				'post_content' => $spec['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => $parent_id,
				// Full-width canvas with the theme-builder header/footer (same
				// template the marketing pages use), so the auth card's paper
				// background and the portal column own the full content width
				// instead of the theme's narrow default container.
				'meta_input'   => array( '_wp_page_template' => 'elementor_header_footer' ),
			);

			if ( $existing instanceof \WP_Post ) {
				$args['ID'] = $existing->ID;
				$page_id    = wp_update_post( $args );
			} else {
				$page_id = wp_insert_post( $args );
			}

			if ( is_wp_error( $page_id ) || 0 === $page_id ) {
				throw new \RuntimeException( 'Could not create portal page /' . self::path( $slug ) . '/' );
			}
			$ids[ $slug ] = (int) $page_id;
		}

		update_option( self::OPTION, $ids, false );
		flush_rewrite_rules();

		return $ids;
	}

	/** Site-relative path (no slashes) for a portal slug, e.g. `candidate/login`. */
	public static function path( string $slug ): string {
		$parent = self::SPECS[ $slug ]['parent'] ?? null;
		return null === $parent ? $slug : $parent . '/' . $slug;
	}
}
