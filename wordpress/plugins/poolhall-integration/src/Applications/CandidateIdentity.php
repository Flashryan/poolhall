<?php
/**
 * Durable candidate identity for website application records.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

/**
 * Candidate keys, rather than mutable or unverified email addresses, are the
 * only automatic reconciliation boundary. A conflicting email or Giig id is
 * retained as evidence and routed to staff review; it never rebinds records.
 */
final class CandidateIdentity {

	public const EMAIL_UPDATED   = 'updated';
	public const EMAIL_UNCHANGED = 'unchanged';
	public const EMAIL_COLLISION = 'collision';
	public const EMAIL_INVALID   = 'invalid';

	public const REVIEW_META        = 'candidate_identity_review';
	public const REVIEW_REASON_META = 'candidate_identity_review_reason';
	public const OWNER_META         = 'candidate_workflow_owner_id';

	private const KEY_META              = 'candidate_key';
	private const KEY_SOURCE_META       = 'candidate_key_source';
	private const EMAIL_META            = 'applicant_email_normalized';
	private const GIIG_META             = 'giig_candidate_id';
	private const CONFLICTING_GIIG_META = 'candidate_identity_conflicting_giig_id';

	/**
	 * Give a newly stored record its own identity. Email is recorded only as
	 * contact data and is deliberately never used to find or copy an identity.
	 */
	public static function attach( int $record_id, string $email ): string {
		if ( $record_id <= 0 ) {
			return '';
		}

		update_post_meta( $record_id, self::EMAIL_META, self::normalize_email( $email ) );
		$key = self::ensure_key( $record_id );
		if ( '' !== $key ) {
			self::workflow_owner( $record_id );
		}
		return $key;
	}

	/** Durable local key without creating one as a side effect. */
	public static function key( int $record_id ): string {
		return $record_id > 0 ? trim( (string) get_post_meta( $record_id, self::KEY_META, true ) ) : '';
	}

	/**
	 * Resolve the Giig id from this record or another record already carrying
	 * the same durable candidate key. Email is intentionally not accepted.
	 */
	public static function giig_id( int $record_id ): string {
		if ( $record_id <= 0 ) {
			return '';
		}

		$direct = trim( (string) get_post_meta( $record_id, self::GIIG_META, true ) );
		$key    = self::key( $record_id );
		if ( '' === $key ) {
			return $direct;
		}

		$records = self::records_for_key( $key, $record_id );
		$ids     = self::giig_ids( $records );
		if ( '' !== $direct ) {
			if ( count( $ids ) > 1 ) {
				self::flag_review( $records, 'giig_id_collision' );
			}
			return $direct;
		}

		if ( 1 === count( $ids ) ) {
			return $ids[0];
		}
		if ( count( $ids ) > 1 ) {
			self::flag_review( $records, 'giig_id_collision' );
		}
		return '';
	}

	/**
	 * Store a newly returned Giig id without overwriting any different id.
	 * Returns false when staff reconciliation is required.
	 */
	public static function remember_giig_id( int $record_id, string $candidate_id ): bool {
		$candidate_id = trim( $candidate_id );
		if ( $record_id <= 0 || '' === $candidate_id ) {
			return false;
		}

		$key     = self::ensure_key( $record_id );
		$records = '' === $key ? array( $record_id ) : self::records_for_key( $key, $record_id );
		$ids     = self::giig_ids( $records );
		if ( array() !== $ids && ( 1 !== count( $ids ) || $candidate_id !== $ids[0] ) ) {
			if ( ! in_array( $candidate_id, $ids, true ) ) {
				self::remember_conflicting_giig_id( $record_id, $candidate_id );
			}
			self::flag_review( $records, 'giig_id_collision' );
			return false;
		}

		foreach ( $records as $candidate_record ) {
			$existing = trim( (string) get_post_meta( $candidate_record, self::GIIG_META, true ) );
			if ( '' === $existing ) {
				update_post_meta( $candidate_record, self::GIIG_META, $candidate_id );
			}
		}
		delete_post_meta( $record_id, self::CONFLICTING_GIIG_META );
		return true;
	}

	/**
	 * Safely update editable contact email. A duplicate is allowed as contact
	 * data but is flagged for review; keys and Giig ids are never changed.
	 */
	public static function update_profile_email( int $record_id, string $email ): string {
		$normalized = self::normalize_email( $email );
		if ( $record_id <= 0 || '' === $normalized || false === filter_var( $normalized, FILTER_VALIDATE_EMAIL ) ) {
			return self::EMAIL_INVALID;
		}

		$key        = self::ensure_key( $record_id );
		$previous   = self::normalized_email( $record_id );
		$collisions = array_values(
			array_filter(
				self::records_for_email( $normalized, $record_id ),
				static function ( int $candidate_record ) use ( $key ): bool {
					$other_key = self::key( $candidate_record );
					return '' === $key || '' === $other_key || ! hash_equals( $key, $other_key );
				}
			)
		);

		update_post_meta( $record_id, 'applicant_email', $normalized );
		update_post_meta( $record_id, self::EMAIL_META, $normalized );
		if ( array() !== $collisions ) {
			$affected = array_values( array_unique( array_merge( array( $record_id ), $collisions ) ) );
			self::flag_review( $affected, 'email_collision' );
			/**
			 * Fires when an editable email now also belongs to another identity.
			 *
			 * @param int[]  $affected Affected application record ids.
			 * @param string $email Normalized colliding email address.
			 */
			do_action( 'poolhall_candidate_identity_email_collision', $affected, $normalized );
			return self::EMAIL_COLLISION;
		}

		return $previous === $normalized ? self::EMAIL_UNCHANGED : self::EMAIL_UPDATED;
	}

