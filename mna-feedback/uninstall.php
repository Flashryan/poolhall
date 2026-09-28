<?php
/**
 * Uninstall handler.
 *
 * Feedback records are kept by default, so reinstalling the plugin brings
 * everything back. They are only deleted when a manager has ticked
 * "Permanently delete all feedback ... when the plugin is deleted" under
 * Feedback > Settings - checked separately for each site on a network.
 *
 * @package MNA\Feedback
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	return;
}

defined( 'MNAFB_VERSION' ) || define( 'MNAFB_VERSION', '1.0.1' );
defined( 'MNAFB_DB_VERSION' ) || define( 'MNAFB_DB_VERSION', 1 );
defined( 'MNAFB_FILE' ) || define( 'MNAFB_FILE', __DIR__ . '/mna-feedback.php' );
defined( 'MNAFB_DIR' ) || define( 'MNAFB_DIR', __DIR__ . '/' );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'MNA\\Feedback\\';
		if ( str_starts_with( $class, $prefix ) ) {
			$path = __DIR__ . '/includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $path ) ) {
				require $path;
			}
		}
	}
);

$mnafb_sites = is_multisite() ? get_sites(
	array(
		'fields' => 'ids',
		'number' => 0,
	)
) : array( get_current_blog_id() );

foreach ( $mnafb_sites as $mnafb_site ) {
	if ( is_multisite() ) {
		switch_to_blog( (int) $mnafb_site );
	}
	$mnafb_settings = get_option( 'mnafb_settings' );
	if ( is_array( $mnafb_settings ) && ! empty( $mnafb_settings['purge_on_uninstall'] ) ) {
		MNA\Feedback\Installer::purge_site_data( false );
	} else {
		wp_clear_scheduled_hook( 'mnafb_daily' );
	}
	if ( is_multisite() ) {
		restore_current_blog();
	}
}
