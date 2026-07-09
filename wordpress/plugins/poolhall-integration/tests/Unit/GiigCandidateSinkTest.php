<?php
/**
 * GiigCandidateSink mapping tests (pure parts).
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Source\CandidatePayload;
use Poolhall\Integration\Source\Giig\GiigCandidateSink;

final class GiigCandidateSinkTest extends TestCase {

	public function test_required_fields_and_source_are_always_present(): void {
		$body = GiigCandidateSink::request_body( new CandidatePayload( 'Jane', 'Smith' ) );

		self::assertSame( 'Jane', $body['FirstName'] );
		self::assertSame( 'Smith', $body['LastName'] );
		self::assertSame( 'Website', $body['Source'] );
	}

	public function test_optional_fields_map_to_giig_names_when_present(): void {
		$body = GiigCandidateSink::request_body(
			new CandidatePayload(
				'Jane',
				'Smith',
				'jane@example.com',
				'07700 900000',
				'Site Manager',
				'Wolverhampton',
				'£45,000',
				'linkedin.com/in/jane',
				'Ten years on major builds.'
			)
		);

		self::assertSame( 'jane@example.com', $body['EmailAddress'] );
		self::assertSame( '07700 900000', $body['PhoneNumber'] );
		self::assertSame( 'Site Manager', $body['RoleTitle'] );
		self::assertSame( 'Wolverhampton', $body['Location'] );
		self::assertSame( '45000.00', $body['SalaryExpectations'] );
		self::assertSame( '0', $body['Currency'] );
		self::assertSame( 'linkedin.com/in/jane', $body['LinkedIn'] );
		self::assertStringContainsString( 'Ten years on major builds.', $body['Notes'] );
	}

	public function test_empty_optional_fields_are_omitted_not_sent_blank(): void {
		$body = GiigCandidateSink::request_body( new CandidatePayload( 'Jane', 'Smith', '', '  ' ) );

		self::assertArrayNotHasKey( 'EmailAddress', $body );
		self::assertArrayNotHasKey( 'PhoneNumber', $body );
		self::assertArrayNotHasKey( 'LinkedIn', $body );
	}

	public function test_values_are_trimmed(): void {
		$body = GiigCandidateSink::request_body( new CandidatePayload( '  Jane  ', "Smith\t" ) );

		self::assertSame( 'Jane', $body['FirstName'] );
		self::assertSame( 'Smith', $body['LastName'] );
	}

	public function test_company_id_included_only_when_given(): void {
		$with    = GiigCandidateSink::request_body( new CandidatePayload( 'Jane', 'Smith' ), '999000' );
		$without = GiigCandidateSink::request_body( new CandidatePayload( 'Jane', 'Smith' ), null );

		self::assertSame( '999000', $with['CompanyId'] );
		self::assertArrayNotHasKey( 'CompanyId', $without );
	}

	public function test_extract_candidate_id_reads_common_shapes(): void {
		self::assertSame( '4321', GiigCandidateSink::extract_candidate_id( array( 'CandidateId' => 4321 ) ) );
		self::assertSame( '4321', GiigCandidateSink::extract_candidate_id( array( 'candidateId' => '4321' ) ) );
		self::assertSame( '4321', GiigCandidateSink::extract_candidate_id( array( 'data' => array( 'CandidateId' => '4321' ) ) ) );
		self::assertSame( '99', GiigCandidateSink::extract_candidate_id( array( 'Candidate' => array( 'Id' => 99 ) ) ) );
	}

	public function test_extract_candidate_id_is_empty_when_absent_or_zero(): void {
		self::assertSame( '', GiigCandidateSink::extract_candidate_id( array( 'message' => 'ok' ) ) );
		self::assertSame( '', GiigCandidateSink::extract_candidate_id( array( 'CandidateId' => 0 ) ) );
		self::assertSame( '', GiigCandidateSink::extract_candidate_id( array() ) );
	}

	public function test_salary_text_becomes_numeric_with_currency_and_note(): void {
		$payload = new \Poolhall\Integration\Source\CandidatePayload(
			first_name: 'Jane',
			last_name: 'Smith',
			salary_expectations: '£45,000+',
			notes: 'CV held by Poolhall: cv.pdf',
			tags: 'Website,Pre-Registration'
		);
		$body    = GiigCandidateSink::request_body( $payload );

		self::assertSame( '45000.00', $body['SalaryExpectations'] );
		self::assertSame( '0', $body['Currency'] );
		self::assertSame( 'Website,Pre-Registration', $body['Tags'] );
		self::assertStringContainsString( 'Salary expectations (as entered): £45,000+', $body['Notes'] );
	}

	public function test_k_suffix_and_dollar_salary(): void {
		self::assertSame( 40000.0, GiigCandidateSink::numeric_salary( '40k plus' ) );
		self::assertSame( 52500.5, GiigCandidateSink::numeric_salary( '52,500.50' ) );
		self::assertNull( GiigCandidateSink::numeric_salary( 'negotiable' ) );
		self::assertSame( '1', GiigCandidateSink::currency_enum( '$60000' ) );
		self::assertSame( '0', GiigCandidateSink::currency_enum( '45k' ) );
	}

	public function test_non_numeric_salary_omitted_from_body_but_noted(): void {
		$payload = new \Poolhall\Integration\Source\CandidatePayload(
			first_name: 'Jane',
			last_name: 'Smith',
			salary_expectations: 'Negotiable'
		);
		$body    = GiigCandidateSink::request_body( $payload );
		self::assertArrayNotHasKey( 'SalaryExpectations', $body );
		self::assertArrayNotHasKey( 'Currency', $body );
		self::assertStringContainsString( 'Negotiable', $body['Notes'] );
	}
}
