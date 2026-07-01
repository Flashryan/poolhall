<?php
/**
 * Sync planner.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

use Poolhall\Integration\Source\SourceJob;

/**
 * Pure decision logic for the sync algorithm (architecture doc §5):
 * upsert by source_job_id, update only on content-hash change, unpublish
 * jobs missing from a complete successful response — and never mass-expire
 * on anything that looks wrong (hard rule 2).
 */
final class SyncPlanner {

	/**
	 * Guard: a "successful" response that would unpublish every local job
	 * while reporting zero (or implausibly few) jobs is treated as suspect.
	 * Poolhall normally lists ~20 roles; the agency dropping to zero
	 * overnight is far more likely an API fault than reality.
	 */
	public const MASS_UNPUBLISH_RATIO     = 0.8;
	public const MASS_UNPUBLISH_MIN_LOCAL = 5;

	/**
	 * @param array<string,string> $local_hashes source_job_id => content hash of currently published local jobs.
	 * @param SourceJob[]          $source_jobs  Jobs from a successful, complete source fetch.
	 */
	public function plan( array $local_hashes, array $source_jobs ): SyncPlan {
		$create    = array();
		$update    = array();
		$unchanged = array();
		$seen      = array();

		foreach ( $source_jobs as $job ) {
			// First occurrence wins; duplicate IDs in one response are skipped.
			if ( isset( $seen[ $job->source_job_id ] ) ) {
				continue;
			}
			$seen[ $job->source_job_id ] = true;

			if ( ! array_key_exists( $job->source_job_id, $local_hashes ) ) {
				$create[] = $job;
			} elseif ( $local_hashes[ $job->source_job_id ] !== $job->content_hash() ) {
				$update[ $job->source_job_id ] = $job;
			} else {
				$unchanged[] = $job->source_job_id;
			}
		}

		// PHP silently casts numeric-string array keys to int; normalize back.
		$missing = array_map( 'strval', array_values( array_diff( array_keys( $local_hashes ), array_keys( $seen ) ) ) );

		// Mass-unpublish guard.
		$local_count = count( $local_hashes );
		if (
			$local_count >= self::MASS_UNPUBLISH_MIN_LOCAL
			&& count( $missing ) >= (int) ceil( $local_count * self::MASS_UNPUBLISH_RATIO )
		) {
			return new SyncPlan(
				create: $create,
				update: $update,
				unchanged: $unchanged,
				unpublish: array(),
				unpublish_suppressed: true,
				guard_reason: sprintf(
					'Source response would unpublish %d of %d local jobs; suppressed pending staff review.',
					count( $missing ),
					$local_count
				),
			);
		}

		return new SyncPlan( $create, $update, $unchanged, $missing );
	}
}
