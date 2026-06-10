<?php
/**
 * Verification-email resend limits.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Portal spec §4: resend cooldown 60 seconds, with hourly and daily limits.
 * Decisions are pure functions of the previous send timestamps so the rules
 * are unit-tested; persistence of the history is the repository's job.
 */
final class ResendPolicy {

	public const COOLDOWN_SECONDS = 60;
	public const MAX_PER_HOUR     = 3;
	public const MAX_PER_DAY      = 10;

	/**
	 * '' when sending is allowed, otherwise the limiting rule:
	 * 'cooldown', 'hourly_limit' or 'daily_limit'.
	 *
	 * @param \DateTimeImmutable[] $previous_sends Earlier sends, any order.
	 */
	public function denial_reason( array $previous_sends, \DateTimeImmutable $now ): string {
		$now_ts      = $now->getTimestamp();
		$last_hour   = 0;
		$last_day    = 0;
		$most_recent = null;

		foreach ( $previous_sends as $sent_at ) {
			$age = $now_ts - $sent_at->getTimestamp();
			if ( $age < 0 ) {
				continue; // Clock skew: a future timestamp never blocks forever.
			}
			if ( $age < 60 * 60 ) {
				++$last_hour;
			}
			if ( $age < 24 * 60 * 60 ) {
				++$last_day;
			}
			if ( null === $most_recent || $sent_at > $most_recent ) {
				$most_recent = $sent_at;
			}
		}

		if ( null !== $most_recent && ( $now_ts - $most_recent->getTimestamp() ) < self::COOLDOWN_SECONDS ) {
			return 'cooldown';
		}
		if ( $last_hour >= self::MAX_PER_HOUR ) {
			return 'hourly_limit';
		}
		if ( $last_day >= self::MAX_PER_DAY ) {
			return 'daily_limit';
		}
		return '';
	}

	/**
	 * History older than the daily window no longer affects any rule.
	 *
	 * @param \DateTimeImmutable[] $previous_sends Earlier sends.
	 * @return \DateTimeImmutable[]
	 */
	public function prune( array $previous_sends, \DateTimeImmutable $now ): array {
		$cutoff = $now->getTimestamp() - 24 * 60 * 60;
		return array_values(
			array_filter(
				$previous_sends,
				static fn( \DateTimeImmutable $sent_at ): bool => $sent_at->getTimestamp() > $cutoff
			)
		);
	}
}
