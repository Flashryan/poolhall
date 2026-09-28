<?php
/**
 * Activation, upgrades and per-site setup.
 *
 * Records survive updates, deactivation and uninstall. Only the explicit
 * purge action (or the opt-in "purge on uninstall" setting) removes them.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}
		self::install();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'mnafb_daily' );
	}

	/**
	 * Creates or upgrades this site's tables, capabilities and storage.
	 */
	public static function install(): void {
		Schema::create_tables();
		Capabilities::grant_defaults();
		Settings::ensure_defaults();
		Data\Attachments::ensure_storage();
		update_option( 'mnafb_db_version', MNAFB_DB_VERSION, false );

		if ( ! wp_next_scheduled( 'mnafb_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mnafb_daily' );
		}
	}

	/**
	 * Runs the migration when the stored schema version is older than the code.
	 * dbDelta is idempotent, so re-running a step is safe.
	 */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( 'mnafb_db_version', 0 ) < MNAFB_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * New sites on a network where the plugin is network-active get their own tables.
	 */
	public static function on_new_site( \WP_Site $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( MNAFB_FILE ) ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		self::install();
		restore_current_blog();
	}

	/**
	 * Permanently removes every record, file and setting for this site.
	 * Used by the manager's purge action and by opt-in uninstall.
	 */
	public static function purge_site_data( bool $keep_plugin_running = true ): void {
		global $wpdb;
		foreach ( Schema::TABLES as $name ) {
			$table = Schema::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		Data\Attachments::delete_storage();
		delete_option( 'mnafb_db_version' );
		delete_option( 'mnafb_storage_dir' );

		if ( $keep_plugin_running ) {
			self::install();
			return;
		}

		delete_option( Settings::OPTION );
		Capabilities::remove_all();
		wp_clear_scheduled_hook( 'mnafb_daily' );
	}
}
