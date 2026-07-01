<?php
/**
 * GiigClient transport-envelope tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Source\Giig\GiigClient;

final class GiigClientTest extends TestCase {

	public function test_unwraps_status_body_envelope(): void {
		$envelope = array(
			'status' => 200,
			'body'   => json_encode( array( 'jobs' => array( array( 'JobId' => 1 ) ), 'data' => null ) ),
		);

		$unwrapped = GiigClient::unwrap_envelope( $envelope );

		self::assertArrayHasKey( 'jobs', $unwrapped );
		self::assertSame( 1, $unwrapped['jobs'][0]['JobId'] );
	}

	public function test_passthrough_for_bare_payload(): void {
		$bare = array( 'jobs' => array( array( 'JobId' => 7 ) ) );

		self::assertSame( $bare, GiigClient::unwrap_envelope( $bare ) );
	}

	public function test_passthrough_when_shape_is_not_exactly_status_and_body(): void {
		// Extra keys mean it is not the transport envelope.
		$decoded = array(
			'status' => 200,
			'body'   => '{"jobs":[]}',
			'extra'  => true,
		);

		self::assertSame( $decoded, GiigClient::unwrap_envelope( $decoded ) );
	}

	public function test_handles_body_already_decoded_as_array(): void {
		$decoded = array(
			'status' => 200,
			'body'   => array( 'jobs' => array() ),
		);

		self::assertSame( array( 'jobs' => array() ), GiigClient::unwrap_envelope( $decoded ) );
	}

	public function test_passthrough_when_body_is_not_valid_json(): void {
		$decoded = array(
			'status' => 200,
			'body'   => 'not-json',
		);

		self::assertSame( $decoded, GiigClient::unwrap_envelope( $decoded ) );
	}
}
