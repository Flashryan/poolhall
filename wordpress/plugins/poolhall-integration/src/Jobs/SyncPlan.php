<?php
/**
 * Sync plan value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

use Poolhall\Integration\Source\SourceJob;

/**
 * The decided outcome of comparing local jobs with a source response.
 * Produced by SyncPlanner (pure, unit-tested); executed by SyncService.
 */
final class SyncPlan {
	/**
	 * @param SourceJob[]             $create         Jobs to create.
	 * @param array<string,SourceJob> $update       post_id-keyed... no: source_job_id => SourceJob to update.
	 * @param string[]                $unchanged      Source job IDs with no content change.
	 * @param string[]                $unpublish      Source job IDs to unpublish (gone from a complete response).
	 * @param bool                    $unpublish_suppressed True when unpublishing was skipped by the safety guard.
	 * @param string|null             $guard_reason   Why the guard fired, for the sync log.
	 */
	public function __construct(
		public readonly array $create,
		public readonly array $update,
		public readonly array $unchanged,
		public readonly array $unpublish,
		public readonly bool $unpublish_suppressed = false,
		public readonly ?string $guard_reason = null,
	) {}

	public function is_noop(): bool {
		return array() === $this->create && array() === $this->update && array() === $this->unpublish;
	}
}
