<?php
/**
 * Candidate password policy.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Portal spec §4: 12-128 characters, spaces/Unicode/paste allowed, no
 * arbitrary character-class rules, reject known/common compromised choices
 * without sending the raw password to a third party. The denylist is a small
 * embedded set of the common >=12-char choices; a fuller offline k-anonymity
 * check can be layered on later without changing callers.
 */
final class PasswordPolicy {

	public const MIN_LENGTH = 12;
	public const MAX_LENGTH = 128;

	/** Lowercase, compared after trimming; all entries are >= MIN_LENGTH. */
	private const COMMON = array(
		'password1234',
		'password12345',
		'password123456',
		'1234567890123',
		'123456789012',
		'12345678901234',
		'qwertyuiop123',
		'qwertyuiopasdf',
		'iloveyou1234',
		'administrator',
		'letmein123456',
		'welcome123456',
		'changeme12345',
		'poolhall12345',
	);

	/**
	 * Error codes for a candidate password, empty array when acceptable.
	 * Codes: too_short, too_long, common_password, low_variety,
	 * contains_email.
	 *
	 * @return string[]
	 */
	public function validate( string $password, string $email = '' ): array {
		$errors = array();
		$length = mb_strlen( $password );

		if ( $length < self::MIN_LENGTH ) {
			$errors[] = 'too_short';
			return $errors; // Too short to evaluate anything else meaningfully.
		}
		if ( $length > self::MAX_LENGTH ) {
			$errors[] = 'too_long';
		}

		$lower = mb_strtolower( trim( $password ) );
		if ( in_array( $lower, self::COMMON, true ) ) {
			$errors[] = 'common_password';
		}

		// A strength floor without character-class rules: a 12+ character
		// string built from under five distinct characters is a keyboard
		// mash or repetition, not a passphrase.
		$distinct = count( array_unique( mb_str_split( $lower ) ) );
		if ( $distinct < 5 ) {
			$errors[] = 'low_variety';
		}

		$local = EmailAddress::local_part( $email );
		if ( strlen( $local ) >= 4 && str_contains( $lower, strtolower( $local ) ) ) {
			$errors[] = 'contains_email';
		}

		return $errors;
	}
}
