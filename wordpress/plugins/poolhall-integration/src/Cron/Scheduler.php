<?php
/**
 * Cron scheduling.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Cron;

/**
 * Four-hourly sync schedule (client agreed same-day freshness; we run every
 * four hours). Real server cron should hit wp-cron.php; WP-Cron pseudo-cron
 * is the fallback only (architecture doc §5).
 */
final class Scheduler {

	public const HOOK     = 'poolhall_job_sync';
	public const INTERVAL = 'poolhall_four_hours';

	public function register(): void {
		add_filter(
			'cron_schedules', // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 4h is intentional.
			static function ( array $schedules ): array {
				$schedules[ self::INTERVAL ] = array(
					'interval' => 4 * HOUR_IN_SECONDS,
					'display'  => __( 'Every four hours (Poolhall)', 'poolhall-integration' ),
				);
				return $schedules;
			}
		);
	}

	public static function activate(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::INTERVAL, self::HOOK );
		}
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}
}
