<?php
declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Onboarding\CandidateSyncLock;
use Poolhall\Integration\Source\CandidateDirectory;
use Poolhall\Integration\Source\CandidatePayload;
use Poolhall\Integration\Source\CandidateSink;
use Poolhall\Integration\Source\SourceException;

/** One durable create/link boundary shared by public submissions and approval. */
final class CandidateResolver {
	public function __construct( private readonly CandidateSink $sink ) {}

	/** An optional caller-owned identity lease is validated, never trusted blindly. */
	public function resolve( int $record, CandidatePayload $profile, ?array $held_lease = null ): string {
		$key = CandidateIdentity::key( $record );
		if ( ApplicationRecord::POST_TYPE !== get_post_type( $record ) || '' === $key || CandidateIdentity::review_required( $record )
			|| ! in_array( (string) get_post_meta( $record, 'kind', true ), array( 'registration', 'job_application' ), true )
			|| 'closed' === (string) get_post_meta( $record, 'workflow_stage', true ) ) {
			throw new SourceException( 'identity_review' );
		}
		if ( ! $profile->is_valid() || ! is_email( $profile->email ) ) {
			throw new SourceException( 'invalid_profile' );
		}
		$lease = $held_lease ?? CandidateSyncLock::acquire( $key );
		$expected = 'poolhall_giig_link_lock_' . hash( 'sha256', $key );
		if ( null === $lease || ( $lease['key'] ?? '' ) !== $expected || get_option( $expected, null ) !== ( $lease['value'] ?? null ) || (int) ( $lease['value']['expires'] ?? 0 ) <= time() ) {
			throw new SourceException( 'sync_busy' );
		}
		$email_lease = null;
		try {
			$id = CandidateIdentity::giig_id( $record );
			if ( CandidateIdentity::review_required( $record ) ) {
				throw new SourceException( 'identity_review' );
			}
			if ( '' !== $id ) {
				return $this->persist( $record, $id );
			}
			if ( ! $this->sink instanceof CandidateDirectory || ! $this->sink->is_configured() ) {
				throw new SourceException( 'unconfigured' );
			}
			$email_lease = CandidateSyncLock::acquire( 'giig-email:' . strtolower( trim( $profile->email ) ) );
			if ( null === $email_lease ) {
				throw new SourceException( 'sync_busy' );
			}
			$matches = $this->sink->find_candidates_by_email( $profile->email );
			if ( count( $matches ) > 1 || 1 === count( $matches ) && ! CandidateMatch::matches( $matches[0], $profile ) ) {
				throw new SourceException( 'ambiguous_match' );
			}
			if ( 1 === count( $matches ) ) {
				$id = $matches[0]['id'];
				update_post_meta( $record, 'giig_link_origin', 'existing' );
			} else {
				foreach ( get_posts( array( 'post_type' => ApplicationRecord::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => 'applicant_email_normalized', 'meta_value' => strtolower( trim( $profile->email ) ) ) ) as $other ) {
					if ( (int) $other !== $record && ( '' !== (string) get_post_meta( (int) $other, 'giig_candidate_id', true ) || '' !== (string) get_post_meta( (int) $other, 'giig_create_uncertain', true ) ) ) {
						throw new SourceException( 'identity_review' );
					}
				}
				if ( '' !== (string) get_post_meta( $record, 'giig_create_uncertain', true ) ) {
					throw new SourceException( 'create_uncertain' );
				}
				update_post_meta( $record, 'giig_create_uncertain', '1' );
				if ( '1' !== (string) get_post_meta( $record, 'giig_create_uncertain', true ) ) {
					throw new SourceException( 'storage_failed' );
				}
				try {
					$id = $this->sink->register_candidate( $profile )->source_candidate_id;
				} catch ( \Throwable $error ) {
					throw new SourceException( 'create_uncertain', previous: $error );
				}
				update_post_meta( $record, 'giig_link_origin', 'created' );
			}
			return $this->persist( $record, $id );
		} finally {
			if ( null !== $email_lease ) CandidateSyncLock::release( $email_lease );
			if ( null === $held_lease ) CandidateSyncLock::release( $lease );
		}
	}

	private function persist( int $record, string $id ): string {
		if ( ! preg_match( '/^[1-9][0-9]*$/', $id ) || ! CandidateMatch::id_available( $record, $id ) ) {
			throw new SourceException( 'giig_id_in_use' );
		}
		if ( ! CandidateIdentity::remember_giig_id( $record, $id ) || $id !== (string) get_post_meta( $record, 'giig_candidate_id', true ) ) {
			throw new SourceException( 'link_storage_failed' );
		}
		delete_post_meta( $record, 'giig_create_uncertain' );
		return $id;
	}
}
