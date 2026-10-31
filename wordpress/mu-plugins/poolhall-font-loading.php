<?php
/**
 * Plugin Name: Poolhall font loading
 * Description: Fonts are served from this site through Elementor's "Load Google Fonts locally". The theme's legacy Archivo / Source Sans 3 / IBM Plex Mono request stays only on the private /candidate pages, whose document forms still use those families.
 */
function poolhall_fonts_candidate_page(): bool {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return 0 === strpos( $path, '/candidate' );
}

add_action( 'wp_enqueue_scripts', static function () {
	$styles = wp_styles();
	if ( ! poolhall_fonts_candidate_page() && isset( $styles->registered['poolhall-fonts'] ) ) {
		$styles->registered['poolhall-fonts']->src = false; // Keeps shared.css's dependency, prints nothing.
	}
	// shared.css changed without a theme version bump, so version it by file time.
	$file = get_stylesheet_directory() . '/assets/css/shared.css';
	if ( isset( $styles->registered['hello-elementor-child'] ) && is_file( $file ) ) {
		$styles->registered['hello-elementor-child']->ver .= '.' . filemtime( $file );
	}
}, 100 );

add_filter( 'wp_resource_hints', static function ( array $urls, string $type ): array {
	if ( poolhall_fonts_candidate_page() || ! in_array( $type, array( 'preconnect', 'dns-prefetch' ), true ) ) {
		return $urls;
	}
	return array_values( array_filter( $urls, static function ( $url ): bool {
		$href = (string) ( is_array( $url ) ? ( $url['href'] ?? '' ) : $url );
		return false === strpos( $href, 'fonts.googleapis.com' ) && false === strpos( $href, 'fonts.gstatic.com' );
	} ) );
}, 20, 2 );
