<?php
/**
 * A log of addresses that returned "page not found", so broken links and
 * old URLs (for example from a previous website) can be found and redirected.
 * Kept small: the 300 most recent distinct addresses, not autoloaded.
 */

defined( 'ABSPATH' ) || exit;

const PHSEO_404_LOG = 'phseo_404_log';
const PHSEO_404_MAX = 300;

function phseo_404_log(): array {
	$log = get_option( PHSEO_404_LOG, array() );
	return is_array( $log ) ? $log : array();
}

add_action( 'template_redirect', static function (): void {
	if ( ! is_404() || is_admin() ) {
		return;
	}
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = phseo_normalise_path( wp_parse_url( $uri, PHP_URL_PATH ) );
	// Probes for files that never existed here are noise, not broken links.
	if ( preg_match( '#^/(wp-content|wp-includes)/|\.(php|env|ini|sql|bak|zip|gz|asp|aspx|cgi)$|/\.#i', $path ) ) {
		return;
	}
	$log = phseo_404_log();
	$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	$entry = $log[ $path ] ?? array( 'hits' => 0, 'first' => time(), 'referrer' => '' );
	$entry['hits']++;
	$entry['last'] = time();
	if ( $ref && false === strpos( $ref, (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
		$entry['referrer'] = substr( $ref, 0, 300 );
	}
	$log[ $path ] = $entry;
	if ( count( $log ) > PHSEO_404_MAX ) {
		uasort( $log, static fn( $a, $b ) => ( $b['last'] ?? 0 ) <=> ( $a['last'] ?? 0 ) );
		$log = array_slice( $log, 0, PHSEO_404_MAX, true );
	}
	update_option( PHSEO_404_LOG, $log, false );
}, 99 );

/** Once an address has a redirect it is no longer broken. */
function phseo_404_forget( string $path ): void {
	$log = phseo_404_log();
	if ( isset( $log[ $path ] ) ) {
		unset( $log[ $path ] );
		update_option( PHSEO_404_LOG, $log, false );
	}
}
