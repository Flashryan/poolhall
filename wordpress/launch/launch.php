<?php
/**
 * Poolhall launch: move WordPress from the Hostinger temporary domain to
 * https://www.poolhallrecruitment.co.uk.
 *
 * Safe to run more than once. Dry run unless $phl_apply is true:
 *   Novamira execute-php:  $phl_apply = true; return include '<dir>/launch.php';
 *   WP-CLI:                wp eval-file launch.php apply
 *
 * Order matters: server paths are rewritten before URLs, and only URLs
 * (anything preceded by //, \/\/ or %2F%2F) have their host changed, so a
 * file path is never turned into a web address.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

const PHL_OLD_HOST = 'lightslategrey-hare-335761.hostingersite.com';
const PHL_NEW_HOST = 'www.poolhallrecruitment.co.uk';
const PHL_ROOT     = 'poolhallrecruitment.co.uk';
const PHL_OLD_DIR  = '/home/u232776807/domains/lightslategrey-hare-335761.hostingersite.com';

$phl_apply = ! empty( $phl_apply ) || ( defined( 'WP_CLI' ) && in_array( 'apply', (array) ( $args ?? array() ), true ) );

if ( ! function_exists( 'phl_replace_deep' ) ) {
	/** Replace inside a raw database value, unpacking serialized data so lengths stay right. */
	function phl_replace_deep( $data, array $pairs, bool &$skipped ) {
		if ( is_string( $data ) ) {
			if ( is_serialized( $data ) ) {
				$value = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore
				if ( false !== $value || 'b:0;' === $data ) {
					return serialize( phl_replace_deep( $value, $pairs, $skipped ) ); // phpcs:ignore
				}
				$skipped = true;
				return $data;
			}
			return strtr( $data, $pairs );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = phl_replace_deep( $value, $pairs, $skipped );
			}
			return $data;
		}
		if ( is_object( $data ) ) {
			$skipped = true; // Objects from unknown classes are left exactly as stored.
		}
		return $data;
	}
}

global $wpdb;
$report  = array( 'mode' => $phl_apply ? 'APPLY' : 'dry run' );
$new_dir = untrailingslashit( dirname( ABSPATH ) );

// 1. What to replace: paths first (only when Hostinger renamed the folder), then URLs.
$pairs = array();
if ( PHL_OLD_DIR !== $new_dir ) {
	$pairs[ PHL_OLD_DIR ]                         = $new_dir;
	$pairs[ str_replace( '/', '\/', PHL_OLD_DIR ) ] = str_replace( '/', '\/', $new_dir );
}
// Hostinger's own domain change may already have swapped the temporary host
// for http://poolhallrecruitment.co.uk, so every form ends on https://www.
$new_url = 'https://' . PHL_NEW_HOST;
foreach ( array( PHL_OLD_HOST, PHL_ROOT, PHL_NEW_HOST ) as $host ) {
	foreach ( array( 'http://', 'https://' ) as $scheme ) {
		$pairs[ $scheme . $host ]                         = $new_url;
		$pairs[ str_replace( '/', '\/', $scheme . $host ) ] = str_replace( '/', '\/', $new_url );
	}
	foreach ( array( '//', '\/\/', '%2F%2F', '%2f%2f' ) as $lead ) {
		$pairs[ $lead . $host ] = $lead . PHL_NEW_HOST;
	}
}
$report['paths'] = PHL_OLD_DIR === $new_dir ? 'folder unchanged, paths left alone' : PHL_OLD_DIR . ' -> ' . $new_dir;

// 2. Where. Option rows that are licences, caches or historical backups are left as they are.
$skip_options = "option_name NOT LIKE '\\_transient\\_%' AND option_name NOT LIKE '\\_site\\_transient\\_%'
	AND option_name NOT LIKE 'novamira%' AND option_name NOT LIKE 'nvp\\_%' AND option_name NOT LIKE 'fs\\_%'
	AND option_name NOT LIKE 'poolhall\\_tpl\\_backup\\_%' AND option_name NOT LIKE 'poolhall\\_content\\_backup\\_%'
	AND option_name NOT LIKE 'poolhall\\_elementor\\_backup\\_%' AND option_name NOT LIKE 'poolhall\\_purged\\_candidates\\_%'
	AND option_name NOT IN ('recently_edited','elementor_log','poolhall_workflow_previous_plugin_dir','poolhall_elementor_backup_latest')";
$targets = array(
	array( $wpdb->posts, 'ID', 'post_content', '' ),
	array( $wpdb->posts, 'ID', 'post_excerpt', '' ),
	array( $wpdb->postmeta, 'meta_id', 'meta_value', '' ),
	array( $wpdb->options, 'option_id', 'option_value', $skip_options ),
	array( $wpdb->usermeta, 'umeta_id', 'meta_value', '' ),
	array( $wpdb->users, 'ID', 'user_url', '' ),
	array( $wpdb->termmeta, 'meta_id', 'meta_value', '' ),
	array( $wpdb->prefix . 'gf_entry_meta', 'id', 'meta_value', '' ),
);

