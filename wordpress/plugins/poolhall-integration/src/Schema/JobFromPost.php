<?php
/**
 * Rebuild a normalized job from stored post meta.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Schema;

use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\Salary;
use Poolhall\Integration\Support\WorkMode;

/**
 * Reconstructs the SourceJob a job page was built from, so structured data
 * and the admin readiness screen both describe exactly what the page shows
 * (Google requires visible content and markup to agree). Shared by
 * SchemaOutput and the Google Jobs admin screen — one source of truth.
 */
final class JobFromPost {

	public static function build( int $post_id ): ?SourceJob {
		$meta = static fn( string $key ): string => (string) get_post_meta( $post_id, $key, true );
		$nul  = static function ( string $key ) use ( $post_id ): ?string {
			$value = (string) get_post_meta( $post_id, $key, true );
			return '' === $value ? null : $value;
		};

		$source_job_id = $meta( 'source_job_id' );
		if ( '' === $source_job_id ) {
			return null;
		}

		$date_posted = null;
		if ( null !== $nul( 'date_posted' ) ) {
			try {
				$date_posted = new \DateTimeImmutable( $meta( 'date_posted' ), new \DateTimeZone( 'UTC' ) );
			} catch ( \Exception ) {
				$date_posted = null;
			}
		}

		$min = $nul( 'salary_min' );
		$max = $nul( 'salary_max' );

		return new SourceJob(
			source: '' !== $meta( 'source' ) ? $meta( 'source' ) : 'giig',
			source_job_id: $source_job_id,
			title: get_the_title( $post_id ),
			description_html: (string) get_post_field( 'post_content', $post_id ),
			salary: new Salary(
				display: $meta( 'salary_display' ),
				currency: $nul( 'salary_currency' ),
				min: null === $min ? null : (float) $min,
				max: null === $max ? null : (float) $max,
				period: $nul( 'salary_period' ),
			),
			work_mode: WorkMode::from_source( $nul( 'work_mode_raw' ) ),
			work_mode_raw: $nul( 'work_mode_raw' ),
			location_display: $nul( 'location_display' ),
			address_locality: $nul( 'address_locality' ),
			address_region: $nul( 'address_region' ),
			address_country: $nul( 'address_country' ),
			sector: null,
			job_type: self::first_term( $post_id, JobPostType::TAX_JOB_TYPE ),
			experience_requirement: $nul( 'experience_requirement' ),
			education_requirement: $nul( 'education_requirement' ),
			date_posted: $date_posted,
			source_company_id: $nul( 'source_company_id' ),
			source_url: $nul( 'source_url' ),
			job_reference: $nul( 'job_reference' ),
		);
	}

	/** Expiry stored on the post, or null when absent/unparseable. */
	public static function expires_at( int $post_id ): ?\DateTimeImmutable {
		$raw = (string) get_post_meta( $post_id, 'expires_at', true );
		if ( '' === $raw ) {
			return null;
		}
		try {
			return new \DateTimeImmutable( $raw );
		} catch ( \Exception ) {
			return null;
		}
	}

	public static function is_expired( int $post_id, ?\DateTimeImmutable $now = null ): bool {
		$expires = self::expires_at( $post_id );
		if ( null === $expires ) {
			return false;
		}
		return ( $now ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) ) >= $expires;
	}

	private static function first_term( int $post_id, string $taxonomy ): ?string {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}
		return $terms[0]->name;
	}
}
