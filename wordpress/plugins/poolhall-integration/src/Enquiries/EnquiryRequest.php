<?php
/**
 * Enquiry form submission value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Enquiries;

use Poolhall\Integration\Accounts\EmailAddress;

/**
 * Normalized hiring/contact enquiry (spec 01 §7 hiring form, §12 contact).
 * Pure domain logic — validation rules are unit-tested without WordPress.
 */
final class EnquiryRequest {

	public const KIND_HIRING  = 'hiring';
	public const KIND_CONTACT = 'contact';

	public const ENQUIRER_TYPES = array(
		'candidate' => 'Candidate looking for work',
		'employer'  => 'Employer looking to hire',
		'recruiter' => 'Recruiter interested in joining',
		'other'     => 'Something else',
	);

	private const MAX_FIELD   = 200;
	private const MAX_MESSAGE = 5000;

	public function __construct(
		public readonly string $kind,
		public readonly string $name,
		public readonly string $company,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $enquirer_type,
		public readonly string $message,
		public readonly bool $consented
	) {
	}

	/**
	 * @param array<string,mixed> $fields Raw POST fields (already unslashed).
	 */
	public static function from_post( string $kind, array $fields ): self {
		return new self(
			self::KIND_HIRING === $kind ? self::KIND_HIRING : self::KIND_CONTACT,
			self::clean( $fields['name'] ?? '', self::MAX_FIELD ),
			self::clean( $fields['company'] ?? '', self::MAX_FIELD ),
			EmailAddress::normalize( is_string( $fields['email'] ?? null ) ? $fields['email'] : '' ),
			self::clean( $fields['phone'] ?? '', self::MAX_FIELD ),
			self::clean_type( $fields['enquirer_type'] ?? '' ),
			self::clean( $fields['message'] ?? '', self::MAX_MESSAGE ),
			'1' === ( $fields['consent'] ?? '' )
		);
	}

	/**
	 * @return string[] Error codes; empty when the submission is valid.
	 */
	public function validation_errors(): array {
		$errors = array();
		if ( '' === $this->name ) {
			$errors[] = 'name_required';
		}
		if ( self::KIND_HIRING === $this->kind && '' === $this->company ) {
			$errors[] = 'company_required';
		}
		if ( ! EmailAddress::is_valid( $this->email ) ) {
			$errors[] = 'email_invalid';
		}
		if ( self::KIND_CONTACT === $this->kind && '' === $this->enquirer_type ) {
			$errors[] = 'type_required';
		}
		if ( '' === $this->message ) {
			$errors[] = 'message_required';
		}
		if ( ! $this->consented ) {
			$errors[] = 'consent_required';
		}
		return $errors;
	}

	/**
	 * A business-development lead rather than a message: the hiring form,
	 * or the contact form with "Employer looking to hire" selected. Only
	 * these are stored for the Giig company-creation review queue.
	 */
	public function is_employer(): bool {
		return self::KIND_HIRING === $this->kind || 'employer' === $this->enquirer_type;
	}

	private static function clean( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure class, no WP loaded in unit tests.
		$value = trim( (string) preg_replace( '/[ \t]+/u', ' ', $value ) );

		return mb_substr( $value, 0, $max );
	}

	private static function clean_type( mixed $value ): string {
		return is_string( $value ) && isset( self::ENQUIRER_TYPES[ $value ] ) ? $value : '';
	}
}
