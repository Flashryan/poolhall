<?php
/**
 * One-time migration from the original single invite/review status model.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Applications\CandidateIdentity;
use Poolhall\Integration\Support\PrivateStorage;

/** Converts legacy candidate records without trusting obsolete bearer links. */
final class WorkflowMigration {

	/** Migrate every candidate record that has not entered the task-state model. */
	public static function run(): int {
		self::initialize_contract_task();
		$migrated = 0;
		$batches  = 0;
		do {
			++$batches;
			if ( $batches > 100 ) {
				throw new \RuntimeException( 'Candidate workflow migration did not converge.' );
			}
			$records = get_posts(
				array(
					'post_type'      => 'poolhall_application',
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-time bounded migration.
						'relation' => 'AND',
					// Early application records predate the kind marker but always
					// carry the validated applicant email captured by either route.
					array(
						'key'     => 'applicant_email',
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => 'workflow_stage',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'   => 'workflow_stage',
							'value' => '',
						),
					),
					),
				)
			);

			foreach ( $records as $record ) {
				self::migrate_record( (int) $record );
				if ( '' === (string) get_post_meta( (int) $record, 'workflow_stage', true ) ) {
					throw new \RuntimeException( 'A candidate workflow record could not be migrated.' );
				}
				++$migrated;
			}
			$record_count = count( $records );
		} while ( 100 === $record_count );

		return $migrated;
	}

	/**
	 * Existing live workflows predate the contract task. Do not add a new legal
	 * obligation retrospectively; initialise it as not required and let the next
	 * accepted-offer transition opt in according to engagement type.
	 */
	private static function initialize_contract_task(): void {
		$batches = 0;
		do {
			++$batches;
			if ( $batches > 100 ) {
				throw new \RuntimeException( 'Agency Worker Contract task migration did not converge.' );
			}
			$records = get_posts(
				array(
					'post_type'      => 'poolhall_application',
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-time bounded migration.
						array(
							'key'     => 'task_contract_status',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			$now     = gmdate( \DateTimeInterface::ATOM );
			foreach ( $records as $record_id ) {
				update_post_meta( (int) $record_id, 'task_contract_status', 'not_required' );
				update_post_meta( (int) $record_id, 'task_contract_updated_at', $now );
			}
			$count = count( $records );
		} while ( 100 === $count );
	}

	private static function migrate_record( int $record_id ): void {
		$kind = sanitize_key( (string) get_post_meta( $record_id, 'kind', true ) );
		if ( ! in_array( $kind, array( 'registration', 'job_application' ), true ) ) {
			$kind = '' !== (string) get_post_meta( $record_id, 'source_job_id', true )
				? 'job_application'
				: 'registration';
			update_post_meta( $record_id, 'kind', $kind );
		}
		$legacy = sanitize_key( (string) get_post_meta( $record_id, 'review_status', true ) );
		$stage  = 'new';
		$full   = 'not_required';
		$mail   = '';

		switch ( $legacy ) {
			case 'invite_sent':
			case 'invite_failed':
			case 'profile_sent':
				$stage = 'awaiting_profile';
				$full  = 'awaiting';
				// Old links cannot be authenticated by the new model. Make the
				// cockpit explicitly prompt a recruiter to issue a fresh link.
				$mail = 'failed';
				break;
			case 'completed':
				$stage = 'registration_review';
				$full  = 'submitted';
				break;
			case 'approved':
				$stage = 'registered';
				$full  = 'accepted';
				break;
			case 'rejected':
				$stage = 'closed';
				break;
		}

		foreach ( WorkflowState::TASKS as $kind ) {
			$status = 'full' === $kind ? $full : 'not_required';
			update_post_meta( $record_id, 'task_' . $kind . '_status', $status );
			update_post_meta( $record_id, 'task_' . $kind . '_updated_at', gmdate( \DateTimeInterface::ATOM ) );
		}
		if ( '' !== $mail ) {
			update_post_meta( $record_id, 'task_full_mail_status', $mail );
			update_post_meta( $record_id, 'task_full_mail_attempted_at', gmdate( \DateTimeInterface::ATOM ) );
		}
		update_post_meta( $record_id, 'workflow_stage', $stage );
		update_post_meta( $record_id, 'workflow_stage_started_at', gmdate( \DateTimeInterface::ATOM ) );
		update_post_meta( $record_id, 'workflow_migrated_from', '' !== $legacy ? $legacy : 'new' );
		CandidateIdentity::attach( $record_id, (string) get_post_meta( $record_id, 'applicant_email', true ) );
		self::migrate_cv( $record_id );

		foreach ( array( 'invite_hash', 'invite_expires', 'invite_kinds', 'invite_used' ) as $meta ) {
			delete_post_meta( $record_id, $meta );
		}
	}

	/** Move legacy deny-by-.htaccess CVs outside the public document root. */
	private static function migrate_cv( int $record_id ): void {
		$stored = (string) get_post_meta( $record_id, 'cv_path', true );
		$source = '' !== $stored ? realpath( $stored ) : false;
		if ( false === $source || ! is_file( $source ) ) {
			return;
		}

		$target = '';
		try {
			$directory = PrivateStorage::directory( 'cvs' );
			$prefix    = rtrim( str_replace( '\\', '/', $directory ), '/' ) . '/';
			$normal    = str_replace( '\\', '/', $source );
			if ( str_starts_with( $normal, $prefix ) ) {
				update_post_meta( $record_id, 'cv_storage_status', 'private' );
				return;
			}

			$extension = sanitize_key( (string) pathinfo( $source, PATHINFO_EXTENSION ) );
			$target    = PrivateStorage::opaque_path( 'cvs', 'candidate-cv', $extension );
			if ( ! copy( $source, $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- controlled local migration outside the web root.
				throw new \RuntimeException( 'Unable to copy legacy CV.' );
			}
			$target = PrivateStorage::protect_file( $target );
			update_post_meta( $record_id, 'cv_path', $target );
			update_post_meta( $record_id, 'cv_storage_status', 'private' );
			wp_delete_file( $source );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			if ( '' !== $target && is_file( $target ) ) {
				wp_delete_file( $target );
			}
			update_post_meta( $record_id, 'cv_storage_status', 'migration_failed' );
		}
	}
}
