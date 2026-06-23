<?php
/**
 * CandidatePayload tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Source\CandidatePayload;

final class CandidatePayloadTest extends TestCase {

	public function test_first_and_last_name_make_it_valid(): void {
		$payload = new CandidatePayload( 'Jane', 'Smith' );

		self::assertTrue( $payload->is_valid() );
	}

	public function test_missing_either_name_is_invalid(): void {
		self::assertFalse( ( new CandidatePayload( 'Jane', '' ) )->is_valid() );
		self::assertFalse( ( new CandidatePayload( '', 'Smith' ) )->is_valid() );
	}

	public function test_whitespace_only_name_is_invalid(): void {
		self::assertFalse( ( new CandidatePayload( '   ', "\t" ) )->is_valid() );
	}

	public function test_source_defaults_to_website(): void {
		self::assertSame( 'Website', ( new CandidatePayload( 'Jane', 'Smith' ) )->source );
	}

	public function test_optional_fields_default_to_empty(): void {
		$payload = new CandidatePayload( 'Jane', 'Smith' );

		self::assertSame( '', $payload->phone );
		self::assertSame( '', $payload->role_title );
		self::assertSame( '', $payload->linkedin );
		self::assertSame( '', $payload->notes );
	}
}
