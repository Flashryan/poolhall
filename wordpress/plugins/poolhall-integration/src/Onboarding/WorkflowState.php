<?php
/**
 * Candidate workflow state and per-task checklist.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/** Keeps overall stage separate from each form's task state. */
final class WorkflowState {

	public const TASKS = array( 'full', 'contract', 'terms', 'starter', 'payroll' );

	private const TASK_STATUSES = array( 'not_required', 'awaiting', 'in_progress', 'needs_correction', 'submitted', 'accepted' );

	public function task_status( int $record_id, string $kind ): string {
		$status = (string) get_post_meta( $record_id, $this->meta( $kind, 'status' ), true );
		return '' !== $status ? $status : 'not_required';
	}

	public function set_task_status( int $record_id, string $kind, string $status ): void {
		$status = sanitize_key( $status );
		if ( $record_id <= 0 || ! in_array( $kind, self::TASKS, true ) || ! in_array( $status, self::TASK_STATUSES, true ) ) {
			return;
		}
		$current = $this->task_status( $record_id, $kind );
		if ( $status !== $current && ! $this->transition_allowed( $current, $status ) ) {
			return;
		}
		update_post_meta( $record_id, $this->meta( $kind, 'status' ), $status );
		update_post_meta( $record_id, $this->meta( $kind, 'updated_at' ), gmdate( \DateTimeInterface::ATOM ) );
		update_post_meta( $record_id, 'workflow_stage_updated_at', gmdate( \DateTimeInterface::ATOM ) );
		$this->mirror_stage( $record_id );
	}

	/** @param string[] $required */
	public function begin_stage( int $record_id, string $stage, array $required ): bool {
		$stage = sanitize_key( $stage );
		if ( $record_id <= 0 || ! in_array( $stage, array( 'profile', 'onboarding' ), true ) ) {
			return false;
		}

		// Starting a stage is intentionally one-way. Resends never call this
		// method, so a stale admin POST cannot reset submitted or accepted tasks.
		$expected = 'profile' === $stage ? 'new' : 'registered';
		if ( $expected !== $this->stage( $record_id ) ) {
			return false;
		}
		$lock = wp_generate_uuid4();
		if ( ! add_post_meta( $record_id, 'workflow_transition_lock', $lock, true ) ) {
			return false;
		}

		try {
			// Recheck after acquiring the per-record transition lock.
			if ( $expected !== $this->stage( $record_id ) ) {
				return false;
			}
			$allowed  = 'profile' === $stage ? array( 'full' ) : TaskPolicy::documents();
			$required = array_values(
				array_unique(
					array_intersect(
						$allowed,
						array_filter(
							array_map(
								static fn( mixed $kind ): string => is_scalar( $kind ) ? sanitize_key( (string) $kind ) : '',
								$required
							)
						)
					)
				)
			);
			$required = 'profile' === $stage
				? array( 'full' )
				: array_values( array_unique( array_merge( TaskPolicy::archived( 'terms' ) ? array() : array( 'terms' ), $required ) ) );
			if ( array() === $required ) {
				return false;
			}
			$now      = gmdate( \DateTimeInterface::ATOM );

			// Write task state directly, then publish the overall stage once. Calling
			// set_task_status() here would mirror several transient intermediate stages.
			foreach ( $allowed as $kind ) {
				$current = $this->task_status( $record_id, $kind );
				$status  = 'not_required' === $current && in_array( $kind, $required, true ) ? 'awaiting' : $current;
				update_post_meta( $record_id, $this->meta( $kind, 'status' ), $status );
				update_post_meta( $record_id, $this->meta( $kind, 'updated_at' ), $now );
			}
			update_post_meta( $record_id, 'workflow_stage', 'profile' === $stage ? 'awaiting_profile' : 'onboarding' );
			update_post_meta( $record_id, 'workflow_stage_started_at', $now );
			update_post_meta( $record_id, 'workflow_stage_updated_at', $now );
			return true;
		} finally {
			delete_post_meta( $record_id, 'workflow_transition_lock', $lock );
		}
	}

