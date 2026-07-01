<?php
/**
 * Export a verified candidate to the ATS (Giig), never losing the record.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Source\CandidatePayload;
use Poolhall\Integration\Source\CandidateSink;
use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Support\Logger;

/**
 * Pushes a candidate's registration details into Giig once their email is
 * verified (so only real, confirmed candidates reach the ATS). Runs on the
 * `poolhall_candidate_verified` action.
 *
 * Never lose a registration: on success the returned CandidateId is stored;
 * on any failure (including no live Giig base URL yet) the record stays
 * flagged pending and Poolhall is emailed the candidate's details as the
 * interim channel, so a real lead is never dropped while the live endpoint
 * is being confirmed.
 */
final class CandidateExporter {

	public const INBOX_OPTION = 'poolhall_candidate_inbox';

	public function __construct(
		private CandidateRepository $candidates,
		private CandidateSink $sink,
		private Mailer $mailer,
		private Logger $logger,
	) {}

	/** @return array{status:string} */
	public function export( int $user_id ): array {
		if ( '' !== $this->candidates->giig_candidate_id( $user_id ) ) {
			return array( 'status' => 'already_exported' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! CandidateRole::is_candidate( $user ) ) {
			return array( 'status' => 'not_candidate' );
		}

		$payload = $this->payload_for( $user );
		if ( ! $payload->is_valid() ) {
			$this->logger->log( 'candidate_export_skipped', array( 'user_id' => $user_id ) );
			return array( 'status' => 'invalid' );
		}

		try {
			$result = $this->sink->register_candidate( $payload );
			$this->candidates->set_giig_candidate_id( $user_id, $result->source_candidate_id );
			$this->candidates->clear_export_pending( $user_id );
			$this->logger->log( 'candidate_exported', array( 'user_id' => $user_id ) );
			return array( 'status' => 'exported' );
		} catch ( SourceException $e ) {
			// Keep the record flagged and route the lead to Poolhall by email
			// so it is never lost while the live Giig endpoint is confirmed.
			$this->candidates->mark_export_pending( $user_id );
			$this->notify( $payload );
			$this->logger->log(
				'candidate_export_deferred',
				array(
					'user_id' => $user_id,
					'reason'  => $e->getMessage(),
				)
			);
			return array( 'status' => 'deferred' );
		}
	}

	private function payload_for( \WP_User $user ): CandidatePayload {
		$profile = $this->candidates->profile( (int) $user->ID );

		return new CandidatePayload(
			(string) $user->first_name,
			(string) $user->last_name,
			(string) $user->user_email,
			$profile['phone'] ?? '',
			$profile['role_title'] ?? '',
			$profile['location'] ?? '',
			$profile['salary_expectations'] ?? '',
			$profile['linkedin'] ?? '',
			$profile['summary'] ?? '',
			'Website'
		);
	}

	private function notify( CandidatePayload $payload ): void {
		$inbox = (string) get_option( self::INBOX_OPTION, '' );
		if ( '' === $inbox ) {
			$inbox = (string) get_option( 'admin_email' );
		}
		/**
		 * Destination inbox for candidate registration notifications.
		 *
		 * @param string $inbox Email address.
		 */
		$inbox = (string) apply_filters( 'poolhall_candidate_inbox', $inbox );
		if ( '' === $inbox ) {
			return;
		}

		$lines = array(
			'Name: ' . trim( $payload->first_name . ' ' . $payload->last_name ),
			'Email: ' . $payload->email,
			'Phone: ' . ( '' !== $payload->phone ? $payload->phone : '(none)' ),
			'Desired role: ' . ( '' !== $payload->role_title ? $payload->role_title : '(none)' ),
			'Location: ' . ( '' !== $payload->location ? $payload->location : '(none)' ),
			'Salary expectations: ' . ( '' !== $payload->salary_expectations ? $payload->salary_expectations : '(none)' ),
			'LinkedIn: ' . ( '' !== $payload->linkedin ? $payload->linkedin : '(none)' ),
			'',
			'Notes:',
			'' !== $payload->notes ? $payload->notes : '(none)',
			'',
			'This candidate verified their email but could not be pushed to Giig automatically (the live Giig endpoint is not configured yet). Their details are above and stored in the site; they will sync once Giig is connected.',
		);

		$this->mailer->send(
			$inbox,
			sprintf(
				/* translators: %s: candidate name. */
				__( 'New candidate registered: %s', 'poolhall-integration' ),
				trim( $payload->first_name . ' ' . $payload->last_name )
			),
			implode( "\n", $lines )
		);
	}
}
