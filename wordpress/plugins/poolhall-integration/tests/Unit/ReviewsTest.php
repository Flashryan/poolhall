<?php
/**
 * Reviews module tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Reviews\CachePolicy;
use Poolhall\Integration\Reviews\PlacesClient;
use Poolhall\Integration\Reviews\Review;
use Poolhall\Integration\Reviews\ReviewsSnapshot;

final class ReviewsTest extends TestCase {

	private function date( string $value ): \DateTimeImmutable {
		return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
	}

	private function snapshot( string $fetched_at, int $review_count = 3 ): ReviewsSnapshot {
		$reviews = array();
		for ( $i = 0; $i < $review_count; $i++ ) {
			$reviews[] = new Review( 'Reviewer ' . $i, null, null, 5, 'Great agency.', 'a week ago' );
		}
		return new ReviewsSnapshot( 'Poolhall Recruitment', 5.0, 21, $reviews, 'https://maps.google.com/?cid=x', $this->date( $fetched_at ) );
	}

	// --- Places (New) payload parsing -----------------------------------

	public function test_parses_place_details_new_payload(): void {
		$payload = array(
			'displayName'     => array(
				'text'         => 'Poolhall Recruitment Ltd',
				'languageCode' => 'en',
			),
			'rating'          => 5.0,
			'userRatingCount' => 21,
			'googleMapsUri'   => 'https://maps.google.com/?cid=123',
			'reviews'         => array(
				array(
					'rating'                         => 5,
					'text'                           => array( 'text' => 'Matt found me a brilliant role.' ),
					'relativePublishTimeDescription' => '2 months ago',
					'authorAttribution'              => array(
						'displayName' => 'Jane D',
						'uri'         => 'https://www.google.com/maps/contrib/1',
						'photoUri'    => 'https://lh3.googleusercontent.com/a/photo',
					),
				),
				array(
					'rating' => 4,
					'text'   => array( 'text' => 'Smooth process.' ),
				),
			),
		);

		$snapshot = PlacesClient::parse( $payload, $this->date( '2026-06-10T12:00:00Z' ) );

		self::assertSame( 'Poolhall Recruitment Ltd', $snapshot->place_name );
		self::assertSame( 5.0, $snapshot->rating );
		self::assertSame( 21, $snapshot->rating_count );
		self::assertCount( 2, $snapshot->reviews );
		self::assertSame( 'Jane D', $snapshot->reviews[0]->author_name );
		self::assertSame( 'https://www.google.com/maps/contrib/1', $snapshot->reviews[0]->author_profile_url );
		self::assertSame( 'Google user', $snapshot->reviews[1]->author_name );
	}

	public function test_parse_caps_reviews_at_five_and_skips_unusable_rows(): void {
		$rows = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$rows[] = array(
				'rating' => 5,
				'text'   => array( 'text' => 'Review ' . $i ),
			);
		}
		$rows[] = array( 'rating' => 5 ); // No text: skipped.

		$snapshot = PlacesClient::parse( array( 'reviews' => $rows ), $this->date( '2026-06-10' ) );

		self::assertCount( 5, $snapshot->reviews );
	}

	public function test_snapshot_round_trips_through_array_storage(): void {
		$original = $this->snapshot( '2026-06-10T08:00:00Z' );
		$restored = ReviewsSnapshot::from_array( $original->to_array() );

		self::assertEquals( $original, $restored );
	}

	// --- Cache policy ----------------------------------------------------

	public function test_empty_cache_needs_refresh_and_is_not_servable(): void {
		$policy = new CachePolicy();
		$now    = $this->date( '2026-06-10T12:00:00Z' );

		self::assertTrue( $policy->needs_refresh( null, $now ) );
		self::assertFalse( $policy->is_servable( null, $now ) );
	}

	public function test_fresh_cache_is_served_without_refresh(): void {
		$policy = new CachePolicy();
		$cached = $this->snapshot( '2026-06-10T00:00:00Z' );

		self::assertFalse( $policy->needs_refresh( $cached, $this->date( '2026-06-10T23:59:00Z' ) ) );
		self::assertTrue( $policy->is_servable( $cached, $this->date( '2026-06-10T23:59:00Z' ) ) );
	}

	public function test_cache_older_than_24h_needs_refresh_but_stays_servable(): void {
		$policy = new CachePolicy();
		$cached = $this->snapshot( '2026-06-08T00:00:00Z' );
		$now    = $this->date( '2026-06-10T12:00:00Z' );

		self::assertTrue( $policy->needs_refresh( $cached, $now ) );
		self::assertTrue( $policy->is_servable( $cached, $now ) ); // Within 7-day stale window.
	}

	public function test_cache_older_than_seven_days_is_not_servable(): void {
		$policy = new CachePolicy();
		$cached = $this->snapshot( '2026-06-01T00:00:00Z' );

		self::assertFalse( $policy->is_servable( $cached, $this->date( '2026-06-08T00:00:01Z' ) ) );
	}

	public function test_snapshot_with_zero_reviews_is_never_servable(): void {
		$policy = new CachePolicy();
		$cached = $this->snapshot( '2026-06-10T08:00:00Z', 0 );

		self::assertFalse( $policy->is_servable( $cached, $this->date( '2026-06-10T09:00:00Z' ) ) );
	}
}
