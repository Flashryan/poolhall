<?php
/**
 * Normalized source job value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

use Poolhall\Integration\Support\Salary;
use Poolhall\Integration\Support\WorkMode;

/**
 * A job as normalized from any ATS. Nothing outside the Source namespace may
 * see raw Giig payloads; this object is the contract.
 */
final class SourceJob {
	public function __construct(
		public readonly string $source,
		public readonly string $source_job_id,
		public readonly string $title,
		public readonly string $description_html,
		public readonly Salary $salary,
		public readonly WorkMode $work_mode,
		public readonly ?string $work_mode_raw,
		public readonly ?string $location_display,
		public readonly ?string $address_locality,
		public readonly ?string $address_region,
		public readonly ?string $address_country,
		public readonly ?string $sector,
		public readonly ?string $job_type,
		public readonly ?string $experience_requirement,
		public readonly ?string $education_requirement,
		public readonly ?\DateTimeImmutable $date_posted,
		public readonly ?string $source_company_id = null,
		public readonly ?string $source_url = null,
		public readonly ?string $job_reference = null,
	) {}

	/**
	 * Stable content hash for change detection (sync step 5: update only when
	 * the normalized payload hash changes).
	 */
	public function content_hash(): string {
		// Plain json_encode: deterministic encoding of scalars is all that is
		// needed for change detection, and it keeps this object WP-free.
		return hash(
			'sha256',
			(string) json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				array(
					$this->title,
					$this->description_html,
					$this->salary->display,
					$this->work_mode->value,
					$this->location_display,
					$this->address_locality,
					$this->address_region,
					$this->address_country,
					$this->sector,
					$this->job_type,
					$this->experience_requirement,
					$this->education_requirement,
					$this->date_posted?->format( 'Y-m-d' ),
					$this->source_url,
					$this->job_reference,
				)
			)
		);
	}
}
