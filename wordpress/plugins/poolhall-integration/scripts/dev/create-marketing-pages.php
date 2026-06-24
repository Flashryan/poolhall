<?php
/**
 * Phase 4 visual: build the marketing pages — Employers, Sectors, Services,
 * Contact and Join Our Team (design system §25 marketing pages; content
 * spec 01 §7–§10). Run inside WordPress:
 *
 *   wp eval-file scripts/dev/create-marketing-pages.php
 *
 * Idempotent: replaces each page's Elementor document on every run (prior
 * _elementor_data backed up first — hard rule 11). Copy comes from the
 * approved prototype (project/ui_kits/website/Employers.jsx, Contact.jsx)
 * and the content spec structures; claims the migration doc lists as
 * unconfirmed (Better Job Adverts price, consultant salary/bonus, partner
 * commission/ownership, ~50yrs experience) are deliberately absent. The
 * hiring and contact forms are the server-rendered
 * `[poolhall_enquiry_form]` shortcodes (honeypot + nonce + Turnstile seam,
 * consent, rate-limited, mailed to the configurable enquiry inbox).
 * The prototype's decorative fake map block is omitted — no fake UI.
 *
 * Requires Elementor and the theme shell pages. Uses only core Elementor
 * containers/widgets, so it works with or without Pro active.
 *
 * No strict_types declaration: wp eval-file runs this through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-marketing-pages.php\n";
	exit( 1 );
}
if ( ! did_action( 'elementor/loaded' ) ) {
	echo "FAIL: Elementor must be active.\n";
	exit( 1 );
}
$poolhall_pages = array();
foreach ( array( 'employers', 'sectors', 'services', 'contact', 'join-our-team', 'better-job-adverts', 'jobs', 'delivery-options', 'why-us', 'bespoke-search', 'hr-services', 'commitment', 'candidates' ) as $poolhall_slug ) {
	$poolhall_page = get_page_by_path( $poolhall_slug );
	if ( ! $poolhall_page instanceof WP_Post ) {
		printf( "FAIL: /%s/ page missing — run create-theme-shell.php first.\n", $poolhall_slug );
		exit( 1 );
	}
	$poolhall_pages[ $poolhall_slug ] = $poolhall_page;
}

// Bundled photography (already imported by the team/home scripts if they
// ran first; the keyed sideload makes this a lookup either way).
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

$poolhall_office_img = $poolhall_import_image( 'poolhall-office.jpg', '' );
$poolhall_story_img  = $poolhall_import_image( 'poolhall-story.jpg', 'The Poolhall team at work' );
if ( 0 === $poolhall_office_img || 0 === $poolhall_story_img ) {
	echo "FAIL: could not import bundled photography.\n";
	exit( 1 );
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

$h1_clamp = 'clamp(2.1rem, 1.7rem + 1.8vw, 3rem)';
$h2_clamp = 'clamp(1.7rem, 1.45rem + 1vw, 2.25rem)';

$lede = static fn( string $text, bool $on_dark = false ): array => $widget(
	'text-editor',
	array( 'editor' => '<p class="ph-lede' . ( $on_dark ? ' ph-text-reversed-soft' : '' ) . '">' . wp_kses_post( $text ) . '</p>' )
);

$body = static fn( string $text ): array => $widget(
	'text-editor',
	array(
		'editor'     => '<p class="ph-body">' . wp_kses_post( $text ) . '</p>',
		'text_color' => '#5A6678',
	)
);

$button = static function ( string $text, string $url, string $style = 'primary' ) use ( $widget ): array {
	$colors = array(
		'primary' => array( '#FDBB5D', '#E0A33F', '#0B2846', '#0B2846' ),
		'navy'    => array( '#0B2846', '#1B4068', '#FFFFFF', '#FFFFFF' ),
		'ghost'   => array( '#FFFFFF', '#F7F8FA', '#0B2846', '#0B2846' ),
	);
	[ $bg, $bg_hover, $fg, $fg_hover ] = $colors[ $style ] ?? $colors['primary'];

	$settings = array(
		'text'                          => $text,
		'link'                          => array(
			'url'         => $url,
			'is_external' => '',
			'nofollow'    => '',
		),
		'background_color'              => $bg,
		'button_background_hover_color' => $bg_hover,
		'button_text_color'             => $fg,
		'hover_color'                   => $fg_hover,
		'typography_typography'         => 'custom',
		'typography_font_family'        => 'Source Sans 3',
		'typography_font_weight'        => '700',
	);
	if ( 'ghost' === $style ) {
		$settings['border_border'] = 'solid';
		$settings['border_width']  = array(
			'unit'     => 'px',
			'top'      => '1',
			'right'    => '1',
			'bottom'   => '1',
			'left'     => '1',
			'isLinked' => true,
		);
		$settings['border_color']  = '#C9D0DA';
	}
	return $widget( 'button', $settings );
};

$column = static fn( array $children, int $gap_px = 14 ): array => $container(
	array(
		'content_width'  => 'full',
		'flex_direction' => 'column',
		'flex_gap'       => $gap( $gap_px ),
	),
	$children,
	true
);

$section_head = static fn( string $eyebrow_text, string $title, string $lede_text = '', bool $on_dark = false ): array => $container(
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
				'' === $lede_text ? null : $lede( $lede_text, $on_dark ),
			)
		)
	),
	true
);

// Slim navy page hero (§9 solid hero), as on the jobs archive.
$hero_slim = static fn( string $eyebrow_text, string $title, string $lede_text, array $extra = array() ): array => $container(
	array(
		'content_width' => 'full',
		'css_classes'   => 'ph-section--navy ph-hero--slim ph-pagehead',
	),
	array(
		$container(
			$boxed(
				array(
					'flex_direction' => 'column',
					'flex_gap'       => $gap( 12 ),
				)
			),
			array_merge(
				array(
					$eyebrow( $eyebrow_text, true ),
					$heading( $title, 'h1', '#FFFFFF', $h1_clamp ),
					$lede( $lede_text, true ),
				),
				$extra
			),
			true
		),
	)
);

$section = static fn( string $bg, array $children, int $pad = 88 ): array => $container(
	$boxed(
		array(
			'background_background' => 'classic',
			'background_color'      => $bg,
			'flex_direction'        => 'column',
			'flex_gap'              => $gap( 40 ),
			'padding'               => $section_padding( $pad ),
		)
	),
	$children
);

$card = static fn( string $title, string $body_text ) => $container(
	array(
		'content_width'  => 'full',
		'flex_direction' => 'column',
		'flex_gap'       => $gap( 8 ),
		'css_classes'    => 'ph-card',
	),
	array(
		$widget(
			'heading',
			array(
				'title'        => $title,
				'header_size'  => 'h3',
				'title_color'  => '#1B4068',
				'_css_classes' => 'ph-h4',
			)
		),
		$body( $body_text ),
	),
	true
);

$check_icon = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
$check_list = static function ( array $items ) use ( $widget, $check_icon ): array {
	$html = '<ul class="ph-stack-sm" style="list-style:none;padding:0;margin:0">';
	foreach ( $items as $item ) {
		$html .= '<li style="display:flex;gap:10px;align-items:flex-start"><span style="color:#1E7F4F;flex-shrink:0;margin-top:2px">' . $check_icon . '</span><span class="ph-body">' . esc_html( $item ) . '</span></li>';
	}
	return $widget( 'text-editor', array( 'editor' => $html . '</ul>' ) );
};

$image_widget = static fn( int $attachment_id ): array => $widget(
	'image',
	array(
		'image'               => array(
			'id'  => $attachment_id,
			'url' => (string) wp_get_attachment_image_url( $attachment_id, 'full' ),
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
);

$write_page = static function ( WP_Post $page, array $data ): void {
	$prior = get_post_meta( $page->ID, '_elementor_data', true );
	if ( ! empty( $prior ) ) {
		update_option( 'poolhall_tpl_backup_' . $page->ID . '_' . gmdate( 'Ymd_His' ), $prior, false );
	}
	update_post_meta( $page->ID, '_elementor_edit_mode', 'builder' );
	update_post_meta( $page->ID, '_elementor_template_type', 'wp-page' );
	update_post_meta( $page->ID, '_wp_page_template', 'elementor_header_footer' );
	update_post_meta( $page->ID, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
};

$contact_details = static fn() => $widget(
	'text-editor',
	array(
		'editor' => '<div class="ph-stack-sm">'
			. '<p class="ph-body"><strong>Call us</strong><br /><a href="tel:01215163000">0121 516 3000</a></p>'
			. '<p class="ph-body"><strong>Email</strong><br /><a href="mailto:jobs@poolhallrecruitment.co.uk">jobs@poolhallrecruitment.co.uk</a></p>'
			. '<p class="ph-body"><strong>Visit</strong><br />Grosvenor House, 11 St Pauls Square,<br />Birmingham, B3 1RB</p>'
			. '<p class="ph-body"><strong>Opening hours</strong><br />Mon&ndash;Fri &middot; 8:30am &ndash; 5:30pm</p>'
			. '</div>',
	)
);

$jobs_url      = (string) get_permalink( $poolhall_pages['jobs'] );
$employers_url = (string) get_permalink( $poolhall_pages['employers'] );
$contact_url   = (string) get_permalink( $poolhall_pages['contact'] );
$services_url  = (string) get_permalink( $poolhall_pages['services'] );
$bja_url       = (string) get_permalink( $poolhall_pages['better-job-adverts'] );

// =================================================== Employers (spec §7) ----
$employers_data = array(
	$hero_slim(
		'For employers',
		'Find the right people, faster',
		'An independent partner that represents your business like its own. Exclusive shortlists, honest advice and a personable service across Construction, Manufacturing and Marketing.',
		array(
			$container(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'row',
					'flex_wrap'      => 'wrap',
					'flex_gap'       => $gap( 12 ),
				),
				array(
					$button( 'Get in touch', $employers_url . '#enquiry', 'primary' ),
					$button( '0121 516 3000', 'tel:01215163000', 'ghost' ),
				),
				true
			),
		)
	),

	// Service cards — the spec's real services, not the prototype labels.
	$section(
		'#FFFFFF',
		array(
			$section_head( 'Our services', 'Three ways we help you hire', 'Flexible support whether you want us to run the whole search or just lend a hand.' ),
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-grid-3',
				),
				array(
					$card( 'Permanent recruitment', 'Bespoke, end-to-end search for permanent roles. We manage sourcing, screening, interviews and offer.' ),
					$card( 'Temp-to-perm', 'Flexible workforce support when you need to scale quickly, with a straightforward route to permanent hires when it works for both sides.' ),
					$card( 'Retained &amp; embedded support', 'A dedicated partner for critical or multiple hires — running your search end to end or working alongside your team.' ),
				),
				true
			),
		)
	),

	// Quality and ethics proof.
	$section(
		'#F7F8FA',
		array(
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-split',
				),
				array(
					$image_widget( $poolhall_office_img ),
					$column(
						array(
							$eyebrow( 'Why Poolhall' ),
							$heading( 'Quality and ethics, not quotas', 'h2', '#1B4068', $h2_clamp ),
							$lede( 'We&rsquo;re an independent agency, so you get a more personable service and a partner genuinely invested in the right outcome.' ),
							$check_list(
								array(
									'About 30 years\' combined recruitment experience',
									'Sector specialists in Construction, Manufacturing & Marketing',
									'Exclusive roles and a transparent, honest process',
									'Rated 5.0 by the candidates and clients we work with',
								)
							),
						)
					),
				),
				true
			),
		)
	),

	// Sectors strip.
	$section(
		'#0B2846',
		array(
			$section_head( 'Sectors we recruit in', 'Specialists where it counts', '', true ),
			$widget(
				'text-editor',
				array(
					'editor' => '<div class="ph-cluster">'
						. implode(
							'',
							array_map(
								static fn( string $s ): string => '<span class="ph-chip" style="background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.16)">' . esc_html( $s ) . '</span>',
								array( 'Construction & Skilled Trade', 'Manufacturing', 'Marketing & PR', 'Sales', 'Insurance', 'Automotive' )
							)
						)
						. '</div>',
				)
			),
		),
		56
	),

	// Better Job Adverts callout (spec §8: never buried; directive §3.6:
	// gold band with a navy action).
	$section(
		'#FFFFFF',
		array(
			$container(
				array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'space-between',
					'flex_gap'             => $gap( 24 ),
					'css_classes'          => 'ph-panel ph-panel--gold',
				),
				array(
					$column(
						array(
							$eyebrow( 'Better Job Adverts' ),
							$heading( 'Prefer to run the hiring yourself?', 'h2', '#1B4068', $h2_clamp ),
							$lede( 'Fixed-fee job advertising across the major boards, with branded adverts, CV screening and an organised, interview-ready shortlist.' ),
						),
						10
					),
					$button( 'How it works', $bja_url, 'navy' ),
				),
				true
			),
		),
		56
	),

	// Hiring enquiry (spec §7 form fields, consent, anti-abuse).
	$section(
		'#F7F8FA',
		array(
			$container(
				array(
					'content_width'    => 'full',
					'css_classes'      => 'ph-split',
					'flex_align_items' => 'flex-start',
				),
				array(
					$column(
						array(
							$eyebrow( 'Looking to hire?' ),
							$heading( 'Tell us who you need', 'h2', '#1B4068', $h2_clamp ),
							$lede( 'Send us a few details and we&rsquo;ll come back to you within one working day. No obligation, no hard sell.' ),
							$contact_details(),
						)
					),
					$widget( 'shortcode', array( 'shortcode' => '[poolhall_enquiry_form kind="hiring"]' ) ),
				),
				true
			),
		)
	),
);

// ===================================================== Sectors (spec §9) ----
$poolhall_sector_sections = array(
	array(
		'Construction & Skilled Trade',
		'From site supervision to commercial leadership, we place the people who deliver projects safely, on time and on budget.',
		array( 'Site Managers', 'Project Managers', 'Quantity Surveyors', 'Estimators', 'Skilled Trades & Labour' ),
	),
	array(
		'Manufacturing',
		'Hands-on specialists for the factory floor and the engineers who keep production moving.',
		array( 'Welders & Fabricators', 'CNC Machinists & Programmers', 'Production Operatives', 'Maintenance Engineers', 'Quality & Compliance' ),
	),
	array(
		'Digital & Marketing',
		'Creative and performance marketers who grow brands — agency-side and in-house.',
		array( 'Marketing Managers', 'Paid Media & PPC', 'SEO Specialists', 'Content & Social', 'PR & Communications' ),
	),
);
$sector_blocks = array();
foreach ( $poolhall_sector_sections as $i => [ $poolhall_s_name, $poolhall_s_lede, $poolhall_s_roles ] ) {
	$chips = '<div class="ph-cluster">' . implode(
		'',
		array_map( static fn( string $r ): string => '<span class="ph-chip">' . esc_html( $r ) . '</span>', $poolhall_s_roles )
	) . '</div>';

	$sector_blocks[] = $section(
		0 === $i % 2 ? '#FFFFFF' : '#F7F8FA',
		array(
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-split',
				),
				array(
					$column(
						array(
							$eyebrow( 'Core sector' ),
							$heading( $poolhall_s_name, 'h2', '#1B4068', $h2_clamp ),
							$lede( $poolhall_s_lede ),
							$widget( 'text-editor', array( 'editor' => $chips ) ),
						)
					),
					$column(
						array(
							$container(
								array(
									'content_width'  => 'full',
									'flex_direction' => 'column',
									'flex_gap'       => $gap( 12 ),
									'css_classes'    => 'ph-card',
								),
								array(
									$body( 'Looking for your next role, or building a team in this sector?' ),
									$button( 'View live roles', $jobs_url, 'primary' ),
									$button( 'Talk to us about hiring', $employers_url . '#enquiry', 'navy' ),
								),
								true
							),
						)
					),
				),
				true
			),
		)
	);
}

$sectors_data = array_merge(
	array(
		$hero_slim(
			'Sectors we cover',
			'Specialists in the work that builds Britain',
			'Three core specialisms, backed by decades of combined experience. We also recruit across Sales, Insurance and Automotive through our live jobs board.'
		),
	),
	$sector_blocks,
	array(
		$section(
			'#0B2846',
			array(
				$container(
					array(
						'content_width'        => 'full',
						'flex_direction'       => 'row',
						'flex_wrap'            => 'wrap',
						'flex_align_items'     => 'center',
						'flex_justify_content' => 'space-between',
						'flex_gap'             => $gap( 24 ),
					),
					array(
						$column(
							array(
								$heading( 'Don&rsquo;t see your specialism?', 'h2', '#FFFFFF', $h2_clamp ),
								$lede( 'Our live board covers more than our core sectors &mdash; and we&rsquo;re always happy to talk.', true ),
							),
							10
						),
						$button( 'Browse all jobs', $jobs_url, 'primary' ),
					),
					true
				),
			),
			56
		),
	)
);

// ==================================================== Services (spec §8) ----
$services_data = array(
	$hero_slim(
		'Our services',
		'Recruitment, the way it should be done',
		'Honest, flexible support for employers — from one-off permanent hires to fully retained search.'
	),
	$section(
		'#FFFFFF',
		array(
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-grid-3',
				),
				array(
					$card( 'Permanent recruitment', 'A bespoke, end-to-end search for permanent roles: sourcing, screening, interview support and offer management, with honest advice throughout.' ),
					$card( 'Temp-to-perm', 'Flexible staffing when you need to scale quickly — with a clear, fair route to permanent employment when the fit is right.' ),
					$card( 'Retained &amp; custom support', 'For critical, confidential or multiple hires: a dedicated, committed search partner working to your timescales.' ),
				),
				true
			),
		)
	),
	// Service tiers (11.6): Bronze/Silver/Gold engagement levels, rendered by
	// the plugin shortcode (per-tier pricing gated off until fees are signed off).
	$section(
		'#F7F8FA',
		array(
			$section_head(
				'How we work with you',
				'Three levels of partnership',
				'Choose the level of support that fits the role. Pricing is confirmed when you enquire, with no obligation.'
			),
			$widget( 'shortcode', array( 'shortcode' => '[poolhall_service_tiers]' ) ),
		)
	),
	// Better Job Adverts gets its own prominent band (spec §8: not buried).
	$section(
		'#F7F8FA',
		array(
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-split',
				),
				array(
					$column(
						array(
							$eyebrow( 'Fixed-fee advertising' ),
							$heading( 'Better Job Adverts', 'h2', '#1B4068', $h2_clamp ),
							$lede( 'Keep recruitment in-house and let us do the heavy lifting: professionally written, branded adverts across the major job boards.' ),
							$check_list(
								array(
									'Fixed fee — no placement percentages',
									'Multi-board advertising reach',
									'Branded advert creation',
									'CV screening and shortlisting',
									'Organised, interview-ready candidates',
								)
							),
							$container(
								array(
									'content_width'  => 'full',
									'flex_direction' => 'row',
									'flex_wrap'      => 'wrap',
									'flex_gap'       => $gap( 12 ),
								),
								array( $button( 'Better Job Adverts in detail', $bja_url, 'primary' ) ),
								true
							),
						)
					),
					$image_widget( $poolhall_story_img ),
				),
				true
			),
		)
	),
	$section(
		'#0B2846',
		array(
			$container(
				array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'space-between',
					'flex_gap'             => $gap( 24 ),
				),
				array(
					$column(
						array(
							$heading( 'Not sure which route fits?', 'h2', '#FFFFFF', $h2_clamp ),
							$lede( 'Tell us what you&rsquo;re trying to build and we&rsquo;ll recommend the most cost-effective way to get there.', true ),
						),
						10
					),
					$button( 'Talk to us', $employers_url . '#enquiry', 'primary' ),
				),
				true
			),
		),
		56
	),
);

// ===================================================== Contact (spec §12) ----
$contact_data = array(
	$hero_slim(
		'Get in touch',
		'We&rsquo;d love to hear from you',
		'Whether you&rsquo;re after your next role or looking to hire, drop us a line and we&rsquo;ll reply within one working day.'
	),
	$section(
		'#F7F8FA',
		array(
			$container(
				array(
					'content_width'    => 'full',
					'css_classes'      => 'ph-split',
					'flex_align_items' => 'flex-start',
				),
				array(
					$column(
						array(
							$heading( 'Talk to a real person', 'h2', '#1B4068', $h2_clamp ),
							$body( 'No call centres, no ticket queues — you&rsquo;ll get one of the team every time.' ),
							$contact_details(),
						)
					),
					$widget( 'shortcode', array( 'shortcode' => '[poolhall_enquiry_form kind="contact"]' ) ),
				),
				true
			),
		)
	),
);

// =============================================== Join Our Team (spec §10) ----
// Both current propositions, with the salary/commission/ownership figures
// the migration doc flags as unconfirmed deliberately left out.
$join_data = array(
	$hero_slim(
		'Join our team',
		'Recruit the way you&rsquo;ve always wanted to',
		'We&rsquo;re building a team of recruiters who care about doing the job properly. Two ways in &mdash; pick the one that fits your ambitions.'
	),
	$section(
		'#FFFFFF',
		array(
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-grid-2',
				),
				array(
					$container(
						array(
							'content_width'  => 'full',
							'flex_direction' => 'column',
							'flex_gap'       => $gap( 10 ),
							'css_classes'    => 'ph-card',
						),
						array(
							$eyebrow( 'Employed' ),
							$widget(
								'heading',
								array(
									'title'        => 'Senior Recruitment Consultant',
									'header_size'  => 'h2',
									'title_color'  => '#1B4068',
									'_css_classes' => 'ph-h3',
								)
							),
							$body( 'Join the team in Birmingham with an established client base, full back-office support and the freedom to recruit ethically — no KPI theatre, no churn culture.' ),
							$check_list(
								array(
									'Established, exclusive client relationships',
									'Full admin, marketing and tech support',
									'A quality-first, ethical way of working',
								)
							),
						),
						true
					),
					$container(
						array(
							'content_width'  => 'full',
							'flex_direction' => 'column',
							'flex_gap'       => $gap( 10 ),
							'css_classes'    => 'ph-card',
						),
						array(
							$eyebrow( 'Self-employed' ),
							$widget(
								'heading',
								array(
									'title'        => 'Partner model',
									'header_size'  => 'h2',
									'title_color'  => '#1B4068',
									'_css_classes' => 'ph-h3',
								)
							),
							$body( 'Run your own desk under the Poolhall brand: our systems, marketing and reputation behind you, your client relationships and your independence intact.' ),
							$check_list(
								array(
									'Your desk, your sector, your way of working',
									'Poolhall brand, systems and marketing behind you',
									'A genuine partnership — terms discussed openly, in person',
								)
							),
						),
						true
					),
				),
				true
			),
		)
	),
	$section(
		'#0B2846',
		array(
			$container(
				array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'space-between',
					'flex_gap'             => $gap( 24 ),
				),
				array(
					$column(
						array(
							$heading( 'Sound like you?', 'h2', '#FFFFFF', $h2_clamp ),
							$lede( 'Get in touch for an honest, confidential conversation about either route.', true ),
						),
						10
					),
					$button( 'Start the conversation', $contact_url, 'primary' ),
				),
				true
			),
		),
		56
	),
);

// ======================================= Better Job Adverts (directive §3.5) ----
// Hard guardrail: never publish a price; "Pricing confirmed when you
// enquire." is the only pricing line allowed.
$poolhall_bja_props = array(
	array( 'One simple fixed fee', 'One agreed fee for the whole campaign. No placement percentages and no surprises.' ),
	array( 'Multi-board reach', 'Your role advertised across the major UK job boards, so the right people actually see it.' ),
	array( 'Branded adverts', 'Professionally written adverts with your brand front and centre, not ours.' ),
	array( 'CV screening', 'We read every application, so you only spend time on candidates worth meeting.' ),
	array( 'Interview-ready shortlists', 'Organised, qualified candidates delivered ready to book in for interview.' ),
	array( 'Results you can check', 'Clear reporting on views, applications and shortlist quality while the advert runs.' ),
);
$bja_prop_cards = array();
foreach ( $poolhall_bja_props as [ $poolhall_prop_title, $poolhall_prop_body ] ) {
	$bja_prop_cards[] = $card( $poolhall_prop_title, $poolhall_prop_body );
}

$poolhall_bja_steps = array(
	array( '1', 'Tell us the role', 'Share the job, the must-haves and your timescales. We take it from there.' ),
	array( '2', 'We write, brand and post', 'A professionally written, branded advert goes out across the major boards.' ),
	array( '3', 'You interview from a shortlist', 'We screen every CV and hand you an organised, interview-ready shortlist.' ),
);
$bja_step_cards = array();
foreach ( $poolhall_bja_steps as [ $poolhall_step_num, $poolhall_step_title, $poolhall_step_body ] ) {
	$bja_step_cards[] = $container(
		array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'flex_gap'       => $gap( 8 ),
			'css_classes'    => 'ph-card',
		),
		array(
			$widget( 'text-editor', array( 'editor' => '<div class="ph-stat__value">' . esc_html( $poolhall_step_num ) . '</div>' ) ),
			$widget(
				'heading',
				array(
					'title'        => $poolhall_step_title,
					'header_size'  => 'h3',
					'title_color'  => '#1B4068',
					'_css_classes' => 'ph-h4',
				)
			),
			$body( $poolhall_step_body ),
		),
		true
	);
}

$bja_data = array(
	// Image hero (§9 compact): office photo under a navy scrim.
	$container(
		$boxed(
			array(
				'flex_direction'                => 'column',
				'flex_justify_content'          => 'center',
				'min_height'                    => array(
					'unit' => 'custom',
					'size' => 'clamp(24rem, 50svh, 32rem)',
				),
				'background_background'         => 'classic',
				'background_color'              => '#06182B',
				'background_image'              => array(
					'id'  => $poolhall_office_img,
					'url' => (string) wp_get_attachment_image_url( $poolhall_office_img, 'full' ),
				),
				'background_size'               => 'cover',
				'background_position'           => 'center center',
				'background_overlay_background' => 'classic',
				'background_overlay_color'      => '#06182B',
				'background_overlay_opacity'    => array(
					'unit' => 'px',
					'size' => 0.82,
				),
				'css_classes'                   => 'ph-goldedge',
				'padding'                       => $section_padding( 96 ),
			)
		),
		array(
			$container(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => $gap( 14 ),
					'width'          => array(
						'unit' => 'custom',
						'size' => 'min(100%, 43rem)',
					),
				),
				array(
					$eyebrow( 'For employers · Better Job Adverts', true ),
					$heading( 'Better job adverts, better applicants', 'h1', '#FFFFFF', $h1_clamp ),
					$lede( 'Keep the hiring in-house and let us make your advert work harder. One fixed fee, your brand front and centre, and a shortlist that&rsquo;s ready to interview.', true ),
					$container(
						array(
							'content_width'  => 'full',
							'flex_direction' => 'row',
							'flex_wrap'      => 'wrap',
							'flex_gap'       => $gap( 12 ),
						),
						array(
							$button( 'Get started', $employers_url . '#enquiry', 'primary' ),
							$button( '0121 516 3000', 'tel:01215163000', 'ghost' ),
						),
						true
					),
				),
				true
			),
		)
	),
	// Six proposition cards (3 x 2).
	$section(
		'#FFFFFF',
		array(
			$section_head( 'What you get', 'Everything a good advert needs to perform' ),
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-grid-3',
				),
				$bja_prop_cards,
				true
			),
			// Pricing pill (guardrail: never a figure).
			$widget(
				'text-editor',
				array(
					'editor' => '<p style="text-align:center"><span class="ph-chip">Pricing confirmed when you enquire. No obligation.</span></p>',
				)
			),
		)
	),
	// Three steps.
	$section(
		'#F7F8FA',
		array(
			$section_head( 'How it works', 'Live in days, not weeks' ),
			$container(
				array(
					'content_width' => 'full',
					'css_classes'   => 'ph-grid-3',
				),
				$bja_step_cards,
				true
			),
		)
	),
	// CTA band.
	$section(
		'#0B2846',
		array(
			$container(
				array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'space-between',
					'flex_gap'             => $gap( 24 ),
				),
				array(
					$column(
						array(
							$heading( 'Got a role to advertise?', 'h2', '#FFFFFF', $h2_clamp ),
							$lede( 'Tell us about it and we&rsquo;ll come back with everything you need to decide.', true ),
						),
						10
					),
					$button( 'Send an enquiry', $employers_url . '#enquiry', 'primary' ),
				),
				true
			),
		),
		56
	),
);

// -------------------------------------------- v2 secondary pages (§7) ----
// Composed from the same helpers the existing marketing pages use, so they
// render identically. Photo page-header (ph-pagehead via $hero_slim) + a
// points grid + a navy CTA band. British English, sentence case, no figures
// or invented contacts (guardrails).
$register_url = home_url( '/candidate/register/' );

$cta_band = static function ( string $title, string $lede_text, string $cta_text, string $cta_url ) use ( $section, $container, $boxed, $gap, $heading, $lede, $button, $h2_clamp ): array {
	return $section(
		'#0B2846',
		array(
			$container(
				$boxed(
					array(
						'flex_direction' => 'column',
						'flex_gap'       => $gap( 16 ),
						'width'          => array(
							'unit' => 'custom',
							'size' => 'min(100%, 42rem)',
						),
					)
				),
				array(
					$heading( $title, 'h2', '#FFFFFF', $h2_clamp ),
					$lede( $lede_text, true ),
					$button( $cta_text, $cta_url, 'primary' ),
				),
				true
			),
		),
		72
	);
};

$points_page = static function ( string $he, string $ht, string $hl, string $se, string $st, array $cards, string $ct, string $cl, string $cta_text, string $cta_url ) use ( $hero_slim, $section, $section_head, $container, $card, $cta_band ): array {
	return array(
		$hero_slim( $he, $ht, $hl ),
		$section(
			'#FFFFFF',
			array(
				$section_head( $se, $st ),
				$container(
					array(
						'content_width' => 'full',
						'css_classes'   => 'ph-grid-3',
					),
					array_map( static fn( array $c ): array => $card( $c[0], $c[1] ), $cards ),
					true
				),
			)
		),
		$cta_band( $ct, $cl, $cta_text, $cta_url ),
	);
};

$write_page(
	$poolhall_pages['delivery-options'],
	$points_page(
		'Delivery options',
		'Flexible ways to work with us',
		'Choose the model that fits the role, the timeline and the budget. We will recommend the right approach when you enquire.',
		'How we deliver',
		'Five ways to hire',
		array(
			array( 'Temporary', 'Cover peaks, projects and absence with vetted temporary staff, managed end to end.' ),
			array( 'Permanent', 'A thorough, committed search for permanent hires, from sourcing to offer management.' ),
			array( 'Scale', 'Volume and multi-site hiring with the structure and reporting to keep it on track.' ),
			array( 'Pay monthly', 'Spread the cost of a permanent hire across manageable monthly payments.' ),
			array( 'On-site', 'An embedded, on-site resourcing partner for high-volume or ongoing requirements.' ),
		),
		'Not sure which fits?',
		'Tell us about the role and we will recommend the right approach, with no obligation.',
		'Talk to our team',
		$employers_url . '#enquiry'
	)
);

$write_page(
	$poolhall_pages['why-us'],
	$points_page(
		'Why us',
		'Recruitment built on relationships, not transactions',
		'West Midlands roots, national reach, and a genuine commitment to doing right by candidates and clients.',
		'Why us',
		'What sets us apart',
		array(
			array( 'Specialist consultants', 'Sector specialists who understand the roles, not generalists working from a script.' ),
			array( 'Honest advice', 'Straight answers, even when they are not what you hoped to hear.' ),
			array( 'Thorough vetting', 'Proper screening and referencing, so shortlists are genuinely interview-ready.' ),
			array( 'Long-term partnerships', 'We invest in relationships that last well beyond a single placement.' ),
		),
		'See it for yourself',
		'Tell us what you are hiring for and judge us on the shortlist.',
		'Start a conversation',
		$contact_url
	)
);

$write_page(
	$poolhall_pages['bespoke-search'],
	$points_page(
		'Bespoke search',
		'A dedicated search for your most important hires',
		'For senior, confidential or hard-to-fill roles, we run a focused, proactive search built around your brief.',
		'Bespoke search',
		'How a search works',
		array(
			array( 'Dedicated consultant', 'One point of contact who owns the brief from first call to offer.' ),
			array( 'Proactive headhunting', 'We approach the people who are not actively looking, discreetly.' ),
			array( 'Confidential by default', 'Sensitive and senior searches handled with full discretion.' ),
			array( 'Market insight', 'Salary benchmarking and availability data to inform your decision.' ),
		),
		'Have a critical role to fill?',
		'Tell us about it and we will scope the search with you.',
		'Discuss a search',
		$employers_url . '#enquiry'
	)
);

$write_page(
	$poolhall_pages['hr-services'],
	$points_page(
		'HR services',
		'Practical HR support for growing teams',
		'Beyond hiring, we help with the people side of running a business.',
		'HR services',
		'Support beyond hiring',
		array(
			array( 'Contracts and policies', 'Practical, compliant documents tailored to how your business works.' ),
			array( 'Onboarding', 'Help new starters settle in quickly, and stay.' ),
			array( 'Compliance', 'Right-to-work and record-keeping support to keep you covered.' ),
			array( 'On-hand advice', 'A sounding board for the day-to-day people questions.' ),
		),
		'Need a hand with HR?',
		'Tell us what you are dealing with and we will point you the right way.',
		'Ask us about HR',
		$contact_url
	)
);

$write_page(
	$poolhall_pages['commitment'],
	$points_page(
		'Our commitment',
		'How we hold ourselves accountable',
		'The standards we work to, for every candidate and every client.',
		'Our commitment',
		'The standards we work to',
		array(
			array( 'Ethical recruitment', 'We place people in roles that are right for them, not just easy to fill.' ),
			array( 'Clear communication', 'You always know where things stand.' ),
			array( 'Confidentiality', 'Your data and your search are handled with care.' ),
			array( 'No pushy tactics', 'Advice over pressure, every time.' ),
		),
		'Recruitment, done well',
		'Whether you are hiring or looking, we would like to help.',
		'Get in touch',
		$contact_url
	)
);

$write_page(
	$poolhall_pages['candidates'],
	$points_page(
		'For candidates',
		'Find work that fits',
		'Roles across construction, manufacturing and digital, with a team that listens and keeps you informed.',
		'For candidates',
		'How it works',
		array(
			array( '1. Register', 'Tell us about your experience and the kind of roles you are after.' ),
			array( '2. We match you', 'We line you up with roles that genuinely fit, and prepare you properly.' ),
			array( '3. You get hired', 'We support you through interviews, the offer and your first weeks.' ),
		),
		'Ready to find your next role?',
		'Create an account and we will be in touch about roles that match.',
		'Register your CV',
		$register_url
	)
);

// ----------------------------------------------------------- write all ----
$write_page( $poolhall_pages['better-job-adverts'], $bja_data );
$write_page( $poolhall_pages['employers'], $employers_data );
$write_page( $poolhall_pages['sectors'], $sectors_data );
$write_page( $poolhall_pages['services'], $services_data );
$write_page( $poolhall_pages['contact'], $contact_data );
$write_page( $poolhall_pages['join-our-team'], $join_data );

// Regenerate CSS and clear caches (hard rule 12).
\Elementor\Plugin::instance()->files_manager->clear_cache();

$poolhall_ids = (array) get_option( 'poolhall_template_ids', array() );
foreach ( array( 'better-job-adverts', 'employers', 'sectors', 'services', 'contact', 'join-our-team', 'delivery-options', 'why-us', 'bespoke-search', 'hr-services', 'commitment', 'candidates' ) as $poolhall_slug ) {
	$poolhall_ids[ str_replace( '-', '_', $poolhall_slug ) . '_page' ] = $poolhall_pages[ $poolhall_slug ]->ID;
}
update_option( 'poolhall_template_ids', $poolhall_ids, false );

printf(
	"Pages: BJA #%d, employers #%d, sectors #%d, services #%d, contact #%d, join-our-team #%d.\n",
	$poolhall_pages['better-job-adverts']->ID,
	$poolhall_pages['employers']->ID,
	$poolhall_pages['sectors']->ID,
	$poolhall_pages['services']->ID,
	$poolhall_pages['contact']->ID,
	$poolhall_pages['join-our-team']->ID
);
echo "Secondary pages: delivery-options, why-us, bespoke-search, hr-services, commitment, candidates built.\n";
echo "OK: marketing pages created. Verify the frontend before treating this as done.\n";
