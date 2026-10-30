<?php
/**
 * WordPress's built-in sitemap (/wp-sitemap.xml), tidied: no author pages,
 * no builder templates, and nothing marked "hide from search engines".
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_sitemaps_add_provider', static function ( $provider, string $name ) {
	return ( phseo_active() && 'users' === $name ) ? false : $provider;
}, 10, 2 );

add_filter( 'wp_sitemaps_post_types', static function ( array $types ): array {
	if ( ! phseo_active() ) {
		return $types;
	}
	return array_intersect_key( $types, array_flip( phseo_post_types() ) );
} );

add_filter( 'wp_sitemaps_taxonomies', static fn( array $taxonomies ) => phseo_active() ? array() : $taxonomies );

add_filter( 'wp_sitemaps_posts_query_args', static function ( array $args ): array {
	if ( ! phseo_active() ) {
		return $args;
	}
	$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
	$args['meta_query'][] = array( 'key' => PHSEO_META_NOINDEX, 'compare' => 'NOT EXISTS' );
	return $args;
} );
