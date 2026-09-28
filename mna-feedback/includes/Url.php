<?php
/**
 * Page address normalisation.
 *
 * Feedback is grouped by page. Two addresses count as the same page when they
 * share host, path and the query parameters that identify content (plain
 * permalinks, search, WooCommerce products). Tracking parameters, filters,
 * sorting, fragments and the plugin's own parameters are ignored, and a
 * trailing slash makes no difference. The server is the single source of truth
 * for this: the interface asks the API for the current page's key.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Url {

	private const CONTENT_PARAMS = array(
		'p',
		'page_id',
		'preview_id',
		'post_type',
		'attachment_id',
		'cat',
		'tag',
		'author',
		'm',
		's',
		'paged',
		'product',
		'product_cat',
		'product_tag',
		'lang',
	);

	/**
	 * The normalised absolute URL, or null when it is not an address on this site.
	 */
	public static function normalize( string $url ): ?string {
		$url   = trim( $url );
		$parts = wp_parse_url( $url );
		if ( ! $parts || empty( $parts['host'] ) ) {
			return null;
		}
		$scheme = strtolower( $parts['scheme'] ?? 'https' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}
		$host = strtolower( $parts['host'] );
		if ( ! self::is_site_host( $host ) ) {
			return null;
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = $parts['path'] ?? '/';
		$path = '/' . ltrim( $path, '/' );
		$path = preg_replace( '#/+#', '/', $path );
		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' ) . '/';
		}

		$query = '';
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $params );
			$kept = array();
			foreach ( self::CONTENT_PARAMS as $key ) {
				if ( isset( $params[ $key ] ) && is_scalar( $params[ $key ] ) && '' !== (string) $params[ $key ] ) {
					$kept[ $key ] = (string) $params[ $key ];
				}
			}
			ksort( $kept );
			$query = $kept ? '?' . http_build_query( $kept, '', '&', PHP_QUERY_RFC3986 ) : '';
		}

		return $scheme . '://' . $host . $port . $path . $query;
	}

	/**
	 * Stable key for grouping feedback by page (scheme-insensitive).
	 */
	public static function key( string $normalized ): string {
		$without_scheme = preg_replace( '#^https?://#i', '', $normalized );
		return hash( 'sha256', (string) $without_scheme );
	}

	private static function is_site_host( string $host ): bool {
		$home = wp_parse_url( home_url() );
		$site = wp_parse_url( site_url() );
		$allowed = array_filter(
			array(
				strtolower( $home['host'] ?? '' ),
				strtolower( $site['host'] ?? '' ),
			)
		);
		/**
		 * Additional hosts that count as this site (for example a staging mirror
		 * sharing the same database).
		 */
		$allowed = (array) apply_filters( 'mnafb_allowed_hosts', $allowed );
		return in_array( $host, $allowed, true );
	}
}
