<?php
/**
 * Conservative candidate-task reminder scheduling.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Applications\CandidateIdentity;

/** Sends at most two automatic reminders for an unfinished workflow stage. */
final class ReminderService {

	public const HOOK = 'poolhall_candidate_reminders';

	private const REMINDER_DAYS = array( 2, 7 );
	private const ACTIVE        = array( 'awaiting', 'in_progress', 'needs_correction' );
	private const BATCH_SIZE    = 100;
	private const INTERVAL      = 'twicedaily';
	private const OFFSET_OPTION = 'poolhall_candidate_reminder_offset';

	public function __construct(
		private readonly InviteMailer $mailer,
		private readonly WorkflowState $state,
	) {
	}

	/**
	 * Attach the service to its own lightweight cron event. The optional
	 * argument keeps deployment compatible with the previous Plugin wiring.
	 */
	public function register( ?string $legacy_hook = null ): void {
		unset( $legacy_hook );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( self::class, 'ensure_scheduled' ), 20 );
	}

	public static function activate(): void {
		self::ensure_scheduled();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
		delete_option( self::OFFSET_OPTION );
	}

	/** Existing deployments get the new event without requiring reactivation. */
	public static function ensure_scheduled(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, self::INTERVAL, self::HOOK );
		}
	}

	/**
	 * @return array{considered:int,sent:int,failed:int,skipped:int}
	 */
	public function run(): array {
		$summary = array(
			'considered' => 0,
			'sent'       => 0,
			'failed'     => 0,
			'skipped'    => 0,
		);
		$now     = time();
		$offset  = max( 0, (int) get_option( self::OFFSET_OPTION, 0 ) );
		$records = $this->active_records( $offset );
		if ( array() === $records && $offset > 0 ) {
			// The active set may shrink between runs; wrap immediately instead of
			// spending a whole cron interval on a stale offset.
			$offset  = 0;
			$records = $this->active_records( $offset );
		}
		$next_offset = count( $records ) < self::BATCH_SIZE ? 0 : $offset + self::BATCH_SIZE;
		update_option( self::OFFSET_OPTION, (string) $next_offset, false );

		foreach ( $records as $record_id ) {
			$record_id = (int) $record_id;
			++$summary['considered'];

			try {
				$outcome = $this->process_record( $record_id, $now );
			} catch ( \Throwable $throwable ) {
				unset( $throwable );
				update_post_meta( $record_id, 'workflow_reminder_status', 'failed' );
				update_post_meta( $record_id, 'workflow_reminder_error', 'processing_exception' );
				$outcome = 'failed';
			}
			++$summary[ $outcome ];
		}

		return $summary;
	}

	/** @return int[] */
	private function active_records( int $offset ): array {
		$task_query = array( 'relation' => 'OR' );
		foreach ( TaskPolicy::tasks() as $kind ) {
			$task_query[] = array(
				'key'     => 'task_' . $kind . '_status',
				'value'   => self::ACTIVE,
				'compare' => 'IN',
			);
		}
		$records = get_posts(
			array(
				'post_type'      => 'poolhall_application',
				'post_status'    => 'any',
				'posts_per_page' => self::BATCH_SIZE,
				'offset'         => max( 0, $offset ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// Bounded private workflow queue run by cron.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'kind',
						'value'   => array( 'registration', 'job_application' ),
						'compare' => 'IN',
					),
					$task_query,
				),
			)
		);
		return array_values( array_filter( array_map( 'intval', $records ) ) );
	}

	/** @return 'sent'|'failed'|'skipped' */
	private function process_record( int $record_id, int $now ): string {
		if ( ! CandidateIdentity::is_workflow_owner( $record_id ) ) {
			return 'skipped';
		}

		$stage = $this->reminder_stage( $record_id );
		if ( '' === $stage || $this->workflow_finished( $record_id ) ) {
			return 'skipped';
		}

		$expires = (int) get_post_meta( $record_id, $this->invite_meta( $stage, 'expires' ), true );
		if ( $expires <= $now ) {
			update_post_meta( $record_id, $this->invite_meta( $stage, 'reminder_status' ), 'expired' );
			return 'skipped';
		}

		// A successful manual resend or Save & Continue email restarts the
		// cooldown, preventing an automatic chase immediately afterwards.
		$baseline = $this->mailer->reminder_baseline_at( $record_id, $stage );
		$day      = $this->due_day( $record_id, $stage, $baseline, $now );
		if ( 0 === $day ) {
			return 'skipped';
		}

		// Claim before sending so overlapping cron workers cannot both emit it.
		// Failed automatic attempts stay visible for a deliberate manual retry.
		$attempt_key = $this->reminder_meta( $stage, $day, 'attempted_at' );
		$attempted   = gmdate( \DateTimeInterface::ATOM );
		if ( ! add_post_meta( $record_id, $attempt_key, $attempted, true ) ) {
			return 'skipped';
		}

		try {
			$accepted = $this->mailer->send_reminder( $record_id, $stage );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			$accepted = false;
			update_post_meta( $record_id, $this->reminder_meta( $stage, $day, 'error' ), 'transport_exception' );
		}
		update_post_meta( $record_id, $this->reminder_meta( $stage, $day, 'status' ), $accepted ? 'accepted' : 'failed' );
		update_post_meta( $record_id, $this->invite_meta( $stage, 'reminder_status' ), $accepted ? 'accepted' : 'failed' );
		if ( ! $accepted ) {
			return 'failed';
		}

		update_post_meta( $record_id, $this->reminder_meta( $stage, $day, 'sent_at' ), gmdate( \DateTimeInterface::ATOM ) );
		delete_post_meta( $record_id, $this->reminder_meta( $stage, $day, 'error' ) );
		return 'sent';
	}

	private function reminder_stage( int $record_id ): string {
		$full = $this->state->task_status( $record_id, 'full' );
		if ( in_array( $full, self::ACTIVE, true ) ) {
			return 'profile';
		}
		foreach ( TaskPolicy::documents() as $kind ) {
			if ( in_array( $this->state->task_status( $record_id, $kind ), self::ACTIVE, true ) && '' !== (string) get_post_meta( $record_id, 'invite_task_' . $kind . '_mail_sent_at', true ) ) {
				return 'task_' . $kind;
			}
		}
		if ( 'accepted' !== $full ) {
			return '';
		}
		foreach ( TaskPolicy::documents() as $kind ) {
			if ( in_array( $this->state->task_status( $record_id, $kind ), self::ACTIVE, true ) ) {
				return 'onboarding';
			}
		}
		return '';
	}

	private function workflow_finished( int $record_id ): bool {
		return in_array( $this->state->stage( $record_id ), array( 'ready', 'closed' ), true )
			|| 'rejected' === (string) get_post_meta( $record_id, 'review_status', true );
	}

	private function due_day( int $record_id, string $stage, int $baseline, int $now ): int {
		if ( $baseline <= 0 || $baseline > $now ) {
			return 0;
		}
		foreach ( array_reverse( self::REMINDER_DAYS ) as $day ) {
			if ( $now < $baseline + ( $day * 86400 ) ) {
				continue;
			}
			// The most recent due checkpoint wins. If its attempt is already
			// claimed, never send an older missed reminder afterwards.
			return '' === (string) get_post_meta( $record_id, $this->reminder_meta( $stage, $day, 'attempted_at' ), true ) ? $day : 0;
		}
		return 0;
	}

	private function invite_meta( string $stage, string $suffix ): string {
		return 'invite_' . sanitize_key( $stage ) . '_' . sanitize_key( $suffix );
	}

	private function reminder_meta( string $stage, int $day, string $suffix ): string {
		return 'invite_' . sanitize_key( $stage ) . '_reminder_' . $day . '_' . sanitize_key( $suffix );
	}
}
