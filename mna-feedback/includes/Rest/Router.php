<?php
/**
 * REST API bootstrap and the protections shared by every endpoint.
 *
 * Every mna-feedback/v1 response is private and uncacheable. Requests from
 * other origins (including sibling subdomains) are refused, JSONP is disabled,
 * and state-changing requests must carry a custom header that browsers only
 * allow same-origin pages to send.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Rest;

use MNA\Feedback\Access\Actor;
use MNA\Feedback\Access\Auth;
use MNA\Feedback\Access\RateLimit;
use MNA\Feedback\Capabilities;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Router {

	public const NS = 'mna-feedback/v1';

	/** Header required on every non-GET request to this namespace. */
	public const CLIENT_HEADER = 'x-mnafb-client';

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register' ) );
		add_filter( 'rest_pre_dispatch', array( self::class, 'guard' ), 5, 3 );
		add_filter( 'rest_post_dispatch', array( self::class, 'private_headers' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( self::class, 'serve_raw' ), 20, 4 );
	}

	public static function register(): void {
		SessionController::register();
		ItemsController::register();
		AdminController::register();
	}

	public static function is_ours( WP_REST_Request $request ): bool {
		return str_starts_with( ltrim( $request->get_route(), '/' ), self::NS );
	}

	/**
	 * Refuses cross-origin, cross-site and JSONP requests before any callback runs.
	 *
	 * @param mixed $result
	 */
	public static function guard( $result, WP_REST_Server $server, WP_REST_Request $request ) {
		if ( null !== $result || ! self::is_ours( $request ) ) {
			return $result;
		}
		self::no_cache();

		if ( isset( $_GET['_jsonp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::error( 'mnafb_jsonp', __( 'JSONP is not supported.', 'mna-feedback' ), 400 );
		}

		$site = strtolower( (string) $request->get_header( 'sec_fetch_site' ) );
		if ( '' !== $site && ! in_array( $site, array( 'same-origin', 'none' ), true ) ) {
			return self::error( 'mnafb_cross_site', __( 'Cross-site requests are not allowed.', 'mna-feedback' ), 403 );
		}

		$origin = (string) $request->get_header( 'origin' );
		if ( '' !== $origin && ! self::origin_allowed( $origin ) ) {
			return self::error( 'mnafb_cross_origin', __( 'Cross-origin requests are not allowed.', 'mna-feedback' ), 403 );
		}

		$method = strtoupper( $request->get_method() );
		if ( ! in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) && '1' !== (string) $request->get_header( self::CLIENT_HEADER ) ) {
			return self::error( 'mnafb_missing_header', __( 'This request is missing the X-MNAFB-Client header.', 'mna-feedback' ), 400 );
		}

		return $result;
	}

	/**
	 * Origins allowed to call the API: the site itself and its admin.
	 */
	public static function origin_allowed( string $origin ): bool {
		$allowed = array();
		foreach ( array( home_url( '/' ), site_url( '/' ) ) as $url ) {
			$normal = self::origin_of( $url );
			if ( $normal ) {
				$allowed[] = $normal;
			}
		}
		/**
		 * Additional origins (scheme://host[:port]) allowed to use the API,
		 * for example extra domains mapped to the same site.
		 *
		 * @param string[] $allowed
		 */
		$allowed = (array) apply_filters( 'mnafb_allowed_origins', $allowed );
		$origin  = self::origin_of( $origin );
		return null !== $origin && in_array( $origin, array_map( array( self::class, 'origin_of' ), $allowed ), true );
	}

	public static function origin_of( string $url ): ?string {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}
		$scheme  = strtolower( $parts['scheme'] );
		$default = 'https' === $scheme ? 443 : 80;
		$port    = isset( $parts['port'] ) && (int) $parts['port'] !== $default ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . strtolower( $parts['host'] ) . $port;
	}

	/**
	 * @param mixed $response
	 */
	public static function private_headers( $response, WP_REST_Server $server, WP_REST_Request $request ) {
		if ( ! self::is_ours( $request ) || ! $response instanceof WP_HTTP_Response ) {
			return $response;
		}
		$response->header( 'Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', '0' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		$response->header( 'Vary', 'Cookie', false );
		return $response;
	}

	/**
	 * Sends CSV exports as-is instead of JSON-encoding them.
	 *
	 * @param bool  $served
	 * @param mixed $result
	 */
	public static function serve_raw( $served, $result, WP_REST_Request $request, WP_REST_Server $server ) {
		if ( $served || ! self::is_ours( $request ) || ! $result instanceof WP_HTTP_Response ) {
			return $served;
		}
		$headers = $result->get_headers();
		$type    = (string) ( $headers['Content-Type'] ?? '' );
		if ( str_starts_with( $type, 'text/csv' ) && is_string( $result->get_data() ) ) {
			echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV body, sanitised in Export.
			return true;
		}
		return $served;
	}

	/**
	 * Tells page and object caches never to store this response.
	 */
	public static function no_cache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'mna-feedback private data' );
	}

	/* ---------------------------------------------------------------------
	 * Permission callbacks
	 * ------------------------------------------------------------------ */

	public static function can_read(): true|WP_Error {
		return Auth::current() ? true : self::unauthorized();
	}

	public static function can_write( WP_REST_Request $request ): true|WP_Error {
		$actor = Auth::current();
		if ( ! $actor ) {
			return self::unauthorized();
		}
		if ( ! Auth::verify_write( $request ) ) {
			return self::error( 'mnafb_bad_token', __( 'Your review session needs refreshing. Reload the page and try again.', 'mna-feedback' ), 403 );
		}
		if ( ! RateLimit::allow( $actor ) ) {
			return self::error( 'mnafb_rate_limited', __( 'Too many changes in a short time. Wait a few minutes and try again.', 'mna-feedback' ), 429 );
		}
		return true;
	}

	public static function can_manage( WP_REST_Request $request ): true|WP_Error {
		$actor = Auth::current();
		if ( ! $actor ) {
			return self::unauthorized();
		}
		if ( ! $actor->is_manager() || $actor->is_guest() ) {
			return self::error( 'mnafb_forbidden', __( 'Only feedback managers can do that.', 'mna-feedback' ), 403 );
		}
		return true;
	}

	public static function actor(): Actor {
		$actor = Auth::current();
		if ( ! $actor ) {
			// Permission callbacks run first, so this is unreachable in practice.
			throw new \RuntimeException( 'No feedback actor.' );
		}
		return $actor;
	}

	/**
	 * 401 with hints the interface uses to recover: whether a WordPress login
	 * cookie is present (fetch a REST nonce and retry) or the user is signed in
	 * without a feedback role.
	 */
	public static function unauthorized( array $extra = array() ): WP_Error {
		$data = array( 'status' => 401 );
		if ( is_user_logged_in() ) {
			$data['no_role'] = true;
		} elseif ( self::cookie_user_has_role() ) {
			$data['wp_login'] = true;
		}
		return new WP_Error(
			'mnafb_unauthorized',
			__( 'Your review session has ended.', 'mna-feedback' ),
			array_merge( $data, $extra )
		);
	}

	/**
	 * Whether the browser carries a WordPress login cookie for someone with a
	 * feedback role. REST requests without a nonce are treated as logged out,
	 * so this only tells the interface to fetch a nonce - it grants nothing.
	 */
	public static function cookie_user_has_role(): bool {
		$id = Auth::cookie_user_id();
		if ( ! $id ) {
			return false;
		}
		$user = get_user_by( 'id', $id );
		return $user && null !== Capabilities::role_for_user( $user );
	}

	public static function error( string $code, string $message, int $status, array $data = array() ): WP_Error {
		return new WP_Error( $code, $message, array_merge( array( 'status' => $status ), $data ) );
	}

	/**
	 * Converts an ISO 8601 or MySQL timestamp to a UTC MySQL datetime.
	 */
	public static function mysql_time( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$ts = strtotime( $value );
		return false === $ts ? null : gmdate( 'Y-m-d H:i:s', $ts );
	}
}
