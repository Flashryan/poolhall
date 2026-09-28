<?php
/**
 * Wires the plugin together.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Installer::maybe_upgrade();

		Rest\Router::init();
		add_action( 'mnafb_daily', array( Access\Sessions::class, 'cleanup' ) );
		add_action( 'wp_initialize_site', array( Installer::class, 'on_new_site' ), 20 );

		Frontend::init();
		Privacy::init();
		Abilities::init();

		if ( is_admin() ) {
			Admin\Admin::init();
		}
	}
}
