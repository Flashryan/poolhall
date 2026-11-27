<?php
/**
 * Register-your-CV: form, endpoint and Giig candidate push.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Source\CandidatePayload;
use Poolhall\Integration\Source\CandidateSink;
use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Support\BrandedMail;
use Poolhall\Integration\Support\Logger;
use Poolhall\Integration\Support\PrivateStorage;

/**
 * The public /register/ flow (directive §5.1, §7.4 "standalone (Register)"):
 * a candidate registers once and every captured detail lands in Giig as a
 * candidate record via POST /candidate, no manual re-keying. The CV is
 * stored outside the public web root and linked from the authenticated
 * recruiter cockpit and Giig Notes (the documented API has no CV-upload
 * endpoint). Ordinary email never carries the CV as an attachment.
 *
 * Same never-lose guarantees as applications: the local record is written
 * before anything else; a failed Giig push queues the record and the
 * 4-hourly cron retries (a candidate is only created once, because a
 * successful create stores the returned CandidateId and leaves the queue).
 * Progressive enhancement: a plain POST + redirect flow, no JS required.
 */
final class CvRegistration {

	public const SHORTCODE = 'poolhall_register_form';
	// Not 'poolhall_register': Accounts\AuthEndpoints owns that admin_post
	// action for portal signup and would swallow the POST.
	public const ACTION = 'poolhall_cv_register';

	private const HONEYPOT          = 'company_url';
	private const RATE_WINDOW       = 3600; // One hour; literal so the class loads in unit tests without WP constants.
	private const RATE_MAX          = 6;
	private const PUSH_MAX_ATTEMPTS = 5;

	private const MESSAGES = array(
		'first_name_required' => 'Please add your first name.',
		'last_name_required'  => 'Please add your last name.',
		'email_invalid'       => 'That email address does not look right.',
		'phone_required'      => 'Please add a phone number.',
		'linkedin_invalid'    => 'LinkedIn links need to start with https://',
		'consent_required'    => 'Please confirm you are happy for us to hold your details.',
		'cv_required'         => 'Please attach your CV.',
		'cv_too_large'        => 'CVs need to be under 25 MB.',
		'cv_wrong_type'       => 'Please attach a PDF or Word document.',
		'cv_upload_failed'    => 'That upload did not complete. Please try again.',
		'system'              => 'We could not save your registration just now. Please try again or call the team.',
	);

	public function __construct(
		private readonly CandidateSink $sink,
		private readonly Logger $logger,
	) {
	}

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	// ------------------------------------------------------------- render --

