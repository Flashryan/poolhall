<?php
declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Applications\ApplicationRecord;
use Poolhall\Integration\Applications\CandidateIdentity;
use Poolhall\Integration\Applications\CandidateMatch;
use Poolhall\Integration\Source\CandidatePayload;
use Poolhall\Integration\Source\CandidateReferenceReader;
use Poolhall\Integration\Source\CandidateSink;

/** Staff-only explicit linking, with auditable human verification on a denied read. */
final class CandidateGiigLink {
	public function __construct( private readonly CandidateSink $sink ) {}

	public function link( int $record, string $id, bool $staff_verified ): string {
		if ( ! current_user_can( 'manage_options' ) || ApplicationRecord::POST_TYPE !== get_post_type( $record )
			|| ! in_array( (string) get_post_meta( $record, 'kind', true ), array( 'registration', 'job_application' ), true )
			|| 'closed' === (string) get_post_meta( $record, 'workflow_stage', true )
			|| CandidateIdentity::workflow_owner( $record ) !== $record || CandidateIdentity::review_required( $record ) ) {
			return 'giig_link_not_allowed';
		}
		$id = trim( $id );
		if ( ! preg_match( '/^[1-9][0-9]{0,19}$/', $id ) ) return $this->blocked( $record, 'invalid_giig_id' );
		$profile = new CandidatePayload(
			trim( (string) get_post_meta( $record, 'applicant_first', true ) ),
			trim( (string) get_post_meta( $record, 'applicant_last', true ) ),
			trim( (string) get_post_meta( $record, 'applicant_email', true ) )
		);
		if ( ! $profile->is_valid() || ! is_email( $profile->email ) ) return $this->blocked( $record, 'invalid_profile' );
		$lease = CandidateSyncLock::acquire( CandidateIdentity::key( $record ) );
		if ( null === $lease ) return 'giig_link_busy';
		$id_lease = null;
		try {
			$existing = CandidateIdentity::giig_id( $record );
			if ( CandidateIdentity::review_required( $record ) || '' !== $existing && $id !== $existing ) return $this->blocked( $record, 'giig_id_conflict' );
			$id_lease = CandidateSyncLock::acquire( 'giig-id:' . $id );
			if ( null === $id_lease ) return 'giig_link_busy';
			if ( ! CandidateMatch::id_available( $record, $id ) ) return $this->blocked( $record, 'giig_id_in_use' );
			if ( ! $this->sink instanceof CandidateReferenceReader || ! $this->sink->is_configured() ) return $this->blocked( $record, 'unconfigured' );
			$method = 'api';
			try {
				$remote = $this->sink->find_candidate_by_id( $id );
				if ( null === $remote || $id !== (string) ( $remote['id'] ?? '' ) || ! CandidateMatch::matches( $remote, $profile ) ) return $this->blocked( $record, 'giig_details_mismatch' );
			} catch ( \Throwable $error ) {
				// Only a documented read-permission denial permits human verification.
				// Never override a mismatch, 401/auth failure, timeout or malformed data.
				if ( ! preg_match( '/(?:HTTP|API status) 403\b/', $error->getMessage() ) ) return $this->blocked( $record, 'id_verification_failed' );
				if ( ! $staff_verified ) return $this->blocked( $record, 'manual_confirmation_required' );
				$method = 'staff_attestation';
			}
			if ( ! CandidateIdentity::remember_giig_id( $record, $id ) || $id !== (string) get_post_meta( $record, 'giig_candidate_id', true ) ) return $this->blocked( $record, 'link_storage_failed' );
			update_post_meta( $record, 'giig_link_verified_method', $method );
			update_post_meta( $record, 'giig_link_verified_by', (string) get_current_user_id() );
			update_post_meta( $record, 'giig_link_verified_at', gmdate( \DateTimeInterface::ATOM ) );
			update_post_meta( $record, 'giig_link_origin', 'manual_existing' );
			update_post_meta( $record, 'giig_link_status', 'linked' );
			delete_post_meta( $record, 'giig_link_error' );
			delete_post_meta( $record, 'giig_create_uncertain' );
			// Linking a candidate must not mark an outstanding job application sent.
			if ( 'job_application' === (string) get_post_meta( $record, 'kind', true ) && 'pushed' !== (string) get_post_meta( $record, 'giig_push', true ) && '' !== (string) get_post_meta( $record, 'source_job_id', true ) ) {
				update_post_meta( $record, 'giig_push', 'queued' );
				update_post_meta( $record, 'giig_push_attempts', '0' );
			} else {
				update_post_meta( $record, 'giig_push', 'pushed' );
				delete_post_meta( $record, 'giig_push_error' );
			}
			( new CandidateGiigSync( $this->sink ) )->queue( $record );
			return 'api' === $method ? 'giig_linked_api' : 'giig_linked_staff';
		} finally {
			if ( null !== $id_lease ) CandidateSyncLock::release( $id_lease );
			CandidateSyncLock::release( $lease );
		}
	}

	private function blocked( int $record, string $error ): string {
		update_post_meta( $record, 'giig_link_error', $error );
		update_post_meta( $record, 'giig_link_status', '' !== (string) get_post_meta( $record, 'giig_candidate_id', true ) ? 'partial' : 'needs_review' );
		return 'giig_link_blocked';
	}
}
