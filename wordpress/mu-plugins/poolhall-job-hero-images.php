<?php
/**
 * Plugin Name: Poolhall job hero images
 * Description: Gives each job page a hero photo from its own sector, chosen from a small pool so jobs in the same sector vary. Each job always shows the same photo.
 */
add_filter( 'do_shortcode_tag', static function ( $html, $tag ) {
	if ( 'poolhall_v2_job_single' !== $tag || ! class_exists( 'Poolhall_Recruitment_Sector_Icons' ) ) {
		return $html;
	}
	$pools = array( // Media Library attachment IDs.
		'construction'  => array( 84, 199, 201 ),
		'manufacturing' => array( 85, 702, 1210 ),
		'digital'       => array( 86, 704, 984 ),
	);
	$post_id = (int) get_the_ID();
	$sector  = Poolhall_Recruitment_Sector_Icons::for_post( $post_id );
	if ( ! isset( $pools[ $sector ] ) ) {
		return $html; // Unknown sector: keep the plugin's default photo.
	}
	$id  = $pools[ $sector ][ $post_id % count( $pools[ $sector ] ) ];
	$src = wp_get_attachment_image_url( $id, 'large' );
	if ( ! $src ) {
		return $html;
	}
	$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	return preg_replace(
		'#<img src="[^"]*/sector-(?:digital|construction|manufacturing)[^"]*" alt="[^"]*"#',
		'<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"',
		$html,
		1
	);
}, 25, 2 );
