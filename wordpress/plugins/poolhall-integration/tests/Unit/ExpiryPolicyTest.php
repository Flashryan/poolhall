<?php
/**
 * ExpiryPolicy tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Jobs\ExpiryPolicy;

final class ExpiryPolicyTest extends TestCase {

	private ExpiryPolicy $policy;

	protected function setUp(): void {
		$this->policy = new ExpiryPolicy();
	}

	private function date( string $value ): \DateTimeImmutable {
		return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
	}

	public function test_default_expiry_is_thirty_days_after_posting(): void {
		$expires = $this->policy->expires_at( $this->date( '2026-06-01' ), null, $this->date( '2026-06-10' ) );

		self::assertSame( '2026-07-01', $expires->format( 'Y-m-d' ) );
	}

	public function test_missing_date_posted_falls_back_to_first_sync(): void {
		$expires = $this->policy->expires_at( null, null, $this->date( '2026-06-10' ) );

		self::assertSame( '2026-07-10', $expires->format( 'Y-m-d' ) );
	}

	public function test_admin_override_always_wins(): void {
		$override = $this->date( '2026-09-01' );
		$expires  = $this->policy->expires_at( $this->date( '2026-06-01' ), $override, $this->date( '2026-06-10' ) );

		self::assertSame( $override, $expires );
	}

	public function test_expiry_boundary(): void {
		$expires = $this->date( '2026-07-01T00:00:00Z' );

		self::assertFalse( $this->policy->is_expired( $expires, $this->date( '2026-06-30T23:59:59Z' ) ) );
		self::assertTrue( $this->policy->is_expired( $expires, $this->date( '2026-07-01T00:00:00Z' ) ) );
	}

	public function test_warning_window_is_72_hours(): void {
		$expires = $this->date( '2026-07-01T12:00:00Z' );

		self::assertFalse( $this->policy->needs_warning( $expires, $this->date( '2026-06-28T11:59:00Z' ) ) );
		self::assertTrue( $this->policy->needs_warning( $expires, $this->date( '2026-06-28T12:00:00Z' ) ) );
		self::assertTrue( $this->policy->needs_warning( $expires, $this->date( '2026-07-01T11:59:00Z' ) ) );
		// Already expired: no warning, it expires instead.
		self::assertFalse( $this->policy->needs_warning( $expires, $this->date( '2026-07-01T12:00:00Z' ) ) );
	}

	public function test_extension_from_live_job_extends_current_expiry(): void {
		$now      = $this->date( '2026-06-10' );
		$expires  = $this->date( '2026-06-20' );
		$extended = $this->policy->extend( $expires, 7, $now );

		self::assertSame( '2026-06-27', $extended->format( 'Y-m-d' ) );
	}

	public function test_extension_of_already_expired_job_extends_from_now(): void {
		$now      = $this->date( '2026-06-10' );
		$expires  = $this->date( '2026-06-01' );
		$extended = $this->policy->extend( $expires, 14, $now );

		self::assertSame( '2026-06-24', $extended->format( 'Y-m-d' ) );
	}

	public function test_unapproved_extension_length_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->policy->extend( $this->date( '2026-06-20' ), 60, $this->date( '2026-06-10' ) );
	}
}
