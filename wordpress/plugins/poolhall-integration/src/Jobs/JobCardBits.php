<?php
/**
 * Server-rendered job card fragments for loop items.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * Fragments the v3 Loop Item templates embed where dynamic tags fall
 * short (design system §12, directive §4.1). Both render from the loop's
 * current post:
 *
 * `[poolhall_job_card_flair]` — sector eyebrow row with the gold
 * Featured pill when the `is_featured` flag is set (the child theme
 * paints the orange top border off the pill's presence).
 *
 * `[poolhall_job_card_meta]` — icon meta row (location, work mode,
 * posted age) plus a three-line clamped summary derived from the job's
 * real description. Fields without data are simply omitted.
 */
final class JobCardBits {

	public const SHORTCODE_FLAIR = 'poolhall_job_card_flair';
	public const SHORTCODE_META  = 'poolhall_job_card_meta';

	public function register(): void {
		add_shortcode( self::SHORTCODE_FLAIR, array( $this, 'render_flair' ) );
		add_shortcode( self::SHORTCODE_META, array( $this, 'render_meta' ) );
	}

	public function render_flair(): string {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || JobPostType::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$sector = get_the_term_list( $post->ID, JobPostType::TAX_SECTOR, '', ', ' );
		$sector = is_string( $sector ) ? wp_strip_all_tags( $sector ) : '';

		$badge = '';
		if ( '' !== (string) get_post_meta( $post->ID, 'is_featured', true ) ) {
			$badge = '<span class="ph-badge ph-badge--featured">'
				. '<svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14l-5-4.87 6.91-1.01z"/></svg>'
				. esc_html__( 'Featured', 'poolhall-integration' ) . '</span>';
		}

		return '<span class="ph-job-flair">'
			. ( '' !== $sector ? '<span class="ph-eyebrow">' . esc_html( $sector ) . '</span>' : '<span></span>' )
			. $badge
			. '</span>';
	}

	public function render_meta(): string {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || JobPostType::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$pin   = '<svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
		$house = '<svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>';
		$clock = '<svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

		$items = array();

		$location = (string) get_post_meta( $post->ID, 'location_display', true );
		if ( '' !== $location ) {
			$items[] = '<span class="ph-job-meta__item">' . $pin . esc_html( $location ) . '</span>';
		}

		$mode = (string) get_post_meta( $post->ID, 'work_mode_raw', true );
		if ( '' !== $mode ) {
			$items[] = '<span class="ph-job-meta__item">' . $house . esc_html( $mode ) . '</span>';
		}

		$posted = (string) get_post_meta( $post->ID, 'date_posted', true );
		$stamp  = '' !== $posted ? strtotime( $posted ) : strtotime( $post->post_date_gmt );
		if ( false !== $stamp ) {
			// Precise ages read fine for a month; "8 months ago" just makes a
			// live role look stale, so older postings cap at 30+ days.
			$age     = time() - $stamp;
			$age_str = $age > 30 * DAY_IN_SECONDS
				? __( '30+ days ago', 'poolhall-integration' )
				: sprintf(
					/* translators: %s: human-readable age, e.g. "3 days". */
					__( '%s ago', 'poolhall-integration' ),
					human_time_diff( $stamp )
				);
			$items[] = '<span class="ph-job-meta__item">' . $clock . esc_html( $age_str ) . '</span>';
		}

		$html = '';
		if ( array() !== $items ) {
			$html .= '<span class="ph-job-meta">' . implode( '', $items ) . '</span>';
		}

		// Summaries come from the description with the leading Location:/
		// Salary:/Job Type: boilerplate removed — those fields are already
		// shown as chips, so repeating them wastes the whole snippet.
		$summary = \Poolhall\Integration\DesignSystem\V2Fragments::strip_leading_meta(
			(string) get_post_field( 'post_content', $post )
		);
		// Space before tags so adjacent paragraphs don't fuse ("roleJoin a…").
		$summary = trim( wp_strip_all_tags( str_replace( '<', ' <', $summary ) ) );
		if ( '' !== $summary ) {
			$html .= '<span class="ph-job-summary ph-body">' . esc_html( wp_trim_words( $summary, 24 ) ) . '</span>';
		}

		return $html;
	}
}
