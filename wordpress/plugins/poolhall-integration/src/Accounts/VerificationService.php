<?php
/**
 * Email verification.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Support\Logger;

/**
 * Redeems single-use verification tokens and handles generic resends
 * (portal spec §4). Failure responses never distinguish unknown tokens
 * from tokens belonging to someone else.
 */
final class VerificationService {

	public function __construct(
		private CandidateRepository $candidates,
		private TokenPolicy $tokens,
		private ResendPolicy $resends,
		private RegistrationService $registration,
		private Logger $logger,
	) {}

	/**
	 * @return array{status:'verified'|'expired'|'invalid'}
	 */
	public function verify( string $token, \DateTimeImmutable $now ): array {
		if ( ! $this->tokens->is_well_formed( $token ) ) {
			return array( 'status' => 'invalid' );
		}

		$user_id = $this->candidates->find_by_token_hash( $this->tokens->hash_token( $token ) );
		if ( null === $user_id || $this->candidates->is_verified( $user_id ) ) {
			return array( 'status' => 'invalid' );
		}

		$issued_at = $this->candidates->token_issued_at( $user_id );
		if ( null === $issued_at || $this->tokens->is_expired( $issued_at, $now, TokenPolicy::VERIFY_TTL_SECONDS ) ) {
			return array( 'status' => 'expired' );
		}

		$this->candidates->mark_verified( $user_id, $now );
		$this->logger->log( 'candidate_verified', array( 'user_id' => $user_id ) );

		return array( 'status' => 'verified' );
	}

	/**
	 * Generic by design: the response is identical whether the address is
	 * unknown, already verified or rate-limited.
	 *
	 * @return array{status:'check_email'}
	 */
	public function resend( string $email, \DateTimeImmutable $now ): array {
		$user = $this->candidates->find_by_email( $email );

		if ( null !== $user && CandidateRole::is_candidate( $user ) && ! $this->candidates->is_verified( (int) $user->ID ) ) {
			$reason = $this->resends->denial_reason( $this->candidates->verification_sends( (int) $user->ID ), $now );
			if ( '' === $reason ) {
				$this->registration->send_verification( (int) $user->ID, (string) $user->user_email, $now );
			} else {
				$this->logger->log(
					'candidate_resend_limited',
					array(
						'user_id' => (int) $user->ID,
						'reason'  => $reason,
					)
				);
			}
		}

		return array( 'status' => 'check_email' );
	}
}
