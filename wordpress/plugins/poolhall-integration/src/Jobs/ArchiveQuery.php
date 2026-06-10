<?php
/**
 * Jobs archive query rules.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Owns the `poolhall_jobs_archive` Elementor query (design system §13):
 * published jobs whose local expiry has not passed, so a job never lingers
 * in the archive between the expiry cron runs. Filters and sorting will
 * extend this query service when the results widget lands.
 */
final class ArchiveQuery {

	public const QUERY_ID = 'poolhall_jobs_archive';

	public function register(): void {
		add_action( 'elementor/query/' . self::QUERY_ID, array( $this, 'apply' ) );
	}

	public function apply( \WP_Query $query ): void {
		$query->set( 'post_type', JobPostType::POST_TYPE );
		$query->set( 'post_status', 'publish' );

		$meta_query   = (array) $query->get( 'meta_query' );
		$meta_query[] = array(
			'key'     => 'expires_at',
			'value'   => gmdate( \DateTimeInterface::ATOM ),
			'compare' => '>',
		);
		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
	}
}
