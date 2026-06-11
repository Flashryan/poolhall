<?php
/**
 * Login attempt rate limits.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Portal spec §5: rate limit by account and network signal without
 * permanently locking the account. A rolling window of recent failures
 * blocks further attempts; the block always clears by itself as failures
 * age out, so there is no lockout an attacker can weaponize against a
 * legitimate candidate. Decisions are pure functions of the failure
 * timestamps; persistence is FailedLoginStore's job.
 */
final class LoginRateLimiter {

	public const MAX_FAILURES   = 5;
	public const WINDOW_SECONDS = 15 * 60;

	/** '' when an attempt may proceed, 'locked' while the window holds too many failures. */
	public function denial_reason( array $previous_failures, \DateTimeImmutable $now ): string {
		return count( $this->within_window( $previous_failures, $now ) ) >= self::MAX_FAILURES ? 'locked' : '';
	}

	/**
	 * Seconds until a locked key unblocks (0 when not locked): the moment
	 * enough failures age past the window for the count to drop below the
	 * maximum.
	 */
	public function retry_after_seconds( array $previous_failures, \DateTimeImmutable $now ): int {
		$in_window = $this->within_window( $previous_failures, $now );
		$over      = count( $in_window ) - self::MAX_FAILURES;
		if ( $over < 0 ) {
			return 0;
		}
		usort(
			$in_window,
			static fn( \DateTimeImmutable $a, \DateTimeImmutable $b ): int => $a <=> $b
		);
		$unblocking = $in_window[ $over ]; // The failure whose expiry brings the count under the limit.
		return max( 1, $unblocking->getTimestamp() + self::WINDOW_SECONDS - $now->getTimestamp() );
	}

	/**
	 * History outside the window no longer affects any decision.
	 *
	 * @param \DateTimeImmutable[] $previous_failures Earlier failures.
	 * @return \DateTimeImmutable[]
	 */
	public function prune( array $previous_failures, \DateTimeImmutable $now ): array {
		return array_values( $this->within_window( $previous_failures, $now ) );
	}

	/**
	 * @param \DateTimeImmutable[] $failures Earlier failures, any order.
	 * @return \DateTimeImmutable[]
	 */
	private function within_window( array $failures, \DateTimeImmutable $now ): array {
		$now_ts = $now->getTimestamp();
		return array_filter(
			$failures,
			static function ( \DateTimeImmutable $failed_at ) use ( $now_ts ): bool {
				$age = $now_ts - $failed_at->getTimestamp();
				// Future timestamps (clock skew) never block forever.
				return $age >= 0 && $age < self::WINDOW_SECONDS;
			}
		);
	}
}
