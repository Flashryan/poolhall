<?php
/**
 * JobPosting JSON-LD generator.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Schema;

use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\WorkMode;

/**
 * Builds schema.org JobPosting data for a single job page, or refuses.
 *
 * Eligibility gate (architecture doc §9): if the hiring organization or any
 * required field cannot be represented accurately, return null — the page
 * then carries no JobPosting markup and the admin health screen shows a
 * warning. Never publish misleading data just to pass validation.
 */
final class JobPostingSchema {

	private const EMPLOYMENT_TYPE_MAP = array(
		'full'       => 'FULL_TIME',
		'permanent'  => 'FULL_TIME',
		'part'       => 'PART_TIME',
		'contract'   => 'CONTRACTOR',
		'temp'       => 'TEMPORARY',
		'intern'     => 'INTERN',
		'apprentice' => 'INTERN',
	);

	/**
	 * @param string  $hiring_org_name Validated hiring organization name.
	 * @param string  $hiring_org_url  Hiring organization URL.
	 * @param ?string $hiring_org_logo Logo URL, optional.
	 * @param string  $default_country ISO 3166-1 alpha-2, applicant country for remote roles.
	 */
	public function __construct(
		private readonly string $hiring_org_name,
		private readonly string $hiring_org_url,
		private readonly ?string $hiring_org_logo = null,
		private readonly string $default_country = 'GB',
	) {}

	/**
	 * Build the JobPosting array, or null when the job is not eligible.
	 *
	 * @param SourceJob          $job          The job.
	 * @param string             $canonical_url First-party URL of the job page.
	 * @param \DateTimeImmutable $valid_through Local expiry (drives validThrough).
	 * @param bool               $direct_apply  True only in proven application Mode A.
	 * @return array<string,mixed>|null
	 */
	public function build(
		SourceJob $job,
		string $canonical_url,
		\DateTimeImmutable $valid_through,
		bool $direct_apply,
	): ?array {
		// Gate: required facts must exist and be accurate.
		if ( array() !== $this->problems( $job ) ) {
			return null;
		}

		$organization = array(
			'@type'  => 'Organization',
			'name'   => $this->hiring_org_name,
			'sameAs' => $this->hiring_org_url,
		);
		if ( null !== $this->hiring_org_logo ) {
			$organization['logo'] = $this->hiring_org_logo;
		}

		$schema = array(
			'@context'           => 'https://schema.org/',
			'@type'              => 'JobPosting',
			'title'              => $job->title,
			'description'        => $job->description_html,
			'identifier'         => array(
				'@type' => 'PropertyValue',
				'name'  => $job->source,
				'value' => $job->job_reference ?? $job->source_job_id,
			),
			'datePosted'         => $job->date_posted->format( 'Y-m-d' ),
			'validThrough'       => $valid_through->format( \DateTimeInterface::ATOM ),
			'hiringOrganization' => $organization,
			'directApply'        => $direct_apply,
			'url'                => $canonical_url,
		);

		$employment_type = $this->employment_type( $job->job_type );
		if ( null !== $employment_type ) {
			$schema['employmentType'] = $employment_type;
		}

		$schema = $this->apply_location( $schema, $job );

		if ( $job->salary->is_reliable() ) {
			$value = array(
				'@type'    => 'QuantitativeValue',
				'unitText' => $job->salary->period,
			);
			if ( null !== $job->salary->max && $job->salary->max !== $job->salary->min ) {
				$value['minValue'] = $job->salary->min;
				$value['maxValue'] = $job->salary->max;
			} else {
				$value['value'] = $job->salary->min;
			}
			$schema['baseSalary'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => $job->salary->currency,
				'value'    => $value,
			);
		}

		if ( null !== $job->experience_requirement ) {
			$schema['experienceRequirements'] = $job->experience_requirement;
		}
		if ( null !== $job->education_requirement ) {
			$schema['educationRequirements'] = $job->education_requirement;
		}

		return $schema;
	}

