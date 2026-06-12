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
 * The prototype's Google Reviews carousel is deliberately absent: real
 * reviews arrive with the Places integration (Phase 8) and sample quotes
 * are not real content. The reviews section lands when that data exists.
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
		'text_color' => $on_dark ? '#F4904A' : '#C45712',
	)
);

$heading = static fn( string $text, string $size, string $color, string $clamp ): array => $widget(
	'heading',
	array(
		'title'                  => $text,
		'header_size'            => $size,
		'title_color'            => $color,
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Source Serif 4',
		'typography_font_weight' => '600',
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
				$heading( $title, 'h2', $on_dark ? '#FFFFFF' : '#1B3052', $h2_clamp ),
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

// 1. Image hero (§9): office photo over navy, search panel + trust row.
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
			'background_color'              => '#14233F',
			'background_image'              => array(
				'id'  => $poolhall_images['hero'],
				'url' => (string) wp_get_attachment_image_url( $poolhall_images['hero'], 'full' ),
			),
			'background_size'               => 'cover',
			'background_position'           => 'center center',
			'background_overlay_background' => 'classic',
			'background_overlay_color'      => '#0F1D33',
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
				$eyebrow( 'Independent recruitment · West Midlands', true ),
				$heading( 'Find your next job with us', 'h1', '#FFFFFF', 'clamp(2.4rem, 1.9rem + 2.2vw, 3.5rem)' ),
				$widget(
					'text-editor',
					array( 'editor' => '<p class="ph-lede ph-text-reversed-soft">We match incredible roles with amazing people. Honest advice, exclusive roles and a process that puts you first.</p>' )
				),
				$widget( 'shortcode', array( 'shortcode' => '[poolhall_job_search]' ) ),
				$widget(
					'text-editor',
					array(
						'editor' => '<div class="ph-search-trust">'
							. '<span class="ph-search-trust__item">' . $star_icon . '5.0 on Google Reviews</span>'
							. '<span class="ph-search-trust__item">' . $shield_icon . 'Quality &amp; ethical, since 2021</span>'
							. '[poolhall_live_roles]'
							. '</div>',
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
						'title_color'  => '#B9510E',
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

// 3. Sectors (§25 step 5, v4 static sector cards): one accessible link per
// card (the title); live per-sector links arrive with the results widget.
$poolhall_sectors = array(
	'Construction & Skilled Trade',
	'Manufacturing',
	'Marketing & PR',
	'Sales',
	'Insurance',
	'Automotive',
);
$sector_cards     = array();
foreach ( $poolhall_sectors as $poolhall_sector_name ) {
	$sector_cards[] = $container(
		array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'flex_gap'       => $gap( 6 ),
			'css_classes'    => 'ph-card ph-card--interactive',
		),
		array(
			$widget(
				'heading',
				array(
					'title'        => $poolhall_sector_name,
					'header_size'  => 'h3',
					'title_color'  => '#1B3052',
					'_css_classes' => 'ph-h4',
					'link'         => array(
						'url'         => $jobs_url,
						'is_external' => '',
						'nofollow'    => '',
					),
				)
			),
			$widget(
				'text-editor',
				array(
					'editor'     => '<p class="ph-small">Live roles and exclusive vacancies</p>',
					'text_color' => '#5A6678',
				)
			),
		),
		true
	);
}

$sectors = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#FFFFFF',
			'flex_direction'        => 'column',
			'flex_gap'              => $gap( 40 ),
			'padding'               => $section_padding(),
		)
	),
	array(
		$section_head( 'Sectors we cover', 'Specialists in the work that builds Britain', 'Decades of combined experience across the industries we know best.' ),
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
					'title_color'  => '#1B3052',
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
			'background_color'      => '#14233F',
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

// 6. Employer CTA split (§25 step 9; reviews carousel lands with Phase 8).
$employer_cta = $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => '#EEF1F5',
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
						$heading( 'We&rsquo;ll represent your business like it&rsquo;s our own', 'h2', '#1B3052', $h2_clamp ),
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
										'background_color'              => '#14233F',
										'button_background_hover_color' => '#1B3052',
										'button_text_color'             => '#FFFFFF',
										'hover_color'                   => '#FFFFFF',
										'typography_typography'         => 'custom',
										'typography_font_family'        => 'Hanken Grotesk',
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
										'button_text_color'             => '#14233F',
										'hover_color'                   => '#14233F',
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
										'typography_font_family'        => 'Hanken Grotesk',
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

$home_data = array( $hero, $featured, $sectors, $steps, $stats, $employer_cta );

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