$undo     = array();
$like     = '%' . $wpdb->esc_like( PHL_OLD_HOST ) . '%';
$like_new = '%' . $wpdb->esc_like( PHL_ROOT ) . '%';
foreach ( $targets as list( $table, $pk, $col, $where ) ) {
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		continue;
	}
	$sql  = "SELECT `$pk` AS pk, `$col` AS val FROM `$table` WHERE (`$col` LIKE %s OR `$col` LIKE %s)" . ( $where ? " AND $where" : '' );
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $like, $like_new ) ); // phpcs:ignore
	$changed = 0;
	$skipped_rows = 0;
	foreach ( $rows as $row ) {
		$skipped = false;
		$value   = phl_replace_deep( $row->val, $pairs, $skipped );
		if ( $skipped ) {
			++$skipped_rows;
		}
		if ( $value === $row->val ) {
			continue;
		}
		++$changed;
		if ( $phl_apply ) {
			$undo[] = array( $table, $pk, $row->pk, $col, $row->val );
			$wpdb->update( $table, array( $col => $value ), array( $pk => $row->pk ) );
		}
	}
	$report['rows'][ "$table.$col" ] = array( 'match' => count( $rows ), 'change' => $changed, 'objects_left' => $skipped_rows );
}

// 3. Site settings.
$settings = array(
	'home'                                 => 'https://' . PHL_NEW_HOST,
	'siteurl'                              => 'https://' . PHL_NEW_HOST,
	'blog_public'                          => '1',
	// Google for Jobs: lifts the staging noindex on vacancies. Its own JobPosting
	// markup stays off: in "confidential" mode it names the employer
	// "confidential" and duplicates Poolhall Integration's complete markup.
	'poolhall_google_jobs_environment'     => 'production',
	'poolhall_google_jobs_production_host' => PHL_NEW_HOST,
	'poolhall_google_jobs_schema_enabled'  => '0',
);
foreach ( $settings as $name => $value ) {
	$report['settings'][ $name ] = get_option( $name ) . ' -> ' . $value;
	if ( $phl_apply ) {
		$undo[] = array( $wpdb->options, 'option_name', $name, 'option_value', get_option( $name ) );
		update_option( $name, $value );
	}
}

// 4. Plugins: the review tool is not for the public site.
$report['deactivate'] = is_plugin_active( 'mna-feedback/mna-feedback.php' ) ? 'mna-feedback' : 'already off';

// 5. The temporary domain sends visitors and search engines to the live address.
$mu   = WPMU_PLUGIN_DIR . '/poolhall-old-host.php';
$code = "<?php\n/**\n * Plugin Name: Poolhall old host redirect\n * Description: Sends the Hostinger temporary domain to the live site with a 301.\n */\n"
	. "if ( isset( \$_SERVER['HTTP_HOST'] ) && '" . PHL_OLD_HOST . "' === strtolower( \$_SERVER['HTTP_HOST'] )\n"
	. "\t&& 'cli' !== PHP_SAPI && 0 !== strpos( (string) ( \$_SERVER['REQUEST_URI'] ?? '' ), '/wp-json/' ) ) {\n"
	. "\theader( 'Location: https://" . PHL_NEW_HOST . "' . ( \$_SERVER['REQUEST_URI'] ?? '/' ), true, 301 );\n\texit;\n}\n";
$report['old_host_redirect'] = file_exists( $mu ) ? 'present' : 'will add ' . $mu;

if ( $phl_apply ) {
	$dir = $new_dir . '/poolhall-deploy-backups/' . gmdate( 'Ymd-His' ) . '-launch/';
	wp_mkdir_p( $dir );
	file_put_contents( $dir . 'undo.json', wp_json_encode( $undo ) );
	$report['undo_file'] = $dir . 'undo.json';

	deactivate_plugins( 'mna-feedback/mna-feedback.php' );
	if ( ! file_exists( $mu ) ) {
		file_put_contents( $mu, $code );
	}
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	flush_rewrite_rules( false );
	wp_cache_flush();
}

// 6. Check what is left.
$left = array();
foreach ( $targets as list( $table, $pk, $col, $where ) ) {
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		continue;
	}
	foreach ( array( PHL_OLD_HOST, '//' . PHL_ROOT, '\/\/' . PHL_ROOT, 'http://www.' . PHL_ROOT, 'http:\/\/www.' . PHL_ROOT ) as $needle ) {
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE `$col` LIKE %s" . ( $where ? " AND $where" : '' ), '%' . $wpdb->esc_like( $needle ) . '%' ) ); // phpcs:ignore
		if ( $n ) {
			$left[ "$table.$col" ][ $needle ] = $n;
		}
	}
}
$report['old_urls_left'] = $left ?: 'none';
if ( class_exists( 'Poolhall\GoogleJobs\Config' ) ) {
	$report['jobs_hidden_from_google'] = ( new \Poolhall\GoogleJobs\Config() )->staging_protection_active();
}

if ( defined( 'WP_CLI' ) ) {
	WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
}
return $report;
