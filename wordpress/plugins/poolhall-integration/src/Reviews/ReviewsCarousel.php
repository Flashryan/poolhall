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
			$read_all = '<a class="ph-link ph-link--arrow ph-reviews__readall" href="' . esc_url( $snapshot->google_maps_uri ) . '" rel="noopener" target="_blank">'
				. esc_html__( 'Read all reviews', 'poolhall-integration' ) . '</a>';
		}

		// Source attribution sits with the "read all" link on the right of the
		// head, so the section reads as Google's rating rather than our own.
		$aside = '<div class="ph-reviews__aside">' . $this->google_mark() . $read_all . '</div>';

		return '<section class="ph-reviews">'
			. '<div class="ph-reviews__head">'
			. '<div class="ph-stack-xs">'
			. '<p class="ph-eyebrow ph-reviews__eyebrow">' . esc_html__( 'Google reviews', 'poolhall-integration' ) . '</p>'
			. '<h2 class="ph-h2 ph-reviews__title">' . esc_html__( 'Rated five stars by the people we work with', 'poolhall-integration' ) . '</h2>'
			. '<p class="ph-lede ph-reviews__lede">' . esc_html__( 'Real feedback from the people we&rsquo;ve placed and the businesses we&rsquo;ve helped to hire.', 'poolhall-integration' ) . '</p>'
			. '</div>'
			. $aside
			. '</div>'
			// `data-ph-carousel` + `.carousel-track` hand this to the shared
			// carousel driver in ui.js, which moves one card per arrow press.
			. '<div class="ph-reviews__carousel" data-ph-carousel>'
			. '<div class="ph-carousel carousel-track" tabindex="0" role="group" aria-label="' . esc_attr__( 'Google reviews', 'poolhall-integration' ) . '">'
			. $cards
			. '</div>'
			. '<div class="ph-reviews__nav">'
			. $this->arrow( 'prev', __( 'Previous review', 'poolhall-integration' ) )
			. $this->arrow( 'next', __( 'Next review', 'poolhall-integration' ) )
			. '</div>'
			. '</div>'
			. '</section>';
	}

	/** One carousel arrow. `direction` is `prev` or `next`. */
	private function arrow( string $direction, string $label ): string {
		$path = 'prev' === $direction ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7';
		return '<button type="button" class="ph-reviews__arrow" data-ph-carousel-' . esc_attr( $direction )
			. ' aria-label="' . esc_attr( $label ) . '">'
			. '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"'
			. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. '<path d="' . $path . '"/></svg>'
			. '</button>';
	}

	/**
	 * The Google "G" in its four brand colours, inline so it needs no request
	 * and inherits nothing from the surrounding dark section.
	 */
	private function google_mark(): string {
		return '<span class="ph-reviews__google">'
			. '<svg class="ph-reviews__google-g" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">'
			. '<path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>'
			. '<path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>'
			. '<path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>'
			. '<path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>'
			. '</svg>'
			. '<span class="screen-reader-text">' . esc_html__( 'Reviews from Google', 'poolhall-integration' ) . '</span>'
			. '</span>';
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
