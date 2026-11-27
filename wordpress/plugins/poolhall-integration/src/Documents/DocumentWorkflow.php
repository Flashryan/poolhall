<?php
/**
 * Candidate document workflow: forms, PDFs, notifications.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

use Poolhall\Integration\Applications\CandidateIdentity;
use Poolhall\Integration\Onboarding\InviteManager;
use Poolhall\Integration\Onboarding\TaskPolicy;
use Poolhall\Integration\Onboarding\WorkflowState;
use Poolhall\Integration\Source\Giig\GiigCandidateSink;
use Poolhall\Integration\Support\BrandedMail;
use Poolhall\Integration\Support\Logger;
use Poolhall\Integration\Support\PrivateStorage;

/**
 * Glues Gravity Forms to the document pipeline: registers the custom
 * signature field, creates/updates the five document forms, and on
 * submission builds a branded PDF in the protected store, emails the
 * admin a data-free entry link, sends the candidate a data-free receipt,
 * and records the delivery attempt. Sensitive PDFs and uploads are never
 * attached to ordinary notification email.
 *
 * Never logged or emailed to candidates: bank details, NI numbers,
 * health answers, signature data, or any full form payload.
 */
final class DocumentWorkflow {

	public const FORM_MAP_OPTION     = 'poolhall_document_forms';
	public const HIDDEN_PAGES_OPTION = 'poolhall_hidden_doc_pages';
	public const SETUP_ERRORS_OPTION = 'poolhall_document_setup_errors';
	private const ADMIN_EMAIL        = 'jobs@poolhallrecruitment.co.uk';

	public function __construct(
		private readonly Logger $logger,
		private readonly InviteManager $invites = new InviteManager(),
		private readonly WorkflowState $state = new WorkflowState(),
	) {
	}

