<?php
/**
 * Plugin Name:       Poolhall SEO
 * Description:       Page titles, meta descriptions, social sharing tags, structured data, sitemap tidy-up, redirects and a 404 log. Self-contained: it makes no outside connections.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Poolhall Recruitment
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       poolhall-seo
 */

defined( 'ABSPATH' ) || exit;

define( 'PHSEO_VERSION', '1.0.0' );
define( 'PHSEO_FILE', __FILE__ );
define( 'PHSEO_DIR', __DIR__ );

require_once PHSEO_DIR . '/includes/settings.php';
require_once PHSEO_DIR . '/includes/meta.php';
require_once PHSEO_DIR . '/includes/head.php';
require_once PHSEO_DIR . '/includes/schema.php';
require_once PHSEO_DIR . '/includes/sitemap.php';
require_once PHSEO_DIR . '/includes/redirects.php';
require_once PHSEO_DIR . '/includes/not-found.php';
require_once PHSEO_DIR . '/includes/admin.php';
