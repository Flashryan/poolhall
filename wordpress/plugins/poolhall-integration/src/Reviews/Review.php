<?php
/**
 * Review value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Reviews;

/**
 * One Google review, reduced to the fields the carousel may display.
 * Places policy: author name must appear close to the review, with the
 * author's profile link when supplied.
 */
final class Review {
	public function __construct(
		public readonly string $author_name,
		public readonly ?string $author_profile_url,
		public readonly ?string $author_photo_url,
		public readonly int $rating,
		public readonly string $text,
		public readonly string $relative_time,
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'author_name'        => $this->author_name,
			'author_profile_url' => $this->author_profile_url,
			'author_photo_url'   => $this->author_photo_url,
			'rating'             => $this->rating,
			'text'               => $this->text,
			'relative_time'      => $this->relative_time,
		);
	}

	/**
	 * @param array<string,mixed> $data Stored array shape.
	 */
	public static function from_array( array $data ): self {
		return new self(
			author_name: (string) ( $data['author_name'] ?? '' ),
			author_profile_url: isset( $data['author_profile_url'] ) && '' !== $data['author_profile_url'] ? (string) $data['author_profile_url'] : null,
			author_photo_url: isset( $data['author_photo_url'] ) && '' !== $data['author_photo_url'] ? (string) $data['author_photo_url'] : null,
			rating: (int) ( $data['rating'] ?? 0 ),
			text: (string) ( $data['text'] ?? '' ),
			relative_time: (string) ( $data['relative_time'] ?? '' ),
		);
	}
}
