<?php
/**
 * EmailAddress tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Accounts\EmailAddress;

final class EmailAddressTest extends TestCase {

	public function test_normalize_trims_and_lowercases(): void {
		self::assertSame( 'sam.jones@example.co.uk', EmailAddress::normalize( '  Sam.Jones@Example.CO.UK ' ) );
	}

	public function test_valid_addresses(): void {
		self::assertTrue( EmailAddress::is_valid( 'candidate@example.com' ) );
		self::assertTrue( EmailAddress::is_valid( ' Candidate+tag@example.com ' ) );
	}

	public function test_invalid_addresses(): void {
		self::assertFalse( EmailAddress::is_valid( '' ) );
		self::assertFalse( EmailAddress::is_valid( 'not-an-email' ) );
		self::assertFalse( EmailAddress::is_valid( 'two@@example.com' ) );
		self::assertFalse( EmailAddress::is_valid( str_repeat( 'a', 250 ) . '@example.com' ) );
	}

	public function test_local_part(): void {
		self::assertSame( 'sam.jones', EmailAddress::local_part( 'Sam.Jones@example.com' ) );
		self::assertSame( '', EmailAddress::local_part( 'no-at-sign' ) );
	}
}
