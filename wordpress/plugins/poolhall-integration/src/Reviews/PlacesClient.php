<?php
/**
 * Google Places API (New) client.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Reviews;

use Poolhall\Integration\Source\SourceException;

/**
 * Server-side Place Details (New) fetch with the minimum field mask
 * (architecture doc §10). The API key never reaches the browser; it comes
 * from wp-config constants or environment:
 *   POOLHALL_PLACES_API_KEY, POOLHALL_PLACE_ID
 */
final class PlacesClient {

	private const ENDPOINT   = 'https://places.googleapis.com/v1/places/';
	private const FIELD_MASK = 'displayName,rating,userRatingCount,reviews,googleMapsUri';

	public function __construct(
		private readonly string $api_key,
		private readonly string $place_id,
		private readonly int $timeout = 15,
	) {}

	public const RESOLVED_PLACE_OPTION = 'poolhall_resolved_place_id';
	private const SEARCH_ENDPOINT      = 'https://places.googleapis.com/v1/places:searchText';
	private const SEARCH_QUERY         = 'Poolhall Recruitment, 11 St Pauls Square, Birmingham, UK';

	public static function from_environment(): self {
		$key = self::config( 'POOLHALL_PLACES_API_KEY' );
		if ( null === $key ) {
			throw new SourceException( 'Google Places credentials are not configured (POOLHALL_PLACES_API_KEY).' );
		}

		// Key-only activation: when no POOLHALL_PLACE_ID is configured, the
		// Place ID is resolved once via Text Search and cached, so adding the
		// API key to wp-config is the only step needed to go live.
		$place_id = self::config( 'POOLHALL_PLACE_ID' );
		if ( null === $place_id ) {
			$stored   = get_option( self::RESOLVED_PLACE_OPTION, '' );
			$place_id = is_string( $stored ) && '' !== $stored ? $stored : self::resolve_place_id( $key );
		}

		return new self( $key, $place_id );
	}

	/** One-off Text Search lookup; the resolved id is cached in an option. */
	private static function resolve_place_id( string $key ): string {
		$response = \wp_remote_post(
			self::SEARCH_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $key,
					'X-Goog-FieldMask' => 'places.id,places.displayName',
				),
				'body'    => \wp_json_encode( array( 'textQuery' => self::SEARCH_QUERY ) ),
			)
		);

		if ( \is_wp_error( $response ) ) {
			throw new SourceException( 'Place lookup failed: ' . $response->get_error_message() );
		}
		if ( 200 !== (int) \wp_remote_retrieve_response_code( $response ) ) {
			throw new SourceException( sprintf( 'Place lookup returned HTTP %d.', (int) \wp_remote_retrieve_response_code( $response ) ) );
		}

		$decoded  = json_decode( (string) \wp_remote_retrieve_body( $response ), true );
		$place_id = self::extract_place_id( is_array( $decoded ) ? $decoded : array() );
		if ( '' === $place_id ) {
			throw new SourceException( 'Place lookup returned no match for the Poolhall listing; set POOLHALL_PLACE_ID explicitly.' );
		}

		update_option( self::RESOLVED_PLACE_OPTION, $place_id, false );
		return $place_id;
	}

	/**
	 * First place id from a Text Search response.
	 *
	 * @param array<mixed> $decoded Decoded response.
	 */
	public static function extract_place_id( array $decoded ): string {
		$places = $decoded['places'] ?? null;
		if ( ! is_array( $places ) || array() === $places ) {
			return '';
		}
		$first = $places[0];
		return is_array( $first ) && isset( $first['id'] ) && is_string( $first['id'] ) ? $first['id'] : '';
	}

	public function fetch_snapshot(): ReviewsSnapshot {
		$response = \wp_remote_get(
			self::ENDPOINT . rawurlencode( $this->place_id ),
			array(
				'timeout' => $this->timeout,
				'headers' => array(
					'X-Goog-Api-Key'   => $this->api_key,
					'X-Goog-FieldMask' => self::FIELD_MASK,
					'Accept'           => 'application/json',
				),
			)
		);

		if ( \is_wp_error( $response ) ) {
			throw new SourceException( 'Places request failed: ' . $response->get_error_message() );
		}
		$status = (int) \wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			throw new SourceException( sprintf( 'Places request returned HTTP %d.', $status ) );
		}

		$decoded = json_decode( (string) \wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			throw new SourceException( 'Places response was not JSON.' );
		}

		return self::parse( $decoded, new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
	}

	/**
	 * Parse a Place Details (New) payload. Public + static so it is testable
	 * against fixtures without HTTP.
	 *
	 * @param array<string,mixed> $place Decoded Place resource.
	 */
	public static function parse( array $place, \DateTimeImmutable $fetched_at ): ReviewsSnapshot {
		$reviews = array();
		foreach ( array_slice( (array) ( $place['reviews'] ?? array() ), 0, 5 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$author = (array) ( $row['authorAttribution'] ?? array() );
			$text   = $row['text']['text'] ?? '';
			if ( '' === $text || ! isset( $row['rating'] ) ) {
				continue; // A review without text or rating cannot be displayed meaningfully.
			}
			$reviews[] = new Review(
				author_name: (string) ( $author['displayName'] ?? 'Google user' ),
				author_profile_url: isset( $author['uri'] ) ? (string) $author['uri'] : null,
				author_photo_url: isset( $author['photoUri'] ) ? (string) $author['photoUri'] : null,
				rating: (int) $row['rating'],
				text: (string) $text,
				relative_time: (string) ( $row['relativePublishTimeDescription'] ?? '' ),
			);
		}

		return new ReviewsSnapshot(
			place_name: (string) ( $place['displayName']['text'] ?? '' ),
			rating: (float) ( $place['rating'] ?? 0 ),
			rating_count: (int) ( $place['userRatingCount'] ?? 0 ),
			reviews: $reviews,
			google_maps_uri: isset( $place['googleMapsUri'] ) ? (string) $place['googleMapsUri'] : null,
			fetched_at: $fetched_at,
		);
	}

	private static function config( string $name ): ?string {
		if ( defined( $name ) ) {
			$value = constant( $name );
			return is_string( $value ) && '' !== $value ? $value : null;
		}
		$env = getenv( $name );
		return false !== $env && '' !== $env ? $env : null;
	}
}
