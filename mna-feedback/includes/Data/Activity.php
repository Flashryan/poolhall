<?php
/**
 * History of edits, status changes and other events on an item.
 *
 * Every entry records who acted and through which channel ("ui" for the
 * review interface, "agent" for Novamira abilities, "system").
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Data;

use MNA\Feedback\Access\Actor;
use MNA\Feedback\Schema;

defined( 'ABSPATH' ) || exit;

final class Activity {

	public static function log( int $item_id, ?Actor $actor, string $action, array $data = array() ): void {
		global $wpdb;
		$wpdb->insert(
			Schema::table( 'activity' ),
			array(
				'item_id'    => $item_id,
				'actor_id'   => $actor ? $actor->reviewer_id : 0,
				'source'     => $actor ? $actor->source : 'system',
				'action'     => $action,
				'data'       => $data ? wp_json_encode( $data ) : null,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * @return object[]
	 */
	public static function for_item( int $item_id, int $limit = 300 ): array {
		global $wpdb;
		$table = Schema::table( 'activity' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id = %d ORDER BY id ASC LIMIT %d", $item_id, $limit ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * IDs of items permanently removed since the given time, so open clients
	 * can drop them.
	 *
	 * @return int[]
	 */
	public static function purged_since( string $since ): array {
		global $wpdb;
		$table = Schema::table( 'activity' );
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT data FROM {$table} WHERE item_id = 0 AND action = 'purged' AND created_at >= %s", $since ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids   = array();
		foreach ( $rows as $json ) {
			$decoded = json_decode( (string) $json, true );
			foreach ( (array) ( $decoded['ids'] ?? array() ) as $id ) {
				$ids[] = (int) $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
