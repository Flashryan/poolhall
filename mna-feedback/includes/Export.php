<?php
/**
 * CSV and JSON exports of every feedback record on this site.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

use MNA\Feedback\Data\Activity;
use MNA\Feedback\Data\Attachments;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Replies;
use MNA\Feedback\Data\Reviewers;

defined( 'ABSPATH' ) || exit;

final class Export {

	public static function filename( string $format ): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return sanitize_file_name( 'feedback-' . $host . '-' . gmdate( 'Y-m-d-His' ) . '.' . $format );
	}

	/**
	 * Every item (including Trash) with its replies, history and screenshot details.
	 */
	public static function data( bool $include_trashed = true ): array {
		$items = self::all_items( $include_trashed );
		$ids   = array_map( static fn( $row ) => (int) $row->id, $items );

		$people = array();
		foreach ( $items as $row ) {
			$people[] = (int) $row->author_id;
			$people[] = (int) $row->assignee_id;
		}
		Reviewers::get_many( $people );
		$reply_counts = Items::reply_counts( $ids );
		$attachments  = $ids ? Attachments::for_items( $ids ) : array();

		$out = array();
		foreach ( $items as $row ) {
			$id      = (int) $row->id;
			$replies = Replies::for_item( $id, true );
			Reviewers::get_many( array_map( static fn( $reply ) => (int) $reply->author_id, $replies ) );
			$history = Activity::for_item( $id, 5000 );
			Reviewers::get_many( array_map( static fn( $entry ) => (int) $entry->actor_id, $history ) );

			$out[] = array(
				'id'           => $id,
				'uuid'         => (string) $row->uuid,
				'title'        => (string) $row->title,
				'description'  => (string) $row->body,
				'status'       => (string) $row->status,
				'priority'     => (string) $row->priority,
				'author'       => self::who( (int) $row->author_id ),
				'assignee'     => (int) $row->assignee_id ? self::who( (int) $row->assignee_id ) : null,
				'page'         => array(
					'url'   => (string) $row->page_url,
					'title' => (string) $row->page_title,
				),
				'pin'          => array(
					'type'   => (string) $row->pin_type,
					'x'      => (float) $row->pin_x,
					'y'      => (float) $row->pin_y,
					'anchor' => $row->anchor ? json_decode( (string) $row->anchor, true ) : null,
				),
				'viewport'     => array(
					'width'  => (int) $row->viewport_w,
					'height' => (int) $row->viewport_h,
				),
				'context'      => $row->context ? json_decode( (string) $row->context, true ) : null,
				'reply_count'  => (int) ( $reply_counts[ $id ] ?? 0 ),
				'created_at'   => Formatter::time( $row->created_at ),
				'updated_at'   => Formatter::time( $row->updated_at ),
				'edited_at'    => Formatter::time( $row->edited_at ),
				'in_trash'     => ! empty( $row->deleted_at ),
				'trashed_at'   => Formatter::time( $row->deleted_at ),
				'replies'      => array_map(
					static fn( $reply ) => array(
						'id'         => (int) $reply->id,
						'kind'       => (string) $reply->kind,
						'body'       => (string) $reply->body,
						'author'     => self::who( (int) $reply->author_id ),
						'created_at' => Formatter::time( $reply->created_at ),
						'edited_at'  => Formatter::time( $reply->edited_at ),
						'in_trash'   => ! empty( $reply->deleted_at ),
					),
					$replies
				),
				'history'      => array_map(
					static fn( $entry ) => array(
						'action'     => (string) $entry->action,
						'source'     => (string) $entry->source,
						'actor'      => self::who( (int) $entry->actor_id ),
						'data'       => $entry->data ? json_decode( (string) $entry->data, true ) : null,
						'created_at' => Formatter::time( $entry->created_at ),
					),
					$history
				),
				'screenshots'  => array_map(
					static fn( $a ) => array(
						'id'         => (int) $a->id,
						'reply_id'   => (int) $a->reply_id,
						'mime'       => (string) $a->mime,
						'width'      => (int) $a->width,
						'height'     => (int) $a->height,
						'bytes'      => (int) $a->size,
						'uploader'   => self::who( (int) $a->uploader_id ),
						'created_at' => Formatter::time( $a->created_at ),
					),
					$attachments[ $id ] ?? array()
				),
			);
		}

		return array(
			'generator'   => 'MNA Feedback ' . MNAFB_VERSION,
			'site'        => home_url( '/' ),
			'exported_at' => Formatter::time( gmdate( 'Y-m-d H:i:s' ) ),
			'items'       => $out,
		);
	}

	public static function json(): string {
		return (string) wp_json_encode( self::data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	public static function csv(): string {
		$data   = self::data();
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so spreadsheets detect the encoding.
		fputcsv(
			$handle,
			array( 'ID', 'Title', 'Description', 'Status', 'Priority', 'Page', 'Page URL', 'Author', 'Author email', 'Assignee', 'Pin', 'Replies', 'Screenshots', 'Created (UTC)', 'Updated (UTC)', 'In Trash' ),
			',',
			'"',
			''
		);
		foreach ( $data['items'] as $item ) {
			fputcsv(
				$handle,
				array_map(
					array( self::class, 'cell' ),
					array(
						$item['id'],
						$item['title'],
						$item['description'],
						self::status_label( $item['status'] ),
						ucfirst( $item['priority'] ),
						$item['page']['title'],
						$item['page']['url'],
						$item['author']['name'] ?? '',
						$item['author']['email'] ?? '',
						$item['assignee']['name'] ?? '',
						'element' === $item['pin']['type'] ? 'Element' : 'Page',
						$item['reply_count'],
						count( $item['screenshots'] ),
						$item['created_at'],
						$item['updated_at'],
						$item['in_trash'] ? 'Yes' : 'No',
					)
				),
				',',
				'"',
				''
			);
		}
		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $csv;
	}

	/**
	 * Neutralises spreadsheet formulas so exported text can never execute.
	 */
	public static function cell( mixed $value ): string {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $value ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public static function status_label( string $status ): string {
		return match ( $status ) {
			'in_progress' => 'In progress',
			'done'        => 'Done',
			default       => 'Open',
		};
	}

	/**
	 * @return object[]
	 */
	private static function all_items( bool $include_trashed ): array {
		$rows   = array();
		$offset = 0;
		do {
			$batch  = Items::query(
				array(
					'trashed' => $include_trashed ? 'any' : false,
					'order'   => 'oldest',
					'limit'   => 1000,
					'offset'  => $offset,
				)
			);
			$rows   = array_merge( $rows, $batch );
			$offset += 1000;
		} while ( count( $batch ) === 1000 );
		return $rows;
	}

	private static function who( int $reviewer_id ): ?array {
		$reviewer = Reviewers::get( $reviewer_id );
		if ( ! $reviewer ) {
			return null;
		}
		return array(
			'id'    => (int) $reviewer->id,
			'name'  => (string) $reviewer->display_name,
			'email' => (string) $reviewer->email,
			'type'  => 'wp_user' === $reviewer->type ? 'team' : 'guest',
		);
	}
}
