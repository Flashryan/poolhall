<?php
/**
 * Plugin Name: Poolhall hero preload
 * Description: On the home page, asks the browser for the first hero slide's photo straight away. The slider shows it as a CSS background, which the browser would otherwise find only after the stylesheets load.
 */
add_action( 'wp_head', static function () {
	if ( ! is_front_page() ) {
		return;
	}
	$page_id = (int) get_option( 'page_on_front' );
	$key     = 'poolhall_hero_preload_' . $page_id . '_' . md5( (string) get_post_modified_time( 'U', true, $page_id ) );
	$url     = get_transient( $key );
	if ( false === $url ) {
		$url  = '';
		$data = json_decode( (string) get_post_meta( $page_id, '_elementor_data', true ), true );
		$find = static function ( $els ) use ( &$find ) {
			foreach ( (array) $els as $el ) {
				if ( 'slides' === ( $el['widgetType'] ?? '' ) ) {
					return (string) ( $el['settings']['slides'][0]['background_image']['url'] ?? '' );
				}
				$hit = $find( $el['elements'] ?? array() );
				if ( '' !== $hit ) {
					return $hit;
				}
			}
			return '';
		};
		$url = $find( $data );
		set_transient( $key, $url, DAY_IN_SECONDS );
	}
	if ( '' !== $url ) {
		echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high" />' . "\n";
	}
}, 1 );
