<?php
/**
 * Work mode classification.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Support;

/**
 * Normalized work mode. Raw source strings are preserved separately in
 * `work_mode_raw` meta; this enum drives taxonomy terms and schema rules.
 */
enum WorkMode: string {
	case Onsite  = 'onsite';
	case Hybrid  = 'hybrid';
	case Remote  = 'remote';
	case Unknown = 'unknown';

	/**
	 * Map a raw Giig work-mode string to a normalized mode.
	 *
	 * Observed Giig values (careers page, 2026-06): "Must Be Onsite",
	 * "Part Remote", "Fully Remote". Matching is defensive against
	 * case/wording drift; unknown values are preserved, not guessed.
	 */
	public static function from_source( ?string $raw ): self {
		$value = strtolower( trim( (string) $raw ) );

		if ( '' === $value ) {
			return self::Unknown;
		}
		if ( str_contains( $value, 'fully remote' ) || 'remote' === $value ) {
			return self::Remote;
		}
		if ( str_contains( $value, 'part remote' ) || str_contains( $value, 'hybrid' ) ) {
			return self::Hybrid;
		}
		if ( str_contains( $value, 'onsite' ) || str_contains( $value, 'on-site' ) || str_contains( $value, 'on site' ) ) {
			return self::Onsite;
		}

		return self::Unknown;
	}

	/** Human label for taxonomy terms and admin display. */
	public function label(): string {
		return match ( $this ) {
			self::Onsite  => 'Onsite',
			self::Hybrid  => 'Part Remote',
			self::Remote  => 'Fully Remote',
			self::Unknown => 'Unspecified',
		};
	}
}
