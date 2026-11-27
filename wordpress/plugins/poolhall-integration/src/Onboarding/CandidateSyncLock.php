<?php
declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/** An atomic, identity-scoped lease prevents concurrent candidate creation. */
final class CandidateSyncLock {

	public static function acquire( string $identity ): ?array {
		$key = 'poolhall_giig_link_lock_' . hash( 'sha256', $identity );
		$old = get_option( $key, null );
		if ( is_array( $old ) && (int) ( $old['expires'] ?? 0 ) < time() ) {
			// Compare-and-delete: never remove a lease another request just acquired.
			global $wpdb;
			$removed = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$key, maybe_serialize( $old )
			) );
			if ( $removed ) {
				wp_cache_delete( $key, 'options' );
			}
		}
		$value = array( 'token' => wp_generate_uuid4(), 'expires' => time() + 180 );
		return add_option( $key, $value, '', false ) ? array( 'key' => $key, 'value' => $value ) : null;
	}

	public static function release( array $lease ): void {
		global $wpdb;
		$removed = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$lease['key'], maybe_serialize( $lease['value'] )
		) );
		if ( $removed ) {
			wp_cache_delete( $lease['key'], 'options' );
		}
	}
}
