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
	 * POST /candidate/update — updates supported basics on an existing
	 * candidate. Requires Giig sensitive-endpoint access; throws on error.
	 *
	 * @param array<string,string> $fields PascalCase Giig fields.
	 */
	public function update_candidate( string $candidate_id, array $fields ): void {
		$body = array( 'CandidateId' => $candidate_id );
		foreach ( $fields as $key => $value ) {
			if ( '' !== trim( $value ) ) {
				$body[ $key ] = trim( $value );
			}
		}
		if ( null !== $this->company_id && '' !== $this->company_id ) {
			$body['CompanyId'] = $this->company_id;
		}
		$this->client->post_json( '/public/api/v1/candidate/update', $body );
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

		// Giig expects SalaryExpectations as a plain number (2dp) plus a
		// Currency enum; free text like "£45,000+" is silently dropped by
		// the ATS. Parse the number out and keep the wording in Notes.
		$notes      = $payload->notes;
		$raw_salary = trim( $payload->salary_expectations );
		$numeric    = self::numeric_salary( $raw_salary );
		if ( '' !== $raw_salary ) {
			$notes .= ( '' !== $notes ? "\n" : '' ) . 'Salary expectations (as entered): ' . $raw_salary;
		}

		$optional = array(
			'EmailAddress'       => $payload->email,
			'PhoneNumber'        => $payload->phone,
			'RoleTitle'          => $payload->role_title,
			'Location'           => $payload->location,
			'SalaryExpectations' => null !== $numeric ? number_format( $numeric, 2, '.', '' ) : '',
			'Currency'           => null !== $numeric ? self::currency_enum( $raw_salary ) : '',
			'Tags'               => $payload->tags,
			'LinkedIn'           => $payload->linkedin,
			'Notes'              => $notes,
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
	 * First number in a salary phrase, tolerating commas and a k suffix;
	 * null when nothing confidently numeric is present.
	 */
	public static function numeric_salary( string $raw ): ?float {
		if ( ! preg_match( '/(\d[\d,]*(?:\.\d+)?)\s*(k)?/i', $raw, $m ) || '' === $m[1] ) {
			return null;
		}
		$value = (float) str_replace( ',', '', $m[1] );
		if ( isset( $m[2] ) && '' !== $m[2] ) {
			$value *= 1000;
		}
		return $value > 0 ? $value : null;
	}

	/** Giig Currency enum: 0=GBP, 1=USD, 2=EUR; default GBP for a UK agency. */
	public static function currency_enum( string $raw ): string {
		if ( str_contains( $raw, '$' ) ) {
			return '1';
		}
		if ( str_contains( $raw, chr( 226 ) . chr( 130 ) . chr( 172 ) ) ) {
			return '2';
		}
		return '0';
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
