<?php
/**
 * Elementor widgets for Poolhall's dynamic fragments.
 *
 * 02-TECHNICAL-ARCHITECTURE SS2A keeps live queries, forms and auth
 * plugin-owned, so these are deliberately thin adapters: they surface in the
 * Elementor panel as named widgets (an editor never sees a raw shortcode
 * block) and delegate rendering to the plugin's shortcode, which stays the
 * single source of truth.
 *
 * IMPORTANT: this file declares classes extending \Elementor\Widget_Base, so it
 * must only ever be required from inside an `elementor/*` hook. Requiring it
 * from functions.php at theme-load time fatals, because Elementor's classes do
 * not exist yet.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( '\Elementor\Widget_Base' ) ) { return; }
if ( class_exists( 'Poolhall_Shortcode_Widget' ) ) { return; }

abstract class Poolhall_Shortcode_Widget extends \Elementor\Widget_Base {

	const PH_NAME  = '';
	const PH_TITLE = '';
	const PH_SC    = '';
	const PH_ICON  = 'eicon-shortcode';

	public function get_name(): string { return static::PH_NAME; }
	public function get_title(): string { return static::PH_TITLE; }
	public function get_icon(): string { return static::PH_ICON; }
	public function get_categories(): array { return array( 'poolhall' ); }
	public function get_keywords(): array { return array( 'poolhall', 'jobs', 'recruitment' ); }

	protected function ph_shortcode(): string { return static::PH_SC; }

	protected function render(): void {
		$sc = $this->ph_shortcode();
		if ( '' === $sc ) { return; }
		$html = do_shortcode( $sc );
		if ( '' === trim( $html ) ) {
			$editing = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
			if ( $editing ) {
				echo '<div style="padding:18px;border:1px dashed #c3c9d1;font:13px/1.5 system-ui;color:#5B6670">'
					. esc_html( static::PH_TITLE ) . ' &mdash; renders on the live site (needs job or session context).</div>';
			}
			return;
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- plugin-rendered markup.
	}
}

final class Poolhall_W_Job_Search extends Poolhall_Shortcode_Widget {
	const PH_NAME='ph-job-search'; const PH_TITLE='Poolhall Job Search'; const PH_ICON='eicon-search';
	protected function register_controls(): void {
		$this->start_controls_section( 'ph_opts', array( 'label' => 'Search' ) );
		$this->add_control( 'variant', array( 'label' => 'Layout', 'type' => Elementor\Controls_Manager::SELECT, 'options' => array( '' => 'Standard', 'wide' => 'Wide' ), 'default' => '' ) );
		$this->end_controls_section();
	}
	protected function ph_shortcode(): string {
		$v = (string) ( $this->get_settings_for_display( 'variant' ) ?? '' );
		return 'wide' === $v ? '[poolhall_job_search variant="wide"]' : '[poolhall_job_search]';
	}
}
final class Poolhall_W_Jobs_Browser extends Poolhall_Shortcode_Widget { const PH_NAME='ph-jobs-browser'; const PH_TITLE='Poolhall Jobs Browser'; const PH_SC='[poolhall_jobs_browser]'; const PH_ICON='eicon-posts-grid'; }
final class Poolhall_W_Featured_Jobs extends Poolhall_Shortcode_Widget { const PH_NAME='ph-featured-jobs'; const PH_TITLE='Poolhall Featured Jobs'; const PH_SC='[poolhall_v2_featured_jobs]'; const PH_ICON='eicon-carousel'; }
final class Poolhall_W_Sector_Jobs extends Poolhall_Shortcode_Widget {
	const PH_NAME='ph-sector-jobs'; const PH_TITLE='Poolhall Sector Jobs'; const PH_ICON='eicon-posts-grid';
	protected function register_controls(): void {
		$this->start_controls_section( 'ph_opts', array( 'label' => 'Jobs' ) );
		$this->add_control( 'sector', array( 'label' => 'Sector', 'type' => Elementor\Controls_Manager::SELECT, 'options' => array( 'construction' => 'Construction', 'manufacturing' => 'Manufacturing', 'digital' => 'Digital' ), 'default' => 'construction' ) );
		$this->add_control( 'limit', array( 'label' => 'Number of jobs', 'type' => Elementor\Controls_Manager::NUMBER, 'default' => 2, 'min' => 1, 'max' => 4 ) );
		$this->end_controls_section();
	}
	protected function ph_shortcode(): string {
		$sector = sanitize_key( (string) ( $this->get_settings_for_display( 'sector' ) ?: 'construction' ) );
		$limit  = min( 4, max( 1, (int) ( $this->get_settings_for_display( 'limit' ) ?: 2 ) ) );
		return '[poolhall_sector_jobs sector="' . $sector . '" limit="' . $limit . '"]';
	}
}
final class Poolhall_W_Reviews extends Poolhall_Shortcode_Widget { const PH_NAME='ph-reviews'; const PH_TITLE='Poolhall Reviews'; const PH_SC='[poolhall_reviews]'; const PH_ICON='eicon-testimonial-carousel'; }
final class Poolhall_W_Enquiry_Form extends Poolhall_Shortcode_Widget {
	const PH_NAME='ph-enquiry-form'; const PH_TITLE='Poolhall Enquiry Form'; const PH_ICON='eicon-form-horizontal';
	protected function register_controls(): void {
		$this->start_controls_section( 'ph_opts', array( 'label' => 'Form' ) );
		$this->add_control( 'kind', array( 'label' => 'Enquiry type', 'type' => Elementor\Controls_Manager::SELECT, 'options' => array( 'contact' => 'General contact', 'hiring' => 'Hiring enquiry' ), 'default' => 'contact' ) );
		$this->end_controls_section();
	}
	protected function ph_shortcode(): string {
		$k = (string) ( $this->get_settings_for_display( 'kind' ) ?? 'contact' );
		return 'hiring' === $k ? '[poolhall_enquiry_form kind="hiring"]' : '[poolhall_enquiry_form]';
	}
}
final class Poolhall_W_Register_Form extends Poolhall_Shortcode_Widget { const PH_NAME='ph-register-form'; const PH_TITLE='Poolhall CV Registration Form'; const PH_SC='[poolhall_register_form]'; const PH_ICON='eicon-form-horizontal'; }
final class Poolhall_W_Job_Single extends Poolhall_Shortcode_Widget { const PH_NAME='ph-job-single'; const PH_TITLE='Poolhall Job Detail'; const PH_SC='[poolhall_v2_job_single]'; const PH_ICON='eicon-single-post'; }
final class Poolhall_W_Job_Matches extends Poolhall_Shortcode_Widget { const PH_NAME='ph-job-matches'; const PH_TITLE='Poolhall Matching Roles'; const PH_SC='[poolhall_v2_job_matches]'; const PH_ICON='eicon-posts-group'; }
final class Poolhall_W_Job_Card_Flair extends Poolhall_Shortcode_Widget { const PH_NAME='ph-job-card-flair'; const PH_TITLE='Poolhall Job Card Flair'; const PH_SC='[poolhall_job_card_flair]'; const PH_ICON='eicon-flash'; }
final class Poolhall_W_Job_Card_Meta extends Poolhall_Shortcode_Widget { const PH_NAME='ph-job-card-meta'; const PH_TITLE='Poolhall Job Card Meta'; const PH_SC='[poolhall_job_card_meta]'; const PH_ICON='eicon-meta-data'; }
final class Poolhall_W_Live_Roles extends Poolhall_Shortcode_Widget { const PH_NAME='ph-live-roles'; const PH_TITLE='Poolhall Live Roles'; const PH_SC='[poolhall_live_roles]'; const PH_ICON='eicon-bullet-list'; }
final class Poolhall_W_Apply_Button extends Poolhall_Shortcode_Widget { const PH_NAME='ph-apply-button'; const PH_TITLE='Poolhall Apply Button'; const PH_SC='[poolhall_apply_button]'; const PH_ICON='eicon-button'; }
final class Poolhall_W_Candidate_Hub extends Poolhall_Shortcode_Widget { const PH_NAME='ph-candidate-hub'; const PH_TITLE='Poolhall Candidate Tasks'; const PH_SC='[poolhall_candidate_hub]'; const PH_ICON='eicon-checkbox'; }
final class Poolhall_W_Candidate_Auth extends Poolhall_Shortcode_Widget { const PH_NAME='ph-candidate-auth'; const PH_TITLE='Poolhall Candidate Sign-in'; const PH_SC='[poolhall_candidate_auth]'; const PH_ICON='eicon-lock-user'; }
final class Poolhall_W_Audience_Switch extends Poolhall_Shortcode_Widget { const PH_NAME='ph-audience-switch'; const PH_TITLE='Poolhall Audience Switch'; const PH_SC='[poolhall_audience_switch]'; const PH_ICON='eicon-toggle'; }
final class Poolhall_W_Account_Link extends Poolhall_Shortcode_Widget { const PH_NAME='ph-account-link'; const PH_TITLE='Poolhall Account Link'; const PH_SC='[poolhall_account_link]'; const PH_ICON='eicon-user-circle-o'; }

/** Gravity Forms embed, with the form chosen from a dropdown. */
final class Poolhall_W_Gravity_Form extends Poolhall_Shortcode_Widget {
	const PH_NAME='ph-gravity-form'; const PH_TITLE='Poolhall Form (Gravity)'; const PH_ICON='eicon-form-horizontal';

