<?php
/**
 * Schema output on single job pages.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Schema;

use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\Options;
use Poolhall\Integration\Support\Salary;
use Poolhall\Integration\Support\WorkMode;

/**
 * Prints JobPosting JSON-LD in <head> on published, unexpired single job
 * pages only. Reconstructs the normalized job from stored meta so visible
 * content and structured data always agree (Google requirement).
 */
final class SchemaOutput {

	public function __construct( private readonly Options $options ) {}

	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_schema' ) );
	}

	public function print_schema(): void {
		if ( ! is_singular( JobPostType::POST_TYPE ) ) {
			return;
		}
		$post = get_post();
		if ( null === $post || 'publish' !== $post->post_status ) {
			return;
		}

		$expires_raw = (string) get_post_meta( $post->ID, 'expires_at', true );
		if ( '' === $expires_raw ) {
			return;
		}
		$expires_at = new \DateTimeImmutable( $expires_raw );
		if ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) >= $expires_at ) {
			return; // Expired jobs must not carry JobPosting markup.
		}

		$job = $this->job_from_post( $post->ID );
		if ( null === $job ) {
			return;
		}

		$generator = new JobPostingSchema(
			$this->options->hiring_org_name(),
			$this->options->hiring_org_url(),
			$this->options->hiring_org_logo()
		);

		$schema = $generator->build(
			$job,
			(string) get_permalink( $post ),
			$expires_at,
			$this->options->direct_apply()
		);

		if ( null === $schema ) {
			return; // Eligibility gate refused; no markup is correct behavior.
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD from wp_json_encode.
	}

	/** Rebuild a SourceJob from stored post meta. */
	private function job_from_post( int $post_id ): ?SourceJob {
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
			job_type: $this->first_term( $post_id, JobPostType::TAX_JOB_TYPE ),
			experience_requirement: $nul( 'experience_requirement' ),
			education_requirement: $nul( 'education_requirement' ),
			date_posted: $date_posted,
			source_company_id: $nul( 'source_company_id' ),
			source_url: $nul( 'source_url' ),
			job_reference: $nul( 'job_reference' ),
		);
	}

	private function first_term( int $post_id, string $taxonomy ): ?string {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}
		return $terms[0]->name;
	}
}