	public function render(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only status flags on a GET back-redirect.
		$status = isset( $_GET['registered'] ) ? sanitize_key( (string) wp_unslash( $_GET['registered'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every exploded segment goes through sanitize_key().
		$codes = isset( $_GET['e'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', (string) wp_unslash( $_GET['e'] ) ) ) ) : array();
		// phpcs:enable

		if ( 'sent' === $status ) {
			return '<div class="ph-card" style="text-align:center;padding:48px 28px">'
				. '<h3 class="ph-h3">' . esc_html__( 'You&rsquo;re registered', 'poolhall-integration' ) . '</h3>'
				. '<p class="ph-body">' . esc_html__( 'Thanks! Your details and CV are with the team, and one of us will call for a proper conversation about what you&rsquo;re after.', 'poolhall-integration' ) . '</p>'
				. '<p class="ph-body"><a class="ph-button ph-button--primary" href="' . esc_url( home_url( '/jobs/' ) ) . '">' . esc_html__( 'Browse live jobs', 'poolhall-integration' ) . '</a></p>'
				. '</div>'
				. do_shortcode( '[poolhall_v2_job_matches]' );
		}

		$notice = '';
		if ( 'rate_limited' === $status ) {
			$notice = '<div class="ph-alert ph-alert--error" role="alert"><p>' . esc_html__( 'Too many registrations from this connection just now. Please try again shortly, or call 0121 516 3000.', 'poolhall-integration' ) . '</p></div>';
		} elseif ( 'invalid' === $status && array() !== $codes ) {
			$items = '';
			foreach ( $codes as $code ) {
				if ( isset( self::MESSAGES[ $code ] ) ) {
					$items .= '<li>' . esc_html( self::MESSAGES[ $code ] ) . '</li>';
				}
			}
			$notice = '<div class="ph-alert ph-alert--error" role="alert"><p><strong>' . esc_html__( 'Check the form and try again:', 'poolhall-integration' ) . '</strong></p><ul>' . $items . '</ul></div>';
		} elseif ( 'received' === $status ) {
			$notice = '<div class="ph-alert ph-alert--success" role="status"><p>' . esc_html__( 'You&rsquo;re registered. Your details are safely stored and the team has been alerted.', 'poolhall-integration' ) . '</p></div>';
		}

		$field = static function ( string $name, string $label, string $type = 'text', bool $required = false, string $autocomplete = '', string $placeholder = '' ): string {
			return '<div class="field">'
				. '<label for="ph-reg-' . esc_attr( $name ) . '">' . esc_html( $label )
				. ( $required ? ' <span class="req" aria-hidden="true">*</span>' : '' ) . '</label>'
				. '<input class="input" type="' . esc_attr( $type ) . '" id="ph-reg-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"'
				. ( $required ? ' required' : '' )
				. ( '' !== $autocomplete ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : '' )
				. ( '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '' )
				. ' /></div>';
		};

		$upload_icon = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 13v8"/><path d="M4 14.9A7 7 0 1 1 15.7 8h1.8a4.5 4.5 0 0 1 2.5 8.2"/><path d="m8 17 4-4 4 4"/></svg>';

		return $notice
			. '<form class="ph-v2reg ph-register-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />'
			. wp_nonce_field( self::ACTION, '_wpnonce', true, false )
			. '<div class="field" style="position:absolute;left:-9999px" aria-hidden="true"><label>' . esc_html__( 'Company website', 'poolhall-integration' ) . '<input type="text" name="' . esc_attr( self::HONEYPOT ) . '" tabindex="-1" autocomplete="off" /></label></div>'
			. '<div class="frow">'
			. $field( 'first_name', __( 'First name', 'poolhall-integration' ), 'text', true, 'given-name', __( 'Jane', 'poolhall-integration' ) )
			. $field( 'last_name', __( 'Last name', 'poolhall-integration' ), 'text', true, 'family-name', __( 'Smith', 'poolhall-integration' ) )
			. '</div>'
			. '<div class="frow">'
			. $field( 'email', __( 'Email', 'poolhall-integration' ), 'email', true, 'email', 'jane@email.com' )
			. $field( 'phone', __( 'Phone', 'poolhall-integration' ), 'tel', true, 'tel', '07700 900000' )
			. '</div>'
			. '<div class="frow">'
			. $field( 'location', __( 'Where are you based?', 'poolhall-integration' ), 'text', false, 'address-level2', __( 'e.g. Wolverhampton', 'poolhall-integration' ) )
			. $field( 'role_title', __( 'What kind of role?', 'poolhall-integration' ), 'text', false, '', __( 'e.g. Site Manager', 'poolhall-integration' ) )
			. '</div>'
			. '<div class="frow">'
			. $field( 'salary_expectations', __( 'Salary expectations (optional)', 'poolhall-integration' ), 'text', false, '', __( 'e.g. &pound;40,000+', 'poolhall-integration' ) )
			. $field( 'linkedin', __( 'LinkedIn (optional)', 'poolhall-integration' ), 'url', false, 'url', 'https://' )
			. '</div>'
			. $field( 'skills', __( 'Key skills (optional)', 'poolhall-integration' ), 'text', false, '', __( 'e.g. AutoCAD, CNC programming, PPC — comma separated', 'poolhall-integration' ) )
			. '<div class="field"><label for="ph-reg-cv">' . esc_html__( 'Upload CV', 'poolhall-integration' ) . ' <span class="req" aria-hidden="true">*</span></label>'
			. '<div class="upload">' . $upload_icon
			. '<span class="ut">' . esc_html__( 'Drag &amp; drop or browse', 'poolhall-integration' ) . '</span>'
			. '<span class="us">' . esc_html__( 'PDF or Word &middot; max 25 MB', 'poolhall-integration' ) . '</span>'
			. '<input type="file" id="ph-reg-cv" name="cv" required accept=".pdf,.doc,.docx" />'
			. '</div></div>'
			. '<div class="field"><label for="ph-reg-message">' . esc_html__( 'Anything else we should know?', 'poolhall-integration' ) . '</label>'
			. '<textarea id="ph-reg-message" name="message" rows="4" placeholder="' . esc_attr__( 'Notice periods, must-haves, anything that matters to you&hellip;', 'poolhall-integration' ) . '"></textarea></div>'
			. '<label class="consent"><input type="checkbox" name="consent" value="1" required /> <span>'
			. wp_kses_post( __( 'I consent to Poolhall holding my details per the <a href="/privacy-policy/">Privacy Policy</a>. <span class="req" aria-hidden="true">*</span>', 'poolhall-integration' ) ) . '</span></label>'
			. '<button type="submit" class="btn btn-primary btn-block">' . esc_html__( 'Register now', 'poolhall-integration' ) . ' <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg></button>'
			. '</form>';
	}

	// ------------------------------------------------------------- handle --

	public function handle(): never {
		$back = ( wp_get_referer() ? wp_get_referer() : home_url( '/register/' ) );
		$back = remove_query_arg( array( 'registered', 'e' ), $back );

		if ( false === wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', self::ACTION )
			|| '' !== ( isset( $_POST[ self::HONEYPOT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::HONEYPOT ] ) ) : '' )
			/** This filter is documented in src/Accounts/AuthEndpoints.php */
			|| true !== (bool) apply_filters( 'poolhall_human_check', true, self::ACTION ) ) {
			$this->redirect( $back, 'invalid', array() );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$request = RegistrationRequest::from_post( wp_unslash( $_POST ) );

		$cv     = $this->capture_cv();
		$errors = array_merge( $request->validation_errors(), $cv['errors'] );
		if ( array() !== $errors ) {
			$this->delete_file( $cv['path'] );
			$this->redirect( $back, 'invalid', $errors );
		}

		if ( ! $this->within_rate_limit() ) {
			$this->delete_file( $cv['path'] );
			$this->redirect( $back, 'rate_limited', array() );
		}

		// Record first: the registration survives everything downstream.
		$record_id = ApplicationRecord::store(
			sprintf(
				/* translators: %s: candidate name. */
				__( '%s — CV registration', 'poolhall-integration' ),
				$request->full_name()
			),
			array(
				'kind'                => 'registration',
				'applicant_name'      => $request->full_name(),
				'applicant_first'     => $request->first_name,
				'applicant_last'      => $request->last_name,
				'applicant_email'     => $request->email,
				'applicant_phone'     => $request->phone,
				'location'            => $request->location,
				'role_title'          => $request->role_title,
				'salary_expectations' => $request->salary_expectations,
				'linkedin'            => $request->linkedin,
				'skills'              => $request->skills,
				'message'             => $request->message,
				'cv_filename'         => $cv['name'],
				'cv_status'           => 'pending',
				'cv_path'             => $cv['path'],
				'review_status'       => 'new',
				'submitted_at'        => gmdate( \DateTimeInterface::ATOM ),
			)
		);
		if ( $record_id <= 0 ) {
			$this->logger->log( 'registration_store_failed', array() );
			$this->delete_file( $cv['path'] );
			$this->redirect( $back, 'invalid', array( 'system' ) );
		}
		CandidateIdentity::attach( $record_id, $request->email );

		// Per-record download key so the Giig Notes can carry a stable CV
		// link. The key alone grants nothing: download_cv() still requires a
		// logged-in admin — this only replaces the session-bound nonce so the
		// link keeps working from inside the ATS.
		$cv_key = wp_generate_password( 32, false );
		ApplicationRecord::update_meta( $record_id, 'cv_key', $cv_key );

		$this->push( $record_id, $request, $cv['name'], self::cv_link( $record_id, $cv_key ) );

		$sent = $this->notify( $request, $cv['path'], $cv['name'], $record_id );
		// The CV is retained in the protected store either way so the
		// recruiter review screen can offer a secure, admin-only download.
		ApplicationRecord::update_meta( $record_id, 'cv_path', $cv['path'] );
		if ( $sent ) {
			ApplicationRecord::update_meta( $record_id, 'cv_status', 'emailed_and_stored' );
		} else {
			ApplicationRecord::update_meta( $record_id, 'cv_status', 'stored_pending' );
			$this->logger->log( 'registration_mail_failed', array( 'record' => (string) $record_id ) );
		}

		// Opaque hint token so the confirmation screen can show matching live
		// roles: the role/skills text stays server-side in a short transient,
		// never in the URL (hard rule: no personal data in URL parameters).
		if ( '' !== $request->role_title || '' !== $request->skills ) {
			$match_token = strtolower( wp_generate_password( 16, false ) );
			set_transient(
				'poolhall_reg_match_' . $match_token,
				array(
					'role'   => $request->role_title,
					'skills' => $request->skills,
				),
				15 * 60
			);
			$back = add_query_arg( 'm', $match_token, $back );
		}

		$this->redirect( $back, $sent ? 'sent' : 'received', array() );
	}

	/** Giig id already bound to this durable candidate key, if any. */
	private function existing_candidate_id( int $current ): string {
		return CandidateIdentity::giig_id( $current );
	}

	/** Admin-post CV download URL for a record (admin login still required). */
	public static function cv_link( int $record_id, string $cv_key ): string {
		return admin_url( 'admin-post.php?action=poolhall_cv_download&record=' . $record_id . '&key=' . rawurlencode( $cv_key ) );
	}

	/** Create the Giig candidate; queue for cron retry on failure. */
	private function push( int $record_id, RegistrationRequest $request, string $cv_name, string $cv_link = '' ): void {
		// Reuse only an id already bound to this durable candidate key. An
		// unverified matching email is never sufficient identity evidence.
		$existing = $this->existing_candidate_id( $record_id );
		if ( '' !== $existing ) {
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
			CandidateIdentity::remember_giig_id( $record_id, $existing );
			ApplicationRecord::update_meta( $record_id, 'giig_push_note', 'Reused the candidate id already bound to this durable candidate key.' );
			// Surface the repeat interest on the Giig timeline — otherwise the
			// reuse is invisible in the ATS. Nothing sensitive in the note.
			if ( $this->sink instanceof \Poolhall\Integration\Source\Giig\GiigCandidateSink ) {
				try {
					$this->sink->add_note(
						$existing,
						'Candidate re-registered via the website pre-registration form.'
						. ( '' !== $request->role_title ? ' Looking for: ' . $request->role_title . '.' : '' )
						. ' Updated details and new CV are with the applications inbox (record #' . $record_id . ').'
						. ( '' !== $cv_link ? "\nNew CV (Poolhall admin login required): " . $cv_link : '' )
					);
				} catch ( \Throwable $t ) {
					$this->logger->log( 'giig_note_failed', array( 'record' => (string) $record_id ) );
				}
			}
			return;
		}
		if ( ! $this->sink->is_configured() ) {
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'unconfigured' );
			return;
		}

		try {
			$result = $this->register_or_link( $record_id, self::payload( $request, $cv_name, $cv_link ) );
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
			CandidateIdentity::remember_giig_id( $record_id, $result->source_candidate_id );
			$this->logger->log(
				'registration_pushed',
				array(
					'record'    => (string) $record_id,
					'candidate' => $result->source_candidate_id,
				)
			);
		} catch ( SourceException $e ) {
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'queued' );
			ApplicationRecord::update_meta( $record_id, 'giig_push_attempts', '1' );
			ApplicationRecord::update_meta( $record_id, 'giig_push_error', $e->getMessage() );
			$this->logger->log(
				'registration_push_failed',
				array(
					'record' => (string) $record_id,
					'error'  => $e->getMessage(),
				)
			);
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'queued' );
			ApplicationRecord::update_meta( $record_id, 'giig_push_attempts', '1' );
			ApplicationRecord::update_meta( $record_id, 'giig_push_error', 'unexpected_push_failure' );
			$this->logger->log( 'registration_push_failed', array( 'record' => (string) $record_id ) );
		}
	}

	/** Cron: retry queued registrations (create-only, so a success leaves the queue). */
	public function retry_queued(): void {
		if ( ! $this->sink->is_configured() ) {
			return;
		}

		$queued = get_posts(
			array(
				'post_type'      => ApplicationRecord::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- tiny bounded queue.
					array(
						'key'   => 'kind',
						'value' => 'registration',
					),
					array(
						'key'   => 'giig_push',
						'value' => 'queued',
					),
				),
			)
		);

		foreach ( $queued as $record_id ) {
			$record_id = (int) $record_id;
			$attempts  = (int) get_post_meta( $record_id, 'giig_push_attempts', true );
			if ( $attempts >= self::PUSH_MAX_ATTEMPTS ) {
				continue;
			}

			$existing = CandidateIdentity::giig_id( $record_id );
			if ( '' !== $existing ) {
				ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
				CandidateIdentity::remember_giig_id( $record_id, $existing );
				continue;
			}

			$cv_key  = (string) get_post_meta( $record_id, 'cv_key', true );
			$payload = new CandidatePayload(
				first_name: (string) get_post_meta( $record_id, 'applicant_first', true ),
				last_name: (string) get_post_meta( $record_id, 'applicant_last', true ),
				email: (string) get_post_meta( $record_id, 'applicant_email', true ),
				phone: (string) get_post_meta( $record_id, 'applicant_phone', true ),
				role_title: (string) get_post_meta( $record_id, 'role_title', true ),
				location: (string) get_post_meta( $record_id, 'location', true ),
				salary_expectations: (string) get_post_meta( $record_id, 'salary_expectations', true ),
				linkedin: (string) get_post_meta( $record_id, 'linkedin', true ),
				notes: self::notes(
					(string) get_post_meta( $record_id, 'message', true ),
					(string) get_post_meta( $record_id, 'cv_filename', true ),
					'' !== $cv_key ? self::cv_link( $record_id, $cv_key ) : ''
				),
				tags: 'Website,Pre-Registration',
				skills: (string) get_post_meta( $record_id, 'skills', true ),
			);

			try {
				$result = $this->register_or_link( $record_id, $payload );
				ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
				CandidateIdentity::remember_giig_id( $record_id, $result->source_candidate_id );
				$this->logger->log( 'registration_push_retried', array( 'record' => (string) $record_id ) );
			} catch ( SourceException $e ) {
				ApplicationRecord::update_meta( $record_id, 'giig_push_attempts', (string) ( $attempts + 1 ) );
				ApplicationRecord::update_meta( $record_id, 'giig_push_error', $e->getMessage() );
			}
		}
	}

	// ------------------------------------------------------------ helpers --

	/** Existing non-directory adapters remain compatible; Giig must search first. */
	private function register_or_link( int $record, CandidatePayload $payload ): \Poolhall\Integration\Source\CandidateResult {
		if ( $this->sink instanceof \Poolhall\Integration\Source\CandidateDirectory ) {
			return new \Poolhall\Integration\Source\CandidateResult( ( new CandidateResolver( $this->sink ) )->resolve( $record, $payload ) );
		}
		return $this->sink->register_candidate( $payload );
	}

	/** Map the request onto the Giig candidate payload (pure, testable). */
	public static function payload( RegistrationRequest $request, string $cv_name, string $cv_link = '' ): CandidatePayload {
		return new CandidatePayload(
			first_name: $request->first_name,
			last_name: $request->last_name,
			email: $request->email,
			phone: $request->phone,
			role_title: $request->role_title,
			location: $request->location,
			salary_expectations: $request->salary_expectations,
			linkedin: $request->linkedin,
			notes: self::notes( $request->message, $cv_name, $cv_link ),
			tags: 'Website,Pre-Registration',
			skills: $request->skills,
		);
	}

	private static function notes( string $message, string $cv_name, string $cv_link = '' ): string {
		$notes = trim( $message );
		if ( '' !== $cv_name ) {
			$notes .= ( '' !== $notes ? "\n\n" : '' ) . 'CV held by Poolhall: ' . $cv_name;
		}
		if ( '' !== $cv_link ) {
			$notes .= "\nDownload CV (Poolhall admin login required): " . $cv_link;
		}
		return $notes;
	}

	/**
	 * @return array{errors:string[],path:string,name:string}
	 */
	private function capture_cv(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in handle(); members sanitized below and the file goes through is_uploaded_file()/move_uploaded_file().
		$file = isset( $_FILES['cv'] ) && is_array( $_FILES['cv'] ) ? $_FILES['cv'] : array();

		$name  = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
		$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$tmp   = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		$errors = CvUpload::validate( $name, $size, $error );
		if ( array() !== $errors ) {
			return array(
				'errors' => $errors,
				'path'   => '',
				'name'   => $name,
			);
		}

		$checked = wp_check_filetype_and_ext(
			$tmp,
			$name,
			array(
				'pdf'  => 'application/pdf',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			)
		);
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return array(
				'errors' => array( 'cv_wrong_type' ),
				'path'   => '',
				'name'   => $name,
			);
		}
		try {
			$target = PrivateStorage::opaque_path( 'cvs', 'candidate-cv', CvUpload::extension( $name ) );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return array(
				'errors' => array( 'cv_upload_failed' ),
				'path'   => '',
				'name'   => $name,
			);
		}

		if ( ! is_uploaded_file( $tmp ) || ! move_uploaded_file( $tmp, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return array(
				'errors' => array( 'cv_upload_failed' ),
				'path'   => '',
				'name'   => $name,
			);
		}
		try {
			$target = PrivateStorage::protect_file( $target );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			wp_delete_file( $target );
			return array(
				'errors' => array( 'cv_upload_failed' ),
				'path'   => '',
				'name'   => $name,
			);
		}

		return array(
			'errors' => array(),
			'path'   => $target,
			'name'   => $name,
		);
	}

	private function notify( RegistrationRequest $request, string $cv_path, string $cv_name, int $record_id ): bool {
		unset( $cv_path );
		$inbox = (string) get_option( ApplicationService::INBOX_OPTION, '' );
		if ( '' === $inbox ) {
			$inbox = (string) get_option( 'admin_email' );
		}
		/** This filter is documented in src/Applications/ApplicationService.php */
		$inbox = (string) apply_filters( 'poolhall_applications_inbox', $inbox );

		$push      = (string) get_post_meta( $record_id, 'giig_push', true );
		$candidate = (string) get_post_meta( $record_id, 'giig_candidate_id', true );

		$lines = array(
			'New CV registration',
			'',
			'Name: ' . $request->full_name(),
			'Email: ' . $request->email,
			'Phone: ' . $request->phone,
			'Location: ' . ( '' !== $request->location ? $request->location : '(not given)' ),
			'Looking for: ' . ( '' !== $request->role_title ? $request->role_title : '(not given)' ),
			'Salary expectations: ' . ( '' !== $request->salary_expectations ? $request->salary_expectations : '(not given)' ),
			'Key skills: ' . ( '' !== $request->skills ? $request->skills : '(not given)' ),
			'LinkedIn: ' . ( '' !== $request->linkedin ? $request->linkedin : '(not given)' ),
			'',
			'Message:',
			'' === $request->message ? '(none)' : $request->message,
			'',
			'CV retained in the protected candidate record: ' . $cv_name,
			'Consent to store and process: yes',
			'',
			'pushed' === $push
				? 'Giig: candidate created automatically (CandidateId ' . $candidate . ')'
				: 'Giig: push queued for automatic retry (see Poolhall Jobs admin)',
			'Review securely: ' . admin_url( 'admin.php?page=poolhall-candidates&record=' . $record_id ),
		);

		$headers = array( 'Reply-To: ' . $request->full_name() . ' <' . $request->email . '>' );
		try {
			$sent = BrandedMail::send_text( $inbox, 'New CV registration: ' . $request->full_name(), implode( "\n", $lines ), $headers );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			$sent = false;
		}
		ApplicationRecord::update_meta( $record_id, 'admin_mail_status', $sent ? 'accepted' : 'failed' );
		ApplicationRecord::update_meta( $record_id, 'admin_mail_attempted_at', gmdate( \DateTimeInterface::ATOM ) );

		// Simple candidate receipt — no submitted data included.
		try {
			$receipt_sent = BrandedMail::send_text(
				$request->email,
				'Poolhall Recruitment - registration received',
				'Thank you. Your registration has been submitted to Poolhall Recruitment. A member of the team will contact you if anything else is required.' . "\n\n"
				. 'Poolhall Recruitment Limited' . "\n" . '0121 516 3000 - jobs@poolhallrecruitment.co.uk'
			);
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			$receipt_sent = false;
		}
		ApplicationRecord::update_meta( $record_id, 'receipt_mail_status', $receipt_sent ? 'accepted' : 'failed' );
		ApplicationRecord::update_meta( $record_id, 'receipt_mail_attempted_at', gmdate( \DateTimeInterface::ATOM ) );

		return $sent;
	}

	private function within_rate_limit(): bool {
		$signal = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $signal ) {
			return true;
		}
		$key   = 'poolhall_register_rate_' . md5( $signal );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_MAX ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	private function delete_file( string $path ): void {
		if ( '' !== $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * @param string[] $errors Error codes.
	 */
	private function redirect( string $back, string $status, array $errors ): never {
		$url = add_query_arg( 'registered', rawurlencode( $status ), $back );
		if ( array() !== $errors ) {
			$url = add_query_arg( 'e', rawurlencode( implode( ',', $errors ) ), $url );
		}
		wp_safe_redirect( $url . '#register' );
		exit;
	}
}
