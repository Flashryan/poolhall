<?php
/**
 * GiigApplicationSink tests (pure request/response mapping).
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Source\ApplicationPayload;
use Poolhall\Integration\Source\Giig\GiigApplicationSink;

final class GiigApplicationSinkTest extends TestCase {

	private function payload( array $overrides = array() ): ApplicationPayload {
		$defaults = array(
			'source_job_id'        => '27918',
			'first_name'           => 'Jane',
			'last_name'            => 'Smith',
			'email'                => 'jane@example.com',
			'phone'                => '07700 900000',
			'message'              => 'Really keen on this one.',
			'cv_private_path'      => null,
			'cv_original_filename' => 'jane-smith-cv.pdf',
			'cv_mime_type'         => null,
			'idempotency_key'      => 'wp-application-42',
			'source_company_id'    => null,
			'role_title'           => 'PPC Specialist',
			'salary_from'          => 28000.0,
		);
		return new ApplicationPayload( ...array_merge( $defaults, $overrides ) );
	}

	public function test_candidate_body_maps_directive_fields(): void {
		$body = GiigApplicationSink::candidate_body( $this->payload(), '166598' );

		self::assertSame( 'Jane', $body['FirstName'] );
		self::assertSame( 'Smith', $body['LastName'] );
		self::assertSame( 'Website', $body['Source'] );
		self::assertSame( 'jane@example.com', $body['EmailAddress'] );
		self::assertSame( '07700 900000', $body['PhoneNumber'] );
		self::assertSame( 'PPC Specialist', $body['RoleTitle'] );
		self::assertSame( '166598', $body['CompanyId'] );
		// The cover message and the locally-held CV are referenced in Notes
		// (no Giig upload endpoint).
		self::assertStringContainsString( 'Really keen on this one.', $body['Notes'] );
		self::assertStringContainsString( 'jane-smith-cv.pdf', $body['Notes'] );
	}

	public function test_candidate_body_omits_empty_optionals(): void {
		$body = GiigApplicationSink::candidate_body(
			$this->payload(
				array(
					'message'              => null,
					'cv_original_filename' => null,
					'role_title'           => null,
					'phone'                => '',
				)
			)
		);

		self::assertArrayNotHasKey( 'Notes', $body );
		self::assertArrayNotHasKey( 'RoleTitle', $body );
		self::assertArrayNotHasKey( 'PhoneNumber', $body );
		self::assertArrayNotHasKey( 'CompanyId', $body );
	}

	public function test_applicant_body_uses_salary_from_or_zero(): void {
		$with = GiigApplicationSink::applicant_body( $this->payload(), '555' );
		self::assertSame( '555', $with['CandidateId'] );
		self::assertSame( '27918', $with['JobId'] );
		self::assertSame( 28000.0, $with['Salary'] );
		self::assertSame( 'Website', $with['Source'] );

		$without = GiigApplicationSink::applicant_body( $this->payload( array( 'salary_from' => null ) ), '555' );
		self::assertSame( 0, $without['Salary'] );
	}

	public function test_extract_applicant_id_tolerates_envelopes(): void {
		self::assertSame( '9001', GiigApplicationSink::extract_applicant_id( array( 'ApplicantId' => 9001 ) ) );
		self::assertSame( '9002', GiigApplicationSink::extract_applicant_id( array( 'data' => array( 'ApplicationId' => '9002' ) ) ) );
		self::assertSame( '9003', GiigApplicationSink::extract_applicant_id( array( 'result' => array( 'id' => 9003 ) ) ) );
		self::assertSame( '', GiigApplicationSink::extract_applicant_id( array( 'status' => 'ok' ) ) );
		self::assertSame( '', GiigApplicationSink::extract_applicant_id( array( 'Id' => 0 ) ) );
	}

	public function test_capabilities_report_no_cv_upload(): void {
		$sink = new GiigApplicationSink( new \Poolhall\Integration\Source\Giig\GiigClient( 'https://example.test', 't', 's' ) );
		$caps = $sink->capabilities();

		self::assertTrue( $caps->supports_candidate_create );
		self::assertTrue( $caps->supports_application_submit );
		self::assertFalse( $caps->supports_cv_upload );
		self::assertFalse( $caps->allows_first_party_apply() );
	}
}
