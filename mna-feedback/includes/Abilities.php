<?php
/**
 * Optional Novamira / WordPress Abilities API integration.
 *
 * A small, narrowly scoped set of abilities lets an AI agent read feedback,
 * record implementation notes, assign items and move them between columns.
 * They go through the same Workflow and Policy code as the review interface,
 * act as the signed-in WordPress user, and every change is recorded in the
 * item's history with the source "agent". None of them touch site content.
 *
 * Off by default; managers switch it on under Feedback > Settings.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

use MNA\Feedback\Access\Actor;
use MNA\Feedback\Access\Auth;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Reviewers;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Abilities {

	public const CATEGORY = 'mna-feedback';

	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register' ) );
	}

	public static function available(): bool {
		return function_exists( 'wp_register_ability' ) && function_exists( 'wp_register_ability_category' );
	}

	public static function enabled(): bool {
		return self::available() && (bool) Settings::get( 'abilities_enabled' );
	}

	public static function register_category(): void {
		if ( ! self::enabled() ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Site feedback', 'mna-feedback' ),
				'description' => __( 'Read stakeholder feedback left on this site and record implementation progress.', 'mna-feedback' ),
			)
		);
	}

	public static function register(): void {
		if ( ! self::enabled() ) {
			return;
		}

		$item_summary = array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'status'      => array( 'type' => 'string' ),
				'priority'    => array( 'type' => 'string' ),
				'page_url'    => array( 'type' => 'string' ),
				'page_title'  => array( 'type' => 'string' ),
				'author'      => array( 'type' => 'string' ),
				'assignee'    => array( 'type' => array( 'object', 'null' ) ),
				'element'     => array( 'type' => array( 'object', 'null' ) ),
				'device'      => array(
					'type'        => array( 'object', 'null' ),
					'description' => 'Device the comment was left on: type (phone, tablet, desktop), a summary such as "iPhone · Safari 18", and details including the operating system and screen size.',
				),
				'replies'     => array( 'type' => 'integer' ),
				'created_at'  => array( 'type' => 'string' ),
				'updated_at'  => array( 'type' => 'string' ),
			),
		);

		self::ability(
			'list-items',
			array(
				'label'         => __( 'List feedback', 'mna-feedback' ),
				'description'   => __( 'Lists feedback items left by reviewers on this site, newest first on each board column. Filter by status (open, in_progress, done), priority (low, normal, high, urgent), device the comment was left on (phone, tablet, desktop), page URL, assignee or a search term. Returns summaries; use get-item for the full discussion and history.', 'mna-feedback' ),
				'input_schema'  => array(
					'type'                 => 'object',
					'properties'           => array(
						'status'   => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
								'enum' => Policy::STATUSES,
							),
						),
						'priority' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
								'enum' => Policy::PRIORITIES,
							),
						),
						'device'   => array(
							'type'        => 'array',
							'description' => 'Only items left on these kinds of device.',
							'items'       => array(
								'type' => 'string',
								'enum' => Device::TYPES,
							),
						),
						'page_url' => array(
							'type'        => 'string',
							'description' => 'Only items left on this page of the site.',
						),
						'assignee' => array(
							'type'        => 'string',
							'description' => 'A team member id from list-team, "me", or "none" for unassigned items.',
						),
						'search'   => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'limit'    => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 200,
							'default' => 50,
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'total' => array( 'type' => 'integer' ),
						'items' => array(
							'type'  => 'array',
							'items' => $item_summary,
						),
					),
				),
				'readonly'      => true,
				'execute'       => array( self::class, 'list_items' ),
				'permission'    => Capabilities::REVIEW,
			)
		);

		self::ability(
			'get-item',
			array(
				'label'         => __( 'Get feedback item', 'mna-feedback' ),
				'description'   => __( 'Returns one feedback item with its full description, the page and element it refers to, every reply and implementation note, screenshot details and the complete change history.', 'mna-feedback' ),
				'input_schema'  => self::id_schema(),
				'output_schema' => array( 'type' => 'object' ),
				'readonly'      => true,
				'execute'       => array( self::class, 'get_item' ),
				'permission'    => Capabilities::REVIEW,
			)
		);

		self::ability(
			'list-team',
			array(
				'label'         => __( 'List feedback team', 'mna-feedback' ),
				'description'   => __( 'Lists the team members who can be assigned feedback, with the ids to use when assigning.', 'mna-feedback' ),
				'input_schema'  => array(
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'   => array( 'type' => 'integer' ),
							'name' => array( 'type' => 'string' ),
							'role' => array( 'type' => 'string' ),
						),
					),
				),
				'readonly'      => true,
				'execute'       => array( self::class, 'list_team' ),
				'permission'    => Capabilities::REVIEW,
			)
		);

		self::ability(
			'add-note',
			array(
				'label'         => __( 'Add implementation note', 'mna-feedback' ),
				'description'   => __( 'Adds an implementation note to a feedback item, for example what was changed and where. Notes are visible to reviewers in the item\'s discussion and are recorded in its history as agent activity. This does not change the website.', 'mna-feedback' ),
				'input_schema'  => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'   => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'note' => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 10000,
						),
					),
					'required'             => array( 'id', 'note' ),
					'additionalProperties' => false,
				),
				'output_schema' => array( 'type' => 'object' ),
				'readonly'      => false,
				'idempotent'    => false,
				'execute'       => array( self::class, 'add_note' ),
				'permission'    => Capabilities::IMPLEMENT,
			)
		);

		self::ability(
			'update-item',
			array(
				'label'         => __( 'Update feedback item', 'mna-feedback' ),
				'description'   => __( 'Moves a feedback item to another column (open, in_progress, done), changes its priority, or assigns it to a team member (assignee_id from list-team, 0 to unassign). Changes are recorded in the item\'s history as agent activity. This does not change the website.', 'mna-feedback' ),
				'input_schema'  => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'          => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'status'      => array(
							'type' => 'string',
							'enum' => Policy::STATUSES,
						),
						'priority'    => array(
							'type' => 'string',
							'enum' => Policy::PRIORITIES,
						),
						'assignee_id' => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'output_schema' => $item_summary,
				'readonly'      => false,
				'idempotent'    => true,
				'execute'       => array( self::class, 'update_item' ),
				'permission'    => Capabilities::IMPLEMENT,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Callbacks
	 * ------------------------------------------------------------------ */

	public static function list_items( mixed $input = null ): array|WP_Error {
		$actor = self::actor();
		if ( ! $actor ) {
			return self::no_access();
		}
		$input = is_array( $input ) ? $input : array();
		$args  = array(
			'order' => 'board',
			'limit' => max( 1, min( 200, (int) ( $input['limit'] ?? 50 ) ) ),
		);
		if ( ! empty( $input['status'] ) ) {
			$args['status'] = array_values( array_intersect( (array) $input['status'], Policy::STATUSES ) ) ?: array( '__none__' );
		}
		if ( ! empty( $input['priority'] ) ) {
			$args['priority'] = array_values( array_intersect( (array) $input['priority'], Policy::PRIORITIES ) ) ?: array( '__none__' );
		}
		if ( ! empty( $input['device'] ) ) {
			$args['device'] = array_values( array_intersect( (array) $input['device'], Device::TYPES ) ) ?: array( '__none__' );
		}
		if ( ! empty( $input['page_url'] ) ) {
			$normalized       = Url::normalize( (string) $input['page_url'] );
			$args['page_key'] = $normalized ? Url::key( $normalized ) : str_repeat( '0', 64 );
		}
		if ( isset( $input['assignee'] ) && '' !== (string) $input['assignee'] ) {
			$assignee         = (string) $input['assignee'];
			$args['assignee'] = 'me' === $assignee ? $actor->reviewer_id : ( 'none' === $assignee ? 'none' : absint( $assignee ) );
		}
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = (string) $input['search'];
		}
		$rows = Items::query( $args );
		return array(
			'total' => Items::count( $args ),
			'items' => array_map( array( self::class, 'summary' ), $rows ),
		);
	}

	public static function get_item( mixed $input = null ): array|WP_Error {
		$actor = self::actor();
		if ( ! $actor ) {
			return self::no_access();
		}
		$item = Items::get( (int) ( is_array( $input ) ? ( $input['id'] ?? 0 ) : 0 ) );
		if ( ! $item || ! Policy::can_view_item( $actor, $item ) ) {
			return new WP_Error( 'mnafb_not_found', __( 'That feedback item does not exist.', 'mna-feedback' ) );
		}
		$detail = Formatter::detail( $item, $actor );
		unset( $detail['can'], $detail['unread'], $detail['order'] );
		foreach ( $detail['attachments'] as &$attachment ) {
			unset( $attachment['url'], $attachment['thumb_url'] );
		}
		unset( $attachment );
		$brief            = static fn( $device ) => is_array( $device ) ? array_intersect_key( $device, array_flip( array( 'type', 'summary', 'details', 'estimated' ) ) ) : null;
		$detail['device'] = $brief( $detail['device'] ?? null );
		foreach ( $detail['replies'] as &$reply ) {
			unset( $reply['can'] );
			$reply['device'] = $brief( $reply['device'] ?? null );
			foreach ( $reply['attachments'] as &$attachment ) {
				unset( $attachment['url'], $attachment['thumb_url'] );
			}
			unset( $attachment );
		}
		unset( $reply );
		$detail['element'] = self::element( $item );
		return $detail;
	}

	public static function list_team(): array|WP_Error {
		if ( ! self::actor() ) {
			return self::no_access();
		}
		$out = array();
		foreach ( Capabilities::assignable_users() as $user ) {
			$reviewer = Reviewers::for_wp_user( $user );
			if ( $reviewer && 'active' === $reviewer->status ) {
				$out[] = array(
					'id'   => (int) $reviewer->id,
					'name' => (string) $reviewer->display_name,
					'role' => (string) Capabilities::role_for_user( $user ),
				);
			}
		}
		return $out;
	}

	public static function add_note( mixed $input = null ): array|WP_Error {
		$actor = self::actor();
		if ( ! $actor ) {
			return self::no_access();
		}
		$input = is_array( $input ) ? $input : array();
		$reply = Workflow::add_reply( $actor, (int) ( $input['id'] ?? 0 ), (string) ( $input['note'] ?? '' ), 'note' );
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}
		$out = Formatter::reply( $reply, $actor );
		unset( $out['can'] );
		return $out;
	}

	public static function update_item( mixed $input = null ): array|WP_Error {
		$actor = self::actor();
		if ( ! $actor ) {
			return self::no_access();
		}
		$input   = is_array( $input ) ? $input : array();
		$changes = array_intersect_key( $input, array_flip( array( 'status', 'priority', 'assignee_id' ) ) );
		if ( ! $changes ) {
			return new WP_Error( 'mnafb_nothing_to_change', __( 'Provide a status, priority or assignee_id to change.', 'mna-feedback' ) );
		}
		$item = Workflow::update_item( $actor, (int) ( $input['id'] ?? 0 ), $changes );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		return self::summary( $item );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * @param array{label: string, description: string, input_schema: array, output_schema: array, readonly: bool, idempotent?: bool, execute: callable, permission: string} $spec
	 */
	private static function ability( string $slug, array $spec ): void {
		$capability = $spec['permission'];
		wp_register_ability(
			self::CATEGORY . '/' . $slug,
			array(
				'label'               => $spec['label'],
				'description'         => $spec['description'],
				'category'            => self::CATEGORY,
				'input_schema'        => $spec['input_schema'],
				'output_schema'       => $spec['output_schema'],
				'execute_callback'    => $spec['execute'],
				'permission_callback' => static function () use ( $capability ) {
					return self::enabled() && current_user_can( $capability ) && null !== self::actor();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => $spec['readonly'],
						'destructive' => false,
						'idempotent'  => $spec['idempotent'] ?? $spec['readonly'],
					),
					'public'       => true,
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
				),
			)
		);
	}

	private static function actor(): ?Actor {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return null;
		}
		$actor = Auth::for_user( $user, 'agent' );
		return $actor;
	}

	private static function no_access(): WP_Error {
		return new WP_Error( 'mnafb_forbidden', __( 'This account does not have access to site feedback.', 'mna-feedback' ) );
	}

	private static function id_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}

	private static function summary( object $row ): array {
		$author   = Reviewers::get( (int) $row->author_id );
		$assignee = Reviewers::get( (int) $row->assignee_id );
		return array(
			'id'          => (int) $row->id,
			'title'       => (string) $row->title,
			'description' => (string) $row->body,
			'status'      => (string) $row->status,
			'priority'    => (string) $row->priority,
			'page_url'    => (string) $row->page_url,
			'page_title'  => (string) $row->page_title,
			'author'      => $author ? (string) $author->display_name : '',
			'assignee'    => $assignee ? array(
				'id'   => (int) $assignee->id,
				'name' => (string) $assignee->display_name,
			) : null,
			'element'     => self::element( $row ),
			'device'      => self::device( $row->device ?? null ),
			'replies'     => (int) ( Items::reply_counts( array( (int) $row->id ) )[ (int) $row->id ] ?? 0 ),
			'created_at'  => (string) Formatter::time( $row->created_at ),
			'updated_at'  => (string) Formatter::time( $row->updated_at ),
		);
	}

	/**
	 * The device summary an agent needs to reproduce the issue (without the
	 * raw browser string).
	 */
	private static function device( ?string $json ): ?array {
		$device = Device::for_output( $json );
		if ( ! $device ) {
			return null;
		}
		return array(
			'type'      => $device['type'],
			'summary'   => $device['summary'],
			'details'   => $device['details'],
			'estimated' => (bool) $device['estimated'],
		);
	}

	/**
	 * What the pin points at, in terms an agent can use to find it in the
	 * page or the Elementor document.
	 */
	private static function element( object $row ): ?array {
		if ( 'element' !== $row->pin_type || ! $row->anchor ) {
			return null;
		}
		$anchor = json_decode( (string) $row->anchor, true );
		if ( ! is_array( $anchor ) ) {
			return null;
		}
		return array_filter(
			array(
				'elementor_id' => $anchor['elementor']['id'] ?? null,
				'html_id'      => $anchor['htmlId']['id'] ?? null,
				'selector'     => $anchor['css'] ?? null,
				'tag'          => $anchor['tag'] ?? null,
				'text'         => isset( $anchor['text'] ) ? mb_substr( (string) $anchor['text'], 0, 200 ) : null,
				'viewport'     => array(
					'width'  => (int) $row->viewport_w,
					'height' => (int) $row->viewport_h,
				),
			),
			static fn( $value ) => null !== $value && '' !== $value
		);
	}
}
