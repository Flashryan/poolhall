<?php
/**
 * Job expiry policy.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Pure expiry rules (architecture doc §5):
 *  - Giig stores no closing date, so default expiry = datePosted + 30 days
 *    (client decision from the original brief). Revised 2026-07-01: source
 *    presence governs visibility — while the source still lists a job, the
 *    sync rolls its expiry clock forward instead of drafting it (see
 *    SyncService step 8), so this age-based rule only hides jobs the source
 *    no longer returns.
 *  - Staff may extend by 7/14/30 days; an explicit override always wins and
 *    a later source update must not silently overwrite it.
 *  - Warn staff 72 hours before expiry when the source still lists the job.
 */
final class ExpiryPolicy {

	public const DEFAULT_LIFETIME_DAYS  = 30;
	public const WARNING_WINDOW_HOURS   = 72;
	public const ALLOWED_EXTENSION_DAYS = array( 7, 14, 30 );

	public function expires_at(
		?\DateTimeImmutable $date_posted,
		?\DateTimeImmutable $override,
		\DateTimeImmutable $fallback_from,
	): \DateTimeImmutable {
		if ( null !== $override ) {
			return $override;
		}
		$base = $date_posted ?? $fallback_from;
		return $base->add( new \DateInterval( 'P' . self::DEFAULT_LIFETIME_DAYS . 'D' ) );
	}

	public function is_expired( \DateTimeImmutable $expires_at, \DateTimeImmutable $now ): bool {
		return $now >= $expires_at;
	}

	/** True inside the 72-hour pre-expiry warning window (and not yet expired). */
	public function needs_warning( \DateTimeImmutable $expires_at, \DateTimeImmutable $now ): bool {
		if ( $this->is_expired( $expires_at, $now ) ) {
			return false;
		}
		return $now >= $expires_at->sub( new \DateInterval( 'PT' . self::WARNING_WINDOW_HOURS . 'H' ) );
	}

	/**
	 * @throws \InvalidArgumentException For a non-approved extension length.
	 */
	public function extend(
		\DateTimeImmutable $current_expires_at,
		int $days,
		\DateTimeImmutable $now,
	): \DateTimeImmutable {
		if ( ! in_array( $days, self::ALLOWED_EXTENSION_DAYS, true ) ) {
			throw new \InvalidArgumentException( 'Extension must be 7, 14 or 30 days.' );
		}
		// Extend from now if already expired, otherwise from the current date.
		$base = max( $current_expires_at, $now );
		return $base->add( new \DateInterval( 'P' . $days . 'D' ) );
	}
}
