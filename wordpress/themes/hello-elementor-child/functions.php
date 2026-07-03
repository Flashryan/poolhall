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

define( 'POOLHALL_CHILD_VERSION', '0.10.7' );

add_action(
	'wp_enqueue_scripts',
	function () {
		// v2 "Engineered" fonts (docs/12 §11.2): Archivo for display/headings,
		// Source Sans 3 for body, IBM Plex Mono for job meta / reference IDs.
		// Loaded explicitly so the families render on every surface (the apply
		// modal, shared.css classes) without depending on Elementor.
		wp_enqueue_style(
			'poolhall-fonts',
			'https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800;900&family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Mono:wght@400;500&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'hello-elementor-child',
			get_stylesheet_directory_uri() . '/assets/css/shared.css',
			array( 'poolhall-fonts' ),
			POOLHALL_CHILD_VERSION
		);
		// Progressive enhancement only: the jobs filters and mobile drawer
		// work without it (GET forms + submit buttons). Deferred so it never
		// blocks render.
		wp_enqueue_script(
			'poolhall-ui',
			get_stylesheet_directory_uri() . '/assets/js/ui.js',
			array(),
			POOLHALL_CHILD_VERSION,
			array( 'strategy' => 'defer' )
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
			$urls[] = 'https://fonts.googleapis.com';
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