	/** Activate exactly one document without changing or resetting other tasks. */
	public function assign_document( int $record_id, string $kind ): bool {
		if ( $record_id <= 0 || ! in_array( $kind, TaskPolicy::documents(), true ) || 'closed' === $this->stage( $record_id ) ) {
			return false;
		}
		$lock = wp_generate_uuid4();
		if ( ! add_post_meta( $record_id, 'workflow_transition_lock', $lock, true ) ) {
			return false;
		}
		try {
			if ( 'not_required' !== $this->task_status( $record_id, $kind ) || 'closed' === $this->stage( $record_id ) ) {
				return false;
			}
			$this->set_task_status( $record_id, $kind, 'awaiting' );
			return true;
		} finally {
			delete_post_meta( $record_id, 'workflow_transition_lock', $lock );
		}
	}

	/** Add the remaining pack after individual documents were already issued. */
	public function extend_onboarding( int $record_id, array $required ): bool {
		if ( $record_id <= 0 || ! in_array( $this->stage( $record_id ), array( 'onboarding', 'onboarding_review', 'ready' ), true ) || 'accepted' !== $this->task_status( $record_id, 'full' ) ) {
			return false;
		}
		$required = array_values( array_intersect( TaskPolicy::documents(), $required ) );
		$pending  = array_values( array_filter( $required, fn( string $kind ): bool => 'not_required' === $this->task_status( $record_id, $kind ) ) );
		if ( array() === $pending ) {
			return false;
		}
		$lock = wp_generate_uuid4();
		if ( ! add_post_meta( $record_id, 'workflow_transition_lock', $lock, true ) ) {
			return false;
		}
		try {
			foreach ( $pending as $kind ) {
				if ( 'not_required' === $this->task_status( $record_id, $kind ) ) {
					$this->set_task_status( $record_id, $kind, 'awaiting' );
				}
			}
			return true;
		} finally {
			delete_post_meta( $record_id, 'workflow_transition_lock', $lock );
		}
	}

	public function mark_submitted( int $record_id, string $kind, int $entry_id ): void {
		if (
			$record_id <= 0
			|| $entry_id <= 0
			|| ! in_array( $kind, TaskPolicy::tasks(), true )
			|| ( 'full' === $kind && 'awaiting_profile' !== $this->stage( $record_id ) )
			|| ! in_array( $this->task_status( $record_id, $kind ), array( 'awaiting', 'in_progress', 'needs_correction' ), true )
		) {
			return;
		}
		update_post_meta( $record_id, $this->meta( $kind, 'entry_id' ), (string) $entry_id );
		update_post_meta( $record_id, $this->meta( $kind, 'submitted_at' ), gmdate( \DateTimeInterface::ATOM ) );
		$this->set_task_status( $record_id, $kind, 'submitted' );
	}

	public function mark_accepted( int $record_id, string $kind ): void {
		if ( $record_id <= 0 || ! in_array( $kind, self::TASKS, true ) || 'submitted' !== $this->task_status( $record_id, $kind ) ) {
			return;
		}
		update_post_meta( $record_id, $this->meta( $kind, 'accepted_at' ), gmdate( \DateTimeInterface::ATOM ) );
		$this->set_task_status( $record_id, $kind, 'accepted' );
	}

	public function mark_needs_correction( int $record_id, string $kind ): void {
		if ( $record_id <= 0 || ! in_array( $kind, self::TASKS, true ) || 'submitted' !== $this->task_status( $record_id, $kind ) ) {
			return;
		}
		$this->set_task_status( $record_id, $kind, 'needs_correction' );
	}

	public function mail_status( int $record_id, string $kind ): string {
		return (string) ( get_post_meta( $record_id, $this->meta( $kind, 'mail_status' ), true ) ?: 'not_sent' );
	}

	public function set_mail_status( int $record_id, string $kind, bool $accepted ): void {
		update_post_meta( $record_id, $this->meta( $kind, 'mail_status' ), $accepted ? 'accepted' : 'failed' );
		update_post_meta( $record_id, $this->meta( $kind, 'mail_attempted_at' ), gmdate( \DateTimeInterface::ATOM ) );
	}

