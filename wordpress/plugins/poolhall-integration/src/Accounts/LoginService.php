<?php
/**
 * Candidate login.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Support\Logger;

/**
 * Portal spec §5. Outcomes are deliberately coarse: unknown email, wrong
 * password and non-candidate account all collapse into the same generic
 * `invalid` so the form can never confirm an address exists. The one
 * exception is `verify_required`, returned only after the caller proved
 * ownership with the correct password — pointing the owner to the resend
 * path reveals nothing to a guesser. Only candidate accounts authenticate
 * here; staff keep using wp-login (hard rule 14 keeps Giig credentials out
 * of site login entirely).
 */
final class LoginService {

	public function __construct(
		private CandidateRepository $candidates,
		private LoginRateLimiter $limiter,
		private FailedLoginStore $failures,
		private Logger $logger,
	) {}

	/**
	 * @param array{email?:string,password?:string} $input Submitted fields.
	 * @param string $network_signal Caller-supplied network identifier (remote address).
	 * @return array{status:'ok'|'invalid'|'rate_limited'|'verify_required',user_id:int}
	 */
	public function attempt( array $input, string $network_signal, \DateTimeImmutable $now ): array {
		$email    = EmailAddress::normalize( $input['email'] ?? '' );
		$password = (string) ( $input['password'] ?? '' );

		if ( ! EmailAddress::is_valid( $email ) || '' === $password ) {
			return self::result( 'invalid' );
		}

		// Hashed keys: raw emails and addresses never reach the options table.
		$account_key = 'acct_' . hash( 'sha256', $email );
		$network_key = 'net_' . hash( 'sha256', $network_signal );

		foreach ( array( $account_key, $network_key ) as $key ) {
			if ( '' !== $this->limiter->denial_reason( $this->failures->failures( $key ), $now ) ) {
				$this->logger->log( 'candidate_login_rate_limited', array( 'key_kind' => str_starts_with( $key, 'acct_' ) ? 'account' : 'network' ) );
				return self::result( 'rate_limited' );
			}
		}

		$user  = $this->candidates->find_by_email( $email );
		$valid = $user instanceof \WP_User
			&& CandidateRole::is_candidate( $user )
			&& wp_check_password( $password, (string) $user->user_pass, (int) $user->ID );

		if ( ! $valid ) {
			$this->failures->record_failure( $account_key, $now );
			$this->failures->record_failure( $network_key, $now );
			return self::result( 'invalid' );
		}

		if ( ! $this->candidates->is_verified( (int) $user->ID ) ) {
			return self::result( 'verify_required', (int) $user->ID );
		}

		$this->failures->clear( $account_key );
		$this->logger->log( 'candidate_login', array( 'user_id' => (int) $user->ID ) );
		return self::result( 'ok', (int) $user->ID );
	}

	/**
	 * @return array{status:'ok'|'invalid'|'rate_limited'|'verify_required',user_id:int}
	 */
	private static function result( string $status, int $user_id = 0 ): array {
		return array(
			'status'  => $status,
			'user_id' => $user_id,
		);
	}
}
