<?php
/**
 * Plugin Name:       MNA Feedback
 * Plugin URI:        https://mnadigital.co.uk/
 * Description:       A review overlay for stakeholders: pin comments to any part of a page, discuss them, and track the work on a Trello-style board. Records stay in this site's database.
 * Version:           1.0.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            MNA Digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mna-feedback
 *
 * @package MNA\Feedback
 */

defined( 'ABSPATH' ) || exit;

define( 'MNAFB_VERSION', '1.0.1' );
define( 'MNAFB_DB_VERSION', 1 );
define( 'MNAFB_FILE', __FILE__ );
define( 'MNAFB_DIR', plugin_dir_path( __FILE__ ) );
define( 'MNAFB_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'MNA Feedback needs PHP 8.1 or newer. The plugin is installed but inactive until PHP is upgraded.', 'mna-feedback' );
			echo '</p></div>';
		}
	);
	return;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'MNA\\Feedback\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = MNAFB_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

register_activation_hook( __FILE__, array( 'MNA\\Feedback\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MNA\\Feedback\\Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'MNA\\Feedback\\Plugin', 'boot' ) );
