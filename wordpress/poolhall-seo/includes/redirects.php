<?php
/**
 * Redirects, issued as real 301s before WordPress renders anything.
 *
 * Guards that matter more than the feature:
 *  - admin, login, AJAX, cron, REST and CLI requests are never redirected,
 *    so a bad rule can never lock anyone out;
 *  - a rule that leads back to where it started (directly or through other
 *    rules) is refused when saved and skipped when matched;
 *  - everything can be switched off with one setting without deleting rules.
 *
 * Rules live in one autoloaded option, so matching costs no database query.
 */

defined( 'ABSPATH' ) || exit;

const PHSEO_REDIRECTS         = 'phseo_redirects';
const PHSEO_REDIRECTS_ENABLED = 'phseo_redirects_enabled';
const PHSEO_REDIRECTS_MAX     = 2000;

/** Path only, decoded, without a trailing slash, so /a and /a/ are one rule. */
function phseo_normalise_path( $path ): string {
	$path = '/' . ltrim( rawurldecode( (string) $path ), '/' );
	if ( strlen( $path ) > 1 ) {
		$path = rtrim( $path, '/' );
	}
	return '' === $path ? '/' : $path;
}

/** Paths no rule may ever cover. */
function phseo_redirect_is_protected( string $path ): bool {
	$path = phseo_normalise_path( $path );
	foreach ( array( '/wp-admin', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php', '/wp-json' ) as $prefix ) {
		if ( $path === $prefix || 0 === strpos( $path . '/', $prefix . '/' ) ) {
			return true;
		}
	}
	return false;
}

function phseo_redirect_should_skip(): bool {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return true;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	return phseo_redirect_is_protected( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
}

function phseo_redirects(): array {
	$rules = get_option( PHSEO_REDIRECTS, array() );
	return is_array( $rules ) ? $rules : array();
}

/** A rule for /folder/* covers /folder and everything inside it. A bare /* is never allowed. */
function phseo_redirect_is_wildcard( string $key ): bool {
	return strlen( $key ) > 2 && '/*' === substr( $key, -2 );
}

function phseo_redirect_covers( string $key, string $path ): bool {
	if ( $key === $path ) {
		return true;
	}
	if ( ! phseo_redirect_is_wildcard( $key ) ) {
		return false;
	}
	$base = substr( $key, 0, -2 );
	return $path === $base || 0 === strpos( $path, $base . '/' );
}

/** The exact rule for a path, else the wildcard for the deepest folder containing it. */
function phseo_redirect_match( string $path, array $rules ): ?array {
	if ( isset( $rules[ $path ] ) && is_array( $rules[ $path ] ) ) {
		return $rules[ $path ];
	}
	$best   = null;
	$length = -1;
	foreach ( $rules as $key => $rule ) {
		$key = (string) $key;
		if ( is_array( $rule ) && phseo_redirect_is_wildcard( $key ) && phseo_redirect_covers( $key, $path ) && strlen( $key ) > $length ) {
			$best   = $rule;
			$length = strlen( $key );
		}
	}
	return $best;
}

function phseo_host_key( $host ): string {
	$host = strtolower( (string) $host );
	return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
}

/** If following $from -> $to through $rules comes back to $from, the chain of paths; else null. */
function phseo_redirect_loop( string $from, string $to, array $rules ): ?array {
	$own   = phseo_host_key( wp_parse_url( home_url(), PHP_URL_HOST ) );
	$chain = array( $from );
	$next  = $to;
	for ( $hop = 0; $hop < 50; $hop++ ) {
		$host = wp_parse_url( $next, PHP_URL_HOST );
		if ( $host && phseo_host_key( $host ) !== $own ) {
			return null;
		}
		$step    = phseo_normalise_path( wp_parse_url( $next, PHP_URL_PATH ) );
		$chain[] = $step;
		if ( phseo_redirect_covers( $from, $step ) ) {
			return $chain;
		}
		$rule = phseo_redirect_match( $step, $rules );
		if ( ! $rule || empty( $rule['to'] ) ) {
			return null;
		}
		$next = (string) $rule['to'];
	}
	return $chain;
}

add_action( 'init', static function (): void {
	if ( '1' !== get_option( PHSEO_REDIRECTS_ENABLED, '1' ) || phseo_redirect_should_skip() ) {
		return;
	}
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = phseo_normalise_path( wp_parse_url( $uri, PHP_URL_PATH ) );
	$rules = phseo_redirects();
	$rule = phseo_redirect_match( $path, $rules );
	if ( ! $rule || empty( $rule['to'] ) ) {
		return;
	}
	$to = (string) $rule['to'];
	if ( phseo_redirect_loop( $path, $to, $rules ) ) {
		return;
	}
	$query = wp_parse_url( $uri, PHP_URL_QUERY );
	if ( $query ) {
		$to .= ( false === strpos( $to, '?' ) ? '?' : '&' ) . $query;
	}
	$code = in_array( (int) ( $rule['code'] ?? 301 ), array( 301, 302, 307, 308 ), true ) ? (int) $rule['code'] : 301;
	// Recorded so the admin screen can show which rules are actually used.
	phseo_redirect_record_hit( $path, $rules );
	wp_redirect( esc_url_raw( $to ), $code, 'Poolhall SEO' );
	exit;
}, 1 );

/** Hit counts are kept apart from the rules so counting never rewrites the autoloaded option. */
function phseo_redirect_record_hit( string $path, array $rules ): void {
	$key  = isset( $rules[ $path ] ) ? $path : '';
	if ( '' === $key ) {
		foreach ( array_keys( $rules ) as $k ) {
			if ( phseo_redirect_covers( (string) $k, $path ) ) {
				$key = (string) $k;
				break;
			}
		}
	}
	$hits = get_option( 'phseo_redirect_hits', array() );
	$hits = is_array( $hits ) ? $hits : array();
	$hits[ $key ] = array( (int) ( $hits[ $key ][0] ?? 0 ) + 1, time() );
	update_option( 'phseo_redirect_hits', $hits, false );
}

/**
 * Adds or replaces one rule. Returns true, or a WP_Error saying why not.
 * Used by the admin screen and the CSV import.
 */
function phseo_redirect_save( string $from, string $to, int $code = 301 ) {
	$path = phseo_normalise_path( wp_parse_url( $from, PHP_URL_PATH ) ?: $from );
	if ( '/' === $path || '/*' === $path ) {
		return new WP_Error( 'phseo_root', 'The homepage cannot be redirected from here.' );
	}
	if ( phseo_redirect_is_protected( $path ) ) {
		return new WP_Error( 'phseo_protected', 'WordPress admin, login and API addresses cannot be redirected.' );
	}
	$to = trim( $to );
	if ( '' !== $to && '/' === $to[0] ) {
		$to = home_url( $to );
	}
	$to = esc_url_raw( $to );
	if ( '' === $to ) {
		return new WP_Error( 'phseo_to', 'The destination is not a valid address.' );
	}
	$rules = phseo_redirects();
	if ( ! isset( $rules[ $path ] ) && count( $rules ) >= PHSEO_REDIRECTS_MAX ) {
		return new WP_Error( 'phseo_max', sprintf( 'This site holds up to %d redirects.', PHSEO_REDIRECTS_MAX ) );
	}
	$candidate = $rules;
	$candidate[ $path ] = array( 'to' => $to, 'code' => $code );
	$loop = phseo_redirect_loop( $path, $to, $candidate );
	if ( $loop ) {
		return new WP_Error( 'phseo_loop', 'That would create a loop: ' . implode( ' → ', $loop ) );
	}
	$candidate[ $path ]['code'] = in_array( $code, array( 301, 302, 307, 308 ), true ) ? $code : 301;
	$candidate[ $path ]['added'] = $rules[ $path ]['added'] ?? time();
	ksort( $candidate );
	update_option( PHSEO_REDIRECTS, $candidate, true );
	return true;
}

function phseo_redirect_delete( string $path ): void {
	$rules = phseo_redirects();
	unset( $rules[ $path ] );
	update_option( PHSEO_REDIRECTS, $rules, true );
}

/** Other redirect layers on the site, which would compete for the same URLs. */
function phseo_other_redirect_plugins(): array {
	$active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	$known  = array(
		'redirection/redirection.php'                     => 'Redirection',
		'safe-redirect-manager/safe-redirect-manager.php' => 'Safe Redirect Manager',
		'simple-301-redirects/wp-simple-301-redirects.php' => 'Simple 301 Redirects',
		'eps-301-redirects/eps-301-redirects.php'         => 'EPS 301 Redirects',
	);
	return array_values( array_intersect_key( $known, array_flip( $active ) ) );
}
