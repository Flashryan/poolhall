<?php
/**
 * ApplicationRequest tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Applications\ApplicationRequest;

final class ApplicationRequestTest extends TestCase {

	/**
	 * @param array<string,mixed> $overrides
	 * @return array<string,mixed>
	 */
	private function post( array $overrides = array() ): array {
		return array_merge(
			array(
				'first_name' => 'Jane',
				'last_name'  => 'Smith',
				'email'      => 'jane@example.com',
				'phone'      => '07700 900000',
				'message'    => 'Keen to apply for this role.',
				'consent'    => '1',
			),
			$overrides
		);
	}

	public function test_valid_application_has_no_errors(): void {
		$request = ApplicationRequest::from_post( $this->post() );

		self::assertSame( array(), $request->validation_errors() );
	}

	public function test_each_required_text_field_is_reported(): void {
		$request = ApplicationRequest::from_post(
			$this->post(
				array(
					'first_name' => '',
					'last_name'  => '',
					'phone'      => '',
				)
			)
		);

		$errors = $request->validation_errors();
		self::assertContains( 'first_name_required', $errors );
		self::assertContains( 'last_name_required', $errors );
		self::assertContains( 'phone_required', $errors );
	}

	public function test_invalid_email_is_an_error(): void {
		$request = ApplicationRequest::from_post( $this->post( array( 'email' => 'not-an-email' ) ) );

		self::assertContains( 'email_invalid', $request->validation_errors() );
	}

	public function test_email_is_normalized(): void {
		$request = ApplicationRequest::from_post( $this->post( array( 'email' => '  Jane@Example.COM ' ) ) );

		self::assertSame( 'jane@example.com', $request->email );
		self::assertSame( array(), $request->validation_errors() );
	}

	public function test_missing_consent_is_an_error(): void {
		$request = ApplicationRequest::from_post( $this->post( array( 'consent' => '' ) ) );

		self::assertContains( 'consent_required', $request->validation_errors() );
	}

	public function test_only_the_string_one_grants_consent(): void {
		self::assertContains( 'consent_required', ApplicationRequest::from_post( $this->post( array( 'consent' => 'yes' ) ) )->validation_errors() );
		self::assertContains( 'consent_required', ApplicationRequest::from_post( $this->post( array( 'consent' => 'on' ) ) )->validation_errors() );
		self::assertNotContains( 'consent_required', ApplicationRequest::from_post( $this->post( array( 'consent' => '1' ) ) )->validation_errors() );
	}

	public function test_markup_is_stripped_and_whitespace_collapsed(): void {
		$request = ApplicationRequest::from_post(
			$this->post(
				array(
					'first_name' => '  <b>Jane</b>   Anne ',
					'phone'      => " 07700\t900000 ",
				)
			)
		);

		self::assertSame( 'Jane Anne', $request->first_name );
		self::assertSame( '07700 900000', $request->phone );
	}

	public function test_name_fields_are_capped(): void {
		$request = ApplicationRequest::from_post( $this->post( array( 'first_name' => str_repeat( 'a', 200 ) ) ) );

		self::assertSame( 100, mb_strlen( $request->first_name ) );
	}

	public function test_message_is_optional_and_keeps_line_breaks(): void {
		$request = ApplicationRequest::from_post( $this->post( array( 'message' => "Line one\nLine two" ) ) );

		self::assertSame( "Line one\nLine two", $request->message );
		self::assertSame( array(), ApplicationRequest::from_post( $this->post( array( 'message' => '' ) ) )->validation_errors() );
	}

	public function test_message_is_capped(): void {
		$request = ApplicationRequest::from_post( $this->post( array( 'message' => str_repeat( 'm', 5000 ) ) ) );

		self::assertSame( 4000, mb_strlen( $request->message ) );
	}

	public function test_full_name_joins_first_and_last(): void {
		$request = ApplicationRequest::from_post( $this->post() );

		self::assertSame( 'Jane Smith', $request->full_name() );
	}

	public function test_non_string_fields_are_treated_as_empty(): void {
		$request = ApplicationRequest::from_post(
			array(
				'first_name' => array( 'x' ),
				'email'      => null,
			)
		);

		self::assertSame( '', $request->first_name );
		self::assertContains( 'first_name_required', $request->validation_errors() );
		self::assertContains( 'email_invalid', $request->validation_errors() );
	}
}
