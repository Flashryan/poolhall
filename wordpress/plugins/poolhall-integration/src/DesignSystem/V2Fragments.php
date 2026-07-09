<?php
/**
 * v2 "Engineered" server-rendered fragments.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\DesignSystem;

use Poolhall\Integration\Jobs\FeaturedQuery;
use Poolhall\Integration\Jobs\JobPostType;

/**
 * Renders the prototype's signature components as exact markup
 * (project/ui_kits/website: blocks.jsx + ui.jsx + kit.css), so the frontend
 * matches the visual contract 1:1 instead of approximating it with builder
 * widgets. The matching CSS lives in the child theme's shared.css (v2 kit
 * block) and the behaviour (hero rotation, carousel arrows, mobile drawer)
 * in the theme's ui.js. All URLs resolve from real pages at render time.
 */
final class V2Fragments {

	private const SHORTCODES = array(
		'poolhall_v2_header'        => 'header',
		'poolhall_v2_footer'        => 'footer',
		'poolhall_v2_hero'          => 'hero',
		'poolhall_v2_feature_strip' => 'feature_strip',
		'poolhall_v2_featured_jobs' => 'featured_jobs',
		'poolhall_v2_sector_tiles'  => 'sector_tiles',
		'poolhall_v2_sector_page'   => 'sector_page',
		'poolhall_v2_delivery'      => 'delivery',
		'poolhall_v2_secondary'     => 'secondary',
		'poolhall_v2_candidates'    => 'candidates',
		'poolhall_v2_about'         => 'about',
		'poolhall_v2_points'        => 'points',
		'poolhall_v2_services'      => 'services',
		'poolhall_v2_register_page' => 'register_page',
		'poolhall_v2_blog_index'    => 'blog_index',
		'poolhall_v2_contact'       => 'contact_page',
		'poolhall_v2_job_single'    => 'job_single',
		'poolhall_v2_jobs_head'     => 'jobs_head',
		'poolhall_v2_legal_head'    => 'legal_head',
		'poolhall_v2_cta_bands'     => 'cta_bands',
	);

	public function __construct( private readonly FeaturedQuery $featured ) {
	}

	public function register(): void {
		foreach ( self::SHORTCODES as $tag => $method ) {
			add_shortcode( $tag, array( $this, $method ) );
		}
	}

	// ------------------------------------------------------------ helpers --

	private function url( string $slug ): string {
		$page = get_page_by_path( $slug );
		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : home_url( '/' . $slug . '/' );
	}

	/** Attachment URL by the keyed sideload used across the setup scripts. */
	private function image( string $filename ): string {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'poolhall_content_image', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- single keyed lookup, tiny library.
				'meta_value'     => $filename, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return array() === $found ? '' : (string) wp_get_attachment_image_url( (int) $found[0], 'large' );
	}

