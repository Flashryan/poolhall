<?php
/**
 * ServiceTiers data tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Marketing\ServiceTiers;

final class ServiceTiersTest extends TestCase {

	/** @return array<string,array<string,mixed>> Tiers keyed by their key. */
	private function byKey(): array {
		$out = array();
		foreach ( ServiceTiers::tiers() as $tier ) {
			$out[ (string) $tier['key'] ] = $tier;
		}
		return $out;
	}

	public function test_three_tiers_in_bronze_silver_gold_order(): void {
		$keys = array_map( static fn( array $t ): string => (string) $t['key'], ServiceTiers::tiers() );

		self::assertSame( array( 'bronze', 'silver', 'gold' ), $keys );
	}

	public function test_products_match_the_spec(): void {
		$tiers = $this->byKey();

		self::assertSame( 'Advertise', $tiers['bronze']['product'] );
		self::assertSame( 'Recruit', $tiers['silver']['product'] );
		self::assertSame( 'Search', $tiers['gold']['product'] );
	}

	public function test_prices_are_the_indicative_percentages(): void {
		$tiers = $this->byKey();

		self::assertSame( '12%', $tiers['bronze']['price'] );
		self::assertSame( '15%', $tiers['silver']['price'] );
		self::assertSame( '20%', $tiers['gold']['price'] );
		foreach ( $tiers as $tier ) {
			self::assertSame( 'of first-year salary', $tier['unit'] );
		}
	}

	public function test_only_gold_is_featured_and_carries_a_ribbon(): void {
		$tiers = $this->byKey();

		self::assertFalse( ! empty( $tiers['bronze']['featured'] ) );
		self::assertFalse( ! empty( $tiers['silver']['featured'] ) );
		self::assertTrue( (bool) $tiers['gold']['featured'] );
		self::assertSame( 'Most popular', $tiers['gold']['ribbon'] );
	}

	public function test_cta_styles_are_ghost_steel_primary(): void {
		$tiers = $this->byKey();

		self::assertSame( 'ghost', $tiers['bronze']['cta_style'] );
		self::assertSame( 'steel', $tiers['silver']['cta_style'] );
		self::assertSame( 'primary', $tiers['gold']['cta_style'] );
	}

	public function test_silver_and_gold_have_an_everything_in_head_row(): void {
		$tiers = $this->byKey();

		self::assertSame( array(), $this->rows( $tiers['bronze'], 'head' ) );
		self::assertNotSame( array(), $this->rows( $tiers['silver'], 'head' ) );
		self::assertNotSame( array(), $this->rows( $tiers['gold'], 'head' ) );
	}

	public function test_every_tier_lists_included_features(): void {
		foreach ( ServiceTiers::tiers() as $tier ) {
			self::assertNotSame( array(), $this->rows( $tier, 'on' ), $tier['key'] . ' should include features' );
		}
	}

	public function test_lower_tiers_show_excluded_features_but_gold_does_not(): void {
		$tiers = $this->byKey();

		self::assertNotSame( array(), $this->rows( $tiers['bronze'], 'off' ) );
		self::assertSame( array(), $this->rows( $tiers['gold'], 'off' ) );
	}

	/**
	 * @param array<string,mixed> $tier
	 * @return array<int,string> Feature labels in the given state.
	 */
	private function rows( array $tier, string $state ): array {
		$out = array();
		foreach ( (array) $tier['features'] as $feature ) {
			if ( $state === ( $feature[0] ?? '' ) ) {
				$out[] = (string) $feature[1];
			}
		}
		return $out;
	}
}
