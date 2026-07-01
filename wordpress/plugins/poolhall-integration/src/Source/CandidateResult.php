<?php
/**
 * Candidate registration result.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Outcome of a successful candidate creation in the ATS: the upstream
 * candidate id we store so the record can be matched later (e.g. when an
 * application is submitted for the same candidate).
 */
final class CandidateResult {
	public function __construct(
		public readonly string $source_candidate_id,
	) {}
}
