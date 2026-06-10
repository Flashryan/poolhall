<?php
/**
 * Salary string parser.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Support;

/**
 * Parses Giig salary display strings such as "£55,000 - £65,000/Year",
 * "£14.50/Hour" or "£30,000/Year" into structured values for schema.org
 * `baseSalary` and the salary filter.
 *
 * Non-numeric strings ("Competitive", "Negotiable", "") parse to a
 * display-only Salary with no numeric data, which downstream consumers
 * treat as "do not emit baseSalary".
 */
final class SalaryParser {

	private const CURRENCIES = array(
		'£' => 'GBP',
		'€' => 'EUR',
		'$' => 'USD',
	);

	private const PERIODS = array(
		'year'  => 'YEAR',
		'annum' => 'YEAR',
		'month' => 'MONTH',
		'week'  => 'WEEK',
		'day'   => 'DAY',
		'hour'  => 'HOUR',
	);

	public function parse( ?string $raw ): Salary {
		$display = trim( (string) $raw );
		if ( '' === $display ) {
			return new Salary( '' );
		}

		$currency = null;
		foreach ( self::CURRENCIES as $symbol => $code ) {
			if ( str_contains( $display, $symbol ) ) {
				$currency = $code;
				break;
			}
		}

		$period = null;
		if ( preg_match( '~(?:/|\bper\s+)\s*(year|annum|month|week|day|hour)s?\b~i', $display, $m ) ) {
			$period = self::PERIODS[ strtolower( $m[1] ) ];
		}

		// All numbers in the string, tolerating thousands separators and decimals.
		preg_match_all( '/\d[\d,]*(?:\.\d+)?/', $display, $matches );
		$numbers = array_map(
			static fn( string $n ): float => (float) str_replace( ',', '', $n ),
			$matches[0]
		);

		if ( array() === $numbers || null === $currency ) {
			// Not confidently numeric — keep as display-only.
			return new Salary( $display );
		}

		$min = min( $numbers );
		$max = count( $numbers ) > 1 ? max( $numbers ) : $min;

		// Infer a missing period from magnitude: hourly rates are small.
		if ( null === $period ) {
			$period = $min < 200 ? 'HOUR' : ( $min < 5000 ? null : 'YEAR' );
		}

		return new Salary( $display, $currency, $min, $max, $period );
	}
}
