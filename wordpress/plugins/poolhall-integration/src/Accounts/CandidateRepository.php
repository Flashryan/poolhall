<?php
/**
 * Candidate account storage.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * All reads/writes of candidate users and their account meta. Verification
 * tokens are stored hash-only and deleted on redemption (single use);
 * consent records keep the accepted document versions and timestamp
 * (portal spec §4).
 */
final class CandidateRepository {

	public const META_VERIFIED_AT  = 'poolhall_verified_at';
	public const META_TOKEN_HASH   = 'poolhall_verify_token_hash';
	public const META_TOKEN_ISSUED = 'poolhall_verify_token_issued_at';
	public const META_VERIFY_SENDS = 'poolhall_verify_sends';
	public const META_CONSENT      = 'poolhall_consent';

	public function find_by_email( string $email ): ?\WP_User {
		$user = get_user_by( 'email', EmailAddress::normalize( $email ) );
		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Create an unverified candidate with an internal non-email username
	 * (portal spec §4 — candidates never surface a public login name).
	 *
	 * @throws \RuntimeException When WordPress refuses the insert.
	 */
	public function create_unverified( string $email, string $password, string $first_name, string $last_name ): int {
		$username = $this->generate_username();

		$result = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => $password,
				'user_email'   => EmailAddress::normalize( $email ),
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'role'         => CandidateRole::ROLE,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( 'Failed to create candidate: ' . $result->get_error_message() );
		}
		return (int) $result;
	}

	/**
	 * @param array{terms_version:string,privacy_version:string,alert_consent:bool} $consent Consent fields.
	 */
	public function record_consent( int $user_id, array $consent, \DateTimeImmutable $accepted_at ): void {
		update_user_meta(
			$user_id,
			self::META_CONSENT,
			wp_json_encode(
				array(
					'terms_version'   => $consent['terms_version'],
					'privacy_version' => $consent['privacy_version'],
					'alert_consent'   => $consent['alert_consent'],
					'accepted_at'     => $accepted_at->format( \DateTimeInterface::ATOM ),
				)
			)
		);
	}

	public function is_verified( int $user_id ): bool {
		return '' !== (string) get_user_meta( $user_id, self::META_VERIFIED_AT, true );
	}

	/** Storing a new token hash invalidates any previous verification link. */
	public function store_verification_token( int $user_id, string $token_hash, \DateTimeImmutable $issued_at ): void {
		update_user_meta( $user_id, self::META_TOKEN_HASH, $token_hash );
		update_user_meta( $user_id, self::META_TOKEN_ISSUED, $issued_at->format( \DateTimeInterface::ATOM ) );
	}

	/** The unverified candidate holding this token hash, if any. */
	public function find_by_token_hash( string $token_hash ): ?int {
		if ( '' === $token_hash ) {
			return null;
		}
		$query = new \WP_User_Query(
			array(
				'role'       => CandidateRole::ROLE,
				'number'     => 1,
				'fields'     => 'ID',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- single keyed lookup.
					array(
						'key'   => self::META_TOKEN_HASH,
						'value' => $token_hash,
					),
				),
			)
		);
		$ids   = $query->get_results();
		return array() === $ids ? null : (int) $ids[0];
	}

	public function token_issued_at( int $user_id ): ?\DateTimeImmutable {
		$raw = (string) get_user_meta( $user_id, self::META_TOKEN_ISSUED, true );
		return '' === $raw ? null : new \DateTimeImmutable( $raw );
	}

	/**
	 * Single use: redeeming deletes the hash so the link can never replay.
	 * All existing sessions are revoked so anything authenticated before
	 * verification rotates (portal spec §4).
	 */
	public function mark_verified( int $user_id, \DateTimeImmutable $now ): void {
		update_user_meta( $user_id, self::META_VERIFIED_AT, $now->format( \DateTimeInterface::ATOM ) );
		delete_user_meta( $user_id, self::META_TOKEN_HASH );
		delete_user_meta( $user_id, self::META_TOKEN_ISSUED );
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
	}

	/** @return \DateTimeImmutable[] */
	public function verification_sends( int $user_id ): array {
		$raw     = (string) get_user_meta( $user_id, self::META_VERIFY_SENDS, true );
		$decoded = '' === $raw ? array() : (array) json_decode( $raw, true );
		$sends   = array();
		foreach ( $decoded as $timestamp ) {
			if ( is_string( $timestamp ) ) {
				$sends[] = new \DateTimeImmutable( $timestamp );
			}
		}
		return $sends;
	}

	/**
	 * @param \DateTimeImmutable[] $sends Full pruned history including the new send.
	 */
	public function record_verification_sends( int $user_id, array $sends ): void {
		update_user_meta(
			$user_id,
			self::META_VERIFY_SENDS,
			wp_json_encode(
				array_map(
					static fn( \DateTimeImmutable $sent_at ): string => $sent_at->format( \DateTimeInterface::ATOM ),
					$sends
				)
			)
		);
	}

	/** Internal, collision-checked, never derived from the email address. */
	private function generate_username(): string {
		do {
			$username = 'ph_c_' . bin2hex( random_bytes( 6 ) );
		} while ( false !== username_exists( $username ) );
		return $username;
	}
}
