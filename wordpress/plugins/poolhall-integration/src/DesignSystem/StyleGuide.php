<?php
/**
 * Design-system style guide.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\DesignSystem;

/**
 * Server-rendered style-guide page (design system doc §26.4/§27): every
 * declared token, class and interactive state, rendered through the same
 * child-theme custom properties and classes the site uses, so the guide can
 * never drift from the implementation. The page is noindexed and removed
 * from sitemaps — it is a staging/QA artifact, not site content.
 */
final class StyleGuide {

	public const PAGE_SLUG = 'style-guide';
	public const SHORTCODE = 'poolhall_style_guide';

	private const COLOR_TOKENS = array(
		'navy-950',
		'navy-900',
		'navy-800',
		'navy-700',
		'orange-700',
		'orange-600',
		'orange-500',
		'orange-50',
		'blue-700',
		'blue-600',
		'gold-500',
		'paper',
		'mist',
		'white',
		'border',
		'ink',
		'muted',
		'success',
		'warning',
		'error',
		'focus',
	);

	private const TYPE_CLASSES = array(
		'ph-display' => 'Display — Source Serif 4',
		'ph-h1'      => 'Heading one — Source Serif 4',
		'ph-h2'      => 'Heading two — Source Serif 4',
		'ph-h3'      => 'Heading three — Source Serif 4',
		'ph-h4'      => 'Heading four — Hanken Grotesk',
		'ph-lede'    => 'Lede paragraph for introductions and supporting copy under page titles.',
		'ph-body'    => 'Body text at one rem with relaxed line height for long-form reading.',
		'ph-small'   => 'Small supporting text for metadata and captions.',
		'ph-eyebrow' => 'Eyebrow label',
		'ph-data'    => 'PH-27499 · IBM Plex Mono',
	);

