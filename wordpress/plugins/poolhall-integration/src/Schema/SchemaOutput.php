<?php
/**
 * Schema output on single job pages.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Schema;

use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Support\Options;

/**
 * Prints JobPosting JSON-LD in <head> on published, unexpired single job
 * pages only. Reconstructs the normalized job from stored meta so visible
 * content and structured data always agree (Google requirement).
 */
final class SchemaOutput {

	public function __construct( private readonly Options $options ) {}

	public function register(): void {
		add_action( 'wp_head', array( $this, 'print_schema' ) );
		// Expiry hygiene (Google "Remove a job posting"): a closed role must
		// stop being advertised as live. Keeping the page reachable but
		// unindexed and out of the sitemap is Google's recommended route and
		// preserves inbound links, unlike deleting it.
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_expired_from_sitemap' ), 10, 2 );
		add_filter( 'wp_robots', array( $this, 'noindex_expired' ) );
	}

	/**
	 * Drop expired roles from the jobs sitemap.
	 *
	 * @param array<string,mixed> $args      Query args.
	 * @param string              $post_type Post type being mapped.
	 * @return array<string,mixed>
	 */
	public function exclude_expired_from_sitemap( $args, $post_type ) {
		if ( JobPostType::POST_TYPE !== $post_type || ! is_array( $args ) ) {
			return $args;
		}

		// Same comparison the jobs archive uses: ATOM strings sort
		// lexicographically at a fixed +00:00 offset, so a plain string
		// compare is correct and avoids MySQL's DATETIME cast choking on
		// the 'T' separator.
		$meta_query   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$meta_query[] = array(
			'key'     => 'expires_at',
			'value'   => gmdate( \DateTimeInterface::ATOM ),
			'compare' => '>',
		);

		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- sitemap generation is cached and bounded.

		return $args;
	}

	/**
	 * Tell search engines to drop an expired role rather than keep listing a
	 * job nobody can apply for.
	 *
	 * @param array<string,mixed> $robots Robots directives.
	 * @return array<string,mixed>
	 */
	public function noindex_expired( $robots ) {
		if ( ! is_array( $robots ) || ! is_singular( JobPostType::POST_TYPE ) ) {
			return $robots;
		}
		$post = get_post();
		if ( $post instanceof \WP_Post && JobFromPost::is_expired( $post->ID ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = false;
		}
		return $robots;
	}

	public function print_schema(): void {
		if ( ! is_singular( JobPostType::POST_TYPE ) ) {
			return;
		}
		$post = get_post();
		if ( null === $post || 'publish' !== $post->post_status ) {
			return;
		}

		$expires_at = JobFromPost::expires_at( $post->ID );
		if ( null === $expires_at ) {
			return;
		}
		if ( JobFromPost::is_expired( $post->ID ) ) {
			return; // Expired jobs must not carry JobPosting markup.
		}

		$job = JobFromPost::build( $post->ID );
		if ( null === $job ) {
			return;
		}

		$generator = new JobPostingSchema(
			$this->options->hiring_org_name(),
			$this->options->hiring_org_url(),
			$this->options->hiring_org_logo()
		);

		$schema = $generator->build(
			$job,
			(string) get_permalink( $post ),
			$expires_at,
			$this->options->direct_apply()
		);

		if ( null === $schema ) {
			return; // Eligibility gate refused; no markup is correct behavior.
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD from wp_json_encode.
	}
}
