<?php
/**
 * Application delivery: store, notify Poolhall, never lose.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Source\ApplicationPayload;
use Poolhall\Integration\Source\ApplicationSink;
use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Support\Logger;

/**
 * Delivers a validated candidate application to Poolhall (build reference:
 * "sent through to poolhall"). This is a first-party capture that emails
 * the application — with the CV attached — to the applications inbox; it
 * does NOT proxy Giig's hosted form (hard rule 5) and never claims a Giig
 * submission happened (hard rule 15). Automated push to Giig stays behind
 * the `ApplicationSink` contract for when that API is proven.
 *
 * Order guarantees "never silently lose an application" (hard rule 1): the
 * private record is written first, then the email is attempted. On a send
 * failure the record and CV are kept and an admin alert is logged, and the
 * candidate is told their application is received — nothing is dropped.
 */
final class ApplicationService {

	public const STATUS_SENT         = 'sent';
	public const STATUS_INVALID      = 'invalid';
	public const STATUS_RATE_LIMITED = 'rate_limited';
	public const STATUS_RECEIVED     = 'received'; // Stored, but the email did not send; admin alerted.

	public const INBOX_OPTION = 'poolhall_applications_inbox';

	private const RATE_WINDOW_SECONDS = HOUR_IN_SECONDS;
	private const RATE_MAX_PER_WINDOW = 8;
	private const PUSH_MAX_ATTEMPTS   = 5;

	public function __construct(
		private readonly Logger $logger,
		private readonly ?ApplicationSink $sink = null,
	) {
	}

	/**
	 * @param array<string,string> $job  Job context: source_job_id, title, reference, salary, sector, url.
	 * @return array{status:string,errors:string[]}
	 */
	public function submit( ApplicationRequest $request, array $job, string $cv_path, string $cv_name, string $network_signal ): array {
		$errors = $request->validation_errors();
		if ( array() !== $errors ) {
			return $this->fail( self::STATUS_INVALID, $errors, $cv_path );
		}

		if ( ! $this->within_rate_limit( $network_signal ) ) {
			$this->logger->log( 'application_rate_limited', array( 'job' => $job['source_job_id'] ?? '' ) );
			return $this->fail( self::STATUS_RATE_LIMITED, array(), $cv_path );
		}

		// Record first (hard rule 1): the application survives even if mail fails.
		$record_id = ApplicationRecord::store(
			sprintf(
				/* translators: 1: applicant name, 2: job title. */
				__( '%1$s — %2$s', 'poolhall-integration' ),
				$request->full_name(),
				$job['title'] ?? __( 'Application', 'poolhall-integration' )
			),
			array(
				'applicant_name'  => $request->full_name(),
				'applicant_first' => $request->first_name,
				'applicant_last'  => $request->last_name,
				'applicant_email' => $request->email,
				'applicant_phone' => $request->phone,
				'message'         => $request->message,
				'job_title'       => $job['title'] ?? '',
				'job_reference'   => $job['reference'] ?? '',
				'source_job_id'   => $job['source_job_id'] ?? '',
				'cv_filename'     => $cv_name,
				'cv_status'       => 'pending',
				'submitted_at'    => gmdate( \DateTimeInterface::ATOM ),
			)
		);

		// Push straight through to Giig (directive §8, two-step write). The
		// candidate-visible outcome never depends on this: a push failure
		// queues the record for the 4-hourly retry and alerts staff, while
		// the email + stored record already guarantee nothing is lost.
		$this->push_to_sink( $record_id, $request, $job, $cv_name );

		$sent = $this->notify( $request, $job, $cv_path, $cv_name, $record_id );

		if ( $sent ) {
			// CV delivered in the email; remove it from disk to minimise PII at rest.
			ApplicationRecord::update_meta( $record_id, 'cv_status', 'emailed' );
			$this->delete_file( $cv_path );
			$this->logger->log(
				'application_sent',
				array(
					'job'    => $job['source_job_id'] ?? '',
					'record' => (string) $record_id,
				)
			);
			return array(
				'status' => self::STATUS_SENT,
				'errors' => array(),
			);
		}

		// Email failed: keep the record and the CV, flag for manual recovery.
		ApplicationRecord::update_meta( $record_id, 'cv_status', 'stored_pending' );
		ApplicationRecord::update_meta( $record_id, 'cv_path', $cv_path );
		$this->logger->log(
			'application_mail_failed',
			array(
				'job'    => $job['source_job_id'] ?? '',
				'record' => (string) $record_id,
			)
		);
		return array(
			'status' => self::STATUS_RECEIVED,
			'errors' => array(),
		);
	}

