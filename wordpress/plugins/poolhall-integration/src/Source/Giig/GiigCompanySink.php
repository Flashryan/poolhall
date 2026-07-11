<?php
/**
 * Giig company sink adapter.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\SourceException;

/**
 * Creates client-company records in Giig from approved employer enquiries.
 *
 * Endpoint (api-doc.giighire.com): POST /public/api/v1/company with Name
 * (required — contract probed live 2026-07-11) plus optional Website,
 * EmailAddress, PhoneNumber, Location and Notes.
 *
 * Deliberately NOT called on enquiry submission: every spam enquiry would
 * become a CRM record. The recruiter approves each enquiry on the Employer
 * enquiries screen first, and only that action creates the company.
 */
final class GiigCompanySink {

	private const PATH = '/public/api/v1/company';

	public function __construct(
		private readonly GiigClient $client,
		private readonly ?string $company_id = null,
	) {}

	public function is_configured(): bool {
		return $this->client->is_configured();
	}

	/**
	 * Create the company; returns the new Giig company id.
	 *
	 * @param array<string,string> $optional Optional Giig fields (Website,
	 *                                       EmailAddress, PhoneNumber,
	 *                                       Location, Notes).
	 * @throws SourceException On transport/API failure or a missing id.
	 */
	public function create_company( string $name, array $optional = array() ): string {
		$decoded = $this->client->post_json( self::PATH, self::request_body( $name, $optional, $this->company_id ) );
		$id      = self::extract_company_id( $decoded, $this->company_id );

		if ( '' === $id ) {
			throw new SourceException( 'Giig company create returned no company id.', true );
		}
		return $id;
	}

	/**
	 * Build the create body: Name always, optional fields only when
	 * non-empty, unknown keys dropped.
	 *
	 * @param array<string,string> $optional Optional Giig fields.
	 * @return array<string,string>
	 */
	public static function request_body( string $name, array $optional = array(), ?string $company_id = null ): array {
		$body = array( 'Name' => trim( $name ) );

		foreach ( array( 'Website', 'EmailAddress', 'PhoneNumber', 'Location', 'Notes' ) as $key ) {
			if ( isset( $optional[ $key ] ) && '' !== trim( $optional[ $key ] ) ) {
				$body[ $key ] = trim( $optional[ $key ] );
			}
		}

		if ( null !== $company_id && '' !== $company_id ) {
			$body['CompanyId'] = $company_id;
		}

		return $body;
	}

	/**
	 * Pull the new company id out of the response. The tenant's own
	 * CompanyId is echoed back on other Giig create responses, so a value
	 * equal to $own_company_id is never accepted as the new record's id.
	 *
	 * @param array<mixed> $decoded Decoded JSON.
	 */
	public static function extract_company_id( array $decoded, ?string $own_company_id = null ): string {
		$scopes = array( $decoded );
		foreach ( array( 'data', 'result', 'company', 'Company' ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_array( $decoded[ $key ] ) ) {
				$scopes[] = $decoded[ $key ];
			}
		}

		foreach ( $scopes as $scope ) {
			foreach ( array( 'id', 'Id', 'CompanyId', 'companyId' ) as $field ) {
				if ( isset( $scope[ $field ] ) && ( is_string( $scope[ $field ] ) || is_int( $scope[ $field ] ) ) ) {
					$value = trim( (string) $scope[ $field ] );
					if ( '' !== $value && '0' !== $value && $value !== $own_company_id ) {
						return $value;
					}
				}
			}
		}

		return '';
	}
}
