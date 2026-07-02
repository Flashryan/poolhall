<?php
/**
 * Application payload value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * One candidate application bound for the ATS. CV is referenced by an
 * encrypted private path, never by raw contents, so the payload can be
 * queued safely (architecture doc §7).
 */
final class ApplicationPayload {
	public function __construct(
		public readonly string $source_job_id,
		public readonly string $first_name,
		public readonly string $last_name,
		public readonly string $email,
		public readonly string $phone,
		public readonly ?string $message,
		public readonly ?string $cv_private_path,
		public readonly ?string $cv_original_filename,
		public readonly ?string $cv_mime_type,
		public readonly string $idempotency_key,
		public readonly ?string $source_company_id = null,
		public readonly ?string $role_title = null,
		public readonly ?float $salary_from = null,
	) {}
}
