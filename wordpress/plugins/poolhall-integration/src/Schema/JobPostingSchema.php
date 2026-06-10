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
		if ( '' === trim( $this->hiring_org_name ) || '' === trim( $this->hiring_org_url ) ) {
			return null;
		}
		if ( '' === trim( $job->title ) || '' === trim( $job->description_html ) || null === $job->date_posted ) {
			return null;
		}
		// A job with no resolvable location cannot be represented accurately
		// unless it is fully remote.
		$has_location = null !== $job->address_locality || null !== $job->address_region || null !== $job->location_display;
		if ( WorkMode::Remote !== $job->work_mode && ! $has_location ) {
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
