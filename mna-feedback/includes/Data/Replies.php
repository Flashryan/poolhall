<?php
/**
 * Replies and implementation notes on an item.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Data;

use MNA\Feedback\Crypto;
use MNA\Feedback\Schema;

defined( 'ABSPATH' ) || exit;

final class Replies {

	public const KINDS = array( 'reply', 'note' );

	public static function table(): string {
		return Schema::table( 'replies' );
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return object[]
	 */
	public static function for_item( int $item_id, bool $include_trashed = false ): array {
		global $wpdb;
		$table   = self::table();
		$trashed = $include_trashed ? '' : 'AND deleted_at IS NULL';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id = %d {$trashed} ORDER BY id ASC", $item_id ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function insert( int $item_id, int $author_id, string $body, string $kind, ?array $device = null ): int {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert(
			self::table(),
			array(
				'uuid'       => Crypto::uuid(),
				'item_id'    => $item_id,
				'author_id'  => $author_id,
				'kind'       => in_array( $kind, self::KINDS, true ) ? $kind : 'reply',
				'body'       => $body,
				'device'     => $device ? wp_json_encode( $device ) : null,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Changes the body only if it still matches $expected_body. Returns false on conflict.
	 */
	public static function compare_and_set_body( int $id, string $body, ?string $expected_body ): bool {
		global $wpdb;
		$table = self::table();
		$now   = gmdate( 'Y-m-d H:i:s' );
		if ( null === $expected_body ) {
			$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET body = %s, revision = revision + 1, updated_at = %s, edited_at = %s WHERE id = %d AND deleted_at IS NULL", $body, $now, $now, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$affected = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET body = %s, revision = revision + 1, updated_at = %s, edited_at = %s WHERE id = %d AND deleted_at IS NULL AND body = %s", $body, $now, $now, $id, $expected_body ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return 1 === (int) $affected;
	}

	public static function set_trashed( int $id, bool $trashed, int $actor_id ): void {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'deleted_at' => $trashed ? gmdate( 'Y-m-d H:i:s' ) : null,
				'deleted_by' => $trashed ? $actor_id : 0,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'id' => $id )
		);
	}

	public static function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/**
	 * Trashed replies across the board (manager Trash view).
	 *
	 * @return object[]
	 */
	public static function trashed( int $limit = 200 ): array {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT %d", $limit ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
