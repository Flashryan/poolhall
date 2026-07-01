<?php
/**
 * Candidate portal pages (portal spec §2). Run inside WordPress:
 *
 *   wp eval-file scripts/dev/create-portal-pages.php
 *
 * Thin wp-cli wrapper around Accounts\PortalPages::ensure() — the same
 * code path the admin Site-setup action runs on hosts without shell
 * access. Idempotent; page IDs land in `poolhall_portal_page_ids`.
 *
 * No strict_types declaration: wp eval-file runs this through eval(), where
 * declare() cannot be the first statement.
 *
 * @package Poolhall\Integration
 */

use Poolhall\Integration\Accounts\PortalPages;

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/create-portal-pages.php\n";
	exit( 1 );
}

try {
	$poolhall_page_ids = PortalPages::ensure();
} catch ( RuntimeException $e ) {
	printf( "FAIL: %s\n", $e->getMessage() );
	exit( 1 );
}

foreach ( $poolhall_page_ids as $slug => $page_id ) {
	printf( "  /%s/ -> page %d\n", PortalPages::path( $slug ), $page_id );
}
printf( "OK: %d portal pages in place.\n", count( $poolhall_page_ids ) );
