<?php
/**
 * Phase 3 visual: build the Meet the Team page (design system §18 Team,
 * §9 compact image hero). Run inside WordPress:
 *
 *   wp eval-file scripts/dev/create-team-page.php
 *
 * Idempotent: replaces the page's Elementor document on each run (prior
 * _elementor_data backed up first — hard rule 11) and sideloads the
 * bundled client photography into the media library once, keyed by the
 * `poolhall_content_image` meta marker. Content mirrors the approved
 * prototype (project/ui_kits/website/Team.jsx): compact navy hero, story
 * split with stats, three v4-style team cards on the shared class
 * registry, navy CTA band. The prototype's placeholder social icons are
 * deliberately absent — no real profile URLs exist yet and '#' links are
 * banned (hard rule 7).
 *
 * Requires Elementor and the theme shell (the /team/ page + menus). The
 * page itself uses only core Elementor containers/widgets, so it works
 * with or without Pro active.
 *
 * No strict_types declaration: wp eval-file runs this through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-team-page.php\n";
	exit( 1 );
}
if ( ! did_action( 'elementor/loaded' ) ) {
	echo "FAIL: Elementor must be active.\n";
	exit( 1 );
}
$poolhall_team_page = get_page_by_path( 'team' );
if ( ! $poolhall_team_page instanceof WP_Post ) {
	echo "FAIL: /team/ page missing — run create-theme-shell.php first.\n";
	exit( 1 );
}

// ------------------------------------------------------------- images ----
// Bundled real photography (extracted from the design handoff); imported
// into the media library so WordPress owns sizes/srcset (doc 10 §21).
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

	// Copy first: media_handle_sideload moves the file and the plugin copy
	// must survive for the next environment.
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
	'office'  => $poolhall_import_image( 'poolhall-office.jpg', '' ),
	'story'   => $poolhall_import_image( 'poolhall-story.jpg', 'The Poolhall team at work' ),
	'matthew' => $poolhall_import_image( 'team-matthew.jpg', 'Matthew Tonks' ),
	'jay'     => $poolhall_import_image( 'team-jay.jpg', 'Jay Thornton' ),
	'sam'     => $poolhall_import_image( 'team-sam.jpg', 'Sam Ogle' ),
);
foreach ( $poolhall_images as $poolhall_img_key => $poolhall_img_id ) {
	if ( 0 === $poolhall_img_id ) {
		printf( "FAIL: could not import image %s\n", $poolhall_img_key );
		exit( 1 );
	}
}

// ----------------------------------------------------------- builders ----
$eid = static fn(): string => substr( md5( uniqid( '', true ) ), 0, 7 );

$container = static function ( array $settings, array $children ) use ( $eid ): array {
	return array(
		'id'       => $eid(),
		'elType'   => 'container',
		'settings' => $settings,
		'elements' => $children,
		'isInner'  => false,
	);
};
$widget    = static function ( string $type, array $settings ) use ( $eid ): array {
	return array(
		'id'         => $eid(),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
		'elements'   => array(),
	);
};

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

$eyebrow = static fn( string $text, bool $on_dark = false ): array => $widget(
	'text-editor',
	array(
		'editor'      => '<p class="ph-eyebrow">' . esc_html( $text ) . '</p>',
		'text_color'  => $on_dark ? '#FECF87' : '#8A5E12',
	)
);

$image_widget = static fn( int $attachment_id, string $size = 'large' ): array => $widget(
	'image',
	array(
		'image'              => array(
			'id'  => $attachment_id,
			'url' => (string) wp_get_attachment_image_url( $attachment_id, 'full' ),
		),
		'image_size'         => $size,
		'image_border_radius' => array(
			'unit'     => 'px',
			'top'      => '16',
			'right'    => '16',
			'bottom'   => '16',
			'left'     => '16',
			'isLinked' => true,
		),
	)
);

// Team member card: ph-card--team is padding-0/overflow-hidden, portrait
// bleeds to the card edge at 4:5, text block carries its own padding.
$team_card = static function ( int $photo_id, string $name, string $role, array $tags, string $bio ) use ( $container, $widget ): array {
	$chips = '';
	foreach ( $tags as $tag ) {
		$chips .= '<span class="ph-chip">' . esc_html( $tag ) . '</span> ';
	}
	return $container(
		array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'flex_gap'       => array(
				'unit'   => 'px',
				'size'   => 0,
				'column' => '0',
				'row'    => '0',
			),
			'css_classes'    => 'ph-card ph-card--team',
		),
		array(
			$widget(
				'image',
				array(
					'image'      => array(
						'id'  => $photo_id,
						'url' => (string) wp_get_attachment_image_url( $photo_id, 'full' ),
					),
					'image_size' => 'large',
					'width'      => array(
						'unit' => '%',
						'size' => 100,
					),
					'_css_classes' => 'ph-team-portrait',
				)
			),
			$container(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 8,
						'column' => '8',
						'row'    => '8',
					),
					'padding'        => array(
						'unit'     => 'px',
						'top'      => '20',
						'right'    => '24',
						'bottom'   => '24',
						'left'     => '24',
						'isLinked' => false,
					),
				),
				array(
					$widget(
						'heading',
						array(
							'title'       => $name,
							'header_size' => 'h3',
							'title_color' => '#1B4068',
							'typography_typography'  => 'custom',
							'typography_font_family' => 'Archivo',
							'typography_font_weight' => '700',
							'typography_font_size'   => array(
								'unit' => 'rem',
								'size' => 1.375,
							),
						)
					),
					$widget(
						'text-editor',
						array(
							'editor'     => '<p class="ph-eyebrow">' . esc_html( $role ) . '</p>',
							'text_color' => '#8A5E12',
						)
					),
					$widget(
						'text-editor',
						array( 'editor' => '<p>' . trim( $chips ) . '</p>' )
					),
					$widget(
						'text-editor',
						array(
							'editor'     => '<p class="ph-body">' . esc_html( $bio ) . '</p>',
							'text_color' => '#5A6678',
						)
					),
				)
			),
		)
	);
};

// --------------------------------------------------------------- page ----
$team_data = array(
	// Compact image hero (§9): full-bleed photo over Navy 900 with a dark
	// overlay; copy width capped at 43rem by the inner container.
	$container(
		$boxed(
			array(
				'flex_direction'         => 'column',
				'flex_justify_content'   => 'flex-end',
				'min_height'             => array(
					'unit' => 'custom',
					'size' => 'clamp(24rem, 50svh, 32rem)',
				),
				'background_background'  => 'classic',
				'background_color'       => '#0B2846',
				'background_image'       => array(
					'id'  => $poolhall_images['office'],
					'url' => (string) wp_get_attachment_image_url( $poolhall_images['office'], 'full' ),
				),
				'background_size'        => 'cover',
				'background_position'    => 'center center',
				'css_classes'            => 'ph-goldedge',
				'background_overlay_background' => 'classic',
				'background_overlay_color'      => '#06182B',
				'background_overlay_opacity'    => array(
					'unit' => 'px',
					'size' => 0.78,
				),
				'padding'                => array(
					'unit'     => 'px',
					'top'      => '96',
					'right'    => '24',
					'bottom'   => '64',
					'left'     => '24',
					'isLinked' => false,
				),
			)
		),
		array(
			$container(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 16,
						'column' => '16',
						'row'    => '16',
					),
					'width'          => array(
						'unit' => 'custom',
						'size' => 'min(100%, 43rem)',
					),
				),
				array(
					$eyebrow( 'About Poolhall', true ),
					$widget(
						'heading',
						array(
							'title'       => 'A local team that takes recruitment personally.',
							'header_size' => 'h1',
							'title_color' => '#FFFFFF',
							'typography_typography'  => 'custom',
							'typography_font_family' => 'Archivo',
							'typography_font_weight' => '800',
							'typography_font_size'   => array(
								'unit' => 'custom',
								'size' => 'clamp(2.4rem, 1.9rem + 2.2vw, 3.6rem)',
							),
							'typography_line_height' => array(
								'unit' => 'em',
								'size' => 1.08,
							),
						)
					),
					$widget(
						'text-editor',
						array(
							'editor' => '<p class="ph-lede ph-text-reversed-soft">Founded in 2021 to do things differently, built on honesty, hard work and genuine relationships.</p>',
						)
					),
				)
			),
		)
	),

	// Story split (§18): copy + stats left, rounded image right.
	$container(
		$boxed(
			array(
				'background_background' => 'classic',
				'background_color'      => '#FFFFFF',
				'padding'               => array(
					'unit'     => 'px',
					'top'      => '88',
					'right'    => '24',
					'bottom'   => '88',
					'left'     => '24',
					'isLinked' => false,
				),
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
							'flex_gap'       => array(
								'unit'   => 'px',
								'size'   => 14,
								'column' => '14',
								'row'    => '14',
							),
						),
						array(
							$eyebrow( 'Our story' ),
							$widget(
								'heading',
								array(
									'title'       => 'Independent, and proud of it',
									'header_size' => 'h2',
									'title_color' => '#1B4068',
									'typography_typography'  => 'custom',
									'typography_font_family' => 'Archivo',
									'typography_font_weight' => '800',
									'typography_font_size'   => array(
										'unit' => 'custom',
										'size' => 'clamp(1.7rem, 1.45rem + 1vw, 2.25rem)',
									),
								)
							),
							$widget(
								'text-editor',
								array( 'editor' => '<p class="ph-lede">Poolhall was founded in 2021 on a simple idea: combine state-of-the-art technology with traditional principles and ethics.</p>' )
							),
							$widget(
								'text-editor',
								array(
									'editor'     => '<p class="ph-body">Being independent means we answer to our candidates and clients, not to shareholders or quotas. That&rsquo;s why people come back to us, and why so many of our roles are exclusive.</p>',
									'text_color' => '#5A6678',
								)
							),
							$widget(
								'text-editor',
								array(
									'editor' => '<div class="ph-cluster">'
										. '<div><div class="ph-stat__value">30yrs</div><div class="ph-stat__label">Combined experience</div></div>'
										. '<div><div class="ph-stat__value">5.0</div><div class="ph-stat__label">Average Google rating</div></div>'
										. '<div><div class="ph-stat__value">6</div><div class="ph-stat__label">Specialist sectors</div></div>'
										. '</div>',
								)
							),
						)
					),
					$image_widget( $poolhall_images['story'] ),
				)
			),
		)
	),

	// Team grid (§18): three approved profiles as cards, 4:5 portraits.
	$container(
		$boxed(
			array(
				'background_background' => 'classic',
				'background_color'      => '#F7F8FA',
				'padding'               => array(
					'unit'     => 'px',
					'top'      => '88',
					'right'    => '24',
					'bottom'   => '88',
					'left'     => '24',
					'isLinked' => false,
				),
				'flex_direction'        => 'column',
				'flex_gap'              => array(
					'unit'   => 'px',
					'size'   => 40,
					'column' => '40',
					'row'    => '40',
				),
			)
		),
		array(
			$container(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 12,
						'column' => '12',
						'row'    => '12',
					),
					'width'          => array(
						'unit' => 'custom',
						'size' => 'min(100%, 43rem)',
					),
				),
				array(
					$eyebrow( 'Say hello' ),
					$widget(
						'heading',
						array(
							'title'       => 'Meet the team',
							'header_size' => 'h2',
							'title_color' => '#1B4068',
							'typography_typography'  => 'custom',
							'typography_font_family' => 'Archivo',
							'typography_font_weight' => '800',
							'typography_font_size'   => array(
								'unit' => 'custom',
								'size' => 'clamp(1.7rem, 1.45rem + 1vw, 2.25rem)',
							),
						)
					),
					$widget(
						'text-editor',
						array( 'editor' => '<p class="ph-lede">The specialists you&rsquo;ll actually speak to.</p>' )
					),
				)
			),
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-grid-3',
				),
				array(
					$team_card(
						$poolhall_images['matthew'],
						'Matthew Tonks',
						'Founder',
						array( 'Construction', 'Manufacturing' ),
						'Matthew founded Poolhall to do recruitment differently, built on honesty, hard work and genuine relationships. With over a decade in the industry, he specialises in Manufacturing and Construction and leads the team\'s quality-first, ethical approach.'
					),
					$team_card(
						$poolhall_images['jay'],
						'Jay Thornton',
						'Recruitment Partner',
						array( 'Marketing & PR', 'Digital' ),
						'Jay heads up our Marketing, PR and Digital desk. He takes the time to really understand both clients and candidates, so every introduction is the right one, never a numbers game.'
					),
					$team_card(
						$poolhall_images['sam'],
						'Sam Ogle',
						'Marketing Specialist',
						array( 'Brand', 'Content' ),
						'Sam looks after Poolhall\'s brand and marketing. With ten years\' experience and a marathon runner\'s discipline, he keeps our voice clear, consistent and genuinely human.'
					),
				)
			),
		)
	),

	// Join-us CTA band: navy, heading + lede left, button right.
	$container(
		$boxed(
			array(
				'background_background' => 'classic',
				'background_color'      => '#0B2846',
				'flex_direction'        => 'row',
				'flex_align_items'      => 'center',
				'flex_justify_content'  => 'space-between',
				'flex_wrap'             => 'wrap',
				'flex_gap'              => array(
					'unit'   => 'px',
					'size'   => 24,
					'column' => '24',
					'row'    => '24',
				),
				'padding'               => array(
					'unit'     => 'px',
					'top'      => '56',
					'right'    => '24',
					'bottom'   => '56',
					'left'     => '24',
					'isLinked' => false,
				),
			)
		),
		array(
			$container(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 10,
						'column' => '10',
						'row'    => '10',
					),
					'width'          => array(
						'unit' => 'custom',
						'size' => 'min(100%, 38rem)',
					),
				),
				array(
					$widget(
						'heading',
						array(
							'title'       => 'Fancy joining the team?',
							'header_size' => 'h2',
							'title_color' => '#FFFFFF',
							'typography_typography'  => 'custom',
							'typography_font_family' => 'Archivo',
							'typography_font_weight' => '800',
							'typography_font_size'   => array(
								'unit' => 'custom',
								'size' => 'clamp(1.7rem, 1.45rem + 1vw, 2.25rem)',
							),
						)
					),
					$widget(
						'text-editor',
						array( 'editor' => '<p class="ph-lede ph-text-reversed-soft">We&rsquo;re always keen to hear from great recruiters who share our values.</p>' )
					),
				)
			),
			$widget(
				'button',
				array(
					'text'                  => 'Get in touch',
					'link'                  => array(
						'url'         => get_permalink( get_page_by_path( 'contact' ) ),
						'is_external' => '',
						'nofollow'    => '',
					),
					'background_color'      => '#FDBB5D',
					'button_text_color'     => '#0B2846',
					'hover_color'           => '#0B2846',
					'button_background_hover_color' => '#E0A33F',
					'border_radius'         => array(
						'unit'     => 'px',
						'top'      => '10',
						'right'    => '10',
						'bottom'   => '10',
						'left'     => '10',
						'isLinked' => true,
					),
					'typography_typography'  => 'custom',
					'typography_font_family' => 'Source Sans 3',
					'typography_font_weight' => '700',
				)
			),
		)
	),
);

// Backup before modifying Elementor data (hard rule 11).
$prior = get_post_meta( $poolhall_team_page->ID, '_elementor_data', true );
if ( ! empty( $prior ) ) {
	update_option( 'poolhall_tpl_backup_' . $poolhall_team_page->ID . '_' . gmdate( 'Ymd_His' ), $prior, false );
}

update_post_meta( $poolhall_team_page->ID, '_elementor_edit_mode', 'builder' );
update_post_meta( $poolhall_team_page->ID, '_elementor_template_type', 'wp-page' );
update_post_meta( $poolhall_team_page->ID, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $poolhall_team_page->ID, '_elementor_data', wp_slash( wp_json_encode( $team_data ) ) );

// Regenerate CSS and clear caches (hard rule 12).
\Elementor\Plugin::instance()->files_manager->clear_cache();

$poolhall_ids           = (array) get_option( 'poolhall_template_ids', array() );
$poolhall_ids['team_page'] = $poolhall_team_page->ID;
update_option( 'poolhall_template_ids', $poolhall_ids, false );

printf( "Images: office #%d, story #%d, portraits #%d/#%d/#%d.\n", $poolhall_images['office'], $poolhall_images['story'], $poolhall_images['matthew'], $poolhall_images['jay'], $poolhall_images['sam'] );
printf( "Team page: #%d built (hero, story split, 3 profile cards, CTA).\n", $poolhall_team_page->ID );
echo "OK: team page created. Verify the frontend before treating this as done.\n";
