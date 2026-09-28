<?php
/**
 * Business logic for feedback: validation, permissions, history and change
 * tracking. Shared by the REST API and the Novamira abilities, so both obey
 * exactly the same rules.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

use MNA\Feedback\Access\Actor;
use MNA\Feedback\Data\Activity;
use MNA\Feedback\Data\Attachments;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Reads;
use MNA\Feedback\Data\Replies;
use MNA\Feedback\Data\Reviewers;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Workflow {

	public const MAX_TITLE  = 200;
	public const MAX_BODY   = 10000;
	public const MAX_ANCHOR = 16384;
	public const MAX_CTX    = 8192;

	/* ---------------------------------------------------------------------
	 * Items
	 * ------------------------------------------------------------------ */

	public static function create_item( Actor $actor, array $in ): \stdClass|WP_Error {
		$title = self::clean_text( (string) ( $in['title'] ?? '' ), self::MAX_TITLE, false );
		$body  = self::clean_text( (string) ( $in['body'] ?? '' ), self::MAX_BODY, true );
		if ( '' === $title && '' !== $body ) {
			$title = self::clean_text( wp_trim_words( $body, 12, '…' ), self::MAX_TITLE, false );
		}
		if ( '' === $title ) {
			return self::error( 'mnafb_title_required', __( 'Add a short title for this feedback.', 'mna-feedback' ), 400 );
		}

		$priority = (string) ( $in['priority'] ?? 'normal' );
		if ( ! in_array( $priority, Policy::PRIORITIES, true ) ) {
			$priority = 'normal';
		}

		$page_url = Url::normalize( (string) ( $in['page_url'] ?? '' ) );
		if ( ! $page_url ) {
			return self::error( 'mnafb_bad_page', __( 'Feedback can only be left on pages of this site.', 'mna-feedback' ), 400 );
		}

		$pin      = is_array( $in['pin'] ?? null ) ? $in['pin'] : array();
		$pin_type = ( 'element' === ( $pin['type'] ?? '' ) ) ? 'element' : 'page';
		if ( 'page' === $pin_type && ! Settings::get( 'page_comments' ) && empty( $in['_agent'] ) ) {
			return self::error( 'mnafb_page_comments_off', __( 'Page-level comments are turned off. Pick an element on the page instead.', 'mna-feedback' ), 400 );
		}

		$anchor = null;
		if ( 'element' === $pin_type ) {
			$anchor = is_array( $in['anchor'] ?? null ) ? wp_json_encode( $in['anchor'] ) : null;
			if ( ! $anchor || strlen( $anchor ) > self::MAX_ANCHOR ) {
				return self::error( 'mnafb_bad_anchor', __( 'The selected element could not be recorded. Try selecting it again.', 'mna-feedback' ), 400 );
			}
		}
		$context = is_array( $in['context'] ?? null ) ? wp_json_encode( $in['context'] ) : null;
		if ( $context && strlen( $context ) > self::MAX_CTX ) {
			$context = null;
		}
		$viewport = is_array( $in['viewport'] ?? null ) ? $in['viewport'] : array();
		$now      = gmdate( 'Y-m-d H:i:s' );

		$id = Items::insert(
			array(
				'uuid'             => Crypto::uuid(),
				'title'            => $title,
				'body'             => $body,
				'status'           => 'open',
				'priority'         => $priority,
				'author_id'        => $actor->reviewer_id,
				'assignee_id'      => 0,
				'page_url'         => $page_url,
				'page_key'         => Url::key( $page_url ),
				'page_title'       => self::page_title( $page_url, (string) ( $in['page_title'] ?? '' ) ),
				'pin_type'         => $pin_type,
				'pin_x'            => self::fraction( $pin['x'] ?? 0.5 ),
				'pin_y'            => self::fraction( $pin['y'] ?? 0.5 ),
				'anchor'           => $anchor,
				'context'          => $context,
				'viewport_w'       => max( 0, min( 20000, (int) ( $viewport['w'] ?? 0 ) ) ),
				'viewport_h'       => max( 0, min( 20000, (int) ( $viewport['h'] ?? 0 ) ) ),
				'board_order'      => Items::min_order( 'open' ) - 1024,
				'created_at'       => $now,
				'updated_at'       => $now,
				'last_activity_at' => $now,
				'last_activity_by' => $actor->reviewer_id,
			)
		);
		if ( ! $id ) {
			return self::error( 'mnafb_db', __( 'The feedback could not be saved. Please try again.', 'mna-feedback' ), 500 );
		}

		Activity::log( $id, $actor, 'created', array( 'pin' => $pin_type ) );
		Reads::mark( $actor->reviewer_id, $id );
		return Items::get( $id ) ?? self::error( 'mnafb_db', __( 'The feedback could not be saved.', 'mna-feedback' ), 500 );
	}

	/**
	 * Applies a partial update. $expected carries the values the editor started
	 * from, for the fields being changed; if someone else has changed one of
	 * them since, the update is refused with a 409 describing the conflict.
	 */
	public static function update_item( Actor $actor, int $id, array $in, ?array $expected = null ): \stdClass|WP_Error {
		$item = Items::get( $id );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return self::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		if ( Policy::is_trashed( $item ) ) {
			return self::error( 'mnafb_trashed', __( 'This feedback is in Trash. Restore it before editing.', 'mna-feedback' ), 409 );
		}

		$set     = array();
		$changes = array();

		if ( array_key_exists( 'title', $in ) || array_key_exists( 'body', $in ) ) {
			if ( ! Policy::can_edit_item( $actor, $item ) ) {
				return self::error( 'mnafb_forbidden', __( 'Only the author can edit this feedback.', 'mna-feedback' ), 403 );
			}
			if ( array_key_exists( 'title', $in ) ) {
				$title = self::clean_text( (string) $in['title'], self::MAX_TITLE, false );
				if ( '' === $title ) {
					return self::error( 'mnafb_title_required', __( 'The title cannot be empty.', 'mna-feedback' ), 400 );
				}
				if ( $title !== $item->title ) {
					$set['title']      = $title;
					$changes['fields'][] = 'title';
				}
			}
			if ( array_key_exists( 'body', $in ) ) {
				$body = self::clean_text( (string) $in['body'], self::MAX_BODY, true );
				if ( $body !== $item->body ) {
					$set['body']       = $body;
					$changes['fields'][] = 'body';
				}
			}
			if ( isset( $changes['fields'] ) ) {
				$set['edited_at'] = gmdate( 'Y-m-d H:i:s' );
			}
		}

		if ( array_key_exists( 'priority', $in ) ) {
			$priority = (string) $in['priority'];
			if ( ! in_array( $priority, Policy::PRIORITIES, true ) ) {
				return self::error( 'mnafb_bad_priority', __( 'Unknown priority.', 'mna-feedback' ), 400 );
			}
			if ( $priority !== $item->priority ) {
				if ( ! Policy::can_set_priority( $actor, $item ) ) {
					return self::error( 'mnafb_forbidden', __( 'You cannot change the priority of this feedback.', 'mna-feedback' ), 403 );
				}
				$set['priority']     = $priority;
				$changes['priority'] = array( $item->priority, $priority );
			}
		}

		if ( array_key_exists( 'status', $in ) ) {
			$status = (string) $in['status'];
			if ( ! in_array( $status, Policy::STATUSES, true ) ) {
				return self::error( 'mnafb_bad_status', __( 'Unknown status.', 'mna-feedback' ), 400 );
			}
			if ( $status !== $item->status ) {
				if ( ! in_array( $status, Policy::allowed_statuses( $actor, $item ), true ) ) {
					return self::error(
						'mnafb_forbidden',
						$actor->is_implementer() ? __( 'That status change is not allowed.', 'mna-feedback' ) : __( 'Reviewers can reopen finished work; the team moves cards between other columns.', 'mna-feedback' ),
						403
					);
				}
				$set['status']            = $status;
				$set['status_changed_at'] = gmdate( 'Y-m-d H:i:s' );
				$changes['status']        = array( $item->status, $status );
				if ( ! array_key_exists( 'order', $in ) ) {
					$set['board_order'] = Items::min_order( $status ) - 1024;
				}
			}
		}

		if ( array_key_exists( 'assignee_id', $in ) ) {
			$assignee = (int) $in['assignee_id'];
			if ( $assignee !== (int) $item->assignee_id ) {
				if ( ! Policy::can_assign( $actor, $item ) ) {
					return self::error( 'mnafb_forbidden', __( 'Only the team can assign feedback.', 'mna-feedback' ), 403 );
				}
				if ( $assignee > 0 && ! self::is_assignable( $assignee ) ) {
					return self::error( 'mnafb_bad_assignee', __( 'That person cannot be assigned work.', 'mna-feedback' ), 400 );
				}
				$set['assignee_id']  = $assignee;
				$changes['assignee'] = array( (int) $item->assignee_id, $assignee );
			}
		}

		if ( array_key_exists( 'order', $in ) && is_numeric( $in['order'] ) ) {
			if ( ! Policy::can_reorder( $actor ) ) {
				return self::error( 'mnafb_forbidden', __( 'Only the team can reorder the board.', 'mna-feedback' ), 403 );
			}
			$set['board_order'] = (float) $in['order'];
		}

		if ( ! $set ) {
			return $item;
		}

		$significant = isset( $changes['status'] ) || isset( $changes['assignee'] ) || isset( $changes['fields'] ) || isset( $changes['priority'] );
		if ( $significant ) {
			$set['last_activity_at'] = gmdate( 'Y-m-d H:i:s' );
			$set['last_activity_by'] = $actor->reviewer_id;
		}

		$guard = array();
		if ( null !== $expected ) {
			foreach ( array( 'title', 'body', 'status', 'priority', 'assignee_id' ) as $field ) {
				if ( array_key_exists( $field, $set ) && array_key_exists( $field, $expected ) ) {
					$guard[ $field ] = 'assignee_id' === $field ? (int) $expected[ $field ] : (string) $expected[ $field ];
				}
			}
		}

		if ( ! Items::compare_and_set( $id, $set, $guard ) ) {
			$current = Items::get( $id );
			$fields  = array();
			if ( $current ) {
				foreach ( $guard as $field => $value ) {
					if ( (string) $current->{$field} !== (string) $value ) {
						$fields[] = $field;
					}
				}
			}
			return new WP_Error(
				'mnafb_conflict',
				__( 'Someone else changed this feedback while you were editing.', 'mna-feedback' ),
				array(
					'status'  => 409,
					'fields'  => $fields,
					'current' => $current ? Formatter::item( $current, $actor ) : null,
				)
			);
		}

		if ( isset( $changes['fields'] ) ) {
			Activity::log( $id, $actor, 'edited', array( 'fields' => $changes['fields'] ) );
		}
		if ( isset( $changes['status'] ) ) {
			Activity::log( $id, $actor, 'status', array( 'from' => $changes['status'][0], 'to' => $changes['status'][1] ) );
		}
		if ( isset( $changes['priority'] ) ) {
			Activity::log( $id, $actor, 'priority', array( 'from' => $changes['priority'][0], 'to' => $changes['priority'][1] ) );
		}
		if ( isset( $changes['assignee'] ) ) {
			Activity::log( $id, $actor, 'assigned', array( 'from' => $changes['assignee'][0], 'to' => $changes['assignee'][1] ) );
		}

		return Items::get( $id ) ?? self::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
	}

	public static function trash_item( Actor $actor, int $id ): \stdClass|WP_Error {
		$item = Items::get( $id );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return self::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		if ( Policy::is_trashed( $item ) ) {
			return $item;
		}
		if ( ! Policy::can_delete_item( $actor, $item ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only the author can delete this feedback.', 'mna-feedback' ), 403 );
		}
		Items::force_set(
			$id,
			array(
				'deleted_at' => gmdate( 'Y-m-d H:i:s' ),
				'deleted_by' => $actor->reviewer_id,
			)
		);
		Activity::log( $id, $actor, 'trashed' );
		return Items::get( $id );
	}

	public static function restore_item( Actor $actor, int $id ): \stdClass|WP_Error {
		if ( ! Policy::can_manage_trash( $actor ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only managers can restore feedback from Trash.', 'mna-feedback' ), 403 );
		}
		$item = Items::get( $id );
		if ( ! $item ) {
			return self::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		if ( ! Policy::is_trashed( $item ) ) {
			return $item;
		}
		Items::force_set(
			$id,
			array(
				'deleted_at'       => null,
				'deleted_by'       => 0,
				'last_activity_at' => gmdate( 'Y-m-d H:i:s' ),
				'last_activity_by' => $actor->reviewer_id,
			)
		);
		Activity::log( $id, $actor, 'restored' );
		return Items::get( $id );
	}

	public static function purge_item( Actor $actor, int $id ): true|WP_Error {
		if ( ! Policy::can_manage_trash( $actor ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only managers can delete feedback permanently.', 'mna-feedback' ), 403 );
		}
		$item = Items::get( $id );
		if ( ! $item ) {
			return true;
		}
		if ( ! Policy::is_trashed( $item ) ) {
			return self::error( 'mnafb_not_trashed', __( 'Move feedback to Trash before deleting it permanently.', 'mna-feedback' ), 409 );
		}
		Items::delete_permanently( $id );
		Activity::log( 0, $actor, 'purged', array( 'ids' => array( $id ) ) );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Replies and notes
	 * ------------------------------------------------------------------ */

	public static function add_reply( Actor $actor, int $item_id, string $body, string $kind = 'reply' ): \stdClass|WP_Error {
		$item = Items::get( $item_id );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return self::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		if ( ! Policy::can_reply( $actor, $item ) ) {
			return self::error( 'mnafb_trashed', __( 'This feedback is in Trash.', 'mna-feedback' ), 409 );
		}
		if ( 'note' === $kind && ! Policy::can_add_note( $actor, $item ) ) {
			$kind = 'reply';
		}
		$body = self::clean_text( $body, self::MAX_BODY, true );
		if ( '' === $body ) {
			return self::error( 'mnafb_empty', __( 'Write something before sending.', 'mna-feedback' ), 400 );
		}
		$reply_id = Replies::insert( $item_id, $actor->reviewer_id, $body, $kind );
		if ( ! $reply_id ) {
			return self::error( 'mnafb_db', __( 'The reply could not be saved. Please try again.', 'mna-feedback' ), 500 );
		}
		Items::touch( $item_id, $actor->reviewer_id );
		Activity::log( $item_id, $actor, 'note' === $kind ? 'note_added' : 'replied', array( 'reply_id' => $reply_id ) );
		Reads::mark( $actor->reviewer_id, $item_id );
		return Replies::get( $reply_id ) ?? self::error( 'mnafb_db', __( 'The reply could not be saved.', 'mna-feedback' ), 500 );
	}

	public static function update_reply( Actor $actor, int $reply_id, string $body, ?string $expected_body ): \stdClass|WP_Error {
		$reply = Replies::get( $reply_id );
		if ( ! $reply || Policy::is_trashed( $reply ) ) {
			return self::error( 'mnafb_not_found', __( 'That reply no longer exists.', 'mna-feedback' ), 404 );
		}
		if ( ! Policy::can_edit_reply( $actor, $reply ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only the author can edit this reply.', 'mna-feedback' ), 403 );
		}
		$body = self::clean_text( $body, self::MAX_BODY, true );
		if ( '' === $body ) {
			return self::error( 'mnafb_empty', __( 'A reply cannot be empty. Delete it instead.', 'mna-feedback' ), 400 );
		}
		if ( $body === $reply->body ) {
			return $reply;
		}
		if ( ! Replies::compare_and_set_body( $reply_id, $body, $expected_body ) ) {
			$current = Replies::get( $reply_id );
			return new WP_Error(
				'mnafb_conflict',
				__( 'This reply changed while you were editing it.', 'mna-feedback' ),
				array(
					'status'  => 409,
					'fields'  => array( 'body' ),
					'current' => $current ? Formatter::reply( $current, $actor ) : null,
				)
			);
		}
		Items::touch( (int) $reply->item_id, $actor->reviewer_id, false );
		Activity::log( (int) $reply->item_id, $actor, 'reply_edited', array( 'reply_id' => $reply_id ) );
		return Replies::get( $reply_id );
	}

	public static function trash_reply( Actor $actor, int $reply_id ): \stdClass|WP_Error {
		$reply = Replies::get( $reply_id );
		if ( ! $reply ) {
			return self::error( 'mnafb_not_found', __( 'That reply no longer exists.', 'mna-feedback' ), 404 );
		}
		if ( Policy::is_trashed( $reply ) ) {
			return $reply;
		}
		if ( ! Policy::can_delete_reply( $actor, $reply ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only the author can delete this reply.', 'mna-feedback' ), 403 );
		}
		Replies::set_trashed( $reply_id, true, $actor->reviewer_id );
		Items::touch( (int) $reply->item_id, $actor->reviewer_id, false );
		Activity::log( (int) $reply->item_id, $actor, 'reply_trashed', array( 'reply_id' => $reply_id ) );
		return Replies::get( $reply_id );
	}

	public static function restore_reply( Actor $actor, int $reply_id ): \stdClass|WP_Error {
		if ( ! Policy::can_manage_trash( $actor ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only managers can restore replies from Trash.', 'mna-feedback' ), 403 );
		}
		$reply = Replies::get( $reply_id );
		if ( ! $reply ) {
			return self::error( 'mnafb_not_found', __( 'That reply no longer exists.', 'mna-feedback' ), 404 );
		}
		Replies::set_trashed( $reply_id, false, 0 );
		Items::touch( (int) $reply->item_id, $actor->reviewer_id, false );
		Activity::log( (int) $reply->item_id, $actor, 'reply_restored', array( 'reply_id' => $reply_id ) );
		return Replies::get( $reply_id );
	}

	public static function purge_reply( Actor $actor, int $reply_id ): true|WP_Error {
		if ( ! Policy::can_manage_trash( $actor ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only managers can delete replies permanently.', 'mna-feedback' ), 403 );
		}
		$reply = Replies::get( $reply_id );
		if ( ! $reply ) {
			return true;
		}
		if ( ! Policy::is_trashed( $reply ) ) {
			return self::error( 'mnafb_not_trashed', __( 'Move the reply to Trash before deleting it permanently.', 'mna-feedback' ), 409 );
		}
		foreach ( Attachments::for_items( array( (int) $reply->item_id ) )[ (int) $reply->item_id ] ?? array() as $attachment ) {
			if ( (int) $attachment->reply_id === $reply_id ) {
				Attachments::delete( $attachment );
			}
		}
		Replies::delete( $reply_id );
		Items::touch( (int) $reply->item_id, $actor->reviewer_id, false );
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Attachments
	 * ------------------------------------------------------------------ */

	public static function add_attachment( Actor $actor, int $item_id, array $file, int $reply_id = 0 ): \stdClass|WP_Error {
		$item = Items::get( $item_id );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return self::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		if ( ! Policy::can_attach( $actor, $item ) ) {
			return self::error( 'mnafb_forbidden', __( 'Screenshots cannot be attached here.', 'mna-feedback' ), 403 );
		}
		if ( $reply_id ) {
			$reply = Replies::get( $reply_id );
			if ( ! $reply || (int) $reply->item_id !== $item_id || ! Policy::is_author( $actor, $reply ) ) {
				$reply_id = 0;
			}
		}
		$attachment = Attachments::store( $file, $item_id, $reply_id, $actor->reviewer_id );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		Items::touch( $item_id, $actor->reviewer_id );
		Activity::log( $item_id, $actor, 'attachment_added', array( 'attachment_id' => (int) $attachment->id ) );
		return $attachment;
	}

	public static function delete_attachment( Actor $actor, int $attachment_id ): true|WP_Error {
		$attachment = Attachments::get( $attachment_id );
		if ( ! $attachment ) {
			return true;
		}
		if ( ! Policy::can_delete_attachment( $actor, $attachment ) ) {
			return self::error( 'mnafb_forbidden', __( 'Only the person who added this screenshot can remove it.', 'mna-feedback' ), 403 );
		}
		Attachments::delete( $attachment );
		Items::touch( (int) $attachment->item_id, $actor->reviewer_id, false );
		Activity::log( (int) $attachment->item_id, $actor, 'attachment_removed', array( 'attachment_id' => $attachment_id ) );
		return true;
	}

	public static function mark_read( Actor $actor, int $item_id, ?int $rev = null ): void {
		Reads::mark( $actor->reviewer_id, $item_id, $rev );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Identities that can be assigned work: WordPress users with the
	 * implementer capability.
	 */
	public static function is_assignable( int $reviewer_id ): bool {
		$reviewer = Reviewers::get( $reviewer_id );
		if ( ! $reviewer || 'wp_user' !== $reviewer->type || 'active' !== $reviewer->status ) {
			return false;
		}
		$user = get_user_by( 'id', (int) $reviewer->wp_user_id );
		return $user && user_can( $user, Capabilities::IMPLEMENT );
	}

	/**
	 * Plain text with line breaks preserved (multiline) or collapsed. Markup is
	 * stripped; the interface renders text safely and linkifies URLs.
	 */
	public static function clean_text( string $text, int $max, bool $multiline ): string {
		$text = wp_check_invalid_utf8( $text, true );
		$text = wp_strip_all_tags( $text, false );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		if ( $multiline ) {
			$text = preg_replace( "/[ \t]+\n/", "\n", $text );
			$text = preg_replace( "/\n{4,}/", "\n\n\n", $text );
		} else {
			$text = preg_replace( '/\s+/u', ' ', $text );
		}
		return mb_substr( trim( (string) $text ), 0, $max );
	}

	/**
	 * A readable title for a page: the post title when the address belongs to
	 * a post, page or product, otherwise the browser title without the
	 * " – Site name" suffix.
	 */
	public static function page_title( string $url, string $given ): string {
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			$title = trim( html_entity_decode( wp_strip_all_tags( (string) get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $title ) {
				return mb_substr( $title, 0, 255 );
			}
		}
		$title = self::clean_text( html_entity_decode( $given, ENT_QUOTES, 'UTF-8' ), 255, false );
		$site  = trim( html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== $site && $title !== $site ) {
			foreach ( array( ' – ', ' — ', ' - ', ' | ', ' · ', ' :: ', ' » ' ) as $separator ) {
				if ( str_ends_with( $title, $separator . $site ) ) {
					return trim( mb_substr( $title, 0, mb_strlen( $title ) - mb_strlen( $separator . $site ) ) );
				}
				if ( str_starts_with( $title, $site . $separator ) ) {
					return trim( mb_substr( $title, mb_strlen( $site . $separator ) ) );
				}
			}
		}
		return $title;
	}

	private static function fraction( mixed $value ): float {
		$number = is_numeric( $value ) ? (float) $value : 0.5;
		return round( max( 0.0, min( 1.0, $number ) ), 5 );
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
