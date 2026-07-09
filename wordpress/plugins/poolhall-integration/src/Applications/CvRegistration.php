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
use Poolhall\Integration\Support\Logger;

/**
 * The public /register/ flow (directive §5.1, §7.4 "standalone (Register)"):
 * a candidate registers once and every captured detail lands in Giig as a
 * candidate record via POST /candidate, no manual re-keying. The CV is
 * stored privately and emailed to the applications inbox with a reference
 * in the Giig Notes (the documented API has no CV-upload endpoint).
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
	private const SUBDIR            = 'poolhall-applications';
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
				. '</div>';
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
				'message'             => $request->message,
				'cv_filename'         => $cv['name'],
				'cv_status'           => 'pending',
				'submitted_at'        => gmdate( \DateTimeInterface::ATOM ),
			)
		);

		$this->push( $record_id, $request, $cv['name'] );

		$sent = $this->notify( $request, $cv['path'], $cv['name'], $record_id );
		if ( $sent ) {
			ApplicationRecord::update_meta( $record_id, 'cv_status', 'emailed' );
			$this->delete_file( $cv['path'] );
		} else {
			ApplicationRecord::update_meta( $record_id, 'cv_status', 'stored_pending' );
			ApplicationRecord::update_meta( $record_id, 'cv_path', $cv['path'] );
			$this->logger->log( 'registration_mail_failed', array( 'record' => (string) $record_id ) );
		}

		$this->redirect( $back, $sent ? 'sent' : 'received', array() );
	}

	/** Create the Giig candidate; queue for cron retry on failure. */
	private function push( int $record_id, RegistrationRequest $request, string $cv_name ): void {
		if ( ! $this->sink->is_configured() ) {
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'unconfigured' );
			return;
		}

		try {
			$result = $this->sink->register_candidate( self::payload( $request, $cv_name ) );
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
			ApplicationRecord::update_meta( $record_id, 'giig_candidate_id', $result->source_candidate_id );
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

			$payload = new CandidatePayload(
				first_name: (string) get_post_meta( $record_id, 'applicant_first', true ),
				last_name: (string) get_post_meta( $record_id, 'applicant_last', true ),
				email: (string) get_post_meta( $record_id, 'applicant_email', true ),
				phone: (string) get_post_meta( $record_id, 'applicant_phone', true ),
				role_title: (string) get_post_meta( $record_id, 'role_title', true ),
				location: (string) get_post_meta( $record_id, 'location', true ),
				salary_expectations: (string) get_post_meta( $record_id, 'salary_expectations', true ),
				linkedin: (string) get_post_meta( $record_id, 'linkedin', true ),
				notes: self::notes( (string) get_post_meta( $record_id, 'message', true ), (string) get_post_meta( $record_id, 'cv_filename', true ) ),
			);

			try {
				$result = $this->sink->register_candidate( $payload );
				ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
				ApplicationRecord::update_meta( $record_id, 'giig_candidate_id', $result->source_candidate_id );
				$this->logger->log( 'registration_push_retried', array( 'record' => (string) $record_id ) );
			} catch ( SourceException $e ) {
				ApplicationRecord::update_meta( $record_id, 'giig_push_attempts', (string) ( $attempts + 1 ) );
				ApplicationRecord::update_meta( $record_id, 'giig_push_error', $e->getMessage() );
			}
		}
	}

	// ------------------------------------------------------------ helpers --

	/** Map the request onto the Giig candidate payload (pure, testable). */
	public static function payload( RegistrationRequest $request, string $cv_name ): CandidatePayload {
		return new CandidatePayload(
			first_name: $request->first_name,
			last_name: $request->last_name,
			email: $request->email,
			phone: $request->phone,
			role_title: $request->role_title,
			location: $request->location,
			salary_expectations: $request->salary_expectations,
			linkedin: $request->linkedin,
			notes: self::notes( $request->message, $cv_name ),
			tags: 'Website,Pre-Registration',
		);
	}

	private static function notes( string $message, string $cv_name ): string {
		$notes = trim( $message );
		if ( '' !== $cv_name ) {
			$notes .= ( '' !== $notes ? "\n\n" : '' ) . 'CV held by Poolhall: ' . $cv_name;
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

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . self::SUBDIR;
		wp_mkdir_p( $target_dir );
		if ( ! file_exists( $target_dir . '/.htaccess' ) ) {
			file_put_contents( $target_dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- deny-all marker in a private dir.
		}
		$target = $target_dir . '/' . wp_generate_password( 20, false ) . '-' . $name;

		if ( ! is_uploaded_file( $tmp ) || ! move_uploaded_file( $tmp, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
			'LinkedIn: ' . ( '' !== $request->linkedin ? $request->linkedin : '(not given)' ),
			'',
			'Message:',
			'' === $request->message ? '(none)' : $request->message,
			'',
			'CV attached: ' . $cv_name,
			'Consent to store and process: yes',
			'',
			'pushed' === $push
				? 'Giig: candidate created automatically (CandidateId ' . $candidate . ')'
				: 'Giig: push queued for automatic retry (see Poolhall Jobs admin)',
		);

		$headers     = array( 'Reply-To: ' . $request->full_name() . ' <' . $request->email . '>' );
		$attachments = ( '' !== $cv_path && file_exists( $cv_path ) ) ? array( $cv_path ) : array();

		// Branded pre-registration PDF for the team (never sent to the candidate).
		$pdf_path = $this->registration_pdf( $request, $cv_name, $record_id );
		if ( '' !== $pdf_path ) {
			$attachments[] = $pdf_path;
		}

		$sent = (bool) wp_mail( $inbox, 'New CV registration: ' . $request->full_name(), implode( "\n", $lines ), $headers, $attachments );

		if ( '' !== $pdf_path ) {
			wp_delete_file( $pdf_path );
		}

		// Simple candidate receipt — no submitted data included.
		wp_mail(
			$request->email,
			'Poolhall Recruitment - registration received',
			'Thank you. Your registration has been submitted to Poolhall Recruitment. A member of the team will contact you if anything else is required.' . "\n\n"
			. 'Poolhall Recruitment Limited' . "\n" . '0121 516 3000 - jobs@poolhallrecruitment.co.uk'
		);

		return $sent;
	}

	/** Renders the pre-registration as a branded PDF; '' when the builder fails. */
	private function registration_pdf( RegistrationRequest $request, string $cv_name, int $record_id ): string {
		try {
			$pdf = new \Poolhall\Integration\Documents\PdfBuilder( 'Candidate Pre-Registration' );
			$pdf->section( 'Candidate details' );
			$pdf->row( 'Name', $request->full_name() );
			$pdf->row( 'Email', $request->email );
			$pdf->row( 'Phone', $request->phone );
			$pdf->row( 'Location', $request->location );
			$pdf->row( 'Looking for', $request->role_title );
			$pdf->row( 'Salary expectations', $request->salary_expectations );
			$pdf->row( 'LinkedIn', $request->linkedin );
			$pdf->row( 'Message', $request->message );
			$pdf->row( 'CV file', $cv_name );
			$pdf->row( 'Consent to store and process', 'Yes' );
			$pdf->audit(
				array(
					'Record ID'            => (string) $record_id,
					'Submission timestamp' => gmdate( 'Y-m-d H:i' ) . ' UTC',
					'IP address'           => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					'Browser / user agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				)
			);
			return $pdf->save( 'Poolhall-Pre-Registration-' . $request->last_name . '-' . $request->first_name . '-' . $record_id . '.pdf' );
		} catch ( \Throwable $t ) {
			$this->logger->log( 'registration_pdf_failed', array( 'record' => (string) $record_id ) );
			return '';
		}
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
