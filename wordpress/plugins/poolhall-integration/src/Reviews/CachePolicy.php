<?php
/**
 * Reviews cache policy.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Reviews;

/**
 * Pure cache rules (architecture doc §10): refresh every 24 hours, serve
 * stale for up to 7 days during API failure, hide the carousel when there
 * is no valid cache at all.
 */
final class CachePolicy {

	public const FRESH_HOURS    = 24;
	public const STALE_MAX_DAYS = 7;

	public function needs_refresh( ?ReviewsSnapshot $cached, \DateTimeImmutable $now ): bool {
		if ( null === $cached ) {
			return true;
		}
		return $now >= $cached->fetched_at->add( new \DateInterval( 'PT' . self::FRESH_HOURS . 'H' ) );
	}

	/** May this snapshot still be served after a failed refresh? */
	public function is_servable( ?ReviewsSnapshot $cached, \DateTimeImmutable $now ): bool {
		if ( null === $cached || array() === $cached->reviews ) {
			return false;
		}
		return $now < $cached->fetched_at->add( new \DateInterval( 'P' . self::STALE_MAX_DAYS . 'D' ) );
	}
}