	public function register(): void {
		// gform_loaded may already have fired by the time this runs, so
		// also register on init (register_field is idempotent either way).
		add_action( 'gform_loaded', array( $this, 'register_field' ), 5 );
		add_action( 'init', array( $this, 'register_field' ), 5 );
		add_action( 'gform_after_submission', array( $this, 'handle_submission' ), 10, 2 );
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 1 );
		add_action( 'admin_post_poolhall_contract_template', array( $this, 'serve_contract_template' ) );
		add_action( 'admin_post_nopriv_poolhall_contract_template', array( $this, 'serve_contract_template' ) );
		add_filter( 'gform_validation', array( $this, 'authorize_submission' ) );
		add_filter( 'gform_field_validation', array( $this, 'validate_starter_field' ), 10, 4 );
		add_action( 'gform_pre_submission', array( $this, 'prepare_starter_submission' ) );
		add_filter( 'gform_upload_path', array( $this, 'private_upload_path' ), 10, 2 );
		add_filter( 'gform_file_path_pre_delete_file', array( $this, 'private_upload_delete_path' ), 10, 2 );
		add_filter( 'gform_get_form_filter', array( $this, 'gate_form' ), 10, 2 );
		add_filter( 'gform_pre_render', array( $this, 'prefill_form' ) );
		add_filter( 'wp_robots', array( $this, 'noindex_hidden_pages' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_hidden_from_sitemap' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'exclude_hidden_from_search' ) );
	}

	/**
	 * Send managed form uploads directly outside the public web root. The URL is
	 * intentionally non-resolving; recruiter access is provided only through
	 * the capability-checked cockpit download action.
	 *
	 * @param array<string,string> $path_info Gravity Forms path/url pair.
	 * @return array<string,string>
	 */
	public function private_upload_path( $path_info, int $form_id ): array {
		if ( '' === $this->kind_for_form( $form_id ) ) {
			return is_array( $path_info ) ? $path_info : array();
		}
		$path_info['path'] = trailingslashit( PrivateStorage::directory( 'incoming-form-files' ) );
		// Trailing slash: Gravity Forms appends the file name directly.
		$path_info['url']  = trailingslashit( home_url( '/poolhall-private-form-file' ) );
		return $path_info;
	}

	/** Resolve the deliberately fake entry URL when Gravity Forms cleans up. */
	public function private_upload_delete_path( string $file_path, string $url ): string {
		$private_url = rtrim( home_url( '/poolhall-private-form-file' ), '/' ) . '/';
		if ( ! str_starts_with( $url, $private_url ) ) {
			return $file_path;
		}
		$basename = sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		return '' === $basename ? $file_path : trailingslashit( PrivateStorage::directory( 'incoming-form-files' ) ) . $basename;
	}

	/**
	 * @param array  $args      Sitemap query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function exclude_hidden_from_sitemap( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$hidden = array_map( 'intval', (array) get_option( self::HIDDEN_PAGES_OPTION, array() ) );
			if ( array() !== $hidden ) {
				$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $hidden );
			}
		}
		return $args;
	}

	/**
	 * @param \WP_Query $query Query.
	 */
	public function exclude_hidden_from_search( $query ): void {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			$hidden = array_map( 'intval', (array) get_option( self::HIDDEN_PAGES_OPTION, array() ) );
			if ( array() !== $hidden ) {
				$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $hidden ) );
			}
		}
	}

	public function register_field(): void {
		if ( class_exists( '\GF_Fields' ) && ! \GF_Fields::exists( 'poolhall_signature' ) ) {
			\GF_Fields::register( new SignatureField() );
		}
	}

	/**
	 * Idempotent create-or-update of the five document forms; returns the
	 * title=>id map (also persisted for the submission dispatcher).
	 *
	 * @return array<string,int>
	 */
	public function setup_forms(): array {
		if ( ! class_exists( '\GFAPI' ) ) {
			return array();
		}
		$this->register_field();

		$definitions = array(
			'full'     => FormDefinitions::full_registration(),
			'contract' => FormDefinitions::agency_worker_contract(),
			'starter'  => FormDefinitions::starter_statement(),
			'terms'    => FormDefinitions::candidate_terms(),
			'payroll'  => FormDefinitions::payroll_details(),
		);

		$map    = (array) get_option( self::FORM_MAP_OPTION, array() );
		$errors = array();
		foreach ( $definitions as $key => $definition ) {
			$existing_id = isset( $map[ $key ] ) ? (int) $map[ $key ] : 0;
			if ( $existing_id > 0 && is_array( \GFAPI::get_form( $existing_id ) ) ) {
				$definition['id'] = $existing_id;
				$updated          = \GFAPI::update_form( $definition, $existing_id );
				if ( true !== $updated ) {
					$errors[ $key ] = is_wp_error( $updated ) ? sanitize_key( $updated->get_error_code() ) : 'update_failed';
				}
				continue;
			}
			$created = \GFAPI::add_form( $definition );
			if ( is_int( $created ) && $created > 0 ) {
				$map[ $key ] = $created;
			} else {
				$errors[ $key ] = is_wp_error( $created ) ? sanitize_key( $created->get_error_code() ) : 'create_failed';
			}
		}
		update_option( self::FORM_MAP_OPTION, $map, false );
		update_option( self::SETUP_ERRORS_OPTION, $errors, false );
		return array_map( 'intval', $map );
	}


	/** Which document kind a form id belongs to; '' when not ours. */
	private function kind_for_form( int $form_id ): string {
		$map  = array_map( 'intval', (array) get_option( self::FORM_MAP_OPTION, array() ) );
		$kind = array_search( $form_id, $map, true );
		return false === $kind ? '' : (string) $kind;
	}

	/**
	 * Document forms only render inside an authorised passwordless session.
	 *
	 * @param string $form_string Rendered form HTML.
	 * @param array  $form        Form meta.
	 */
	public function gate_form( $form_string, $form ) {
		$kind = is_array( $form ) ? $this->kind_for_form( (int) $form['id'] ) : '';
		if ( '' === $kind || self::just_submitted( (int) $form['id'] ) ) {
			return $form_string;
		}
		if ( TaskPolicy::archived( $kind ) ) {
			return '<div class="poolhall-form-note poolhall-form-final"><p>This form has been archived and is no longer required.</p></div>';
		}
		if ( 'terms' === $kind && ! $this->terms_ready() ) {
			return '<div class="poolhall-form-note poolhall-form-final"><p><strong>This task is waiting for Poolhall.</strong> '
				. 'The approved terms will be issued by the team when they are ready.</p></div>';
		}
		if ( 'contract' === $kind && ! ContractPdfBuilder::source_ready() ) {
			return '<div class="poolhall-form-note poolhall-form-final"><p><strong>This contract is temporarily unavailable.</strong> '
				. 'Poolhall must restore the approved legal source before it can be issued.</p></div>';
		}
		if ( 0 === $this->invites->record_for_kind( $kind ) ) {
			return '<div class="poolhall-form-note poolhall-form-final"><p><strong>This form needs a personal invitation link.</strong> '
				. 'Open the secure task link sent by Poolhall. If it has expired, call 0121 516 3000 or email jobs@poolhallrecruitment.co.uk.</p></div>';
		}
		return $form_string;
	}

	/**
	 * Reject crafted direct posts before Gravity Forms creates an entry. The
	 * rendered-form gate is only a presentation control; this is the actual
	 * submission boundary for every managed candidate form.
	 *
	 * @param array<string,mixed> $result Gravity Forms validation result.
	 * @return array<string,mixed>
	 */
	public function authorize_submission( $result ): array {
		if ( ! is_array( $result ) || ! isset( $result['form'] ) || ! is_array( $result['form'] ) ) {
			return is_array( $result ) ? $result : array();
		}

		$form = $result['form'];
		$kind = $this->kind_for_form( (int) ( $form['id'] ?? 0 ) );
		if ( '' === $kind ) {
			return $result;
		}

		$authorised = ! TaskPolicy::archived( $kind ) && $this->invites->record_for_kind( $kind ) > 0
			&& ( 'terms' !== $kind || ( $this->terms_ready() && $this->posted_terms_are_current() ) )
			&& ( 'contract' !== $kind || ContractPdfBuilder::source_ready() );
		if ( $authorised ) {
			try {
				PdfBuilder::storage_dir();
			} catch ( \Throwable $throwable ) {
				unset( $throwable );
				$authorised = false;
			}
		}
		if ( $authorised ) {
			return $result;
		}

		$result['is_valid'] = false;
		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) || in_array( (string) $field->type, array( 'page', 'html', 'section' ), true ) ) {
				continue;
			}
			$field->failed_validation  = true;
			$field->validation_message = 'This secure form session is no longer valid. Please reopen the personal task link sent by Poolhall.';
			break;
		}
		$result['form'] = $form;
		return $result;
	}

	/**
	 * True when this request has just accepted a final submission of the form,
	 * i.e. Gravity Forms is about to show its confirmation. The task is already
	 * marked submitted by then, so the session gates must let that through.
	 */
	public static function just_submitted( int $form_id ): bool {
		if ( $form_id <= 0 || ! class_exists( '\GFFormDisplay' ) || ! isset( \GFFormDisplay::$submission[ $form_id ] ) || ! is_array( \GFFormDisplay::$submission[ $form_id ] ) ) {
			return false;
		}
		$submission = \GFFormDisplay::$submission[ $form_id ];
		// GF 2.10 leaves a confirmation message; earlier versions set is_confirmation.
		return ! empty( $submission['is_valid'] ) && ( ! empty( $submission['is_confirmation'] ) || ! empty( $submission['confirmation_message'] ) );
	}

	/**
	 * Starter checklist checks Gravity Forms can't do on its own: a National
	 * Insurance number in HMRC's format (it is optional, so only when given)
	 * and a date of birth in the past.
	 *
	 * @param array<string,mixed> $result Validation result.
	 * @param mixed               $value  Submitted value.
	 * @param array<string,mixed> $form   Form meta.
	 * @param \GF_Field           $field  Field being validated.
	 * @return array<string,mixed>
	 */
	public function validate_starter_field( $result, $value, $form, $field ) {
		if ( ! is_array( $result ) || empty( $result['is_valid'] ) || ! is_array( $form ) || ! is_object( $field ) || 'starter' !== $this->kind_for_form( (int) ( $form['id'] ?? 0 ) ) ) {
			return $result;
		}
		$id = (int) $field->id;
		if ( StarterChecklist::NI_NUMBER === $id && is_string( $value ) && '' !== trim( $value ) && ! StarterChecklist::valid_ni( $value ) ) {
			$result['is_valid'] = false;
			$result['message']  = 'Enter your National Insurance number in the format QQ 12 34 56 C, or leave it blank if you don\'t know it.';
		}
		if ( StarterChecklist::BIRTH_DATE === $id && class_exists( '\GFCommon' ) ) {
			$date = \GFCommon::parse_date( $value, 'dmy' );
			if ( isset( $date['year'], $date['month'], $date['day'] ) ) {
				$birth = sprintf( '%04d-%02d-%02d', (int) $date['year'], (int) $date['month'], (int) $date['day'] );
				$today = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'Europe/London' ) ) )->format( 'Y-m-d' );
				if ( $birth >= $today ) {
					$result['is_valid'] = false;
					$result['message']  = 'Your date of birth must be in the past.';
				} elseif ( (int) $date['year'] < (int) substr( $today, 0, 4 ) - 100 ) {
					$result['is_valid'] = false;
					$result['message']  = 'Check the year of your date of birth.';
				}
			}
		}
		return $result;
	}

	/**
	 * Final starter submission only: store the HMRC statement worked out from
	 * questions 8-10 (never trusting a posted value) and tidy the NI number
	 * and postcode into their usual written form.
	 *
	 * @param array<string,mixed> $form Form meta.
	 */
	public function prepare_starter_submission( $form ): void {
		if ( ! is_array( $form ) || 'starter' !== $this->kind_for_form( (int) ( $form['id'] ?? 0 ) ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Gravity Forms has already validated this submission, including the candidate session check.
		$posted = static function ( int $id ): string {
			$value = $_POST[ 'input_' . $id ] ?? '';
			return is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
		};
		$_POST[ 'input_' . StarterChecklist::STATEMENT ] = StarterChecklist::statement(
			$posted( StarterChecklist::ANOTHER_JOB ),
			$posted( StarterChecklist::PENSION ),
			$posted( StarterChecklist::SINCE_APRIL )
		);
		$ni = $posted( StarterChecklist::NI_NUMBER );
		if ( '' !== $ni ) {
			$_POST[ 'input_' . StarterChecklist::NI_NUMBER ] = StarterChecklist::format_ni( $ni );
		}
		$postcode = $posted( StarterChecklist::POSTCODE );
		if ( '' !== $postcode ) {
			$_POST[ 'input_' . StarterChecklist::POSTCODE ] = StarterChecklist::format_postcode( $postcode );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Prefills the safe basics (never bank/NI/health) from the invited
	 * candidate's pre-registration record, shown for correction.
	 *
	 * @param array $form Form meta.
	 * @return array
	 */
	public function prefill_form( $form ) {
		$kind = is_array( $form ) ? $this->kind_for_form( (int) $form['id'] ) : '';
		if ( '' === $kind ) {
			return $form;
		}
		$record = $this->invites->record_for_kind( $kind );
		if ( 0 === $record ) {
			return $form;
		}
		$data = array(
			'first_name' => (string) get_post_meta( $record, 'applicant_first', true ),
			'last_name'  => (string) get_post_meta( $record, 'applicant_last', true ),
			'email'      => (string) get_post_meta( $record, 'applicant_email', true ),
			'phone'      => (string) get_post_meta( $record, 'applicant_phone', true ),
			'location'   => (string) get_post_meta( $record, 'location', true ),
			'role_title' => (string) ( get_post_meta( $record, 'onboarding_role', true ) ?: get_post_meta( $record, 'role_title', true ) ),
			'start_date' => $this->date_for_gravity_forms( (string) get_post_meta( $record, 'onboarding_start_date', true ) ),
			'address_1'  => (string) get_post_meta( $record, 'address_line_1', true ),
			'address_2'  => (string) get_post_meta( $record, 'address_line_2', true ),
			'town_city'  => (string) ( get_post_meta( $record, 'town_city', true ) ?: get_post_meta( $record, 'location', true ) ),
			'county'     => (string) get_post_meta( $record, 'county', true ),
			'postcode'   => (string) get_post_meta( $record, 'postcode', true ),
		);
		$map  = match ( $kind ) {
			'full' => array(
				2  => 'first_name',
				3  => 'last_name',
				11 => 'email',
				12 => 'phone',
				6  => 'location',
				91 => 'full_name',
				92 => 'role_title',
			),
			'terms' => array(
				1 => 'full_name',
				2 => 'email',
				3 => 'role_title',
			),
			'contract' => array(
				1 => 'full_name',
				2 => 'email',
				4 => 'address_1',
				5 => 'address_2',
				6 => 'town_city',
				7 => 'county',
				8 => 'postcode',
			),
			'starter' => array(
				StarterChecklist::FIRST_NAMES => 'first_name',
				StarterChecklist::LAST_NAME   => 'last_name',
				StarterChecklist::START_DATE  => 'start_date',
				StarterChecklist::ADDRESS_1   => 'address_1',
				StarterChecklist::ADDRESS_2   => 'address_2',
				StarterChecklist::TOWN        => 'town_city',
				StarterChecklist::COUNTY      => 'county',
				StarterChecklist::POSTCODE    => 'postcode',
			),
			'payroll' => array(
				1 => 'full_name',
				2 => 'email',
			),
			default => array(),
		};
		$full = trim( $data['first_name'] . ' ' . $data['last_name'] );
		foreach ( $form['fields'] as $field ) {
			if ( 'starter' === $kind ) {
				$this->show_tax_year( $field );
			}
			if ( 'contract' === $kind && 'html' === (string) $field->type && 9 === (int) $field->id ) {
				$url            = add_query_arg( 'action', 'poolhall_contract_template', admin_url( 'admin-post.php' ) );
				$field->content = str_replace( '{{poolhall_contract_review_url}}', esc_url( $url ), (string) $field->content );
				continue;
			}
			$key = $map[ (int) $field->id ] ?? '';
			if ( '' === $key ) {
				continue;
			}
			$value = 'full_name' === $key ? $full : (string) ( $data[ $key ] ?? '' );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms' own property name.
			if ( '' !== $value && '' === (string) $field->defaultValue ) {
				$field->defaultValue = $value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms' own property name.
			}
		}
		return $form;
	}

	/** Name the start of the current tax year wherever the starter checklist says "6 April". */
	private function show_tax_year( \GF_Field $field ): void {
		$token = '{{tax_year_start}}';
		$label = StarterChecklist::tax_year_start()->format( 'j F Y' );
		foreach ( array( 'label', 'description', 'content' ) as $property ) {
			if ( isset( $field->$property ) && is_string( $field->$property ) && str_contains( $field->$property, $token ) ) {
				$field->$property = str_replace( $token, $label, $field->$property );
			}
		}
	}

	/** Convert the cockpit's ISO date input to the managed form's dmy format. */
	private function date_for_gravity_forms( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', trim( $value ) );
		if ( false === $date ) {
			return '';
		}
		$errors = \DateTimeImmutable::getLastErrors();
		if ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
			return '';
		}
		return $date->format( 'd/m/Y' );
	}

	// -------------------------------------------------------- submission --

	/**
	 * @param array $entry Gravity Forms entry.
	 * @param array $form  Gravity Forms form meta.
	 */
	public function handle_submission( $entry, $form ): void {
		$map  = array_map( 'intval', (array) get_option( self::FORM_MAP_OPTION, array() ) );
		$kind = array_search( (int) $form['id'], $map, true );
		if ( false === $kind ) {
			return;
		}

		$record = $this->invites->record_for_kind( (string) $kind );
		if ( $record <= 0 ) {
			$this->note( $entry, 'Submission stored, but no authorised candidate task session could be matched. Manual review required.' );
			return;
		}

		try {
			$mail_accepted = $this->process( (string) $kind, $record, $entry, $form );
			// Publish the workflow transition only after the signed evidence and
			// protected uploads have been created successfully. A PDF/storage
			// failure therefore leaves the task recoverable instead of presenting
			// an evidence-less submission to a recruiter.
			$this->state->mark_submitted( $record, (string) $kind, (int) ( $entry['id'] ?? 0 ) );
			foreach ( array( 'draft_token_ciphertext', 'draft_token_hash', 'draft_saved_at', 'draft_expires_at' ) as $draft_suffix ) {
				delete_post_meta( $record, $this->state->meta( (string) $kind, $draft_suffix ) );
			}
			if ( 'full' === $kind ) {
				$this->remember_profile( $record, $entry );
				$this->update_giig_candidate( $record, $entry );
			}
			update_post_meta( $record, $this->state->meta( (string) $kind, 'document_mail_status' ), $mail_accepted ? 'accepted' : 'failed' );
			update_post_meta( $record, $this->state->meta( (string) $kind, 'document_mail_attempted_at' ), gmdate( \DateTimeInterface::ATOM ) );
			$this->giig_timeline_note( $record, $entry, (string) $kind, $mail_accepted );
		} catch ( \Throwable $t ) {
			// Entry is already stored by Gravity Forms — nothing is lost.
			update_post_meta( $record, $this->state->meta( (string) $kind, 'document_mail_status' ), 'failed' );
			update_post_meta( $record, $this->state->meta( (string) $kind, 'document_mail_attempted_at' ), gmdate( \DateTimeInterface::ATOM ) );
			update_post_meta( $record, $this->state->meta( (string) $kind, 'document_error_code' ), 'pipeline_exception' );
			$this->note( $entry, 'Document pipeline error: ' . $t->getMessage() );
			$this->logger->log( 'document_pipeline_error', array( 'entry' => (string) ( $entry['id'] ?? '' ) ) );
		}
	}

	/** Keep corrected, non-sensitive profile basics as the canonical local identity. */
	private function remember_profile( int $record, array $entry ): void {
		$first = trim( (string) ( $entry['2'] ?? '' ) );
		$last  = trim( (string) ( $entry['3'] ?? '' ) );
		$email = trim( (string) ( $entry['11'] ?? '' ) );
		$map   = array(
			'applicant_first' => $first,
			'applicant_last'  => $last,
			'applicant_name'  => trim( $first . ' ' . $last ),
			'applicant_phone' => trim( (string) ( $entry['12'] ?? '' ) ),
			'location'        => trim( (string) ( $entry['6'] ?? '' ) ),
			'address_line_1'  => trim( (string) ( $entry['4'] ?? '' ) ),
			'address_line_2'  => trim( (string) ( $entry['5'] ?? '' ) ),
			'town_city'       => trim( (string) ( $entry['6'] ?? '' ) ),
			'county'          => trim( (string) ( $entry['7'] ?? '' ) ),
			'postcode'        => trim( (string) ( $entry['8'] ?? '' ) ),
			'role_title'      => trim( (string) ( $entry['92'] ?? '' ) ),
		);
		foreach ( $map as $key => $value ) {
			if ( '' !== $value ) {
				update_post_meta( $record, $key, $value );
			}
		}
		$email_status = CandidateIdentity::update_profile_email( $record, $email );
		if ( CandidateIdentity::EMAIL_COLLISION === $email_status ) {
			$this->note( $entry, 'The corrected email also belongs to a separate website candidate identity. No records or Giig IDs were merged; recruiter review is required.' );
		}
	}


	/**
	 * Updates the pre-registration's existing Giig candidate (basics only)
	 * instead of creating a duplicate. Requires Giig's sensitive-endpoint
	 * access for /candidate/update; failures are noted, never fatal.
	 */
	private function update_giig_candidate( int $record, array $entry ): void {
		$candidate_id = (string) get_post_meta( $record, 'giig_candidate_id', true );
		if ( '' === $candidate_id ) {
			return;
		}
		try {
			$sink = \Poolhall\Integration\Plugin::instance()->candidate_sink_public();
			if ( ! $sink instanceof GiigCandidateSink ) {
				return;
			}
			$sink->update_candidate(
				$candidate_id,
				array(
					'FirstName'    => trim( (string) ( $entry['2'] ?? '' ) ),
					'LastName'     => trim( (string) ( $entry['3'] ?? '' ) ),
					'PhoneNumber'  => trim( (string) ( $entry['12'] ?? '' ) ),
					'RoleTitle'    => trim( (string) ( $entry['92'] ?? '' ) ),
					'Location'     => trim( (string) ( $entry['6'] ?? '' ) ),
					'AddressLine1' => trim( (string) ( $entry['4'] ?? '' ) ),
					'Town'         => trim( (string) ( $entry['6'] ?? '' ) ),
					'County'       => trim( (string) ( $entry['7'] ?? '' ) ),
					'Postcode'     => trim( (string) ( $entry['8'] ?? '' ) ),
				)
			);
			update_post_meta( $record, 'giig_update_status', 'updated' );
			update_post_meta( $record, 'giig_update_attempted_at', gmdate( \DateTimeInterface::ATOM ) );
			delete_post_meta( $record, 'giig_update_error' );
			$this->note( $entry, 'Giig candidate #' . $candidate_id . ' updated from the full registration (no duplicate created).' );
		} catch ( \Throwable $t ) {
			update_post_meta( $record, 'giig_update_status', 'failed' );
			update_post_meta( $record, 'giig_update_attempted_at', gmdate( \DateTimeInterface::ATOM ) );
			update_post_meta( $record, 'giig_update_error', 'candidate_update_failed' );
			$this->note( $entry, 'Giig candidate update skipped: ' . $t->getMessage() . ' (candidate remains #' . $candidate_id . ').' );
		}
	}

	/**
	 * Logs the completed document on the candidate's Giig timeline so the
	 * recruiter sees onboarding progress inside the ATS. Only the fact of
	 * completion is sent — never form content (hard GDPR rule). Non-fatal.
	 */
	private function giig_timeline_note( int $record, array $entry, string $kind, bool $mail_accepted ): void {
		$candidate_id = (string) get_post_meta( $record, 'giig_candidate_id', true );
		if ( '' === $candidate_id ) {
			return;
		}
		$what = match ( $kind ) {
			'full'     => 'Full Candidate Registration',
			'contract' => 'Agency Worker Contract',
			'starter'  => 'HMRC Starter Checklist',
			'terms'    => 'Candidate Terms',
			'payroll'  => 'Payroll Details form',
			default    => '',
		};
		if ( '' === $what ) {
			return;
		}
		try {
			$sink = \Poolhall\Integration\Plugin::instance()->candidate_sink_public();
			if ( ! $sink instanceof GiigCandidateSink ) {
				return;
			}
			$sink->add_note(
				$candidate_id,
				'Candidate completed and signed the ' . $what . ' on the website'
				. ( 'starter' === $kind && StarterChecklist::P45_UPLOADED === StarterChecklist::value( $entry, StarterChecklist::P45 ) ? ' and uploaded a P45' : '' )
				. ' (entry #' . (string) ( $entry['id'] ?? '' ) . ').'
				. ( $mail_accepted ? ' Poolhall was notified by email.' : ' Email notification failed; the protected website record is retained for review.' )
			);
			$this->note( $entry, 'Logged on Giig candidate #' . $candidate_id . ' timeline.' );
		} catch ( \Throwable $t ) {
			$this->logger->log( 'giig_note_failed', array( 'entry' => (string) ( $entry['id'] ?? '' ) ) );
		}
	}

	private function process( string $kind, int $record, array $entry, array $form ): bool {
		$entry_id = (int) $entry['id'];

		[ $title, $subject, $filename, $person, $email ] = $this->headline( $kind, $entry, $record );

		$terms_evidence = 'terms' === $kind ? $this->terms_evidence( $entry ) : array();
		if ( 'terms' === $kind && array() === $terms_evidence ) {
			throw new \RuntimeException( 'The approved terms snapshot could not be verified.' );
		}

		if ( 'contract' === $kind ) {
			$path = $this->build_contract_pdf( $entry, $form );
		} else {
			$pdf = new PdfBuilder( $title );
			if ( 'starter' === $kind ) {
				$this->render_starter( $pdf, $entry );
			} else {
				$this->render_answers( $pdf, $entry, $form, $kind, (string) ( $terms_evidence['text'] ?? '' ) );
			}
			$this->render_signature( $pdf, $entry, $form, $kind );
			$pdf->audit(
				array(
					'Candidate name'       => $person,
					'Candidate email'      => $email,
					'Form'                 => (string) $form['title'],
					'Form ID'              => (string) $form['id'],
					'Entry ID'             => (string) $entry_id,
					'Declaration'          => 'Confirmed by candidate',
					'Signature timestamp'  => (string) ( $entry['date_created'] ?? '' ) . ' UTC',
					'Submission timestamp' => (string) ( $entry['date_created'] ?? '' ) . ' UTC',
					'IP address'           => (string) ( $entry['ip'] ?? '' ),
					'Browser / user agent' => (string) ( $entry['user_agent'] ?? '' ),
					'Page URL'             => $this->safe_source_url( (string) ( $entry['source_url'] ?? '' ) ),
				)
			);
			if ( array() !== $terms_evidence ) {
				$pdf->audit(
					array(
						'Terms version' => $terms_evidence['version'],
						'Terms SHA-256' => $terms_evidence['hash'],
					)
				);
			}
			$path = $pdf->save( 'document-' . wp_generate_uuid4() . '.pdf' );
		}
		try {
			$this->protect_uploads( $record, $kind, $entry, $form );
		} catch ( \Throwable $throwable ) {
			wp_delete_file( $path );
			throw $throwable;
		}
		update_post_meta( $record, $this->state->meta( $kind, 'pdf_path' ), $path );
		update_post_meta( $record, $this->state->meta( $kind, 'pdf_download_name' ), sanitize_file_name( $filename ) );
		if ( 'starter' === $kind ) {
			// Cockpit one-liner: statement, loan and P45 only - no identity data.
			update_post_meta( $record, $this->state->meta( $kind, 'summary' ), StarterChecklist::summary_line( $entry ) );
		}
		if ( array() !== $terms_evidence ) {
			update_post_meta( $record, $this->state->meta( $kind, 'terms_version' ), $terms_evidence['version'] );
			update_post_meta( $record, $this->state->meta( $kind, 'terms_hash' ), $terms_evidence['hash'] );
		}
		if ( 'contract' === $kind ) {
			update_post_meta( $record, $this->state->meta( $kind, 'source_version' ), ContractPdfBuilder::SOURCE_VERSION );
			update_post_meta( $record, $this->state->meta( $kind, 'source_hash' ), ContractPdfBuilder::SOURCE_SHA256 );
			update_post_meta(
				$record,
				$this->state->meta( $kind, 'signature_evidence' ),
				wp_json_encode(
					array(
						'entry_id'       => $entry_id,
						'declaration'    => 'Confirmed by candidate',
						'signed_at'      => (string) ( $entry['date_created'] ?? '' ) . ' UTC',
						'submitted_at'   => (string) ( $entry['date_created'] ?? '' ) . ' UTC',
						'source_sha256'  => ContractPdfBuilder::SOURCE_SHA256,
						'source_version' => ContractPdfBuilder::SOURCE_VERSION,
					)
				)
			);
		}
		$body = 'A candidate workflow document has been submitted on the Poolhall website.' . "\n\n"
			. 'Form: ' . $form['title'] . "\n"
			. 'Candidate: ' . $person . "\n"
			. 'Entry ID: ' . $entry_id . "\n\n"
			. 'The completed document is retained in the protected Poolhall document store; no payroll, tax or identity document is attached to this email.' . "\n"
			. 'Entry record: ' . admin_url( 'admin.php?page=gf_entries&view=entry&id=' . (int) $form['id'] . '&lid=' . $entry_id );

		try {
			$sent = BrandedMail::send_text( self::ADMIN_EMAIL, $subject, $body );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			$sent = false;
		}

		if ( $sent ) {
			$this->note( $entry, 'Poolhall notification accepted by wp_mail; PDF retained in the protected document store (' . basename( $path ) . ').' );
		} else {
			$this->note( $entry, 'EMAIL FAILED - PDF retained at ' . basename( $path ) . ' in the protected documents folder. Review this entry and check mail configuration.' );
			$this->logger->log( 'document_email_failed', array( 'entry' => (string) $entry_id ) );
		}

		// The full profile is the authenticated candidate's chance to correct
		// their contact address. Other onboarding receipts continue to use the
		// canonical address already accepted into the workflow.
		$trusted_email = 'full' === $kind ? sanitize_email( $email ) : (string) get_post_meta( $record, 'applicant_email', true );
		if ( '' !== $trusted_email && is_email( $trusted_email ) ) {
			try {
				$receipt_sent = BrandedMail::send_text(
					$trusted_email,
					'Poolhall Recruitment - form received',
					'Thank you. Your form has been submitted to Poolhall Recruitment. A member of the team will contact you if anything else is required.' . "\n\n"
					. 'Poolhall Recruitment Limited' . "\n" . '0121 516 3000 - jobs@poolhallrecruitment.co.uk'
				);
			} catch ( \Throwable $throwable ) {
				unset( $throwable );
				$receipt_sent = false;
			}
			update_post_meta( $record, $this->state->meta( $kind, 'receipt_mail_status' ), $receipt_sent ? 'accepted' : 'failed' );
			update_post_meta( $record, $this->state->meta( $kind, 'receipt_mail_attempted_at' ), gmdate( \DateTimeInterface::ATOM ) );
		}
		return $sent;
	}

	/** Build the signed legal source without reflowing or duplicating its text. */
	private function build_contract_pdf( array $entry, array $form ): string {
		$signature = $this->signature_envelope( $entry, $form );
		if ( null === $signature ) {
			throw new \RuntimeException( 'The Agency Worker Contract signature is missing.' );
		}

		return ( new ContractPdfBuilder() )->save(
			array(
				'full_name'      => trim( (string) ( $entry['1'] ?? '' ) ),
				'effective_date' => trim( (string) ( $entry['3'] ?? '' ) ),
				'address_line_1' => trim( (string) ( $entry['4'] ?? '' ) ),
				'address_line_2' => trim( (string) ( $entry['5'] ?? '' ) ),
				'town_city'      => trim( (string) ( $entry['6'] ?? '' ) ),
				'county'         => trim( (string) ( $entry['7'] ?? '' ) ),
				'postcode'       => trim( (string) ( $entry['8'] ?? '' ) ),
			),
			$signature
		);
	}

	/** @return array{method:string,data:string,signer:string,date:string}|null */
	private function signature_envelope( array $entry, array $form ): ?array {
		foreach ( $form['fields'] as $field ) {
			if ( 'poolhall_signature' !== (string) $field->type ) {
				continue;
			}
			$decoded = SignatureField::decode( (string) ( $entry[ (string) $field->id ] ?? '' ) );
			if ( null === $decoded ) {
				return null;
			}
			return array(
				'method' => $decoded['method'],
				'data'   => $decoded['data'],
				'signer' => $this->signer_name( $entry, $form ),
				'date'   => $this->signed_date( $entry, $form ),
			);
		}
		return null;
	}

	/** Remove every query parameter so access credentials never reach the PDF. */
	private function safe_source_url( string $url ): string {
		$position = strpos( $url, '?' );
		return false === $position ? $url : substr( $url, 0, $position );
	}

	/**
	 * Per-kind title, admin subject, PDF filename, person name and email.
	 *
	 * @return array{0:string,1:string,2:string,3:string,4:string}
	 */
	private function headline( string $kind, array $entry, int $record = 0 ): array {
		$entry_id = (int) $entry['id'];
		if ( 'full' === $kind ) {
			$forename = trim( (string) ( $entry['2'] ?? '' ) );
			$surname  = trim( (string) ( $entry['3'] ?? '' ) );
			$person   = trim( $forename . ' ' . $surname );
			return array(
				'Full Candidate Registration',
				'New Full Candidate Registration - ' . $person . ' - Entry ' . $entry_id,
				'Poolhall-Full-Registration-' . $surname . '-' . $forename . '-' . $entry_id . '.pdf',
				$person,
				trim( (string) ( $entry['11'] ?? '' ) ),
			);
		}
		if ( 'starter' === $kind ) {
			// The checklist asks only what HMRC asks; the email comes from the workflow record.
			$first  = StarterChecklist::value( $entry, StarterChecklist::FIRST_NAMES );
			$last   = StarterChecklist::value( $entry, StarterChecklist::LAST_NAME );
			$person = StarterChecklist::full_name( $entry );
			return array(
				'Starter Checklist',
				'New Starter Checklist - ' . $person . ' - Entry ' . $entry_id,
				'Poolhall-Starter-Checklist-' . $last . '-' . $first . '-' . $entry_id . '.pdf',
				$person,
				$record > 0 ? trim( (string) get_post_meta( $record, 'applicant_email', true ) ) : '',
			);
		}
		if ( 'contract' === $kind ) {
			$person = trim( (string) ( $entry['1'] ?? '' ) );
			return array(
				'Agency Worker Contract',
				'Agency Worker Contract Signed - ' . $person . ' - Entry ' . $entry_id,
				'Poolhall-Agency-Worker-Contract-' . $person . '-' . $entry_id . '.pdf',
				$person,
				trim( (string) ( $entry['2'] ?? '' ) ),
			);
		}
		if ( 'payroll' === $kind ) {
			$person = trim( (string) ( $entry['1'] ?? '' ) );
			return array(
				'Payroll Details',
				'New Payroll Details - ' . $person . ' - Entry ' . $entry_id,
				'Poolhall-Payroll-Details-' . $person . '-' . $entry_id . '.pdf',
				$person,
				trim( (string) ( $entry['2'] ?? '' ) ),
			);
		}
		$person = trim( (string) ( $entry['1'] ?? '' ) );
		return array(
			'Candidate Terms of Service',
			'Candidate Terms Signed - ' . $person . ' - Entry ' . $entry_id,
			'Poolhall-Candidate-Terms-' . $person . '-' . $entry_id . '.pdf',
			$person,
			trim( (string) ( $entry['2'] ?? '' ) ),
		);
	}

	/** Walks the form fields in page order, writing sectioned label/value rows. */
	private function render_answers( PdfBuilder $pdf, array $entry, array $form, string $kind, string $terms_text = '' ): void {
		$pages      = isset( $form['pagination']['pages'] ) && is_array( $form['pagination']['pages'] ) ? $form['pagination']['pages'] : array();
		$page_index = 0;
		$pdf->section( array() !== $pages ? (string) $pages[0] : 'Submitted details' );

		foreach ( $form['fields'] as $field ) {
			$type = (string) $field->type;
			if ( 'page' === $type ) {
				++$page_index;
				$pdf->section( (string) ( $pages[ $page_index ] ?? 'Section ' . ( $page_index + 1 ) ) );
				continue;
			}
			if ( 'html' === $type && 'terms' === $kind && 4 === (int) $field->id ) {
				if ( '' !== $terms_text ) {
					$pdf->section( 'Candidate terms agreed' );
					$pdf->note( $terms_text );
				}
				continue;
			}
			if ( in_array( $type, array( 'html', 'hidden', 'poolhall_signature', 'honeypot', 'captcha' ), true ) ) {
				continue;
			}

			$label = (string) $field->label;
			if ( 'checkbox' === $type ) {
				$values = array();
				foreach ( (array) $field->inputs as $input ) {
					$v = (string) ( $entry[ (string) $input['id'] ] ?? '' );
					if ( '' !== $v ) {
						$values[] = $v;
					}
				}
				$pdf->row( $label, implode( "\n", $values ) );
				continue;
			}
			if ( 'fileupload' === $type ) {
				$url = (string) ( $entry[ (string) $field->id ] ?? '' );
				$pdf->row( $label, '' === $url ? 'Not provided' : basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) . ' (retained with the protected entry)' );
				continue;
			}
			$pdf->row( $label, (string) ( $entry[ (string) $field->id ] ?? '' ) );
		}
	}

	/**
	 * The starter checklist as payroll needs it: a summary box with the
	 * statement, loan and P45 position, then HMRC's own questions and
	 * numbering, then HMRC's employer guidance.
	 */
	private function render_starter( PdfBuilder $pdf, array $entry ): void {
		$value     = static fn( int $id ): string => StarterChecklist::value( $entry, $id );
		$statement = StarterChecklist::statement_for_entry( $entry );
		$not_asked = 'Not asked';
		$job       = $value( StarterChecklist::ANOTHER_JOB );
		$pension   = $value( StarterChecklist::PENSION );
		$has_loan  = $value( StarterChecklist::HAS_LOAN );
		$exempt    = $value( StarterChecklist::LOAN_EXEMPT );
		$loans     = StarterChecklist::loan_types( $entry );
		$ni        = $value( StarterChecklist::NI_NUMBER );

		$pdf->note( 'HMRC Starter checklist (HMRC 09/22), completed online and signed electronically by the employee. For the employer\'s use only - do not send this form to HMRC.' );
		$pdf->callout(
			'For payroll',
			array(
				'Employee'                => StarterChecklist::signer( $entry ),
				'Employment start date'   => StarterChecklist::uk_date( $value( StarterChecklist::START_DATE ) ),
				'Employee statement'      => '' !== $statement
					? 'Statement ' . $statement . ' - ' . StarterChecklist::TAX_CODES[ $statement ]
					: 'Not determined - check with the employee',
				'Student loan deductions' => StarterChecklist::loan_summary( $entry ),
				'P45'                     => StarterChecklist::p45_summary( $entry ),
				'Tax year'                => StarterChecklist::tax_year_label( StarterChecklist::submitted_at( $entry ) ),
			)
		);

		$pdf->section( 'Employee\'s personal details' );
		$pdf->question( '1', 'Last name', $value( StarterChecklist::LAST_NAME ) );
		$pdf->question( '2', 'First names', $value( StarterChecklist::FIRST_NAMES ) );
		$pdf->question( '3', 'What is your sex? (as shown on birth certificate or gender recognition certificate)', $value( StarterChecklist::SEX ) );
		$pdf->question( '4', 'Date of birth', StarterChecklist::uk_date( $value( StarterChecklist::BIRTH_DATE ) ) );
		$pdf->question( '5', 'Home address', implode( "\n", StarterChecklist::address_lines( $entry ) ) );
		$pdf->question( '6', 'National Insurance number (if known)', '' !== $ni ? $ni : 'Not known' );
		$pdf->question( '7', 'Employment start date', StarterChecklist::uk_date( $value( StarterChecklist::START_DATE ) ) );

		$pdf->section( 'Employee statement' );
		$pdf->question( '8', 'Do you have another job?', $job );
		$pdf->question( '9', 'Do you receive payments from a State, workplace or private pension?', 'No' === $job ? $pension : $not_asked );
		$pdf->question(
			'10',
			'Since 6 April have you received payments from another job which has ended, or any of the following taxable benefits: Jobseeker\'s Allowance (JSA), Employment and Support Allowance (ESA), Incapacity Benefit?',
			'No' === $job && 'No' === $pension ? $value( StarterChecklist::SINCE_APRIL ) : $not_asked
		);
		$pdf->question( '', 'Statement', '' !== $statement ? 'Statement ' . $statement : 'Not determined' );
		if ( '' !== $statement ) {
			$pdf->note( '"' . StarterChecklist::STATEMENTS[ $statement ] . '"' );
		}

		$pdf->section( 'Student loans' );
		$pdf->question( '11', 'Do you have a student or postgraduate loan?', $has_loan );
		$pdf->question(
			'12',
			'Do any of the following apply: still studying on a course the loan relates to; completed or left the course after 6 April; already repaid the loan in full; paying the Student Loans Company by Direct Debit to manage the end of loan repayments?',
			'Yes' === $has_loan ? $exempt : $not_asked
		);
		$pdf->question(
			'13',
			'Student loans held (tick all that apply)',
			'Yes' === $has_loan && 'No' === $exempt ? str_replace( StarterChecklist::LOAN_NOT_SURE, 'Not sure of plan type', implode( "\n", $loans ) ) : $not_asked
		);

		$pdf->section( 'P45' );
		$pdf->question( '', 'Does the employee have a P45 from a previous employer?', StarterChecklist::p45_summary( $entry ) );
		$upload = $value( StarterChecklist::P45_UPLOAD );
		if ( StarterChecklist::P45_UPLOADED === $value( StarterChecklist::P45 ) && '' !== $upload ) {
			$pdf->question( '', 'P45 file', basename( (string) wp_parse_url( $upload, PHP_URL_PATH ) ) . ' (download it from the candidate record)' );
		}

		$pdf->section( 'Employer guidance (HMRC)' );
		$pdf->note( 'Use the statement above to set the tax code: Statement A - use the current personal allowance. Statement B - use the current personal allowance on a week 1/month 1 basis. Statement C - use tax code BR.' );
		$pdf->note( 'If you have already submitted your first FPS, keep using the tax code in that FPS until HMRC sends you a new tax code. If the employee gives you their P45 after the first FPS submission, use the tax code shown in parts 2 and 3 of the P45.' );
		$pdf->note( 'Keep the information recorded on this starter checklist for the current and next 3 tax years. Guidance: www.gov.uk/new-employee-tax-code and, for student loans, www.gov.uk/guidance/special-rules-for-student-loans' );
	}

	private function render_signature( PdfBuilder $pdf, array $entry, array $form, string $kind ): void {
		foreach ( $form['fields'] as $field ) {
			if ( 'poolhall_signature' !== (string) $field->type ) {
				continue;
			}
			$decoded = SignatureField::decode( (string) ( $entry[ (string) $field->id ] ?? '' ) );
			if ( null === $decoded ) {
				return;
			}
			$signer = $this->signer_name( $entry, $form );
			$date   = $this->signed_date( $entry, $form );
			if ( 'starter' === $kind ) {
				$pdf->section( 'Declaration' );
				$date = StarterChecklist::uk_date( $date );
			}
			$pdf->note( FormDefinitions::declaration_for( $kind ) );
			$pdf->signature( $decoded['method'], $decoded['data'], $signer, $date );
			return;
		}
	}

	private function signer_name( array $entry, array $form ): string {
		if ( 'starter' === $this->kind_for_form( (int) ( $form['id'] ?? 0 ) ) ) {
			return StarterChecklist::signer( $entry ); // HMRC: full name in capitals.
		}
		// The field labelled Full name / Candidate full name nearest the signature.
		foreach ( $form['fields'] as $field ) {
			if ( str_contains( strtolower( (string) $field->label ), 'full name' ) ) {
				$value = trim( (string) ( $entry[ (string) $field->id ] ?? '' ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}
		return trim( ( (string) ( $entry['2'] ?? '' ) ) . ' ' . ( (string) ( $entry['3'] ?? '' ) ) );
	}

	private function signed_date( array $entry, array $form ): string {
		foreach ( $form['fields'] as $field ) {
			if ( 'date' === (string) $field->type && str_contains( strtolower( (string) $field->label ), 'signed' ) ) {
				$value = trim( (string) ( $entry[ (string) $field->id ] ?? '' ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}
		return (string) ( $entry['date_created'] ?? '' );
	}

	/**
	 * Move submitted identity/tax uploads out of their URL-addressable GF
	 * location and retain them beside the protected PDF. The GF entry keeps a
	 * non-link marker while the recruiter screen provides capability-checked
	 * downloads.
	 */
	private function protect_uploads( int $record, string $kind, array $entry, array $form ): void {
		$uploads     = wp_upload_dir();
		$base_dir    = realpath( (string) $uploads['basedir'] );
		$base_url    = rtrim( (string) $uploads['baseurl'], '/' );
		$private_dir = realpath( PrivateStorage::directory( 'incoming-form-files' ) );
		$private_url = rtrim( home_url( '/poolhall-private-form-file' ), '/' );
		$stored      = array();
		if ( false === $private_dir || false === $base_dir || '' === $base_url ) {
			return;
		}

		foreach ( $form['fields'] as $field ) {
			if ( 'fileupload' !== (string) $field->type ) {
				continue;
			}
			$field_id = (string) $field->id;
			$raw      = trim( (string) ( $entry[ $field_id ] ?? '' ) );
			if ( '' === $raw ) {
				if ( 'starter' === $kind && StarterChecklist::P45_UPLOAD === (int) $field->id && StarterChecklist::P45_UPLOADED === StarterChecklist::value( $entry, StarterChecklist::P45 ) ) {
					throw new \RuntimeException( 'The P45 the candidate chose to upload is missing from the protected submission.' );
				}
				continue;
			}
			$decoded = json_decode( $raw, true );
			$urls    = is_array( $decoded ) ? array_map( 'strval', $decoded ) : array( $raw );
			if ( array() === $urls ) {
				throw new \RuntimeException( 'A required submitted file could not be resolved.' );
			}
			$names = array();
			foreach ( $urls as $url ) {
				if ( str_starts_with( $url, $private_url . '/' ) ) {
					$source = realpath( trailingslashit( $private_dir ) . sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) );
					$root   = $private_dir;
				} elseif ( str_starts_with( $url, $base_url . '/' ) ) {
					$source = realpath( $base_dir . substr( $url, strlen( $base_url ) ) );
					$root   = $base_dir;
				} else {
					throw new \RuntimeException( 'A submitted file was not stored in an approved location.' );
				}
				$size = false !== $source && is_file( $source ) ? filesize( $source ) : false;
				if ( false === $source || ! str_starts_with( $source, trailingslashit( $root ) ) || ! is_file( $source ) || false === $size || $size <= 0 || $size > 15 * 1024 * 1024 ) {
					throw new \RuntimeException( 'A submitted file could not be verified in protected storage.' );
				}
				$original = sanitize_file_name( basename( $source ) );
				$ext      = sanitize_key( (string) pathinfo( $original, PATHINFO_EXTENSION ) );
				$allowed  = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) ( $field->allowedExtensions ?? '' ) ) ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms' property name.
				if ( '' === $ext || ( array() !== $allowed && ! in_array( $ext, $allowed, true ) ) ) {
					throw new \RuntimeException( 'A submitted file has an unapproved file type.' );
				}
				$target = PrivateStorage::opaque_path( 'documents', 'candidate-upload', $ext );
				if ( ! copy( $source, $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- copy first so a failed protection step never destroys the submitted source.
					$this->logger->log( 'document_upload_protection_failed', array( 'entry' => (string) ( $entry['id'] ?? '' ) ) );
					throw new \RuntimeException( 'A submitted file could not be moved into private storage.' );
				}
				try {
					$target = PrivateStorage::protect_file( $target );
				} catch ( \Throwable $throwable ) {
					unset( $throwable );
					wp_delete_file( $target );
					throw new \RuntimeException( 'A submitted file could not be protected.' );
				}
				wp_delete_file( $source );
				$stored[] = array(
					'field'    => (int) $field->id,
					'label'    => (string) $field->label,
					'original' => $original,
					'path'     => $target,
				);
				$names[]  = $original;
			}
			if ( count( $names ) !== count( $urls ) ) {
				throw new \RuntimeException( 'Not every submitted file could be protected.' );
			}
			if ( array() !== $names && class_exists( '\GFAPI' ) && method_exists( '\GFAPI', 'update_entry_field' ) ) {
				\GFAPI::update_entry_field( (int) ( $entry['id'] ?? 0 ), (int) $field->id, 'Stored securely by Poolhall: ' . implode( ', ', $names ) );
			}
		}

		if ( array() !== $stored ) {
			update_post_meta( $record, $this->state->meta( $kind, 'protected_uploads' ), wp_json_encode( $stored ) );
		}
	}

	/** Approved legal copy must be explicit; a boolean alone cannot activate it. */
	private function terms_ready(): bool {
		return FormDefinitions::candidate_terms_ready();
	}

	/** @return array{version:string,hash:string,text:string} */
	private function terms_evidence( array $entry ): array {
		$version = sanitize_text_field( (string) ( $entry['11'] ?? '' ) );
		$hash    = sanitize_text_field( (string) ( $entry['12'] ?? '' ) );
		$history = (array) get_option( 'poolhall_candidate_terms_versions', array() );
		$bucket  = isset( $history[ $hash ] ) && is_array( $history[ $hash ] ) ? $history[ $hash ] : array();
		// New snapshots are keyed by both content hash and version. Accept the
		// earlier flat shape so documents already signed during migration remain
		// verifiable.
		$snapshot = isset( $bucket[ $version ] ) && is_array( $bucket[ $version ] ) ? $bucket[ $version ] : $bucket;
		$display  = (string) ( $snapshot['display_text'] ?? $snapshot['text'] ?? '' );
		$text     = FormDefinitions::canonical_terms_text( $display );
		$known    = (string) ( $snapshot['version'] ?? '' );
		if ( '' !== $text && '' !== $version && '' !== $hash && hash_equals( $known, $version ) && hash_equals( $hash, hash( 'sha256', $text ) ) ) {
			return array(
				'version' => $version,
				'hash'    => $hash,
				'text'    => '' !== trim( $display ) ? trim( $display ) : $text,
			);
		}
		return array();
	}

	private function posted_terms_are_current(): bool {
		$current = FormDefinitions::canonical_terms_text( (string) get_option( 'poolhall_candidate_terms_text', '' ) );
		$hash    = isset( $_POST['input_12'] ) ? sanitize_text_field( wp_unslash( $_POST['input_12'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- authenticated candidate task session is checked before this submitted integrity field.
		$version = isset( $_POST['input_11'] ) ? sanitize_text_field( wp_unslash( $_POST['input_11'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- authenticated candidate task session is checked before this submitted integrity field.
		return '' !== $current
			&& hash_equals( hash( 'sha256', $current ), $hash )
			&& hash_equals( (string) get_option( 'poolhall_candidate_terms_version', '' ), $version );
	}

	private function note( array $entry, string $text ): void {
		if ( class_exists( '\GFAPI' ) && method_exists( '\GFAPI', 'add_note' ) ) {
			\GFAPI::add_note( (int) $entry['id'], 0, 'Poolhall workflow', $text );
		}
	}

	/** Stream the unfilled legal source only to an authorised contract task session. */
	public function serve_contract_template(): void {
		if ( $this->invites->record_for_kind( 'contract' ) <= 0 || ! ContractPdfBuilder::source_ready() ) {
			status_header( 404 );
			exit;
		}

		$path = ContractPdfBuilder::source_path();
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="Poolhall-Agency-Worker-Contract.pdf"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- fixed, hash-verified local legal source after candidate-session authorization.
		exit;
	}

	// ------------------------------------------------------------ assets --

	/**
	 * @param array $form Gravity Forms form meta.
	 */
	public function enqueue_assets( $form ): void {
		if ( ! is_array( $form ) || ! str_contains( (string) ( $form['cssClass'] ?? '' ), 'poolhall-document-form' ) ) {
			return;
		}
		wp_enqueue_script(
			'poolhall-signature-pad',
			POOLHALL_INTEGRATION_URL . 'assets/js/vendor/signature_pad.umd.min.js',
			array(),
			'5.0.4-' . POOLHALL_INTEGRATION_VERSION,
			true
		);
		wp_enqueue_script(
			'poolhall-documents',
			POOLHALL_INTEGRATION_URL . 'assets/js/documents.js',
			array( 'poolhall-signature-pad' ),
			POOLHALL_INTEGRATION_VERSION,
			true
		);
		wp_enqueue_style(
			'poolhall-documents',
			POOLHALL_INTEGRATION_URL . 'assets/css/documents.css',
			array(),
			POOLHALL_INTEGRATION_VERSION
		);
	}

	/**
	 * @param array<string,bool> $robots Robots directives.
	 * @return array<string,bool>
	 */
	public function noindex_hidden_pages( $robots ) {
		$hidden = array_map( 'intval', (array) get_option( self::HIDDEN_PAGES_OPTION, array() ) );
		if ( array() !== $hidden && is_page( $hidden ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
}
