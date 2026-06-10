<?php
/**
 * ResendPolicy tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Accounts\ResendPolicy;

final class ResendPolicyTest extends TestCase {

	private ResendPolicy $policy;

	protected function setUp(): void {
		$this->policy = new ResendPolicy();
	}

	private function date( string $value ): \DateTimeImmutable {
		return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
	}

	public function test_first_send_is_allowed(): void {
		self::assertSame( '', $this->policy->denial_reason( array(), $this->date( '2026-06-10T12:00:00Z' ) ) );
	}

	public function test_cooldown_blocks_within_60_seconds(): void {
		$sends = array( $this->date( '2026-06-10T12:00:00Z' ) );

		self::assertSame( 'cooldown', $this->policy->denial_reason( $sends, $this->date( '2026-06-10T12:00:59Z' ) ) );
		self::assertSame( '', $this->policy->denial_reason( $sends, $this->date( '2026-06-10T12:01:00Z' ) ) );
	}

	public function test_hourly_limit_blocks_a_fourth_send(): void {
		$sends = array(
			$this->date( '2026-06-10T11:10:00Z' ),
			$this->date( '2026-06-10T11:30:00Z' ),
			$this->date( '2026-06-10T11:50:00Z' ),
		);

		self::assertSame( 'hourly_limit', $this->policy->denial_reason( $sends, $this->date( '2026-06-10T12:00:00Z' ) ) );
	}

	public function test_sends_older_than_an_hour_free_the_hourly_limit(): void {
		$sends = array(
			$this->date( '2026-06-10T10:00:00Z' ),
			$this->date( '2026-06-10T11:30:00Z' ),
			$this->date( '2026-06-10T11:50:00Z' ),
		);

		self::assertSame( '', $this->policy->denial_reason( $sends, $this->date( '2026-06-10T12:00:00Z' ) ) );
	}

	public function test_daily_limit_blocks_an_eleventh_send(): void {
		$sends = array();
		foreach ( range( 0, 9 ) as $hours_ago ) {
			$sends[] = $this->date( '2026-06-10T12:00:00Z' )->modify( sprintf( '-%d hours -10 minutes', 2 * $hours_ago ) );
		}

		self::assertSame( 'daily_limit', $this->policy->denial_reason( $sends, $this->date( '2026-06-10T12:00:00Z' ) ) );
	}

	public function test_future_timestamps_do_not_block(): void {
		// Clock skew between web heads must not lock an account out of resends.
		$sends = array( $this->date( '2026-06-10T12:05:00Z' ) );

		self::assertSame( '', $this->policy->denial_reason( $sends, $this->date( '2026-06-10T12:00:00Z' ) ) );
	}

	public function test_prune_drops_history_older_than_a_day(): void {
		$now    = $this->date( '2026-06-10T12:00:00Z' );
		$sends  = array(
			$this->date( '2026-06-09T11:00:00Z' ),
			$this->date( '2026-06-10T11:00:00Z' ),
		);
		$pruned = $this->policy->prune( $sends, $now );

		self::assertCount( 1, $pruned );
		self::assertSame( '2026-06-10T11:00:00+00:00', $pruned[0]->format( \DateTimeInterface::ATOM ) );
	}
}
