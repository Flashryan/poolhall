<?php
/**
 * EnquiryRequest tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Enquiries\EnquiryRequest;

final class EnquiryRequestTest extends TestCase {

	/**
	 * @param array<string,mixed> $overrides
	 */
	private function post( array $overrides = array() ): array {
		return array_merge(
			array(
				'name'          => 'Jane Smith',
				'company'       => 'Acme Ltd',
				'email'         => 'jane@acme.example',
				'phone'         => '07700 900000',
				'enquirer_type' => 'employer',
				'message'       => 'We need two welders.',
				'consent'       => '1',
			),
			$overrides
		);
	}

	public function test_valid_hiring_enquiry_has_no_errors(): void {
		$request = EnquiryRequest::from_post( EnquiryRequest::KIND_HIRING, $this->post() );

		self::assertSame( array(), $request->validation_errors() );
	}

	public function test_hiring_requires_company_but_contact_does_not(): void {
		$post = $this->post( array( 'company' => '' ) );

		self::assertContains( 'company_required', EnquiryRequest::from_post( EnquiryRequest::KIND_HIRING, $post )->validation_errors() );
		self::assertSame( array(), EnquiryRequest::from_post( EnquiryRequest::KIND_CONTACT, $post )->validation_errors() );
	}

	public function test_contact_requires_enquirer_type_but_hiring_does_not(): void {
		$post = $this->post( array( 'enquirer_type' => '' ) );

		self::assertContains( 'type_required', EnquiryRequest::from_post( EnquiryRequest::KIND_CONTACT, $post )->validation_errors() );
		self::assertSame( array(), EnquiryRequest::from_post( EnquiryRequest::KIND_HIRING, $post )->validation_errors() );
	}

	public function test_unknown_enquirer_type_is_rejected(): void {
		$request = EnquiryRequest::from_post( EnquiryRequest::KIND_CONTACT, $this->post( array( 'enquirer_type' => 'alien' ) ) );

		self::assertContains( 'type_required', $request->validation_errors() );
	}

	public function test_missing_consent_is_an_error(): void {
		$request = EnquiryRequest::from_post( EnquiryRequest::KIND_HIRING, $this->post( array( 'consent' => '' ) ) );

		self::assertContains( 'consent_required', $request->validation_errors() );
	}

	public function test_invalid_email_is_an_error(): void {
		$request = EnquiryRequest::from_post( EnquiryRequest::KIND_HIRING, $this->post( array( 'email' => 'not-an-email' ) ) );

		self::assertContains( 'email_invalid', $request->validation_errors() );
	}

	public function test_markup_is_stripped_and_fields_capped(): void {
		$request = EnquiryRequest::from_post(
			EnquiryRequest::KIND_HIRING,
			$this->post(
				array(
					'name'    => '<b>Jane</b>  Smith ',
					'message' => str_repeat( 'm', 6000 ),
				)
			)
		);

		self::assertSame( 'Jane Smith', $request->name );
		self::assertSame( 5000, mb_strlen( $request->message ) );
	}

	public function test_message_keeps_line_breaks(): void {
		$request = EnquiryRequest::from_post( EnquiryRequest::KIND_CONTACT, $this->post( array( 'message' => "Line one\nLine two" ) ) );

		self::assertSame( "Line one\nLine two", $request->message );
	}

	public function test_unknown_kind_falls_back_to_contact(): void {
		$request = EnquiryRequest::from_post( 'bogus', $this->post() );

		self::assertSame( EnquiryRequest::KIND_CONTACT, $request->kind );
	}
}
