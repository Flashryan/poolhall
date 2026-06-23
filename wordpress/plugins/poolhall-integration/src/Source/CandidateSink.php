<?php
/**
 * Candidate registration abstraction (ATS write side).
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Pushes a registered candidate into the ATS. Separate from ApplicationSink:
 * registering for the talent pool creates a candidate record, while applying
 * additionally submits an applicant against a job. Implementations own all
 * endpoint, auth and payload detail; nothing else may know it is Giig.
 */
interface CandidateSink {

	public function is_configured(): bool;

	/**
	 * Create the candidate in the ATS and return its new id.
	 *
	 * @throws SourceException On any failure; callers must keep the record
	 *                         and retry/notify, never silently lose it.
	 */
	public function register_candidate( CandidatePayload $payload ): CandidateResult;
}
