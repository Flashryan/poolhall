<?php
/**
 * Candidate registration payload.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * A candidate captured by the website registration form, ready to be pushed
 * to the ATS. Source-neutral: the field names here are ours, and the sink
 * maps them onto the upstream API (the Giig "Create candidate" fields live
 * in GiigCandidateSink, not here). FirstName + LastName are the only fields
 * Giig requires; everything else is optional and omitted when empty.
 */
final class CandidatePayload {

	public function __construct(
		public readonly string $first_name,
		public readonly string $last_name,
		public readonly string $email = '',
		public readonly string $phone = '',
		public readonly string $role_title = '',
		public readonly string $location = '',
		public readonly string $salary_expectations = '',
		public readonly string $linkedin = '',
		public readonly string $notes = '',
		public readonly string $source = 'Website',
		public readonly string $tags = '',
		public readonly string $skills = '',
	) {}

	/** Giig requires a first and last name; without them there is nothing to create. */
	public function is_valid(): bool {
		return '' !== trim( $this->first_name ) && '' !== trim( $this->last_name );
	}
}
