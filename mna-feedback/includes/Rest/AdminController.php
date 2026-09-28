<?php
/**
 * Manager endpoints: shared links, participants, team roles, settings,
 * exports, Trash and the full purge.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Rest;

use MNA\Feedback\Access\Links;
use MNA\Feedback\Access\Sessions;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Replies;
use MNA\Feedback\Data\Reviewers;
use MNA\Feedback\Export;
use MNA\Feedback\Formatter;
use MNA\Feedback\Installer;
use MNA\Feedback\Settings;
use MNA\Feedback\Team;
use MNA\Feedback\Url;
use MNA\Feedback\Workflow;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class AdminController {

	public const PURGE_PHRASE = 'DELETE ALL FEEDBACK';

	public static function register(): void {
		$manage = array( Router::class, 'can_manage' );
		$id     = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
		$link   = array(
			'label'       => array(
				'type'      => 'string',
				'maxLength' => 120,
			),
			'expires_at'  => array(
				'type'        => array( 'string', 'null' ),
				'description' => 'ISO 8601 date or date-time. A date alone means the end of that day in the site time zone. Empty or null for no expiry.',
			),
			'landing_url' => array( 'type' => 'string' ),
		);

		register_rest_route(
			Router::NS,
			'/admin/links',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'links' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create_link' ),
					'permission_callback' => $manage,
					'args'                => $link,
				),
			)
		);
		register_rest_route(
			Router::NS,
			'/admin/links/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( self::class, 'update_link' ),
				'permission_callback' => $manage,
				'args'                => $id + $link,
			)
		);
		register_rest_route(
			Router::NS,
			'/admin/links/(?P<id>\d+)/(?P<op>revoke|rotate|reactivate)',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'link_action' ),
				'permission_callback' => $manage,
				'args'                => $id,
			)
		);

		register_rest_route(
			Router::NS,
			'/admin/reviewers',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'reviewers' ),
				'permission_callback' => $manage,
			)
		);
		register_rest_route(
			Router::NS,
			'/admin/reviewers/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( self::class, 'update_reviewer' ),
				'permission_callback' => $manage,
				'args'                => $id + array(
					'status'       => array(
						'type' => 'string',
						'enum' => array( 'active', 'blocked' ),
					),
					'display_name' => array(
						'type'      => 'string',
						'maxLength' => 200,
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/admin/team',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'team' ),
					'permission_callback' => $manage,
					'args'                => array(
						'search' => array(
							'type'      => 'string',
							'maxLength' => 100,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'set_team_role' ),
					'permission_callback' => $manage,
					'args'                => array(
						'user_id' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
						'role'    => array(
							'type'        => 'string',
							'enum'        => array( 'reviewer', 'implementer', 'manager', 'none' ),
							'required'    => true,
							'description' => 'Feedback role to grant, or "none" to remove access.',
						),
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/admin/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'settings' ),
					'permission_callback' => $manage,
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( self::class, 'update_settings' ),
					'permission_callback' => $manage,
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/admin/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'export' ),
				'permission_callback' => $manage,
				'args'                => array(
					'format' => array(
						'type'    => 'string',
						'enum'    => array( 'json', 'csv' ),
						'default' => 'json',
					),
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/admin/trash',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'trash' ),
				'permission_callback' => $manage,
			)
		);
		register_rest_route(
			Router::NS,
			'/admin/trash/empty',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'empty_trash' ),
				'permission_callback' => $manage,
			)
		);

		register_rest_route(
			Router::NS,
			'/admin/purge',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'purge' ),
				'permission_callback' => $manage,
				'args'                => array(
					'confirm' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Links
	 * ------------------------------------------------------------------ */

	public static function links() {
		$counts = Links::reviewer_counts();
		return array_map( static fn( $link ) => Formatter::link( $link, null, $counts ), Links::all() );
	}

	public static function create_link( WP_REST_Request $request ) {
		$fields = self::link_fields( $request, true );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$created = Links::create( $fields['label'], $fields['expires_at'], $fields['landing_url'], get_current_user_id() );
		if ( ! $created['link'] ) {
			return Router::error( 'mnafb_db', __( 'The link could not be created.', 'mna-feedback' ), 500 );
		}
		return new WP_REST_Response( Formatter::link( $created['link'], $created['token'] ), 201 );
	}

	public static function update_link( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( ! Links::get( $id ) ) {
			return Router::error( 'mnafb_not_found', __( 'That link no longer exists.', 'mna-feedback' ), 404 );
		}
		$fields = self::link_fields( $request, false );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		Links::update( $id, $fields );
		return Formatter::link( Links::get( $id ), null, Links::reviewer_counts() );
	}

	public static function link_action( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		if ( ! Links::get( $id ) ) {
			return Router::error( 'mnafb_not_found', __( 'That link no longer exists.', 'mna-feedback' ), 404 );
		}
		$token = null;
		switch ( (string) $request->get_param( 'op' ) ) {
			case 'revoke':
				Links::revoke( $id );
				break;
			case 'reactivate':
				Links::reactivate( $id );
				break;
			case 'rotate':
				$token = Links::rotate( $id );
				break;
		}
		return Formatter::link( Links::get( $id ), $token, Links::reviewer_counts() );
	}

	/**
	 * Validated link fields from a request. On create, missing fields get defaults.
	 */
	public static function link_fields( WP_REST_Request $request, bool $creating ): array|\WP_Error {
		$fields = array();
		if ( $creating || null !== $request->get_param( 'label' ) ) {
			$label           = trim( sanitize_text_field( (string) $request->get_param( 'label' ) ) );
			$fields['label'] = '' !== $label ? $label : __( 'Review link', 'mna-feedback' );
		}
		if ( $creating || $request->has_param( 'expires_at' ) ) {
			$expiry = self::parse_expiry( $request->get_param( 'expires_at' ) );
			if ( false === $expiry ) {
				return Router::error( 'mnafb_bad_expiry', __( 'The expiry date is not valid.', 'mna-feedback' ), 400 );
			}
			$fields['expires_at'] = $expiry;
		}
		if ( $creating || null !== $request->get_param( 'landing_url' ) ) {
			$landing = trim( (string) $request->get_param( 'landing_url' ) );
			if ( '' === $landing ) {
				$fields['landing_url'] = home_url( '/' );
			} else {
				$normalized = Url::normalize( $landing );
				if ( ! $normalized ) {
					return Router::error( 'mnafb_bad_landing', __( 'The starting page must be a page on this site.', 'mna-feedback' ), 400 );
				}
				$fields['landing_url'] = $normalized;
			}
		}
		return $fields;
	}

	/**
	 * Parses an expiry value. Returns a UTC MySQL datetime, null for "never",
	 * or false when invalid.
	 */
	public static function parse_expiry( mixed $value ): string|null|false {
		if ( null === $value ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			// A date alone: the end of that day in the site's time zone.
			$date = date_create_immutable( $value . ' 23:59:59', wp_timezone() );
			return $date ? $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : false;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
			// A local date-time without a zone (from a datetime-local field).
			$date = date_create_immutable( str_replace( 'T', ' ', $value ), wp_timezone() );
			return $date ? $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : false;
		}
		$ts = strtotime( $value );
		return false === $ts ? false : gmdate( 'Y-m-d H:i:s', $ts );
	}

	/* ---------------------------------------------------------------------
	 * Participants and team
	 * ------------------------------------------------------------------ */

	public static function reviewers() {
		$counts = Reviewers::item_counts();
		return array_map( static fn( $reviewer ) => Formatter::reviewer_admin( $reviewer, $counts ), Reviewers::all() );
	}

	public static function update_reviewer( WP_REST_Request $request ) {
		$id       = (int) $request->get_param( 'id' );
		$reviewer = Reviewers::get( $id );
		if ( ! $reviewer ) {
			return Router::error( 'mnafb_not_found', __( 'That person no longer exists.', 'mna-feedback' ), 404 );
		}
		$fields = array();
		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			if ( 'wp_user' === $reviewer->type && 'blocked' === $status ) {
				return Router::error( 'mnafb_team_member', __( 'Team members are managed on the Team tab.', 'mna-feedback' ), 400 );
			}
			$fields['status'] = (string) $status;
		}
		$name = $request->get_param( 'display_name' );
		if ( null !== $name && 'guest' === $reviewer->type ) {
			$clean = Reviewers::sanitize_name( (string) $name );
			if ( '' === $clean ) {
				return Router::error( 'mnafb_name_required', __( 'The name cannot be empty.', 'mna-feedback' ), 400 );
			}
			$fields['display_name'] = $clean;
		}
		if ( $fields ) {
			Reviewers::update( $id, $fields );
			if ( 'blocked' === ( $fields['status'] ?? '' ) ) {
				Sessions::revoke_for_reviewer( $id );
			}
		}
		return Formatter::reviewer_admin( Reviewers::get( $id ), Reviewers::item_counts() );
	}

	public static function team( WP_REST_Request $request ) {
		$search = (string) $request->get_param( 'search' );
		return array(
			'members'    => Team::members(),
			'candidates' => '' !== $search ? Team::search( $search ) : array(),
		);
	}

	public static function set_team_role( WP_REST_Request $request ) {
		$role   = (string) $request->get_param( 'role' );
		$role   = 'none' === $role ? null : $role;
		$result = Team::set_role( (int) $request->get_param( 'user_id' ), $role, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'members' => Team::members() );
	}

	/* ---------------------------------------------------------------------
	 * Settings, export, Trash, purge
	 * ------------------------------------------------------------------ */

	public static function settings() {
		return Settings::all() + array( 'logo_url' => Settings::branding()['logo'] );
	}

	public static function update_settings( WP_REST_Request $request ) {
		$input = array_intersect_key( (array) $request->get_json_params() + $request->get_body_params(), Settings::DEFAULTS );
		Settings::update( $input );
		return self::settings();
	}

	public static function export( WP_REST_Request $request ) {
		$format = (string) $request->get_param( 'format' );
		if ( 'csv' === $format ) {
			$response = new WP_REST_Response( Export::csv() );
			$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		} else {
			$response = new WP_REST_Response( Export::data() );
		}
		$response->header( 'Content-Disposition', 'attachment; filename="' . Export::filename( $format ) . '"' );
		return $response;
	}

	public static function trash() {
		$actor   = Router::actor();
		$items   = Items::query(
			array(
				'trashed' => true,
				'order'   => 'updated',
				'limit'   => 500,
			)
		);
		$replies = Replies::trashed( 500 );
		$people  = array();
		foreach ( $replies as $reply ) {
			$people[] = (int) $reply->author_id;
		}
		Reviewers::get_many( $people );
		return array(
			'items'   => Formatter::items( $items, $actor ),
			'replies' => array_map(
				static function ( $reply ) use ( $actor ) {
					$item = Items::get( (int) $reply->item_id );
					return Formatter::reply( $reply, $actor ) + array(
						'item_title'   => $item ? (string) $item->title : '',
						'item_trashed' => $item ? ! empty( $item->deleted_at ) : true,
						'trashed_at'   => Formatter::time( $reply->deleted_at ),
					);
				},
				$replies
			),
		);
	}

	public static function empty_trash() {
		$actor  = Router::actor();
		$purged = 0;
		foreach ( Replies::trashed( 5000 ) as $reply ) {
			if ( true === Workflow::purge_reply( $actor, (int) $reply->id ) ) {
				++$purged;
			}
		}
		do {
			$batch = Items::query(
				array(
					'trashed' => true,
					'limit'   => 200,
				)
			);
			foreach ( $batch as $item ) {
				if ( true === Workflow::purge_item( $actor, (int) $item->id ) ) {
					++$purged;
				}
			}
		} while ( count( $batch ) === 200 );
		return array( 'purged' => $purged );
	}

	public static function purge( WP_REST_Request $request ) {
		if ( self::PURGE_PHRASE !== trim( (string) $request->get_param( 'confirm' ) ) ) {
			return Router::error(
				'mnafb_confirm',
				/* translators: %s: confirmation phrase */
				sprintf( __( 'Type %s to confirm.', 'mna-feedback' ), self::PURGE_PHRASE ),
				400
			);
		}
		Installer::purge_site_data( true );
		return array( 'purged' => true );
	}
}
