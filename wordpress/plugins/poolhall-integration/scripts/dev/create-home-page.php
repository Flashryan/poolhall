<?php
/**
 * Build the Home page to the v2 "Engineered" design system (docs/12 §7.1,
 * source of truth: the design handoff's ui_kits/website/screen-home.jsx,
 * blocks.jsx and data.js). Run inside WordPress:
 *
 *   wp eval-file scripts/dev/create-home-page.php
 *
 * Idempotent: creates the `home` page once, points the site front page at
 * it, and replaces its Elementor document on each run (prior
 * _elementor_data backed up first — hard rule 11).
 *
 * Section order matches screen-home.jsx exactly: hero (no search box) →
 * feature strip (overlaps the hero on a white card) → featured jobs Loop
 * Carousel (query `poolhall_featured_jobs`, no autoplay) → Google reviews
 * (dark band) → three real-photo sector tiles → "what makes us different"
 * split (checklist + media collage) → credibility stat strip (bordered box
 * on navy) → paired candidate/employer CTA bands.
 *
 * The Google Reviews section renders through `[poolhall_reviews]`, which
 * shows the cached Places snapshot (Phase 8) or, on staging only, the
 * seeded demo quotes — and renders nothing at all otherwise, so the
 * section can never appear empty or leak placeholders to production.
 *
 * Requires Elementor Pro (Loop Carousel), the theme shell (pages, menus)
 * and the jobs templates (the `PH Loop - Job Featured Card` loop item).
 *
 * No strict_types declaration: wp eval-file runs this through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-home-page.php\n";
	exit( 1 );
}
if ( ! did_action( 'elementor/loaded' ) || ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
	echo "FAIL: Elementor + Elementor Pro must be active.\n";
	exit( 1 );
}
$poolhall_tpl_ids       = (array) get_option( 'poolhall_template_ids', array() );
$poolhall_featured_card = (int) ( $poolhall_tpl_ids['loop_job_featured_card'] ?? 0 );
if ( 0 === $poolhall_featured_card ) {
	echo "FAIL: featured-card loop item missing — run create-jobs-templates.php first.\n";
	exit( 1 );
}
if ( ! get_page_by_path( 'jobs' ) instanceof WP_Post ) {
	echo "FAIL: /jobs/ page missing — run create-theme-shell.php first.\n";
	exit( 1 );
}

// ------------------------------------------------------------- images ----
// Same keyed sideload as create-team-page.php: photography ships in the
// plugin and imports into the media library once.
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

$poolhall_images = array(
	'hero'          => $poolhall_import_image( 'poolhall-office.jpg', '' ),
	'story'         => $poolhall_import_image( 'poolhall-story.jpg', 'The Poolhall team at work' ),
	'construction'  => $poolhall_import_image( 'sector-construction.webp', 'Construction site team at work' ),
	'manufacturing' => $poolhall_import_image( 'sector-manufacturing.webp', 'Manufacturing and engineering at work' ),
	'digital'       => $poolhall_import_image( 'sector-digital.webp', 'Digital and marketing team at work' ),
);
foreach ( $poolhall_images as $poolhall_img_key => $poolhall_img_id ) {
	if ( 0 === $poolhall_img_id ) {
		printf( "FAIL: could not import image %s\n", $poolhall_img_key );
		exit( 1 );
	}
}

// --------------------------------------------------------------- page ----
$poolhall_home_page = get_page_by_path( 'home' );
if ( ! $poolhall_home_page instanceof WP_Post ) {
	$poolhall_home_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Home',
			'post_name'   => 'home',
		)
	);
	if ( is_wp_error( $poolhall_home_id ) || 0 === $poolhall_home_id ) {
		echo "FAIL: could not create the home page.\n";
		exit( 1 );
	}
	$poolhall_home_page = get_post( $poolhall_home_id );
}

// ----------------------------------------------------------- builders ----
$eid = static fn(): string => substr( md5( uniqid( '', true ) ), 0, 7 );

$container = static fn( array $settings, array $children, bool $inner = false ): array => array(
	'id'       => $eid(),
	'elType'   => 'container',
	'settings' => $settings,
	'elements' => $children,
	'isInner'  => $inner,
);
$widget    = static fn( string $type, array $settings ): array => array(
	'id'         => $eid(),
	'elType'     => 'widget',
	'widgetType' => $type,
	'settings'   => $settings,
	'elements'   => array(),
);

$boxed = static fn( array $extra = array() ): array => array_merge(
	array(
		'content_width' => 'boxed',
		'boxed_width'   => array(
			'unit' => 'px',
			'size' => 1152,
		),
	),
	$extra
);

$gap = static fn( int $px ): array => array(
	'unit'   => 'px',
	'size'   => $px,
	'column' => (string) $px,
	'row'    => (string) $px,
);

$section_padding = static fn( int $block = 88 ): array => array(
	'unit'     => 'px',
	'top'      => (string) $block,
	'right'    => '24',
	'bottom'   => (string) $block,
	'left'     => '24',
	'isLinked' => false,
);

$eyebrow = static fn( string $text, bool $on_dark = false ): array => $widget(
	'text-editor',
	array(
		'editor'     => '<p class="ph-eyebrow">' . esc_html( $text ) . '</p>',
		'text_color' => $on_dark ? '#FECF87' : '#8A5E12',
	)
);

$heading = static fn( string $text, string $size, string $color, string $clamp ): array => $widget(
	'heading',
	array(
		'title'                  => $text,
		'header_size'            => $size,
		'title_color'            => $color,
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Archivo',
		'typography_font_weight' => '800',
		'typography_font_size'   => array(
			'unit' => 'custom',
			'size' => $clamp,
		),
		'typography_line_height' => array(
			'unit' => 'em',
			'size' => 1.12,
		),
	)
);

$h2_clamp = 'clamp(1.7rem, 1.45rem + 1vw, 2.25rem)';

// Section head: eyebrow + H2 (+ optional lede), width-capped like the
// prototype's SectionHead.
$section_head = static fn( string $eyebrow_text, string $title, string $lede = '', bool $on_dark = false ): array => $container(
	array(
		'content_width'  => 'full',
		'flex_direction' => 'column',
		'flex_gap'       => $gap( 12 ),
		'width'          => array(
			'unit' => 'custom',
			'size' => 'min(100%, 43rem)',
		),
	),
	array_values(
		array_filter(
			array(
				$eyebrow( $eyebrow_text, $on_dark ),
				$heading( $title, 'h2', $on_dark ? '#FFFFFF' : '#1B4068', $h2_clamp ),
				'' === $lede ? null : $widget(
					'text-editor',
					array( 'editor' => '<p class="ph-lede' . ( $on_dark ? ' ph-text-reversed-soft' : '' ) . '">' . esc_html( $lede ) . '</p>' )
				),
			)
		)
	),
	true
);

// ------------------------------------------------------------ sections ----
$jobs_url      = (string) get_permalink( get_page_by_path( 'jobs' ) );
$employers_url = (string) get_permalink( get_page_by_path( 'employers' ) );

// 1. Hero (§7.1): office photo over brand navy, eyebrow, gold-emphasis H1,
// lead, gold "Find work" + ghost-light "Hire talent" CTAs, world markers.
// No search box in the hero (Poolhall is a partner, not a job board).
$hero = $container(
	$boxed(
		array(
			'flex_direction'                => 'column',
			'flex_justify_content'          => 'center',
			'min_height'                    => array(
				'unit' => 'custom',
				'size' => 'clamp(32rem, 64svh, 40rem)',
			),
			'background_background'         => 'classic',
			'background_color'              => '#0B2846',
			'background_image'              => array(
				'id'  => $poolhall_images['hero'],
				'url' => (string) wp_get_attachment_image_url( $poolhall_images['hero'], 'full' ),
			),
			'background_size'               => 'cover',
			'background_position'           => 'center center',
			'background_overlay_background' => 'classic',
			'background_overlay_color'      => '#06182B',
			'background_overlay_opacity'    => array(
				'unit' => 'px',
				'size' => 0.78,
			),
			'padding'                       => $section_padding( 96 ),
		)
	),
	array(
		$container(
			array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'flex_gap'       => $gap( 18 ),
				'width'          => array(
					'unit' => 'custom',
					'size' => 'min(100%, 43rem)',
				),
			),
			array(
				$eyebrow( 'Recruitment, done well', true ),
				$widget(
					'text-editor',
					array( 'editor' => '<h1 class="ph-display" style="color:#FFFFFF;margin:0">West Midlands roots. <span style="color:var(--ph-color-gold-500)">National</span> recruitment reach.</h1>' )
				),
				$widget(
					'text-editor',
					array( 'editor' => '<p class="ph-lede ph-text-reversed-soft">We help the people who build, make and market British business find their next move, across Construction, Manufacturing and Digital. Independent, down-to-earth and always happy to talk things through.</p>' )
				),
				$widget(
					'text-editor',
					array(
						'editor' => '<div class="ph-cluster" style="gap:14px;margin-top:6px">'
							. '<a class="ph-button ph-button--primary ph-button--lg" href="' . esc_url( home_url( '/jobs/' ) ) . '">Find work</a>'
							. '<a class="ph-button ph-button--inverse ph-button--lg" href="' . esc_url( home_url( '/employers/' ) ) . '">Hire talent</a>'
							. '</div>',
					)
				),
				$widget(
					'text-editor',
					array(
						'editor' => '<div class="ph-hero-worlds"><span class="is-active"><b>01</b> Construction</span><span><b>02</b> Manufacturing</span><span><b>03</b> Digital</span><span><b>04</b> Our Team</span></div>',
					)
				),
			),
			true
		),
	)
);

// 2. Featured jobs (§12): section head + View all jobs + Loop Carousel.
// No autoplay and no infinite loop (§22, §24): predictable order, keyboard
// and touch controls from the Pro widget.
$featured = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#F7F8FA',
			'flex_direction'        => 'column',
			'flex_gap'              => $gap( 40 ),
			'padding'               => $section_padding(),
		)
	),
	array(
		$container(
			array(
				'content_width'        => 'full',
				'flex_direction'       => 'row',
				'flex_justify_content' => 'space-between',
				'flex_align_items'     => 'flex-end',
				'flex_wrap'            => 'wrap',
				'flex_gap'             => $gap( 16 ),
			),
			array(
				$section_head( '01 / Featured roles', 'Live jobs, this week', 'A snapshot of what we&rsquo;re recruiting right now. New roles land all the time.' ),
				$widget(
					'text-editor',
					array(
						'editor' => '<a class="ph-button ph-button--ghost" href="' . esc_url( $jobs_url ) . '">View all jobs &rarr;</a>',
					)
				),
			),
			true
		),
		$widget(
			'loop-carousel',
			array(
				'_skin'                 => 'post',
				'template_id'           => $poolhall_featured_card,
				'posts_per_page'        => 6,
				'post_query_post_type'  => 'poolhall_job',
				'post_query_query_id'   => 'poolhall_featured_jobs',
				'slides_to_show'        => '3',
				'slides_to_show_tablet' => '2',
				'slides_to_show_mobile' => '1',
				'space_between'         => array( 'size' => 24 ),
				'autoplay'              => '',
				'loop'                  => '',
				'arrows'                => 'yes',
				'pagination'            => 'bullets',
			)
		),
	)
);

// 3. Sectors (§9.1 SectorTile): three real-photo tiles (bottom-heavy gradient
// + gold "N live roles" tag + Explore link), using the bundled sector photos.
// The photo renders as a plain <img> (absolutely positioned by CSS) rather
// than through Elementor's container background_image setting, which proved
// unreliable this deep in the nesting (rendered as a flat colour only,
// confirmed via a live browser check — computed background-image: none).
$sector_tile = static function ( string $name, int $image_id, int $count, string $desc ) use ( $container, $widget, $jobs_url ): array {
	return $container(
		array(
			'content_width'         => 'full',
			'flex_direction'        => 'column',
			'flex_justify_content'  => 'flex-end',
			'background_background' => 'classic',
			'background_color'      => '#123255',
			'padding'               => array(
				'unit'     => 'px',
				'top'      => '26',
				'right'    => '26',
				'bottom'   => '26',
				'left'     => '26',
				'isLinked' => true,
			),
			'flex_gap'              => array(
				'unit'   => 'px',
				'size'   => 4,
				'column' => '4',
				'row'    => '4',
			),
			'css_classes'           => 'ph-sector-tile',
			'link'                  => array(
				'url'         => $jobs_url,
				'is_external' => '',
				'nofollow'    => '',
			),
		),
		array(
			$widget(
				'text-editor',
				array(
					'editor' => '<img class="ph-sector-tile__photo" src="' . esc_url( (string) wp_get_attachment_image_url( $image_id, 'large' ) ) . '" alt="" />',
				)
			),
			$widget(
				'text-editor',
				array( 'editor' => '<p class="ph-sector-tile__count">' . esc_html( $count ) . ' live roles</p>' )
			),
			$widget(
				'heading',
				array(
					'title'        => $name,
					'header_size'  => 'h3',
					'_css_classes' => 'ph-sector-tile__name',
					'title_color'  => '#FFFFFF',
				)
			),
			$widget( 'text-editor', array( 'editor' => '<p class="ph-sector-tile__desc">' . esc_html( $desc ) . '</p>' ) ),
			$widget( 'text-editor', array( 'editor' => '<p class="ph-sector-tile__more">Explore sector <span aria-hidden="true">&rarr;</span></p>' ) ),
		),
		true
	);
};

$sector_cards = array(
	$sector_tile( 'Construction', $poolhall_images['construction'], 9, 'Site managers, project managers, engineers and skilled trades for contractors across the Midlands and nationally.' ),
	$sector_tile( 'Manufacturing', $poolhall_images['manufacturing'], 6, 'Welders, fabricators, production and engineering talent for the Black Country&rsquo;s manufacturing heartland.' ),
	$sector_tile( 'Digital', $poolhall_images['digital'], 5, 'Marketing, PPC, SEO and digital specialists for fast-growing agencies and in-house teams.' ),
);

$sectors = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#F2F4F6',
			'flex_direction'        => 'column',
			'flex_gap'              => $gap( 40 ),
			'padding'               => $section_padding(),
		)
	),
	array(
		$section_head( '03 / Where we work', 'Three sectors, real depth', 'We focus on a few industries rather than all of them, so we really understand the work and the people who build, make and market British business.' ),
		$container(
			array(
				'content_width' => 'full',
				'css_classes'   => 'ph-grid-3',
			),
			$sector_cards,
			true
		),
	)
);

// 4. "What makes us different" (§9.1 Home step 4): checklist + media collage.
$check_icon_home          = '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
$poolhall_differentiators = array(
	'Independent and experienced, you&rsquo;ll always speak with a senior consultant who knows your sector.',
	'Friendly, honest advice, we&rsquo;ll always share our genuine view to help you decide.',
	'Quality over quantity, a considered shortlist that&rsquo;s thoughtfully put together.',
	'Easy to reach, a local team that&rsquo;s always happy to pick up the phone.',
);
$poolhall_checklist_html  = '<ul class="ph-checklist">';
foreach ( $poolhall_differentiators as $poolhall_point ) {
	$poolhall_checklist_html .= '<li>' . $check_icon_home . '<span>' . $poolhall_point . '</span></li>';
}
$poolhall_checklist_html .= '</ul>';

$about_split = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#FFFFFF',
			'padding'               => $section_padding(),
		)
	),
	array(
		$container(
			array(
				'content_width' => 'full',
				'css_classes'   => 'ph-split',
			),
			array(
				$container(
					array(
						'content_width'  => 'full',
						'flex_direction' => 'column',
					),
					array(
						$eyebrow( '04 / What makes us different' ),
						$heading( 'Recruitment with people at the heart of it.', 'h2', '#1B4068', $h2_clamp ),
						$widget(
							'text-editor',
							array( 'editor' => '<p class="ph-lede" style="margin-top:16px">We&rsquo;re an independent agency that believes recruitment works best when it&rsquo;s done thoughtfully, honestly, and with people at the centre.</p>' )
						),
						$widget( 'text-editor', array( 'editor' => $poolhall_checklist_html ) ),
					),
					true
				),
				$widget(
					'text-editor',
					array(
						'editor' => '<div class="ph-media-collage">'
							. '<div class="ph-media-collage__item ph-media-collage__item--tall"><img src="' . esc_url( (string) wp_get_attachment_image_url( $poolhall_images['hero'], 'large' ) ) . '" alt="" /></div>'
							. '<div class="ph-media-collage__item"><img src="' . esc_url( (string) wp_get_attachment_image_url( $poolhall_images['story'], 'large' ) ) . '" alt="" /></div>'
							. '<div class="ph-media-collage__item"><img src="' . esc_url( (string) wp_get_attachment_image_url( $poolhall_images['construction'], 'large' ) ) . '" alt="" /></div>'
							. '</div>',
					)
				),
			),
			true
		),
	)
);

// 5. Credibility stat strip (§9.1): bordered box on brand navy, white values.
$poolhall_stat_cells = array(
	array( '30', 'yrs', 'Combined experience' ),
	array( '5.0', '', 'Average Google rating' ),
	array( '3', '', 'Specialist sectors' ),
	array( '2021', '', 'Independent since' ),
);
$poolhall_stats_html = '<div class="ph-stats-box">';
foreach ( $poolhall_stat_cells as [ $poolhall_stat_value, $poolhall_stat_suffix, $poolhall_stat_label ] ) {
	$poolhall_stats_html .= '<div class="ph-stats-box__cell"><div class="ph-stats-box__value">' . esc_html( $poolhall_stat_value ) . esc_html( $poolhall_stat_suffix ) . '</div>'
		. '<div class="ph-stats-box__label">' . esc_html( $poolhall_stat_label ) . '</div></div>';
}
$poolhall_stats_html .= '</div>';

$stats = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#0B2846',
			'padding'               => $section_padding( 56 ),
		)
	),
	array(
		$widget( 'text-editor', array( 'editor' => $poolhall_stats_html ) ),
	)
);

// 6. Google reviews (§9.1 step 3): dark navy band; the shortcode emits head +
// carousel only when review data exists, so this never shows an empty band.
$reviews = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#06182B',
			'padding'               => array(
				'unit'     => 'px',
				'top'      => '0',
				'right'    => '24',
				'bottom'   => '0',
				'left'     => '24',
				'isLinked' => false,
			),
		)
	),
	array(
		$widget( 'shortcode', array( 'shortcode' => '[poolhall_reviews]' ) ),
	)
);


// Feature strip (§9.1 FeatureStrip): overlaps the hero by -46px on a white
// card (the overlap and card styling live in .ph-feature-strip-wrap/-strip).
$feature_item = static function ( string $icon, string $title, string $text ) use ( $widget ): array {
	return $widget(
		'text-editor',
		array(
			'editor' => '<div class="ph-feature"><span class="ph-feature__icon" aria-hidden="true">' . $icon . '</span>'
				. '<div><h3 class="ph-feature__title">' . esc_html( $title ) . '</h3>'
				. '<p class="ph-feature__text">' . esc_html( $text ) . '</p></div></div>',
		)
	);
};
$icon_target  = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>';
$icon_phone   = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
$icon_map     = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>';

$feature_strip = $container(
	array(
		'content_width' => 'full',
		'css_classes'   => 'ph-feature-strip-wrap',
	),
	array(
		$container(
			$boxed(
				array(
					'css_classes' => 'ph-grid-3 ph-feature-strip',
				)
			),
			array(
				$feature_item( $icon_target, 'Specialist, not generalist', 'Three sectors we know inside out, so the conversation starts in the right place.' ),
				$feature_item( $icon_phone, 'A friendly voice on the phone', 'Consultants who pick up, take the time to listen, and offer genuine advice.' ),
				$feature_item( $icon_map, 'National reach', 'Black Country roots, helping people find roles the length of the country.' ),
			),
			true
		),
	)
);

// Paired CTA bands (§9.1 CtaBands): candidate (navy + gold edge, two CTAs) +
// employer (steel-700 + steel-200 edge, two CTAs) side by side.
$register_url = home_url( '/candidate/register/' );
$contact_url  = (string) get_permalink( get_page_by_path( 'contact' ) );

$cta_card = static function ( string $classes, string $eyebrow_text, string $title, string $text, array $primary, array $secondary ) use ( $container, $widget, $heading, $h2_clamp ): array {
	[ $primary_text, $primary_url, $primary_variant ] = $primary;
	[ $secondary_text, $secondary_url ]               = $secondary;
	return $container(
		array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'flex_gap'       => array(
				'unit'   => 'px',
				'size'   => 12,
				'column' => '12',
				'row'    => '12',
			),
			'padding'        => array(
				'unit'     => 'px',
				'top'      => '44',
				'right'    => '40',
				'bottom'   => '44',
				'left'     => '40',
				'isLinked' => false,
			),
			'css_classes'    => $classes,
		),
		array(
			$widget( 'text-editor', array( 'editor' => '<p class="ph-eyebrow" style="color:#FECF87">' . esc_html( $eyebrow_text ) . '</p>' ) ),
			$heading( $title, 'h2', '#FFFFFF', $h2_clamp ),
			$widget( 'text-editor', array( 'editor' => '<p class="ph-lede ph-text-reversed-soft">' . esc_html( $text ) . '</p>' ) ),
			$widget(
				'text-editor',
				array(
					'editor' => '<div class="ph-cluster" style="margin-top:8px">'
						. '<a class="ph-button ph-button--' . esc_attr( $primary_variant ) . '" href="' . esc_url( $primary_url ) . '">' . esc_html( $primary_text ) . ' &rarr;</a>'
						. '<a class="ph-button ph-button--inverse" href="' . esc_url( $secondary_url ) . '">' . esc_html( $secondary_text ) . '</a>'
						. '</div>',
				)
			),
		),
		true
	);
};

$paired_cta = $container(
	array( 'content_width' => 'full' ),
	array(
		$container(
			array(
				'content_width' => 'full',
				'css_classes'   => 'ph-grid-2',
			),
			array(
				$cta_card(
					'ph-cta-card ph-cta-card--candidate',
					'For candidates',
					'Looking for your next role?',
					'Register in a couple of minutes and we&rsquo;ll be in touch when something that fits comes along, no pressure, just a friendly heads-up.',
					array( 'Register now', $register_url, 'primary' ),
					array( 'View jobs', $jobs_url )
				),
				$cta_card(
					'ph-cta-card ph-cta-card--employer',
					'For employers',
					'Looking to hire?',
					'Tell us who you&rsquo;re looking for and we&rsquo;ll put together a shortlist of people we think you&rsquo;ll be glad to meet.',
					array( 'Enquire now', $employers_url, 'navy' ),
					array( 'Book a call', $contact_url )
				),
			),
			true
		),
	)
);

// ---- v2 server-rendered swaps -----------------------------------------
// The signature sections are rendered by the plugin (V2Fragments) so the
// frontend is the prototype markup 1:1: four-stage hero, overlapping
// feature strip, jobcard carousel, photo sector tiles and full-bleed
// paired CTA bands. Reviews / about split / stats stay builder-composed.
$poolhall_v2_full = static fn( string $shortcode ): array => $container(
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
	array( $widget( 'shortcode', array( 'shortcode' => $shortcode ) ) )
);

$hero          = $poolhall_v2_full( '[poolhall_v2_hero]' );
$feature_strip = $poolhall_v2_full( '[poolhall_v2_feature_strip]' );
$paired_cta    = $poolhall_v2_full( '[poolhall_v2_cta_bands]' );

$featured = $container(
	$boxed(
		array(
			'flex_direction' => 'column',
			'flex_gap'       => $gap( 40 ),
			'padding'        => $section_padding(),
		)
	),
	array(
		$section_head( '01 / Featured roles', 'Live jobs, this week', 'A snapshot of what we&rsquo;re recruiting right now. New roles land all the time.' ),
		$widget( 'shortcode', array( 'shortcode' => '[poolhall_v2_featured_jobs]' ) ),
	)
);

$sectors = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#F2F4F6',
			'flex_direction'        => 'column',
			'flex_gap'              => $gap( 40 ),
			'padding'               => $section_padding(),
		)
	),
	array(
		$section_head( '03 / Where we work', 'Three sectors, real depth', 'We focus on a few industries rather than all of them, so we really understand the work and the people who build, make and market British business.' ),
		$widget( 'shortcode', array( 'shortcode' => '[poolhall_v2_sector_tiles]' ) ),
	)
);

$home_data = array( $hero, $feature_strip, $featured, $reviews, $sectors, $about_split, $stats, $paired_cta );

// Backup before modifying Elementor data (hard rule 11).
$prior = get_post_meta( $poolhall_home_page->ID, '_elementor_data', true );
if ( ! empty( $prior ) ) {
	update_option( 'poolhall_tpl_backup_' . $poolhall_home_page->ID . '_' . gmdate( 'Ymd_His' ), $prior, false );
}

update_post_meta( $poolhall_home_page->ID, '_elementor_edit_mode', 'builder' );
update_post_meta( $poolhall_home_page->ID, '_elementor_template_type', 'wp-page' );
update_post_meta( $poolhall_home_page->ID, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $poolhall_home_page->ID, '_elementor_data', wp_slash( wp_json_encode( $home_data ) ) );

// Point the site front page at /home/ (idempotent).
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $poolhall_home_page->ID );

// Regenerate CSS and clear caches (hard rule 12).
\Elementor\Plugin::instance()->files_manager->clear_cache();

$poolhall_tpl_ids['home_page'] = $poolhall_home_page->ID;
update_option( 'poolhall_template_ids', $poolhall_tpl_ids, false );

printf( "Images: hero #%d, story #%d, construction #%d, manufacturing #%d, digital #%d.\n", $poolhall_images['hero'], $poolhall_images['story'], $poolhall_images['construction'], $poolhall_images['manufacturing'], $poolhall_images['digital'] );
printf( "Home page: #%d built (hero, feature strip, featured carousel, reviews, sectors, about split, stats, paired CTAs) and set as front page.\n", $poolhall_home_page->ID );
echo "OK: home page created. Verify the frontend before treating this as done.\n";