	/**
	 * Establish one deterministic workflow-owning record for a candidate key.
	 * Existing valid ownership wins; otherwise the oldest workflow-bearing
	 * record wins, falling back to the oldest record id.
	 */
	public static function workflow_owner( int $record_id ): int {
		if ( $record_id <= 0 ) {
			return 0;
		}

		$key = self::ensure_key( $record_id );
		if ( '' === $key ) {
			return 0;
		}
		$records = self::records_for_key( $key, $record_id );
		$owners  = array();
		foreach ( $records as $candidate_record ) {
			$owner = (int) get_post_meta( $candidate_record, self::OWNER_META, true );
			if ( in_array( $owner, $records, true ) ) {
				$owners[] = $owner;
			}
		}
		$owners = array_values( array_unique( $owners ) );

		if ( 1 === count( $owners ) ) {
			$owner = $owners[0];
		} else {
			if ( count( $owners ) > 1 ) {
				self::flag_review( $records, 'workflow_owner_collision' );
			}
			$workflow_records = array_values( array_filter( $records, array( self::class, 'has_workflow' ) ) );
			if ( count( $workflow_records ) > 1 ) {
				self::flag_review( $workflow_records, 'multiple_workflow_records' );
			}
			$owner = min( array() !== $workflow_records ? $workflow_records : $records );
		}

		foreach ( $records as $candidate_record ) {
			update_post_meta( $candidate_record, self::OWNER_META, (string) $owner );
		}
		return $owner;
	}

	public static function is_workflow_owner( int $record_id ): bool {
		return 0 < $record_id && self::workflow_owner( $record_id ) === $record_id;
	}

	/**
	 * Explicitly join a new submission to an existing identity after a staff
	 * member has reviewed both records. Exact normalized email is a discovery
	 * safeguard, not the authority: the authenticated admin action is what
	 * permits the merge. Existing conflicting Giig IDs remain intact and are
	 * flagged for manual ATS reconciliation.
	 */
	public static function merge_after_staff_review( int $record_id, int $target_id ): bool {
		if ( $record_id <= 0 || $target_id <= 0 || $record_id === $target_id ) {
			return false;
		}
		if ( ApplicationRecord::POST_TYPE !== get_post_type( $record_id ) || ApplicationRecord::POST_TYPE !== get_post_type( $target_id ) ) {
			return false;
		}
		$email = self::normalized_email( $record_id );
		if ( '' === $email || ! hash_equals( $email, self::normalized_email( $target_id ) ) ) {
			return false;
		}

		// Never absorb a second live workflow. This operation is deliberately
		// limited to an untouched/new activity record.
		$stage = (string) get_post_meta( $record_id, 'workflow_stage', true );
		if ( '' !== $stage && 'new' !== $stage ) {
			return false;
		}
		foreach ( array( 'full', 'contract', 'terms', 'starter', 'payroll' ) as $kind ) {
			$status = (string) get_post_meta( $record_id, 'task_' . $kind . '_status', true );
			if ( ! in_array( $status, array( '', 'not_required' ), true ) ) {
				return false;
			}
		}

		$target_key = self::ensure_key( $target_id );
		if ( '' === $target_key ) {
			return false;
		}
		$target_owner = self::workflow_owner( $target_id );
		if ( $target_owner <= 0 ) {
			return false;
		}
		$target_stage = (string) get_post_meta( $target_owner, 'workflow_stage', true );
		// A returning candidate must be able to begin a new recruitment cycle.
		// Reuse an actually active workflow; otherwise make this fresh submission
		// the owner while retaining the earlier completed record as history.
		$new_owner = in_array( $target_stage, array( 'ready', 'closed' ), true ) ? $record_id : $target_owner;

		update_post_meta( $record_id, self::KEY_META, $target_key );
		update_post_meta( $record_id, self::KEY_SOURCE_META, 'staff_review' );
		$linked_records = self::records_for_key( $target_key, $record_id );
		foreach ( $linked_records as $linked_record ) {
			update_post_meta( $linked_record, self::OWNER_META, (string) $new_owner );
		}
		update_post_meta( $record_id, 'candidate_merged_into', (string) $new_owner );
		update_post_meta( $record_id, 'candidate_merged_at', gmdate( \DateTimeInterface::ATOM ) );
		update_post_meta( $record_id, 'candidate_merged_by', (string) get_current_user_id() );

		self::workflow_owner( $new_owner );
		$resolved_giig = self::giig_id( $new_owner );
		if ( '' !== $resolved_giig ) {
			self::remember_giig_id( $new_owner, $resolved_giig );
		}
		return true;
	}