	/** Minimal inline icons (Lucide-style strokes; brand glyphs are paths). */
	private function icon( string $name ): string {
		$stroke = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
		$paths  = array(
			'phone'           => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3A19.5 19.5 0 0 1 5.1 13 19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/>',
			'mail'            => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
			'map-pin'         => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
			'briefcase'       => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
			'banknote'        => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
			'building'        => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
			'arrow-right'     => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
			'chevron-down'    => '<path d="m6 9 6 6 6-6"/>',
			'chevron-right'   => '<path d="m9 18 6-6-6-6"/>',
			'menu'            => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
			'x'               => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
			'star'            => '<path d="M11.5 2.6a.6.6 0 0 1 1 0l2.8 5.7 6.3.9a.6.6 0 0 1 .3 1l-4.5 4.4 1 6.3a.6.6 0 0 1-.8.6L12 18.6l-5.6 3a.6.6 0 0 1-.9-.7l1.1-6.3L2.1 10a.6.6 0 0 1 .3-1l6.3-.9z"/>',
			'clipboard-list'  => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
			'messages-square' => '<path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/>',
			'file-text'       => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
			'circle-check'    => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
			'clock'           => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'users'           => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'shield-check'    => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
			'user-check'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
			'trending-up'     => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
			'calendar-clock'  => '<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><path d="M17.5 17.5 16 16.25V14"/><path d="M22 16a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"/>',
			'hard-hat'        => '<path d="M2 18a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1z"/><path d="M10 10V5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5"/><path d="M4 15v-3a6 6 0 0 1 6-6"/><path d="M14 6a6 6 0 0 1 6 6v3"/>',
			'linkedin'        => '<path fill="currentColor" stroke="none" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.07 2.07 0 1 1 0-4.13 2.07 2.07 0 0 1 0 4.13zM7.12 20.45H3.55V9h3.57zM22.22 0H1.77C.8 0 0 .78 0 1.74v20.51C0 23.21.8 24 1.77 24h20.45c.98 0 1.78-.79 1.78-1.75V1.74C24 .78 23.2 0 22.22 0z"/>',
			'instagram'       => '<rect width="20" height="20" x="2" y="2" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg viewBox="0 0 24 24" aria-hidden="true" ' . ( 'linkedin' === $name ? 'fill="currentColor" stroke="none"' : $stroke ) . '>' . $paths[ $name ] . '</svg>';
	}

	/** Sector term name to prototype tag class + tile photo. */
	private function sector_key( string $sector ): string {
		$s = strtolower( $sector );
		if ( str_contains( $s, 'construct' ) ) {
			return 'con';
		}
		if ( str_contains( $s, 'manufact' ) ) {
			return 'man';
		}
		return 'dig';
	}

	/**
	 * Stricter than sector_key(): that helper buckets every unknown industry
	 * as 'dig' (fine for card tinting), which would let Insurance or
	 * Automotive roles onto the Digital sector page's job list.
	 */
	private function term_matches_key( string $term, string $key ): bool {
		if ( 'dig' !== $key ) {
			return $this->sector_key( $term ) === $key;
		}
		$s = strtolower( $term );
		foreach ( array( 'market', 'digital', 'creative', 'media', 'seo' ) as $needle ) {
			if ( str_contains( $s, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private function sector_photo( string $key ): string {
		return $this->image(
			match ( $key ) {
				'con'   => 'sector-construction.webp',
				'man'   => 'sector-manufacturing.webp',
				default => 'sector-digital.webp',
			}
		);
	}

	// ------------------------------------------------------------- header --

	public function header(): string {
		$logo = $this->image( 'poolhall-logo.png' );

		$employers = array(
			array( 'shield-check', 'Why us', $this->url( 'why-us' ) ),
			array( 'users', 'Recruitment delivery options', $this->url( 'delivery-options' ) ),
			array( 'briefcase', 'Bespoke search', $this->url( 'bespoke-search' ) ),
			array( 'star', 'Better Job Adverts', $this->url( 'better-job-adverts' ) ),
			array( 'building', 'HR services', $this->url( 'hr-services' ) ),
		);
		$sectors   = array(
			array( 'building', 'Construction', $this->url( 'sectors/construction' ) ),
			array( 'users', 'Manufacturing', $this->url( 'sectors/manufacturing' ) ),
			array( 'briefcase', 'Digital', $this->url( 'sectors/digital' ) ),
		);
		$cands     = array(
			array( 'users', 'Register', $this->url( 'register' ) ),
			array( 'briefcase', 'Jobs', $this->url( 'jobs' ) ),
			array( 'shield-check', 'Registration guide', $this->url( 'registration-guide' ) ),
			array( 'star', 'Interview tips', $this->url( 'interview-tips' ) ),
			array( 'mail', 'CV tips', $this->url( 'cv-tips' ) ),
		);

		$dropdown = function ( array $items ): string {
			$out = '<div class="dropdown">';
			foreach ( $items as [ $icon, $label, $url ] ) {
				$out .= '<a href="' . esc_url( $url ) . '">' . $this->icon( $icon ) . esc_html( $label ) . '</a>';
			}
			return $out . '</div>';
		};
		$group    = function ( string $label, string $url, array $items ) use ( $dropdown ): string {
			return '<div class="nav-item"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $this->icon( 'chevron-down' ) . '</a>' . $dropdown( $items ) . '</div>';
		};
		$plain    = static function ( string $label, string $url ): string {
			return '<div class="nav-item"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div>';
		};

		$drawer_links = static function ( array $items ): string {
			$out = '';
			foreach ( $items as [ , $label, $url ] ) {
				$out .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
			return $out;
		};

		return '<header class="ph-v2-header">'
			// No-JS fallback: the burger needs JS, so without it the full nav
			// links show (wrapped) and the burger hides. Core routes always
			// reachable (directive: JS disabled leaves content and links working).
			. '<noscript><style>.ph-v2-header .nav-links{display:flex !important;flex-wrap:wrap}.ph-v2-header .burger{display:none !important}.ph-v2-header .nav{height:auto;padding:10px 0;flex-wrap:wrap}</style></noscript>'
			. '<div class="topbar"><div class="container">'
			. '<div class="seg"><a href="tel:01215163000">' . $this->icon( 'phone' ) . '0121 516 3000</a>'
			. '<a class="hide-sm" href="mailto:jobs@poolhallrecruitment.co.uk">' . $this->icon( 'mail' ) . 'jobs@poolhallrecruitment.co.uk</a></div>'
			. '<div class="seg hide-sm"><span class="loc">' . $this->icon( 'map-pin' ) . 'West Midlands &middot; National reach</span></div>'
			. '</div></div>'
			. '<div class="container"><nav class="nav" aria-label="Primary">'
			. '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '">'
			. ( '' !== $logo ? '<img src="' . esc_url( $logo ) . '" alt="Poolhall Recruitment Limited" />' : '<span class="bn">Poolhall</span>' )
			. '</a>'
			. '<div class="nav-links">'
			. $group( 'Employers', $this->url( 'employers' ), $employers )
			. $group( 'Sectors', $this->url( 'sectors' ), $sectors )
			. $plain( 'Jobs', $this->url( 'jobs' ) )
			. $group( 'Candidates', $this->url( 'candidates' ), $cands )
			. $plain( 'About', $this->url( 'about' ) )
			. $plain( 'Blog', $this->url( 'blog' ) )
			. $plain( 'Contact', $this->url( 'contact' ) )
			. '</div>'
			. '<div class="nav-spacer"></div>'
			. '<div class="nav-cta">'
			. '<a class="btn btn-ghost btn-find" href="' . esc_url( $this->url( 'jobs' ) ) . '">Find work</a>'
			. '<a class="btn btn-primary" href="' . esc_url( $this->url( 'employers' ) ) . '">Hire talent' . $this->icon( 'arrow-right' ) . '</a>'
			. '<button class="burger" type="button" data-ph-drawer-open aria-label="Menu">' . $this->icon( 'menu' ) . '</button>'
			. '</div></nav></div>'
			. '</header>'
			// Mobile drawer: OUTSIDE the header element, because the header's
			// backdrop-filter makes it the containing block for fixed
			// descendants (the drawer's height:100% resolved to the header's
			// 116px instead of the viewport - the squashed-drawer bug).
			. '<div class="ph-v2-drawer-root">'
			. '<div class="drawer-overlay" data-ph-drawer-overlay></div>'
			. '<div class="drawer" data-ph-drawer role="dialog" aria-label="Menu">'
			. '<div class="drawer-head"><span class="bn">POOLHALL</span>'
			. '<button class="drawer-close" type="button" data-ph-drawer-close aria-label="Close">' . $this->icon( 'x' ) . '</button></div>'
			. '<div class="drawer-body">'
			. '<div class="drawer-grp emp"><div class="gl">Employers, hire talent</div>'
			. '<a href="' . esc_url( $this->url( 'employers' ) ) . '">Employers hub</a>' . $drawer_links( $employers ) . '</div>'
			. '<div class="drawer-grp cand"><div class="gl">Candidates, find work</div>'
			. '<a href="' . esc_url( $this->url( 'candidates' ) ) . '">Candidate hub</a>' . $drawer_links( $cands ) . '</div>'
			. '<div class="drawer-grp"><div class="gl">Sectors</div>' . $drawer_links( $sectors ) . '</div>'
			. '<div class="drawer-grp"><div class="gl">Company</div>'
			. '<a href="' . esc_url( $this->url( 'about' ) ) . '">About us</a>'
			. '<a href="' . esc_url( $this->url( 'team' ) ) . '">Meet the team</a>'
			. '<a href="' . esc_url( $this->url( 'blog' ) ) . '">Blog</a>'
			. '<a href="' . esc_url( $this->url( 'contact' ) ) . '">Contact</a></div>'
			. '</div>'
			. '<div class="drawer-foot">'
			. '<a class="btn btn-ghost" href="' . esc_url( $this->url( 'jobs' ) ) . '">Find work</a>'
			. '<a class="btn btn-primary" href="' . esc_url( $this->url( 'employers' ) ) . '">Hire talent</a>'
			. '</div></div>'
			. '</div>';
	}

	// ------------------------------------------------------------- footer --

	public function footer(): string {
		$logo   = $this->image( 'poolhall-logo.png' );
		$col    = static function ( string $head, array $links ): string {
			$out = '<div><h5>' . esc_html( $head ) . '</h5><ul>';
			foreach ( $links as [ $label, $url ] ) {
				$out .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
			}
			return $out . '</ul></div>';
		};
		$accred = array_filter(
			array(
				$this->image( 'accred-team.png' ),
				$this->image( 'accred-bni.png' ),
			)
		);

		$chips = '';
		foreach ( $accred as $src ) {
			$chips .= '<span class="chip"><img src="' . esc_url( $src ) . '" alt="Accreditation logo" loading="lazy" /></span>';
		}

		return '<footer class="ph-v2-footer"><div class="container">'
			. '<div class="footer-top">'
			. '<div class="footer-brand"><div class="fb">'
			. ( '' !== $logo ? '<img src="' . esc_url( $logo ) . '" alt="Poolhall Recruitment Limited" />' : '<span class="bn">Poolhall</span>' )
			. '</div>'
			. '<p>West Midlands roots, national recruitment reach. Specialists in Construction, Manufacturing and Digital, since 2021.</p>'
			. '<div class="footer-soc">'
			. '<a href="https://www.linkedin.com/company/poolhall-recruitment" aria-label="LinkedIn">' . $this->icon( 'linkedin' ) . '</a>'
			. '<a href="https://www.instagram.com/poolhallrecruitment" aria-label="Instagram">' . $this->icon( 'instagram' ) . '</a>'
			. '</div></div>'
			. $col(
				'Sectors',
				array(
					array( 'Construction', $this->url( 'sectors/construction' ) ),
					array( 'Manufacturing', $this->url( 'sectors/manufacturing' ) ),
					array( 'Digital', $this->url( 'sectors/digital' ) ),
					array( 'All live jobs', $this->url( 'jobs' ) ),
				)
			)
			. $col(
				'Employers',
				array(
					array( 'Why us', $this->url( 'why-us' ) ),
					array( 'Delivery options', $this->url( 'delivery-options' ) ),
					array( 'Bespoke search', $this->url( 'bespoke-search' ) ),
					array( 'Better Job Adverts', $this->url( 'better-job-adverts' ) ),
					array( 'HR services', $this->url( 'hr-services' ) ),
				)
			)
			. $col(
				'Candidates',
				array(
					array( 'Find a job', $this->url( 'jobs' ) ),
					array( 'Register', $this->url( 'register' ) ),
					array( 'Full registration', $this->url( 'candidate-registration' ) ),
					array( 'CV tips', $this->url( 'cv-tips' ) ),
					array( 'Interview tips', $this->url( 'interview-tips' ) ),
				)
			)
			. $col(
				'Company',
				array(
					array( 'About us', $this->url( 'about' ) ),
					array( 'Meet the team', $this->url( 'team' ) ),
					array( 'Blog', $this->url( 'blog' ) ),
					array( 'Contact', $this->url( 'contact' ) ),
					array( 'Our commitment', $this->url( 'commitment' ) ),
				)
			)
			. '</div>'
			. ( '' !== $chips ? '<div class="footer-accred"><span class="al">Proud members of</span><span class="chips">' . $chips . '</span></div>' : '' )
			. '<div class="footer-bottom">'
			. '<span>&copy; ' . esc_html( gmdate( 'Y' ) ) . ' Poolhall Recruitment Limited &middot; Company No. 13319338 &middot; VAT 383617377</span>'
			. '<span class="links">'
			. '<a href="' . esc_url( $this->url( 'terms' ) ) . '">Terms</a>'
			. '<a href="' . esc_url( $this->url( 'privacy-policy' ) ) . '">Privacy</a>'
			. '<a href="' . esc_url( $this->url( 'cookies' ) ) . '">Cookies</a>'
			. '<span>Grosvenor House, 11 St Pauls Square, Birmingham, B3 1RB</span>'
			. '</span></div>'
			. '</div>'
			. '<button class="ph-backtop" type="button" data-ph-backtop aria-label="Back to top">' . $this->icon( 'arrow-right' ) . '</button>'
			// Essential-cookies notice (directive §12/§13): this site sets no
			// analytics or third-party cookies, so the notice is informational
			// with a link to the cookie policy; dismissal is remembered locally.
			. '<div class="ph-cookies" data-ph-cookies hidden><p>We only use essential cookies to run this site. No tracking, no third parties. <a href="' . esc_url( $this->url( 'cookies' ) ) . '">Cookie policy</a></p>'
			. '<button type="button" class="btn btn-primary" data-ph-cookies-ok>OK</button></div>'
			. '</footer>';
	}

	// --------------------------------------------------------------- hero --

	public function hero(): string {
		$worlds = array(
			array( 'Construction', $this->image( 'sector-construction.webp' ) ),
			array( 'Manufacturing', $this->image( 'sector-manufacturing.webp' ) ),
			array( 'Digital', $this->image( 'sector-digital.webp' ) ),
			array( 'Our team', $this->image( 'poolhall-office.jpg' ) ),
		);

		$slides  = '';
		$markers = '';
		foreach ( $worlds as $i => [ $label, $img ] ) {
			$on       = 0 === $i ? ' on' : '';
			$slides  .= '<div class="hero-slide' . $on . '">'
				. ( '' !== $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="' . ( 0 === $i ? 'eager' : 'lazy' ) . '" />' : '' )
				. '</div>';
			$markers .= '<button type="button" class="world' . $on . '" data-ph-world="' . (int) $i . '">'
				. '<span class="wi">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span>'
				. '<span class="wn">' . esc_html( $label ) . '</span>'
				. '</button>';
		}

		return '<section class="hero ph-v2-hero" data-ph-hero>'
			. '<div class="hero-stage">' . $slides . '</div>'
			. '<div class="container"><div class="hero-inner">'
			. '<p class="label on-dark"><span class="idx">//</span> Recruitment, done well</p>'
			. '<h1 class="display on-dark">West Midlands roots.<br /><em>National</em> recruitment reach.</h1>'
			. '<p class="lead on-dark">We help the people who build, make and market British business find their next move, across Construction, Manufacturing and Digital. Independent, down-to-earth and always happy to talk things through.</p>'
			. '<div class="hero-cta">'
			. '<a class="btn btn-primary btn-lg" href="' . esc_url( $this->url( 'jobs' ) ) . '">Find work' . $this->icon( 'arrow-right' ) . '</a>'
			. '<a class="btn btn-ghost-light btn-lg" href="' . esc_url( $this->url( 'employers' ) ) . '">Hire talent</a>'
			. '</div></div></div>'
			. '<div class="worlds"><div class="container">' . $markers . '</div></div>'
			. '</section>';
	}

	// ------------------------------------------------------ feature strip --

	public function feature_strip(): string {
		$items = array(
			array( 'shield-check', 'Specialist, not generalist', 'Three sectors we know inside out, so the conversation starts in the right place.' ),
			array( 'phone', 'A friendly voice on the phone', 'Consultants who pick up, take the time to listen, and offer genuine advice.' ),
			array( 'map-pin', 'National reach', 'Black Country roots, helping people find roles the length of the country.' ),
		);
		$cells = '';
		foreach ( $items as [ $icon, $title, $line ] ) {
			$cells .= '<div class="feat"><span class="fi">' . $this->icon( $icon ) . '</span>'
				. '<div><h4>' . esc_html( $title ) . '</h4><p>' . esc_html( $line ) . '</p></div></div>';
		}
		return '<div class="feature-strip ph-v2-strip"><div class="container"><div class="grid">' . $cells . '</div></div></div>';
	}

	// ------------------------------------------------------ featured jobs --

	public function featured_jobs(): string {
		$ids = $this->featured->ids();
		if ( array() === $ids ) {
			return '<div class="empty-state"><p>New roles are added all the time. Register your CV and we will be in touch.</p></div>';
		}

		$cards = '';
		foreach ( $ids as $post_id ) {
			$cards .= $this->job_card( $post_id );
		}

		return '<div class="carousel" data-ph-carousel>'
			. '<div class="carousel-track">' . $cards . '</div>'
			. '<div class="carousel-nav">'
			. '<button type="button" class="cbtn" data-ph-carousel-prev aria-label="Previous">' . $this->icon( 'arrow-right' ) . '</button>'
			. '<button type="button" class="cbtn" data-ph-carousel-next aria-label="Next">' . $this->icon( 'arrow-right' ) . '</button>'
			. '</div></div>';
	}

	private function job_card( int $post_id ): string {
		$title    = get_the_title( $post_id );
		$url      = (string) get_permalink( $post_id );
		$sectors  = wp_get_object_terms( $post_id, JobPostType::TAX_SECTOR, array( 'fields' => 'names' ) );
		$sector   = is_array( $sectors ) && array() !== $sectors ? (string) $sectors[0] : '';
		$key      = $this->sector_key( $sector );
		$photo    = $this->sector_photo( $key );
		$featured = '' !== (string) get_post_meta( $post_id, 'is_featured', true );
		$location = (string) get_post_meta( $post_id, 'location_display', true );
		$mode     = (string) get_post_meta( $post_id, 'work_mode_raw', true );
		$salary   = (string) get_post_meta( $post_id, 'salary_display', true );
		$types    = wp_get_object_terms( $post_id, JobPostType::TAX_JOB_TYPE, array( 'fields' => 'names' ) );
		$type     = is_array( $types ) && array() !== $types ? (string) $types[0] : '';
		$ref      = (string) get_post_meta( $post_id, 'source_job_id', true );
		$snip     = wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), 24, '&hellip;' );

		$meta = '';
		if ( '' !== $location ) {
			$meta .= '<span class="m">' . $this->icon( 'map-pin' ) . esc_html( $location ) . '</span>';
		}
		if ( '' !== $mode ) {
			$meta .= '<span class="m">' . $this->icon( 'clock' ) . esc_html( $mode ) . '</span>';
		}
		if ( '' !== $salary || '' !== $type ) {
			$meta .= '<span class="m">' . $this->icon( 'banknote' )
				. ( '' !== $salary ? '<span class="sal">' . esc_html( $salary ) . '</span>' : '' )
				. ( '' !== $type ? '<span>&nbsp;&middot;&nbsp;' . esc_html( $type ) . '</span>' : '' )
				. '</span>';
		}

		return '<a class="jobcard' . ( $featured ? ' feat' : '' ) . '" href="' . esc_url( $url ) . '">'
			. '<span class="jc-photo">'
			. ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" loading="lazy" />' : '' )
			. ( '' !== $sector ? '<span class="jc-tag ' . esc_attr( $key ) . '">' . esc_html( $sector ) . '</span>' : '' )
			. ( $featured ? '<span class="jc-feat">Featured</span>' : '' )
			. '</span>'
			. '<span class="jc-title">' . esc_html( $title ) . '</span>'
			. '<span class="jc-meta">' . $meta . '</span>'
			. ( '' !== $snip ? '<span class="jc-snip">' . esc_html( $snip ) . '</span>' : '' )
			. '<span class="jc-foot"><span class="jc-view">View job ' . $this->icon( 'arrow-right' ) . '</span>'
			. ( '' !== $ref ? '<span class="jc-ref">#' . esc_html( $ref ) . '</span>' : '' )
			. '</span></a>';
	}

	// ------------------------------------------------------- sector tiles --

	public function sector_tiles(): string {
		$tiles = array(
			array( 'Construction', 'con', 'Site managers, project managers, engineers and skilled trades for contractors across the Midlands and nationally.', 'sectors/construction' ),
			array( 'Manufacturing', 'man', 'Welders, fabricators, production and engineering talent for the Black Country&rsquo;s manufacturing heartland.', 'sectors/manufacturing' ),
			array( 'Digital', 'dig', 'Marketing, PPC, SEO and digital specialists for fast-growing agencies and in-house teams.', 'sectors/digital' ),
		);

		$out = '<div class="sector-grid">';
		foreach ( $tiles as $i => [ $name, $key, $line, $slug ] ) {
			$term  = get_term_by( 'name', 'con' === $key ? 'Construction & Skilled Trade' : ( 'man' === $key ? 'Manufacturing' : 'Marketing & PR' ), JobPostType::TAX_SECTOR );
			$count = $term instanceof \WP_Term ? (int) $term->count : 0;
			$photo = $this->sector_photo( $key );
			$out  .= '<a class="sector-tile" href="' . esc_url( $this->url( $slug ) ) . '">'
				. ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" loading="lazy" />' : '' )
				. '<span class="st-body">'
				. '<span class="st-idx">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . ' / ' . ( $count > 0 ? esc_html( $count . ' live role' . ( 1 === $count ? '' : 's' ) ) : 'Sector' ) . '</span>'
				. '<h3>' . esc_html( $name ) . '</h3>'
				. '<p>' . wp_kses( $line, array() ) . '</p>'
				. '<span class="st-go">Explore sector ' . $this->icon( 'arrow-right' ) . '</span>'
				. '</span></a>';
		}
		return $out . '</div>';
	}

	// -------------------------------------------------------- sector page --

	/**
	 * Full sector template (prototype screen-pages.jsx SectorPage, §9.5):
	 * photo pagehead with breadcrumb, "Roles we place" split, four numbered
	 * points, live jobs in the sector, paired CTA bands.
	 *
	 * @param array<string,string>|string $atts Shortcode atts: sector=construction|manufacturing|digital.
	 */
	public function sector_page( $atts ): string {
		$atts = shortcode_atts( array( 'sector' => 'construction' ), is_array( $atts ) ? $atts : array() );
		$key  = $this->sector_key( (string) $atts['sector'] );

		$copy = array(
			'con' => array(
				'name'  => 'Construction',
				'blurb' => 'Site managers, project managers, engineers and skilled trades for contractors across the Midlands and nationally.',
				'roles' => array( 'Project & Site Managers', 'Quantity Surveyors', 'Site Engineers', 'Skilled Trades & Operatives', 'M&amp;E / HVAC Engineers', 'Estimators' ),
			),
			'man' => array(
				'name'  => 'Manufacturing',
				'blurb' => 'Welders, fabricators, production and engineering talent for the Black Country&rsquo;s manufacturing heartland.',
				'roles' => array( 'Welders & Fabricators', 'CNC Machinists', 'Production Operatives', 'Maintenance Engineers', 'Quality Inspectors', 'Production Managers' ),
			),
			'dig' => array(
				'name'  => 'Digital',
				'blurb' => 'Marketing, PPC, SEO and digital specialists for fast-growing agencies and in-house teams.',
				'roles' => array( 'Paid Media & PPC', 'SEO Specialists', 'Content & Social', 'Designers', 'Developers', 'Marketing Managers' ),
			),
		)[ $key ];

		$name  = $copy['name'];
		$lower = strtolower( $name );
		$photo = $this->sector_photo( $key );

		$crumb = '<div class="crumb">'
			. '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>' . $this->icon( 'chevron-right' )
			. '<a href="' . esc_url( $this->url( 'sectors' ) ) . '">Sectors</a>' . $this->icon( 'chevron-right' )
			. '<span>' . esc_html( $name ) . '</span></div>';

		$head = '<div class="pagehead photo">'
			. ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" />' : '' )
			. '<div class="container">' . $crumb
			. '<p class="label on-dark"><span class="idx">//</span> Sector</p>'
			. '<h1>' . esc_html( $name ) . ' recruitment</h1>'
			. '<p class="lead">' . wp_kses( $copy['blurb'], array() ) . '</p>'
			. '</div></div>';

		$role_items = '';
		foreach ( $copy['roles'] as $role ) {
			$role_items .= '<div class="role">' . wp_kses( $role, array() ) . '</div>';
		}
		$split = '<section class="section"><div class="container"><div class="split">'
			. '<div>'
			. '<p class="label"><span class="idx">01 /</span> Roles we place</p>'
			. '<h2 class="h2">Talent across the whole ' . esc_html( $lower ) . ' chain.</h2>'
			. '<p class="lead" style="margin-top:14px">From hands-on roles through to leadership, we help across the whole ' . esc_html( $lower ) . ' chain, with consultants who genuinely understand the work.</p>'
			. '<div class="rolegrid">' . $role_items . '</div>'
			. '</div>'
			. '<div class="media">' . ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" />' : '' ) . '</div>'
			. '</div></div></section>';

		$points = '<section class="section section--white"><div class="container"><div class="points">'
			. '<div class="point"><div class="pi">// 01</div><h3>We know the market</h3><p>Real sector knowledge means we understand the role, the rates and the right fit, first time.</p></div>'
			. '<div class="point"><div class="pi">// 02</div><h3>A network that runs deep</h3><p>Years of relationships across ' . esc_html( $lower ) . ' mean we reach people the job boards never see.</p></div>'
			. '<div class="point"><div class="pi">// 03</div><h3>Compliance handled</h3><p>Right-to-work, certs and checks managed properly, so you can hire with confidence.</p></div>'
			. '<div class="point"><div class="pi">// 04</div><h3>A focused shortlist</h3><p>We send you a carefully chosen handful of strong candidates, so you can review them in the time you&rsquo;ve got.</p></div>'
			. '</div></div></section>';

		$jobs_html = '';
		$job_ids   = $this->sector_job_ids( $key );
		if ( array() !== $job_ids ) {
			$cards = '';
			foreach ( $job_ids as $post_id ) {
				$cards .= $this->job_card( $post_id );
			}
			$jobs_html = '<section class="section"><div class="container">'
				. '<div class="section-head"><div>'
				. '<p class="label"><span class="idx">02 /</span> Open roles</p>'
				. '<h2 class="h2">Live ' . esc_html( $name ) . ' jobs</h2>'
				. '</div><a class="btn btn-ghost" href="' . esc_url( $this->url( 'jobs' ) ) . '">All jobs ' . $this->icon( 'arrow-right' ) . '</a></div>'
				. '<div class="secjobs">' . $cards . '</div>'
				. '</div></section>';
		}

		return '<div class="ph-v2page">' . $head . $split . $points . $jobs_html . '</div>' . $this->cta_bands();
	}

	// ----------------------------------------------------- delivery options --

	/**
	 * Recruitment Delivery Options (prototype screen-pages.jsx Delivery,
	 * §9.7): photo pagehead with breadcrumb, five numbered service cards in
	 * a 3 + 2 grid (second card dark), the "Not sure which fits?" photo
	 * band, then the paired CTA bands.
	 */
	public function delivery(): string {
		$contact  = $this->url( 'contact' );
		$services = array(
			array( '01', 'users', 'Temporary', 'Flexible, compliant workforce when you need to scale up fast. Vetted, paid and managed by us.', 'sector-manufacturing.webp' ),
			array( '02', 'user-check', 'Permanent', 'Full end-to-end search for permanent hires, sourced, screened and shortlisted to a brief.', 'poolhall-matt-desk.jpg' ),
			array( '03', 'trending-up', 'Scale', 'Volume and project recruitment for growth phases, multiple hires through one accountable partner.', 'sector-digital.webp' ),
			array( '04', 'calendar-clock', 'Pay Monthly', 'Spread the cost of a permanent placement across manageable monthly payments.', 'poolhall-office-culture.jpg' ),
			array( '05', 'hard-hat', 'On-Site', 'A fully managed on-site service for high-volume, single-location requirements.', 'sector-construction.webp' ),
		);

		$card = function ( array $svc, bool $dark ) use ( $contact ): string {
			[ $num, $icon, $title, $line, $img ] = $svc;
			$photo                               = $this->image( $img );
			return '<div class="svc' . ( $dark ? ' dark' : '' ) . '">'
				. '<div class="svc-photo">' . ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" loading="lazy" />' : '' ) . '</div>'
				. '<div class="num">' . esc_html( $num ) . '</div>'
				. '<span class="si">' . $this->icon( $icon ) . '</span>'
				. '<h3>' . esc_html( $title ) . '</h3>'
				. '<p>' . esc_html( $line ) . '</p>'
				. '<a class="btn-text" href="' . esc_url( $contact ) . '">Enquire ' . $this->icon( 'arrow-right' ) . '</a>'
				. '</div>';
		};

		$top = '';
		foreach ( array_slice( $services, 0, 3 ) as $i => $svc ) {
			$top .= $card( $svc, 1 === $i );
		}
		$bottom = '';
		foreach ( array_slice( $services, 3 ) as $svc ) {
			$bottom .= $card( $svc, false );
		}

		$photo = $this->sector_photo( 'con' );
		$head  = '<div class="pagehead photo">'
			. ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" />' : '' )
			. '<div class="container">'
			. '<div class="crumb">'
			. '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>' . $this->icon( 'chevron-right' )
			. '<a href="' . esc_url( $this->url( 'employers' ) ) . '">Employers</a>' . $this->icon( 'chevron-right' )
			. '<span>Delivery Options</span></div>'
			. '<p class="label on-dark"><span class="idx">//</span> For employers</p>'
			. '<h1>Recruitment Delivery Options</h1>'
			. '<p class="lead">Five ways to work together, all with the same care and quality. Whichever suits you best, you&rsquo;re in good hands.</p>'
			. '</div></div>';

		$grid = '<section class="section"><div class="container">'
			. '<div class="svc-grid">' . $top . '</div>'
			. '<div class="svc-grid svc-grid--two">' . $bottom . '</div>'
			. '</div></section>';

		$band_photo = $this->sector_photo( 'man' );
		$band       = '<section class="photoband">'
			. ( '' !== $band_photo ? '<img src="' . esc_url( $band_photo ) . '" alt="" />' : '' )
			. '<div class="container"><div class="pb-inner">'
			. '<p class="label on-dark"><span class="idx">//</span> Not sure which fits?</p>'
			. '<h2 class="h2 on-dark">Tell us the brief and we&rsquo;ll point you the right way.</h2>'
			. '<p>A single conversation is usually all it takes. We&rsquo;ll give you our honest take on what&rsquo;s likely to work for your role, your timeline and your budget.</p>'
			. '<div class="acts"><a class="btn btn-primary" href="' . esc_url( $contact ) . '">Talk to us ' . $this->icon( 'arrow-right' ) . '</a></div>'
			. '</div></div></section>';

		return '<div class="ph-v2page">' . $head . $grid . $band . '</div>' . $this->cta_bands();
	}

	/**
	 * Published job ids in every sector term that maps to the given key —
	 * the adapter creates Giig industry terms on demand, so one display
	 * sector can span several taxonomy terms.
	 *
	 * @return int[]
	 */
	private function sector_job_ids( string $key ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => JobPostType::TAX_SECTOR,
				'hide_empty' => true,
			)
		); // phpcs:ignore WordPress.WP.DeprecatedParameters.Get_termsParam2Found -- array form is current.
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$ids = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term && $this->term_matches_key( $term->name, $key ) ) {
				$ids[] = $term->term_id;
			}
		}
		if ( array() === $ids ) {
			return array();
		}
		$found = get_posts(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded archive query on a tiny CPT.
					array(
						'taxonomy' => JobPostType::TAX_SECTOR,
						'field'    => 'term_id',
						'terms'    => $ids,
					),
				),
			)
		);
		return array_map( 'intval', $found );
	}

	// ------------------------------------------------- shared page pieces --

	/** Prototype photo pagehead: gold edge, dark gradient, label, h1, lead. */
	private function pagehead( string $eyebrow, string $title, string $lead, string $photo_file, string $extra = '', string $crumb = '' ): string {
		$photo = $this->image( $photo_file );
		return '<div class="pagehead photo">'
			. ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" />' : '' )
			. '<div class="container">' . $crumb
			. '<p class="label on-dark"><span class="idx">//</span> ' . esc_html( $eyebrow ) . '</p>'
			. '<h1>' . wp_kses( $title, array() ) . '</h1>'
			. '<p class="lead">' . wp_kses( $lead, array() ) . '</p>'
			. $extra
			. '</div></div>';
	}

	/** Prototype PhotoBand: full-bleed photo, kicker label, h2, text, actions. */
	private function photoband( string $photo_file, string $kicker, string $title, string $text, string $actions ): string {
		$photo = $this->image( $photo_file );
		return '<section class="photoband">'
			. ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" />' : '' )
			. '<div class="container"><div class="pb-inner">'
			. '<p class="label on-dark"><span class="idx">//</span> ' . esc_html( $kicker ) . '</p>'
			. '<h2 class="h2 on-dark">' . wp_kses( $title, array() ) . '</h2>'
			. '<p>' . wp_kses( $text, array() ) . '</p>'
			. '<div class="acts">' . $actions . '</div>'
			. '</div></div></section>';
	}

	/**
	 * Bordered points grid (prototype .points).
	 *
	 * @param array<int,array{0:string,1:string}> $items Title/line pairs.
	 */
	private function points_grid( array $items ): string {
		$out = '<div class="points">';
		foreach ( $items as $i => [ $title, $line ] ) {
			$out .= '<div class="point"><div class="pi">// ' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</div>'
				. '<h3>' . wp_kses( $title, array() ) . '</h3><p>' . wp_kses( $line, array() ) . '</p></div>';
		}
		return $out . '</div>';
	}

	/** One prototype service card (.svc). */
	private function svc_card( string $num, string $icon, string $title, string $line, string $img, bool $dark, string $cta, string $href ): string {
		$photo = $this->image( $img );
		return '<div class="svc' . ( $dark ? ' dark' : '' ) . '">'
			. '<div class="svc-photo">' . ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" loading="lazy" />' : '' ) . '</div>'
			. ( '' !== $num ? '<div class="num">' . esc_html( $num ) . '</div>' : '' )
			. '<span class="si">' . $this->icon( $icon ) . '</span>'
			. '<h3>' . esc_html( $title ) . '</h3>'
			. '<p>' . wp_kses( $line, array() ) . '</p>'
			. '<a class="btn-text" href="' . esc_url( $href ) . '">' . esc_html( $cta ) . ' ' . $this->icon( 'arrow-right' ) . '</a>'
			. '</div>';
	}

	/** The five delivery services as the prototype's 3 + 2 grid. */
	private function svc_grids( string $cta, string $href ): string {
		$services = array(
			array( '01', 'users', 'Temporary', 'Flexible, compliant workforce when you need to scale up fast. Vetted, paid and managed by us.', 'sector-manufacturing.webp' ),
			array( '02', 'user-check', 'Permanent', 'Full end-to-end search for permanent hires, sourced, screened and shortlisted to a brief.', 'poolhall-matt-desk.jpg' ),
			array( '03', 'trending-up', 'Scale', 'Volume and project recruitment for growth phases, multiple hires through one accountable partner.', 'sector-digital.webp' ),
			array( '04', 'calendar-clock', 'Pay Monthly', 'Spread the cost of a permanent placement across manageable monthly payments.', 'poolhall-office-culture.jpg' ),
			array( '05', 'hard-hat', 'On-Site', 'A fully managed on-site service for high-volume, single-location requirements.', 'sector-construction.webp' ),
		);

		$top = '';
		foreach ( array_slice( $services, 0, 3 ) as $i => $svc ) {
			$top .= $this->svc_card( $svc[0], $svc[1], $svc[2], $svc[3], $svc[4], 1 === $i, $cta, $href );
		}
		$bottom = '';
		foreach ( array_slice( $services, 3 ) as $svc ) {
			$bottom .= $this->svc_card( $svc[0], $svc[1], $svc[2], $svc[3], $svc[4], false, $cta, $href );
		}
		return '<div class="svc-grid">' . $top . '</div><div class="svc-grid svc-grid--two">' . $bottom . '</div>';
	}

	// -------------------------------------------- embeddable partials --

	/** [poolhall_v2_points set="employers"] — bordered grid inside a builder page. */
	public function points( $atts ): string {
		$atts  = shortcode_atts( array( 'set' => 'employers' ), is_array( $atts ) ? $atts : array() );
		$sets  = array(
			'employers' => array(
				array( 'Hire better people', 'Specialist consultants who understand the role and the sector, so the shortlist is genuinely worth your time.' ),
				array( 'Better value for your budget', 'We take the time to get the fit right, so you spend less time on CVs that aren&rsquo;t quite there, and keep your cost-per-hire sensible.' ),
				array( 'A strategic partner', 'From a single hire to a whole team, we plan with you, not just react to a vacancy.' ),
				array( 'Honest, friendly advice', 'We&rsquo;ll always give you our genuine view on salary, market and timelines, gently and openly, so you can plan with confidence.' ),
			),
		);
		$items = $sets[ (string) $atts['set'] ] ?? $sets['employers'];
		return '<div class="ph-v2page is-embed">' . $this->points_grid( $items ) . '</div>';
	}

	/** [poolhall_v2_services] — the five ways grid inside a builder page. */
	public function services(): string {
		return '<div class="ph-v2page is-embed">' . $this->svc_grids( 'Learn more', $this->url( 'delivery-options' ) ) . '</div>';
	}

	// ------------------------------------------------- secondary template --

	/**
	 * Generic secondary page (prototype Secondary, §9.8): photo pagehead,
	 * four points, "Let's chat" photo band, CTA bands. BJA additionally
	 * keeps its flyer split (the real artwork, per the photography brief).
	 *
	 * @param array<string,string>|string $atts Shortcode atts: page=why-us|bespoke|hr|commitment|bja.
	 */
	public function secondary( $atts ): string {
		$atts = shortcode_atts( array( 'page' => 'why-us' ), is_array( $atts ) ? $atts : array() );
		$data = array(
			'why-us'     => array(
				'eyebrow' => 'For employers',
				'title'   => 'Why Us',
				'lead'    => 'What an independent, experienced team does a little differently, and why it matters to your hire.',
				'photo'   => 'poolhall-office-culture.jpg',
				'points'  => array(
					array( 'Senior by default', 'You&rsquo;ll work with experienced consultants who take the time to understand your brief.' ),
					array( 'Genuinely independent', 'No franchise targets pulling our focus away from your role.' ),
					array( 'Honest advice', 'Straight talk on salary, market and timelines.' ),
					array( 'Long-term relationships', 'We&rsquo;re here for your next hire, and the one after.' ),
				),
			),
			'bespoke'    => array(
				'eyebrow' => 'For employers',
				'title'   => 'Bespoke Search',
				'lead'    => 'Discreet, targeted search for the senior roles you really want to get right.',
				'photo'   => 'poolhall-matt-desk.jpg',
				'points'  => array(
					array( 'Mapped to a brief', 'We build a target list, not a job-board post.' ),
					array( 'Confidential', 'Sensitive and senior searches handled discreetly.' ),
					array( 'Thorough vetting', 'Deep screening before anyone reaches your desk.' ),
					array( 'Market intelligence', 'Real insight on who&rsquo;s out there and what they cost.' ),
				),
			),
			'bja'        => array(
				'eyebrow' => 'For employers',
				'title'   => 'Better Job Adverts',
				'lead'    => 'Keep hiring in-house and let us make your advert work harder. One fixed fee, your brand front and centre, a shortlist that&rsquo;s ready to interview. Pricing confirmed when you enquire.',
				'photo'   => 'sector-digital.webp',
				'points'  => array(
					array( 'One simple fixed fee', 'No surprises. Pricing confirmed when you enquire.' ),
					array( 'Multi-board reach', 'Your role, posted across the boards that matter.' ),
					array( 'Branded adverts', 'Written and presented properly, as your business.' ),
					array( 'CV screening', 'We sift so you only see the candidates worth your time.' ),
				),
			),
			'hr'         => array(
				'eyebrow' => 'For employers',
				'title'   => 'HR Services',
				'lead'    => 'Support that goes beyond the hire, for businesses without a full HR function.',
				'photo'   => 'poolhall-office-culture.jpg',
				'points'  => array(
					array( 'Onboarding support', 'Help your new starter land well.' ),
					array( 'Contracts & compliance', 'The paperwork done properly.' ),
					array( 'Retention advice', 'Keep the good people you worked hard to hire.' ),
					array( 'A partner on call', 'Someone to ask when an HR question lands.' ),
				),
			),
			'commitment' => array(
				'eyebrow' => 'Compliance',
				'title'   => 'Poolhall&rsquo;s Commitment',
				'lead'    => 'Doing recruitment ethically isn&rsquo;t a tagline, it&rsquo;s how we operate. Our policies, in plain English.',
				'photo'   => 'poolhall-matt-desk.jpg',
				'points'  => array(
					array( 'Modern Slavery', 'Our statement and the checks we run.' ),
					array( 'Complaints Procedure', 'How to raise a concern and what happens next.' ),
					array( 'Candidate Registration', 'Exactly how we handle your data and consent.' ),
					array( 'Equal opportunities', 'Fair process for every candidate, every time.' ),
				),
			),
		);
		$page = $data[ (string) $atts['page'] ] ?? $data['why-us'];

		$flyer = '';
		if ( 'bja' === $atts['page'] ) {
			$art   = $this->image( 'bja-flyer.png' );
			$flyer = '<section class="section section--white"><div class="container"><div class="split">'
				. '<div>'
				. '<p class="label"><span class="idx">//</span> How it works</p>'
				. '<h2 class="h2">We post. They apply. You hire.</h2>'
				. '<p class="lead" style="margin-top:14px">Your role written up properly, posted across the major boards under your brand, with every CV screened into an organised, interview-ready shortlist.</p>'
				. '<div class="rolegrid" style="margin-top:22px">'
				. '<div class="role">CV-Library</div><div class="role">Reed</div><div class="role">Totaljobs</div><div class="role">Indeed</div>'
				. '</div>'
				. '<div class="acts" style="display:flex;gap:12px;margin-top:26px;flex-wrap:wrap">'
				. '<a class="btn btn-primary" href="tel:01858457500">Call 01858 457500 ' . $this->icon( 'arrow-right' ) . '</a>'
				. '<a class="btn btn-ghost" href="https://betterjobadverts.co.uk" target="_blank" rel="noopener">betterjobadverts.co.uk</a>'
				. '</div></div>'
				. '<div class="media media--flyer">' . ( '' !== $art ? '<img src="' . esc_url( $art ) . '" alt="Better Job Adverts flyer" />' : '' ) . '</div>'
				. '</div></div></section>';
		}

		return '<div class="ph-v2page">'
			. $this->pagehead( $page['eyebrow'], $page['title'], $page['lead'], $page['photo'] )
			. '<section class="section"><div class="container">' . $this->points_grid( $page['points'] ) . '</div></section>'
			. $flyer
			. $this->photoband(
				'sector-manufacturing.webp',
				$page['eyebrow'],
				'Let&rsquo;s chat about what you need.',
				'A quick, friendly conversation with a senior consultant who&rsquo;ll really listen. We&rsquo;ll give you an honest, no-pressure view on how we can help.',
				'<a class="btn btn-primary" href="' . esc_url( $this->url( 'contact' ) ) . '">Get in touch ' . $this->icon( 'arrow-right' ) . '</a>'
			)
			. '</div>' . $this->cta_bands();
	}

	// ----------------------------------------------------- candidate hub --

	/** Candidate hub (prototype CandidateHub, §9.6). */
	public function candidates(): string {
		$actions = '<div class="acts" style="display:flex;gap:12px;margin-top:26px;flex-wrap:wrap">'
			. '<a class="btn btn-primary" href="' . esc_url( $this->url( 'jobs' ) ) . '">View jobs ' . $this->icon( 'arrow-right' ) . '</a>'
			. '<a class="btn btn-ghost-light" href="' . esc_url( $this->url( 'register' ) ) . '">Register your CV</a>'
			. '</div>';

		$steps_photo = $this->image( 'poolhall-office-culture.jpg' );
		$steps       = '<section class="section section--white"><div class="container"><div class="split">'
			. '<div>'
			. '<p class="label"><span class="idx">01 /</span> How it works</p>'
			. '<h2 class="h2">Three steps to your next role.</h2>'
			. '<ul class="checklist">'
			. '<li>' . $this->icon( 'circle-check' ) . '<span><strong>Register</strong> &mdash; send your CV and tell us what a good next move looks like.</span></li>'
			. '<li>' . $this->icon( 'circle-check' ) . '<span><strong>We match you</strong> &mdash; only to roles that genuinely fit, so every introduction feels worthwhile.</span></li>'
			. '<li>' . $this->icon( 'circle-check' ) . '<span><strong>We guide you</strong> &mdash; interview prep and honest feedback through to your first day.</span></li>'
			. '</ul>'
			. '<p style="margin-top:26px"><a class="btn btn-primary" href="' . esc_url( $this->url( 'register' ) ) . '">Register now ' . $this->icon( 'arrow-right' ) . '</a></p>'
			. '</div>'
			. '<div class="media">' . ( '' !== $steps_photo ? '<img src="' . esc_url( $steps_photo ) . '" alt="" />' : '' ) . '</div>'
			. '</div></div></section>';

		$guides = array(
			array( 'clipboard-list', 'Registration Guide', 'What to expect when you register and how we&rsquo;ll work with you.', 'registration-guide', 'sector-digital.webp' ),
			array( 'messages-square', 'Interview Tips', 'Practical prep that actually helps you land the role.', 'interview-tips', 'poolhall-office.jpg' ),
			array( 'file-text', 'CV Tips', 'Make your experience count, straight from the consultants who read CVs all day.', 'cv-tips', 'poolhall-story.jpg' ),
		);
		$cards  = '';
		foreach ( $guides as [ $icon, $title, $line, $slug, $img ] ) {
			$cards .= $this->svc_card( '', $icon, $title, $line, $img, false, 'Read guide', $this->url( $slug ) );
		}
		$guides_html = '<section class="section"><div class="container">'
			. '<div class="section-head center"><div>'
			. '<p class="label"><span class="idx">02 /</span> Guides &amp; advice</p>'
			. '<h2 class="h2">Help when you need it</h2>'
			. '</div></div>'
			. '<div class="svc-grid">' . $cards . '</div>'
			. '</div></section>';

		return '<div class="ph-v2page">'
			. $this->pagehead(
				'For candidates',
				'Find work that fits.',
				'Register once and we&rsquo;ll match you to roles worth your time, with honest advice the whole way through.',
				'poolhall-matt-desk.jpg',
				$actions
			)
			. $steps . $guides_html
			. '</div>' . $this->cta_bands();
	}

	// -------------------------------------------------------- about page --

	/** About (prototype screen-about.jsx): story + stats + collage, values, mission band, team teaser. */
	public function about(): string {
		$stats = '<div class="stats">'
			. '<div class="stat"><div class="sv">30<span>yrs</span></div><div class="sl">Combined experience</div></div>'
			. '<div class="stat"><div class="sv">5.0</div><div class="sl">Average Google rating</div></div>'
			. '<div class="stat"><div class="sv">3</div><div class="sl">Specialist sectors</div></div>'
			. '<div class="stat"><div class="sv">2021</div><div class="sl">Independent since</div></div>'
			. '</div>';

		$c1      = $this->image( 'poolhall-office-culture.jpg' );
		$c2      = $this->image( 'poolhall-office.jpg' );
		$c3      = $this->image( 'sector-manufacturing.webp' );
		$collage = '<div class="media-collage">'
			. '<div class="m tall">' . ( '' !== $c1 ? '<img src="' . esc_url( $c1 ) . '" alt="" />' : '' ) . '</div>'
			. '<div class="m">' . ( '' !== $c2 ? '<img src="' . esc_url( $c2 ) . '" alt="" />' : '' ) . '</div>'
			. '<div class="m">' . ( '' !== $c3 ? '<img src="' . esc_url( $c3 ) . '" alt="" />' : '' ) . '</div>'
			. '</div>';

		$story = '<section class="section section--white"><div class="container"><div class="split">'
			. '<div>'
			. '<p class="label"><span class="idx">01 /</span> Our story</p>'
			. '<h2 class="h2">Independent, by choice.</h2>'
			. '<p class="lead" style="margin-top:14px">Poolhall was founded in 2021 by Matthew Tonks to show recruitment can be done thoughtfully, without the pushy tactics and numbers games the industry is sometimes known for.</p>'
			. '<p class="ph-v2p">Being independent means we answer to our clients and candidates, not a franchise target. Run from Grosvenor House on St Pauls Square in Birmingham, we&rsquo;ve kept our roots in the West Midlands while helping people find roles the length of the country.</p>'
			. '<p class="ph-v2p">With around 30 years of combined experience across our three sectors, we know the work, the markets and the people, and we treat every introduction like it matters. Because it does.</p>'
			. $stats
			. '</div>'
			. $collage
			. '</div></div></section>';

		$values = '<section class="section"><div class="container">'
			. '<div class="section-head center"><div>'
			. '<p class="label"><span class="idx">02 /</span> What we stand for</p>'
			. '<h2 class="h2">Four things we won&rsquo;t compromise on</h2>'
			. '<p class="lead" style="margin-top:14px">The same principles guide every call, every shortlist and every placement, whether you&rsquo;re hiring or looking for your next role.</p>'
			. '</div></div>'
			. $this->points_grid(
				array(
					array( 'Honest, always', 'Straight advice on salary, market and timelines, even when it isn&rsquo;t what you were hoping to hear. No spin, no pressure.' ),
					array( 'Ethical by default', 'Doing recruitment the right way isn&rsquo;t a tagline, it&rsquo;s how we operate, from how we handle your data to how we represent you.' ),
					array( 'Genuinely personable', 'Independent means a real conversation with someone who picks up the phone, listens properly and remembers who you are.' ),
					array( 'Specialist, not generalist', 'Three sectors we know inside out, so the conversation starts in the right place and the introductions actually fit.' ),
				)
			)
			. '</div></section>';

		$mission = $this->photoband(
			'sector-manufacturing.webp',
			'Our mission',
			'State-of-the-art technology, traditional principles.',
			'We pair modern recruitment tools with old-fashioned values, honesty, hard work and real relationships, to match incredible roles with amazing people. That&rsquo;s the whole job, and we take it seriously.',
			'<a class="btn btn-primary" href="' . esc_url( $this->url( 'contact' ) ) . '">Get in touch ' . $this->icon( 'arrow-right' ) . '</a>'
		);

		$people = array(
			array( 'team-matthew.jpg', 'Founder', 'Matthew Tonks', array( 'Construction', 'Manufacturing' ) ),
			array( 'team-jay.jpg', 'Recruitment Partner', 'Jay Thornton', array( 'Marketing & PR', 'Digital' ) ),
			array( 'team-sam.jpg', 'Marketing Specialist', 'Sam Ogle', array( 'Brand', 'Content' ) ),
		);
		$tcards = '';
		foreach ( $people as [ $img, $role, $name, $tags ] ) {
			$photo = $this->image( $img );
			$chips = '';
			foreach ( $tags as $tag ) {
				$chips .= '<span class="ttag">' . esc_html( $tag ) . '</span>';
			}
			$tcards .= '<div class="tcard">'
				. '<div class="tphoto">' . ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" />' : '' ) . '</div>'
				. '<p class="trole">' . esc_html( strtoupper( $role ) ) . '</p>'
				. '<h3>' . esc_html( $name ) . '</h3>'
				. '<div class="ttags">' . $chips . '</div>'
				. '</div>';
		}
		$team = '<section class="section section--white"><div class="container">'
			. '<div class="section-head"><div>'
			. '<p class="label"><span class="idx">03 /</span> The people behind it</p>'
			. '<h2 class="h2">A small team you&rsquo;ll actually deal with</h2>'
			. '</div><a class="btn btn-ghost" href="' . esc_url( $this->url( 'join-our-team' ) ) . '">Meet the team ' . $this->icon( 'arrow-right' ) . '</a></div>'
			. '<div class="team-grid">' . $tcards . '</div>'
			. '</div></section>';

		return '<div class="ph-v2page">'
			. $this->pagehead(
				'About Poolhall',
				'Recruitment, done with care.',
				'An independent West Midlands agency built in 2021 to prove that hiring can be honest, thoughtful and genuinely personable, across Construction, Manufacturing and Digital.',
				'poolhall-matthew-portrait.jpg'
			)
			. $story . $values . $mission . $team
			. '</div>' . $this->cta_bands();
	}

	// ----------------------------------------------------- register page --

	/** Register-your-CV page shell: prototype pagehead + centred form column. */
	public function register_page(): string {
		return '<div class="ph-v2page">'
			. $this->pagehead(
				'For candidates',
				'Register your CV',
				'One quick form and we&rsquo;ll be in touch the moment something right comes along. We&rsquo;ll always treat your details with care.',
				'poolhall-matt-desk.jpg'
			)
			. '<section class="section"><div class="container" style="max-width:820px">'
			. do_shortcode( '[poolhall_register_form]' )
			. '</div></section>'
			. '</div>';
	}

	// -------------------------------------------------------- blog index --

	/** Blog index (prototype Blog, §9.9): photo pagehead + card grid or empty state. */
	public function blog_index(): string {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 12,
			)
		);

		if ( array() === $posts ) {
			$body = '<div class="empty-card">'
				. '<h2 class="h2" style="font-size:1.4rem">Articles are on their way</h2>'
				. '<p>We&rsquo;re moving our guides and articles over. In the meantime, our team is always happy to share advice directly, just get in touch.</p>'
				. '<div class="acts" style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:22px">'
				. '<a class="btn btn-primary" href="' . esc_url( $this->url( 'jobs' ) ) . '">Browse live jobs</a>'
				. '<a class="btn btn-ghost" href="' . esc_url( $this->url( 'contact' ) ) . '">Contact us</a>'
				. '</div></div>';
		} else {
			$cards = '';
			foreach ( $posts as $post ) {
				$thumb  = (string) get_the_post_thumbnail_url( $post, 'large' );
				$cards .= '<a class="bcard" href="' . esc_url( (string) get_permalink( $post ) ) . '">'
					. '<span class="bphoto">' . ( '' !== $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" />' : '' ) . '</span>'
					. '<span class="bbody">'
					. '<span class="bmeta">' . esc_html( (string) get_the_date( 'd M Y', $post ) ) . '</span>'
					. '<span class="btitle">' . esc_html( get_the_title( $post ) ) . '</span>'
					. '<span class="bex">' . esc_html( wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 18, '&hellip;' ) ) . '</span>'
					. '</span></a>';
			}
			$body = '<div class="blog-grid">' . $cards . '</div>';
		}

		return '<div class="ph-v2page">'
			. $this->pagehead(
				'News & insight',
				'The Poolhall blog',
				'Practical advice on hiring and finding work, from a team that does it every day.',
				'poolhall-matt-desk.jpg'
			)
			. '<section class="section"><div class="container">' . $body . '</div></section>'
			. '</div>' . $this->cta_bands();
	}

	// ------------------------------------------------------ contact page --

	/** Contact (prototype screen-contact.jsx): info cards + photo beside the enquiry form. */
	public function contact_page(): string {
		$grosvenor = $this->image( 'poolhall-grosvenor-house.jpg' );

		$info = '<div class="cinfo">'
			. '<div class="crow"><span class="ci">' . $this->icon( 'phone' ) . '</span><div>'
			. '<p class="ce">Call us</p><p class="cv"><a href="tel:01215163000">0121 516 3000</a></p><p class="cs">Mon&ndash;Fri, 8:30&ndash;5:30</p></div></div>'
			. '<div class="crow"><span class="ci">' . $this->icon( 'mail' ) . '</span><div>'
			. '<p class="ce">Email</p><p class="cv"><a href="mailto:jobs@poolhallrecruitment.co.uk">jobs@poolhallrecruitment.co.uk</a></p><p class="cs">We reply within one working day</p></div></div>'
			. '<div class="crow"><span class="ci">' . $this->icon( 'map-pin' ) . '</span><div>'
			. '<p class="ce">Visit</p><p class="cv">Grosvenor House, 11 St Pauls Square</p><p class="cs">Birmingham B3 1RB &middot; the black door on the corner, five minutes from St Paul&rsquo;s tram stop</p></div></div>'
			. '<div class="cmap">' . ( '' !== $grosvenor ? '<img src="' . esc_url( $grosvenor ) . '" alt="Grosvenor House, 11 St Pauls Square" />' : '' ) . '</div>'
			. '</div>';

		return '<div class="ph-v2page">'
			. $this->pagehead(
				'Contact',
				'Let&rsquo;s talk',
				'Choose whichever way suits you best and we&rsquo;ll make sure the right person gets back to you quickly. There&rsquo;s always a real person here to help.',
				'poolhall-office-culture.jpg'
			)
			. '<section class="section"><div class="container"><div class="contact-grid">'
			. $info
			. '<div class="cform">' . do_shortcode( '[poolhall_enquiry_form]' ) . '</div>'
			. '</div></div></section>'
			. '</div>';
	}


	// -------------------------------------------------------- job single --

	/**
	 * Single-job template (prototype screen-jobsingle.jsx, §9.4): photo
	 * pagehead with breadcrumb + sector tag + mono meta row, job body
	 * beside the sticky apply aside (salary, meta rows, apply, save,
	 * "or call"), similar roles, and the mobile apply bar.
	 */
	public function job_single(): string {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || JobPostType::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$sectors  = wp_get_object_terms( $post->ID, JobPostType::TAX_SECTOR, array( 'fields' => 'names' ) );
		$sector   = is_array( $sectors ) && array() !== $sectors ? (string) $sectors[0] : '';
		$key      = $this->sector_key( $sector );
		$photo    = $this->sector_photo( $key );
		$featured = '' !== (string) get_post_meta( $post->ID, 'is_featured', true );
		$location = (string) get_post_meta( $post->ID, 'location_display', true );
		$mode     = (string) get_post_meta( $post->ID, 'work_mode_raw', true );
		$salary   = (string) get_post_meta( $post->ID, 'salary_display', true );
		$types    = wp_get_object_terms( $post->ID, JobPostType::TAX_JOB_TYPE, array( 'fields' => 'names' ) );
		$type     = is_array( $types ) && array() !== $types ? (string) $types[0] : '';
		$ref      = (string) get_post_meta( $post->ID, 'source_job_id', true );
		$posted   = (string) get_the_date( 'j M Y', $post );

		$meta_row = '<div class="jmeta">';
		if ( '' !== $location ) {
			$meta_row .= '<span>' . $this->icon( 'map-pin' ) . esc_html( $location ) . '</span>';
		}
		if ( '' !== $mode ) {
			$meta_row .= '<span>' . $this->icon( 'building' ) . esc_html( $mode ) . '</span>';
		}
		if ( '' !== $salary ) {
			$meta_row .= '<span>' . $this->icon( 'banknote' ) . esc_html( $salary ) . '</span>';
		}
		$meta_row .= '<span>' . $this->icon( 'clock' ) . 'Posted ' . esc_html( $posted ) . '</span></div>';

		$crumb = '<div class="crumb">'
			. '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>' . $this->icon( 'chevron-right' )
			. '<a href="' . esc_url( $this->url( 'jobs' ) ) . '">Jobs</a>' . $this->icon( 'chevron-right' )
			. '<span>' . esc_html( '' !== $sector ? $sector : get_the_title( $post ) ) . '</span></div>';

		$head = '<div class="pagehead photo">'
			. ( '' !== $photo ? '<img src="' . esc_url( $photo ) . '" alt="" />' : '' )
			. '<div class="container">' . $crumb
			. '<div class="jtags">'
			. ( '' !== $sector ? '<span class="jc-tag ' . esc_attr( $key ) . '">' . esc_html( $sector ) . '</span>' : '' )
			. ( $featured ? '<span class="jfeat">' . $this->icon( 'star' ) . ' Featured</span>' : '' )
			. '</div>'
			. '<h1 style="max-width:20ch">' . esc_html( get_the_title( $post ) ) . '</h1>'
			. $meta_row
			. '</div></div>';

		// Salary line splits into the big figure and the mono qualifier.
		$sal_main = $salary;
		$sal_per  = '' !== $type ? strtoupper( $type ) : '';
		if ( str_contains( $salary, ' / ' ) ) {
			[ $sal_main, $per ] = explode( ' / ', $salary, 2 );
			$sal_per            = strtoupper( 'per ' . $per . ( '' !== $type ? ' · ' . $type : '' ) );
		}

		$rows = '';
		foreach ( array(
			array( 'Sector', $sector ),
			array( 'Location', $location ),
			array( 'Work mode', $mode ),
			array( 'Type', $type ),
			array( 'Reference', '' !== $ref ? '#' . $ref : '' ),
		) as [ $label, $value ] ) {
			if ( '' === $value ) {
				continue;
			}
			$rows .= '<div class="r"><span>' . esc_html( $label ) . '</span><span>' . esc_html( $value ) . '</span></div>';
		}

		$aside = '<aside class="apply-aside">'
			. ( '' !== $sal_main ? '<div class="sal">' . esc_html( $sal_main ) . '</div>' : '' )
			. ( '' !== $sal_per ? '<div class="per">' . esc_html( $sal_per ) . '</div>' : '' )
			. '<div class="aside-meta">' . $rows . '</div>'
			. do_shortcode( '[poolhall_apply_button]' )
			. '<button type="button" class="btn btn-ghost btn-block save-job" data-ph-save data-job-id="' . esc_attr( (string) $post->ID ) . '">Save job</button>'
			. '<div class="orcall">or call 0121 516 3000</div>'
			. '</aside>';

		$body = '<div class="job-body">' . wpautop( wp_kses_post( $post->post_content ) ) . '</div>';

		$similar     = '';
		$similar_ids = array_values( array_diff( $this->sector_job_ids( $key ), array( $post->ID ) ) );
		$similar_ids = array_slice( $similar_ids, 0, 2 );
		if ( array() !== $similar_ids && '' !== $sector ) {
			$cards = '';
			foreach ( $similar_ids as $sid ) {
				$cards .= $this->job_card( $sid );
			}
			$similar = '<section style="padding:0 0 96px">'
				. '<div class="section-head"><div>'
				. '<p class="label"><span class="idx">//</span> Similar roles</p>'
				. '<h2 class="h2">More in ' . esc_html( $sector ) . '</h2>'
				. '</div><a class="btn btn-ghost" href="' . esc_url( $this->url( 'jobs' ) ) . '">All jobs ' . $this->icon( 'arrow-right' ) . '</a></div>'
				. '<div class="secjobs">' . $cards . '</div>'
				. '</section>';
		}

		$mobile = '<div class="mobile-apply"><div class="ms">'
			. ( '' !== $sal_main ? '<div class="v">' . esc_html( $sal_main ) . '</div>' : '' )
			. '<div class="l">' . esc_html( trim( $type . ( '' !== $type && '' !== $location ? ' · ' : '' ) . $location ) ) . '</div>'
			. '</div>' . do_shortcode( '[poolhall_apply_button]' ) . '</div>';

		return '<div class="ph-v2page">' . $head
			. '<div class="container"><div class="job-layout">' . $body . $aside . '</div>' . $similar . '</div>'
			. $mobile
			. '</div>';
	}

	// --------------------------------------------------- jobs archive head --

	/** Jobs archive pagehead (prototype Jobs screen: photo header). */
	public function jobs_head(): string {
		return '<div class="ph-v2page">'
			. $this->pagehead(
				'Jobs',
				'Find your next role',
				'Live roles across Construction, Manufacturing and Digital/Marketing in the West Midlands and beyond.',
				'sector-construction.webp'
			)
			. '</div>';
	}

	// --------------------------------------------------------- legal head --

	/**
	 * Legal pagehead (prototype Legal template): plain navy, breadcrumb,
	 * title, lead and the mono updated line.
	 *
	 * @param array<string,string>|string $atts Shortcode atts: title, lead, updated.
	 */
	public function legal_head( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'   => 'Legal',
				'lead'    => '',
				'updated' => '',
				'crumb'   => 'Legal',
			),
			is_array( $atts ) ? $atts : array()
		);
		return '<div class="ph-v2page">'
			. '<div class="pagehead"><div class="container">'
			. '<div class="crumb">'
			. '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>' . $this->icon( 'chevron-right' )
			. '<span>' . esc_html( (string) $atts['crumb'] ) . '</span>' . $this->icon( 'chevron-right' )
			. '<span>' . esc_html( (string) $atts['title'] ) . '</span></div>'
			. '<h1>' . esc_html( (string) $atts['title'] ) . '</h1>'
			. ( '' !== $atts['lead'] ? '<p class="lead">' . esc_html( (string) $atts['lead'] ) . '</p>' : '' )
			. ( '' !== $atts['updated'] ? '<p class="jupdated">' . esc_html( (string) $atts['updated'] ) . '</p>' : '' )
			. '</div></div></div>';
	}

	// ---------------------------------------------------------- CTA bands --

	public function cta_bands(): string {
		return '<div class="cta-bands ph-v2-ctas">'
			. '<div class="cta-band cand"><div class="inner">'
			. '<p class="ce">For candidates</p>'
			. '<h2>Looking for your next role?</h2>'
			. '<p>Register in a couple of minutes and we&rsquo;ll be in touch when something that fits comes along. No pressure, just a friendly heads-up.</p>'
			. '<div class="acts">'
			. '<a class="btn btn-primary" href="' . esc_url( $this->url( 'register' ) ) . '">Register now ' . $this->icon( 'arrow-right' ) . '</a>'
			. '<a class="btn btn-ghost-light" href="' . esc_url( $this->url( 'jobs' ) ) . '">View jobs</a>'
			. '</div></div></div>'
			. '<div class="cta-band emp"><div class="inner">'
			. '<p class="ce">For employers</p>'
			. '<h2>Looking to hire?</h2>'
			. '<p>Tell us who you&rsquo;re looking for and we&rsquo;ll put together a shortlist of people we think you&rsquo;ll be glad to meet.</p>'
			. '<div class="acts">'
			. '<a class="btn btn-dark" href="' . esc_url( $this->url( 'employers' ) . '#enquiry' ) . '">Enquire now ' . $this->icon( 'arrow-right' ) . '</a>'
			. '<a class="btn btn-ghost-light" href="tel:01215163000">Book a call</a>'
			. '</div></div></div>'
			. '</div>';
	}
}
