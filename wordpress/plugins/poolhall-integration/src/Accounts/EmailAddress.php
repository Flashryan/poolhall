<?php
/**
 * Email validation and normalization.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Pure email helpers (portal spec §4: validate and normalize email).
 * Lowercasing the whole address is deliberate: case-sensitive local parts
 * are legal but treating them as distinct accounts only enables duplicate
 * registrations for the same inbox.
 */
final class EmailAddress {

	private const MAX_LENGTH = 254;

	public static function normalize( string $email ): string {
		return strtolower( trim( $email ) );
	}

	public static function is_valid( string $email ): bool {
		$email = self::normalize( $email );
		if ( '' === $email || strlen( $email ) > self::MAX_LENGTH ) {
			return false;
		}
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/** Local part of a normalized address, '' when the address is malformed. */
	public static function local_part( string $email ): string {
		$email = self::normalize( $email );
		$at    = strrpos( $email, '@' );
		return false === $at ? '' : substr( $email, 0, $at );
	}
}
