<?php
/**
 * Phase 3: create the global shell — core pages, menus, and Elementor Pro
 * Theme Builder header/footer templates with site-wide display conditions.
 *
 *   wp eval-file scripts/dev/create-theme-shell.php
 *
 * Idempotent: existing items are found by slug/title and updated, not
 * duplicated. Navigation links are real URLs, never prototype '#' links
 * (hard rule 7). This script is the deployment artifact for staging.
 *
 * No strict_types: wp eval-file runs through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-theme-shell.php\n";
	exit( 1 );
}
if ( ! did_action( 'elementor/loaded' ) || ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
	echo "FAIL: Elementor + Elementor Pro must be active.\n";
	exit( 1 );
}

// ---------------------------------------------------------------- pages ----
$pages = array(
	'jobs'               => 'Find a Job',
	'employers'          => 'Employers',
	'services'           => 'Services',
	'sectors'            => 'Sectors',
	'better-job-adverts' => 'Better Job Adverts',
	'team'               => 'Meet the Team',
	'join-our-team'      => 'Join Our Team',
	'contact'            => 'Contact',
);

$page_ids = array();
foreach ( $pages as $slug => $title ) {
	$existing = get_page_by_path( $slug );
	if ( $existing instanceof WP_Post ) {
		$page_ids[ $slug ] = $existing->ID;
		continue;
	}
	$page_ids[ $slug ] = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_name'   => $slug,
		)
	);
}
echo 'Pages: ' . count( $page_ids ) . " present.\n";

// Style guide (design system §26.4): server-rendered from the child-theme
// token contract via the plugin shortcode; noindexed by the StyleGuide class.
$style_guide = get_page_by_path( 'style-guide' );
if ( ! $style_guide instanceof WP_Post ) {
	$style_guide_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Style Guide',
			'post_name'    => 'style-guide',
			'post_content' => '[poolhall_style_guide]',
		)
	);
} else {
	$style_guide_id = $style_guide->ID;
}
echo "Style guide: page #{$style_guide_id} (noindex via plugin).\n";

// ---------------------------------------------------------------- menus ----
$ensure_menu = static function ( string $name, array $slugs ) use ( $page_ids ): int {
	$menu = wp_get_nav_menu_object( $name );
	$id   = $menu instanceof WP_Term ? (int) $menu->term_id : (int) wp_create_nav_menu( $name );

	$existing_titles = array_map(
		static fn( $item ): string => (string) $item->title,
		wp_get_nav_menu_items( $id ) ?: array()
	);
	foreach ( $slugs as $slug ) {
		$page_id = $page_ids[ $slug ] ?? 0;
		if ( 0 === $page_id || in_array( get_the_title( $page_id ), $existing_titles, true ) ) {
			continue;
		}
		wp_update_nav_menu_item(
			$id,
			0,
			array(
				'menu-item-object-id' => $page_id,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
	}
	return $id;
};

$primary_menu = $ensure_menu( 'Primary', array( 'jobs', 'employers', 'sectors', 'team', 'contact' ) );
$footer_menu  = $ensure_menu( 'Footer', array( 'services', 'better-job-adverts', 'join-our-team', 'contact' ) );
echo "Menus: Primary #{$primary_menu}, Footer #{$footer_menu}.\n";

// ------------------------------------------------------------ templates ----
$eid = static fn(): string => substr( md5( uniqid( '', true ) ), 0, 7 );

$container = static fn( array $settings, array $children ): array => array(
	'id'       => $eid(),
	'elType'   => 'container',
	'settings' => $settings,
	'elements' => $children,
	'isInner'  => false,
);
$widget    = static fn( string $type, array $settings ): array => array(
	'id'         => $eid(),
	'elType'     => 'widget',
	'widgetType' => $type,
	'settings'   => $settings,
	'elements'   => array(),
);

$header_data = array(
	// Contact strip (design system §8): Navy 900, phone/email left, location
	// right. Below 640px the child-theme .ph-contact-strip rules hide the
	// location; links keep hover underline and visible focus.
	$container(
		array(
			'content_width'         => 'boxed',
			'boxed_width'           => array(
				'unit' => 'px',
				'size' => 1152,
			),
			'flex_direction'        => 'row',
			'flex_align_items'      => 'center',
			'flex_justify_content'  => 'space-between',
			'flex_gap'              => array(
				'unit'   => 'px',
				'size'   => 16,
				'column' => '16',
				'row'    => '16',
			),
			'background_background' => 'classic',
			'background_color'      => '#0F1D33',
			'padding'               => array(
				'unit'     => 'px',
				'top'      => '8',
				'right'    => '24',
				'bottom'   => '8',
				'left'     => '24',
				'isLinked' => false,
			),
			'css_classes'           => 'ph-contact-strip',
		),
		array(
			$widget(
				'text-editor',
				array(
					'editor'     => '<p><a href="tel:01215163000">0121 516 3000</a> &nbsp;·&nbsp; <a href="mailto:jobs@poolhallrecruitment.co.uk">jobs@poolhallrecruitment.co.uk</a></p>',
					'text_color' => '#FFFFFF',
				)
			),
			$widget(
				'text-editor',
				array(
					'editor'       => '<p class="ph-contact-strip__location">Birmingham · West Midlands</p>',
					'text_color'   => '#FFFFFF',
				)
			),
		)
	),
	$container(
		array(
			'content_width'    => 'boxed',
			'boxed_width'      => array(
				'unit' => 'px',
				'size' => 1152,
			),
			'flex_direction'      => 'row',
			'flex_align_items'    => 'center',
			'flex_justify_content' => 'space-between',
			'flex_gap'            => array(
				'unit'   => 'px',
				'size'   => 24,
				'column' => '24',
				'row'    => '24',
			),
			'background_background' => 'classic',
			'background_color'      => '#FFFFFF',
			'border_border'         => 'solid',
			'border_width'          => array(
				'unit'     => 'px',
				'top'      => '0',
				'right'    => '0',
				'bottom'   => '1',
				'left'     => '0',
				'isLinked' => false,
			),
			'border_color'          => '#E3E7ED',
			'padding'               => array(
				'unit'     => 'px',
				'top'      => '16',
				'right'    => '24',
				'bottom'   => '16',
				'left'     => '24',
				'isLinked' => false,
			),
		),
		array(
			$widget(
				'theme-site-title',
				array(
					'header_size' => 'p',
					'title_color' => '#1B3052',
					'typography_typography'  => 'custom',
					'typography_font_family' => 'Source Serif 4',
					'typography_font_weight' => '600',
					'typography_font_size'   => array(
						'unit' => 'rem',
						'size' => 1.375,
					),
				)
			),
			$widget(
				'nav-menu',
				array(
					'menu'                   => (string) $primary_menu,
					'layout'                 => 'horizontal',
					'pointer'                => 'underline',
					'menu_typography_typography'  => 'custom',
					'menu_typography_font_family' => 'Hanken Grotesk',
					'menu_typography_font_weight' => '600',
					'menu_typography_font_size'   => array(
						'unit' => 'rem',
						'size' => 1,
					),
					'color_menu_item'        => '#16202F',
					'color_menu_item_hover'  => '#1B3052',
					'pointer_color_menu_item_hover' => '#EC6F1E',
					// Mobile drawer (fixes the prototype's missing mobile nav).
					'dropdown'               => 'tablet',
					'toggle'                 => 'burger',
					'toggle_color'           => '#1B3052',
					'full_width'             => 'stretch',
					'text_align'             => 'aside',
				)
			),
			// Audience switch (§8): segmented Candidates/Employers control,
			// desktop only — the child-theme .ph-audience-switch rules hide
			// it below 900px (it moves into the mobile drawer in Phase 4).
			$widget(
				'text-editor',
				array(
					'editor' => '<p class="ph-audience-switch"><a class="is-active" href="' . esc_url( get_permalink( $page_ids['jobs'] ) ) . '">Candidates</a><a href="' . esc_url( get_permalink( $page_ids['employers'] ) ) . '">Employers</a></p>',
				)
			),
			$widget(
				'button',
				array(
					'text'                  => 'Browse live jobs',
					'link'                  => array(
						'url'         => get_permalink( $page_ids['jobs'] ),
						'is_external' => '',
						'nofollow'    => '',
					),
					'background_color'      => '#EC6F1E',
					'button_background_hover_color' => '#D45F12',
					'button_text_color'     => '#FFFFFF',
					'hover_color'           => '#FFFFFF',
					'border_radius'         => array(
						'unit'     => 'px',
						'top'      => '10',
						'right'    => '10',
						'bottom'   => '10',
						'left'     => '10',
						'isLinked' => true,
					),
					'typography_typography'  => 'custom',
					'typography_font_family' => 'Hanken Grotesk',
					'typography_font_weight' => '700',
				)
			),
		)
	),
);

$year        = gmdate( 'Y' );
$footer_data = array(
	$container(
		array(
			'content_width'         => 'boxed',
			'boxed_width'           => array(
				'unit' => 'px',
				'size' => 1152,
			),
			'flex_direction'        => 'column',
			'flex_gap'              => array(
				'unit'   => 'px',
				'size'   => 32,
				'column' => '32',
				'row'    => '32',
			),
			'background_background' => 'classic',
			'background_color'      => '#0B1626',
			'padding'               => array(
				'unit'     => 'px',
				'top'      => '64',
				'right'    => '24',
				'bottom'   => '40',
				'left'     => '24',
				'isLinked' => false,
			),
		),
		array(
			$widget(
				'heading',
				array(
					'title'       => 'Poolhall Recruitment',
					'header_size' => 'h2',
					'title_color' => '#FFFFFF',
					'typography_typography'  => 'custom',
					'typography_font_family' => 'Source Serif 4',
					'typography_font_weight' => '600',
					'typography_font_size'   => array(
						'unit' => 'rem',
						'size' => 1.5,
					),
				)
			),
			$widget(
				'text-editor',
				array(
					'editor'     => '<p>Independent West Midlands recruitment across Construction, Manufacturing and Digital/Marketing.</p>',
					'text_color' => '#A7B4C8',
				)
			),
			$widget(
				'nav-menu',
				array(
					'menu'                  => (string) $footer_menu,
					'layout'                => 'horizontal',
					'pointer'               => 'none',
					'color_menu_item'       => '#EEF2F8',
					'color_menu_item_hover' => '#F4904A',
					'dropdown'              => 'none',
				)
			),
			$widget(
				'text-editor',
				array(
					'editor'     => '<p>© ' . $year . ' Poolhall Recruitment Ltd. All rights reserved.</p>',
					'text_color' => '#6B7686',
				)
			),
		)
	),
);

$ensure_template = static function ( string $title, string $type, array $data ): int {
	$existing = get_posts(
		array(
			'post_type'      => 'elementor_library',
			'title'          => $title,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	$post_id  = $existing ? (int) $existing[0] : wp_insert_post(
		array(
			'post_type'   => 'elementor_library',
			'post_status' => 'publish',
			'post_title'  => $title,
		)
	);

	// Backup before modifying Elementor data (hard rule 11).
	$prior = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! empty( $prior ) ) {
		update_option( 'poolhall_tpl_backup_' . $post_id . '_' . gmdate( 'Ymd_His' ), $prior, false );
	}

	update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $post_id, '_elementor_template_type', $type );
	update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $post_id, '_elementor_conditions', array( 'include/general' ) );
	wp_set_object_terms( $post_id, $type, 'elementor_library_type' );

	return $post_id;
};

$header_id = $ensure_template( 'PH Global Header', 'header', $header_data );
$footer_id = $ensure_template( 'PH Global Footer', 'footer', $footer_data );

// Rebuild Pro's conditions cache so the templates actually display.
if ( class_exists( '\ElementorPro\Plugin' ) ) {
	$theme_builder = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
	if ( $theme_builder && method_exists( $theme_builder->get_conditions_manager(), 'get_cache' ) ) {
		$theme_builder->get_conditions_manager()->get_cache()->regenerate();
	}
}

\Elementor\Plugin::instance()->files_manager->clear_cache();

echo "Templates: header #{$header_id}, footer #{$footer_id} (conditions: site-wide).\n";
echo "OK: theme shell created. Verify the frontend before treating this as done.\n";
