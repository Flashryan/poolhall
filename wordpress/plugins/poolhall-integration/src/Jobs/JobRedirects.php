<?php
/**
 * Redirects for jobs that left the feed.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Directive §12: a job removed from the source is unpublished, and its URL
 * must not dead-end. Requests for a job that exists but is no longer
 * published 301 to the jobs archive, so shared links and stale search
 * results land somewhere useful. Locally-expired jobs stay published with
 * the expired state (§7.3) and never hit this path.
 */
final class JobRedirects {

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
	}

	public function maybe_redirect(): void {
		if ( ! is_404() ) {
			return;
		}

		$job_slug = get_query_var( JobPostType::POST_TYPE );
		$job_slug = is_string( $job_slug ) ? $job_slug : '';
		if ( '' === $job_slug ) {
			$name      = get_query_var( 'name' );
			$post_type = get_query_var( 'post_type' );
			if ( JobPostType::POST_TYPE !== $post_type || ! is_string( $name ) ) {
				return;
			}
			$job_slug = $name;
		}

		$existing = get_posts(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'any',
				'name'           => sanitize_title( $job_slug ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( array() === $existing ) {
			return; // Never a job: let the branded 404 handle it.
		}

		$jobs = get_page_by_path( 'jobs' );
		wp_safe_redirect( $jobs instanceof \WP_Post ? (string) get_permalink( $jobs ) : home_url( '/jobs/' ), 301 );
		exit;
	}
}
