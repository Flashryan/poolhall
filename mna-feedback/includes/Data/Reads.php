<?php
/**
 * Per-person read markers, used to flag items with unseen replies or changes.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Data;

use MNA\Feedback\Schema;

defined( 'ABSPATH' ) || exit;

final class Reads {

	/**
	 * Records that the person has seen the item up to activity revision $rev
	 * (the item's current revision when null).
	 */
	public static function mark( int $reviewer_id, int $item_id, ?int $rev = null ): void {
		global $wpdb;
		$items   = Schema::table( 'items' );
		$current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT activity_rev FROM {$items} WHERE id = %d", $item_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rev     = null === $rev ? $current : max( 0, min( $rev, $current ) );
		$wpdb->replace(
			Schema::table( 'reads' ),
			array(
				'reviewer_id' => $reviewer_id,
				'item_id'     => $item_id,
				'read_at'     => gmdate( 'Y-m-d H:i:s' ),
				'read_rev'    => $rev,
			)
		);
	}

	/**
	 * @param int[] $item_ids
	 * @return array<int, int> item id => activity revision seen
	 */
	public static function map( int $reviewer_id, array $item_ids ): array {
		if ( ! $item_ids ) {
			return array();
		}
		global $wpdb;
		$table        = Schema::table( 'reads' );
		$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
		$params       = array_merge( array( $reviewer_id ), array_map( 'intval', $item_ids ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT item_id, read_rev FROM {$table} WHERE reviewer_id = %d AND item_id IN ({$placeholders})", $params ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out          = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->item_id ] = (int) $row->read_rev;
		}
		return $out;
	}
}
