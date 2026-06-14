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

	public function test_work_mode_and_type_are_slugged(): void {
		$request = SearchRequest::from_query(
			array(
				'work_mode' => 'Hybrid!!',
				'type'      => 'Permanent',
			)
		);

		self::assertSame( 'hybrid', $request->work_mode );
		self::assertSame( 'permanent', $request->type );
		self::assertTrue( $request->is_filtered() );
	}

	public function test_salary_min_is_clamped_to_a_non_negative_int(): void {
		self::assertSame( 30000, SearchRequest::from_query( array( 'salary_min' => '30000' ) )->salary_min );
		self::assertSame( 0, SearchRequest::from_query( array( 'salary_min' => '-5' ) )->salary_min );
		self::assertSame( 0, SearchRequest::from_query( array( 'salary_min' => 'abc' ) )->salary_min );
		self::assertSame( 1000000, SearchRequest::from_query( array( 'salary_min' => '99999999' ) )->salary_min );
	}

	public function test_sort_falls_back_to_recent_for_unknown_values(): void {
		self::assertSame( 'salary', SearchRequest::from_query( array( 'sort' => 'salary' ) )->sort );
		self::assertSame( 'recent', SearchRequest::from_query( array( 'sort' => 'bogus' ) )->sort );
		self::assertSame( 'recent', SearchRequest::from_query( array() )->sort );
	}

	public function test_page_is_at_least_one(): void {
		self::assertSame( 3, SearchRequest::from_query( array( 'pg' => '3' ) )->page );
		self::assertSame( 1, SearchRequest::from_query( array( 'pg' => '0' ) )->page );
		self::assertSame( 1, SearchRequest::from_query( array( 'pg' => '-2' ) )->page );
	}

	public function test_sort_alone_counts_as_filtered_but_page_does_not(): void {
		self::assertTrue( SearchRequest::from_query( array( 'sort' => 'az' ) )->is_filtered() );
		self::assertFalse( SearchRequest::from_query( array( 'pg' => '4' ) )->is_filtered() );
	}

	public function test_active_filters_excludes_sort_and_page(): void {
		$request = SearchRequest::from_query(
			array(
				'sector'     => 'manufacturing',
				'salary_min' => '40000',
				'sort'       => 'salary',
				'pg'         => '2',
			)
		);
		$active = $request->active_filters();

		self::assertSame( array( 'sector', 'salary_min' ), array_keys( $active ) );
		self::assertSame( 'manufacturing', $active['sector'] );
		self::assertSame( '40000', $active['salary_min'] );
	}
}
