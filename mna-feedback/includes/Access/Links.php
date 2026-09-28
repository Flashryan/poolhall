<?php
/**
 * Shared review links.
 *
 * A link grants reviewer access to anyone who opens it and enters their name.
 * Managers can set an expiry, revoke a link (ending every session that joined
 * through it) or replace its token (the old address stops working; people
 * already reviewing keep going).
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Access;

use MNA\Feedback\Crypto;
use MNA\Feedback\Schema;

defined( 'ABSPATH' ) || exit;

final class Links {

	public const QUERY_JOIN   = 'mna-review';
	public const QUERY_RETURN = 'mna-return';

	/**
	 * @return array{link: object, token: string}
	 */
	public static function create( string $label, ?string $expires_at, string $landing_url, int $created_by ): array {
		global $wpdb;
		$token = Crypto::token();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert(
			Schema::table( 'links' ),
			array(
				'uuid'        => Crypto::uuid(),
				'label'       => mb_substr( $label, 0, 120 ),
				'token_hash'  => Crypto::hash( $token ),
				'token_enc'   => Crypto::encrypt( $token ),
				'landing_url' => $landing_url,
				'created_by'  => $created_by,
				'created_at'  => $now,
				'expires_at'  => $expires_at,
			)
		);
		return array(
			'link'  => self::get( (int) $wpdb->insert_id ),
			'token' => $token,
		);
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		$table = Schema::table( 'links' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function find_by_token( string $token ): ?object {
		if ( ! Crypto::is_token( $token ) ) {
			return null;
		}
		global $wpdb;
		$table = Schema::table( 'links' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", Crypto::hash( $token ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ?: null;
	}

	/**
	 * @return object[]
	 */
	public static function all(): array {
		global $wpdb;
		$table = Schema::table( 'links' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY revoked_at IS NULL DESC, created_at DESC" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function is_active( ?object $link ): bool {
		if ( ! $link || ! empty( $link->revoked_at ) ) {
			return false;
		}
		if ( ! empty( $link->expires_at ) && strtotime( $link->expires_at . ' UTC' ) <= time() ) {
			return false;
		}
		return true;
	}

	public static function status( object $link ): string {
		if ( ! empty( $link->revoked_at ) ) {
			return 'revoked';
		}
		if ( ! empty( $link->expires_at ) && strtotime( $link->expires_at . ' UTC' ) <= time() ) {
			return 'expired';
		}
		return 'active';
	}

	/**
	 * The shareable address, or null if the stored token can no longer be decrypted.
	 */
	public static function url( object $link, ?string $token = null ): ?string {
		$token = $token ?? Crypto::decrypt( $link->token_enc ?? null );
		if ( ! $token ) {
			return null;
		}
		$base = $link->landing_url ?: home_url( '/' );
		return add_query_arg( self::QUERY_JOIN, $token, $base );
	}

	public static function update( int $id, array $fields ): void {
		global $wpdb;
		$data = array();
		if ( array_key_exists( 'label', $fields ) ) {
			$data['label'] = mb_substr( (string) $fields['label'], 0, 120 );
		}
		if ( array_key_exists( 'expires_at', $fields ) ) {
			$data['expires_at'] = $fields['expires_at'];
		}
		if ( array_key_exists( 'landing_url', $fields ) ) {
			$data['landing_url'] = (string) $fields['landing_url'];
		}
		if ( $data ) {
			$wpdb->update( Schema::table( 'links' ), $data, array( 'id' => $id ) );
		}
	}

	/**
	 * Replaces the token. Returns the new token.
	 */
	public static function rotate( int $id ): ?string {
		global $wpdb;
		if ( ! self::get( $id ) ) {
			return null;
		}
		$token = Crypto::token();
		$wpdb->update(
			Schema::table( 'links' ),
			array(
				'token_hash' => Crypto::hash( $token ),
				'token_enc'  => Crypto::encrypt( $token ),
				'rotated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
		return $token;
	}

	public static function revoke( int $id ): void {
		global $wpdb;
		$wpdb->update(
			Schema::table( 'links' ),
			array( 'revoked_at' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => $id )
		);
		Sessions::revoke_for_link( $id );
	}

	public static function reactivate( int $id ): void {
		global $wpdb;
		$wpdb->update( Schema::table( 'links' ), array( 'revoked_at' => null ), array( 'id' => $id ) );
	}

	public static function record_use( int $id ): void {
		global $wpdb;
		$table = Schema::table( 'links' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET use_count = use_count + 1, last_used_at = %s WHERE id = %d", gmdate( 'Y-m-d H:i:s' ), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Number of reviewer identities that joined through each link.
	 *
	 * @return array<int, int>
	 */
	public static function reviewer_counts(): array {
		global $wpdb;
		$table = Schema::table( 'reviewers' );
		$rows  = $wpdb->get_results( "SELECT link_id, COUNT(*) AS n FROM {$table} WHERE link_id > 0 GROUP BY link_id" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->link_id ] = (int) $row->n;
		}
		return $out;
	}
}
