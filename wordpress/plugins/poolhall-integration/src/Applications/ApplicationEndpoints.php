<?php
/**
 * Application submission endpoint.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Jobs\JobPostType;

/**
 * Handles `poolhall_apply` submissions over admin-post (signed-in and
 * guest). Verifies nonce + honeypot + the `poolhall_human_check` Turnstile
 * seam, validates the text fields (ApplicationRequest) and the CV
 * (CvUpload + a server-side filetype re-check), stores the uploaded CV in
 * a non-public directory, then hands off to ApplicationService.
 *
 * The popup submits by fetch and expects JSON; a plain (no-JS) POST gets a
 * redirect back to the job with a status flag. CVs are written only to a
 * protected uploads subdirectory (deny-all + random names), never the
 * media library, and are removed once the notification email carries them.
 */
final class ApplicationEndpoints {

	public const ACTION         = 'poolhall_apply';
	public const HONEYPOT_FIELD = 'company_url';
	private const SUBDIR        = 'poolhall-applications';

	public function __construct( private readonly ApplicationService $service ) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle(): never {
		$job = $this->resolve_job();

		if ( ! $this->request_passes() || null === $job ) {
			$this->respond( 'invalid', array( 'system' ), $job );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in request_passes().
		$request = ApplicationRequest::from_post( wp_unslash( $_POST ) );

		$cv          = $this->capture_cv();
		$text_errors = $request->validation_errors();
		$errors      = array_merge( $text_errors, $cv['errors'] );
		if ( array() !== $errors ) {
			$this->delete( $cv['path'] );
			$this->respond( 'invalid', $errors, $job );
		}

		try {
			$result = $this->service->submit( $request, $job, $cv['path'], $cv['name'], $this->network_signal() );
		} catch ( \Throwable $e ) {
			// Whatever happens server-side, a fetch submit must get JSON back,
			// never an HTML 500 (the modal would show the generic alert with
			// no diagnostic value). Log it and tell the client honestly.
			error_log( 'poolhall_apply crashed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- deliberate crash breadcrumb for staging diagnosis.
			$this->respond( 'invalid', array( 'system' ), $job );
		}
		$this->respond( $result['status'], $result['errors'], $job );
	}

	/** Nonce, honeypot and the pluggable human check, in that order. */
	private function request_passes(): bool {
		if ( false === wp_verify_nonce( $this->field( '_wpnonce' ), self::ACTION ) ) {
			return false;
		}
		if ( '' !== $this->field( self::HONEYPOT_FIELD ) ) {
			return false;
		}
		/** This filter is documented in src/Accounts/AuthEndpoints.php */
		return (bool) apply_filters( 'poolhall_human_check', true, self::ACTION );
	}

	/**
	 * @return array<string,string>|null Job context, or null when the job id is not a live role.
	 */
	private function resolve_job(): ?array {
		$post_id = (int) $this->field( 'job_id' );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || JobPostType::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$sector = get_the_term_list( $post->ID, JobPostType::TAX_SECTOR, '', ', ' );
		return array(
			'source_job_id' => (string) get_post_meta( $post->ID, 'source_job_id', true ),
			'title'         => get_the_title( $post ),
			'reference'     => (string) get_post_meta( $post->ID, 'job_reference', true ),
			'salary'        => (string) get_post_meta( $post->ID, 'salary_display', true ),
			'salary_min'    => (string) get_post_meta( $post->ID, 'salary_min', true ),
			'sector'        => is_string( $sector ) ? wp_strip_all_tags( $sector ) : '',
			'url'           => (string) get_permalink( $post ),
		);
	}

	/**
	 * Validate and move the uploaded CV into the protected directory.
	 *
	 * @return array{errors:string[],path:string,name:string}
	 */
	private function capture_cv(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in request_passes(); the upload array cannot be sanitized wholesale, each member is sanitized/cast below and the file goes through is_uploaded_file()/move_uploaded_file().
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

		// Server-side filetype re-check: the real bytes must match an allowed type.
		$checked = wp_check_filetype_and_ext( $tmp, $name, $this->allowed_mimes() );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return array(
				'errors' => array( 'cv_wrong_type' ),
				'path'   => '',
				'name'   => $name,
			);
		}

		if ( ! is_uploaded_file( $tmp ) ) {
			return array(
				'errors' => array( 'cv_upload_failed' ),
				'path'   => '',
				'name'   => $name,
			);
		}

		$dir  = $this->protected_dir();
		$dest = trailingslashit( $dir ) . wp_generate_uuid4() . '.' . CvUpload::extension( $name );
		if ( ! move_uploaded_file( $tmp, $dest ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_move_uploaded_file
			return array(
				'errors' => array( 'cv_upload_failed' ),
				'path'   => '',
				'name'   => $name,
			);
		}
		return array(
			'errors' => array(),
			'path'   => $dest,
			'name'   => $name,
		);
	}

	/** @return array<string,string> */
	private function allowed_mimes(): array {
		return array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
	}

	/** Ensure the deny-all protected uploads subdirectory exists. */
	private function protected_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $dir . '/index.html' ) ) {
			file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	private function delete( string $path ): void {
		if ( '' !== $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * JSON for the popup (fetch), or a redirect back to the job for no-JS.
	 *
	 * @param array<string,string>|null $job
	 * @param string[]                  $errors
	 */
	private function respond( string $status, array $errors, ?array $job ): never {
		if ( $this->wants_json() ) {
			wp_send_json(
				array(
					'status' => $status,
					'errors' => array_values( $errors ),
				)
			);
		}

		$url = ( null !== $job && '' !== $job['url'] ) ? $job['url'] : home_url( '/jobs/' );
		wp_safe_redirect( add_query_arg( 'applied', rawurlencode( $status ), $url ) . '#apply' );
		exit;
	}

	private function wants_json(): bool {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
		$xhr    = isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) : '';
		return false !== stripos( $accept, 'application/json' ) || 'xmlhttprequest' === strtolower( $xhr );
	}

	private function field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in request_passes() before any state change.
	}

	private function network_signal(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
