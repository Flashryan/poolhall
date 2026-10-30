<?php
/**
 * Removes the plugin's settings, redirects and 404 log. Per-page SEO fields
 * (post meta beginning _phseo_) are left in place so they survive a reinstall.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'phseo_settings', 'phseo_redirects', 'phseo_redirects_enabled', 'phseo_404_log' ) as $option ) {
	delete_option( $option );
}
