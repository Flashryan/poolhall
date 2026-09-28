<?php
/**
 * Tokens, hashing and reversible encryption for re-displayable links.
 *
 * Access tokens are 256-bit random values. Only their SHA-256 hash is used to
 * look them up, so a database leak does not reveal working links. Share and
 * return links are additionally stored encrypted (libsodium secretbox, key
 * derived from the site's AUTH salt) so a manager can copy them again. If the
 * salts change, stored links can no longer be displayed but still work.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	public static function token( int $bytes = 32 ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}

	public static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	public static function is_token( string $value ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9_-]{32,64}$/', $value );
	}

	public static function uuid(): string {
		return wp_generate_uuid4();
	}

	private static function key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . '|mna-feedback', true );
	}

	public static function encrypt( string $plain ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) );
	}

	public static function decrypt( ?string $encoded ): ?string {
		if ( ! $encoded ) {
			return null;
		}
		$raw = base64_decode( $encoded, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		try {
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
		} catch ( \SodiumException $e ) {
			return null;
		}
		return false === $plain ? null : $plain;
	}
}
