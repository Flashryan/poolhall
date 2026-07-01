<?php
/**
 * Post-login return destination allowlist.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Portal spec §5: preserve only allowlisted same-origin return
 * destinations. Only relative paths inside the candidate portal or the
 * jobs area survive; everything else — absolute URLs, protocol-relative
 * tricks, traversal, control characters — falls back to the dashboard.
 * Pure string logic so the rules are unit-tested.
 */
final class ReturnUrlPolicy {

	public const DASHBOARD = '/candidate/';

	/** Signed-out saves and guarded portal pages both return here (spec §2/§7). */
	private const ALLOWED_PREFIXES = array( '/candidate/', '/jobs/' );

	public function sanitize( string $raw ): string {
		$candidate = trim( rawurldecode( $raw ) );

		if ( '' === $candidate || 1 === preg_match( '/[\x00-\x1f\x7f]/', $candidate ) ) {
			return self::DASHBOARD;
		}
		// Same-origin means relative-only: no scheme, no host, and none of
		// the protocol-relative ('//host') or backslash ('/\host') forms
		// browsers treat as absolute.
		if ( ! str_starts_with( $candidate, '/' )
			|| str_starts_with( $candidate, '//' )
			|| str_starts_with( $candidate, '/\\' )
			|| str_contains( $candidate, '://' )
			|| str_contains( $candidate, '..' )
		) {
			return self::DASHBOARD;
		}

		$path = (string) ( parse_url( $candidate, PHP_URL_PATH ) ?? '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure domain layer, unit-tested without WordPress.
		foreach ( self::ALLOWED_PREFIXES as $prefix ) {
			if ( str_starts_with( $path, $prefix ) || rtrim( $prefix, '/' ) === $path ) {
				return $candidate;
			}
		}
		return self::DASHBOARD;
	}
}
