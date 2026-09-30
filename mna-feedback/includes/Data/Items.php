<?php
/**
 * Feedback item storage.
 *
 * Writes use compare-and-set: the UPDATE only succeeds when the fields the
 * caller started from still hold the values it saw, so two people editing at
 * once cannot silently overwrite each other.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Data;

use MNA\Feedback\Schema;

defined( 'ABSPATH' ) || exit;

final class Items {

	private const FORMATS = array(
		'title'             => '%s',
		'body'              => '%s',
		'status'            => '%s',
		'priority'          => '%s',
		'assignee_id'       => '%d',
		'board_order'       => '%f',
		'edited_at'         => '%s',
		'status_changed_at' => '%s',
		'last_activity_at'  => '%s',
		'last_activity_by'  => '%d',
		'deleted_at'        => '%s',
		'deleted_by'        => '%d',
		'updated_at'        => '%s',
	);

	public static function table(): string {
		return Schema::table( 'items' );
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param array{
	 *   status?: string[], priority?: string[], device?: string[], author_id?: int, assignee?: int|string,
	 *   page_key?: string, search?: string, trashed?: bool|string, updated_since?: string,
	 *   limit?: int, offset?: int, order?: string, ids?: int[]
	 * } $args
	 * @return object[]
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$table = self::table();
		[ $where, $params ] = self::where( $args );

		$order = match ( $args['order'] ?? 'newest' ) {
			'oldest'  => 'id ASC',
			'board'   => 'board_order ASC, id DESC',
			'updated' => 'updated_at DESC, id DESC',
			default   => 'id DESC',
		};
		$limit  = max( 1, min( 1000, (int) ( $args['limit'] ?? 500 ) ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function count( array $args = array() ): int {
		global $wpdb;
		$table = self::table();
		[ $where, $params ] = self::where( $args );
		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return array{0: string, 1: array}
	 */
	private static function where( array $args ): array {
		global $wpdb;
		$clauses = array( '1=1' );
		$params  = array();

		$trashed = $args['trashed'] ?? false;
		if ( 'any' !== $trashed ) {
			$clauses[] = $trashed ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
		}
		foreach ( array(
			'status'   => 'status',
			'priority' => 'priority',
			'device'   => 'device_type',
		) as $arg => $column ) {
			if ( ! empty( $args[ $arg ] ) ) {
				$values    = array_values( (array) $args[ $arg ] );
				$clauses[] = $column . ' IN (' . implode( ',', array_fill( 0, count( $values ), '%s' ) ) . ')';
				$params    = array_merge( $params, $values );
			}
		}
		if ( ! empty( $args['ids'] ) ) {
			$ids       = array_map( 'intval', $args['ids'] );
			$clauses[] = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params    = array_merge( $params, $ids );
		}
		if ( ! empty( $args['author_id'] ) ) {
			$clauses[] = 'author_id = %d';
			$params[]  = (int) $args['author_id'];
		}
		if ( isset( $args['assignee'] ) && '' !== $args['assignee'] ) {
			$clauses[] = 'assignee_id = %d';
			$params[]  = 'none' === $args['assignee'] ? 0 : (int) $args['assignee'];
		}
		if ( ! empty( $args['page_key'] ) ) {
			$clauses[] = 'page_key = %s';
			$params[]  = (string) $args['page_key'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$clauses[] = '(title LIKE %s OR body LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
		}
		if ( ! empty( $args['updated_since'] ) ) {
			$clauses[] = 'updated_at >= %s';
			$params[]  = (string) $args['updated_since'];
		}
		return array( implode( ' AND ', $clauses ), $params );
	}

	public static function insert( array $data ): int {
		global $wpdb;
		$wpdb->insert( self::table(), $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Applies $set only if every field in $expected still has the expected value
	 * and the item is not in Trash. Bumps the revision. Returns false on conflict.
	 */
	public static function compare_and_set( int $id, array $set, array $expected = array() ): bool {
		global $wpdb;
		$table = self::table();

		$set['updated_at'] = $set['updated_at'] ?? gmdate( 'Y-m-d H:i:s' );
		[ $assign, $params ] = self::assignments( $set );

		$where   = array( 'id = %d', 'deleted_at IS NULL' );
		$params[] = $id;
		foreach ( $expected as $column => $value ) {
			if ( ! isset( self::FORMATS[ $column ] ) ) {
				continue;
			}
			if ( 'board_order' === $column ) {
				continue; // Ordering is last-writer-wins.
			}
			$where[]  = "{$column} = " . self::FORMATS[ $column ];
			$params[] = $value;
		}

		$sql = "UPDATE {$table} SET " . implode( ', ', $assign ) . ' WHERE ' . implode( ' AND ', $where );
		$affected = $wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return 1 === (int) $affected;
	}

	/**
	 * Unconditional change (Trash, restore). Bumps the revision.
	 */
	public static function force_set( int $id, array $set ): void {
		global $wpdb;
		$table = self::table();
		$set['updated_at'] = $set['updated_at'] ?? gmdate( 'Y-m-d H:i:s' );
		[ $assign, $params ] = self::assignments( $set );
		$params[] = $id;
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET " . implode( ', ', $assign ) . ' WHERE id = %d', $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Marks the item as changed without touching its editable fields (a reply,
	 * an attachment), so other viewers pick it up without a false conflict.
	 */
	public static function touch( int $id, int $actor_id, bool $counts_as_activity = true ): void {
		global $wpdb;
		$table = self::table();
		$now   = gmdate( 'Y-m-d H:i:s' );
		if ( $counts_as_activity ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET updated_at = %s, last_activity_at = %s, last_activity_by = %d, activity_rev = activity_rev + 1 WHERE id = %d", $now, $now, $actor_id, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET updated_at = %s WHERE id = %d", $now, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * SET clauses for an update. Every write bumps the revision; writes that
	 * count as activity (they set last_activity_at) also bump activity_rev,
	 * which drives the unread markers.
	 *
	 * @return array{0: string[], 1: array}
	 */
	private static function assignments( array $set ): array {
		$assign = array( 'revision = revision + 1' );
		if ( array_key_exists( 'last_activity_at', $set ) ) {
			$assign[] = 'activity_rev = activity_rev + 1';
		}
		$params = array();
		foreach ( $set as $column => $value ) {
			if ( ! isset( self::FORMATS[ $column ] ) ) {
				continue;
			}
			if ( null === $value ) {
				$assign[] = "{$column} = NULL";
				continue;
			}
			$assign[] = "{$column} = " . self::FORMATS[ $column ];
			$params[] = $value;
		}
		return array( $assign, $params );
	}

	public static function min_order( string $status ): float {
		global $wpdb;
		$table = self::table();
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(board_order) FROM {$table} WHERE status = %s AND deleted_at IS NULL", $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return null === $value ? 0.0 : (float) $value;
	}

	/**
	 * Pages that have feedback, with counts per status.
	 *
	 * @return array<int, array{key: string, url: string, title: string, open: int, in_progress: int, done: int}>
	 */
	public static function pages(): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT page_key, MAX(page_url) AS url, MAX(page_title) AS title, status, COUNT(*) AS n FROM {$table} WHERE deleted_at IS NULL GROUP BY page_key, status" ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pages = array();
		foreach ( $rows as $row ) {
			$key = (string) $row->page_key;
			if ( ! isset( $pages[ $key ] ) ) {
				$pages[ $key ] = array(
					'key'         => $key,
					'url'         => (string) $row->url,
					'title'       => (string) $row->title,
					'open'        => 0,
					'in_progress' => 0,
					'done'        => 0,
				);
			}
			if ( isset( $pages[ $key ][ $row->status ] ) ) {
				$pages[ $key ][ $row->status ] = (int) $row->n;
			}
		}
		return array_values( $pages );
	}

	/**
	 * @param int[] $ids
	 * @return array<int, int>
	 */
	public static function reply_counts( array $ids ): array {
		if ( ! $ids ) {
			return array();
		}
		global $wpdb;
		$table        = Schema::table( 'replies' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT item_id, COUNT(*) AS n FROM {$table} WHERE deleted_at IS NULL AND item_id IN ({$placeholders}) GROUP BY item_id", array_map( 'intval', $ids ) ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out          = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->item_id ] = (int) $row->n;
		}
		return $out;
	}

	/**
	 * Removes the item and everything attached to it.
	 */
	public static function delete_permanently( int $id ): void {
		global $wpdb;
		Attachments::delete_for_item( $id );
		$wpdb->delete( Schema::table( 'replies' ), array( 'item_id' => $id ) );
		$wpdb->delete( Schema::table( 'activity' ), array( 'item_id' => $id ) );
		$wpdb->delete( Schema::table( 'reads' ), array( 'item_id' => $id ) );
		$wpdb->delete( self::table(), array( 'id' => $id ) );
	}
}
