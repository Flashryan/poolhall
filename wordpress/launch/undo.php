<?php
/**
 * Poolhall launch rollback: puts back every value launch.php changed.
 *
 *   Novamira execute-php:  $phl_undo_file = '<dir>/undo.json'; return include '<dir>/undo.php';
 *   WP-CLI:                wp eval-file undo.php <path-to-undo.json>
 *
 * Then point DNS back at Wix (root A 185.230.63.107 / .171 / .186,
 * www CNAME cdn3.wixdns.net).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;
$file = $phl_undo_file ?? ( $args[0] ?? '' );
$rows = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : null;
if ( ! is_array( $rows ) ) {
	return 'No undo file at ' . $file;
}

foreach ( array_reverse( $rows ) as list( $table, $pk, $id, $col, $value ) ) {
	$wpdb->update( $table, array( $col => $value ), array( $pk => $id ) );
}
wp_cache_flush();

$mu = WPMU_PLUGIN_DIR . '/poolhall-old-host.php';
if ( file_exists( $mu ) ) {
	unlink( $mu );
}
activate_plugin( 'mna-feedback/mna-feedback.php' );
if ( class_exists( '\Elementor\Plugin' ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
flush_rewrite_rules( false );

return array( 'restored' => count( $rows ), 'home' => get_option( 'home' ) );
