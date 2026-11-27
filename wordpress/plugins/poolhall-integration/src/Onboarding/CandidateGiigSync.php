<?php
declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Applications\ApplicationRecord;
use Poolhall\Integration\Applications\CandidateIdentity;
use Poolhall\Integration\Applications\CandidateResolver;
use Poolhall\Integration\Source\CandidateDirectory;
use Poolhall\Integration\Source\CandidatePayload;
use Poolhall\Integration\Source\CandidateSink;

/** Approval-to-ATS orchestration, independent of forms, PDF storage and mail. */
final class CandidateGiigSync {

	public const APPROVED_HOOK = 'poolhall_candidate_profile_accepted';
	public const DOCUMENT_HOOK = 'poolhall_candidate_document_accepted';
	public const RETRY_HOOK = 'poolhall_candidate_giig_retry';
	public const CRON_HOOK = 'poolhall_candidate_giig_sync';

	public function __construct( private readonly CandidateSink $sink ) {}

	public function register(): void {
		add_action( self::APPROVED_HOOK, array( $this, 'queue' ) );
		add_action( self::DOCUMENT_HOOK, array( $this, 'queue' ) );
		add_action( self::RETRY_HOOK, array( $this, 'run' ) );
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
	}

	/** Approval stays fast and durable even if the external API is unavailable. */
	public function queue( int $record ): void {
		if ( ! $this->eligible( $record ) ) {
			return;
		}
		if ( $this->schedule( $record ) ) {
			$this->status( $record, 'queued', '' );
		} else {
			$this->status( $record, 'failed', 'queue_failed' );
		}
	}

	public function run( int $record ): void {
		if ( ! $this->eligible( $record ) ) {
			return;
		}
		$key = CandidateIdentity::key( $record );
		if ( '' === $key || CandidateIdentity::review_required( $record ) ) {
			$this->status( $record, 'needs_review', 'identity_review' );
			return;
		}
		$lease = CandidateSyncLock::acquire( $key );
		if ( null === $lease ) {
			return; // Do not overwrite another running attempt's status.
		}
		$operation = 'lookup';
		try {
			$this->status( $record, 'syncing', '' );
			update_post_meta( $record, 'giig_link_attempts', (string) ( 1 + (int) get_post_meta( $record, 'giig_link_attempts', true ) ) );
			if ( ! $this->sink instanceof CandidateDirectory || ! $this->sink->is_configured() ) {
				$this->status( $record, 'failed', 'unconfigured' );
				return;
			}
			$profile = $this->profile( $record );
			if ( ! $profile->is_valid() || ! is_email( $profile->email ) ) {
				$this->status( $record, 'needs_review', 'invalid_profile' );
				return;
			}
			$id = ( new CandidateResolver( $this->sink ) )->resolve( $record, $profile, $lease );
			// Candidate linking alone must not mark an outstanding job application sent.
			if ( 'registration' === (string) get_post_meta( $record, 'kind', true ) ) {
				update_post_meta( $record, 'giig_push', 'pushed' );
				delete_post_meta( $record, 'giig_push_error' );
			}
			$address = $this->address( $record );
			$digest = hash_hmac( 'sha256', (string) wp_json_encode( array( $profile, $address ) ), wp_salt( 'auth' ) );
			if ( $digest !== (string) get_post_meta( $record, 'giig_link_profile_digest', true ) ) {
				$operation = 'update';
				$this->sink->update_profile( $id, $profile, $address );
				update_post_meta( $record, 'giig_link_profile_digest', $digest );
				update_post_meta( $record, 'giig_update_status', 'updated' );
				update_post_meta( $record, 'giig_update_attempted_at', gmdate( \DateTimeInterface::ATOM ) );
			}
			// At most one timeline note per run keeps external calls bounded.
			// Other accepted tasks are continued in a separate background run.
			$pending = $this->pending_notes( $record, $id );
			if ( array() !== $pending ) {
				$operation = 'note';
				$task = array_key_first( $pending );
				$entry = $pending[ $task ];
				$labels = array( 'full' => 'Candidate Profile', 'contract' => 'Agency Worker Contract', 'terms' => 'Candidate Terms', 'starter' => 'HMRC Starter Checklist', 'payroll' => 'Payroll Details' );
				$this->sink->add_note( $id, 'Poolhall reviewed and accepted the ' . $labels[ $task ] . ' (website entry #' . $entry . '). The signed PDF is held in the protected Poolhall record: ' . $this->record_url( $record ) . ' — staff login required.' );
				update_post_meta( $record, 'task_' . $task . '_giig_accepted_note', $id . ':' . $entry );
			}
			if ( array() !== $this->pending_notes( $record, $id ) ) {
				$this->status( $record, 'partial', $this->schedule( $record ) ? 'notes_pending' : 'queue_failed' );
			} else {
				$this->status( $record, 'synced', '' );
				update_post_meta( $record, 'giig_link_synced_at', gmdate( \DateTimeInterface::ATOM ) );
			}
		} catch ( \Throwable $error ) {
			$permission = (bool) preg_match( '/(?:HTTP|API status) (?:401|403)\b/', $error->getMessage() );
			$code = $permission ? 'permission_denied' : $operation . '_failed';
			$known = array( 'identity_review', 'invalid_profile', 'ambiguous_match', 'create_uncertain', 'storage_failed', 'link_storage_failed', 'giig_id_in_use', 'sync_busy', 'unconfigured' );
			if ( in_array( $error->getMessage(), $known, true ) ) $code = $error->getMessage();
			if ( 'create' === $operation ) {
				$code = 'create_uncertain';
			} elseif ( 'lookup' === $operation && $error instanceof \Poolhall\Integration\Source\SourceException && $error->response_incomplete ) {
				$code = 'lookup_incomplete';
			}
			$linked = '' !== (string) get_post_meta( $record, 'giig_candidate_id', true );
			$this->status( $record, $linked ? 'partial' : ( in_array( $code, array( 'create_uncertain', 'lookup_incomplete' ), true ) ? 'needs_review' : 'failed' ), $code );
		} finally {
			CandidateSyncLock::release( $lease );
		}
	}