	/** @return array<string,string> */
	public function checklist( int $record_id ): array {
		$out = array();
		foreach ( self::TASKS as $kind ) {
			$out[ $kind ] = $this->task_status( $record_id, $kind );
		}
		return $out;
	}

	public function stage( int $record_id ): string {
		$stored = (string) get_post_meta( $record_id, 'workflow_stage', true );
		if ( '' !== $stored ) {
			return $stored;
		}
		$legacy = (string) get_post_meta( $record_id, 'review_status', true );
		return match ( $legacy ) {
			'rejected'    => 'closed',
			'invite_sent' => 'awaiting_profile',
			'completed'   => 'registration_review',
			default       => 'new',
		};
	}

	public function next_action( int $record_id ): string {
		$stage = $this->stage( $record_id );
		if ( 'new' === $stage ) {
			return 'approve_profile';
		}
		if ( 'awaiting_profile' === $stage && 'failed' === $this->mail_status( $record_id, 'full' ) ) {
			return 'retry_profile';
		}
		if ( 'registration_review' === $stage ) {
			return 'review_profile';
		}
		if ( 'registered' === $stage ) {
			return 'start_onboarding';
		}
		if ( 'onboarding' === $stage ) {
			foreach ( TaskPolicy::documents() as $kind ) {
				if ( 'failed' === $this->mail_status( $record_id, $kind ) ) {
					return 'retry_onboarding';
				}
			}
			return 'awaiting_candidate';
		}
		if ( 'onboarding_review' === $stage ) {
			return 'review_onboarding';
		}
		if ( 'ready' === $stage || 'closed' === $stage ) {
			return 'view';
		}
		return 'awaiting_candidate';
	}

	public function meta( string $kind, string $suffix ): string {
		return 'task_' . sanitize_key( $kind ) . '_' . sanitize_key( $suffix );
	}

	private function transition_allowed( string $from, string $to ): bool {
		$allowed = array(
			'not_required'     => array( 'awaiting' ),
			'awaiting'         => array( 'in_progress', 'submitted' ),
			'in_progress'      => array( 'submitted' ),
			'needs_correction' => array( 'in_progress', 'submitted' ),
			'submitted'        => array( 'accepted', 'needs_correction' ),
		);
		return in_array( $to, $allowed[ $from ] ?? array(), true );
	}

	/** Recalculate the active stage after a document is archived or restored. */
	public function reconcile( int $record_id ): void {
		$this->mirror_stage( $record_id );
	}

	private function mirror_stage( int $record_id ): void {
		if ( 'closed' === $this->stage( $record_id ) ) {
			return;
		}
		$full = $this->task_status( $record_id, 'full' );
		if ( 'needs_correction' === $full ) {
			update_post_meta( $record_id, 'workflow_stage', 'awaiting_profile' );
			return;
		}
		if ( 'submitted' === $full ) {
			update_post_meta( $record_id, 'workflow_stage', 'registration_review' );
			update_post_meta( $record_id, 'review_status', 'completed' );
			return;
		}
		if ( 'accepted' !== $full ) {
			return;
		}

		$onboarding_required = array_filter(
			TaskPolicy::documents(),
			fn( string $kind ): bool => 'not_required' !== $this->task_status( $record_id, $kind )
		);
		if ( array() === $onboarding_required ) {
			update_post_meta( $record_id, 'workflow_stage', 'registered' );
			return;
		}

		$statuses = array_map( fn( string $kind ): string => $this->task_status( $record_id, $kind ), $onboarding_required );
		if ( count( array_filter( $statuses, static fn( string $status ): bool => 'accepted' === $status ) ) === count( $statuses ) ) {
			update_post_meta( $record_id, 'workflow_stage', 'ready' );
			return;
		}
		if ( count( array_filter( $statuses, static fn( string $status ): bool => in_array( $status, array( 'submitted', 'accepted' ), true ) ) ) === count( $statuses ) ) {
			update_post_meta( $record_id, 'workflow_stage', 'onboarding_review' );
			return;
		}
		update_post_meta( $record_id, 'workflow_stage', 'onboarding' );
	}
}
