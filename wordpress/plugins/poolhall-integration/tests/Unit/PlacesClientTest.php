<?php
/**
 * PlacesClient parsing tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Reviews\PlacesClient;

final class PlacesClientTest extends TestCase {

	public function test_extracts_first_place_id_from_text_search(): void {
		$decoded = array(
			'places' => array(
				array(
					'id'          => 'ChIJexample123',
					'displayName' => array( 'text' => 'Poolhall Recruitment' ),
				),
				array( 'id' => 'ChIJother456' ),
			),
		);

		self::assertSame( 'ChIJexample123', PlacesClient::extract_place_id( $decoded ) );
	}

	public function test_empty_or_malformed_search_yields_no_id(): void {
		self::assertSame( '', PlacesClient::extract_place_id( array() ) );
		self::assertSame( '', PlacesClient::extract_place_id( array( 'places' => array() ) ) );
		self::assertSame( '', PlacesClient::extract_place_id( array( 'places' => array( array( 'displayName' => 'x' ) ) ) ) );
		self::assertSame( '', PlacesClient::extract_place_id( array( 'places' => 'nope' ) ) );
	}
}
