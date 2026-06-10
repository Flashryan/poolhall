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
