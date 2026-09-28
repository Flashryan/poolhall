<?php
/**
 * Cookie helpers.
 *
 * - mnafb_s  HttpOnly session token for guest reviewers.
 * - mnafb_p  HttpOnly pending-join token, set when a shared link is opened and
 *            consumed once the reviewer enters their name.
 * - mnafb_on Non-secret flag ("1") read by the public bootstrap script to decide
 *            whether to load the review interface. It grants nothing on its own.
 *
 * All are SameSite=Lax and Secure on HTTPS. On a multisite network the names
 * carry the site ID (mnafb_s_2), because sites often share a cookie path or
 * domain: reviewing one site must not sign you out of another or switch review
 * mode on elsewhere.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Access;

defined( 'ABSPATH' ) || exit;

final class Cookies {

	public const SESSION = 'mnafb_s';
	public const PENDING = 'mnafb_p';
	public const FLAG    = 'mnafb_on';

	/**
	 * The cookie name used on this site.
	 */
	public static function name( string $base ): string {
		return is_multisite() ? $base . '_' . get_current_blog_id() : $base;
	}

	public static function set( string $base, string $value, int $expires, bool $http_only = true ): void {
		$name = self::name( $base );
		if ( ! headers_sent() ) {
			setcookie(
				$name,
				$value,
				array(
					'expires'  => $expires,
					'path'     => self::path(),
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => $http_only,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ $name ] = $value;
	}

	public static function clear( string $base, bool $http_only = true ): void {
		self::set( $base, '', time() - YEAR_IN_SECONDS, $http_only );
		unset( $_COOKIE[ self::name( $base ) ] );
	}

	public static function get( string $base ): ?string {
		$name = self::name( $base );
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return null;
		}
		$value = wp_unslash( $_COOKIE[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return '' === $value ? null : $value;
	}

	public static function set_flag( bool $on ): void {
		if ( $on ) {
			self::set( self::FLAG, '1', time() + YEAR_IN_SECONDS, false );
		} else {
			self::clear( self::FLAG, false );
		}
	}

	public static function path(): string {
		return defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
	}
}