	/**
	 * Why this job would carry no JobPosting markup — empty means eligible.
	 *
	 * Same gate `build()` applies, exposed so the Google Jobs admin screen
	 * can tell staff exactly what to fix instead of silently omitting the
	 * markup. Messages are written for a non-technical reader.
	 *
	 * @return string[]
	 */
	public function problems( SourceJob $job ): array {
		$problems = array();

		if ( '' === trim( $this->hiring_org_name ) ) {
			$problems[] = __( 'The hiring organisation name is not set (Google Jobs settings).', 'poolhall-integration' );
		}
		if ( '' === trim( $this->hiring_org_url ) ) {
			$problems[] = __( 'The hiring organisation website is not set (Google Jobs settings).', 'poolhall-integration' );
		}
		if ( '' === trim( $job->title ) ) {
			$problems[] = __( 'The job has no title.', 'poolhall-integration' );
		}
		if ( '' === trim( $job->description_html ) ) {
			$problems[] = __( 'The job has no description. Add the full advert text in Giig.', 'poolhall-integration' );
		}
		if ( null === $job->date_posted ) {
			$problems[] = __( 'The job has no posting date from Giig.', 'poolhall-integration' );
		}

		// A job with no resolvable location cannot be represented accurately
		// unless it is fully remote.
		$has_location = null !== $job->address_locality || null !== $job->address_region || null !== $job->location_display;
		if ( WorkMode::Remote !== $job->work_mode && ! $has_location ) {
			$problems[] = __( 'The job has no location, and is not marked fully remote. Add a location in Giig.', 'poolhall-integration' );
		}

		return $problems;
	}

	/**
	 * Non-blocking gaps: the job still qualifies, but filling these makes the
	 * Google listing richer and lets candidates filter to it (salary and
	 * employment-type filters are the two candidates use most).
	 *
	 * @return string[]
	 */
	public function recommendations( SourceJob $job ): array {
		$notes = array();

		if ( ! $job->salary->is_reliable() ) {
			$notes[] = __( 'No structured salary — the listing cannot appear in salary-filtered searches. Add a salary range in Giig.', 'poolhall-integration' );
		}
		if ( null === $this->employment_type( $job->job_type ) ) {
			$notes[] = __( 'No employment type (full-time, contract…) — set the job type in Giig.', 'poolhall-integration' );
		}
		if ( null === $this->hiring_org_logo ) {
			$notes[] = __( 'No organisation logo set — Google may show the listing without your logo.', 'poolhall-integration' );
		}

		return $notes;
	}

	/**
	 * Work-mode mapping (architecture doc §9):
	 *  - Onsite/hybrid: physical jobLocation. Hybrid adds remote fields only
	 *    when applicant geography is known — not in v1, so hybrid stays
	 *    location-only.
	 *  - Fully remote: jobLocationType TELECOMMUTE + applicantLocationRequirements.
	 *
	 * @param array<string,mixed> $schema Schema being built.
	 * @return array<string,mixed>
	 */
	private function apply_location( array $schema, SourceJob $job ): array {
		if ( WorkMode::Remote === $job->work_mode ) {
			$schema['jobLocationType']               = 'TELECOMMUTE';
			$schema['applicantLocationRequirements'] = array(
				'@type' => 'Country',
				'name'  => $this->default_country,
			);
			return $schema;
		}

		$address = array( '@type' => 'PostalAddress' );
		if ( null !== $job->address_locality ) {
			$address['addressLocality'] = $job->address_locality;
		}
		if ( null !== $job->address_region ) {
			$address['addressRegion'] = $job->address_region;
		}
		$address['addressCountry'] = $job->address_country ?? $this->default_country;

		// Fall back to the display string when structured parts are missing.
		if ( ! isset( $address['addressLocality'] ) && ! isset( $address['addressRegion'] ) && null !== $job->location_display ) {
			$address['addressLocality'] = $job->location_display;
		}

		$schema['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => $address,
		);

		return $schema;
	}

	private function employment_type( ?string $job_type ): ?string {
		if ( null === $job_type ) {
			return null;
		}
		$haystack = strtolower( $job_type );
		foreach ( self::EMPLOYMENT_TYPE_MAP as $needle => $schema_type ) {
			if ( str_contains( $haystack, $needle ) ) {
				return $schema_type;
			}
		}
		return null;
	}
}
