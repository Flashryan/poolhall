<?php
/**
 * Server-rendered home jobs search panel.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * `[poolhall_job_search]` renders the §11 home search module: a plain GET
 * form to `/jobs/` (keyword, location, sector from the synced taxonomy)
 * that works without JavaScript and keeps its values on the results page
 * (spec 01 §3). `[poolhall_live_roles]` renders the hero trust item with
 * the live role count from the local job store, and renders nothing while
 * the store is empty so the hero never claims roles that do not exist.
 * The future Elementor widgets wrap these same renderers, mirroring the
 * `[poolhall_candidate_auth]` pattern.
 */
final class SearchForm {

	public const SHORTCODE_SEARCH     = 'poolhall_job_search';
	public const SHORTCODE_LIVE_ROLES = 'poolhall_live_roles';

	public function register(): void {
		add_shortcode( self::SHORTCODE_SEARCH, array( $this, 'render_search' ) );
		add_shortcode( self::SHORTCODE_LIVE_ROLES, array( $this, 'render_live_roles' ) );
	}

	/**
	 * @param array<string,string>|string $atts Shortcode attributes; `variant="wide"`
	 *                                          renders the full-width archive panel (§11).
	 */
	public function render_search( $atts = array() ): string {
		$jobs_page = get_page_by_path( 'jobs' );
		if ( ! $jobs_page instanceof \WP_Post ) {
			return '';
		}

		$atts    = shortcode_atts( array( 'variant' => '' ), is_array( $atts ) ? $atts : array() );
		$classes = 'ph-search' . ( 'wide' === $atts['variant'] ? ' ph-search--wide' : '' );

		// Read-only public search parameters; no state changes, no nonce.
		$request = SearchRequest::from_query( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$sectors = get_terms(
			array(
				'taxonomy'   => JobPostType::TAX_SECTOR,
				'hide_empty' => true,
			)
		);
		$sectors = is_array( $sectors ) ? $sectors : array();

		ob_start();
		?>
		<form class="<?php echo esc_attr( $classes ); ?>" role="search" method="get" action="<?php echo esc_url( (string) get_permalink( $jobs_page ) ); ?>">
			<div class="ph-search__field">
				<label class="ph-visually-hidden" for="ph-search-q"><?php esc_html_e( 'Job title, skill or keyword', 'poolhall-integration' ); ?></label>
				<input type="search" id="ph-search-q" name="q" placeholder="<?php esc_attr_e( 'Job title, skill or keyword', 'poolhall-integration' ); ?>" value="<?php echo esc_attr( $request->keyword ); ?>" />
			</div>
			<div class="ph-search__field">
				<label class="ph-visually-hidden" for="ph-search-location"><?php esc_html_e( 'Location', 'poolhall-integration' ); ?></label>
				<input type="text" id="ph-search-location" name="location" placeholder="<?php esc_attr_e( 'Location', 'poolhall-integration' ); ?>" value="<?php echo esc_attr( $request->location ); ?>" />
			</div>
			<?php if ( array() !== $sectors ) : ?>
			<div class="ph-search__field">
				<label class="ph-visually-hidden" for="ph-search-sector"><?php esc_html_e( 'Sector', 'poolhall-integration' ); ?></label>
				<select id="ph-search-sector" name="sector">
					<option value=""><?php esc_html_e( 'All sectors', 'poolhall-integration' ); ?></option>
					<?php foreach ( $sectors as $sector ) : ?>
						<option value="<?php echo esc_attr( $sector->slug ); ?>" <?php selected( $sector->slug, $request->sector ); ?>><?php echo esc_html( $sector->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>
			<button class="ph-button ph-button--primary ph-button--lg" type="submit"><?php esc_html_e( 'Search', 'poolhall-integration' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public function render_live_roles(): string {
		$live  = new \WP_Query(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small volume (~20 jobs).
					array(
						'key'     => 'expires_at',
						'value'   => gmdate( \DateTimeInterface::ATOM ),
						'compare' => '>',
					),
				),
			)
		);
		$count = (int) $live->found_posts;
		if ( 0 === $count ) {
			return '';
		}

		return '<span class="ph-search-trust__item">'
			. '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>'
			. esc_html(
				sprintf(
					/* translators: %d: number of live jobs. */
					_n( '%d live role right now', '%d live roles right now', $count, 'poolhall-integration' ),
					$count
				)
			)
			. '</span>';
	}
}
