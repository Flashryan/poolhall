<?php
/**
 * Giig payload normalizer.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\SalaryParser;
use Poolhall\Integration\Support\WorkMode;

/**
 * Turns one raw Giig job payload into a SourceJob.
 *
 * IMPORTANT — Phase 1 contract: the public Giig docs (api-doc.giighire.com,
 * reviewed 2026-06-10) do not publish a full response example, so the key
 * map below tolerates the plausible naming variants. Phase 1 runs against
 * real credentials, records the exact response in
 * tests/fixtures/giig-getjobs.live.json and this KEYS map is then locked.
 * The exit gate forbids advancing while a required key is unmapped.
 */
final class GiigNormalizer {

	private const KEYS = array(
		'id'          => array( 'jobId', 'JobId', 'id', 'Id' ),
		'company_id'  => array( 'companyId', 'CompanyId' ),
		'title'       => array( 'jobTitle', 'title', 'JobTitle', 'Title', 'name' ),
		'description' => array( 'jobDescription', 'description', 'JobDescription', 'Description' ),
		'salary'      => array( 'salary', 'Salary', 'salaryRange', 'SalaryRange' ),
		'work_mode'   => array( 'remoteOnsite', 'RemoteOnsite', 'workMode', 'remote', 'Remote', 'workplaceType' ),
		'locality'    => array( 'city', 'City', 'town' ),
		'region'      => array( 'state', 'State', 'region', 'county' ),
		'country'     => array( 'country', 'Country' ),
		'location'    => array( 'location', 'Location', 'jobLocation' ),
		'sector'      => array( 'industry', 'Industry', 'sector' ),
		'job_type'    => array( 'jobType', 'JobType', 'employmentType' ),
		'experience'  => array( 'experienceRequired', 'ExperienceRequired', 'experience' ),
		'education'   => array( 'educationRequired', 'EducationRequired', 'education' ),
		'date_posted' => array( 'datePosted', 'DatePosted', 'createdAt', 'postedDate', 'created' ),
		'reference'   => array( 'jobReference', 'reference', 'JobReference' ),
		'url'         => array( 'jobUrl', 'url', 'JobUrl', 'applyUrl' ),
	);

	public function __construct(
		private readonly SalaryParser $salary_parser = new SalaryParser(),
	) {}

	/**
	 * @param array<string,mixed> $raw One job object from the Giig API.
	 * @throws SourceException When required fields are missing.
	 */
	public function normalize( array $raw ): SourceJob {
		$id    = $this->pick( $raw, 'id' );
		$title = $this->pick( $raw, 'title' );

		if ( null === $id || '' === trim( (string) $id ) ) {
			throw new SourceException( 'Giig job payload has no recognizable job ID.', true );
		}
		if ( null === $title || '' === trim( (string) $title ) ) {
			throw new SourceException( sprintf( 'Giig job %s has no recognizable title.', (string) $id ), true );
		}

		$work_mode_raw = $this->str( $raw, 'work_mode' );
		$date_posted   = $this->date( $this->str( $raw, 'date_posted' ) );

		return new SourceJob(
			source: 'giig',
			source_job_id: (string) $id,
			title: trim( (string) $title ),
			description_html: (string) ( $this->pick( $raw, 'description' ) ?? '' ),
			salary: $this->salary_parser->parse( $this->str( $raw, 'salary' ) ),
			work_mode: WorkMode::from_source( $work_mode_raw ),
			work_mode_raw: $work_mode_raw,
			location_display: $this->str( $raw, 'location' ) ?? $this->compose_location( $raw ),
			address_locality: $this->str( $raw, 'locality' ),
			address_region: $this->str( $raw, 'region' ),
			address_country: $this->str( $raw, 'country' ),
			sector: $this->str( $raw, 'sector' ),
			job_type: $this->str( $raw, 'job_type' ),
			experience_requirement: $this->str( $raw, 'experience' ),
			education_requirement: $this->str( $raw, 'education' ),
			date_posted: $date_posted,
			source_company_id: $this->str( $raw, 'company_id' ),
			source_url: $this->str( $raw, 'url' ),
			job_reference: $this->str( $raw, 'reference' ),
		);
	}

	/**
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function pick( array $raw, string $field ): mixed {
		foreach ( self::KEYS[ $field ] as $key ) {
			if ( array_key_exists( $key, $raw ) && null !== $raw[ $key ] && '' !== $raw[ $key ] ) {
				return $raw[ $key ];
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function str( array $raw, string $field ): ?string {
		$value = $this->pick( $raw, $field );
		if ( null === $value || is_array( $value ) || is_object( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return '' === $value ? null : $value;
	}

	/**
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function compose_location( array $raw ): ?string {
		$parts = array_filter(
			array( $this->str( $raw, 'locality' ), $this->str( $raw, 'region' ), $this->str( $raw, 'country' ) )
		);
		return array() === $parts ? null : implode( ', ', $parts );
	}

	private function date( ?string $value ): ?\DateTimeImmutable {
		if ( null === $value ) {
			return null;
		}
		try {
			return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception ) {
			return null;
		}
	}
}
