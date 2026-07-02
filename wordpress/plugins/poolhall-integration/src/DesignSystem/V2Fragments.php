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
			'phone'         => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3A19.5 19.5 0 0 1 5.1 13 19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z"/>',
			'mail'          => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
			'map-pin'       => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
			'briefcase'     => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
			'banknote'      => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
			'building'      => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
			'arrow-right'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
			'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
			'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
			'menu'          => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
			'x'             => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
			'star'          => '<path d="M11.5 2.6a.6.6 0 0 1 1 0l2.8 5.7 6.3.9a.6.6 0 0 1 .3 1l-4.5 4.4 1 6.3a.6.6 0 0 1-.8.6L12 18.6l-5.6 3a.6.6 0 0 1-.9-.7l1.1-6.3L2.1 10a.6.6 0 0 1 .3-1l6.3-.9z"/>',
			'clock'         => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'users'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
			'shield-check'  => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
			'linkedin'      => '<path fill="currentColor" stroke="none" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.07 2.07 0 1 1 0-4.13 2.07 2.07 0 0 1 0 4.13zM7.12 20.45H3.55V9h3.57zM22.22 0H1.77C.8 0 0 .78 0 1.74v20.51C0 23.21.8 24 1.77 24h20.45c.98 0 1.78-.79 1.78-1.75V1.74C24 .78 23.2 0 22.22 0z"/>',
			'instagram'     => '<rect width="20" height="20" x="2" y="2" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
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
			// Mobile drawer.
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
			. '</header>';
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
			. '</div></footer>';
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
