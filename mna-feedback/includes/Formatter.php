<?php
/**
 * Shapes records for the API. Each item carries a `can` map so the interface
 * only offers actions the current person is allowed to take.
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

defined( 'ABSPATH' ) || exit;

final class Formatter {

	public static function time( ?string $mysql ): ?string {
		if ( ! $mysql || str_starts_with( $mysql, '0000' ) ) {
			return null;
		}
		return gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $mysql . ' UTC' ) );
	}

	public static function initials( string $name ): string {
		$parts = preg_split( '/\s+/u', trim( $name ) ) ?: array();
		$first = $parts[0] ?? '';
		$last  = count( $parts ) > 1 ? end( $parts ) : '';
		$out   = mb_substr( $first, 0, 1 ) . mb_substr( (string) $last, 0, 1 );
		return mb_strtoupper( '' !== $out ? $out : '?' );
	}

	public static function person( ?object $reviewer ): ?array {
		if ( ! $reviewer ) {
			return null;
		}
		$role = 'reviewer';
		if ( 'wp_user' === $reviewer->type ) {
			$user = get_user_by( 'id', (int) $reviewer->wp_user_id );
			$role = $user ? ( Capabilities::role_for_user( $user ) ?? 'former' ) : 'former';
		}
		return array(
			'id'       => (int) $reviewer->id,
			'name'     => (string) $reviewer->display_name,
			'initials' => self::initials( (string) $reviewer->display_name ),
			'color'    => (string) ( $reviewer->color ?: '#4F46E5' ),
			'type'     => (string) $reviewer->type,
			'role'     => $role,
			'blocked'  => 'active' !== $reviewer->status,
		);
	}

	/**
	 * @param object[] $rows
	 */
	public static function items( array $rows, Actor $actor ): array {
		if ( ! $rows ) {
			return array();
		}
		$ids     = array_map( static fn( $row ) => (int) $row->id, $rows );
		$people  = array();
		foreach ( $rows as $row ) {
			$people[] = (int) $row->author_id;
			$people[] = (int) $row->assignee_id;
		}
		Reviewers::get_many( $people );
		$context = array(
			'replies'     => Items::reply_counts( $ids ),
			'attachments' => Attachments::for_items( $ids ),
			'reads'       => Reads::map( $actor->reviewer_id, $ids ),
		);
		return array_map( static fn( $row ) => self::item( $row, $actor, $context ), $rows );
	}

	public static function item( object $row, Actor $actor, ?array $context = null ): array {
		$id = (int) $row->id;
		if ( null === $context ) {
			$context = array(
				'replies'     => Items::reply_counts( array( $id ) ),
				'attachments' => Attachments::for_items( array( $id ) ),
				'reads'       => Reads::map( $actor->reviewer_id, array( $id ) ),
			);
		}
		$trashed     = Policy::is_trashed( $row );
		$attachments = array_values(
			array_filter(
				$context['attachments'][ $id ] ?? array(),
				static fn( $a ) => 0 === (int) $a->reply_id
			)
		);

		// Unread: someone else has been active since this person last looked.
		// Without a read marker, only activity since they joined counts, so a
		// newcomer is not greeted by every historical item flagged as new.
		$unread = false;
		if ( (int) $row->last_activity_by !== $actor->reviewer_id ) {
			if ( isset( $context['reads'][ $id ] ) ) {
				$unread = (int) $row->activity_rev > (int) $context['reads'][ $id ];
			} else {
				$unread = strtotime( $row->last_activity_at . ' UTC' ) >= strtotime( $actor->joined_at . ' UTC' );
			}
		}

		return array(
			'id'          => $id,
			'uuid'        => (string) $row->uuid,
			'title'       => (string) $row->title,
			'body'        => (string) $row->body,
			'status'      => (string) $row->status,
			'priority'    => (string) $row->priority,
			'author'      => self::person( Reviewers::get( (int) $row->author_id ) ),
			'assignee'    => self::person( Reviewers::get( (int) $row->assignee_id ) ),
			'page'        => array(
				'url'   => (string) $row->page_url,
				'key'   => (string) $row->page_key,
				'title' => (string) $row->page_title,
			),
			'pin'         => array(
				'type'   => (string) $row->pin_type,
				'x'      => (float) $row->pin_x,
				'y'      => (float) $row->pin_y,
				'anchor' => $row->anchor ? json_decode( (string) $row->anchor, true ) : null,
			),
			'viewport'    => array(
				'w' => (int) $row->viewport_w,
				'h' => (int) $row->viewport_h,
			),
			'context'     => $row->context ? json_decode( (string) $row->context, true ) : null,
			'counts'      => array(
				'replies'     => (int) ( $context['replies'][ $id ] ?? 0 ),
				'attachments' => count( $attachments ),
			),
			'attachments' => array_map( array( self::class, 'attachment' ), $attachments ),
			'unread'      => $unread,
			'order'       => (float) $row->board_order,
			'revision'    => (int) $row->revision,
			'activity'    => (int) $row->activity_rev,
			'created_at'  => self::time( $row->created_at ),
			'updated_at'  => self::time( $row->updated_at ),
			'edited_at'   => self::time( $row->edited_at ),
			'trashed'     => $trashed,
			'trashed_at'  => self::time( $row->deleted_at ),
			'can'         => array(
				'edit'     => Policy::can_edit_item( $actor, $row ),
				'delete'   => Policy::can_delete_item( $actor, $row ),
				'priority' => Policy::can_set_priority( $actor, $row ),
				'assign'   => Policy::can_assign( $actor, $row ),
				'reorder'  => ! $trashed && Policy::can_reorder( $actor ),
				'statuses' => Policy::allowed_statuses( $actor, $row ),
				'reply'    => Policy::can_reply( $actor, $row ),
				'note'     => Policy::can_add_note( $actor, $row ),
				'attach'   => Policy::can_attach( $actor, $row ),
				'restore'  => $trashed && Policy::can_manage_trash( $actor ),
				'purge'    => $trashed && Policy::can_manage_trash( $actor ),
			),
		);
	}

	/**
	 * Full item with replies, attachments and history.
	 */
	public static function detail( object $row, Actor $actor ): array {
		$item      = self::item( $row, $actor );
		$trash     = Policy::can_manage_trash( $actor );
		$replies   = Replies::for_item( (int) $row->id, $trash );
		$activity  = Activity::for_item( (int) $row->id );
		$people    = array();
		foreach ( $replies as $reply ) {
			$people[] = (int) $reply->author_id;
		}
		foreach ( $activity as $entry ) {
			$people[] = (int) $entry->actor_id;
			$data     = $entry->data ? json_decode( (string) $entry->data, true ) : array();
			if ( 'assigned' === $entry->action ) {
				$people[] = (int) ( $data['from'] ?? 0 );
				$people[] = (int) ( $data['to'] ?? 0 );
			}
		}
		Reviewers::get_many( $people );
		$all_attachments = Attachments::for_items( array( (int) $row->id ) )[ (int) $row->id ] ?? array();
		$by_reply        = array();
		foreach ( $all_attachments as $attachment ) {
			if ( (int) $attachment->reply_id > 0 ) {
				$by_reply[ (int) $attachment->reply_id ][] = self::attachment( $attachment );
			}
		}
		$item['replies']  = array_map(
			static function ( $reply ) use ( $actor, $by_reply ) {
				$out                = self::reply( $reply, $actor );
				$out['attachments'] = $by_reply[ (int) $reply->id ] ?? array();
				return $out;
			},
			$replies
		);
		$item['activity'] = array_map( array( self::class, 'activity' ), $activity );
		return $item;
	}

	public static function reply( object $row, Actor $actor ): array {
		$trashed = Policy::is_trashed( $row );
		return array(
			'id'         => (int) $row->id,
			'item_id'    => (int) $row->item_id,
			'kind'       => (string) $row->kind,
			'body'       => (string) $row->body,
			'author'     => self::person( Reviewers::get( (int) $row->author_id ) ),
			'revision'   => (int) $row->revision,
			'created_at' => self::time( $row->created_at ),
			'edited_at'  => self::time( $row->edited_at ),
			'trashed'    => $trashed,
			'can'        => array(
				'edit'    => Policy::can_edit_reply( $actor, $row ),
				'delete'  => Policy::can_delete_reply( $actor, $row ),
				'restore' => $trashed && Policy::can_manage_trash( $actor ),
				'purge'   => $trashed && Policy::can_manage_trash( $actor ),
			),
		);
	}

	public static function attachment( object $row ): array {
		$base = rest_url( 'mna-feedback/v1/attachments/' . (int) $row->id );
		return array(
			'id'          => (int) $row->id,
			'mime'        => (string) $row->mime,
			'width'       => (int) $row->width,
			'height'      => (int) $row->height,
			'size'        => (int) $row->size,
			'uploader_id' => (int) $row->uploader_id,
			'url'         => $base,
			'thumb_url'   => $row->thumb ? add_query_arg( 'size', 'thumb', $base ) : $base,
			'created_at'  => self::time( $row->created_at ),
		);
	}

	/**
	 * A shared link, for managers.
	 *
	 * @param array<int, int> $reviewer_counts
	 */
	public static function link( object $link, ?string $token = null, array $reviewer_counts = array() ): array {
		$creator = get_user_by( 'id', (int) $link->created_by );
		return array(
			'id'           => (int) $link->id,
			'label'        => (string) $link->label,
			'status'       => Access\Links::status( $link ),
			'url'          => Access\Links::url( $link, $token ),
			'landing_url'  => (string) $link->landing_url,
			'created_by'   => $creator ? (string) $creator->display_name : '',
			'created_at'   => self::time( $link->created_at ),
			'expires_at'   => self::time( $link->expires_at ),
			'revoked_at'   => self::time( $link->revoked_at ),
			'rotated_at'   => self::time( $link->rotated_at ),
			'last_used_at' => self::time( $link->last_used_at ),
			'use_count'    => (int) $link->use_count,
			'reviewers'    => (int) ( $reviewer_counts[ (int) $link->id ] ?? 0 ),
		);
	}

	/**
	 * A participant identity with the details only managers see.
	 *
	 * @param array<int, int> $item_counts
	 */
	public static function reviewer_admin( object $reviewer, array $item_counts = array() ): array {
		$link = (int) $reviewer->link_id > 0 ? Access\Links::get( (int) $reviewer->link_id ) : null;
		return array_merge(
			self::person( $reviewer ) ?? array(),
			array(
				'email'        => (string) $reviewer->email,
				'status'       => (string) $reviewer->status,
				'wp_user_id'   => (int) $reviewer->wp_user_id,
				'link'         => $link ? array(
					'id'    => (int) $link->id,
					'label' => (string) $link->label,
				) : null,
				'items'        => (int) ( $item_counts[ (int) $reviewer->id ] ?? 0 ),
				'created_at'   => self::time( $reviewer->created_at ),
				'last_seen_at' => self::time( $reviewer->last_seen_at ),
			)
		);
	}

	public static function activity( object $row ): array {
		$data = $row->data ? json_decode( (string) $row->data, true ) : array();
		if ( 'assigned' === $row->action ) {
			$data['from_person'] = self::person( Reviewers::get( (int) ( $data['from'] ?? 0 ) ) );
			$data['to_person']   = self::person( Reviewers::get( (int) ( $data['to'] ?? 0 ) ) );
		}
		return array(
			'id'         => (int) $row->id,
			'action'     => (string) $row->action,
			'source'     => (string) $row->source,
			'actor'      => self::person( Reviewers::get( (int) $row->actor_id ) ),
			'data'       => $data ?: new \stdClass(),
			'created_at' => self::time( $row->created_at ),
		);
	}
}
