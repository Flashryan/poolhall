<?php
/**
 * RegistrationRequest + CvRegistration payload tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Applications\CvRegistration;
use Poolhall\Integration\Applications\RegistrationRequest;

final class RegistrationRequestTest extends TestCase {

	private function post( array $overrides = array() ): array {
		return array_merge(
			array(
				'first_name'          => 'Jane',
				'last_name'           => 'Smith',
				'email'               => 'Jane@Example.com ',
				'phone'               => '07700 900000',
				'location'            => 'Wolverhampton',
				'role_title'          => 'Site Manager',
				'salary_expectations' => '£45,000+',
				'linkedin'            => 'https://www.linkedin.com/in/janesmith',
				'message'             => 'Four weeks notice.',
				'consent'             => '1',
			),
			$overrides
		);
	}

	public function test_valid_submission_has_no_errors(): void {
		$request = RegistrationRequest::from_post( $this->post() );

		self::assertSame( array(), $request->validation_errors() );
		self::assertSame( 'jane@example.com', $request->email );
		self::assertSame( 'Jane Smith', $request->full_name() );
	}

	public function test_required_fields_and_consent_enforced(): void {
		$request = RegistrationRequest::from_post(
			$this->post(
				array(
					'first_name' => '',
					'email'      => 'not-an-email',
					'phone'      => '',
					'consent'    => '',
				)
			)
		);

		$errors = $request->validation_errors();
		self::assertContains( 'first_name_required', $errors );
		self::assertContains( 'email_invalid', $errors );
		self::assertContains( 'phone_required', $errors );
		self::assertContains( 'consent_required', $errors );
	}

	public function test_linkedin_must_be_https(): void {
		$request = RegistrationRequest::from_post( $this->post( array( 'linkedin' => 'http://linkedin.com/in/x' ) ) );
		self::assertContains( 'linkedin_invalid', $request->validation_errors() );

		$blank = RegistrationRequest::from_post( $this->post( array( 'linkedin' => '' ) ) );
		self::assertNotContains( 'linkedin_invalid', $blank->validation_errors() );
	}

	public function test_payload_maps_every_field_for_giig(): void {
		$request = RegistrationRequest::from_post( $this->post() );
		$payload = CvRegistration::payload( $request, 'jane-smith-cv.pdf' );

		self::assertSame( 'Jane', $payload->first_name );
		self::assertSame( 'Smith', $payload->last_name );
		self::assertSame( 'jane@example.com', $payload->email );
		self::assertSame( '07700 900000', $payload->phone );
		self::assertSame( 'Site Manager', $payload->role_title );
		self::assertSame( 'Wolverhampton', $payload->location );
		self::assertSame( '£45,000+', $payload->salary_expectations );
		self::assertSame( 'https://www.linkedin.com/in/janesmith', $payload->linkedin );
		self::assertStringContainsString( 'Four weeks notice.', $payload->notes );
		self::assertStringContainsString( 'jane-smith-cv.pdf', $payload->notes );
		self::assertTrue( $payload->is_valid() );
	}
}
