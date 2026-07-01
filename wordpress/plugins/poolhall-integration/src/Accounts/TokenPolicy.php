<?php
/**
 * Single-use account token rules.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Verification/reset tokens (portal spec §4-5): random, high entropy,
 * stored only as a hash, single use, fixed expiry windows. Single-use is
 * enforced by the repository deleting the hash on redemption; this class
 * owns generation, hashing and expiry math so those rules are unit-tested.
 */
final class TokenPolicy {

	public const VERIFY_TTL_SECONDS = 24 * 60 * 60;
	public const RESET_TTL_SECONDS  = 60 * 60;
	/** Email-change confirmation mirrors registration verification (spec §5). */
	public const EMAIL_CHANGE_TTL_SECONDS = 24 * 60 * 60;

	/** 32 random bytes as 64 hex chars. */
	public function generate(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/** Only this hash is ever persisted; the raw token goes in the email link. */
	public function hash_token( string $token ): string {
		return hash( 'sha256', $token );
	}

	/** Constant-time comparison of a presented token against the stored hash. */
	public function matches( string $token, string $stored_hash ): bool {
		if ( ! $this->is_well_formed( $token ) || '' === $stored_hash ) {
			return false;
		}
		return hash_equals( $stored_hash, $this->hash_token( $token ) );
	}

	public function is_well_formed( string $token ): bool {
		return 1 === preg_match( '/^[0-9a-f]{64}$/', $token );
	}

	public function is_expired( \DateTimeImmutable $issued_at, \DateTimeImmutable $now, int $ttl_seconds ): bool {
		return $now->getTimestamp() >= $issued_at->getTimestamp() + $ttl_seconds;
	}
}
