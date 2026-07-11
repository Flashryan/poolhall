<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Unit tests cover the pure domain layer (parsers, planner, policies,
 * schema). They run without WordPress; the handful of WP functions the
 * domain layer touches are shimmed here. Anything needing real WordPress
 * runs as an integration script via `wp eval-file` (see scripts/dev/).
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data  Data.
	 * @param int   $flags JSON flags.
	 */
	function wp_json_encode( $data, int $flags = 0 ): string|false {
		return json_encode( $data, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $value ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		return trim( strip_tags( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url, ?array $protocols = null ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		unset( $protocols );
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$clean = filter_var( $url, FILTER_SANITIZE_URL );
		return is_string( $clean ) ? $clean : '';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		$text = strip_tags( (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) );
		if ( $remove_breaks ) {
			$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string { // phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
