<?php
/**
 * Stops the dev builders destroying Elementor documents that were edited after
 * the builder last wrote them.
 *
 * These scripts replace a page's `_elementor_data` wholesale on every run. That
 * was safe while pages were only ever built by script. The site has since been
 * converted from shortcode mounts to native Elementor widgets, so an unguarded
 * re-run would silently wipe that work (create-home-page.php and
 * create-marketing-pages.php also reassign their native sections to shortcode
 * mounts immediately before writing, so a re-run reintroduces shortcodes too).
 *
 * Each guarded write records a hash of exactly what the builder produced. On the
 * next run the document is only replaced if it still matches that hash, i.e. it
 * has not been touched since. Otherwise the page is left alone and the skip is
 * logged.
 *
 * To overwrite deliberately (e.g. a genuine rebuild):
 *
 *   POOLHALL_BUILDER_FORCE=1 wp eval-file scripts/dev/create-home-page.php
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'POOLHALL_BUILDER_HASH_KEY' ) ) {
	define( 'POOLHALL_BUILDER_HASH_KEY', '_poolhall_builder_hash' );
}

if ( ! function_exists( 'poolhall_builder_log' ) ) {
	function poolhall_builder_log( string $message ): void {
		$line = '[poolhall-builder] ' . $message;
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::log( $line );
			return;
		}
		echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CLI diagnostic.
	}
}

if ( ! function_exists( 'poolhall_builder_forced' ) ) {
	function poolhall_builder_forced(): bool {
		return defined( 'POOLHALL_BUILDER_FORCE' ) || (bool) getenv( 'POOLHALL_BUILDER_FORCE' );
	}
}

if ( ! function_exists( 'poolhall_builder_may_write' ) ) {
	/**
	 * True when it is safe to replace this document's Elementor data.
	 */
	function poolhall_builder_may_write( int $post_id, string $label = '' ): bool {
		$label   = '' !== $label ? $label : (string) get_the_title( $post_id );
		$current = (string) get_post_meta( $post_id, '_elementor_data', true );

		if ( '' === trim( $current ) || '[]' === trim( $current ) ) {
			return true; // nothing to lose
		}

		$stamped = (string) get_post_meta( $post_id, POOLHALL_BUILDER_HASH_KEY, true );
		if ( '' !== $stamped && hash( 'sha256', $current ) === $stamped ) {
			return true; // still exactly what the builder last wrote
		}

		if ( poolhall_builder_forced() ) {
			poolhall_builder_log( sprintf( 'FORCED overwrite of #%d (%s).', $post_id, $label ) );
			return true;
		}

		poolhall_builder_log(
			sprintf(
				'SKIPPED #%d (%s) - edited since the builder last wrote it. Re-run with POOLHALL_BUILDER_FORCE=1 to overwrite.',
				$post_id,
				$label
			)
		);
		return false;
	}
}

if ( ! function_exists( 'poolhall_builder_stamp' ) ) {
	/** Record what the builder just wrote, so the next run can detect edits. */
	function poolhall_builder_stamp( int $post_id ): void {
		$current = (string) get_post_meta( $post_id, '_elementor_data', true );
		update_post_meta( $post_id, POOLHALL_BUILDER_HASH_KEY, hash( 'sha256', $current ) );
	}
}
