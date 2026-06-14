<?php
/**
 * Jobs archive query rules.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Owns the jobs-archive query contract (spec 01 §4, directive §3.2):
 * published jobs whose local expiry has not passed, narrowed by the public
 * filters (`q`, `location`, `sector`, `work_mode`, `type`, `salary_min`)
 * and ordered by `sort`. `build_args()` is the single source of truth for
 * those rules; the server-rendered results list and the legacy
 * `poolhall_jobs_archive` Elementor query both go through it. Filtered
 * result URLs are marked `noindex,follow`; only the bare `/jobs/` archive
 * is indexable.
 */
final class ArchiveQuery {

	public const QUERY_ID = 'poolhall_jobs_archive';

	public function register(): void {
		add_action( 'elementor/query/' . self::QUERY_ID, array( $this, 'apply' ) );
		add_filter( 'wp_robots', array( $this, 'noindex_filtered_results' ) );
	}

	/**
	 * WP_Query args for the archive under the given request.
	 *
	 * @return array<string,mixed>
	 */
	public function build_args( SearchRequest $request, int $per_page ): array {
		$meta_query = array(
			'relation' => 'AND',
			'expiry'   => array(
				'key'     => 'expires_at',
				'value'   => gmdate( \DateTimeInterface::ATOM ),
				'compare' => '>',
			),
		);

		if ( '' !== $request->location ) {
			$meta_query[] = array(
				'key'     => 'location_display',
				'value'   => $request->location,
				'compare' => 'LIKE',
			);
		}
		$needle = JobFacets::work_mode_needle( $request->work_mode );
		if ( '' !== $needle ) {
			$meta_query[] = array(
				'key'     => 'work_mode_raw',
				'value'   => $needle,
				'compare' => 'LIKE',
			);
		}
		if ( '' !== $request->type ) {
			$meta_query['type'] = array(
				'key'     => 'employment_type',
				'value'   => $request->type,
				'compare' => '=',
			);
		}
		if ( $request->salary_min > 0 ) {
			$meta_query['salary'] = array(
				'key'     => 'salary_min',
				'value'   => $request->salary_min,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}

		$args = array(
			'post_type'      => JobPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $request->page,
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
		);

		if ( '' !== $request->keyword ) {
			$args['s'] = $request->keyword;
		}
		if ( '' !== $request->sector ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small volume (~20 jobs).
				array(
					'taxonomy' => JobPostType::TAX_SECTOR,
					'field'    => 'slug',
					'terms'    => $request->sector,
				),
			);
		}

		switch ( $request->sort ) {
			case 'salary':
				$args['orderby']   = array(
					'salary' => 'DESC',
					'date'   => 'DESC',
				);
				$args['meta_key']  = 'salary_min'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['meta_type'] = 'NUMERIC';
				break;
			case 'az':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}

		return $args;
	}

	public function apply( \WP_Query $query ): void {
		$args = $this->build_args( $this->request(), (int) ( $query->get( 'posts_per_page' ) ?: 10 ) );
		foreach ( $args as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * @param array<string,bool> $robots Robots directives.
	 * @return array<string,bool>
	 */
	public function noindex_filtered_results( array $robots ): array {
		if ( is_page( 'jobs' ) && $this->request()->is_filtered() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	private function request(): SearchRequest {
		// Read-only public filters; no state changes, no nonce.
		return SearchRequest::from_query( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
