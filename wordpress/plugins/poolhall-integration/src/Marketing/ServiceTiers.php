<?php
/**
 * Service tiers (Bronze / Silver / Gold) pricing component.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Marketing;

/**
 * `[poolhall_service_tiers]` — the three-up Bronze/Silver/Gold pricing grid
 * (docs/12 §11.6). Bronze = Advertise, Silver = Recruit, Gold = Search; Gold
 * is the featured tier with a "Most popular" ribbon and the gold primary CTA.
 *
 * The percentages are indicative only: the mandatory pricing note below the
 * grid says so in plain terms (guardrail: tier prices indicative). No Better
 * Job Adverts price appears here. The tier data is a pure array so its shape
 * (prices, included/excluded features, CTA styles) is unit-tested; render()
 * adds only escaping and the contact link.
 */
final class ServiceTiers {

	public const SHORTCODE = 'poolhall_service_tiers';

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Pure tier data. Each feature is [ state, label ] where state is
	 * 'on' (included, check), 'off' (excluded, cross) or 'head' (a
	 * "Everything in X, plus" sub-heading with no icon).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function tiers(): array {
		return array(
			array(
				'key'       => 'bronze',
				'tier'      => __( 'Bronze', 'poolhall-integration' ),
				'product'   => __( 'Advertise', 'poolhall-integration' ),
				'sub'       => __( 'Get your role in front of the right people, fast.', 'poolhall-integration' ),
				'price'     => '12%',
				'unit'      => __( 'of first-year salary', 'poolhall-integration' ),
				'cta'       => __( 'Advertise a role', 'poolhall-integration' ),
				'cta_style' => 'ghost',
				'featured'  => false,
				'features'  => array(
					array( 'on', __( 'Branded job advert', 'poolhall-integration' ) ),
					array( 'on', __( 'Posted across the major job boards', 'poolhall-integration' ) ),
					array( 'on', __( 'CV sift and screening', 'poolhall-integration' ) ),
					array( 'on', __( 'Shortlist of up to 5 candidates', 'poolhall-integration' ) ),
					array( 'on', __( '30-day replacement rebate', 'poolhall-integration' ) ),
					array( 'off', __( 'Competency-based phone screening', 'poolhall-integration' ) ),
					array( 'off', __( 'Proactive headhunting', 'poolhall-integration' ) ),
				),
			),
			array(
				'key'       => 'silver',
				'tier'      => __( 'Silver', 'poolhall-integration' ),
				'product'   => __( 'Recruit', 'poolhall-integration' ),
				'sub'       => __( 'Managed recruitment with screening and a structured shortlist.', 'poolhall-integration' ),
				'price'     => '15%',
				'unit'      => __( 'of first-year salary', 'poolhall-integration' ),
				'cta'       => __( 'Start recruiting', 'poolhall-integration' ),
				'cta_style' => 'steel',
				'featured'  => false,
				'features'  => array(
					array( 'head', __( 'Everything in Advertise, plus', 'poolhall-integration' ) ),
					array( 'on', __( 'Phone and competency-based screening', 'poolhall-integration' ) ),
					array( 'on', __( 'Active search across our network', 'poolhall-integration' ) ),
					array( 'on', __( 'Managed interview scheduling', 'poolhall-integration' ) ),
					array( 'on', __( 'Structured shortlist with notes', 'poolhall-integration' ) ),
					array( 'on', __( '60-day replacement rebate', 'poolhall-integration' ) ),
					array( 'off', __( 'Proactive headhunting', 'poolhall-integration' ) ),
				),
			),
			array(
				'key'       => 'gold',
				'tier'      => __( 'Gold', 'poolhall-integration' ),
				'product'   => __( 'Search', 'poolhall-integration' ),
				'sub'       => __( 'A dedicated, proactive search for senior and hard-to-fill roles.', 'poolhall-integration' ),
				'price'     => '20%',
				'unit'      => __( 'of first-year salary', 'poolhall-integration' ),
				'cta'       => __( 'Commission a search', 'poolhall-integration' ),
				'cta_style' => 'primary',
				'featured'  => true,
				'ribbon'    => __( 'Most popular', 'poolhall-integration' ),
				'features'  => array(
					array( 'head', __( 'Everything in Recruit, plus', 'poolhall-integration' ) ),
					array( 'on', __( 'Proactive headhunting', 'poolhall-integration' ) ),
					array( 'on', __( 'Reference and right-to-work checks', 'poolhall-integration' ) ),
					array( 'on', __( 'Dedicated account manager', 'poolhall-integration' ) ),
					array( 'on', __( 'Market and salary insight', 'poolhall-integration' ) ),
					array( 'on', __( 'Onboarding and aftercare', 'poolhall-integration' ) ),
					array( 'on', __( '90-day replacement guarantee', 'poolhall-integration' ) ),
					array( 'on', __( 'Priority response', 'poolhall-integration' ) ),
				),
			),
		);
	}

