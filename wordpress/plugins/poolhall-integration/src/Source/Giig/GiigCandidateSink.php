<?php
/**
 * Giig candidate sink adapter.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\CandidatePayload;
use Poolhall\Integration\Source\CandidateResult;
use Poolhall\Integration\Source\CandidateSink;
use Poolhall\Integration\Source\SourceException;

/**
 * CandidateSink implementation for Giig Hire.
 *
 * Endpoint (api-doc.giighire.com, reviewed 2026-06-10):
 *   POST /public/api/v1/candidate
 *   Required: FirstName, LastName.
 *   Optional: EmailAddress, PhoneNumber, RoleTitle, Location, Source,
 *             Notes, SalaryExpectations, LinkedIn.
 *   Returns the new CandidateId.
 *
 * There is no CV/file field on candidate create (documented critical gap),
 * so the CV is handled separately by the application flow; registration only
 * creates the candidate record.
 */
final class GiigCandidateSink implements CandidateSink {

	private const PATH = '/public/api/v1/candidate';

	public function __construct(
		private readonly GiigClient $client,
		private readonly ?string $company_id = null,
	) {}

	public function is_configured(): bool {
		return $this->client->is_configured();
	}

	public function register_candidate( CandidatePayload $payload ): CandidateResult {
		if ( ! $payload->is_valid() ) {
			throw new SourceException( 'Candidate payload is missing a first or last name.' );
		}

		$decoded = $this->client->post_json( self::PATH, self::request_body( $payload, $this->company_id ) );
		$id      = self::extract_candidate_id( $decoded );

		if ( '' === $id ) {
			throw new SourceException( 'Giig candidate create returned no CandidateId.', true );
		}

		return new CandidateResult( $id );
	}

	/**
	 * Map our neutral payload onto Giig's candidate-create fields. Optional
	 * fields are omitted when empty rather than sent blank.
	 *
	 * @return array<string,string>
	 */
	public static function request_body( CandidatePayload $payload, ?string $company_id = null ): array {
		$body = array(
			'FirstName' => trim( $payload->first_name ),
			'LastName'  => trim( $payload->last_name ),
			'Source'    => '' !== trim( $payload->source ) ? trim( $payload->source ) : 'Website',
		);

		$optional = array(
			'EmailAddress'       => $payload->email,
			'PhoneNumber'        => $payload->phone,
			'RoleTitle'          => $payload->role_title,
			'Location'           => $payload->location,
			'SalaryExpectations' => $payload->salary_expectations,
			'LinkedIn'           => $payload->linkedin,
			'Notes'              => $payload->notes,
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
	 * Pull the new candidate id out of the response, tolerating the envelope
	 * shapes the docs leave unspecified (bare, data-wrapped, varied casing).
	 *
	 * @param array<mixed> $decoded Decoded JSON.
	 */
	public static function extract_candidate_id( array $decoded ): string {
		$scopes = array( $decoded );
		foreach ( array( 'data', 'result', 'candidate', 'Candidate' ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_array( $decoded[ $key ] ) ) {
				$scopes[] = $decoded[ $key ];
			}
		}

		foreach ( $scopes as $scope ) {
			foreach ( array( 'CandidateId', 'candidateId', 'candidate_id', 'Id', 'id' ) as $field ) {
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
}
