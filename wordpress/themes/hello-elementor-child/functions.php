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

define( 'POOLHALL_CHILD_VERSION', '0.25.31' );

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

/** Whether the current request is one of the isolated v3 Elementor pilots. */
function poolhall_is_design_v3_page(): bool {
	return is_singular( 'page' )
		&& 'v3' === (string) get_post_meta( get_queried_object_id(), '_poolhall_design_version', true );
}

/** Load the new system only on its pilot/reference pages. */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! poolhall_is_design_v3_page() ) {
			return;
		}

		wp_enqueue_style(
			'poolhall-manrope',
			'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'poolhall-design-system-v3',
			get_stylesheet_directory_uri() . '/assets/css/design-system-v3.css',
			array( 'hello-elementor-child', 'poolhall-manrope' ),
			POOLHALL_CHILD_VERSION
		);

		if ( 'construction' === (string) get_post_meta( get_queried_object_id(), '_poolhall_design_pilot', true ) ) {
			wp_enqueue_script(
				'poolhall-construction-pilot',
				get_stylesheet_directory_uri() . '/assets/js/construction-pilot.js',
				array(),
				POOLHALL_CHILD_VERSION,
				array( 'strategy' => 'defer' )
			);
		}
	},
	30
);

/** Stable body hooks keep the transparent header and pilot CSS page-scoped. */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( ! poolhall_is_design_v3_page() ) {
			return $classes;
		}
		$classes[] = 'ph-design-v3';
		if ( 'construction' === (string) get_post_meta( get_queried_object_id(), '_poolhall_design_pilot', true ) ) {
			$classes[] = 'ph-construction-pilot';
		}
		return $classes;
	}
);

/**
 * Floating header state, printed early in <head> so both classes exist before
 * the first paint. html.ph-hdr-js opts the page into the fixed, transparent
 * header in shared.css; html.ph-hdr-scrolled switches it to the compact white
 * bar once the page moves. Kept out of ui.js, which is deferred and would let
 * the old header show for a frame. Skipped on the v3 pilots, which run their
 * own header, and in the Elementor editor preview, where a fixed bar would sit
 * over the canvas.
 */
add_action(
	'wp_head',
	static function (): void {
		if ( poolhall_is_design_v3_page() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check.
			return;
		}
		echo "<script>(function(h){h.classList.add('ph-hdr-js');var on=false;function s(){var n=(window.pageYOffset||h.scrollTop||0)>32;if(n!==on){on=n;h.classList.toggle('ph-hdr-scrolled',n);}}s();window.addEventListener('scroll',s,{passive:true});window.addEventListener('pageshow',s);})(document.documentElement);</script>\n";
	},
	1
);

/** Pilot/reference pages are intentionally public-but-unlisted and noindexed. */
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( poolhall_is_design_v3_page() ) {
			$robots['noindex'] = true;
			// Respect a staging-wide nofollow policy instead of emitting the
			// contradictory "nofollow, follow" pair. On production the pilots
			// remain noindex,follow until one replaces a canonical page.
			if ( isset( $robots['nofollow'] ) ) {
				unset( $robots['follow'] );
			} else {
				$robots['follow'] = true;
			}
		}
		return $robots;
	}
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

/**
 * Elementor widgets for the plugin's dynamic fragments.
 *
 * Registered from inside the Elementor hooks on purpose:
 * inc/elementor-widgets.php declares classes extending Elementor Widget_Base,
 * which do not exist at theme-load time. Requiring it directly here fatals
 * the entire site.
 */
add_action(
	'elementor/elements/categories_registered',
	static function ( $mgr ) {
		$mgr->add_category( 'poolhall', array( 'title' => 'Poolhall', 'icon' => 'eicon-nerd' ) );
	}
);

add_action(
	'elementor/widgets/register',
	static function ( $mgr ) {
		require_once get_stylesheet_directory() . '/inc/elementor-widgets.php';
		if ( function_exists( 'poolhall_register_elementor_widgets' ) ) {
			poolhall_register_elementor_widgets( $mgr );
		}
	}
);
