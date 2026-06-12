<?php
/**
 * Featured jobs query rules.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Owns the `poolhall_featured_jobs` Elementor query for the home carousel
 * (design system §12): published, unexpired jobs, manually featured first
 * then newest, capped at six. The `is_featured` flag is editor-owned post
 * meta (set from the job edit screen); jobs without the flag still fill
 * the carousel so it never renders empty while live roles exist.
 */
final class FeaturedQuery {

	public const QUERY_ID = 'poolhall_featured_jobs';
	public const MAX_JOBS = 6;

	public function register(): void {
		add_action( 'elementor/query/' . self::QUERY_ID, array( $this, 'apply' ) );
	}

	public function apply( \WP_Query $query ): void {
		$query->set( 'post_type', JobPostType::POST_TYPE );
		$query->set( 'post_status', 'publish' );
		$query->set( 'posts_per_page', self::MAX_JOBS );

		// The OR pair makes every live job eligible while exposing a named
		// clause to order by: jobs carrying the flag sort before jobs
		// without it (NULL joins sort last under DESC in MySQL and SQLite).
		$query->set(
			'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
			array(
				'relation' => 'AND',
				array(
					'key'     => 'expires_at',
					'value'   => gmdate( \DateTimeInterface::ATOM ),
					'compare' => '>',
				),
				array(
					'relation'         => 'OR',
					'featured_flag'    => array(
						'key'     => 'is_featured',
						'compare' => 'EXISTS',
					),
					'featured_missing' => array(
						'key'     => 'is_featured',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$query->set(
			'orderby',
			array(
				'featured_flag' => 'DESC',
				'date'          => 'DESC',
			)
		);
	}
}
