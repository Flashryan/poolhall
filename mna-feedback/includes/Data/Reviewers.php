<?php
/**
 * Participant identities.
 *
 * Every person gets a distinct identity row. Guests are identified by their
 * session, never by name, so two people who both type "Sam" remain separate
 * and cannot edit each other's comments. WordPress users get one row each,
 * created on first use. A private return link lets a guest resume the same
 * identity on another device.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Data;

use MNA\Feedback\Access\Links;
use MNA\Feedback\Crypto;
use MNA\Feedback\Schema;

defined( 'ABSPATH' ) || exit;

final class Reviewers {

	private const PALETTE = array( '#4F46E5', '#0891B2', '#059669', '#D97706', '#DC2626', '#7C3AED', '#DB2777', '#2563EB', '#65A30D', '#EA580C', '#0D9488', '#9333EA' );

	/** @var array<int, object> */
	private static array $cache = array();

	public static function get( int $id ): ?object {
		if ( $id <= 0 ) {
			return null;
		}
		if ( ! array_key_exists( $id, self::$cache ) ) {
			global $wpdb;
			$table              = Schema::table( 'reviewers' );
			self::$cache[ $id ] = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return self::$cache[ $id ];
	}

	/**
	 * @param int[] $ids
	 * @return array<int, object>
	 */
	public static function get_many( array $ids ): array {
		$ids     = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$missing = array_values( array_diff( $ids, array_keys( self::$cache ) ) );
		if ( $missing ) {
			global $wpdb;
			$table        = Schema::table( 'reviewers' );
			$placeholders = implode( ',', array_fill( 0, count( $missing ), '%d' ) );
			$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", $missing ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $rows as $row ) {
				self::$cache[ (int) $row->id ] = $row;
			}
			foreach ( $missing as $id ) {
				self::$cache[ $id ] = self::$cache[ $id ] ?? null;
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( self::$cache[ $id ] ) {
				$out[ $id ] = self::$cache[ $id ];
			}
		}
		return $out;
	}

	/**
	 * @return array{reviewer: object, return_token: string}
	 */
	public static function create_guest( string $name, string $email, int $link_id ): array {
		global $wpdb;
		$uuid  = Crypto::uuid();
		$token = Crypto::token();
		$wpdb->insert(
			Schema::table( 'reviewers' ),
			array(
				'uuid'         => $uuid,
				'type'         => 'guest',
				'display_name' => $name,
				'email'        => $email,
				'link_id'      => $link_id,
				'return_hash'  => Crypto::hash( $token ),
				'return_enc'   => Crypto::encrypt( $token ),
				'color'        => self::color( $uuid ),
				'status'       => 'active',
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				'last_seen_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return array(
			'reviewer'     => self::get( (int) $wpdb->insert_id ),
			'return_token' => $token,
		);
	}

	/**
	 * The identity for a WordPress user, created on first use and kept in step
	 * with their display name and email.
	 */
	public static function for_wp_user( \WP_User $user ): ?object {
		global $wpdb;
		$table = Schema::table( 'reviewers' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE type = 'wp_user' AND wp_user_id = %d", $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$name  = mb_substr( $user->display_name ?: $user->user_login, 0, 80 );

		if ( ! $row ) {
			$uuid = Crypto::uuid();
			$wpdb->insert(
				$table,
				array(
					'uuid'         => $uuid,
					'type'         => 'wp_user',
					'wp_user_id'   => $user->ID,
					'display_name' => $name,
					'email'        => mb_substr( (string) $user->user_email, 0, 190 ),
					'color'        => self::color( $uuid ),
					'status'       => 'active',
					'created_at'   => gmdate( 'Y-m-d H:i:s' ),
					'last_seen_at' => gmdate( 'Y-m-d H:i:s' ),
				)
			);
			unset( self::$cache[ (int) $wpdb->insert_id ] );
			return self::get( (int) $wpdb->insert_id );
		}

		if ( $row->display_name !== $name || $row->email !== $user->user_email ) {
			$wpdb->update(
				$table,
				array(
					'display_name' => $name,
					'email'        => mb_substr( (string) $user->user_email, 0, 190 ),
				),
				array( 'id' => $row->id )
			);
			$row->display_name = $name;
			$row->email        = $user->user_email;
		}
		self::$cache[ (int) $row->id ] = $row;
		return $row;
	}

	public static function find_by_return_token( string $token ): ?object {
		if ( ! Crypto::is_token( $token ) ) {
			return null;
		}
		global $wpdb;
		$table = Schema::table( 'reviewers' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE type = 'guest' AND return_hash = %s", Crypto::hash( $token ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ?: null;
	}

	/**
	 * Issues a new private return link, invalidating the previous one.
	 */
	public static function rotate_return_token( int $id ): string {
		global $wpdb;
		$token = Crypto::token();
		$wpdb->update(
			Schema::table( 'reviewers' ),
			array(
				'return_hash' => Crypto::hash( $token ),
				'return_enc'  => Crypto::encrypt( $token ),
			),
			array( 'id' => $id )
		);
		unset( self::$cache[ $id ] );
		return $token;
	}

	public static function return_url( object $reviewer, ?string $token = null ): ?string {
		$token = $token ?? Crypto::decrypt( $reviewer->return_enc ?? null );
		if ( ! $token ) {
			return null;
		}
		$link = (int) $reviewer->link_id > 0 ? Links::get( (int) $reviewer->link_id ) : null;
		$base = ( $link && $link->landing_url ) ? $link->landing_url : home_url( '/' );
		return add_query_arg( Links::QUERY_RETURN, $token, $base );
	}

	public static function update( int $id, array $fields ): void {
		global $wpdb;
		$allowed = array_intersect_key( $fields, array_flip( array( 'display_name', 'email', 'status' ) ) );
		if ( $allowed ) {
			$wpdb->update( Schema::table( 'reviewers' ), $allowed, array( 'id' => $id ) );
			unset( self::$cache[ $id ] );
		}
	}

	public static function touch( int $id ): void {
		global $wpdb;
		$wpdb->update( Schema::table( 'reviewers' ), array( 'last_seen_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id ) );
	}

	/**
	 * @return object[]
	 */
	public static function all(): array {
		global $wpdb;
		$table = Schema::table( 'reviewers' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return object[]
	 */
	public static function find_by_email( string $email ): array {
		global $wpdb;
		$table = Schema::table( 'reviewers' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Number of (non-trashed) items each identity has created.
	 *
	 * @return array<int, int>
	 */
	public static function item_counts(): array {
		global $wpdb;
		$table = Schema::table( 'items' );
		$rows  = $wpdb->get_results( "SELECT author_id, COUNT(*) AS n FROM {$table} WHERE deleted_at IS NULL GROUP BY author_id" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->author_id ] = (int) $row->n;
		}
		return $out;
	}

	public static function color( string $seed ): string {
		return self::PALETTE[ abs( crc32( $seed ) ) % count( self::PALETTE ) ];
	}

	public static function sanitize_name( string $name ): string {
		$name = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $name ) ) );
		return mb_substr( $name, 0, 80 );
	}

	public static function flush_cache(): void {
		self::$cache = array();
	}
}
