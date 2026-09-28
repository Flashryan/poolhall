<?php
/**
 * /session - who am I, join through a shared link, update my name, leave.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Rest;

use MNA\Feedback\Access\Actor;
use MNA\Feedback\Access\Auth;
use MNA\Feedback\Access\Cookies;
use MNA\Feedback\Access\Join;
use MNA\Feedback\Access\Links;
use MNA\Feedback\Access\Sessions;
use MNA\Feedback\Capabilities;
use MNA\Feedback\Data\Reviewers;
use MNA\Feedback\Formatter;
use MNA\Feedback\Settings;
use MNA\Feedback\Url;
use MNA\Feedback\Workflow;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class SessionController {

	public static function register(): void {
		register_rest_route(
			Router::NS,
			'/session',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'show' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'url' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'join' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'name'  => array(
							'type'      => 'string',
							'required'  => true,
							'maxLength' => 200,
						),
						'email' => array(
							'type'      => 'string',
							'default'   => '',
							'maxLength' => 190,
						),
						'url'   => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( self::class, 'update' ),
					'permission_callback' => array( Router::class, 'can_write' ),
					'args'                => array(
						'name'  => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'email' => array(
							'type'      => 'string',
							'maxLength' => 190,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'leave' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			Router::NS,
			'/session/nonce',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'nonce' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			Router::NS,
			'/session/return-link',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'return_link' ),
					'permission_callback' => array( Router::class, 'can_read' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'rotate_return_link' ),
					'permission_callback' => array( Router::class, 'can_write' ),
				),
			)
		);
	}

	/**
	 * Who is asking. Answers 200 either way (so browsers do not log an error on
	 * every page view); "authenticated" says whether a session exists and, if
	 * not, what the interface should show instead.
	 */
	public static function show( WP_REST_Request $request ) {
		$actor = Auth::current();
		if ( ! $actor ) {
			$state = self::not_joined();
			$data  = (array) $state->get_error_data();
			unset( $data['status'] );
			return array( 'authenticated' => false ) + $data;
		}
		return self::payload( $actor, (string) $request->get_param( 'url' ) );
	}

	/**
	 * Creates a reviewer identity and session from the pending shared link.
	 */
	public static function join( WP_REST_Request $request ) {
		$pending = Join::pending();
		if ( 'valid' !== $pending['state'] ) {
			return Router::error(
				'mnafb_link_invalid',
				self::reason_message( $pending['reason'] ?: 'invalid' ),
				403,
				array( 'reason' => $pending['reason'] ?: 'invalid' )
			);
		}

		$name = Reviewers::sanitize_name( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return Router::error( 'mnafb_name_required', __( 'Enter your name so the team knows who left each comment.', 'mna-feedback' ), 400 );
		}
		$email = trim( (string) $request->get_param( 'email' ) );
		if ( '' !== $email ) {
			$email = sanitize_email( $email );
			if ( ! is_email( $email ) ) {
				return Router::error( 'mnafb_bad_email', __( 'That email address does not look right. Leave it blank if you prefer.', 'mna-feedback' ), 400 );
			}
		}
		if ( ! Join::allow_from_ip() ) {
			return Router::error( 'mnafb_rate_limited', __( 'Too many people have joined from this network recently. Try again later.', 'mna-feedback' ), 429 );
		}

		$link    = $pending['link'];
		$created = Reviewers::create_guest( $name, $email, (int) $link->id );
		if ( empty( $created['reviewer'] ) ) {
			return Router::error( 'mnafb_db', __( 'Your review session could not be started. Please try again.', 'mna-feedback' ), 500 );
		}

		Sessions::end_current();
		Sessions::create( (int) $created['reviewer']->id, (int) $link->id );
		Join::clear();
		Auth::reset();

		$actor = Auth::current();
		if ( ! $actor ) {
			return Router::error( 'mnafb_session', __( 'Your review session could not be started. Check that cookies are allowed for this site.', 'mna-feedback' ), 500 );
		}

		$payload                = self::payload( $actor, (string) $request->get_param( 'url' ) );
		$payload['return_link'] = Reviewers::return_url( $created['reviewer'], $created['return_token'] );
		return new WP_REST_Response( $payload, 201 );
	}

	public static function update( WP_REST_Request $request ) {
		$actor = Router::actor();
		if ( ! $actor->is_guest() ) {
			return Router::error( 'mnafb_wp_profile', __( 'Your name comes from your WordPress profile.', 'mna-feedback' ), 400 );
		}
		$fields = array();
		if ( null !== $request->get_param( 'name' ) ) {
			$name = Reviewers::sanitize_name( (string) $request->get_param( 'name' ) );
			if ( '' === $name ) {
				return Router::error( 'mnafb_name_required', __( 'Your name cannot be empty.', 'mna-feedback' ), 400 );
			}
			$fields['display_name'] = $name;
		}
		if ( null !== $request->get_param( 'email' ) ) {
			$email = trim( (string) $request->get_param( 'email' ) );
			if ( '' !== $email && ! is_email( sanitize_email( $email ) ) ) {
				return Router::error( 'mnafb_bad_email', __( 'That email address does not look right.', 'mna-feedback' ), 400 );
			}
			$fields['email'] = '' === $email ? '' : sanitize_email( $email );
		}
		if ( $fields ) {
			Reviewers::update( $actor->reviewer_id, $fields );
			Auth::reset();
		}
		return self::payload( Router::actor(), '' );
	}

	/**
	 * Guests: sign out on this device. Team members: hide the review interface.
	 */
	public static function leave() {
		$actor = Auth::current();
		if ( ! $actor || $actor->is_guest() ) {
			Sessions::end_current();
		}
		Join::clear();
		Cookies::set_flag( false );
		Auth::reset();
		return array( 'ok' => true );
	}

	/**
	 * A wp_rest nonce for a signed-in team member. Public pages never contain
	 * one (they may be cached), so the interface asks for it here. The custom
	 * header cannot be sent by other origins, and the router refuses
	 * cross-site requests, so only this site's own pages can read the nonce.
	 */
	public static function nonce( WP_REST_Request $request ) {
		if ( '1' !== (string) $request->get_header( Router::CLIENT_HEADER ) ) {
			return Router::error( 'mnafb_missing_header', __( 'This request is missing the X-MNAFB-Client header.', 'mna-feedback' ), 400 );
		}
		$user_id = is_user_logged_in() ? get_current_user_id() : Auth::cookie_user_id();
		$user    = $user_id ? get_user_by( 'id', $user_id ) : null;
		if ( ! $user || null === Capabilities::role_for_user( $user ) ) {
			return Router::error( 'mnafb_no_login', __( 'Not signed in as a team member.', 'mna-feedback' ), 401 );
		}
		wp_set_current_user( $user->ID );
		return array( 'nonce' => wp_create_nonce( 'wp_rest' ) );
	}

	public static function return_link() {
		$actor = Router::actor();
		if ( ! $actor->is_guest() ) {
			return Router::error( 'mnafb_not_guest', __( 'Sign in to WordPress on your other device instead.', 'mna-feedback' ), 400 );
		}
		$reviewer = Reviewers::get( $actor->reviewer_id );
		$url      = $reviewer ? Reviewers::return_url( $reviewer ) : null;
		if ( ! $url ) {
			$token = Reviewers::rotate_return_token( $actor->reviewer_id );
			$url   = Reviewers::return_url( Reviewers::get( $actor->reviewer_id ), $token );
		}
		return array( 'url' => $url );
	}

	public static function rotate_return_link() {
		$actor = Router::actor();
		if ( ! $actor->is_guest() ) {
			return Router::error( 'mnafb_not_guest', __( 'Sign in to WordPress on your other device instead.', 'mna-feedback' ), 400 );
		}
		$token = Reviewers::rotate_return_token( $actor->reviewer_id );
		return array( 'url' => Reviewers::return_url( Reviewers::get( $actor->reviewer_id ), $token ) );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Everything the interface needs to start.
	 */
	public static function payload( Actor $actor, string $url ): array {
		$reviewer = Reviewers::get( $actor->reviewer_id );
		$me       = Formatter::person( $reviewer ) ?? array();
		$me       = array_merge(
			$me,
			array(
				'role'  => $actor->role,
				'type'  => $actor->type,
				'email' => $reviewer ? (string) $reviewer->email : '',
				'caps'  => array(
					'implement' => $actor->is_implementer(),
					'manage'    => $actor->is_manager(),
				),
			)
		);

		$page       = null;
		$normalized = '' !== $url ? Url::normalize( $url ) : null;
		if ( $normalized ) {
			$page = array(
				'key'   => Url::key( $normalized ),
				'url'   => $normalized,
				'title' => Workflow::page_title( $normalized, '' ),
			);
		}

		$link = null;
		if ( $actor->link_id ) {
			$row = Links::get( $actor->link_id );
			if ( $row ) {
				$link = array(
					'label'      => (string) $row->label,
					'expires_at' => Formatter::time( $row->expires_at ),
				);
			}
		}

		$session = $actor->is_guest() ? Sessions::current() : null;

		return array(
			'authenticated' => true,
			'me'          => $me,
			'csrf'        => $session ? Sessions::csrf_token( $session ) : null,
			'branding'    => Settings::branding(),
			'page'        => $page,
			'link'        => $link,
			'joined_at'   => Formatter::time( $actor->joined_at ),
			'server_time' => Formatter::time( gmdate( 'Y-m-d H:i:s' ) ),
			'admin_url'   => $actor->is_manager() ? admin_url( 'admin.php?page=mna-feedback' ) : null,
			'wp_login'    => $actor->is_guest() && ! is_user_logged_in() && Router::cookie_user_has_role(),
		);
	}

	/**
	 * 401 describing what the interface should show: a join form, a message
	 * about an unusable link, or that access has ended.
	 */
	private static function not_joined(): \WP_Error {
		$pending = Join::pending();
		$extra   = array();
		if ( 'valid' === $pending['state'] ) {
			$extra['join']     = array(
				'state' => 'required',
				'label' => (string) $pending['link']->label,
			);
			$extra['branding'] = Settings::branding();
		} elseif ( 'invalid' === $pending['state'] ) {
			$extra['join'] = array(
				'state'   => 'invalid',
				'reason'  => $pending['reason'],
				'message' => self::reason_message( $pending['reason'] ),
			);
		} elseif ( null !== Cookies::get( Cookies::SESSION ) ) {
			$extra['ended'] = true;
		}
		return Router::unauthorized( $extra );
	}

	private static function reason_message( string $reason ): string {
		return match ( $reason ) {
			'expired' => __( 'This review link has expired. Ask the person who shared it for a new link.', 'mna-feedback' ),
			'revoked' => __( 'This review link has been switched off. Ask the person who shared it for a new link.', 'mna-feedback' ),
			'return'  => __( 'This private return link is no longer valid. Open the shared review link again to continue.', 'mna-feedback' ),
			'blocked' => __( 'Your review access has been removed. Contact the site owner if you think this is a mistake.', 'mna-feedback' ),
			default   => __( 'This review link is not valid. Check you copied the whole address, or ask for a new link.', 'mna-feedback' ),
		};
	}
}
