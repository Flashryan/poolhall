<?php
/**
 * Application result value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Outcome of a successful ATS submission. Application history may only show
 * states proven by this result (hard rule 15) — a redirect is never one.
 */
final class ApplicationResult {
	public function __construct(
		public readonly string $source_candidate_id,
		public readonly ?string $source_application_id,
		public readonly bool $cv_attached,
	) {}
}
