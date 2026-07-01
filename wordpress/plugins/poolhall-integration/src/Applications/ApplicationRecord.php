<?php
/**
 * Private application record store.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

/**
 * The `poolhall_application` custom post type: a private, admin-only,
 * never-public record of every submitted application. It is the
 * "never silently lose an application" backstop (hard rule 1) — written
 * before the notification email is attempted, so Poolhall can always
 * recover an application from wp-admin even if email delivery fails.
 *
 * Not public, excluded from search and feeds, no front-end route. Contains
 * candidate PII, so it is never exposed to visitors or indexes
 * (hard rules 4/16); on staging it must only ever hold test data.
 */
final class ApplicationRecord {

	public const POST_TYPE = 'poolhall_application';

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Applications', 'poolhall-integration' ),
					'singular_name' => __( 'Application', 'poolhall-integration' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'poolhall-jobs',
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'menu_icon'           => 'dashicons-media-text',
			)
		);
	}

	/**
	 * Persist an application before the email is attempted. Returns the
	 * record id, or 0 on failure.
	 *
	 * @param array<string,scalar> $meta Flat metadata to store on the record.
	 */
	public static function store( string $title, array $meta ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $id ) || 0 === $id ) {
			return 0;
		}
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return (int) $id;
	}

	public static function update_meta( int $id, string $key, string $value ): void {
		if ( $id > 0 ) {
			update_post_meta( $id, $key, $value );
		}
	}
}
