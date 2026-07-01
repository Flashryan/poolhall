<?php
/**
 * Candidate application request normalization + validation.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Accounts\EmailAddress;

/**
 * A normalized candidate application captured by the first-party apply
 * popup (build reference: applypopupjourney.html). Pure domain logic — no
 * WordPress, no file handling — so the field rules are unit-tested. The CV
 * file and the job post are validated separately (CvUpload + the endpoint);
 * this object owns the text fields and consent.
 */
final class ApplicationRequest {

	private const MAX_NAME    = 100;
	private const MAX_PHONE   = 40;
	private const MAX_MESSAGE = 4000;

	public function __construct(
		public readonly string $first_name,
		public readonly string $last_name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $message,
		public readonly bool $consented
	) {
	}

	/**
	 * @param array<string,mixed> $fields Raw POST fields (already unslashed).
	 */
	public static function from_post( array $fields ): self {
		return new self(
			self::clean( $fields['first_name'] ?? '', self::MAX_NAME ),
			self::clean( $fields['last_name'] ?? '', self::MAX_NAME ),
			EmailAddress::normalize( is_string( $fields['email'] ?? null ) ? $fields['email'] : '' ),
			self::clean( $fields['phone'] ?? '', self::MAX_PHONE ),
			self::clean_multiline( $fields['message'] ?? '', self::MAX_MESSAGE ),
			'1' === ( $fields['consent'] ?? '' )
		);
	}

	/**
	 * @return string[] Error codes; empty when the text fields are valid.
	 */
	public function validation_errors(): array {
		$errors = array();
		if ( '' === $this->first_name ) {
			$errors[] = 'first_name_required';
		}
		if ( '' === $this->last_name ) {
			$errors[] = 'last_name_required';
		}
		if ( ! EmailAddress::is_valid( $this->email ) ) {
			$errors[] = 'email_invalid';
		}
		if ( '' === $this->phone ) {
			$errors[] = 'phone_required';
		}
		if ( ! $this->consented ) {
			$errors[] = 'consent_required';
		}
		return $errors;
	}

	public function full_name(): string {
		return trim( $this->first_name . ' ' . $this->last_name );
	}

	private static function clean( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure class, no WP loaded in unit tests.
		$value = trim( (string) preg_replace( '/[ \t]+/u', ' ', $value ) );

		return mb_substr( $value, 0, $max );
	}

	private static function clean_multiline( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure class, no WP loaded in unit tests.
		$value = trim( $value );

		return mb_substr( $value, 0, $max );
	}
}
