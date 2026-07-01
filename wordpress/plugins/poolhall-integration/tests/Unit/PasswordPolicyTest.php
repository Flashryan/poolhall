<?php
/**
 * PasswordPolicy tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Accounts\PasswordPolicy;

final class PasswordPolicyTest extends TestCase {

	private PasswordPolicy $policy;

	protected function setUp(): void {
		$this->policy = new PasswordPolicy();
	}

	public function test_eleven_characters_is_too_short(): void {
		self::assertSame( array( 'too_short' ), $this->policy->validate( 'abcDef12345' ) );
	}

	public function test_twelve_character_passphrase_is_accepted(): void {
		self::assertSame( array(), $this->policy->validate( 'plum kettle9' ) );
	}

	public function test_over_128_characters_is_too_long(): void {
		$password = str_repeat( 'correct horse battery staple ', 5 ); // 145 chars.

		self::assertContains( 'too_long', $this->policy->validate( $password ) );
	}

	public function test_spaces_and_unicode_are_allowed(): void {
		self::assertSame( array(), $this->policy->validate( 'pferd Straße käse Mond' ) );
	}

	public function test_unicode_length_is_counted_in_characters_not_bytes(): void {
		// 12 multibyte characters: must pass the length rule.
		self::assertSame( array(), $this->policy->validate( 'äöüßéàçñøåæž' ) );
	}

	public function test_common_password_is_rejected_case_insensitively(): void {
		self::assertContains( 'common_password', $this->policy->validate( 'Password1234' ) );
		self::assertContains( 'common_password', $this->policy->validate( 'ADMINISTRATOR' ) );
	}

	public function test_low_variety_mash_is_rejected(): void {
		self::assertContains( 'low_variety', $this->policy->validate( 'aaaabbbbaaaabbbb' ) );
		self::assertContains( 'low_variety', $this->policy->validate( '121212121212' ) );
	}

	public function test_password_containing_email_local_part_is_rejected(): void {
		$errors = $this->policy->validate( 'sam.jones-2026!', 'Sam.Jones@example.co.uk' );

		self::assertContains( 'contains_email', $errors );
	}

	public function test_short_local_part_does_not_trigger_email_rule(): void {
		// 'sj' is under the 4-char floor: too easy to hit by coincidence.
		self::assertSame( array(), $this->policy->validate( 'sj likes good tea', 'sj@example.com' ) );
	}

	public function test_no_character_class_rules(): void {
		// All-lowercase, no digits or symbols: acceptable per spec.
		self::assertSame( array(), $this->policy->validate( 'quiet velvet morning' ) );
	}
}
