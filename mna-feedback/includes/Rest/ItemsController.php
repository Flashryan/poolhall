<?php
/**
 * Feedback items, replies, attachments, change sync and filter data.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Rest;

use MNA\Feedback\Capabilities;
use MNA\Feedback\Data\Activity;
use MNA\Feedback\Data\Attachments;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Replies;
use MNA\Feedback\Data\Reviewers;
use MNA\Feedback\Device;
use MNA\Feedback\Formatter;
use MNA\Feedback\Policy;
use MNA\Feedback\Url;
use MNA\Feedback\Workflow;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class ItemsController {

	/** Items returned by one sync before the client is told to reload. */
	private const SYNC_LIMIT = 500;

	public static function register(): void {
		$id = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);

		register_rest_route(
			Router::NS,
			'/items',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'index' ),
					'permission_callback' => array( Router::class, 'can_read' ),
					'args'                => array(
						'url'      => array( 'type' => 'string' ),
						'page'     => array(
							'type'    => 'string',
							'pattern' => '^[a-f0-9]{64}$',
						),
						'status'   => array( 'type' => 'string' ),
						'priority' => array( 'type' => 'string' ),
						'device'   => array( 'type' => 'string' ),
						'author'   => array( 'type' => 'string' ),
						'assignee' => array( 'type' => 'string' ),
						'search'   => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'trashed'  => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'limit'    => array(
							'type'    => 'integer',
							'default' => 500,
							'minimum' => 1,
							'maximum' => 1000,
						),
						'offset'   => array(
							'type'    => 'integer',
							'default' => 0,
							'minimum' => 0,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create' ),
					'permission_callback' => array( Router::class, 'can_write' ),
					'args'                => array(
						'title'      => array(
							'type'      => 'string',
							'default'   => '',
							'maxLength' => 1000,
						),
						'body'       => array(
							'type'      => 'string',
							'default'   => '',
							'maxLength' => 50000,
						),
						'priority'   => array(
							'type'    => 'string',
							'enum'    => Policy::PRIORITIES,
							'default' => 'normal',
						),
						'page_url'   => array(
							'type'     => 'string',
							'required' => true,
						),
						'page_title' => array(
							'type'    => 'string',
							'default' => '',
						),
						'pin'        => array( 'type' => array( 'object', 'null' ) ),
						'anchor'     => array( 'type' => array( 'object', 'null' ) ),
						'context'    => array( 'type' => array( 'object', 'null' ) ),
						'viewport'   => array( 'type' => array( 'object', 'null' ) ),
						'device'     => array( 'type' => array( 'object', 'null' ) ),
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/items/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'show' ),
					'permission_callback' => array( Router::class, 'can_read' ),
					'args'                => $id,
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => array( Router::class, 'can_write' ),
					'args'                => $id + array(
						'title'       => array(
							'type'      => 'string',
							'maxLength' => 1000,
						),
						'body'        => array(
							'type'      => 'string',
							'maxLength' => 50000,
						),
						'priority'    => array(
							'type' => 'string',
							'enum' => Policy::PRIORITIES,
						),
						'status'      => array(
							'type' => 'string',
							'enum' => Policy::STATUSES,
						),
						'assignee_id' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'order'       => array( 'type' => 'number' ),
						'expected'    => array( 'type' => array( 'object', 'null' ) ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete' ),
					'permission_callback' => array( Router::class, 'can_write' ),
					'args'                => $id + array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/items/(?P<id>\d+)/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'restore' ),
				'permission_callback' => array( Router::class, 'can_write' ),
				'args'                => $id,
			)
		);

		register_rest_route(
			Router::NS,
			'/items/(?P<id>\d+)/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'mark_read' ),
				'permission_callback' => array( Router::class, 'can_read' ),
				'args'                => $id + array(
					'activity_rev' => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'The item activity revision the reader has seen. Defaults to the current one.',
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/items/(?P<id>\d+)/activity',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'activity' ),
				'permission_callback' => array( Router::class, 'can_read' ),
				'args'                => $id,
			)
		);

		register_rest_route(
			Router::NS,
			'/items/(?P<id>\d+)/replies',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'create_reply' ),
				'permission_callback' => array( Router::class, 'can_write' ),
				'args'                => $id + array(
					'body' => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 50000,
					),
					'kind'   => array(
						'type'    => 'string',
						'enum'    => Replies::KINDS,
						'default' => 'reply',
					),
					'device' => array( 'type' => array( 'object', 'null' ) ),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/replies/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( self::class, 'update_reply' ),
					'permission_callback' => array( Router::class, 'can_write' ),
					'args'                => $id + array(
						'body'          => array(
							'type'      => 'string',
							'required'  => true,
							'maxLength' => 50000,
						),
						'expected_body' => array( 'type' => 'string' ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete_reply' ),
					'permission_callback' => array( Router::class, 'can_write' ),
					'args'                => $id + array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/replies/(?P<id>\d+)/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'restore_reply' ),
				'permission_callback' => array( Router::class, 'can_write' ),
				'args'                => $id,
			)
		);

		register_rest_route(
			Router::NS,
			'/items/(?P<id>\d+)/attachments',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'upload' ),
				'permission_callback' => array( Router::class, 'can_write' ),
				'args'                => $id + array(
					'reply_id' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/attachments/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'download' ),
					'permission_callback' => array( Router::class, 'can_read' ),
					'args'                => $id + array(
						'size' => array(
							'type'    => 'string',
							'enum'    => array( 'full', 'thumb' ),
							'default' => 'full',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete_attachment' ),
					'permission_callback' => array( Router::class, 'can_write' ),
					'args'                => $id,
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/sync',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'sync' ),
				'permission_callback' => array( Router::class, 'can_read' ),
				'args'                => array(
					'since' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/pages',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'pages' ),
				'permission_callback' => array( Router::class, 'can_read' ),
			)
		);

		register_rest_route(
			Router::NS,
			'/people',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'people' ),
				'permission_callback' => array( Router::class, 'can_read' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Items
	 * ------------------------------------------------------------------ */

	public static function index( WP_REST_Request $request ) {
		$actor = Router::actor();
		$args  = array(
			'limit'  => (int) $request->get_param( 'limit' ),
			'offset' => (int) $request->get_param( 'offset' ),
			'order'  => 'board',
		);

		$page = (string) $request->get_param( 'page' );
		if ( '' === $page && $request->get_param( 'url' ) ) {
			$normalized = Url::normalize( (string) $request->get_param( 'url' ) );
			$page       = $normalized ? Url::key( $normalized ) : str_repeat( '0', 64 );
		}
		if ( '' !== $page ) {
			$args['page_key'] = $page;
		}

		foreach ( array(
			'status'   => Policy::STATUSES,
			'priority' => Policy::PRIORITIES,
			'device'   => Device::TYPES,
		) as $field => $allowed ) {
			$values = self::csv( (string) $request->get_param( $field ) );
			if ( $values ) {
				$args[ $field ] = array_values( array_intersect( $values, $allowed ) ) ?: array( '__none__' );
			}
		}

		$author = (string) $request->get_param( 'author' );
		if ( '' !== $author ) {
			$args['author_id'] = 'me' === $author ? $actor->reviewer_id : absint( $author );
		}
		$assignee = (string) $request->get_param( 'assignee' );
		if ( '' !== $assignee ) {
			$args['assignee'] = match ( $assignee ) {
				'me'    => $actor->reviewer_id,
				'none'  => 'none',
				default => absint( $assignee ),
			};
		}
		$search = trim( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			$args['search'] = $search;
		}
		if ( $request->get_param( 'trashed' ) && Policy::can_manage_trash( $actor ) ) {
			$args['trashed'] = true;
			$args['order']   = 'updated';
		}

		$server_time = gmdate( 'Y-m-d H:i:s' );
		$rows        = Items::query( $args );
		$total       = Items::count( $args );

		$response = new WP_REST_Response(
			array(
				'items'       => Formatter::items( $rows, $actor ),
				'total'       => $total,
				'server_time' => Formatter::time( $server_time ),
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		return $response;
	}

	public static function create( WP_REST_Request $request ) {
		$actor = Router::actor();
		$item  = Workflow::create_item(
			$actor,
			array(
				'title'      => $request->get_param( 'title' ),
				'body'       => $request->get_param( 'body' ),
				'priority'   => $request->get_param( 'priority' ),
				'page_url'   => $request->get_param( 'page_url' ),
				'page_title' => $request->get_param( 'page_title' ),
				'pin'        => $request->get_param( 'pin' ),
				'anchor'     => $request->get_param( 'anchor' ),
				'context'    => $request->get_param( 'context' ),
				'viewport'   => $request->get_param( 'viewport' ),
				'device'     => Device::detect( (string) $request->get_header( 'user_agent' ), $request->get_param( 'device' ), (array) ( $request->get_param( 'viewport' ) ?? array() ) ),
			)
		);
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		return new WP_REST_Response( Formatter::item( $item, $actor ), 201 );
	}

	public static function show( WP_REST_Request $request ) {
		$actor = Router::actor();
		$item  = Items::get( (int) $request->get_param( 'id' ) );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return Router::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		return Formatter::detail( $item, $actor );
	}

	public static function update( WP_REST_Request $request ) {
		$actor  = Router::actor();
		$params = $request->get_params();
		$in     = array_intersect_key( $params, array_flip( array( 'title', 'body', 'priority', 'status', 'assignee_id', 'order' ) ) );
		$in     = array_filter( $in, static fn( $value ) => null !== $value );

		$expected = $request->get_param( 'expected' );
		$item     = Workflow::update_item( $actor, (int) $request->get_param( 'id' ), $in, is_array( $expected ) ? $expected : null );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		return Formatter::item( $item, $actor );
	}

	public static function delete( WP_REST_Request $request ) {
		$actor = Router::actor();
		$id    = (int) $request->get_param( 'id' );
		if ( $request->get_param( 'force' ) ) {
			$result = Workflow::purge_item( $actor, $id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'deleted' => true,
				'purged'  => true,
				'id'      => $id,
			);
		}
		$item = Workflow::trash_item( $actor, $id );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		return array(
			'deleted' => true,
			'purged'  => false,
			'id'      => $id,
			'item'    => Policy::can_view_item( $actor, $item ) ? Formatter::item( $item, $actor ) : null,
		);
	}

	public static function restore( WP_REST_Request $request ) {
		$actor = Router::actor();
		$item  = Workflow::restore_item( $actor, (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		return Formatter::item( $item, $actor );
	}

	public static function mark_read( WP_REST_Request $request ) {
		$actor = Router::actor();
		$item  = Items::get( (int) $request->get_param( 'id' ) );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return Router::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		$seen = $request->get_param( 'activity_rev' );
		Workflow::mark_read( $actor, (int) $item->id, null === $seen ? null : (int) $seen );
		return array( 'ok' => true );
	}

	public static function activity( WP_REST_Request $request ) {
		$actor = Router::actor();
		$item  = Items::get( (int) $request->get_param( 'id' ) );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return Router::error( 'mnafb_not_found', __( 'That feedback no longer exists.', 'mna-feedback' ), 404 );
		}
		$rows   = Activity::for_item( (int) $item->id );
		$people = array();
		foreach ( $rows as $row ) {
			$people[] = (int) $row->actor_id;
		}
		Reviewers::get_many( $people );
		return array_map( array( Formatter::class, 'activity' ), $rows );
	}

	/* ---------------------------------------------------------------------
	 * Replies
	 * ------------------------------------------------------------------ */

	public static function create_reply( WP_REST_Request $request ) {
		$actor = Router::actor();
		$id    = (int) $request->get_param( 'id' );
		$device = Device::detect( (string) $request->get_header( 'user_agent' ), $request->get_param( 'device' ) );
		$reply  = Workflow::add_reply( $actor, $id, (string) $request->get_param( 'body' ), (string) $request->get_param( 'kind' ), $device );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		$item = Items::get( $id );
		return new WP_REST_Response(
			array(
				'reply' => Formatter::reply( $reply, $actor ) + array( 'attachments' => array() ),
				'item'  => $item ? Formatter::item( $item, $actor ) : null,
			),
			201
		);
	}

	public static function update_reply( WP_REST_Request $request ) {
		$actor    = Router::actor();
		$expected = $request->get_param( 'expected_body' );
		$reply    = Workflow::update_reply( $actor, (int) $request->get_param( 'id' ), (string) $request->get_param( 'body' ), null === $expected ? null : (string) $expected );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		return Formatter::reply( $reply, $actor );
	}

	public static function delete_reply( WP_REST_Request $request ) {
		$actor = Router::actor();
		$id    = (int) $request->get_param( 'id' );
		if ( $request->get_param( 'force' ) ) {
			$result = Workflow::purge_reply( $actor, $id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'deleted' => true,
				'purged'  => true,
				'id'      => $id,
			);
		}
		$reply = Workflow::trash_reply( $actor, $id );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		return array(
			'deleted' => true,
			'purged'  => false,
			'id'      => $id,
			'reply'   => Formatter::reply( $reply, $actor ),
		);
	}

	public static function restore_reply( WP_REST_Request $request ) {
		$actor = Router::actor();
		$reply = Workflow::restore_reply( $actor, (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		return Formatter::reply( $reply, $actor );
	}

	/* ---------------------------------------------------------------------
	 * Attachments
	 * ------------------------------------------------------------------ */

	public static function upload( WP_REST_Request $request ) {
		$actor = Router::actor();
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) ) {
			return Router::error( 'mnafb_no_file', __( 'Choose an image to upload.', 'mna-feedback' ), 400 );
		}
		$attachment = Workflow::add_attachment( $actor, (int) $request->get_param( 'id' ), $file, (int) $request->get_param( 'reply_id' ) );
		if ( is_wp_error( $attachment ) ) {
			return $attachment;
		}
		return new WP_REST_Response( Formatter::attachment( $attachment ), 201 );
	}

	public static function download( WP_REST_Request $request ) {
		$actor      = Router::actor();
		$attachment = Attachments::get( (int) $request->get_param( 'id' ) );
		$item       = $attachment ? Items::get( (int) $attachment->item_id ) : null;
		if ( ! $attachment || ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return Router::error( 'mnafb_not_found', __( 'That screenshot no longer exists.', 'mna-feedback' ), 404 );
		}
		Attachments::stream( $attachment, (string) $request->get_param( 'size' ) );
		return Router::error( 'mnafb_not_found', __( 'That screenshot no longer exists.', 'mna-feedback' ), 404 );
	}

	public static function delete_attachment( WP_REST_Request $request ) {
		$actor  = Router::actor();
		$result = Workflow::delete_attachment( $actor, (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'deleted' => true,
			'id'      => (int) $request->get_param( 'id' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Sync and filter data
	 * ------------------------------------------------------------------ */

	/**
	 * Items changed since the given time. Items this person can no longer see
	 * (moved to Trash, deleted permanently) are listed in "removed".
	 */
	public static function sync( WP_REST_Request $request ) {
		$actor = Router::actor();
		$since = Router::utc_datetime( (string) $request->get_param( 'since' ) );
		if ( ! $since ) {
			return Router::error( 'mnafb_bad_since', __( 'Invalid sync time.', 'mna-feedback' ), 400 );
		}
		$server_time = gmdate( 'Y-m-d H:i:s' );
		// Overlap slightly so writes landing in the same second are not missed.
		$from = gmdate( 'Y-m-d H:i:s', strtotime( $since . ' UTC' ) - 5 );

		$rows = Items::query(
			array(
				'updated_since' => $from,
				'trashed'       => 'any',
				'order'         => 'updated',
				'limit'         => self::SYNC_LIMIT + 1,
			)
		);
		if ( count( $rows ) > self::SYNC_LIMIT ) {
			return array(
				'reset'       => true,
				'items'       => array(),
				'removed'     => array(),
				'server_time' => Formatter::time( $server_time ),
			);
		}

		$visible = array();
		$removed = Activity::purged_since( $from );
		foreach ( $rows as $row ) {
			if ( Policy::can_view_item( $actor, $row ) ) {
				$visible[] = $row;
			} else {
				$removed[] = (int) $row->id;
			}
		}

		return array(
			'reset'       => false,
			'items'       => Formatter::items( $visible, $actor ),
			'removed'     => array_values( array_unique( $removed ) ),
			'server_time' => Formatter::time( $server_time ),
		);
	}

	public static function pages() {
		return Items::pages();
	}

	/**
	 * People for filters and assignment: everyone who has left feedback, plus
	 * team members who can be assigned work. Email addresses are never included.
	 */
	public static function people() {
		global $wpdb;
		$table   = Items::table();
		$authors = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT author_id FROM {$table} WHERE deleted_at IS NULL" ) ?: array() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$assigned = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT assignee_id FROM {$table} WHERE deleted_at IS NULL AND assignee_id > 0" ) ?: array() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$assignable = array();
		foreach ( Capabilities::assignable_users() as $user ) {
			$reviewer = Reviewers::for_wp_user( $user );
			if ( $reviewer && 'active' === $reviewer->status ) {
				$assignable[] = (int) $reviewer->id;
			}
		}

		$people = array();
		foreach ( Reviewers::get_many( array_merge( $authors, $assigned, $assignable ) ) as $reviewer ) {
			$people[] = Formatter::person( $reviewer );
		}
		usort( $people, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

		return array(
			'people'     => $people,
			'assignable' => array_values( array_unique( $assignable ) ),
		);
	}

	/**
	 * @return string[]
	 */
	private static function csv( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}
}
