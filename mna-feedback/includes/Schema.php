<?php
/**
 * Versioned database schema. Tables use the site's prefix, so each site on a
 * multisite network keeps separate records.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const TABLES = array( 'items', 'replies', 'activity', 'reviewers', 'links', 'sessions', 'attachments', 'reads' );

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'mnafb_' . $name;
	}

	public static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::statements() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * dbDelta-formatted CREATE TABLE statements (two spaces after PRIMARY KEY,
	 * one column per line).
	 *
	 * @return string[]
	 */
	private static function statements(): array {
		global $wpdb;
		$c = $wpdb->get_charset_collate();

		$items       = self::table( 'items' );
		$replies     = self::table( 'replies' );
		$activity    = self::table( 'activity' );
		$reviewers   = self::table( 'reviewers' );
		$links       = self::table( 'links' );
		$sessions    = self::table( 'sessions' );
		$attachments = self::table( 'attachments' );
		$reads       = self::table( 'reads' );

		return array(
			"CREATE TABLE {$items} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  title varchar(200) NOT NULL,
  body longtext NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'open',
  priority varchar(10) NOT NULL DEFAULT 'normal',
  author_id bigint(20) unsigned NOT NULL DEFAULT 0,
  assignee_id bigint(20) unsigned NOT NULL DEFAULT 0,
  page_url text NOT NULL,
  page_key char(64) NOT NULL,
  page_title varchar(255) NOT NULL DEFAULT '',
  pin_type varchar(10) NOT NULL DEFAULT 'page',
  pin_x decimal(8,5) NOT NULL DEFAULT 0,
  pin_y decimal(8,5) NOT NULL DEFAULT 0,
  anchor longtext NULL,
  context longtext NULL,
  viewport_w smallint(5) unsigned NOT NULL DEFAULT 0,
  viewport_h smallint(5) unsigned NOT NULL DEFAULT 0,
  device_type varchar(10) NOT NULL DEFAULT '',
  device longtext NULL,
  board_order double NOT NULL DEFAULT 0,
  revision int(10) unsigned NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  edited_at datetime NULL DEFAULT NULL,
  status_changed_at datetime NULL DEFAULT NULL,
  last_activity_at datetime NOT NULL,
  last_activity_by bigint(20) unsigned NOT NULL DEFAULT 0,
  activity_rev int(10) unsigned NOT NULL DEFAULT 1,
  deleted_at datetime NULL DEFAULT NULL,
  deleted_by bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY status (status),
  KEY page_key (page_key),
  KEY author_id (author_id),
  KEY assignee_id (assignee_id),
  KEY device_type (device_type),
  KEY updated_at (updated_at),
  KEY deleted_at (deleted_at)
) {$c};",

			"CREATE TABLE {$replies} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  item_id bigint(20) unsigned NOT NULL,
  author_id bigint(20) unsigned NOT NULL DEFAULT 0,
  kind varchar(12) NOT NULL DEFAULT 'reply',
  body longtext NOT NULL,
  device longtext NULL,
  revision int(10) unsigned NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  edited_at datetime NULL DEFAULT NULL,
  deleted_at datetime NULL DEFAULT NULL,
  deleted_by bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY item_id (item_id),
  KEY author_id (author_id)
) {$c};",

			"CREATE TABLE {$activity} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  item_id bigint(20) unsigned NOT NULL DEFAULT 0,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source varchar(12) NOT NULL DEFAULT 'ui',
  action varchar(40) NOT NULL,
  data longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY item_id (item_id),
  KEY action (action),
  KEY created_at (created_at)
) {$c};",

			"CREATE TABLE {$reviewers} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  type varchar(10) NOT NULL DEFAULT 'guest',
  wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  display_name varchar(80) NOT NULL,
  email varchar(190) NOT NULL DEFAULT '',
  link_id bigint(20) unsigned NOT NULL DEFAULT 0,
  return_hash char(64) NOT NULL DEFAULT '',
  return_enc text NULL,
  color char(7) NOT NULL DEFAULT '',
  status varchar(10) NOT NULL DEFAULT 'active',
  created_at datetime NOT NULL,
  last_seen_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY wp_user_id (wp_user_id),
  KEY return_hash (return_hash),
  KEY email (email)
) {$c};",

			"CREATE TABLE {$links} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  label varchar(120) NOT NULL DEFAULT '',
  token_hash char(64) NOT NULL,
  token_enc text NULL,
  landing_url text NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  expires_at datetime NULL DEFAULT NULL,
  revoked_at datetime NULL DEFAULT NULL,
  rotated_at datetime NULL DEFAULT NULL,
  last_used_at datetime NULL DEFAULT NULL,
  use_count int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  UNIQUE KEY token_hash (token_hash)
) {$c};",

			"CREATE TABLE {$sessions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token_hash char(64) NOT NULL,
  reviewer_id bigint(20) unsigned NOT NULL,
  link_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_agent varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  last_seen_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  revoked_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token_hash (token_hash),
  KEY reviewer_id (reviewer_id),
  KEY link_id (link_id)
) {$c};",

			"CREATE TABLE {$attachments} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  item_id bigint(20) unsigned NOT NULL,
  reply_id bigint(20) unsigned NOT NULL DEFAULT 0,
  uploader_id bigint(20) unsigned NOT NULL DEFAULT 0,
  file varchar(255) NOT NULL,
  thumb varchar(255) NOT NULL DEFAULT '',
  mime varchar(40) NOT NULL,
  size int(10) unsigned NOT NULL DEFAULT 0,
  width int(10) unsigned NOT NULL DEFAULT 0,
  height int(10) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY item_id (item_id)
) {$c};",

			"CREATE TABLE {$reads} (
  reviewer_id bigint(20) unsigned NOT NULL,
  item_id bigint(20) unsigned NOT NULL,
  read_at datetime NOT NULL,
  read_rev int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (reviewer_id,item_id),
  KEY item_id (item_id)
) {$c};",
		);
	}
}
