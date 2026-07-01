<?php
/**
 * Structured event logger.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Support;

/**
 * Structured log events (architecture doc §14) in a custom table.
 * Context must already be PII-free when it reaches here; convention is
 * enforced by callers and checked in review, the logger additionally strips
 * obviously sensitive keys as a backstop.
 */
final class Logger {

	private const TABLE         = 'poolhall_log';
	private const SENSITIVE     = array( 'email', 'phone', 'password', 'token', 'secret', 'cv', 'authorization' );
	private const MAX_ROWS_KEPT = 2000;

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** Create/upgrade the log table (called from migrations). */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				event VARCHAR(64) NOT NULL,
				correlation_id VARCHAR(36) NOT NULL DEFAULT '',
				context LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY event (event),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/**
	 * @param array<string,mixed> $context PII-free context.
	 */
	public function log( string $event, array $context = array(), string $correlation_id = '' ): void {
		global $wpdb;

		foreach ( array_keys( $context ) as $key ) {
			foreach ( self::SENSITIVE as $needle ) {
				if ( str_contains( strtolower( (string) $key ), $needle ) ) {
					$context[ $key ] = '[redacted]';
				}
			}
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			array(
				'event'          => substr( $event, 0, 64 ),
				'correlation_id' => substr( $correlation_id, 0, 36 ),
				'context'        => wp_json_encode( $context ),
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Latest events for the admin health screen.
	 *
	 * @return array<int,object>
	 */
	public function recent( int $limit = 20, ?string $event = null ): array {
		global $wpdb;
		$table = self::table_name();

		if ( null !== $event ) {
			$sql = $wpdb->prepare(
				"SELECT event, correlation_id, context, created_at FROM {$table} WHERE event = %s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
				$event,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT event, correlation_id, context, created_at FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			);
		}

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Trim old rows; called occasionally from cron. */
	public function prune(): void {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id < (SELECT min_id FROM (SELECT MIN(id) AS min_id FROM (SELECT id FROM {$table} ORDER BY id DESC LIMIT %d) keep_rows) bounds)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::MAX_ROWS_KEPT
			)
		);
	}
}
