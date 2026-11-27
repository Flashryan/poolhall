<?php
/**
 * Candidate document workflow setup: Gravity Forms + pages.
 *
 * Creates/updates the five candidate document forms and their hidden,
 * noindexed direct-link pages, plus the hidden /candidate-tasks/ hub.
 * Run inside WordPress:
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

// Refuse to clobber Elementor documents edited since the builder last ran.
require_once __DIR__ . '/_builder-guard.php';
if ( ! class_exists( 'GFAPI' ) ) {
	echo "FAIL: Gravity Forms must be active.\n";
	exit( 1 );
}

// 1. Forms.
$poolhall_workflow    = \Poolhall\Integration\Plugin::instance()->document_workflow();
$poolhall_forms       = $poolhall_workflow->setup_forms();
$poolhall_form_errors = (array) get_option( \Poolhall\Integration\Documents\DocumentWorkflow::SETUP_ERRORS_OPTION, array() );
if ( ! isset( $poolhall_forms['full'], $poolhall_forms['contract'], $poolhall_forms['starter'], $poolhall_forms['terms'], $poolhall_forms['payroll'] ) || array() !== $poolhall_form_errors ) {
	echo 'FAIL: form setup incomplete: ' . wp_json_encode( $poolhall_forms ) . "\n";
	if ( array() !== $poolhall_form_errors ) {
		echo 'Form API errors: ' . wp_json_encode( $poolhall_form_errors ) . "\n";
	}
	exit( 1 );
}

// 2. Pages (Elementor documents mounting the v2 pagehead + task shortcode).
$poolhall_eid = static fn(): string => substr( md5( uniqid( '', true ) ), 0, 7 );

$poolhall_page_data = static function ( string $head_shortcode, string $body_html, string $task_shortcode ) use ( $poolhall_eid ): array {
	$full   = static fn( array $children ): array => array(
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
				$widget( 'shortcode', array( 'shortcode' => $task_shortcode ) ),
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
	$poolhall_page_template = get_post_meta( $page_id, '_wp_page_template', true );
	if ( 'elementor_canvas' !== $poolhall_page_template ) {
		$poolhall_page_template = 'default';
	}
	update_post_meta( $page_id, '_wp_page_template', $poolhall_page_template );
	if ( poolhall_builder_may_write( (int) $page_id ) ) {
		update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		poolhall_builder_stamp( (int) $page_id );
	}
	update_post_meta( $page_id, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
	return (int) $page_id;
};

$poolhall_form_shortcode = static fn( int $form_id ): string => '[gravityform id="' . $form_id . '" title="false" description="false" ajax="false"]';
$poolhall_terms_ready    = \Poolhall\Integration\Documents\FormDefinitions::candidate_terms_ready();

$poolhall_reg_page = $poolhall_make_page(
	'candidate-registration',
	'Complete Your Candidate Profile',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Complete Your Candidate Profile" lead="Complete this after Poolhall has reviewed your initial registration and invited you to progress. You can save and finish later using the link in the form." updated=""]',
		'<div class="poolhall-form-note"><p><strong>Before you start:</strong> have your employment history and referee details ready. Your CV and initial contact details are reused where possible; payroll and starter information is requested separately only when needed. See our <a href="/privacy-policy/">privacy policy</a> for how we use your information.</p></div>',
		$poolhall_form_shortcode( (int) $poolhall_forms['full'] )
	)
);

$poolhall_starter_page = $poolhall_make_page(
	'candidate-starter-statement',
	'Starter Checklist',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Starter checklist" lead="HMRC\'s starter checklist, completed online. Payroll uses it to set your tax code and any student loan repayments. Complete it only if Poolhall has sent you a link." updated=""]',
		'<div class="poolhall-form-note"><p>It takes about five minutes. Have your National Insurance number to hand, and your P45 if you have one from a job you\'ve left.</p></div>',
		$poolhall_form_shortcode( (int) $poolhall_forms['starter'] )
	)
);

$poolhall_contract_page = $poolhall_make_page(
	'candidate-agency-worker-contract',
	'Agency Worker Contract',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Agency Worker Contract" lead="Review and sign the Contract for Services issued after your offer has been accepted." updated=""]',
		'<div class="poolhall-form-note"><p>Your name, contract effective date and address will appear on page one. Your electronic signature, printed name and signing date will appear on the final page.</p></div>',
		$poolhall_form_shortcode( (int) $poolhall_forms['contract'] )
	)
);

$poolhall_terms_page = $poolhall_make_page(
	'candidate-terms',
	'Candidate Terms of Service',
	$poolhall_page_data(
		$poolhall_terms_ready
			? '[poolhall_v2_legal_head crumb="Candidates" title="Candidate Terms of Service" lead="Read and sign the approved terms issued by Poolhall Recruitment." updated=""]'
			: '[poolhall_v2_legal_head crumb="Candidates" title="Candidate Terms of Service" lead="This task will be issued only after Poolhall Recruitment has approved the final legal wording." updated=""]',
		$poolhall_terms_ready
			? '<div class="poolhall-form-note"><p>Read the full wording carefully before confirming and signing.</p></div>'
			: '<div class="poolhall-form-note"><p>This form is currently a draft and cannot be completed or sent to candidates until Poolhall Recruitment approves the final terms.</p></div>',
		$poolhall_form_shortcode( (int) $poolhall_forms['terms'] )
	)
);

$poolhall_payroll_page = $poolhall_make_page(
	'candidate-payroll-details',
	'Payroll Details',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Payroll Details" lead="Provide payment and emergency-contact information only after Poolhall has approved you to progress and sent your secure task link." updated=""]',
		'<div class="poolhall-form-note"><p>Have your National Insurance number and bank or building society details ready. Check the account number and sort code carefully before submitting.</p></div>',
		$poolhall_form_shortcode( (int) $poolhall_forms['payroll'] )
	)
);

$poolhall_tasks_page = $poolhall_make_page(
	'candidate-tasks',
	'Candidate Tasks',
	$poolhall_page_data(
		'[poolhall_v2_legal_head crumb="Candidates" title="Candidate Tasks" lead="Your secure onboarding checklist from Poolhall Recruitment." updated=""]',
		'<div class="poolhall-form-note"><p>Complete only the tasks shown as available. Poolhall will unlock and send each task when it is needed.</p></div>',
		'[poolhall_candidate_hub]'
	)
);

// 3. Every workflow page is direct-link only, noindexed and excluded from search/sitemaps.
$poolhall_hidden_pages = array(
	$poolhall_reg_page,
	$poolhall_contract_page,
	$poolhall_starter_page,
	$poolhall_terms_page,
	$poolhall_payroll_page,
	$poolhall_tasks_page,
);
update_option( \Poolhall\Integration\Documents\DocumentWorkflow::HIDDEN_PAGES_OPTION, $poolhall_hidden_pages, false );

foreach ( $poolhall_hidden_pages as $poolhall_hidden_id ) {
	update_post_meta( $poolhall_hidden_id, '_poolhall_hidden_document', '1' );
}

if ( \Poolhall\Integration\Onboarding\TaskPolicy::archived( 'terms' ) ) {
	( new \Poolhall\Integration\Onboarding\TermsArchive() )->set_archived( true );
}

do_action( 'litespeed_purge_all' );

echo esc_html(
	sprintf(
		"Forms: profile #%d, contract #%d, starter #%d, terms #%d (%s), payroll #%d.\nPages: /candidate-registration/ #%d, /candidate-agency-worker-contract/ #%d, /candidate-starter-statement/ #%d, /candidate-terms/ #%d, /candidate-payroll-details/ #%d, /candidate-tasks/ #%d (all hidden, noindex).\nOK: document workflow setup complete. Verify the frontend before treating this as done.\n",
		$poolhall_forms['full'],
		$poolhall_forms['contract'],
		$poolhall_forms['starter'],
		$poolhall_forms['terms'],
		\Poolhall\Integration\Onboarding\TaskPolicy::archived( 'terms' ) ? 'archived/inactive' : ( $poolhall_terms_ready ? 'approved/active' : 'draft/inactive' ),
		$poolhall_forms['payroll'],
		$poolhall_reg_page,
		$poolhall_contract_page,
		$poolhall_starter_page,
		$poolhall_terms_page,
		$poolhall_payroll_page,
		$poolhall_tasks_page
	)
);
