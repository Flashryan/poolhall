<?php
/**
 * Guest review sessions.
 *
 * The browser holds a random token in an HttpOnly cookie; the database holds
 * only its hash. A session is valid while it is unexpired and unrevoked, its
 * reviewer is not blocked, and the link it joined through is still active -
 * revoking a link therefore ends every session created from it.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Access;

use MNA\Feedback\Crypto;
use MNA\Feedback\Data\Reviewers;
use MNA\Feedback\Schema;
use MNA\Feedback\Settings;

defined( 'ABSPATH' ) || exit;

final class Sessions {

	private static bool $resolved = false;
	private static ?object $current = null;

	public static function create( int $reviewer_id, int $link_id ): object {
		global $wpdb;
		$token   = Crypto::token();
		$days    = (int) Settings::get( 'session_days' );
		$now     = time();
		$expires = $now + $days * DAY_IN_SECONDS;
		$agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';

		$wpdb->insert(
			Schema::table( 'sessions' ),
			array(
				'token_hash'   => Crypto::hash( $token ),
				'reviewer_id'  => $reviewer_id,
				'link_id'      => $link_id,
				'user_agent'   => $agent,
				'created_at'   => gmdate( 'Y-m-d H:i:s', $now ),
				'last_seen_at' => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'   => gmdate( 'Y-m-d H:i:s', $expires ),
			)
		);

		Cookies::set( Cookies::SESSION, $token, $expires );
		Cookies::set_flag( true );

		self::$resolved = false;
		self::$current  = null;
		return self::current();
	}

	/**
	 * The valid session for this request, if any.
	 */
	public static function current(): ?object {
		if ( self::$resolved ) {
			return self::$current;
		}
		self::$resolved = true;

		$token = Cookies::get( Cookies::SESSION );
		if ( ! $token || ! Crypto::is_token( $token ) ) {
			return null;
		}

		global $wpdb;
		$table   = Schema::table( 'sessions' );
		$session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", Crypto::hash( $token ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $session || ! self::is_valid( $session ) ) {
			return null;
		}

		// Sliding expiry, written at most every ten minutes.
		if ( strtotime( $session->last_seen_at . ' UTC' ) < time() - 10 * MINUTE_IN_SECONDS ) {
			$days    = (int) Settings::get( 'session_days' );
			$expires = time() + $days * DAY_IN_SECONDS;
			$wpdb->update(
				$table,
				array(
					'last_seen_at' => gmdate( 'Y-m-d H:i:s' ),
					'expires_at'   => gmdate( 'Y-m-d H:i:s', $expires ),
				),
				array( 'id' => $session->id )
			);
			Cookies::set( Cookies::SESSION, $token, $expires );
			Reviewers::touch( (int) $session->reviewer_id );
		}

		self::$current = $session;
		return $session;
	}

	public static function is_valid( object $session ): bool {
		if ( ! empty( $session->revoked_at ) ) {
			return false;
		}
		if ( strtotime( $session->expires_at . ' UTC' ) <= time() ) {
			return false;
		}
		$reviewer = Reviewers::get( (int) $session->reviewer_id );
		if ( ! $reviewer || 'active' !== $reviewer->status ) {
			return false;
		}
		if ( (int) $session->link_id > 0 && ! Links::is_active( Links::get( (int) $session->link_id ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Per-session secret the interface sends back in the X-MNAFB-Token header on
	 * every write. Browsers already withhold SameSite=Lax cookies from
	 * cross-site requests; this is a second, independent check.
	 */
	public static function csrf_token( object $session ): string {
		return hash_hmac( 'sha256', 'csrf|' . $session->token_hash, wp_salt( 'nonce' ) );
	}

	public static function end_current(): void {
		$session = self::current();
		if ( $session ) {
			self::revoke( (int) $session->id );
		}
		Cookies::clear( Cookies::SESSION );
		Cookies::set_flag( false );
		self::$resolved = true;
		self::$current  = null;
	}

	public static function revoke( int $id ): void {
		global $wpdb;
		$wpdb->update( Schema::table( 'sessions' ), array( 'revoked_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id ) );
	}

	public static function revoke_for_link( int $link_id ): void {
		global $wpdb;
		$table = Schema::table( 'sessions' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET revoked_at = %s WHERE link_id = %d AND revoked_at IS NULL", gmdate( 'Y-m-d H:i:s' ), $link_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function revoke_for_reviewer( int $reviewer_id ): void {
		global $wpdb;
		$table = Schema::table( 'sessions' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET revoked_at = %s WHERE reviewer_id = %d AND revoked_at IS NULL", gmdate( 'Y-m-d H:i:s' ), $reviewer_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Daily housekeeping: drop sessions that ended more than a week ago.
	 */
	public static function cleanup(): void {
		global $wpdb;
		$table  = Schema::table( 'sessions' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s OR (revoked_at IS NOT NULL AND revoked_at < %s)", $cutoff, $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Forget the per-request cache (after a join or sign-out inside one request).
	 */
	public static function reset(): void {
		self::$resolved = false;
		self::$current  = null;
	}
}
