<?php
/**
 * SearchRequest tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Jobs\SearchRequest;

final class SearchRequestTest extends TestCase {

	public function test_empty_query_is_not_filtered(): void {
		$request = SearchRequest::from_query( array() );

		self::assertFalse( $request->is_filtered() );
		self::assertSame( '', $request->keyword );
		self::assertSame( '', $request->location );
		self::assertSame( '', $request->sector );
	}

	public function test_text_is_trimmed_and_whitespace_collapsed(): void {
		$request = SearchRequest::from_query(
			array(
				'q'        => "  site \n manager  ",
				'location' => " Wolverhampton\t ",
			)
		);

		self::assertTrue( $request->is_filtered() );
		self::assertSame( 'site manager', $request->keyword );
		self::assertSame( 'Wolverhampton', $request->location );
	}

	public function test_markup_is_stripped_from_text(): void {
		$request = SearchRequest::from_query( array( 'q' => '<script>alert(1)</script>welder' ) );

		self::assertSame( 'alert(1)welder', $request->keyword );
	}

	public function test_text_is_capped_at_120_characters(): void {
		$request = SearchRequest::from_query( array( 'q' => str_repeat( 'a', 500 ) ) );

		self::assertSame( 120, mb_strlen( $request->keyword ) );
	}

	public function test_sector_keeps_only_slug_characters(): void {
		$request = SearchRequest::from_query( array( 'sector' => 'Construction & Skilled-Trade!' ) );

		self::assertSame( 'constructionskilled-trade', $request->sector );
	}

	public function test_non_string_values_are_ignored(): void {
		$request = SearchRequest::from_query(
			array(
				'q'        => array( 'nested' ),
				'location' => 7,
				'sector'   => null,
			)
		);

		self::assertFalse( $request->is_filtered() );
	}
}
