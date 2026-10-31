<?php
/**
 * Plugin Name: Poolhall hidden reviews
 * Description: Keeps chosen Google reviews out of the website carousel, even after the reviews refresh from Google.
 */
add_filter( 'option_poolhall_reviews_snapshot', static function ( $snapshot ) {
	$hidden = array( 'ellis dennant' ); // Lower-case reviewer names.
	if ( is_array( $snapshot ) && ! empty( $snapshot['reviews'] ) && is_array( $snapshot['reviews'] ) ) {
		$snapshot['reviews'] = array_values( array_filter( $snapshot['reviews'], static function ( $review ) use ( $hidden ) {
			return ! in_array( strtolower( trim( (string) ( $review['author_name'] ?? '' ) ) ), $hidden, true );
		} ) );
	}
	return $snapshot;
} );