	/**
	 * Attempt the Giig push for a stored record. Failures queue the record
	 * (giig_push = queued) for retry_queued_pushes(); successes stamp the
	 * returned CandidateId/ApplicantId on the record for the admin screen.
	 *
	 * @param array<string,string> $job Job context.
	 */
	private function push_to_sink( int $record_id, ApplicationRequest $request, array $job, string $cv_name ): void {
		if ( null === $this->sink || '' === (string) ( $job['source_job_id'] ?? '' ) ) {
			return;
		}

		$payload = new ApplicationPayload(
			source_job_id: (string) $job['source_job_id'],
			first_name: $request->first_name,
			last_name: $request->last_name,
			email: $request->email,
			phone: $request->phone,
			message: $request->message,
			cv_private_path: null,
			cv_original_filename: $cv_name,
			cv_mime_type: null,
			idempotency_key: 'wp-application-' . $record_id,
			role_title: (string) ( $job['title'] ?? '' ),
			salary_from: is_numeric( $job['salary_min'] ?? '' ) ? (float) $job['salary_min'] : null,
		);

		try {
			$result = $this->sink->submit( $payload );
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
			ApplicationRecord::update_meta( $record_id, 'giig_candidate_id', $result->source_candidate_id );
			ApplicationRecord::update_meta( $record_id, 'giig_applicant_id', (string) $result->source_application_id );
			$this->logger->log(
				'application_pushed',
				array(
					'record'    => (string) $record_id,
					'candidate' => $result->source_candidate_id,
					'applicant' => (string) $result->source_application_id,
				)
			);
		} catch ( SourceException $e ) {
			ApplicationRecord::update_meta( $record_id, 'giig_push', 'queued' );
			ApplicationRecord::update_meta( $record_id, 'giig_push_attempts', '1' );
			ApplicationRecord::update_meta( $record_id, 'giig_push_error', $e->getMessage() );
			$this->logger->log(
				'application_push_failed',
				array(
					'record' => (string) $record_id,
					'error'  => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Retry queued Giig pushes (invoked from the 4-hourly cron). Bounded
	 * attempts; a record that keeps failing stays visible to staff with its
	 * last error rather than retrying forever.
	 */
	public function retry_queued_pushes(): void {
		if ( null === $this->sink ) {
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

			$payload = new ApplicationPayload(
				source_job_id: (string) get_post_meta( $record_id, 'source_job_id', true ),
				first_name: (string) get_post_meta( $record_id, 'applicant_first', true ),
				last_name: (string) get_post_meta( $record_id, 'applicant_last', true ),
				email: (string) get_post_meta( $record_id, 'applicant_email', true ),
				phone: (string) get_post_meta( $record_id, 'applicant_phone', true ),
				message: (string) get_post_meta( $record_id, 'message', true ),
				cv_private_path: null,
				cv_original_filename: (string) get_post_meta( $record_id, 'cv_filename', true ),
				cv_mime_type: null,
				idempotency_key: 'wp-application-' . $record_id,
				role_title: (string) get_post_meta( $record_id, 'job_title', true ),
			);

			try {
				$result = $this->sink->submit( $payload );
				ApplicationRecord::update_meta( $record_id, 'giig_push', 'pushed' );
				ApplicationRecord::update_meta( $record_id, 'giig_candidate_id', $result->source_candidate_id );
				ApplicationRecord::update_meta( $record_id, 'giig_applicant_id', (string) $result->source_application_id );
				$this->logger->log( 'application_push_retried', array( 'record' => (string) $record_id ) );
			} catch ( SourceException $e ) {
				ApplicationRecord::update_meta( $record_id, 'giig_push_attempts', (string) ( $attempts + 1 ) );
				ApplicationRecord::update_meta( $record_id, 'giig_push_error', $e->getMessage() );
			}
		}
	}

	/**
	 * @param array<string,string> $job
	 */
	private function notify( ApplicationRequest $request, array $job, string $cv_path, string $cv_name, int $record_id = 0 ): bool {
		$inbox = (string) get_option( self::INBOX_OPTION, '' );
		if ( '' === $inbox ) {
			$inbox = (string) get_option( 'admin_email' );
		}
		/**
		 * Destination inbox for application notifications.
		 *
		 * @param string $inbox Email address.
		 */
		$inbox = (string) apply_filters( 'poolhall_applications_inbox', $inbox );

		$subject = sprintf(
			/* translators: 1: applicant name, 2: job title. */
			__( 'New application: %1$s for %2$s', 'poolhall-integration' ),
			$request->full_name(),
			$job['title'] ?? ''
		);

		$lines = array(
			'Applicant: ' . $request->full_name(),
			'Email: ' . $request->email,
			'Phone: ' . $request->phone,
			'',
			'Role: ' . ( $job['title'] ?? '' ),
			'Reference: ' . ( $job['reference'] ?? '' ),
			'Salary: ' . ( $job['salary'] ?? '' ),
			'Job page: ' . ( $job['url'] ?? '' ),
			'',
			'Message:',
			'' === $request->message ? '(none)' : $request->message,
			'',
			'CV attached: ' . $cv_name,
			'Consent to store and process: yes',
		);

		if ( $record_id > 0 && null !== $this->sink ) {
			$push      = (string) get_post_meta( $record_id, 'giig_push', true );
			$candidate = (string) get_post_meta( $record_id, 'giig_candidate_id', true );
			$lines[]   = '';
			$lines[]   = 'pushed' === $push
				? 'Giig: pushed (CandidateId ' . $candidate . ')'
				: 'Giig: push queued for automatic retry (see Poolhall Jobs admin)';
		}

		$headers     = array( 'Reply-To: ' . $request->full_name() . ' <' . $request->email . '>' );
		$attachments = ( '' !== $cv_path && file_exists( $cv_path ) ) ? array( $cv_path ) : array();

		return (bool) wp_mail( $inbox, $subject, implode( "\n", $lines ), $headers, $attachments );
	}

	/**
	 * @param string[] $errors
	 * @return array{status:string,errors:string[]}
	 */
	private function fail( string $status, array $errors, string $cv_path ): array {
		// No record was written on these paths, so the temp CV is safe to drop.
		$this->delete_file( $cv_path );
		return array(
			'status' => $status,
			'errors' => $errors,
		);
	}

	private function delete_file( string $path ): void {
		if ( '' !== $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	private function within_rate_limit( string $network_signal ): bool {
		if ( '' === $network_signal ) {
			return true;
		}
		$key   = 'poolhall_apply_rate_' . md5( $network_signal );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_MAX_PER_WINDOW ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW_SECONDS );
		return true;
	}
}
