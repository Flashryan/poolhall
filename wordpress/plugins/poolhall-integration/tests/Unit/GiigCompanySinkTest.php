<?php
/**
 * GiigCompanySink mapping tests (pure parts).
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Source\Giig\GiigCompanySink;

final class GiigCompanySinkTest extends TestCase {

	public function test_name_is_always_present_and_trimmed(): void {
		$body = GiigCompanySink::request_body( '  Acme Fabrication Ltd ' );

		self::assertSame( 'Acme Fabrication Ltd', $body['Name'] );
	}

	public function test_optional_fields_pass_through_when_present(): void {
		$body = GiigCompanySink::request_body(
			'Acme Fabrication Ltd',
			array(
				'EmailAddress' => 'ops@acme.example',
				'PhoneNumber'  => '0121 000 0000',
				'Notes'        => 'Contact: Jane Smith',
			),
			'166598'
		);

		self::assertSame( 'ops@acme.example', $body['EmailAddress'] );
		self::assertSame( '0121 000 0000', $body['PhoneNumber'] );
		self::assertSame( 'Contact: Jane Smith', $body['Notes'] );
		self::assertSame( '166598', $body['CompanyId'] );
	}

	public function test_empty_and_unknown_optionals_are_dropped(): void {
		$body = GiigCompanySink::request_body(
			'Acme',
			array(
				'EmailAddress' => '  ',
				'Website'      => '',
				'Unmapped'     => 'nope',
			)
		);

		self::assertSame( array( 'Name' => 'Acme' ), $body );
	}

	public function test_extract_company_id_reads_common_shapes(): void {
		self::assertSame( '777', GiigCompanySink::extract_company_id( array( 'id' => 777 ) ) );
		self::assertSame( '777', GiigCompanySink::extract_company_id( array( 'data' => array( 'Id' => '777' ) ) ) );
		self::assertSame( '777', GiigCompanySink::extract_company_id( array( 'Company' => array( 'CompanyId' => 777 ) ) ) );
		self::assertSame( '', GiigCompanySink::extract_company_id( array( 'message' => 'ok' ) ) );
		self::assertSame( '', GiigCompanySink::extract_company_id( array( 'id' => 0 ) ) );
	}

	public function test_extract_never_returns_the_tenants_own_company_id(): void {
		// Giig create responses echo the tenant's CompanyId alongside the new
		// record's id; the tenant id must never be mistaken for the result.
		$decoded = array(
			'CompanyId' => '166598',
			'id'        => 888,
		);

		self::assertSame( '888', GiigCompanySink::extract_company_id( $decoded, '166598' ) );
		self::assertSame( '', GiigCompanySink::extract_company_id( array( 'CompanyId' => '166598' ), '166598' ) );
	}
}
