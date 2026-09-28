<?php
/**
 * Feedback roles expressed as WordPress capabilities.
 *
 * Reviewer    - view the shared review space, create feedback, reply, edit or
 *               delete their own contributions, reopen finished items.
 * Implementer - everything a reviewer can do, plus triage, assign and move
 *               cards through every status.
 * Manager     - everything above, plus access links, team permissions,
 *               settings, exports and Trash.
 *
 * Guests who arrive through a shared link are always reviewers.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	public const REVIEW    = 'mnafb_review';
	public const IMPLEMENT = 'mnafb_implement';
	public const MANAGE    = 'mnafb_manage';

	public const ROLES = array(
		'reviewer'    => array( self::REVIEW ),
		'implementer' => array( self::REVIEW, self::IMPLEMENT ),
		'manager'     => array( self::REVIEW, self::IMPLEMENT, self::MANAGE ),
	);

	public static function grant_defaults(): void {
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( self::ROLES['manager'] as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	public static function remove_all(): void {
		$caps = self::ROLES['manager'];
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
		foreach ( self::team_user_ids() as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				foreach ( $caps as $cap ) {
					$user->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * The feedback role a WordPress user holds, or null.
	 */
	public static function role_for_user( \WP_User $user ): ?string {
		if ( ! $user->exists() ) {
			return null;
		}
		if ( user_can( $user, self::MANAGE ) ) {
			return 'manager';
		}
		if ( user_can( $user, self::IMPLEMENT ) ) {
			return 'implementer';
		}
		if ( user_can( $user, self::REVIEW ) ) {
			return 'reviewer';
		}
		return null;
	}

	/**
	 * Grants a user a feedback role (or removes it when $role is null) using
	 * per-user capabilities, so it never alters the site's WordPress roles.
	 */
	public static function set_user_role( int $user_id, ?string $role ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}
		foreach ( self::ROLES['manager'] as $cap ) {
			$user->remove_cap( $cap );
		}
		if ( null !== $role && isset( self::ROLES[ $role ] ) ) {
			foreach ( self::ROLES[ $role ] as $cap ) {
				$user->add_cap( $cap );
			}
		}
		return true;
	}

	/**
	 * Users holding any feedback capability, whether through their WordPress
	 * role or a per-user grant.
	 *
	 * @return int[]
	 */
	public static function team_user_ids(): array {
		$ids = get_users(
			array(
				'capability__in' => self::ROLES['manager'],
				'fields'         => 'ID',
				'number'         => 500,
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * WordPress users who can be assigned work.
	 *
	 * @return \WP_User[]
	 */
	public static function assignable_users(): array {
		return get_users(
			array(
				'capability' => self::IMPLEMENT,
				'number'     => 500,
				'orderby'    => 'display_name',
			)
		);
	}
}
