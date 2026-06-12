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
 *
 * The selection is resolved to explicit IDs first (two cheap ID lookups
 * over a ~20-job store) because ordering by an OR'd EXISTS/NOT EXISTS
 * meta clause binds the sort column to arbitrary meta rows for posts
 * without the flag, which breaks the featured-first ordering on both
 * MySQL and SQLite.
 */
final class FeaturedQuery {

	public const QUERY_ID = 'poolhall_featured_jobs';
	public const MAX_JOBS = 6;

	/**
	 * Elementor dispatches `elementor/query/{id}` through a global
	 * pre_get_posts wrapper while the widget query is being built, so the
	 * ID lookups below would re-enter this handler forever without a guard.
	 */
	private bool $resolving = false;

	public function register(): void {
		add_action( 'elementor/query/' . self::QUERY_ID, array( $this, 'apply' ) );
	}

	public function apply( \WP_Query $query ): void {
		if ( $this->resolving ) {
			return;
		}
		$this->resolving = true;
		$ids             = $this->selected_ids();
		$this->resolving = false;

		$query->set( 'post_type', JobPostType::POST_TYPE );
		$query->set( 'post_status', 'publish' );
		$query->set( 'posts_per_page', self::MAX_JOBS );
		// An empty carousel is the honest state when no live jobs exist.
		$query->set( 'post__in', array() === $ids ? array( 0 ) : $ids );
		$query->set( 'orderby', 'post__in' );
		$query->set( 'ignore_sticky_posts', true );
	}

	/**
	 * @return int[] Up to six job IDs, featured first, newest first within
	 *               each group.
	 */
	private function selected_ids(): array {
		$live = array(
			'key'     => 'expires_at',
			'value'   => gmdate( \DateTimeInterface::ATOM ),
			'compare' => '>',
		);

		$featured = get_posts(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_JOBS,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
					$live,
					array(
						'key'     => 'is_featured',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$remaining = self::MAX_JOBS - count( $featured );
		if ( $remaining <= 0 ) {
			return array_map( 'intval', $featured );
		}

		$newest = get_posts(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $remaining,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post__not_in'   => array() === $featured ? array( 0 ) : $featured, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- bounded list of at most six IDs.
				'meta_query'     => array( $live ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
			)
		);

		return array_map( 'intval', array_merge( $featured, $newest ) );
	}
}
