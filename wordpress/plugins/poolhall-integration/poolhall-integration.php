<?php
/**
 * Plugin Name: Poolhall Integration
 * Plugin URI: https://www.poolhallrecruitment.co.uk/
 * Description: Jobs, Giig ATS sync, applications, Google reviews, JobPosting schema, admin health and candidate accounts for Poolhall Recruitment.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: Poolhall Recruitment
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: poolhall-integration
 *
 * @package Poolhall\Integration
 */

defined( 'ABSPATH' ) || exit;

define( 'POOLHALL_INTEGRATION_VERSION', '0.1.0' );
define( 'POOLHALL_INTEGRATION_FILE', __FILE__ );
define( 'POOLHALL_INTEGRATION_DIR', plugin_dir_path( __FILE__ ) );
define( 'POOLHALL_INTEGRATION_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader (dev) with a PSR-4 fallback for hosts deployed without
// the vendor directory — the plugin has no production Composer dependencies.
$poolhall_autoload = POOLHALL_INTEGRATION_DIR . 'vendor/autoload.php';
if ( file_exists( $poolhall_autoload ) ) {
	require $poolhall_autoload;
} else {
	spl_autoload_register(
		function ( string $class_name ): void {
			$prefix = 'Poolhall\\Integration\\';
			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}
			$path = POOLHALL_INTEGRATION_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( file_exists( $path ) ) {
				require $path;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \Poolhall\Integration\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Poolhall\Integration\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \Poolhall\Integration\Plugin::class, 'boot' ) );
