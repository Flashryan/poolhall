<?php
/**
 * SalaryParser tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Support\SalaryParser;

final class SalaryParserTest extends TestCase {

	private SalaryParser $parser;

	protected function setUp(): void {
		$this->parser = new SalaryParser();
	}

	public function test_parses_giig_yearly_range(): void {
		$salary = $this->parser->parse( '£55,000 - £65,000/Year' );

		self::assertSame( '£55,000 - £65,000/Year', $salary->display );
		self::assertSame( 'GBP', $salary->currency );
		self::assertSame( 55000.0, $salary->min );
		self::assertSame( 65000.0, $salary->max );
		self::assertSame( 'YEAR', $salary->period );
		self::assertTrue( $salary->is_reliable() );
	}

	public function test_parses_single_yearly_value(): void {
		$salary = $this->parser->parse( '£30,000/Year' );

		self::assertSame( 30000.0, $salary->min );
		self::assertSame( 30000.0, $salary->max );
		self::assertSame( 'YEAR', $salary->period );
		self::assertTrue( $salary->is_reliable() );
	}

	public function test_parses_hourly_rate_with_decimals(): void {
		$salary = $this->parser->parse( '£14.50/Hour' );

		self::assertSame( 14.5, $salary->min );
		self::assertSame( 'HOUR', $salary->period );
		self::assertTrue( $salary->is_reliable() );
	}

	public function test_parses_per_annum_wording(): void {
		$salary = $this->parser->parse( '£42,000 per annum' );

		self::assertSame( 42000.0, $salary->min );
		self::assertSame( 'YEAR', $salary->period );
	}

	public function test_infers_hourly_period_from_magnitude(): void {
		$salary = $this->parser->parse( '£13.25' );

		self::assertSame( 'HOUR', $salary->period );
		self::assertTrue( $salary->is_reliable() );
	}

	public function test_infers_yearly_period_from_magnitude(): void {
		$salary = $this->parser->parse( '£38,000' );

		self::assertSame( 'YEAR', $salary->period );
	}

	#[DataProvider( 'unreliable_inputs' )]
	public function test_non_numeric_strings_are_display_only( ?string $input, string $expected_display ): void {
		$salary = $this->parser->parse( $input );

		self::assertSame( $expected_display, $salary->display );
		self::assertFalse( $salary->is_reliable() );
		self::assertNull( $salary->min );
	}

	/**
	 * @return array<string,array{0:?string,1:string}>
	 */
	public static function unreliable_inputs(): array {
		return array(
			'competitive'    => array( 'Competitive', 'Competitive' ),
			'negotiable'     => array( 'Negotiable DOE', 'Negotiable DOE' ),
			'empty'          => array( '', '' ),
			'null'           => array( null, '' ),
			'no currency'    => array( '40000 - 50000', '40000 - 50000' ),
		);
	}

	public function test_ambiguous_mid_range_number_gets_no_period_guess(): void {
		// £2,000 with no period: too big for hourly, too small for a salary —
		// numbers parse but period stays null, so schema must skip baseSalary.
		$salary = $this->parser->parse( '£2,000' );

		self::assertNull( $salary->period );
		self::assertFalse( $salary->is_reliable() );
	}
}
