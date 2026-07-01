<?php
/**
 * Phase 3/4 visual: build the Home page (design system §25 Home composition,
 * §9 image hero, §11 home search, §12 featured carousel). Run inside
 * WordPress:
 *
 *   wp eval-file scripts/dev/create-home-page.php
 *
 * Idempotent: creates the `home` page once, points the site front page at
 * it, and replaces its Elementor document on each run (prior
 * _elementor_data backed up first — hard rule 11). Content mirrors the
 * approved prototype (project/ui_kits/website/Home.jsx): image hero with
 * the server-rendered `[poolhall_job_search]` panel and trust row
 * (`[poolhall_live_roles]` hides itself while the job store is empty),
 * featured jobs Loop Carousel (query `poolhall_featured_jobs`, no
 * autoplay), six static sector cards, the three-step candidate process,
 * the credibility stat strip and the employer CTA split.
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
	'hero'  => $poolhall_import_image( 'poolhall-office.jpg', '' ),
	'story' => $poolhall_import_image( 'poolhall-story.jpg', 'The Poolhall team at work' ),
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
$star_icon   = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14l-5-4.87 6.91-1.01z"/></svg>';
$shield_icon = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>';

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
					array( 'editor' => '<p class="ph-lede ph-text-reversed-soft">We place the people who build, make and market British business, across Construction, Manufacturing and Digital. Independent, practical and human. We actually answer the phone.</p>' )
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
						'editor' => '<div class="ph-hero-worlds"><span class="is-active"><b>01</b> Construction</span><span><b>02</b> Manufacturing</span><span><b>03</b> Digital</span><span><b>04</b> Team</span></div>',
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
				'content_width'         => 'full',
				'flex_direction'        => 'row',
				'flex_justify_content'  => 'space-between',
				'flex_align_items'      => 'flex-end',
				'flex_wrap'             => 'wrap',
				'flex_gap'              => $gap( 16 ),
			),
			array(
				$section_head( 'Current roles', 'Featured jobs this week', 'Hand-picked roles from our latest live vacancies.' ),
				$widget(
					'heading',
					array(
						'title'        => 'View all jobs',
						'header_size'  => 'p',
						'title_color'  => '#8A5E12',
						'_css_classes' => 'ph-link ph-link--arrow',
						'link'         => array(
							'url'         => $jobs_url,
							'is_external' => '',
							'nofollow'    => '',
						),
					)
				),
			),
			true
		),
		$widget(
			'loop-carousel',
			array(
				'_skin'                => 'post',
				'template_id'          => $poolhall_featured_card,
				'posts_per_page'       => 6,
				'post_query_post_type' => 'poolhall_job',
				'post_query_query_id'  => 'poolhall_featured_jobs',
				'slides_to_show'       => '3',
				'slides_to_show_tablet' => '2',
				'slides_to_show_mobile' => '1',
				'space_between'        => array( 'size' => 24 ),
				'autoplay'             => '',
				'loop'                 => '',
				'arrows'               => 'yes',
				'pagination'           => 'bullets',
			)
		),
	)
);

// 3. Sectors (§7.1 #4): three photo tiles (overlay + gold tag + Explore
// link). The office photo is a placeholder until per-sector photography is
// supplied; the heavy navy overlay keeps the three reading as a set.
$sector_tile = static function ( string $name, string $tag, string $desc ) use ( $container, $widget, $poolhall_images, $jobs_url ): array {
	return $container(
		array(
			'content_width'                 => 'full',
			'flex_direction'                => 'column',
			'flex_justify_content'          => 'flex-end',
			'min_height'                    => array(
				'unit' => 'custom',
				'size' => '24rem',
			),
			'background_background'          => 'classic',
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
				'size' => 0.74,
			),
			'border_radius'                 => array(
				'unit'     => 'px',
				'top'      => '6',
				'right'    => '6',
				'bottom'   => '6',
				'left'     => '6',
				'isLinked' => true,
			),
			'padding'                       => array(
				'unit'     => 'px',
				'top'      => '28',
				'right'    => '28',
				'bottom'   => '28',
				'left'     => '28',
				'isLinked' => false,
			),
			'flex_gap'                      => array(
				'unit'   => 'px',
				'size'   => 8,
				'column' => '8',
				'row'    => '8',
			),
			'css_classes'                   => 'ph-sector-tile',
			'link'                          => array(
				'url'         => $jobs_url,
				'is_external' => '',
				'nofollow'    => '',
			),
		),
		array(
			$widget( 'text-editor', array( 'editor' => '<p class="ph-sector-tile__count">' . esc_html( $tag ) . '</p>' ) ),
			$widget(
				'heading',
				array(
					'title'        => $name,
					'header_size'  => 'h3',
					'_css_classes' => 'ph-h3 ph-sector-tile__name',
					'title_color'  => '#FFFFFF',
				)
			),
			$widget( 'text-editor', array( 'editor' => '<p class="ph-sector-tile__desc">' . esc_html( $desc ) . '</p>' ) ),
			$widget( 'text-editor', array( 'editor' => '<p class="ph-sector-tile__more">Explore sector &rarr;</p>' ) ),
		),
		true
	);
};

$sector_cards = array(
	$sector_tile( 'Construction', 'Live roles', 'Site managers, project managers, engineers and skilled trades for contractors across the Midlands and nationally.' ),
	$sector_tile( 'Manufacturing', 'Live roles', 'Welders, fabricators, production and engineering talent for the region&rsquo;s manufacturing heartland.' ),
	$sector_tile( 'Digital', 'Live roles', 'Marketing, PPC, SEO and digital specialists for fast-growing agencies and in-house teams.' ),
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
		$section_head( 'Where we work', 'Three sectors. Deep expertise.', 'We don&rsquo;t recruit for everything. We recruit brilliantly for the industries that build, make and market British business.' ),
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

// 4. Three-step candidate process (§25 step 6).
$poolhall_steps = array(
	array( '1', 'Tell us what you want', 'Share your CV and what a great next move looks like. No pressure, no spam, just a real conversation.' ),
	array( '2', 'We match you to roles', 'We only put you forward for roles that genuinely fit your skills, salary and ambitions.' ),
	array( '3', 'We guide you to offer', 'Interview prep, honest feedback and support right through to your first day and beyond.' ),
);
$step_cards     = array();
foreach ( $poolhall_steps as [ $poolhall_step_num, $poolhall_step_title, $poolhall_step_body ] ) {
	$step_cards[] = $container(
		array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'flex_gap'       => $gap( 8 ),
			'css_classes'    => 'ph-card',
		),
		array(
			$widget(
				'text-editor',
				array( 'editor' => '<div class="ph-stat__value">' . esc_html( $poolhall_step_num ) . '</div>' )
			),
			$widget(
				'heading',
				array(
					'title'        => $poolhall_step_title,
					'header_size'  => 'h3',
					'title_color'  => '#1B4068',
					'_css_classes' => 'ph-h4',
				)
			),
			$widget(
				'text-editor',
				array(
					'editor'     => '<p class="ph-body">' . esc_html( $poolhall_step_body ) . '</p>',
					'text_color' => '#5A6678',
				)
			),
		),
		true
	);
}

$steps = $container(
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
		$section_head( 'How we work with you', 'Three steps to your next role' ),
		$container(
			array(
				'content_width' => 'full',
				'css_classes'   => 'ph-grid-3',
			),
			$step_cards,
			true
		),
	)
);

// 5. Credibility stat strip (§25 step 7): navy, values orange on dark.
$stats = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#0B2846',
			'padding'               => $section_padding( 56 ),
		)
	),
	array(
		$widget(
			'text-editor',
			array(
				'editor' => '<div class="ph-grid-2 ph-grid-auto">'
					. '<div><div class="ph-stat__value">30yrs</div><div class="ph-stat__label ph-text-reversed-soft">Combined experience</div></div>'
					. '<div><div class="ph-stat__value">5.0</div><div class="ph-stat__label ph-text-reversed-soft">Average Google rating</div></div>'
					. '<div><div class="ph-stat__value">6</div><div class="ph-stat__label ph-text-reversed-soft">Specialist sectors</div></div>'
					. '<div><div class="ph-stat__value">2021</div><div class="ph-stat__label ph-text-reversed-soft">Independent since</div></div>'
					. '</div>',
			)
		),
	)
);

// 6. Google reviews (§25 step 8): self-contained server render — the
// shortcode emits head + carousel only when review data exists.
$reviews = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#FFFFFF',
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

// 7. Employer CTA split (§25 step 9).
$employer_cta = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#F2F4F6',
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
						'flex_gap'       => $gap( 14 ),
					),
					array(
						$eyebrow( 'Looking to hire?' ),
						$heading( 'We&rsquo;ll represent your business like it&rsquo;s our own', 'h2', '#1B4068', $h2_clamp ),
						$widget(
							'text-editor',
							array( 'editor' => '<p class="ph-lede">We work with PLCs and SMEs on exclusive and exciting roles. Tell us who you need and we&rsquo;ll find the right people.</p>' )
						),
						$container(
							array(
								'content_width'  => 'full',
								'flex_direction' => 'row',
								'flex_wrap'      => 'wrap',
								'flex_gap'       => $gap( 12 ),
							),
							array(
								$widget(
									'button',
									array(
										'text'                          => 'For employers',
										'link'                          => array(
											'url'         => $employers_url,
											'is_external' => '',
											'nofollow'    => '',
										),
										'background_color'              => '#0B2846',
										'button_background_hover_color' => '#1B4068',
										'button_text_color'             => '#FFFFFF',
										'hover_color'                   => '#FFFFFF',
										'typography_typography'         => 'custom',
										'typography_font_family'        => 'Source Sans 3',
										'typography_font_weight'        => '700',
									)
								),
								$widget(
									'button',
									array(
										'text'                          => '0121 516 3000',
										'link'                          => array(
											'url'         => 'tel:01215163000',
											'is_external' => '',
											'nofollow'    => '',
										),
										'background_color'              => '#FFFFFF',
										'button_background_hover_color' => '#F7F8FA',
										'button_text_color'             => '#0B2846',
										'hover_color'                   => '#0B2846',
										'border_border'                 => 'solid',
										'border_width'                  => array(
											'unit'     => 'px',
											'top'      => '1',
											'right'    => '1',
											'bottom'   => '1',
											'left'     => '1',
											'isLinked' => true,
										),
										'border_color'                  => '#C9D0DA',
										'typography_typography'         => 'custom',
										'typography_font_family'        => 'Source Sans 3',
										'typography_font_weight'        => '700',
									)
								),
							),
							true
						),
					),
					true
				),
				$widget(
					'image',
					array(
						'image'               => array(
							'id'  => $poolhall_images['story'],
							'url' => (string) wp_get_attachment_image_url( $poolhall_images['story'], 'full' ),
						),
						'image_size'          => 'large',
						'image_border_radius' => array(
							'unit'     => 'px',
							'top'      => '16',
							'right'    => '16',
							'bottom'   => '16',
							'left'     => '16',
							'isLinked' => true,
						),
					)
				),
			),
			true
		),
	)
);

// Feature strip (design §7.1 #2): three reasons on a navy bar under the hero.
$feature_item = static function ( string $icon, string $title, string $text ) use ( $widget ): array {
	return $widget(
		'text-editor',
		array(
			'editor' => '<div class="ph-feature"><span class="ph-feature__icon" aria-hidden="true">' . $icon . '</span>'
				. '<h3 class="ph-feature__title">' . esc_html( $title ) . '</h3>'
				. '<p class="ph-feature__text">' . esc_html( $text ) . '</p></div>',
		)
	);
};
$icon_target = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>';
$icon_phone  = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
$icon_map    = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>';

$feature_strip = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#0B2846',
			'flex_direction'        => 'column',
			'padding'               => $section_padding( 52 ),
		)
	),
	array(
		$container(
			array(
				'content_width' => 'full',
				'css_classes'   => 'ph-grid-3 ph-feature-strip',
			),
			array(
				$feature_item( $icon_target, 'Specialist, not generalist', 'We focus on Construction, Manufacturing and Digital, so we actually know your roles.' ),
				$feature_item( $icon_phone, 'A friendly voice on the phone', 'Real people who answer, listen and keep you informed. No call centres.' ),
				$feature_item( $icon_map, 'National reach', 'West Midlands roots, placing people the length and breadth of the country.' ),
			),
			true
		),
	)
);

// Paired CTA bands (design §7.1 #8): candidate (navy + gold edge) + employer
// (steel) side by side.
$cta_card = static function ( string $classes, string $eyebrow_text, string $title, string $text, string $btn_text, string $btn_url, string $btn_variant ) use ( $container, $widget, $heading, $h2_clamp ): array {
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
			$widget( 'text-editor', array( 'editor' => '<div style="margin-top:8px"><a class="ph-button ph-button--' . esc_attr( $btn_variant ) . ' ph-button--lg" href="' . esc_url( $btn_url ) . '">' . esc_html( $btn_text ) . '</a></div>' ) ),
		),
		true
	);
};

$paired_cta = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#F2F4F6',
			'padding'               => $section_padding(),
		)
	),
	array(
		$container(
			array(
				'content_width' => 'full',
				'css_classes'   => 'ph-grid-2',
			),
			array(
				$cta_card( 'ph-cta-card ph-cta-card--candidate', 'For candidates', 'Looking for your next role?', 'Tell us what a great move looks like and we will be in touch about roles that fit.', 'Find work', home_url( '/jobs/' ), 'primary' ),
				$cta_card( 'ph-cta-card ph-cta-card--employer', 'For employers', 'Looking to hire?', 'PLCs and SMEs trust us with exclusive roles. Tell us who you need and we will find them.', 'Hire talent', $employers_url, 'inverse' ),
			),
			true
		),
	)
);

$home_data = array( $hero, $feature_strip, $featured, $reviews, $sectors, $stats, $paired_cta );

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

printf( "Images: hero #%d, story #%d.\n", $poolhall_images['hero'], $poolhall_images['story'] );
printf( "Home page: #%d built (hero + search, featured carousel, sectors, steps, stats, employer CTA) and set as front page.\n", $poolhall_home_page->ID );
echo "OK: home page created. Verify the frontend before treating this as done.\n";
