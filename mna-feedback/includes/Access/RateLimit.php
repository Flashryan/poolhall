<?php
/**
 * Simple fixed-window write throttle per identity, so a leaked review link
 * cannot be used to flood the board.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Access;

defined( 'ABSPATH' ) || exit;

final class RateLimit {

	public const WINDOW = 600;
	public const WRITES = 150;

	public static function allow( Actor $actor, string $bucket = 'write' ): bool {
		if ( $actor->is_implementer() ) {
			return true;
		}
		$key   = 'mnafb_rl_' . $bucket . '_' . $actor->reviewer_id;
		$count = (int) get_transient( $key );
		if ( $count >= self::WRITES ) {
			return false;
		}
		set_transient( $key, $count + 1, self::WINDOW );
		return true;
	}
}
