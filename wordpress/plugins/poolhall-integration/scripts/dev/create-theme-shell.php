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
	// v2 secondary pages (docs/12 §7.5/§7.7/§7.9).
	'delivery-options'   => 'Delivery Options',
	'why-us'             => 'Why Us',
	'bespoke-search'     => 'Bespoke Search',
	'hr-services'        => 'HR Services',
	'commitment'         => 'Our Commitment',
	'candidates'         => 'For Candidates',
	// v2 nav pages (prototype ui.jsx nav model).
	'about'              => 'About Us',
	'blog'               => 'Blog',
	'registration-guide' => 'Registration Guide',
	'interview-tips'     => 'Interview Tips',
	'cv-tips'            => 'CV Tips',
	// Legal (docs/12 §7.9). Concise notices; full wording confirmed with the client.
	'privacy-policy'     => 'Privacy Policy',
	'terms'              => 'Terms of Use',
	'cookies'            => 'Cookie Policy',
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

// Sector detail pages live under /sectors/ (prototype SECTOR_MENU).
foreach ( array(
	'construction'  => 'Construction Recruitment',
	'manufacturing' => 'Manufacturing Recruitment',
	'digital'       => 'Digital Recruitment',
) as $poolhall_sector_slug => $poolhall_sector_title ) {
	$existing = get_page_by_path( 'sectors/' . $poolhall_sector_slug );
	if ( $existing instanceof WP_Post ) {
		$page_ids[ 'sectors/' . $poolhall_sector_slug ] = $existing->ID;
		continue;
	}
	$page_ids[ 'sectors/' . $poolhall_sector_slug ] = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $poolhall_sector_title,
			'post_name'   => $poolhall_sector_slug,
			'post_parent' => $page_ids['sectors'],
		)
	);
}
echo "Sector pages: construction, manufacturing, digital present.\n";

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

$primary_menu = $ensure_menu( 'Primary', array( 'jobs', 'employers', 'candidates', 'sectors', 'team', 'contact' ) );
$footer_menu  = $ensure_menu( 'Footer', array( 'services', 'delivery-options', 'better-job-adverts', 'why-us', 'bespoke-search', 'hr-services', 'commitment', 'join-our-team', 'contact' ) );
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

// v2 chrome: the header and footer are server-rendered by the plugin
// (V2Fragments: [poolhall_v2_header] / [poolhall_v2_footer]) so the frontend
// matches the prototype's ui.jsx Header/Footer exactly, dropdown nav and
// mobile drawer included. The Theme Builder templates below just mount them
// site-wide through Elementor Pro's conditions machinery.
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

$header_data = array(
	$container(
		array(
			'content_width' => 'full',
			'padding'       => array(
				'unit'     => 'px',
				'top'      => '0',
				'right'    => '0',
				'bottom'   => '0',
				'left'     => '0',
				'isLinked' => true,
			),
		),
		array(
			$widget( 'shortcode', array( 'shortcode' => '[poolhall_v2_header]' ) ),
		)
	),
);

$footer_data = array(
	$container(
		array(
			'content_width' => 'full',
			'padding'       => array(
				'unit'     => 'px',
				'top'      => '0',
				'right'    => '0',
				'bottom'   => '0',
				'left'     => '0',
				'isLinked' => true,
			),
		),
		array(
			$widget( 'shortcode', array( 'shortcode' => '[poolhall_v2_footer]' ) ),
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