	/**
	 * The tier fees are an open question for the client (spec §16 #1: confirm
	 * the % and whether to show them publicly at all). Until that is settled
	 * this option stays off and the cards show the approved "pricing confirmed
	 * when you enquire" line instead of a figure, honouring the no-published-
	 * price guardrail. Flip it to '1' only once the fees are signed off.
	 */
	public const SHOW_PRICES_OPTION = 'poolhall_tier_pricing_public';

	public function render(): string {
		$contact = get_page_by_path( 'contact' );
		$cta_url = $contact instanceof \WP_Post ? (string) get_permalink( $contact ) : home_url( '/contact/' );

		$show_prices = '1' === (string) get_option( self::SHOW_PRICES_OPTION, '' );

		$cards = '';
		foreach ( self::tiers() as $tier ) {
			$cards .= $this->card( $tier, $cta_url, $show_prices );
		}

		$note = $show_prices
			? esc_html__( 'Fees shown are indicative and depend on the role, seniority and hiring volume. We confirm full terms in writing before any engagement.', 'poolhall-integration' )
			: esc_html__( 'Pricing is confirmed when you enquire, with no obligation. We confirm full terms in writing before any engagement.', 'poolhall-integration' );

		return '<div class="ph-tiers">' . $cards . '</div>'
			. '<p class="ph-pricing-note ph-data">' . $note . '</p>';
	}

	/**
	 * @param array<string,mixed> $tier
	 */
	private function card( array $tier, string $cta_url, bool $show_prices ): string {
		$key      = (string) ( $tier['key'] ?? '' );
		$featured = ! empty( $tier['featured'] );

		$ribbon = '';
		if ( $featured && isset( $tier['ribbon'] ) ) {
			$ribbon = '<span class="ph-tier__ribbon">' . esc_html( (string) $tier['ribbon'] ) . '</span>';
		}

		$feats = '';
		foreach ( (array) ( $tier['features'] ?? array() ) as $feature ) {
			$feats .= $this->feature_row( (string) ( $feature[0] ?? 'on' ), (string) ( $feature[1] ?? '' ) );
		}

		$button_class = 'ph-button ph-button--full ph-button--' . $this->cta_class( (string) ( $tier['cta_style'] ?? 'ghost' ) );

		$price = $show_prices
			? '<p class="ph-tier__price"><span class="ph-tier__figure">' . esc_html( (string) ( $tier['price'] ?? '' ) ) . '</span> '
				. '<span class="ph-tier__unit ph-data">' . esc_html( (string) ( $tier['unit'] ?? '' ) ) . '</span></p>'
			: '<p class="ph-tier__price ph-tier__price--enquire ph-data">' . esc_html__( 'Pricing confirmed when you enquire', 'poolhall-integration' ) . '</p>';

		return '<div class="ph-tier ph-tier--' . esc_attr( $key ) . ( $featured ? ' is-featured' : '' ) . '">'
			. $ribbon
			. '<div class="ph-tier__head">'
			. '<span class="ph-tier__name"><span class="ph-tier__dot" aria-hidden="true"></span>' . esc_html( (string) ( $tier['tier'] ?? '' ) ) . '</span>'
			. '<h3 class="ph-tier__product ph-h3">' . esc_html( (string) ( $tier['product'] ?? '' ) ) . '</h3>'
			. '<p class="ph-tier__sub ph-small ph-text-muted">' . esc_html( (string) ( $tier['sub'] ?? '' ) ) . '</p>'
			. $price
			. '</div>'
			. '<ul class="ph-tier-feats">' . $feats . '</ul>'
			. '<a class="' . esc_attr( $button_class ) . '" href="' . esc_url( $cta_url ) . '">' . esc_html( (string) ( $tier['cta'] ?? '' ) ) . '</a>'
			. '</div>';
	}

	private function feature_row( string $state, string $label ): string {
		if ( 'head' === $state ) {
			return '<li class="ph-tier-feat ph-tier-feat--head">' . esc_html( $label ) . '</li>';
		}

		$on   = 'on' === $state;
		$icon = $on
			? '<svg class="ph-tier-feat__icon ph-tier-feat__icon--on" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
			: '<svg class="ph-tier-feat__icon ph-tier-feat__icon--off" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

		return '<li class="ph-tier-feat' . ( $on ? '' : ' ph-tier-feat--off' ) . '">' . $icon . '<span>' . esc_html( $label ) . '</span></li>';
	}

	private function cta_class( string $style ): string {
		return in_array( $style, array( 'primary', 'steel', 'ghost', 'navy', 'secondary' ), true ) ? $style : 'ghost';
	}
}
