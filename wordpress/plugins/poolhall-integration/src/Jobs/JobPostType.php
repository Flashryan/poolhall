<?php
/**
 * Job custom post type and taxonomies.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Registers `poolhall_job` and its taxonomies (architecture doc §3).
 * Jobs are not publicly creatable in admin by editors — they come from sync —
 * but remain visible/editable for expiry overrides and featured flags.
 */
final class JobPostType {

	public const POST_TYPE     = 'poolhall_job';
	public const TAX_SECTOR    = 'poolhall_sector';
	public const TAX_JOB_TYPE  = 'poolhall_job_type';
	public const TAX_WORK_MODE = 'poolhall_work_mode';
	public const TAX_LOCATION  = 'poolhall_location';

	public const META_KEYS = array(
		'source',
		'source_job_id',
		'source_company_id',
		'source_url',
		'source_payload_hash',
		'source_modified_at',
		'source_status',
		'job_reference',
		'salary_display',
		'salary_currency',
		'salary_min',
		'salary_max',
		'salary_period',
		'location_display',
		'address_locality',
		'address_region',
		'address_country',
		'work_mode_raw',
		'experience_requirement',
		'education_requirement',
		'date_posted',
		'expires_at',
		'expiry_override_at',
		'is_featured',
		'schema_hiring_org_name',
		'schema_hiring_org_url',
		'schema_hiring_org_logo',
		'last_synced_at',
	);

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Jobs', 'poolhall-integration' ),
					'singular_name' => __( 'Job', 'poolhall-integration' ),
				),
				'public'       => true,
				'has_archive'  => false, // The jobs archive is an Elementor page with plugin widgets.
				'rewrite'      => array(
					'slug'       => 'jobs',
					'with_front' => false,
				),
				'menu_icon'    => 'dashicons-businessman',
				'supports'     => array( 'title', 'editor', 'custom-fields' ),
				'show_in_rest' => true,
				'capabilities' => array(
					// Jobs are created by sync, not by hand.
					'create_posts' => 'manage_options',
				),
				'map_meta_cap' => true,
			)
		);

		$taxonomies = array(
			self::TAX_SECTOR    => __( 'Sectors', 'poolhall-integration' ),
			self::TAX_JOB_TYPE  => __( 'Job types', 'poolhall-integration' ),
			self::TAX_WORK_MODE => __( 'Work modes', 'poolhall-integration' ),
			self::TAX_LOCATION  => __( 'Locations', 'poolhall-integration' ),
		);

		foreach ( $taxonomies as $taxonomy => $label ) {
			register_taxonomy(
				$taxonomy,
				self::POST_TYPE,
				array(
					'label'             => $label,
					'public'            => true,
					'hierarchical'      => false,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => false,
				)
			);
		}

		foreach ( self::META_KEYS as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'single'        => true,
					'type'          => 'string',
					'show_in_rest'  => false,
					'auth_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
}
