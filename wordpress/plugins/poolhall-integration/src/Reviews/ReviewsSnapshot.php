<?php
/**
 * Reviews snapshot value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Reviews;

/**
 * Everything the reviews carousel needs, captured at one refresh. The
 * Google Maps URI and attribution fields satisfy Places display policies.
 */
final class ReviewsSnapshot {
	/**
	 * @param Review[] $reviews Up to five reviews (Places API limit).
	 */
	public function __construct(
		public readonly string $place_name,
		public readonly float $rating,
		public readonly int $rating_count,
		public readonly array $reviews,
		public readonly ?string $google_maps_uri,
		public readonly \DateTimeImmutable $fetched_at,
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'place_name'      => $this->place_name,
			'rating'          => $this->rating,
			'rating_count'    => $this->rating_count,
			'reviews'         => array_map( static fn( Review $r ): array => $r->to_array(), $this->reviews ),
			'google_maps_uri' => $this->google_maps_uri,
			'fetched_at'      => $this->fetched_at->format( \DateTimeInterface::ATOM ),
		);
	}

	/**
	 * @param array<string,mixed> $data Stored array shape.
	 */
	public static function from_array( array $data ): self {
		return new self(
			place_name: (string) ( $data['place_name'] ?? '' ),
			rating: (float) ( $data['rating'] ?? 0 ),
			rating_count: (int) ( $data['rating_count'] ?? 0 ),
			reviews: array_map(
				static fn( array $r ): Review => Review::from_array( $r ),
				array_values( (array) ( $data['reviews'] ?? array() ) )
			),
			google_maps_uri: isset( $data['google_maps_uri'] ) && '' !== $data['google_maps_uri'] ? (string) $data['google_maps_uri'] : null,
			fetched_at: new \DateTimeImmutable( (string) ( $data['fetched_at'] ?? 'now' ) ),
		);
	}
}