	private function eligible( int $record ): bool {
		return $record > 0 && ApplicationRecord::POST_TYPE === get_post_type( $record )
			&& in_array( (string) get_post_meta( $record, 'kind', true ), array( 'registration', 'job_application' ), true )
			&& 'accepted' === (string) get_post_meta( $record, 'task_full_status', true )
			&& 'closed' !== (string) get_post_meta( $record, 'workflow_stage', true )
			&& CandidateIdentity::workflow_owner( $record ) === $record;
	}

	private function profile( int $record ): CandidatePayload {
		return new CandidatePayload(
			first_name: trim( (string) get_post_meta( $record, 'applicant_first', true ) ),
			last_name: trim( (string) get_post_meta( $record, 'applicant_last', true ) ),
			email: trim( (string) get_post_meta( $record, 'applicant_email', true ) ),
			phone: trim( (string) get_post_meta( $record, 'applicant_phone', true ) ),
			role_title: trim( (string) get_post_meta( $record, 'role_title', true ) ),
			location: trim( (string) get_post_meta( $record, 'location', true ) ),
			notes: 'Candidate profile approved by Poolhall. Protected website record: ' . $this->record_url( $record ),
			source: '1' === (string) get_post_meta( $record, 'added_manually', true ) ? 'Poolhall staff registration' : 'Website',
			tags: 'Website,Profile Approved'
		);
	}

	private function address( int $record ): array {
		$address = array();
		foreach ( array( 'line1' => 'address_line_1', 'town' => 'town_city', 'county' => 'county', 'postcode' => 'postcode' ) as $key => $meta ) {
			$address[ $key ] = trim( (string) get_post_meta( $record, $meta, true ) );
		}
		return $address;
	}


	private function pending_notes( int $record, string $id ): array {
		$pending = array();
		foreach ( WorkflowState::TASKS as $task ) {
			$entry = (int) get_post_meta( $record, 'task_' . $task . '_entry_id', true );
			if ( 'accepted' === (string) get_post_meta( $record, 'task_' . $task . '_status', true ) && $entry > 0
				&& $id . ':' . $entry !== (string) get_post_meta( $record, 'task_' . $task . '_giig_accepted_note', true ) ) {
				$pending[ $task ] = $entry;
			}
		}
		return $pending;
	}

	private function record_url( int $record ): string {
		return admin_url( 'admin.php?page=poolhall-candidates&record=' . $record );
	}

	private function schedule( int $record ): bool {
		return false !== wp_next_scheduled( self::CRON_HOOK, array( $record ) )
			|| true === wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $record ), true );
	}

	private function status( int $record, string $status, string $error ): void {
		update_post_meta( $record, 'giig_link_status', $status );
		update_post_meta( $record, 'giig_link_error', $error );
		update_post_meta( $record, 'giig_link_checked_at', gmdate( \DateTimeInterface::ATOM ) );
	}
}
