<?php
/**
 * Live filter facets for the jobs archive.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Computes the filter options the jobs archive sidebar can offer, with
 * per-option counts, from the live (published, unexpired) job store only.
 * Groups with no data are simply omitted by the renderer, so a filter
 * never offers a value that would return nothing, and the Job type group
 * stays hidden until the source actually populates employment types
 * (honest, data-driven — no invented options). Salary bounds frame the
 * minimum-salary control.
 */
final class JobFacets {

	/** Working-pattern buckets mapped to a case-insensitive match needle. */
	private const WORK_MODES = array(
		'onsite' => array(
			'label'  => 'Must be onsite',
			'needle' => 'onsite',
		),
		'hybrid' => array(
			'label'  => 'Part remote / hybrid',
			'needle' => 'part remote',
		),
		'remote' => array(
			'label'  => 'Fully remote',
			'needle' => 'fully remote',
		),
	);

	/**
	 * @return array{
	 *   total:int,
	 *   sectors:array<int,array{slug:string,name:string,count:int}>,
	 *   work_modes:array<int,array{slug:string,label:string,count:int}>,
	 *   types:array<int,array{slug:string,label:string,count:int}>,
	 *   salary_min:int,
	 *   salary_max:int
	 * }
	 */
	public function compute(): array {
		$ids = $this->live_ids();

		return array(
			'total'      => count( $ids ),
			'sectors'    => $this->sector_facet( $ids ),
			'work_modes' => $this->work_mode_facet( $ids ),
			'types'      => $this->type_facet( $ids ),
			'salary_min' => $this->salary_bound( $ids, 'min' ),
			'salary_max' => $this->salary_bound( $ids, 'max' ),
		);
	}

	/** @return int[] */
	private function live_ids(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => JobPostType::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- facet scan over a small (~20) job store.
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
						array(
							'key'     => 'expires_at',
							'value'   => gmdate( \DateTimeInterface::ATOM ),
							'compare' => '>',
						),
					),
				)
			)
		);
	}

	/**
	 * @param int[] $ids
	 * @return array<int,array{slug:string,name:string,count:int}>
	 */
	private function sector_facet( array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}
		$counts = array();
		$names  = array();
		foreach ( $ids as $id ) {
			$terms = get_the_terms( $id, JobPostType::TAX_SECTOR );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$counts[ $term->slug ] = ( $counts[ $term->slug ] ?? 0 ) + 1;
				$names[ $term->slug ]  = $term->name;
			}
		}
		ksort( $counts );
		$out = array();
		foreach ( $counts as $slug => $count ) {
			$out[] = array(
				'slug'  => (string) $slug,
				'name'  => (string) $names[ $slug ],
				'count' => (int) $count,
			);
		}
		return $out;
	}

	/**
	 * @param int[] $ids
	 * @return array<int,array{slug:string,label:string,count:int}>
	 */
	private function work_mode_facet( array $ids ): array {
		$out = array();
		foreach ( self::WORK_MODES as $slug => $bucket ) {
			$count = 0;
			foreach ( $ids as $id ) {
				if ( false !== stripos( (string) get_post_meta( $id, 'work_mode_raw', true ), $bucket['needle'] ) ) {
					++$count;
				}
			}
			if ( $count > 0 ) {
				$out[] = array(
					'slug'  => $slug,
					'label' => $bucket['label'],
					'count' => $count,
				);
			}
		}
		return $out;
	}

	/**
	 * @param int[] $ids
	 * @return array<int,array{slug:string,label:string,count:int}>
	 */
	private function type_facet( array $ids ): array {
		$counts = array();
		foreach ( $ids as $id ) {
			$type = trim( (string) get_post_meta( $id, 'employment_type', true ) );
			if ( '' === $type ) {
				continue;
			}
			$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
		}
		ksort( $counts );
		$out = array();
		foreach ( $counts as $label => $count ) {
			$out[] = array(
				'slug'  => sanitize_title( (string) $label ),
				'label' => (string) $label,
				'count' => (int) $count,
			);
		}
		return $out;
	}

	/**
	 * @param int[] $ids
	 */
	private function salary_bound( array $ids, string $which ): int {
		$values = array();
		foreach ( $ids as $id ) {
			$raw = get_post_meta( $id, 'salary_min', true );
			// Annual salaries only; ignore hourly/low values that would skew the floor.
			if ( is_numeric( $raw ) && (int) $raw >= 1000 ) {
				$values[] = (int) $raw;
			}
		}
		if ( array() === $values ) {
			return 0;
		}
		return 'min' === $which ? min( $values ) : max( $values );
	}

	/** The working-pattern match needle for a bucket slug, or '' if unknown. */
	public static function work_mode_needle( string $slug ): string {
		return self::WORK_MODES[ $slug ]['needle'] ?? '';
	}

	/** The display label for a working-pattern bucket slug, or '' if unknown. */
	public static function work_mode_label( string $slug ): string {
		return self::WORK_MODES[ $slug ]['label'] ?? '';
	}
}
