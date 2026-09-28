<?php
/**
 * Resolves who is making a request.
 *
 * 1. A logged-in WordPress user holding a feedback capability (REST requests
 *    must carry the usual wp_rest nonce, otherwise WordPress treats them as
 *    logged out).
 * 2. Otherwise a valid guest review session.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Access;

use MNA\Feedback\Capabilities;
use MNA\Feedback\Data\Reviewers;

defined( 'ABSPATH' ) || exit;

final class Auth {

	private static bool $resolved = false;
	private static ?Actor $current = null;

	public static function current(): ?Actor {
		if ( self::$resolved ) {
			return self::$current;
		}
		self::$resolved = true;

		$user = wp_get_current_user();
		if ( $user->exists() ) {
			$actor = self::for_user( $user );
			if ( $actor ) {
				self::$current = $actor;
				return $actor;
			}
		}

		$session = Sessions::current();
		if ( $session ) {
			$reviewer = Reviewers::get( (int) $session->reviewer_id );
			if ( $reviewer && 'active' === $reviewer->status ) {
				self::$current = new Actor(
					(int) $reviewer->id,
					(string) $reviewer->type,
					0,
					'reviewer',
					(string) $reviewer->display_name,
					(string) $reviewer->created_at,
					(int) $session->id,
					(int) $session->link_id
				);
			}
		}
		return self::$current;
	}

	/**
	 * The actor for a WordPress user with a feedback role, or null.
	 */
	public static function for_user( \WP_User $user, string $source = 'ui' ): ?Actor {
		$role = Capabilities::role_for_user( $user );
		if ( ! $role ) {
			return null;
		}
		$reviewer = Reviewers::for_wp_user( $user );
		if ( ! $reviewer || 'active' !== $reviewer->status ) {
			return null;
		}
		return new Actor(
			(int) $reviewer->id,
			'wp_user',
			(int) $user->ID,
			$role,
			(string) $reviewer->display_name,
			(string) $reviewer->created_at,
			0,
			0,
			$source
		);
	}

	/**
	 * Detects a logged-in WordPress user from the auth cookie even when the REST
	 * API has treated the request as anonymous because no nonce was sent. Used
	 * only to tell the interface to fetch a nonce and retry - never to grant access.
	 */
	public static function cookie_user_id(): int {
		$id = wp_validate_auth_cookie( '', 'logged_in' );
		return $id ? (int) $id : 0;
	}

	/**
	 * Writes from guest sessions must echo the per-session CSRF token.
	 */
	public static function verify_write( \WP_REST_Request $request ): bool {
		$actor = self::current();
		if ( ! $actor ) {
			return false;
		}
		if ( ! $actor->is_guest() ) {
			return true; // WordPress already verified the wp_rest nonce for cookie-authenticated users.
		}
		$session = Sessions::current();
		if ( ! $session ) {
			return false;
		}
		$sent = (string) $request->get_header( 'x-mnafb-token' );
		return '' !== $sent && hash_equals( Sessions::csrf_token( $session ), $sent );
	}

	public static function reset(): void {
		self::$resolved = false;
		self::$current  = null;
		Sessions::reset();
	}
}
