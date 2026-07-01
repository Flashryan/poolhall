<?php
/**
 * Job repository.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\WorkMode;

/**
 * All reads/writes of `poolhall_job` posts. Guarded lookup on
 * source + source_job_id is the dedupe contract (architecture doc §3).
 */
final class JobRepository {

	/**
	 * Published local jobs for one source as source_job_id => content hash.
	 *
	 * @return array<string,string>
	 */
	public function published_hashes( string $source ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
					array(
						'key'   => 'source',
						'value' => $source,
					),
				),
			)
		);

		$hashes = array();
		foreach ( $query->posts as $post_id ) {
			$source_job_id = (string) get_post_meta( (int) $post_id, 'source_job_id', true );
			if ( '' !== $source_job_id ) {
				$hashes[ $source_job_id ] = (string) get_post_meta( (int) $post_id, 'source_payload_hash', true );
			}
		}
		return $hashes;
	}

	/** Find a job post (any status) by source identity. Guarded dedupe lookup. */
	public function find_post_id( string $source, string $source_job_id ): ?int {
		$query = new \WP_Query(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'source',
						'value' => $source,
					),
					array(
						'key'   => 'source_job_id',
						'value' => $source_job_id,
					),
				),
			)
		);

		return array() === $query->posts ? null : (int) $query->posts[0];
	}

	/**
	 * Create or update the local post for a source job. Returns the post ID.
	 *
	 * @throws \RuntimeException When WordPress refuses the insert/update.
	 */
	public function upsert( SourceJob $job, ExpiryPolicy $expiry, \DateTimeImmutable $now ): int {
		$existing_id = $this->find_post_id( $job->source, $job->source_job_id );

		$postarr = array(
			'post_type'    => JobPostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $job->title,
			'post_content' => wp_kses_post( $job->description_html ),
		);
		if ( null !== $existing_id ) {
			$postarr['ID'] = $existing_id;
			$result        = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'Failed to save job post: ' . $result->get_error_message() );
		}
		$post_id = (int) $result;

		// Respect an explicit admin expiry override (never silently overwrite).
		$override_raw = (string) get_post_meta( $post_id, 'expiry_override_at', true );
		$override     = '' !== $override_raw ? new \DateTimeImmutable( $override_raw ) : null;
		$expires_at   = $expiry->expires_at( $job->date_posted, $override, $now );

		$meta = array(
			'source'                 => $job->source,
			'source_job_id'          => $job->source_job_id,
			'source_company_id'      => (string) $job->source_company_id,
			'source_url'             => (string) $job->source_url,
			'source_payload_hash'    => $job->content_hash(),
			'source_modified_at'     => $now->format( \DateTimeInterface::ATOM ),
			'source_status'          => 'open',
			'job_reference'          => (string) $job->job_reference,
			'salary_display'         => $job->salary->display,
			'salary_currency'        => (string) $job->salary->currency,
			'salary_min'             => null === $job->salary->min ? '' : (string) $job->salary->min,
			'salary_max'             => null === $job->salary->max ? '' : (string) $job->salary->max,
			'salary_period'          => (string) $job->salary->period,
			'location_display'       => (string) $job->location_display,
			'address_locality'       => (string) $job->address_locality,
			'address_region'         => (string) $job->address_region,
			'address_country'        => (string) $job->address_country,
			'work_mode_raw'          => (string) $job->work_mode_raw,
			'experience_requirement' => (string) $job->experience_requirement,
			'education_requirement'  => (string) $job->education_requirement,
			'date_posted'            => $job->date_posted?->format( 'Y-m-d' ) ?? '',
			'expires_at'             => $expires_at->format( \DateTimeInterface::ATOM ),
			'last_synced_at'         => $now->format( \DateTimeInterface::ATOM ),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// Taxonomies.
		if ( null !== $job->sector ) {
			wp_set_object_terms( $post_id, $job->sector, JobPostType::TAX_SECTOR );
		}
		if ( null !== $job->job_type ) {
			wp_set_object_terms( $post_id, $job->job_type, JobPostType::TAX_JOB_TYPE );
		}
		if ( WorkMode::Unknown !== $job->work_mode ) {
			wp_set_object_terms( $post_id, $job->work_mode->label(), JobPostType::TAX_WORK_MODE );
		}
		if ( null !== $job->location_display ) {
			wp_set_object_terms( $post_id, $job->location_display, JobPostType::TAX_LOCATION );
		}

		return $post_id;
	}

	/** Unpublish a job that left the source feed or expired. */
	public function unpublish( string $source, string $source_job_id, string $reason ): ?int {
		$post_id = $this->find_post_id( $source, $source_job_id );
		if ( null === $post_id ) {
			return null;
		}
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		update_post_meta( $post_id, 'source_status', $reason );
		return $post_id;
	}

	/**
	 * Published jobs whose local expiry has passed.
	 *
	 * @return int[] Post IDs.
	 */
	public function expired_post_ids( \DateTimeImmutable $now ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'expires_at',
						'value'   => $now->format( \DateTimeInterface::ATOM ),
						'compare' => '<=',
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}
}