	private const SPACE_TOKENS = array( '1', '2', '3', '4', '5', '6', '8', '10', 'section', 'section-lg' );

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_filter( 'wp_robots', array( $this, 'noindex' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_from_sitemap' ), 10, 2 );
	}

	/**
	 * @param array<string,bool> $robots Robots directives.
	 * @return array<string,bool>
	 */
	public function noindex( array $robots ): array {
		if ( is_page( self::PAGE_SLUG ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * @param array<string,mixed> $args WP_Query arguments.
	 * @return array<string,mixed>
	 */
	public function exclude_from_sitemap( array $args, string $post_type ): array {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		$page = get_page_by_path( self::PAGE_SLUG );
		if ( $page instanceof \WP_Post ) {
			$args['post__not_in']   = (array) ( $args['post__not_in'] ?? array() );
			$args['post__not_in'][] = $page->ID;
		}
		return $args;
	}

	public function render(): string {
		$sections = array(
			$this->colors(),
			$this->typography(),
			$this->spacing(),
			$this->buttons(),
			$this->badges(),
			$this->cards(),
			$this->forms(),
		);

		return '<div class="ph-container ph-section ph-stack-lg">'
			. '<header class="ph-stack-xs"><p class="ph-eyebrow" style="color: var(--ph-color-orange-700);">Design system</p>'
			. '<h1 class="ph-h1" style="color: var(--ph-color-navy-700);">Poolhall style guide</h1>'
			. '<p class="ph-lede">Every token, class and state from the design system (doc 10). Rendered from the live child-theme contract — this page is noindexed.</p></header>'
			. implode( '', $sections )
			. '</div>';
	}

	private function section( string $title, string $body ): string {
		return '<section class="ph-stack-md"><h2 class="ph-h3" style="color: var(--ph-color-navy-700);">' . esc_html( $title ) . '</h2>' . $body . '</section>';
	}

	private function colors(): string {
		$tiles = '';
		foreach ( self::COLOR_TOKENS as $token ) {
			$variable = '--ph-color-' . $token;
			$tiles   .= '<div class="ph-card" style="padding: 0; overflow: hidden;">'
				. '<div style="block-size: 3.5rem; background: var(' . esc_attr( $variable ) . '); border-block-end: var(--ph-border);"></div>'
				. '<p class="ph-data" style="padding: 0.5rem 0.75rem; margin: 0;">' . esc_html( $variable ) . '</p></div>';
		}
		return $this->section( 'Color tokens', '<div class="ph-grid-auto" style="--ph-space-6: 1rem;">' . $tiles . '</div>' );
	}

	private function typography(): string {
		$rows = '';
		foreach ( self::TYPE_CLASSES as $class => $sample ) {
			$rows .= '<div class="ph-stack-xs"><p class="ph-data ph-text-muted" style="margin: 0;">.' . esc_html( $class ) . '</p>'
				. '<p class="' . esc_attr( $class ) . '" style="margin: 0;">' . esc_html( $sample ) . '</p></div>';
		}
		$rows .= '<div class="ph-stack-xs"><p class="ph-data ph-text-muted" style="margin: 0;">.ph-link / .ph-link--arrow</p>'
			. '<p style="margin: 0;"><a class="ph-link" href="#colors">Text link</a> · <a class="ph-link ph-link--arrow" href="#colors">Arrow link</a></p></div>';
		return $this->section( 'Typography', '<div class="ph-stack-md">' . $rows . '</div>' );
	}

	private function spacing(): string {
		$bars = '';
		foreach ( self::SPACE_TOKENS as $token ) {
			$variable = '--ph-space-' . $token;
			$bars    .= '<div class="ph-cluster"><span class="ph-data" style="inline-size: 10rem;">' . esc_html( $variable ) . '</span>'
				. '<span style="display: inline-block; inline-size: var(' . esc_attr( $variable ) . '); block-size: 1rem; background: var(--ph-color-orange-500); border-radius: 2px;"></span></div>';
		}
		return $this->section( 'Spacing scale', '<div class="ph-stack-xs">' . $bars . '</div>' );
	}

	private function buttons(): string {
		$body = '<div class="ph-cluster">'
			. '<a class="ph-button ph-button--primary" href="#colors">Primary action</a>'
			. '<a class="ph-button ph-button--primary ph-button--lg" href="#colors">Large primary</a>'
			. '<a class="ph-button ph-button--secondary" href="#colors">Secondary</a>'
			. '<a class="ph-button ph-button--ghost" href="#colors">Ghost</a>'
			. '<a class="ph-button ph-button--danger" href="#colors">Danger</a>'
			. '<button class="ph-button ph-button--primary" disabled>Disabled</button>'
			. '<button class="ph-icon-button" aria-label="Example icon action">&#9825;</button>'
			. '</div>'
			. '<div class="ph-panel--navy ph-panel ph-cluster"><a class="ph-button ph-button--inverse" href="#colors">Inverse on navy</a></div>';
		return $this->section( 'Buttons', '<div class="ph-stack-md">' . $body . '</div>' );
	}

	private function badges(): string {
		$body = '<div class="ph-cluster">'
			. '<span class="ph-badge">Default</span>'
			. '<span class="ph-badge ph-badge--featured">Featured</span>'
			. '<span class="ph-badge ph-badge--success">Success</span>'
			. '<span class="ph-badge ph-badge--warning">Warning</span>'
			. '<span class="ph-badge ph-badge--error">Error</span>'
			. '<span class="ph-chip">Filter chip</span>'
			. '</div>'
			. '<div class="ph-alert"><p class="ph-body" style="margin: 0;">Informational alert with a supporting message.</p></div>'
			. '<div class="ph-alert ph-alert--success"><p class="ph-body" style="margin: 0;">Success alert.</p></div>'
			. '<div class="ph-alert ph-alert--error"><p class="ph-body" style="margin: 0;">Error alert.</p></div>';
		return $this->section( 'Badges, chips and alerts', '<div class="ph-stack-sm">' . $body . '</div>' );
	}

	private function cards(): string {
		$job_card = '<article class="ph-card ph-card--job ph-card--interactive is-featured ph-stack-xs">'
			. '<p class="ph-eyebrow" style="color: var(--ph-color-orange-700); margin: 0;">Manufacturing</p>'
			. '<h3 class="ph-h3" style="margin: 0;"><a class="ph-link" style="color: var(--ph-color-navy-700);" href="#colors">Example Job Title</a></h3>'
			. '<p class="ph-small ph-text-muted" style="margin: 0;">Wolverhampton · Full time · Onsite</p>'
			. '<hr class="ph-divider" style="margin-block: 0.75rem;" />'
			. '<p class="ph-h4" style="margin: 0; font-family: var(--ph-font-display); font-weight: 500;">£40,000 – £45,000</p>'
			. '</article>';
		$empty    = '<div class="ph-card ph-empty-state"><span class="ph-empty-state__icon" aria-hidden="true">&#9734;</span>'
			. '<h3 class="ph-h3" style="margin: 0;">No saved jobs yet</h3><p class="ph-body ph-text-muted" style="margin: 0;">One-sentence explanation with a single action.</p>'
			. '<a class="ph-button ph-button--primary" href="#colors">Browse live jobs</a></div>';
		$body     = '<div class="ph-grid-2">'
			. '<div class="ph-card"><p class="ph-body" style="margin: 0;">Base card: white surface, one-pixel border, sixteen-pixel radius, card shadow.</p></div>'
			. '<div class="ph-card ph-card--interactive"><p class="ph-body" style="margin: 0;">Interactive card: hover raises by two pixels with the raised shadow.</p></div>'
			. $job_card
			. '<div class="ph-panel"><p class="ph-body" style="margin: 0;">Quiet panel on mist surface.</p></div>'
			. '</div>' . $empty;
		return $this->section( 'Cards, panels and empty states', '<div class="ph-stack-md">' . $body . '</div>' );
	}

	private function forms(): string {
		$body = '<div class="ph-card"><div class="ph-form"><div class="ph-form-grid">'
			. '<div class="ph-field"><label class="ph-field__label" for="ph-sg-name">Full name</label>'
			. '<input class="ph-field__control" id="ph-sg-name" type="text" placeholder="" />'
			. '<p class="ph-field__help" style="margin: 0;">Help text sits below the control.</p></div>'
			. '<div class="ph-field ph-field--invalid"><label class="ph-field__label" for="ph-sg-email">Email (invalid state)</label>'
			. '<input class="ph-field__control" id="ph-sg-email" type="email" aria-invalid="true" />'
			. '<p class="ph-field__error" style="margin: 0;">Enter an email address in the right format.</p></div>'
			. '</div>'
			. '<div class="ph-field"><label class="ph-field__label" for="ph-sg-msg">Message</label>'
			. '<textarea class="ph-field__control" id="ph-sg-msg"></textarea></div>'
			. '<label class="ph-checkbox"><input type="checkbox" /> <span class="ph-body">Checkbox with an adjacent label.</span></label>'
			. '<div class="ph-file-upload"><p class="ph-body" style="margin: 0;">File upload drop area</p><p class="ph-small ph-text-muted" style="margin: 0;">PDF, DOC or DOCX up to 5 MB</p></div>'
			. '<div><button class="ph-button ph-button--primary" type="button">Submit example</button></div>'
			. '</div></div>';
		return $this->section( 'Form states', $body );
	}
}
