<?php
/**
 * Permission rules for feedback records.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

use MNA\Feedback\Access\Actor;

defined( 'ABSPATH' ) || exit;

final class Policy {

	public const STATUSES   = array( 'open', 'in_progress', 'done' );
	public const PRIORITIES = array( 'low', 'normal', 'high', 'urgent' );

	public static function is_author( Actor $actor, object $row ): bool {
		return (int) $row->author_id === $actor->reviewer_id;
	}

	public static function is_trashed( object $row ): bool {
		return ! empty( $row->deleted_at );
	}

	/** Title and description. */
	public static function can_edit_item( Actor $actor, object $item ): bool {
		return ! self::is_trashed( $item ) && ( self::is_author( $actor, $item ) || $actor->is_manager() );
	}

	/** Move to Trash. */
	public static function can_delete_item( Actor $actor, object $item ): bool {
		return self::can_edit_item( $actor, $item );
	}

	public static function can_set_priority( Actor $actor, object $item ): bool {
		return ! self::is_trashed( $item ) && ( $actor->is_implementer() || self::is_author( $actor, $item ) );
	}

	public static function can_assign( Actor $actor, object $item ): bool {
		return ! self::is_trashed( $item ) && $actor->is_implementer();
	}

	public static function can_reorder( Actor $actor ): bool {
		return $actor->is_implementer();
	}

	/**
	 * Statuses this person may move the item to. Implementers may use any;
	 * reviewers may only reopen finished work.
	 *
	 * @return string[]
	 */
	public static function allowed_statuses( Actor $actor, object $item ): array {
		if ( self::is_trashed( $item ) ) {
			return array();
		}
		if ( $actor->is_implementer() ) {
			return array_values( array_diff( self::STATUSES, array( $item->status ) ) );
		}
		return 'done' === $item->status ? array( 'open' ) : array();
	}

	public static function can_reply( Actor $actor, object $item ): bool {
		return ! self::is_trashed( $item );
	}

	public static function can_add_note( Actor $actor, object $item ): bool {
		return ! self::is_trashed( $item ) && $actor->is_implementer();
	}

	public static function can_attach( Actor $actor, object $item ): bool {
		if ( self::is_trashed( $item ) ) {
			return false;
		}
		return ! $actor->is_guest() || (bool) Settings::get( 'guest_uploads' );
	}

	public static function can_edit_reply( Actor $actor, object $reply ): bool {
		return ! self::is_trashed( $reply ) && ( self::is_author( $actor, $reply ) || $actor->is_manager() );
	}

	public static function can_delete_reply( Actor $actor, object $reply ): bool {
		return self::can_edit_reply( $actor, $reply );
	}

	public static function can_delete_attachment( Actor $actor, object $attachment ): bool {
		return (int) $attachment->uploader_id === $actor->reviewer_id || $actor->is_manager();
	}

	/** Restore from Trash, delete permanently, see the Trash. */
	public static function can_manage_trash( Actor $actor ): bool {
		return $actor->is_manager();
	}

	public static function can_view_item( Actor $actor, object $item ): bool {
		return ! self::is_trashed( $item ) || $actor->is_manager();
	}
}
