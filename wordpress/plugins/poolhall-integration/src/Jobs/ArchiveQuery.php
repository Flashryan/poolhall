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
 * in the archive between the expiry cron runs. Applies the public search
 * parameters (`q`, `location`, `sector` — spec 01 §4) server-side, and
 * marks filtered result URLs `noindex,follow` (the page's canonical
 * already points at the clean `/jobs/` permalink). The remaining filters
 * and sorting extend this service when the results widget lands.
 */
final class ArchiveQuery {

	public const QUERY_ID = 'poolhall_jobs_archive';

	public function register(): void {
		add_action( 'elementor/query/' . self::QUERY_ID, array( $this, 'apply' ) );
		add_filter( 'wp_robots', array( $this, 'noindex_filtered_results' ) );
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

		$request = $this->request();
		if ( '' !== $request->keyword ) {
			$query->set( 's', $request->keyword );
		}
		if ( '' !== $request->location ) {
			$meta_query[] = array(
				'key'     => 'location_display',
				'value'   => $request->location,
				'compare' => 'LIKE',
			);
		}
		if ( '' !== $request->sector ) {
			$query->set(
				'tax_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small volume (~20 jobs).
				array(
					array(
						'taxonomy' => JobPostType::TAX_SECTOR,
						'field'    => 'slug',
						'terms'    => $request->sector,
					),
				)
			);
		}

		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
	}

	/**
	 * Search/filtered result URLs are `noindex,follow` (spec 01 §4); only
	 * the clean `/jobs/` archive is indexable. Page canonicals already
	 * point at the bare permalink, so no canonical override is needed.
	 *
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
		// Read-only public search parameters; no state changes, no nonce.
		return SearchRequest::from_query( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
