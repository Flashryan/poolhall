<?php
/**
 * Saved jobs storage.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Jobs\JobRepository;

/**
 * Saved jobs (portal spec §7) keyed by source identity, not post ID: when a
 * sync recreates a job under the same source ID the saved relationship is
 * preserved, and a saved record can never attach to a different source job.
 * Saves and removals are idempotent. Hard rule 6: this authenticated service
 * is the only saved-jobs state — never local browser storage.
 */
final class SavedJobsRepository {

	private const TABLE = 'poolhall_saved_jobs';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** Create/upgrade the table (called from activation and migrations). */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				source VARCHAR(32) NOT NULL,
				source_job_id VARCHAR(64) NOT NULL,
				saved_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_job (user_id,source,source_job_id),
				KEY user_id (user_id)
			) {$charset};"
		);
	}

	/** True when newly saved; repeating the request is a no-op, not an error. */
	public function save( int $user_id, string $source, string $source_job_id, \DateTimeImmutable $now ): bool {
		if ( $this->is_saved( $user_id, $source, $source_job_id ) ) {
			return false;
		}
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			array(
				'user_id'       => $user_id,
				'source'        => $source,
				'source_job_id' => $source_job_id,
				'saved_at'      => $now->format( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
		return true;
	}

	/** True when a saved record was removed; already-unsaved is a no-op. */
	public function unsave( int $user_id, string $source, string $source_job_id ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			array(
				'user_id'       => $user_id,
				'source'        => $source,
				'source_job_id' => $source_job_id,
			),
			array( '%d', '%s', '%s' )
		);
		return (int) $deleted > 0;
	}

	public function is_saved( int $user_id, string $source, string $source_job_id ): bool {
		global $wpdb;
		$table = self::table_name();
		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND source = %s AND source_job_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from trusted constant.
				$user_id,
				$source,
				$source_job_id
			)
		);
		return null !== $found;
	}

	/**
	 * The candidate's saved jobs with the current role status, live jobs
	 * first then newest saved first (portal spec §7). The post is resolved
	 * by source identity at read time so recreated jobs stay attached.
	 *
	 * @return array<int,array{source:string,source_job_id:string,saved_at:string,post_id:int|null,status:'live'|'expired'|'removed'}>
	 */
	public function list_for_user( int $user_id, JobRepository $jobs ): array {
		global $wpdb;
		$table = self::table_name();
		$rows  = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT source, source_job_id, saved_at FROM {$table} WHERE user_id = %d ORDER BY saved_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			),
			ARRAY_A
		);

		$items = array();
		foreach ( $rows as $row ) {
			$post_id = $jobs->find_post_id( (string) $row['source'], (string) $row['source_job_id'] );
			$status  = 'removed';
			if ( null !== $post_id ) {
				$status = 'publish' === get_post_status( $post_id ) ? 'live' : 'expired';
			}
			$items[] = array(
				'source'        => (string) $row['source'],
				'source_job_id' => (string) $row['source_job_id'],
				'saved_at'      => (string) $row['saved_at'],
				'post_id'       => $post_id,
				'status'        => $status,
			);
		}

		// Stable partition: live first, the saved_at DESC order kept within each group.
		$live  = array_filter( $items, static fn( array $item ): bool => 'live' === $item['status'] );
		$other = array_filter( $items, static fn( array $item ): bool => 'live' !== $item['status'] );
		return array_values( array_merge( $live, $other ) );
	}

	public function count_for_user( int $user_id ): int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
	}

	/**
	 * Candidate-confirmed bulk removal of saved jobs that are no longer
	 * live (portal spec §7). Returns the number removed.
	 */
	public function clear_not_live_for_user( int $user_id, JobRepository $jobs ): int {
		$removed = 0;
		foreach ( $this->list_for_user( $user_id, $jobs ) as $item ) {
			if ( 'live' !== $item['status'] && $this->unsave( $user_id, $item['source'], $item['source_job_id'] ) ) {
				++$removed;
			}
		}
		return $removed;
	}
}
