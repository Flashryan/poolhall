<?php
/**
 * Phase 4: jobs user experience — v3 Loop Item templates, the jobs archive
 * Loop Grid and the single-job Theme Builder template.
 *
 *   wp eval-file scripts/dev/create-jobs-templates.php
 *
 * Follows the design system (doc 10): v3 loops only for query-backed
 * repeated content (§1/§12), classes mirror the v4 registry instead of raw
 * values (§12), archive grid is one column with query ID
 * `poolhall_jobs_archive` (§13), single job uses the navy hero and apply
 * sidebar (§14). Apply remains a contact CTA while the application mode is
 * unset (hard rules 5/15 — no fake apply, no Giig hosted-form proxy).
 *
 * Idempotent: templates are found by title and updated, with an
 * _elementor_data backup taken before every write (hard rule 11).
 *
 * No strict_types: wp eval-file runs through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-jobs-templates.php\n";
	exit( 1 );
}
if ( ! did_action( 'elementor/loaded' ) || ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
	echo "FAIL: Elementor + Elementor Pro must be active.\n";
	exit( 1 );
}

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
// Dynamic tag value for a widget setting (Elementor's serialized tag format).
$tag = static fn( string $name, array $settings = array() ): string => sprintf(
	'[elementor-tag id="%s" name="%s" settings="%s"]',
	$eid(),
	$name,
	rawurlencode( wp_json_encode( (object) $settings ) )
);

$ensure_template = static function ( string $title, string $type, array $data, ?array $conditions = null ) {
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
	update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	if ( null !== $conditions ) {
		update_post_meta( $post_id, '_elementor_conditions', $conditions );
	}
	wp_set_object_terms( $post_id, $type, 'elementor_library_type' );

	return $post_id;
};

// ------------------------------------------------- loop item templates ----
// §12 `PH Loop - Job Featured Card`: vertical card for the Home carousel
// (anatomy per directive §4.1: flair row, serif 600 title, icon meta row +
// clamped summary, divider, salary + View job footer; the child theme
// paints the featured top border off the gold pill's presence).
$featured_card = array(
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
			'css_classes'    => 'ph-card ph-card--job ph-card--interactive',
		),
		array(
			$widget( 'shortcode', array( 'shortcode' => '[poolhall_job_card_flair]' ) ),
			$widget(
				'heading',
				array(
					'title'       => '',
					'header_size' => 'h3',
					'title_color' => '#1B3052',
					'_css_classes' => 'ph-h3',
					'__dynamic__' => array(
						'title' => $tag( 'post-title' ),
						'link'  => $tag( 'post-url' ),
					),
				)
			),
			$widget( 'shortcode', array( 'shortcode' => '[poolhall_job_card_meta]' ) ),
			$widget( 'divider', array( 'color' => '#E3E7ED' ) ),
			$container(
				array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_justify_content' => 'space-between',
					'flex_align_items'     => 'center',
					'flex_wrap'            => 'wrap',
					'flex_gap'             => array(
						'unit'   => 'px',
						'size'   => 8,
						'column' => '8',
						'row'    => '8',
					),
				),
				array(
					$widget(
						'heading',
						array(
							'title'       => '',
							'header_size' => 'p',
							'_css_classes' => 'ph-job-salary',
							'__dynamic__' => array( 'title' => $tag( 'post-custom-field', array( 'custom_key' => 'salary_display' ) ) ),
						)
					),
					$widget(
						'heading',
						array(
							'title'       => 'View job',
							'header_size' => 'p',
							'title_color' => '#B9510E',
							'_css_classes' => 'ph-link ph-link--arrow',
							'__dynamic__' => array( 'link' => $tag( 'post-url' ) ),
						)
					),
				),
				true
			),
		)
	),
);

// §12 `PH Loop - Job Result Row`: archive row — horizontal on desktop,
// vertical card on mobile (container direction flips at the mobile break).
$result_row = array(
	$container(
		array(
			'content_width'          => 'full',
			'flex_direction'         => 'row',
			'flex_direction_mobile'  => 'column',
			'flex_justify_content'   => 'space-between',
			'flex_align_items'       => 'center',
			'flex_align_items_mobile' => 'flex-start',
			'flex_gap'               => array(
				'unit'   => 'px',
				'size'   => 16,
				'column' => '16',
				'row'    => '16',
			),
			'css_classes'            => 'ph-card ph-card--job',
		),
		array(
			$container(
				array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 6,
						'column' => '6',
						'row'    => '6',
					),
				),
				array(
					$widget( 'shortcode', array( 'shortcode' => '[poolhall_job_card_flair]' ) ),
					$widget(
						'heading',
						array(
							'title'       => '',
							'header_size' => 'h3',
							'title_color' => '#1B3052',
							'_css_classes' => 'ph-h3',
							'__dynamic__' => array(
								'title' => $tag( 'post-title' ),
								'link'  => $tag( 'post-url' ),
							),
						)
					),
					$widget( 'shortcode', array( 'shortcode' => '[poolhall_job_card_meta]' ) ),
				),
				true
			),
			$container(
				array(
					'content_width'    => 'full',
					'flex_direction'   => 'column',
					'flex_align_items' => 'flex-end',
					'flex_align_items_mobile' => 'flex-start',
					'flex_gap'         => array(
						'unit'   => 'px',
						'size'   => 8,
						'column' => '8',
						'row'    => '8',
					),
					'width'            => array(
						'unit' => '%',
						'size' => 30,
					),
					'width_mobile'     => array(
						'unit' => '%',
						'size' => 100,
					),
				),
				array(
					$widget(
						'heading',
						array(
							'title'       => '',
							'header_size' => 'p',
							'_css_classes' => 'ph-job-salary',
							'__dynamic__' => array( 'title' => $tag( 'post-custom-field', array( 'custom_key' => 'salary_display' ) ) ),
						)
					),
					$widget(
						'heading',
						array(
							'title'       => 'View job',
							'header_size' => 'p',
							'title_color' => '#B9510E',
							'_css_classes' => 'ph-link ph-link--arrow',
							'__dynamic__' => array( 'link' => $tag( 'post-url' ) ),
						)
					),
				),
				true
			),
		)
	),
);

$featured_card_id = $ensure_template( 'PH Loop - Job Featured Card', 'loop-item', $featured_card );
$result_row_id    = $ensure_template( 'PH Loop - Job Result Row', 'loop-item', $result_row );

// ----------------------------------------------------- jobs archive page ----
// §13: paper surface, H1 left, one-column Loop Grid, numbered pagination.
// Filters/sort arrive with the custom results widget (plugin, later phase).
$jobs_page = get_page_by_path( 'jobs' );
if ( ! $jobs_page instanceof WP_Post ) {
	echo "FAIL: jobs page missing — run create-theme-shell.php first.\n";
	exit( 1 );
}

$archive_data = array(
	$container(
		array(
			'content_width' => 'full',
			'css_classes'   => 'ph-section--navy ph-hero--slim',
		),
		array(
			$container(
				array(
					'content_width'  => 'boxed',
					'boxed_width'    => array(
						'unit' => 'px',
						'size' => 1152,
					),
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 8,
						'column' => '8',
						'row'    => '8',
					),
				),
				array(
					$widget(
						'heading',
						array(
							'title'       => 'Find your next role',
							'header_size' => 'h1',
							'title_color' => '#FFFFFF',
							'_css_classes' => 'ph-h1',
						)
					),
					$widget(
						'heading',
						array(
							'title'       => 'Live roles across Construction, Manufacturing and Digital/Marketing in the West Midlands and beyond.',
							'header_size' => 'p',
							'title_color' => '#A7B4C8',
							'_css_classes' => 'ph-body-lg',
						)
					),
				),
				true
			),
		)
	),
	$container(
		array(
			'content_width'         => 'full',
			'background_background' => 'classic',
			'background_color'      => '#F7F8FA',
			'css_classes'           => 'ph-section',
		),
		array(
			$container(
				array(
					'content_width'  => 'boxed',
					'boxed_width'    => array(
						'unit' => 'px',
						'size' => 1152,
					),
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 32,
						'column' => '32',
						'row'    => '32',
					),
				),
				array(
					// §11 archive search: same server-rendered form as the
					// home hero, full container width, values retained.
					$widget( 'shortcode', array( 'shortcode' => '[poolhall_job_search variant="wide"]' ) ),
					$widget(
						'loop-grid',
						array(
							'_skin'                => 'post',
							'template_id'          => $result_row_id,
							'posts_per_page'       => 10,
							'columns'              => 1,
							'columns_tablet'       => 1,
							'columns_mobile'       => 1,
							'pagination_type'      => 'numbers_and_prev_next',
							'post_query_post_type' => 'poolhall_job',
							'post_query_orderby'   => 'date',
							'post_query_order'     => 'DESC',
							'post_query_query_id'  => 'poolhall_jobs_archive',
						)
					),
				),
				true
			),
		)
	),
);

// Backup before modifying Elementor data (hard rule 11).
$prior = get_post_meta( $jobs_page->ID, '_elementor_data', true );
if ( ! empty( $prior ) ) {
	update_option( 'poolhall_tpl_backup_' . $jobs_page->ID . '_' . gmdate( 'Ymd_His' ), $prior, false );
}
update_post_meta( $jobs_page->ID, '_elementor_edit_mode', 'builder' );
update_post_meta( $jobs_page->ID, '_elementor_template_type', 'wp-page' );
update_post_meta( $jobs_page->ID, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $jobs_page->ID, '_elementor_data', wp_slash( wp_json_encode( $archive_data ) ) );

// ------------------------------------------------- single job template ----
// §14: navy hero with sector/title/meta, content + apply-card sidebar.
// The sidebar CTA routes to Contact while the application mode is unset.
$contact_url = get_permalink( get_page_by_path( 'contact' )->ID ?? 0 );

$single_data = array(
	$container(
		array(
			'content_width' => 'full',
			'css_classes'   => 'ph-section--navy ph-hero--job',
		),
		array(
			$container(
				array(
					'content_width'  => 'boxed',
					'boxed_width'    => array(
						'unit' => 'px',
						'size' => 1152,
					),
					'flex_direction' => 'column',
					'flex_gap'       => array(
						'unit'   => 'px',
						'size'   => 10,
						'column' => '10',
						'row'    => '10',
					),
				),
				array(
					$widget(
						'heading',
						array(
							'title'       => '',
							'header_size' => 'p',
							'title_color' => '#F4904A',
							'_css_classes' => 'ph-eyebrow',
							'__dynamic__' => array( 'title' => $tag( 'post-terms', array( 'taxonomy' => 'poolhall_sector' ) ) ),
						)
					),
					$widget(
						'heading',
						array(
							'title'       => '',
							'header_size' => 'h1',
							'title_color' => '#FFFFFF',
							'_css_classes' => 'ph-h1',
							'__dynamic__' => array( 'title' => $tag( 'post-title' ) ),
						)
					),
					$widget(
						'heading',
						array(
							'title'       => '',
							'header_size' => 'p',
							'title_color' => '#A7B4C8',
							'_css_classes' => 'ph-small',
							'__dynamic__' => array( 'title' => $tag( 'post-custom-field', array( 'custom_key' => 'location_display' ) ) ),
						)
					),
				),
				true
			),
		)
	),
	$container(
		array(
			'content_width'         => 'full',
			'background_background' => 'classic',
			'background_color'      => '#F7F8FA',
			'css_classes'           => 'ph-section',
		),
		array(
			$container(
				array(
					'content_width'        => 'boxed',
					'boxed_width'          => array(
						'unit' => 'px',
						'size' => 1152,
					),
					'flex_direction'        => 'row',
					'flex_direction_mobile' => 'column',
					'flex_align_items'      => 'flex-start',
					'flex_gap'              => array(
						'unit'   => 'px',
						'size'   => 40,
						'column' => '40',
						'row'    => '40',
					),
				),
				array(
					$container(
						array(
							'content_width' => 'full',
							'flex_grow'     => 1,
							'width'         => array(
								'unit' => '%',
								'size' => 64,
							),
							'width_mobile'  => array(
								'unit' => '%',
								'size' => 100,
							),
						),
						array(
							$widget( 'theme-post-content', array() ),
						),
						true
					),
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
								'unit' => '%',
								'size' => 32,
							),
							'width_mobile'   => array(
								'unit' => '%',
								'size' => 100,
							),
							'css_classes'    => 'ph-card ph-sticky-card',
						),
						array(
							$widget(
								'heading',
								array(
									'title'       => '',
									'header_size' => 'p',
									'_css_classes' => 'ph-job-salary',
									'__dynamic__' => array( 'title' => $tag( 'post-custom-field', array( 'custom_key' => 'salary_display' ) ) ),
								)
							),
							$widget(
								'heading',
								array(
									'title'       => '',
									'header_size' => 'p',
									'title_color' => '#586375',
									'_css_classes' => 'ph-data',
									'__dynamic__' => array( 'title' => $tag( 'post-custom-field', array( 'custom_key' => 'job_reference' ) ) ),
								)
							),
							$widget(
								'heading',
								array(
									'title'       => '',
									'header_size' => 'p',
									'title_color' => '#586375',
									'_css_classes' => 'ph-small',
									'__dynamic__' => array( 'title' => $tag( 'post-custom-field', array( 'custom_key' => 'location_display' ) ) ),
								)
							),
							$widget( 'divider', array( 'color' => '#E3E7ED' ) ),
							$widget(
								'button',
								array(
									'text'                          => 'Contact us about this role',
									'link'                          => array(
										'url'         => $contact_url,
										'is_external' => '',
										'nofollow'    => '',
									),
									'background_color'              => '#EC6F1E',
									'button_background_hover_color' => '#D45F12',
									'button_text_color'             => '#FFFFFF',
									'hover_color'                   => '#FFFFFF',
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
		)
	),
);

$single_id = $ensure_template( 'PH Single Job', 'single', $single_data, array( 'include/singular/poolhall_job' ) );

// Rebuild Pro's conditions cache so the single template displays.
if ( class_exists( '\ElementorPro\Plugin' ) ) {
	$theme_builder = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
	if ( $theme_builder && method_exists( $theme_builder->get_conditions_manager(), 'get_cache' ) ) {
		$theme_builder->get_conditions_manager()->get_cache()->regenerate();
	}
}
\Elementor\Plugin::instance()->files_manager->clear_cache();

// Template inventory (§12: every Loop Item has an ID and usage record).
update_option(
	'poolhall_template_ids',
	array(
		'loop_job_featured_card' => $featured_card_id,
		'loop_job_result_row'    => $result_row_id,
		'single_job'             => $single_id,
		'jobs_archive_page'      => $jobs_page->ID,
	),
	false
);

echo "Loop items: featured card #{$featured_card_id}, result row #{$result_row_id}.\n";
echo "Jobs archive: page #{$jobs_page->ID} (query ID poolhall_jobs_archive, 10/page, 1 column).\n";
echo "Single job: template #{$single_id} (condition: singular/poolhall_job).\n";
echo "OK: jobs templates created. Verify the frontend before treating this as done.\n";
