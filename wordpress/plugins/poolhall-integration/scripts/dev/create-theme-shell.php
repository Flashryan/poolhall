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
// Dynamic tag value for a widget setting (Elementor's serialized tag format).
$tag = static fn( string $name, array $settings = array() ): string => sprintf(
	'[elementor-tag id="%s" name="%s" settings="%s"]',
	$eid(),
	$name,
	rawurlencode( wp_json_encode( (object) $settings ) )
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
					// Explicit dynamic tags + static fallbacks: the widget's
					// own dynamic defaults only apply through the editor, not
					// programmatic writes — without these the logo rendered
					// the heading placeholder with no link at all.
					'title'       => get_bloginfo( 'name' ),
					'link'        => array(
						'url'         => home_url( '/' ),
						'is_external' => '',
						'nofollow'    => '',
					),
					'__dynamic__' => array(
						'title' => $tag( 'site-title' ),
						'link'  => $tag( 'site-url' ),
					),
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
			// server-rendered per page (active side + animated pill) by the
			// plugin shortcode; desktop only — the child-theme rules hide it
			// below 900px (it moves into the mobile drawer in Phase 4).
			$widget( 'shortcode', array( 'shortcode' => '[poolhall_audience_switch]' ) ),
			// Candidate account entry (§8): Sign in / My account by state.
			$widget( 'shortcode', array( 'shortcode' => '[poolhall_account_link]' ) ),
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

$year = gmdate( 'Y' );

// Footer images: brand logo + TEAM/BNI accreditation marks (bundled in the
// plugin; keyed sideload identical to the team/home scripts).
$poolhall_import_image = static function ( string $filename, string $alt ): int {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'poolhall_content_image', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single keyed lookup at setup time.
			'meta_value'     => $filename, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( array() !== $existing ) {
		return (int) $existing[0];
	}
	$source = POOLHALL_INTEGRATION_DIR . 'assets/img/content/' . $filename;
	if ( ! file_exists( $source ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$tmp = wp_tempnam( $filename );
	copy( $source, $tmp );
	$attachment_id = media_handle_sideload(
		array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		),
		0,
		null,
		array( 'post_excerpt' => '' )
	);
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temp cleanup.
		return 0;
	}
	update_post_meta( $attachment_id, 'poolhall_content_image', $filename );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	return (int) $attachment_id;
};

$logo_id  = $poolhall_import_image( 'poolhall-logo.png', 'Poolhall Recruitment' );
$team_id  = $poolhall_import_image( 'accred-team.png', 'TEAM member' );
$bni_id   = $poolhall_import_image( 'accred-bni.png', 'BNI member' );
$logo_url = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
$team_url = $team_id ? (string) wp_get_attachment_image_url( $team_id, 'full' ) : '';
$bni_url  = $bni_id ? (string) wp_get_attachment_image_url( $bni_id, 'full' ) : '';

// Footer links: real, published pages only (hard rule 7 — the prototype's
// '#' socials/About/Career-advice links are omitted until real URLs exist;
// Privacy policy appears as soon as the page is published and Site setup
// re-runs).
$poolhall_footer_link = static function ( string $slug, string $label ): string {
	$page = get_page_by_path( $slug );
	if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
		return '';
	}
	return '<li><a href="' . esc_url( (string) get_permalink( $page ) ) . '">' . esc_html( $label ) . '</a></li>';
};

$footer_columns = '<div class="ph-footer-grid">'
	. '<div class="ph-footer-col ph-footer-col--brand">'
	. ( '' !== $logo_url ? '<p class="ph-footer-brand"><img src="' . esc_url( $logo_url ) . '" alt="" width="52" height="52" /><span>Poolhall</span></p>' : '<p class="ph-footer-brand"><span>Poolhall</span></p>' )
	. '<p class="ph-footer-about">Independent recruitment across Construction, Manufacturing and Marketing. Quality and ethical solutions since 2021.</p>'
	. '</div>'
	. '<div class="ph-footer-col"><p class="ph-footer-head">Candidates</p><ul>'
	. $poolhall_footer_link( 'jobs', 'Find a job' )
	. $poolhall_footer_link( 'sectors', 'Browse sectors' )
	. $poolhall_footer_link( 'candidate/register', 'Register your CV' )
	. $poolhall_footer_link( 'candidate/login', 'Sign in' )
	. '</ul></div>'
	. '<div class="ph-footer-col"><p class="ph-footer-head">Employers</p><ul>'
	. $poolhall_footer_link( 'employers', 'Hire talent' )
	. $poolhall_footer_link( 'services', 'Our services' )
	. $poolhall_footer_link( 'better-job-adverts', 'Better Job Adverts' )
	. $poolhall_footer_link( 'join-our-team', 'Partner with us' )
	. '</ul></div>'
	. '<div class="ph-footer-col"><p class="ph-footer-head">Company</p><ul>'
	. $poolhall_footer_link( 'team', 'Meet the team' )
	. $poolhall_footer_link( 'join-our-team', 'Join our team' )
	. $poolhall_footer_link( 'contact', 'Contact' )
	. $poolhall_footer_link( 'privacy-policy', 'Privacy policy' )
	. '</ul></div>'
	. '</div>';

$footer_accred = '<div class="ph-footer-accred">'
	. '<span class="ph-footer-accred__label">Proud members of</span>'
	. '<span class="ph-footer-accred__logos">'
	. ( '' !== $team_url ? '<span class="ph-footer-chip"><img src="' . esc_url( $team_url ) . '" alt="TEAM member" height="26" /></span>' : '' )
	. ( '' !== $bni_url ? '<span class="ph-footer-chip"><img src="' . esc_url( $bni_url ) . '" alt="BNI member" height="26" /></span>' : '' )
	. '</span></div>';

$footer_bottom = '<div class="ph-footer-bottom">'
	. '<span>&copy; ' . esc_html( $year ) . ' Poolhall Recruitment Limited &middot; Company No. 13319338 &middot; VAT 383617377</span>'
	. '<span>Grosvenor House, 11 St Pauls Square, Birmingham, B3 1RB</span>'
	. '</div>';

// Footer (directive §2.1): navy-900, 4-column grid, accreditation strip,
// company/VAT/address bottom bar. Social icon buttons are deliberately
// absent until real profile URLs exist (hard rule 7).
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
				'size'   => 0,
				'column' => '0',
				'row'    => '0',
			),
			'background_background' => 'classic',
			'background_color'      => '#0F1D33',
			'padding'               => array(
				'unit'     => 'px',
				'top'      => '64',
				'right'    => '24',
				'bottom'   => '28',
				'left'     => '24',
				'isLinked' => false,
			),
			'css_classes'           => 'ph-footer',
		),
		array(
			$widget( 'text-editor', array( 'editor' => $footer_columns ) ),
			$widget( 'text-editor', array( 'editor' => $footer_accred ) ),
			$widget( 'text-editor', array( 'editor' => $footer_bottom ) ),
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
