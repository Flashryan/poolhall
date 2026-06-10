<?php
/**
 * WorkMode tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Support\WorkMode;

final class WorkModeTest extends TestCase {

	#[DataProvider( 'mappings' )]
	public function test_maps_source_strings( ?string $raw, WorkMode $expected ): void {
		self::assertSame( $expected, WorkMode::from_source( $raw ) );
	}

	/**
	 * @return array<string,array{0:?string,1:WorkMode}>
	 */
	public static function mappings(): array {
		return array(
			// Exact Giig values observed on the live careers page.
			'giig onsite'      => array( 'Must Be Onsite', WorkMode::Onsite ),
			'giig part remote' => array( 'Part Remote', WorkMode::Hybrid ),
			'giig fully remote' => array( 'Fully Remote', WorkMode::Remote ),
			// Defensive variants.
			'lowercase'        => array( 'must be onsite', WorkMode::Onsite ),
			'hyphenated'       => array( 'On-Site', WorkMode::Onsite ),
			'hybrid wording'   => array( 'Hybrid', WorkMode::Hybrid ),
			'bare remote'      => array( 'Remote', WorkMode::Remote ),
			'unknown value'    => array( 'Flexible-ish', WorkMode::Unknown ),
			'empty'            => array( '', WorkMode::Unknown ),
			'null'             => array( null, WorkMode::Unknown ),
		);
	}
}
