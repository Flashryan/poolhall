<?php
/**
 * Candidate portal pages (portal spec §2). Run inside WordPress:
 *
 *   wp eval-file scripts/dev/create-portal-pages.php
 *
 * Idempotent: creates (or updates) the /candidate/ page tree with the
 * server-rendered auth shortcodes. These pages work with plain WordPress —
 * no Elementor Pro required — and stay correct later when Elementor shells
 * replace the editorial framing, because the flows live in the shortcode
 * renderer and the plugin handlers, not the page content. Page IDs are
 * recorded in the `poolhall_portal_page_ids` option.
 *
 * No strict_types declaration: wp eval-file runs this through eval(), where
 * declare() cannot be the first statement.
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-portal-pages.php\n";
	exit( 1 );
}

$poolhall_portal_pages = array(
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

$poolhall_page_ids = array();
foreach ( $poolhall_portal_pages as $slug => $page ) {
	$parent_id = null === $page['parent'] ? 0 : (int) ( $poolhall_page_ids[ $page['parent'] ] ?? 0 );
	$path      = null === $page['parent'] ? $slug : $page['parent'] . '/' . $slug;
	$existing  = get_page_by_path( $path );

	$args = array(
		'post_title'   => $page['title'],
		'post_name'    => $slug,
		'post_content' => $page['content'],
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_parent'  => $parent_id,
	);

	if ( $existing instanceof WP_Post ) {
		$args['ID'] = $existing->ID;
		$page_id    = wp_update_post( $args );
	} else {
		$page_id = wp_insert_post( $args );
	}

	if ( is_wp_error( $page_id ) || 0 === $page_id ) {
		printf( "FAIL: could not create page %s\n", $path );
		exit( 1 );
	}
	$poolhall_page_ids[ $slug ] = (int) $page_id;
	printf( "  /%s/ -> page %d\n", $path, $page_id );
}

update_option( 'poolhall_portal_page_ids', $poolhall_page_ids, false );
flush_rewrite_rules();
printf( "OK: %d portal pages in place.\n", count( $poolhall_page_ids ) );
