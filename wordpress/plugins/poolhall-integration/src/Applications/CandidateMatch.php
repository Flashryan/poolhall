<?php
declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Source\CandidatePayload;

/** Exact email and normalized name are mandatory for automatic linking. */
final class CandidateMatch {
	public static function matches( array $remote, CandidatePayload $profile ): bool {
		$normalize = static fn( string $value ): string => strtolower( trim( (string) preg_replace( '/\s+/', ' ', $value ) ) );
		return strtolower( trim( (string) ( $remote['email'] ?? '' ) ) ) === strtolower( trim( $profile->email ) )
			&& $normalize( (string) ( $remote['first_name'] ?? '' ) ) === $normalize( $profile->first_name )
			&& $normalize( (string) ( $remote['last_name'] ?? '' ) ) === $normalize( $profile->last_name );
	}

	/** A Giig ID cannot bind two different durable website identities. */
	public static function id_available( int $record, string $id ): bool {
		$key = CandidateIdentity::key( $record );
		foreach ( get_posts( array( 'post_type' => ApplicationRecord::POST_TYPE, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => 'giig_candidate_id', 'meta_value' => $id ) ) as $other ) {
			if ( (int) $other !== $record && ( '' === $key || CandidateIdentity::key( (int) $other ) !== $key ) ) {
				return false;
			}
		}
		return true;
	}
}
