<?php
/**
 * Reviews service.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Reviews;

use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Support\Logger;

/**
 * Owns the cached snapshot. Public pages only ever read the cache; the
 * Places API is hit at most daily by cron (or on first demand when the
 * cache is empty), never during normal page rendering with a warm cache.
 */
final class ReviewsService {

	private const OPTION       = 'poolhall_reviews_snapshot';
	private const ERROR_OPTION = 'poolhall_reviews_last_error';

	/** @var callable():ReviewsSnapshot */
	private $fetcher;

	/**
	 * @param callable():ReviewsSnapshot $fetcher Usually PlacesClient::fetch_snapshot; injectable for tests.
	 */
	public function __construct(
		callable $fetcher,
		private readonly CachePolicy $policy,
		private readonly Logger $logger,
	) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Snapshot for display, or null (carousel hides, layout stays intact).
	 */
	public function snapshot_for_display(): ?ReviewsSnapshot {
		$now    = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$cached = $this->cached();

		if ( ! $this->policy->needs_refresh( $cached, $now ) ) {
			return $this->policy->is_servable( $cached, $now ) ? $cached : null;
		}

		// Backoff: with nothing cached and a recent failure, do not re-fetch
		// on every render (a bad key would otherwise add an API call per
		// pageview). The cron retries after the cooldown.
		if ( null === $cached && $this->recently_failed( $now ) ) {
			return null;
		}

		try {
			$fresh = ( $this->fetcher )();
			update_option( self::OPTION, $fresh->to_array(), false );
			delete_option( self::ERROR_OPTION );
			return $this->policy->is_servable( $fresh, $now ) ? $fresh : null;
		} catch ( SourceException $e ) {
			update_option(
				self::ERROR_OPTION,
				array(
					'error' => $e->getMessage(),
					'at'    => $now->format( \DateTimeInterface::ATOM ),
				),
				false
			);
			$this->logger->log( 'reviews_refresh_failed', array( 'error' => $e->getMessage() ) );
			// Stale-tolerant fallback.
			return $this->policy->is_servable( $cached, $now ) ? $cached : null;
		}
	}

	/** Cron entry point: refresh without rendering. */
	public function refresh(): void {
		$this->snapshot_for_display();
	}

	private function recently_failed( \DateTimeImmutable $now ): bool {
		$error = get_option( self::ERROR_OPTION, null );
		if ( ! is_array( $error ) || ! isset( $error['at'] ) || ! is_string( $error['at'] ) ) {
			return false;
		}
		try {
			$at = new \DateTimeImmutable( $error['at'] );
		} catch ( \Exception ) {
			return false;
		}
		return $now < $at->add( new \DateInterval( 'PT30M' ) );
	}

	private function cached(): ?ReviewsSnapshot {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			return null;
		}
		try {
			return ReviewsSnapshot::from_array( $stored );
		} catch ( \Exception ) {
			return null;
		}
	}

	/**
	 * Health info for the admin screen.
	 *
	 * @return array<string,mixed>
	 */
	public function health(): array {
		$cached = $this->cached();
		$error  = get_option( self::ERROR_OPTION, null );
		return array(
			'last_refresh' => $cached?->fetched_at->format( \DateTimeInterface::ATOM ),
			'review_count' => null === $cached ? 0 : count( $cached->reviews ),
			'last_error'   => is_array( $error ) ? $error : null,
		);
	}
}
