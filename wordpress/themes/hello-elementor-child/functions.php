<?php
/**
 * Hello Elementor Child — Poolhall Recruitment.
 *
 * Owns: shared CSS custom properties (fallback for Elementor Site Settings),
 * font loading and no-JS fallbacks. Contains no business logic — that belongs
 * to the poolhall-integration plugin.
 *
 * @package HelloElementorChild
 */

defined( 'ABSPATH' ) || exit;

define( 'POOLHALL_CHILD_VERSION', '0.3.0' );

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'hello-elementor-child',
			get_stylesheet_directory_uri() . '/assets/css/shared.css',
			array(),
			POOLHALL_CHILD_VERSION
		);
	},
	20
);

/**
 * Preconnect for Google Fonts until fonts are self-hosted (see design system
 * doc §fonts — self-hosting is the launch target; this is the dev fallback).
 */
add_filter(
	'wp_resource_hints',
	function ( array $urls, string $relation_type ): array {
		if ( 'preconnect' === $relation_type ) {
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	},
	10,
	2
);
