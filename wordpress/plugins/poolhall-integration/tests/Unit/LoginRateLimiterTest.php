<?php
/**
 * LoginRateLimiter tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Accounts\LoginRateLimiter;

final class LoginRateLimiterTest extends TestCase {

	private LoginRateLimiter $limiter;

	protected function setUp(): void {
		$this->limiter = new LoginRateLimiter();
	}

	private function date( string $value ): \DateTimeImmutable {
		return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
	}

	/** @return \DateTimeImmutable[] */
	private function failures( int $count, string $base = '2026-06-10T12:00:00Z' ): array {
		$failures = array();
		foreach ( range( 1, $count ) as $minute ) {
			$failures[] = $this->date( $base )->modify( sprintf( '+%d minutes', $minute ) );
		}
		return $failures;
	}

	public function test_no_failures_allows_an_attempt(): void {
		self::assertSame( '', $this->limiter->denial_reason( array(), $this->date( '2026-06-10T12:00:00Z' ) ) );
	}

	public function test_four_recent_failures_still_allow_an_attempt(): void {
		self::assertSame( '', $this->limiter->denial_reason( $this->failures( 4 ), $this->date( '2026-06-10T12:06:00Z' ) ) );
	}

	public function test_five_recent_failures_lock(): void {
		self::assertSame( 'locked', $this->limiter->denial_reason( $this->failures( 5 ), $this->date( '2026-06-10T12:06:00Z' ) ) );
	}

	public function test_lock_is_never_permanent(): void {
		// The same five failures stop counting once they age past the window.
		$failures = $this->failures( 5 );

		self::assertSame( 'locked', $this->limiter->denial_reason( $failures, $this->date( '2026-06-10T12:10:00Z' ) ) );
		self::assertSame( '', $this->limiter->denial_reason( $failures, $this->date( '2026-06-10T12:20:01Z' ) ) );
	}

	public function test_lock_clears_gradually_as_failures_age_out(): void {
		$failures = $this->failures( 6 );

		// At 12:16:30 the 12:01 failure has aged out: five remain, still locked.
		self::assertSame( 'locked', $this->limiter->denial_reason( $failures, $this->date( '2026-06-10T12:16:30Z' ) ) );
		// At 12:17:01 the 12:02 failure has aged out too: four remain, unlocked.
		self::assertSame( '', $this->limiter->denial_reason( $failures, $this->date( '2026-06-10T12:17:01Z' ) ) );
	}

	public function test_retry_after_reports_when_the_window_unlocks(): void {
		$failures = $this->failures( 5 ); // 12:01 … 12:05.

		// Locked at 12:10; the oldest failure (12:01) expires at 12:16.
		self::assertSame( 6 * 60, $this->limiter->retry_after_seconds( $failures, $this->date( '2026-06-10T12:10:00Z' ) ) );
		self::assertSame( 0, $this->limiter->retry_after_seconds( $this->failures( 4 ), $this->date( '2026-06-10T12:10:00Z' ) ) );
	}

	public function test_future_timestamps_never_block(): void {
		// Clock skew between web heads must not lock an account.
		$failures = $this->failures( 5, '2026-06-10T13:00:00Z' );

		self::assertSame( '', $this->limiter->denial_reason( $failures, $this->date( '2026-06-10T12:00:00Z' ) ) );
	}

	public function test_prune_drops_history_outside_the_window(): void {
		$now    = $this->date( '2026-06-10T12:00:00Z' );
		$pruned = $this->limiter->prune(
			array(
				$this->date( '2026-06-10T11:00:00Z' ),
				$this->date( '2026-06-10T11:50:00Z' ),
			),
			$now
		);

		self::assertCount( 1, $pruned );
		self::assertSame( '2026-06-10T11:50:00+00:00', $pruned[0]->format( \DateTimeInterface::ATOM ) );
	}
}