	public static function review_required( int $record_id ): bool {
		return '1' === (string) get_post_meta( $record_id, self::REVIEW_META, true );
	}

	public static function review_reasons( int $record_id ): array {
		$stored = (string) get_post_meta( $record_id, self::REVIEW_REASON_META, true );
		return '' === $stored ? array() : array_values( array_filter( explode( ',', $stored ) ) );
	}

	public static function normalize_email( string $email ): string {
		return strtolower( trim( sanitize_email( $email ) ) );
	}

	private static function normalized_email( int $record_id ): string {
		$normalized = self::normalize_email( (string) get_post_meta( $record_id, self::EMAIL_META, true ) );
		return '' !== $normalized ? $normalized : self::normalize_email( (string) get_post_meta( $record_id, 'applicant_email', true ) );
	}

	private static function ensure_key( int $record_id ): string {
		$key = self::key( $record_id );
		if ( '' !== $key || $record_id <= 0 ) {
			return $key;
		}
		$key = wp_generate_uuid4();
		update_post_meta( $record_id, self::KEY_META, $key );
		update_post_meta( $record_id, self::KEY_SOURCE_META, 'record' );
		return $key;
	}

	/** @return int[] */
	private static function records_for_key( string $key, int $include_record = 0 ): array {
		if ( '' === $key ) {
			return 0 < $include_record ? array( $include_record ) : array();
		}
		$records = get_posts(
			array(
				'post_type'      => ApplicationRecord::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- identity reconciliation requires the complete key group.
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$ids     = array_map( 'intval', $records );
		if ( 0 < $include_record ) {
			$ids[] = $include_record;
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	/** @return int[] */
	private static function records_for_email( string $normalized, int $exclude ): array {
		$records = get_posts(
			array(
				'post_type'      => ApplicationRecord::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'exclude'        => array( $exclude ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- collision prevention requires both legacy and normalized contact values.
					'relation' => 'OR',
					array(
						'key'   => self::EMAIL_META,
						'value' => $normalized,
					),
					array(
						'key'   => 'applicant_email',
						'value' => $normalized,
					),
				),
			)
		);
		return array_values( array_unique( array_filter( array_map( 'intval', $records ) ) ) );
	}

	/** @param int[] $records @return string[] */
	private static function giig_ids( array $records ): array {
		$ids = array();
		foreach ( $records as $candidate_record ) {
			$candidate_id = trim( (string) get_post_meta( $candidate_record, self::GIIG_META, true ) );
			if ( '' !== $candidate_id ) {
				$ids[] = $candidate_id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids, SORT_STRING );
		return $ids;
	}

	private static function has_workflow( int $record_id ): bool {
		if ( '' !== (string) get_post_meta( $record_id, 'workflow_stage', true ) ) {
			return true;
		}
		foreach ( array( 'full', 'contract', 'terms', 'starter', 'payroll' ) as $kind ) {
			if ( '' !== (string) get_post_meta( $record_id, 'task_' . $kind . '_status', true ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param int[] $records */
	private static function flag_review( array $records, string $reason ): void {
		$records = array_values( array_unique( array_filter( array_map( 'intval', $records ) ) ) );
		$reason  = sanitize_key( $reason );
		foreach ( $records as $candidate_record ) {
			$reasons   = self::review_reasons( $candidate_record );
			$reasons[] = $reason;
			$reasons   = array_values( array_unique( array_filter( $reasons ) ) );
			sort( $reasons, SORT_STRING );
			update_post_meta( $candidate_record, self::REVIEW_META, '1' );
			update_post_meta( $candidate_record, self::REVIEW_REASON_META, implode( ',', $reasons ) );
			update_post_meta( $candidate_record, 'candidate_identity_review_at', gmdate( \DateTimeInterface::ATOM ) );
		}

		/**
		 * Fires whenever automatic identity reconciliation is deliberately stopped.
		 *
		 * @param int[]  $records Affected application record ids.
		 * @param string $reason Machine-readable collision reason.
		 */
		do_action( 'poolhall_candidate_identity_review_required', $records, $reason );
	}

	private static function remember_conflicting_giig_id( int $record_id, string $candidate_id ): void {
		$ids   = array_filter( explode( ',', (string) get_post_meta( $record_id, self::CONFLICTING_GIIG_META, true ) ) );
		$ids[] = $candidate_id;
		$ids   = array_values( array_unique( array_map( 'trim', $ids ) ) );
		sort( $ids, SORT_STRING );
		update_post_meta( $record_id, self::CONFLICTING_GIIG_META, implode( ',', $ids ) );
	}
}
