<?php
/**
 * Jobs search request normalization.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Normalizes the public jobs query string (spec 01 §4 URL state) into clean
 * values the archive query and filter UI share: `q`, `location`, `sector`,
 * `work_mode`, `type`, `salary_min`, `sort`, `pg`. Pure domain logic, no
 * WordPress dependency, so the trimming, slug rules, salary clamping and
 * sort/page validation are unit-tested.
 */
final class SearchRequest {

	private const MAX_TEXT_LENGTH = 120;
	private const MAX_SLUG_LENGTH = 80;
	private const MAX_SALARY      = 1_000_000;

	public const SORTS = array( 'recent', 'salary', 'az' );

	public function __construct(
		public readonly string $keyword = '',
		public readonly string $location = '',
		public readonly string $sector = '',
		public readonly string $work_mode = '',
		public readonly string $type = '',
		public readonly int $salary_min = 0,
		public readonly string $sort = 'recent',
		public readonly int $page = 1
	) {
	}

	/**
	 * @param array<string,mixed> $params Raw query parameters (already unslashed).
	 */
	public static function from_query( array $params ): self {
		return new self(
			self::clean_text( $params['q'] ?? '' ),
			self::clean_text( $params['location'] ?? '' ),
			self::clean_slug( $params['sector'] ?? '' ),
			self::clean_slug( $params['work_mode'] ?? '' ),
			self::clean_slug( $params['type'] ?? '' ),
			self::clean_salary( $params['salary_min'] ?? 0 ),
			self::clean_sort( $params['sort'] ?? '' ),
			self::clean_page( $params['pg'] ?? 1 )
		);
	}

	/** True when any filter beyond the bare archive is active (drives noindex). */
	public function is_filtered(): bool {
		return '' !== $this->keyword
			|| '' !== $this->location
			|| '' !== $this->sector
			|| '' !== $this->work_mode
			|| '' !== $this->type
			|| $this->salary_min > 0
			|| 'recent' !== $this->sort;
	}

	/**
	 * Active filters as code => display-ready raw value, for the chip row.
	 * Sort and page are navigation, not filters, so they are excluded.
	 *
	 * @return array<string,string>
	 */
	public function active_filters(): array {
		$active = array();
		if ( '' !== $this->keyword ) {
			$active['q'] = $this->keyword;
		}
		if ( '' !== $this->location ) {
			$active['location'] = $this->location;
		}
		if ( '' !== $this->sector ) {
			$active['sector'] = $this->sector;
		}
		if ( '' !== $this->work_mode ) {
			$active['work_mode'] = $this->work_mode;
		}
		if ( '' !== $this->type ) {
			$active['type'] = $this->type;
		}
		if ( $this->salary_min > 0 ) {
			$active['salary_min'] = (string) $this->salary_min;
		}
		return $active;
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

	private static function clean_salary( mixed $value ): int {
		$value = is_numeric( $value ) ? (int) $value : 0;

		return max( 0, min( self::MAX_SALARY, $value ) );
	}

	private static function clean_sort( mixed $value ): string {
		return is_string( $value ) && in_array( $value, self::SORTS, true ) ? $value : 'recent';
	}

	private static function clean_page( mixed $value ): int {
		$value = is_numeric( $value ) ? (int) $value : 1;

		return max( 1, $value );
	}
}
