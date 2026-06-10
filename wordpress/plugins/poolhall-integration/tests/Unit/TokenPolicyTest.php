<?php
/**
 * TokenPolicy tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Accounts\TokenPolicy;

final class TokenPolicyTest extends TestCase {

	private TokenPolicy $policy;

	protected function setUp(): void {
		$this->policy = new TokenPolicy();
	}

	private function date( string $value ): \DateTimeImmutable {
		return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
	}

	public function test_generated_tokens_are_64_hex_chars_and_unique(): void {
		$first  = $this->policy->generate();
		$second = $this->policy->generate();

		self::assertTrue( $this->policy->is_well_formed( $first ) );
		self::assertTrue( $this->policy->is_well_formed( $second ) );
		self::assertNotSame( $first, $second );
	}

	public function test_token_matches_its_own_hash_only(): void {
		$token = $this->policy->generate();
		$hash  = $this->policy->hash_token( $token );

		self::assertTrue( $this->policy->matches( $token, $hash ) );
		self::assertFalse( $this->policy->matches( $this->policy->generate(), $hash ) );
	}

	public function test_hash_is_not_the_token(): void {
		$token = $this->policy->generate();

		self::assertNotSame( $token, $this->policy->hash_token( $token ) );
	}

	public function test_malformed_tokens_never_match(): void {
		$hash = $this->policy->hash_token( $this->policy->generate() );

		self::assertFalse( $this->policy->matches( '', $hash ) );
		self::assertFalse( $this->policy->matches( 'not-a-token', $hash ) );
		self::assertFalse( $this->policy->matches( strtoupper( $this->policy->generate() ), $hash ) );
	}

	public function test_empty_stored_hash_never_matches(): void {
		self::assertFalse( $this->policy->matches( $this->policy->generate(), '' ) );
	}

	public function test_verification_expiry_boundary_is_24_hours(): void {
		$issued = $this->date( '2026-06-10T12:00:00Z' );

		self::assertFalse( $this->policy->is_expired( $issued, $this->date( '2026-06-11T11:59:59Z' ), TokenPolicy::VERIFY_TTL_SECONDS ) );
		self::assertTrue( $this->policy->is_expired( $issued, $this->date( '2026-06-11T12:00:00Z' ), TokenPolicy::VERIFY_TTL_SECONDS ) );
	}

	public function test_reset_expiry_boundary_is_60_minutes(): void {
		$issued = $this->date( '2026-06-10T12:00:00Z' );

		self::assertFalse( $this->policy->is_expired( $issued, $this->date( '2026-06-10T12:59:59Z' ), TokenPolicy::RESET_TTL_SECONDS ) );
		self::assertTrue( $this->policy->is_expired( $issued, $this->date( '2026-06-10T13:00:00Z' ), TokenPolicy::RESET_TTL_SECONDS ) );
	}
}
