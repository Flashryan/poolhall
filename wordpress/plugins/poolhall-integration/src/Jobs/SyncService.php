<?php
/**
 * Job sync service.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

use Poolhall\Integration\Source\JobSource;
use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Support\Logger;

/**
 * Executes the sync algorithm (architecture doc §5). Decisions are made by
 * the pure SyncPlanner; this class owns the lock, WordPress writes, expiry
 * pass and the single summarized sync log entry.
 */
final class SyncService {

	private const LOCK_KEY    = 'poolhall_sync_lock';
	private const LOCK_TTL    = 10 * MINUTE_IN_SECONDS;
	public const STATE_OPTION = 'poolhall_sync_state';

	public function __construct(
		private readonly JobSource $source,
		private readonly JobRepository $repository,
		private readonly SyncPlanner $planner,
		private readonly ExpiryPolicy $expiry,
		private readonly Logger $logger,
	) {}

	/**
	 * Run one sync. Returns a summary array (also persisted for the admin
	 * health screen). Never throws — failures are logged and counted.
	 *
	 * @return array<string,mixed>
	 */
	public function run( string $trigger = 'cron' ): array {
		$correlation = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'sync_', true );
		$now         = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		// Step 1: short-lived lock; never overlap.
		if ( false === add_option( self::LOCK_KEY, $now->getTimestamp(), '', false ) ) {
			$held_since = (int) get_option( self::LOCK_KEY );
			if ( $now->getTimestamp() - $held_since < self::LOCK_TTL ) {
				return array( 'status' => 'skipped_locked' );
			}
			// Stale lock: take it over.
			update_option( self::LOCK_KEY, $now->getTimestamp(), false );
		}

		$this->logger->log( 'job_sync_started', array( 'trigger' => $trigger ), $correlation );

		try {
			// Steps 2–3: fetch + normalize (the adapter throws on incomplete).
			$source_jobs = $this->source->fetch_open_jobs();

			// Steps 4–7: plan against current local state, then execute.
			$local = $this->repository->published_hashes( $this->source->source_key() );
			$plan  = $this->planner->plan( $local, $source_jobs );

			foreach ( $plan->create as $job ) {
				$this->repository->upsert( $job, $this->expiry, $now );
			}
			foreach ( $plan->update as $job ) {
				$this->repository->upsert( $job, $this->expiry, $now );
			}
			foreach ( $plan->unpublish as $source_job_id ) {
				$this->repository->unpublish( $this->source->source_key(), $source_job_id, 'removed_from_source' );
			}

			// Step 8: local expiry rules. Source presence governs visibility
			// (client decision, 2026-07-01): a job the fetch still lists is
			// open by definition, so instead of drafting it we roll its expiry
			// clock forward — the source has re-confirmed the role. Age-based
			// expiry now only hides jobs the source no longer returns (e.g.
			// kept published because the mass-unpublish guard suppressed
			// removals). An explicit staff override still wins (doc §5) and is
			// never rolled.
			$open_post_ids = array();
			foreach ( $source_jobs as $source_job ) {
				$open_id = $this->repository->find_post_id( $this->source->source_key(), $source_job->source_job_id );
				if ( null !== $open_id ) {
					$open_post_ids[ $open_id ] = true;
				}
			}

			$expired = 0;
			foreach ( $this->repository->expired_post_ids( $now ) as $post_id ) {
				$has_override = '' !== (string) get_post_meta( $post_id, 'expiry_override_at', true );
				if ( isset( $open_post_ids[ $post_id ] ) && ! $has_override ) {
					update_post_meta(
						$post_id,
						'expires_at',
						$now->add( new \DateInterval( 'P' . ExpiryPolicy::DEFAULT_LIFETIME_DAYS . 'D' ) )
							->format( \DateTimeInterface::ATOM )
					);
					continue;
				}
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
				update_post_meta( $post_id, 'source_status', 'expired' );
				$this->logger->log( 'job_expired', array( 'post_id' => $post_id ), $correlation );
				++$expired;
			}

			$summary = array(
				'status'               => 'success',
				'trigger'              => $trigger,
				'fetched'              => count( $source_jobs ),
				'created'              => count( $plan->create ),
				'updated'              => count( $plan->update ),
				'unchanged'            => count( $plan->unchanged ),
				'unpublished'          => count( $plan->unpublish ),
				'expired'              => $expired,
				'unpublish_suppressed' => $plan->unpublish_suppressed,
				'guard_reason'         => $plan->guard_reason,
				'completed_at'         => $now->format( \DateTimeInterface::ATOM ),
			);

			// Step 10: one summarized log entry; reset the failure streak.
			$this->logger->log( 'job_sync_completed', $summary, $correlation );
			$this->persist_state( $summary, 0 );

			if ( $plan->unpublish_suppressed ) {
				$this->logger->log( 'job_sync_guard_triggered', array( 'reason' => $plan->guard_reason ), $correlation );
			}

			return $summary;
		} catch ( SourceException $e ) {
			// Hard rule 2: failed/incomplete fetch expires nothing.
			$failures = $this->bump_failures();
			$summary  = array(
				'status'       => 'failed',
				'trigger'      => $trigger,
				'error'        => $e->getMessage(),
				'incomplete'   => $e->response_incomplete,
				'failures'     => $failures,
				'completed_at' => $now->format( \DateTimeInterface::ATOM ),
			);
			$this->logger->log( 'job_sync_failed', $summary, $correlation );
			$this->persist_state( $summary, $failures );

			if ( $failures >= 3 ) {
				$this->alert_staff( $failures, $e->getMessage() );
			}
			return $summary;
		} catch ( \Throwable $e ) {
			$failures = $this->bump_failures();
			$this->logger->log(
				'job_sync_failed',
				array(
					'status' => 'failed',
					'error'  => $e->getMessage(),
				),
				$correlation
			);
			$this->persist_state(
				array(
					'status' => 'failed',
					'error'  => $e->getMessage(),
				),
				$failures
			);
			return array(
				'status' => 'failed',
				'error'  => $e->getMessage(),
			);
		} finally {
			// Step 11: release the lock.
			delete_option( self::LOCK_KEY );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	public function state(): array {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * @param array<string,mixed> $summary Last run summary.
	 */
	private function persist_state( array $summary, int $consecutive_failures ): void {
		$state                         = $this->state();
		$state['last_run']             = $summary;
		$state['consecutive_failures'] = $consecutive_failures;
		if ( 'success' === ( $summary['status'] ?? '' ) ) {
			$state['last_success'] = $summary;
		}
		update_option( self::STATE_OPTION, $state, false );
	}

	private function bump_failures(): int {
		$state = $this->state();
		return (int) ( $state['consecutive_failures'] ?? 0 ) + 1;
	}

	private function alert_staff( int $failures, string $error ): void {
		$recipient = (string) get_option( 'admin_email' );
		wp_mail(
			$recipient,
			sprintf( '[Poolhall] Job sync has failed %d times in a row', $failures ),
			"The Giig job sync is failing.\n\nMost recent error: {$error}\n\n"
			. 'Existing jobs have NOT been expired or removed. Check Poolhall Jobs → Health in WordPress admin.'
		);
	}
}
