<?php
/**
 * Server-rendered Google reviews section.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Reviews;

/**
 * `[poolhall_reviews]` renders the home reviews section (directive §3.1.6):
 * section head plus a scroll-snap track of review cards. Data comes from
 * the cached Places snapshot; when none exists and demo content is enabled
 * (staging only, `poolhall_demo_content` option set by the gated seeding
 * action) the demo snapshot is used instead. With neither, the shortcode
 * renders nothing at all, so the section never shows an empty shell and
 * placeholder quotes can never reach production by default.
 */
final class ReviewsCarousel {

	public const SHORTCODE   = 'poolhall_reviews';
	public const DEMO_OPTION = 'poolhall_demo_reviews_snapshot';

	public function __construct( private readonly ReviewsService $service ) {
	}

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	public function render(): string {
		$snapshot = $this->service->snapshot_for_display();

		if ( null === $snapshot && '1' === (string) get_option( 'poolhall_demo_content' ) ) {
			$demo = get_option( self::DEMO_OPTION );
			if ( is_array( $demo ) && array() !== $demo ) {
				$snapshot = ReviewsSnapshot::from_array( $demo );
			}
		}

		if ( ! $snapshot instanceof ReviewsSnapshot || array() === $snapshot->reviews ) {
			return '';
		}

		$cards = '';
		foreach ( $snapshot->reviews as $review ) {
			$cards .= $this->card( $review );
		}

		$read_all = '';
		if ( null !== $snapshot->google_maps_uri ) {
			$read_all = '<a class="ph-link ph-link--arrow" href="' . esc_url( $snapshot->google_maps_uri ) . '" rel="noopener" target="_blank">'
				. esc_html__( 'Read all reviews', 'poolhall-integration' ) . '</a>';
		}

		return '<section class="ph-reviews">'
			. '<div class="ph-reviews__head">'
			. '<div class="ph-stack-xs">'
			. '<p class="ph-eyebrow" style="color:#C45712">' . esc_html__( 'Google reviews', 'poolhall-integration' ) . '</p>'
			. '<h2 class="ph-h2" style="color:#1B3052">' . esc_html__( 'Rated five stars by the people we place', 'poolhall-integration' ) . '</h2>'
			. '<p class="ph-lede">' . esc_html__( 'Real feedback from candidates and clients across the UK.', 'poolhall-integration' ) . '</p>'
			. '</div>'
			. $read_all
			. '</div>'
			. '<div class="ph-carousel" tabindex="0" role="group" aria-label="' . esc_attr__( 'Google reviews', 'poolhall-integration' ) . '">'
			. $cards
			. '</div>'
			. '</section>';
	}

	private function card( Review $review ): string {
		$star  = '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14l-5-4.87 6.91-1.01z"/></svg>';
		$stars = str_repeat( $star, max( 0, min( 5, $review->rating ) ) );

		$initials = '';
		foreach ( array_slice( preg_split( '/\s+/', trim( $review->author_name ) ) ?: array(), 0, 2 ) as $word ) {
			$initials .= mb_substr( $word, 0, 1 );
		}

		return '<article class="ph-card ph-card--review">'
			. '<span class="ph-card--review__stars" aria-label="' . esc_attr( sprintf( '%d/5', $review->rating ) ) . '">' . $stars . '</span>'
			. '<blockquote class="ph-card--review__quote">' . esc_html( $review->text ) . '</blockquote>'
			. '<footer class="ph-card--review__footer">'
			. '<span class="ph-avatar" aria-hidden="true">' . esc_html( mb_strtoupper( $initials ) ) . '</span>'
			. '<span><strong>' . esc_html( $review->author_name ) . '</strong>'
			. ( '' !== $review->relative_time ? '<br /><span class="ph-small ph-text-muted">' . esc_html( $review->relative_time ) . '</span>' : '' )
			. '</span>'
			. '</footer>'
			. '</article>';
	}
}
