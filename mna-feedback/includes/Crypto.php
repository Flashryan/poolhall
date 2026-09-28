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
 * Screenshots are sealed with a separate random per-site key kept in the
 * options table, so files are unreadable even on web servers that ignore the
 * storage folder's .htaccess rules (nginx), and a salt rotation never makes
 * them unrecoverable.
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

	private const FILE_MAGIC = "MNAFB1\0";

	private static ?string $file_key = null;

	/**
	 * The per-site file key, created atomically on first use.
	 */
	public static function file_key(): string {
		if ( null !== self::$file_key ) {
			return self::$file_key;
		}
		global $wpdb;
		$read = static fn() => $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'mnafb_file_key' ) );
		$stored = $read();
		if ( ! $stored ) {
			// INSERT IGNORE is atomic: if two uploads race, both end up using the winner's key.
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", 'mnafb_file_key', base64_encode( random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) ) ) );
			wp_cache_delete( 'mnafb_file_key', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$stored = $read();
		}
		$key = base64_decode( (string) $stored, true );
		if ( false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen( $key ) ) {
			throw new \RuntimeException( 'MNA Feedback file key is unavailable.' );
		}
		self::$file_key = $key;
		return $key;
	}

	/**
	 * Encrypts file contents for storage.
	 */
	public static function seal( string $plain ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return self::FILE_MAGIC . $nonce . sodium_crypto_secretbox( $plain, $nonce, self::file_key() );
	}

	/**
	 * Decrypts stored file contents. Files written before encryption was
	 * introduced are returned as they are. Returns null if the data cannot be
	 * decrypted.
	 */
	public static function unseal( string $stored ): ?string {
		if ( ! str_starts_with( $stored, self::FILE_MAGIC ) ) {
			return $stored;
		}
		$offset = strlen( self::FILE_MAGIC );
		$nonce  = substr( $stored, $offset, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $stored, $offset + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		try {
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, self::file_key() );
		} catch ( \SodiumException $e ) {
			return null;
		}
		return false === $plain ? null : $plain;
	}

	/** Forgets the cached key (after a purge). */
	public static function reset(): void {
		self::$file_key = null;
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
