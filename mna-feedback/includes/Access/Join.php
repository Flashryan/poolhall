<?php
/**
 * The step between opening a shared link and entering a name.
 *
 * Opening a link stores its token in an HttpOnly "pending" cookie and strips
 * it from the address bar. The interface then asks for a name; joining turns
 * the pending token into a reviewer identity and a session. When a link or
 * return link turns out to be unusable, a short marker is stored instead so
 * the interface can explain why.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Access;

use MNA\Feedback\Crypto;

defined( 'ABSPATH' ) || exit;

final class Join {

	public const REASONS = array( 'invalid', 'expired', 'revoked', 'return', 'blocked' );

	/**
	 * @return array{state: string, reason: string, link: ?object}
	 */
	public static function pending(): array {
		$value = Cookies::get( Cookies::PENDING );
		if ( null === $value ) {
			return array(
				'state'  => 'none',
				'reason' => '',
				'link'   => null,
			);
		}
		if ( ! Crypto::is_token( $value ) ) {
			$reason = str_starts_with( $value, 'x-' ) ? substr( $value, 2 ) : 'invalid';
			return array(
				'state'  => 'invalid',
				'reason' => in_array( $reason, self::REASONS, true ) ? $reason : 'invalid',
				'link'   => null,
			);
		}
		$link = Links::find_by_token( $value );
		if ( ! $link ) {
			return array(
				'state'  => 'invalid',
				'reason' => 'invalid',
				'link'   => null,
			);
		}
		$status = Links::status( $link );
		if ( 'active' !== $status ) {
			return array(
				'state'  => 'invalid',
				'reason' => $status,
				'link'   => $link,
			);
		}
		return array(
			'state'  => 'valid',
			'reason' => '',
			'link'   => $link,
		);
	}

	public static function set_pending( string $token ): void {
		Cookies::set( Cookies::PENDING, $token, time() + DAY_IN_SECONDS );
		Cookies::set_flag( true );
	}

	public static function mark_invalid( string $reason ): void {
		$reason = in_array( $reason, self::REASONS, true ) ? $reason : 'invalid';
		Cookies::set( Cookies::PENDING, 'x-' . $reason, time() + 15 * MINUTE_IN_SECONDS );
		Cookies::set_flag( true );
	}

	public static function clear(): void {
		if ( null !== Cookies::get( Cookies::PENDING ) ) {
			Cookies::clear( Cookies::PENDING );
		}
	}

	/**
	 * Limits how many identities one network address can create per hour
	 * through shared links.
	 */
	public static function allow_from_ip(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' === $ip ) {
			return true;
		}
		$key   = 'mnafb_join_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
		$count = (int) get_transient( $key );
		/** Maximum identities created per address per hour. */
		$limit = (int) apply_filters( 'mnafb_joins_per_hour', 30 );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}
}
