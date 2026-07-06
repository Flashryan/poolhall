<?php
/**
 * CV registration request normalization + validation.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Accounts\EmailAddress;

/**
 * A register-your-CV submission (directive §5.1 /register/, §7.4): not a
 * site account, a candidate record bound for Giig. Mirrors the Giig
 * candidate-create surface, so every captured field lands in the ATS
 * without manual re-keying. Pure domain logic; the CV file is validated
 * separately by CvUpload.
 */
final class RegistrationRequest {

	private const MAX_NAME    = 100;
	private const MAX_PHONE   = 40;
	private const MAX_SHORT   = 160;
	private const MAX_MESSAGE = 4000;

	public function __construct(
		public readonly string $first_name,
		public readonly string $last_name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $location,
		public readonly string $role_title,
		public readonly string $salary_expectations,
		public readonly string $linkedin,
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
			self::clean( $fields['location'] ?? '', self::MAX_SHORT ),
			self::clean( $fields['role_title'] ?? '', self::MAX_SHORT ),
			self::clean( $fields['salary_expectations'] ?? '', self::MAX_SHORT ),
			self::clean_url( $fields['linkedin'] ?? '' ),
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
		if ( '' !== $this->linkedin && ! str_starts_with( $this->linkedin, 'https://' ) ) {
			$errors[] = 'linkedin_invalid';
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
		return mb_substr( trim( sanitize_text_field( $value ) ), 0, $max );
	}

	private static function clean_multiline( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return mb_substr( trim( sanitize_textarea_field( $value ) ), 0, $max );
	}

	private static function clean_url( mixed $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		// No protocol allow-list here: a scheme filter would silently blank
		// http:// URLs before validation_errors() could tell the candidate.
		return esc_url_raw( trim( $value ) );
	}
}
