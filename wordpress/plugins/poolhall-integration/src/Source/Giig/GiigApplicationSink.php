<?php
/**
 * Giig application sink adapter.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\ApplicationCapabilities;
use Poolhall\Integration\Source\ApplicationPayload;
use Poolhall\Integration\Source\ApplicationResult;
use Poolhall\Integration\Source\ApplicationSink;
use Poolhall\Integration\Source\SourceException;

/**
 * ApplicationSink implementation for Giig Hire (directive §2.2/§8): an
 * application is a TWO-STEP write, there is no single "apply" call.
 *
 *   1. POST /public/api/v1/candidate            -> CandidateId
 *   2. POST /public/api/v1/applicant/submit     -> applicant for the job
 *
 * §12 retry contract: if step 1 succeeded but step 2 failed, the retry must
 * not re-create the candidate. The CandidateId is therefore cached against
 * the payload's idempotency key (transient, 7 days) and reused on retry.
 *
 * CV: the documented API has no file-upload endpoint (directive critical
 * gap), so capabilities report `supports_cv_upload = false`; the CV is
 * stored locally and emailed by ApplicationService, and the candidate Notes
 * reference the filename so the Giig record points at it.
 */
final class GiigApplicationSink implements ApplicationSink {

	private const PATH_CANDIDATE = '/public/api/v1/candidate';
	private const PATH_APPLICANT = '/public/api/v1/applicant/submit';

	private const CANDIDATE_CACHE_PREFIX  = 'poolhall_giig_cand_';
	private const CANDIDATE_CACHE_SECONDS = 7 * 86400; // WP's DAY_IN_SECONDS is unavailable to pure unit tests.

	public function __construct(
		private readonly GiigClient $client,
		private readonly ?string $company_id = null,
	) {}

	public function capabilities(): ApplicationCapabilities {
		return new ApplicationCapabilities(
			supports_candidate_create: true,
			supports_application_submit: true,
			supports_cv_upload: false,
			evidence_note: 'Live two-step write proven 2026-07-02; no CV endpoint in the documented API (directive §2.2).'
		);
	}

	public function submit( ApplicationPayload $payload ): ApplicationResult {
		$candidate_id = $this->cached_candidate_id( $payload->idempotency_key );

		if ( null === $candidate_id ) {
			$decoded      = $this->client->post_json( self::PATH_CANDIDATE, self::candidate_body( $payload, $this->company_id ) );
			$candidate_id = GiigCandidateSink::extract_candidate_id( $decoded );
			if ( '' === $candidate_id ) {
				throw new SourceException( 'Giig candidate create returned no CandidateId.', true );
			}
			// Step 1 done: never re-create this candidate on retry (§12).
			set_transient( self::CANDIDATE_CACHE_PREFIX . md5( $payload->idempotency_key ), $candidate_id, self::CANDIDATE_CACHE_SECONDS );
		}

		$decoded      = $this->client->post_json( self::PATH_APPLICANT, self::applicant_body( $payload, $candidate_id ) );
		$applicant_id = self::extract_applicant_id( $decoded );

		delete_transient( self::CANDIDATE_CACHE_PREFIX . md5( $payload->idempotency_key ) );

		return new ApplicationResult(
			source_candidate_id: $candidate_id,
			source_application_id: '' !== $applicant_id ? $applicant_id : null,
			cv_attached: false
		);
	}

	/**
	 * Candidate-create body (directive §2.2). Optional fields are omitted
	 * when empty rather than sent blank; the CV filename is referenced in
	 * Notes because the API has no upload endpoint.
	 *
	 * @return array<string,string>
	 */
	public static function candidate_body( ApplicationPayload $payload, ?string $company_id = null ): array {
		$notes = trim( (string) $payload->message );
		if ( null !== $payload->cv_original_filename && '' !== $payload->cv_original_filename ) {
			$notes .= ( '' !== $notes ? "\n\n" : '' ) . 'CV held by Poolhall: ' . $payload->cv_original_filename;
		}

		$body = array(
			'FirstName' => trim( $payload->first_name ),
			'LastName'  => trim( $payload->last_name ),
			'Source'    => 'Website',
		);

		$optional = array(
			'EmailAddress' => $payload->email,
			'PhoneNumber'  => $payload->phone,
			'RoleTitle'    => (string) $payload->role_title,
			'Notes'        => $notes,
		);
		foreach ( $optional as $key => $value ) {
			if ( '' !== trim( (string) $value ) ) {
				$body[ $key ] = trim( (string) $value );
			}
		}

		if ( null !== $company_id && '' !== $company_id ) {
			$body['CompanyId'] = $company_id;
		}

		return $body;
	}

	/**
	 * Applicant-submit body. Salary is the job's advertised SalaryFrom, or 0
	 * when unknown (directive §2.2: "pass the job's SalaryFrom (or 0 if
	 * unknown)").
	 *
	 * @return array<string,string|int|float>
	 */
	public static function applicant_body( ApplicationPayload $payload, string $candidate_id ): array {
		return array(
			'CandidateId' => $candidate_id,
			'JobId'       => $payload->source_job_id,
			'Salary'      => null !== $payload->salary_from ? $payload->salary_from : 0,
			'Source'      => 'Website',
		);
	}

	/**
	 * Pull the applicant id out of the response, tolerating unspecified
	 * envelope shapes (same defensive pattern as the candidate sink).
	 *
	 * @param array<mixed> $decoded Decoded JSON.
	 */
	public static function extract_applicant_id( array $decoded ): string {
		$scopes = array( $decoded );
		foreach ( array( 'data', 'result', 'applicant', 'Applicant' ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_array( $decoded[ $key ] ) ) {
				$scopes[] = $decoded[ $key ];
			}
		}

		foreach ( $scopes as $scope ) {
			foreach ( array( 'ApplicantId', 'applicantId', 'ApplicationId', 'applicationId', 'Id', 'id' ) as $field ) {
				if ( isset( $scope[ $field ] ) && ( is_string( $scope[ $field ] ) || is_int( $scope[ $field ] ) ) ) {
					$value = trim( (string) $scope[ $field ] );
					if ( '' !== $value && '0' !== $value ) {
						return $value;
					}
				}
			}
		}

		return '';
	}

	private function cached_candidate_id( string $idempotency_key ): ?string {
		$cached = get_transient( self::CANDIDATE_CACHE_PREFIX . md5( $idempotency_key ) );
		return is_string( $cached ) && '' !== $cached ? $cached : null;
	}
}