	protected function register_controls(): void {
		$this->start_controls_section( 'ph_form', array( 'label' => 'Form' ) );
		$forms = array();
		if ( class_exists( 'GFAPI' ) ) {
			foreach ( (array) \GFAPI::get_forms() as $f ) { $forms[ (string) $f['id'] ] = $f['title']; }
		}
		if ( $forms ) {
			$this->add_control( 'form_id', array( 'label' => 'Form', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $forms, 'default' => (string) array_key_first( $forms ) ) );
		} else {
			$this->add_control( 'form_id', array( 'label' => 'Form ID', 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 1 ) );
		}
		$this->add_control( 'show_title', array( 'label' => 'Show title', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->add_control( 'use_ajax', array( 'label' => 'AJAX submit', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->end_controls_section();
	}

	protected function ph_shortcode(): string {
		$s  = $this->get_settings_for_display();
		$id = (int) ( $s['form_id'] ?? 0 );
		if ( $id <= 0 ) { return ''; }
		$title = ! empty( $s['show_title'] ) ? 'true' : 'false';
		$ajax  = ! empty( $s['use_ajax'] ) ? 'true' : 'false';
		return '[gravityform id="' . $id . '" title="' . $title . '" description="false" ajax="' . $ajax . '"]';
	}
}

/* Whole-page fragments whose static copy still wraps a live query or form.
   These stay plugin-rendered until the fragment is split plugin-side; the
   widget at least keeps the page free of raw shortcode blocks. */
final class Poolhall_W_Contact_Page extends Poolhall_Shortcode_Widget { const PH_NAME='ph-contact-page'; const PH_TITLE='Poolhall Contact Page'; const PH_SC='[poolhall_v2_contact]'; const PH_ICON='eicon-envelope'; }
final class Poolhall_W_Register_Page extends Poolhall_Shortcode_Widget { const PH_NAME='ph-register-page'; const PH_TITLE='Poolhall Register Page'; const PH_SC='[poolhall_v2_register_page]'; const PH_ICON='eicon-form-horizontal'; }
final class Poolhall_W_Blog_Index extends Poolhall_Shortcode_Widget { const PH_NAME='ph-blog-index'; const PH_TITLE='Poolhall Blog Index'; const PH_SC='[poolhall_v2_blog_index]'; const PH_ICON='eicon-posts-grid'; }

final class Poolhall_W_Sector_Page extends Poolhall_Shortcode_Widget {
	const PH_NAME='ph-sector-page'; const PH_TITLE='Poolhall Sector Page'; const PH_ICON='eicon-single-page';
	protected function register_controls(): void {
		$this->start_controls_section( 'ph_opts', array( 'label' => 'Sector' ) );
		$this->add_control( 'sector', array( 'label' => 'Sector', 'type' => Elementor\Controls_Manager::SELECT, 'options' => array( 'construction' => 'Construction', 'manufacturing' => 'Manufacturing', 'digital' => 'Digital' ), 'default' => 'construction' ) );
		$this->end_controls_section();
	}
	protected function ph_shortcode(): string {
		$s = sanitize_key( (string) ( $this->get_settings_for_display( 'sector' ) ?: 'construction' ) );
		return '[poolhall_v2_sector_page sector="' . $s . '"]';
	}
}

function poolhall_elementor_widget_classes(): array {
	return array(
		'Poolhall_W_Job_Search', 'Poolhall_W_Jobs_Browser', 'Poolhall_W_Featured_Jobs', 'Poolhall_W_Sector_Jobs',
		'Poolhall_W_Reviews', 'Poolhall_W_Enquiry_Form', 'Poolhall_W_Register_Form',
		'Poolhall_W_Job_Single', 'Poolhall_W_Job_Matches', 'Poolhall_W_Job_Card_Flair',
		'Poolhall_W_Job_Card_Meta', 'Poolhall_W_Live_Roles', 'Poolhall_W_Apply_Button',
		'Poolhall_W_Candidate_Hub', 'Poolhall_W_Candidate_Auth', 'Poolhall_W_Audience_Switch',
		'Poolhall_W_Account_Link', 'Poolhall_W_Gravity_Form',
		'Poolhall_W_Contact_Page', 'Poolhall_W_Register_Page', 'Poolhall_W_Blog_Index',
		'Poolhall_W_Sector_Page',
	);
}

function poolhall_register_elementor_widgets( $mgr ): void {
	foreach ( poolhall_elementor_widget_classes() as $cls ) {
		if ( class_exists( $cls ) ) { $mgr->register( new $cls() ); }
	}
}
