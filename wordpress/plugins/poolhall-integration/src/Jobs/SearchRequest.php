<?php
/**
 * Jobs search request normalization.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Normalizes the public jobs-search query string (spec 01 §4 URL state:
 * `q`, `location`, `sector`) into clean values the archive query can apply.
 * Pure domain logic — no WordPress dependency — so the trimming, length
 * caps and slug rules are unit-tested.
 */
final class SearchRequest {

	private const MAX_TEXT_LENGTH = 120;
	private const MAX_SLUG_LENGTH = 80;

	public function __construct(
		public readonly string $keyword = '',
		public readonly string $location = '',
		public readonly string $sector = ''
	) {
	}

	/**
	 * @param array<string,mixed> $params Raw query parameters (already unslashed).
	 */
	public static function from_query( array $params ): self {
		return new self(
			self::clean_text( $params['q'] ?? '' ),
			self::clean_text( $params['location'] ?? '' ),
			self::clean_slug( $params['sector'] ?? '' )
		);
	}

	public function is_filtered(): bool {
		return '' !== $this->keyword || '' !== $this->location || '' !== $this->sector;
	}

	private static function clean_text( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strip_tags( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure class, no WP loaded in unit tests.
		$value = (string) preg_replace( '/\s+/u', ' ', $value );
		$value = trim( $value );

		return mb_substr( $value, 0, self::MAX_TEXT_LENGTH );
	}

	private static function clean_slug( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strtolower( trim( $value ) );
		$value = (string) preg_replace( '/[^a-z0-9_-]/', '', $value );

		return substr( $value, 0, self::MAX_SLUG_LENGTH );
	}
}
