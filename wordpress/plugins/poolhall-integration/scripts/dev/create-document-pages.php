<?php
/**
 * Candidate document workflow setup: Gravity Forms + pages.
 *
 * Creates/updates the three document forms (full registration, starter
 * statement, candidate terms), the public /candidate-registration/ page
 * (linked from the footer Candidates column) and the two hidden,
 * noindexed direct-link pages. Run inside WordPress:
 *
 *   wp eval-file scripts/dev/create-document-pages.php
 *
 * Idempotent. No strict_types: wp eval-file runs this through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-document-pages.php\n";
	exit( 1 );
}
if ( ! class_exists( 'GFAPI' ) ) {
	echo "FAIL: Gravity Forms must be active.\n";
	exit( 1 );
}

// 1. Forms.
$poolhall_workflow = \Poolhall\Integration\Plugin::instance()->document_workflow();
$poolhall_forms    = $poolhall_workflow->setup_forms();
if ( ! isset( $poolhall_forms['full'], $poolhall_forms['starter'], $poolhall_forms['terms'] ) ) {
	echo 'FAIL: form setup incomplete: ' . wp_json_encode( $poolhall_forms ) . "\n";
	exit( 1 );
}

// 2. Pages (Elementor documents mounting the v2 pagehead + the GF shortcode).
$poolhall_eid = static fn(): string => substr( md5( uniqid( '', true ) ), 0, 7 );

$poolhall_page_data = static function ( string $head_shortcode, string $body_html, int $form_id ) use ( $poolhall_eid ): array {
	$full = static fn( array $children ): array => array(
		'id'       => $poolhall_eid(),
		'elType'   => 'container',
		'settings' => array(
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
		'elements' => $children,
		'isInner'  => false,
	);
	$widget = static fn( string $type, array $settings ): array => array(
		'id'         => $poolhall_eid(),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
		'elements'   => array(),
	);
	return array(
		$full( array( $widget( 'shortcode', array( 'shortcode' => $head_shortcode ) ) ) ),
		array(
			'id'       => $poolhall_eid(),
			'elType'   => 'container',
			'settings' => array(
				'content_width' => 'boxed',
				'boxed_width'   => array(
					'unit' => 'px',
					'size' => 900,
				),
				'padding'       => array(
					'unit'     => 'px',
					'top'      => '72',
					'right'    => '24',
					'bottom'   => '96',
					'left'     => '24',
					'isLinked' => false,
				),
			),
			'elements' => array(
				$widget( 'text-editor', array( 'editor' => $body_html ) ),
				$widget( 'shortcode', array( 'shortcode' => '[gravityform id="' . $form_id . '" title="false" description="false" ajax="false"]' ) ),
			),
			'isInner'  => false,
		),
	);
};

$poolhall_make_page = static function ( string $slug, string $title, array $data ): int {
	$page = get_page_by_path( $slug );
	if ( ! $page instanceof WP_Post ) {
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_name'   => $slug,
			)
		);
	} else {
		$page_id = $page->ID;
	}
	$prior = get_post_meta( $page_id, '_elementor_data', true );
	if ( is_string( $prior ) && '' !== $prior ) {
		update_post_meta( $page_id, '_elementor_data_backup_' . gmdate( 'Ymd_His' ), $prior );
	}
	update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' === get_post_meta( $page_id, '_wp_page_template', true ) ? 'elementor_canvas' : 'default' );
	update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
	return (int) $page_id;
};

$poolhall_reg_page = $poolhall_make_page(
	'candidate-registration',
	'Full Candidate Registration',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Full Candidate Registration" lead="The complete registration for working with Poolhall Recruitment. It takes around 10 minutes; you can save and continue later at any point. Your signed form goes securely to our team." updated=""]',
		'<div class="poolhall-form-note"><p><strong>Before you start:</strong> you&rsquo;ll need your National Insurance number, bank details for payroll, your employment history and a referee. Everything you enter is used only for your registration and payroll, as set out in our <a href="/privacy-policy/">privacy policy</a>.</p></div>',
		(int) $poolhall_forms['full']
	)
);

$poolhall_starter_page = $poolhall_make_page(
	'candidate-starter-statement',
	'Starter Statement / P45 Declaration',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Starter Statement / P45 Declaration" lead="For payroll and tax setup. Complete this only if a member of the Poolhall team has sent you this link. This is not an official HMRC P45." updated=""]',
		'<div class="poolhall-form-note"><p>If you have a P45 from your previous employer, upload it below. If not, choose the starter statement that applies to you.</p></div>',
		(int) $poolhall_forms['starter']
	)
);

$poolhall_terms_page = $poolhall_make_page(
	'candidate-terms',
	'Candidate Terms of Service',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Candidate Terms of Service" lead="Please read the terms below, then confirm and sign electronically. Complete this only if a member of the Poolhall team has sent you this link." updated=""]',
		'',
		(int) $poolhall_forms['terms']
	)
);

// 3. Hidden pages: noindex + keep out of any menus (they are simply never linked).
update_option( \Poolhall\Integration\Documents\DocumentWorkflow::HIDDEN_PAGES_OPTION, array( $poolhall_starter_page, $poolhall_terms_page ), false );

// Exclude hidden pages from search results and sitemaps too.
foreach ( array( $poolhall_starter_page, $poolhall_terms_page ) as $poolhall_hidden_id ) {
	update_post_meta( $poolhall_hidden_id, '_poolhall_hidden_document', '1' );
}

do_action( 'litespeed_purge_all' );

printf(
	"Forms: full #%d, starter #%d, terms #%d.\nPages: /candidate-registration/ #%d (footer-linked), /candidate-starter-statement/ #%d (hidden), /candidate-terms/ #%d (hidden, noindex).\nOK: document workflow setup complete. Verify the frontend before treating this as done.\n",
	$poolhall_forms['full'],
	$poolhall_forms['starter'],
	$poolhall_forms['terms'],
	$poolhall_reg_page,
	$poolhall_starter_page,
	$poolhall_terms_page
);
