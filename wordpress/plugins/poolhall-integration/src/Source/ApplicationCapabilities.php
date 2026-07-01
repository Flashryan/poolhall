<?php
/**
 * Application capabilities value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * What the configured ATS has *proven* it can do (Phase 1 gate). The
 * application mode decision tree in the build brief keys off these flags:
 * Mode A requires `supports_cv_upload === true` with written/test proof.
 */
final class ApplicationCapabilities {
	public function __construct(
		public readonly bool $supports_candidate_create,
		public readonly bool $supports_application_submit,
		public readonly bool $supports_cv_upload,
		public readonly string $evidence_note = '',
	) {}

	public function allows_first_party_apply(): bool {
		return $this->supports_candidate_create
			&& $this->supports_application_submit
			&& $this->supports_cv_upload;
	}
}
