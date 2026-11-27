<?php
/**
 * Passwordless candidate task hub.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Documents\DocumentWorkflow;

/**
 * Stages a one-time emailed capability, asks the candidate to explicitly
 * continue on a clean URL, then renders only the checklist and forms that
 * the resulting browser session permits.
 */
final class CandidateHub {

	public const SHORTCODE        = 'poolhall_candidate_hub';
	public const ELEMENTOR_WIDGET = 'ph-candidate-hub';
	public const TASK_ARG         = 'task';

	private const FORM_MAP_OPTION = 'poolhall_document_forms';
	private const CONTINUE_ACTION = 'poolhall_candidate_continue';

	public function __construct(
		private readonly InviteManager $invites = new InviteManager(),
		private readonly WorkflowState $state = new WorkflowState(),
		private readonly DraftManager $drafts = new DraftManager(),
	) {
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_request' ), 0 );
		add_action( 'send_headers', array( $this, 'send_security_headers' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	public static function hub_url(): string {
		return home_url( '/' . InviteManager::HUB_SLUG . '/' );
	}

	public static function task_url( string $kind ): string {
		return add_query_arg( self::TASK_ARG, sanitize_key( $kind ), self::hub_url() );
	}

	/** Whether saved page markup contains a supported Candidate Tasks renderer. */
	public static function is_embedded( string $markup ): bool {
		return str_contains( $markup, '[' . self::SHORTCODE )
			|| str_contains( $markup, self::ELEMENTOR_WIDGET );
	}

	/**
	 * A GET only stages the bearer and removes it from browser history. The
	 * clean hub then requires an explicit candidate POST before the invite is
	 * consumed and converted to an HttpOnly session. Link-preview scanners do
	 * not invalidate invitations merely by following them.
	 */
	public function handle_request(): void {
		if ( ! $this->is_hub_request() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'Private candidate task hub' );

		$this->send_security_headers();

		// A native GF token is never accepted from a browser-visible URL.
		if ( isset( $_GET['gf_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only credential stripping.
			wp_safe_redirect( self::hub_url(), 303 );
			exit;
		}

		if ( isset( $_GET[ InviteManager::ACCESS_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The signed one-time value is staged, not consumed, on GET.
			$token    = sanitize_text_field( wp_unslash( $_GET[ InviteManager::ACCESS_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated cryptographically by InviteManager.
			$prepared = $this->invites->prepare_access( $token );

			// Never fall back to a different candidate's prior browser session
			// when any emailed link is opened on a shared device.
			$this->invites->clear_browser_session();
			if ( ! $prepared ) {
				$this->invites->clear_pending_access();
			}
			wp_safe_redirect( self::hub_url(), 303 );
			exit;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'POST' !== $method || ! isset( $_POST[ self::CONTINUE_ACTION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- SameSite pending cookie is the one-time credential; no guest-global WP nonce.
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST[ self::CONTINUE_ACTION ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- See method-level credential boundary above.
		if ( 'continue' === $action ) {
			$this->invites->accept_pending();
		}
		wp_safe_redirect( self::hub_url(), 303 );
		exit;
	}

	/** Send privacy headers on every hub response, including redirects. */
	public function send_security_headers(): void {
		if ( ! $this->is_hub_request() || headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	/** @param array<string,bool> $robots WordPress robots directives. */
	public function robots( array $robots ): array {
		if ( ! $this->is_hub_request() ) {
			return $robots;
		}
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;
		return $robots;
	}

	/** Passwordless checklist and optionally the selected task's form. */
	public function render( mixed $attributes = array() ): string {
		unset( $attributes );
		wp_enqueue_style(
			'poolhall-documents',
			POOLHALL_INTEGRATION_URL . 'assets/css/documents.css',
			array(),
			POOLHALL_INTEGRATION_VERSION
		);
		$context = $this->invites->session_context();
		if ( null === $context ) {
			if ( $this->invites->has_pending_access() ) {
				return $this->render_continue();
			}
			return '<div class="poolhall-form-note poolhall-form-final"><h2>'
				. esc_html__( 'This secure link has expired', 'poolhall-integration' )
				. '</h2><p>'
				. esc_html__( 'Ask Poolhall Recruitment for a fresh personal link. No candidate information is available from this page without one.', 'poolhall-integration' )
				. '</p></div>';
		}

		$record_id = $context['record_id'];
		$stage     = $context['stage'];
		$selected  = isset( $_GET[ self::TASK_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::TASK_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only task selection; session authorises it.
		$first     = trim( (string) get_post_meta( $record_id, 'applicant_first', true ) );
		if ( '' !== $first ) {
			/* translators: %s: candidate first name. */
			$heading = sprintf( __( 'Welcome, %s', 'poolhall-integration' ), $first );
		} else {
			$heading = __( 'Your next steps', 'poolhall-integration' );
		}

		$html  = '<div class="poolhall-candidate-hub">';
		// ph3-eyebrow is the Elementor global class every page eyebrow uses, so
		// this one takes its look (and any later change to it) from Elementor.
		$html .= '<header class="poolhall-candidate-hub__header"><p class="poolhall-eyebrow ph3-eyebrow">'
			. esc_html__( 'Candidate tasks', 'poolhall-integration' ) . '</p><h1>'
			. esc_html( $heading )
			. '</h1><p>' . esc_html__( 'Complete only the items shown below. Your progress and documents are not exposed through the page address.', 'poolhall-integration' ) . '</p></header>';
		$html .= $this->render_checklist( $record_id, $stage, $selected );
		if ( '' !== $selected ) {
			$html .= $this->render_task( $record_id, $stage, $selected );
		}
		$html .= '</div>';

		return $html;
	}

	/** Human confirmation boundary between a scanner-safe GET and consumption. */
	private function render_continue(): string {
		return '<div class="poolhall-form-note poolhall-form-final"><h2>'
			. esc_html__( 'Open your secure candidate tasks', 'poolhall-integration' )
			. '</h2><p>'
			. esc_html__( 'Select Continue to confirm you opened this personal link. Your invitation is only used at that point.', 'poolhall-integration' )
			. '</p><form method="post" action="' . esc_url( self::hub_url() ) . '">'
			. '<button class="button button-primary" type="submit" name="' . esc_attr( self::CONTINUE_ACTION ) . '" value="continue">'
			. esc_html__( 'Continue to Candidate Tasks', 'poolhall-integration' )
			. '</button></form></div>';
	}

	private function render_checklist( int $record_id, string $stage, string $selected ): string {
		$labels   = array(
			'full'     => __( 'Complete your candidate profile', 'poolhall-integration' ),
			'terms'    => __( 'Read and sign your terms', 'poolhall-integration' ),
			'starter'  => __( 'Complete your starter checklist', 'poolhall-integration' ),
			'payroll'  => __( 'Payroll details', 'poolhall-integration' ),
			'contract' => __( 'Read and sign your Agency Worker Contract', 'poolhall-integration' ),
		);
		$statuses = $this->state->checklist( $record_id );
		$items    = '';

		foreach ( TaskPolicy::tasks() as $kind ) {
			if ( ! $this->invites->stage_allows( $stage, $kind ) ) {
				continue;
			}
			$status = (string) ( $statuses[ $kind ] ?? 'not_required' );
			if ( 'not_required' === $status ) {
				continue;
			}
			$active = in_array( $status, array( 'awaiting', 'in_progress', 'needs_correction' ), true );
			$action = $active
				? '<a class="button' . ( $selected === $kind ? ' button-primary' : '' ) . '" href="' . esc_url( self::task_url( $kind ) ) . '">'
					. esc_html( 'in_progress' === $status ? __( 'Continue', 'poolhall-integration' ) : __( 'Open', 'poolhall-integration' ) ) . '</a>'
				: '<span class="poolhall-task__complete">' . esc_html__( 'Complete', 'poolhall-integration' ) . '</span>';
			$items .= '<li class="poolhall-task poolhall-task--' . esc_attr( $status ) . '"><div><strong>'
				. esc_html( (string) ( $labels[ $kind ] ?? $kind ) ) . '</strong><span class="poolhall-task__status">'
				. esc_html( $this->status_label( $status ) ) . '</span></div>' . $action . '</li>';
		}

		if ( '' === $items ) {
			$items = '<li class="poolhall-task"><p>' . esc_html__( 'There are no outstanding tasks at the moment.', 'poolhall-integration' ) . '</p></li>';
		}

		return '<section class="poolhall-candidate-hub__checklist" aria-labelledby="poolhall-task-heading"><h2 id="poolhall-task-heading">'
			. esc_html__( 'Your checklist', 'poolhall-integration' ) . '</h2><ul>' . $items . '</ul></section>';
	}

	private function render_task( int $record_id, string $stage, string $kind ): string {
		if ( ! in_array( $kind, WorkflowState::TASKS, true ) || ! $this->invites->stage_allows( $stage, $kind ) ) {
			return $this->task_message( __( 'That task is not available from this link.', 'poolhall-integration' ) );
		}

		// The submission was accepted earlier in this request, so the task now
		// reads as submitted: show Gravity Forms' thank-you instead of "already
		// completed". just_submitted() only holds for a validated final submit.
		$form_id = $this->form_id( $kind );
		if ( $form_id > 0 && function_exists( 'gravity_form' ) && DocumentWorkflow::just_submitted( $form_id ) ) {
			return '<section class="poolhall-candidate-hub__form" aria-labelledby="poolhall-current-task"><h2 id="poolhall-current-task">'
				. esc_html__( 'Task complete', 'poolhall-integration' ) . '</h2>' . $this->render_gravity_form( $form_id, array(), '' ) . '</section>';
		}

		$status = $this->state->task_status( $record_id, $kind );
		if ( ! in_array( $status, array( 'awaiting', 'in_progress', 'needs_correction' ), true ) || $this->invites->record_for_kind( $kind ) !== $record_id ) {
			return $this->task_message( __( 'This task has already been completed or is no longer available.', 'poolhall-integration' ) );
		}

		if ( $form_id <= 0 || ! function_exists( 'gravity_form' ) ) {
			return $this->task_message( __( 'This form is temporarily unavailable. Please contact Poolhall Recruitment.', 'poolhall-integration' ) );
		}

		if ( function_exists( 'gravity_form_enqueue_scripts' ) ) {
			gravity_form_enqueue_scripts( $form_id, false );
		}

		$field_values = apply_filters( 'poolhall_candidate_hub_field_values', array(), $record_id, $kind, $form_id );
		if ( ! is_array( $field_values ) ) {
			$field_values = array();
		}

		$resume_token = DraftManager::PROFILE_KIND === $kind ? $this->drafts->resume_token( $record_id ) : '';
		$form_html    = $this->render_gravity_form( $form_id, $field_values, $resume_token );

		return '<section class="poolhall-candidate-hub__form" aria-labelledby="poolhall-current-task"><h2 id="poolhall-current-task">'
			. esc_html__( 'Current task', 'poolhall-integration' ) . '</h2>' . $form_html . '</section>';
	}

	/**
	 * The native credential exists in PHP memory only for the synchronous GF
	 * render. REQUEST_URI, emails and generated hub links remain token-free.
	 *
	 * @param array<string,mixed> $field_values Dynamic population values.
	 */
	private function render_gravity_form( int $form_id, array $field_values, string $resume_token ): string {
		if ( '' !== $resume_token ) {
			$_GET['gf_token']     = $resume_token;
			$_REQUEST['gf_token'] = $resume_token;
		}

		try {
			$html = gravity_form( $form_id, false, false, false, $field_values, false, 0, false );
			return is_string( $html ) ? $html : '';
		} finally {
			unset( $_GET['gf_token'], $_REQUEST['gf_token'] );
		}
	}

	private function form_id( string $kind ): int {
		$map = (array) get_option( self::FORM_MAP_OPTION, array() );
		return isset( $map[ $kind ] ) ? (int) $map[ $kind ] : 0;
	}

	private function status_label( string $status ): string {
		return match ( $status ) {
			'awaiting'         => __( 'Not started', 'poolhall-integration' ),
			'in_progress'      => __( 'In progress', 'poolhall-integration' ),
			'needs_correction' => __( 'Update requested', 'poolhall-integration' ),
			'submitted'        => __( 'Submitted for review', 'poolhall-integration' ),
			'accepted'         => __( 'Accepted', 'poolhall-integration' ),
			default            => __( 'Not required', 'poolhall-integration' ),
		};
	}

	private function task_message( string $message ): string {
		return '<div class="poolhall-form-note"><p>' . esc_html( $message ) . '</p></div>';
	}

	private function is_hub_request(): bool {
		if ( function_exists( 'is_page' ) && is_page( InviteManager::HUB_SLUG ) ) {
			return true;
		}
		$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path    = wp_parse_url( $request, PHP_URL_PATH );
		return is_string( $path ) && trim( $path, '/' ) === InviteManager::HUB_SLUG;
	}
}
