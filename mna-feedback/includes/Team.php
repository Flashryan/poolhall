<?php
/**
 * Team membership: which WordPress users hold which feedback role, and
 * whether that comes from their WordPress role (administrators are always
 * managers) or a per-user grant made on the People screen.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Team {

	private const RANK = array(
		'reviewer'    => 1,
		'implementer' => 2,
		'manager'     => 3,
	);

	/**
	 * Feedback role granted by the user's WordPress role(s) alone.
	 */
	public static function role_from_wp_roles( \WP_User $user ): ?string {
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return 'manager';
		}
		$caps = array();
		foreach ( (array) $user->roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( Capabilities::ROLES['manager'] as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$caps[ $cap ] = true;
				}
			}
		}
		if ( isset( $caps[ Capabilities::MANAGE ] ) ) {
			return 'manager';
		}
		if ( isset( $caps[ Capabilities::IMPLEMENT ] ) ) {
			return 'implementer';
		}
		return isset( $caps[ Capabilities::REVIEW ] ) ? 'reviewer' : null;
	}

	/**
	 * @return array{user_id: int, name: string, email: string, login: string, role: ?string, locked_role: ?string}
	 */
	public static function member( \WP_User $user ): array {
		return array(
			'user_id'     => (int) $user->ID,
			'name'        => (string) $user->display_name,
			'email'       => (string) $user->user_email,
			'login'       => (string) $user->user_login,
			'role'        => Capabilities::role_for_user( $user ),
			'locked_role' => self::role_from_wp_roles( $user ),
		);
	}

	/**
	 * @return array<int, array>
	 */
	public static function members(): array {
		$out = array();
		foreach ( Capabilities::team_user_ids() as $id ) {
			$user = get_user_by( 'id', $id );
			if ( $user ) {
				$out[] = self::member( $user );
			}
		}
		usort( $out, static fn( $a, $b ) => ( self::RANK[ $b['role'] ] ?? 0 ) <=> ( self::RANK[ $a['role'] ] ?? 0 ) ?: strcasecmp( $a['name'], $b['name'] ) );
		return $out;
	}

	/**
	 * Users who could be added to the team.
	 *
	 * @return array<int, array>
	 */
	public static function search( string $term ): array {
		$term = trim( $term );
		if ( mb_strlen( $term ) < 2 ) {
			return array();
		}
		$users = get_users(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'number'         => 20,
				'orderby'        => 'display_name',
			)
		);
		return array_map( array( self::class, 'member' ), $users );
	}

	/**
	 * Grants a feedback role, or removes it with null.
	 */
	public static function set_role( int $user_id, ?string $role, int $acting_user_id ): bool|WP_Error {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new WP_Error( 'mnafb_no_user', __( 'That user does not exist.', 'mna-feedback' ), array( 'status' => 404 ) );
		}
		if ( null !== $role && ! isset( self::RANK[ $role ] ) ) {
			return new WP_Error( 'mnafb_bad_role', __( 'Unknown feedback role.', 'mna-feedback' ), array( 'status' => 400 ) );
		}
		$locked = self::role_from_wp_roles( $user );
		if ( $locked && ( null === $role || self::RANK[ $role ] < self::RANK[ $locked ] ) ) {
			return new WP_Error(
				'mnafb_role_locked',
				/* translators: %s: feedback role name */
				sprintf( __( 'This person\'s WordPress role already makes them a %s. Change their WordPress role to lower it.', 'mna-feedback' ), self::label( $locked ) ),
				array( 'status' => 409 )
			);
		}
		if ( $user_id === $acting_user_id && 'manager' === Capabilities::role_for_user( $user ) && 'manager' !== $role ) {
			return new WP_Error( 'mnafb_self_demote', __( 'You cannot remove your own manager access. Ask another manager.', 'mna-feedback' ), array( 'status' => 409 ) );
		}
		Capabilities::set_user_role( $user_id, $role === $locked ? null : $role );
		clean_user_cache( $user_id );
		return true;
	}

	public static function label( string $role ): string {
		return match ( $role ) {
			'manager'     => __( 'Manager', 'mna-feedback' ),
			'implementer' => __( 'Implementer', 'mna-feedback' ),
			'reviewer'    => __( 'Reviewer', 'mna-feedback' ),
			default       => __( 'No access', 'mna-feedback' ),
		};
	}
}
