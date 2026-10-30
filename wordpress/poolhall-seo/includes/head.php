<?php
/**
 * What goes in <head>: the title, meta description, robots and social tags.
 */

defined( 'ABSPATH' ) || exit;

/** Another SEO plugin owns the head; stand back rather than print everything twice. */
function phseo_other_seo_plugin(): string {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'Yoast SEO';
	}
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		return 'Rank Math';
	}
	if ( defined( 'AIOSEO_VERSION' ) ) {
		return 'All in One SEO';
	}
	if ( defined( 'SEOPRESS_VERSION' ) ) {
		return 'SEOPress';
	}
	return '';
}

function phseo_active(): bool {
	return '' === phseo_other_seo_plugin();
}

/** Trim to a length at a word boundary, adding an ellipsis when cut. */
function phseo_trim( string $text, int $max ): string {
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, $max - 1 );
	$space = mb_strrpos( $cut, ' ' );
	if ( false !== $space && $space > $max * 0.6 ) {
		$cut = mb_substr( $cut, 0, $space );
	}
	return rtrim( $cut, " ,.;:-–—" ) . '…';
}

function phseo_site_name(): string {
	$name = trim( (string) phseo_setting( 'site_name' ) );
	return '' !== $name ? $name : (string) get_bloginfo( 'name' );
}

function phseo_separator(): string {
	return (string) phseo_setting( 'separator' );
}

function phseo_default_title( WP_Post $post ): string {
	if ( (int) get_option( 'page_on_front' ) === $post->ID ) {
		$home = trim( (string) phseo_setting( 'home_title' ) );
		return '' !== $home ? $home : phseo_site_name();
	}
	return wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) . ' ' . phseo_separator() . ' ' . phseo_site_name();
}

function phseo_title_for( WP_Post $post ): string {
	$custom = trim( (string) get_post_meta( $post->ID, PHSEO_META_TITLE, true ) );
	return '' !== $custom ? $custom : phseo_default_title( $post );
}

/**
 * A description written from the page itself: the excerpt if there is one,
 * otherwise the first proper paragraph of the content.
 */
function phseo_auto_description( WP_Post $post ): string {
	if ( (int) get_option( 'page_on_front' ) === $post->ID && '' !== trim( (string) phseo_setting( 'home_description' ) ) ) {
		return phseo_trim( (string) phseo_setting( 'home_description' ), 160 );
	}
	// Written for the blog posts migrated from Wix.
	$migrated = trim( (string) get_post_meta( $post->ID, '_poolhall_seo_description', true ) );
	if ( '' !== $migrated && mb_strlen( $migrated ) > 60 ) {
		return phseo_trim( $migrated, 160 );
	}
	if ( has_excerpt( $post ) ) {
		return phseo_trim( wp_strip_all_tags( $post->post_excerpt ), 158 );
	}
	// Elementor keeps a plain copy of each page in post_content that can lag
	// behind the layout, so read the layout itself when there is one.
	$from_builder = phseo_elementor_text( $post->ID );
	if ( '' !== $from_builder ) {
		return phseo_trim( $from_builder, 158 );
	}
	$html = strip_shortcodes( (string) $post->post_content );
	if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', $html, $m ) ) {
		foreach ( $m[1] as $para ) {
			$text = trim( html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>#i', ' ', $para ) ), ENT_QUOTES, 'UTF-8' ) );
			if ( mb_strlen( $text ) >= 70 ) {
				return phseo_trim( $text, 158 );
			}
		}
	}
	$text = trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	return '' !== $text ? phseo_trim( $text, 158 ) : '';
}

function phseo_description_for( WP_Post $post ): string {
	$custom = trim( (string) get_post_meta( $post->ID, PHSEO_META_DESCRIPTION, true ) );
	return '' !== $custom ? $custom : phseo_auto_description( $post );
}

/** The share image as [url, width, height, alt], or null. Raster only: social sites ignore SVG. */
function phseo_image_for( ?WP_Post $post ): ?array {
	$ids = array();
	if ( $post ) {
		$ids[] = (int) get_post_meta( $post->ID, PHSEO_META_IMAGE, true );
		$ids[] = (int) get_post_thumbnail_id( $post );
	}
	$ids[] = (int) phseo_setting( 'default_image' );
	foreach ( array_filter( $ids ) as $id ) {
		if ( 'image/svg+xml' === get_post_mime_type( $id ) ) {
			continue;
		}
		$src = wp_get_attachment_image_src( $id, 'large' );
		if ( $src ) {
			$full = wp_get_attachment_image_src( $id, 'full' );
			$use  = ( $full && $full[1] <= 2400 ) ? $full : $src;
			return array( $use[0], (int) $use[1], (int) $use[2], (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		}
	}
	return null;
}

/** The post the current request is about, if it is about a single one. */
function phseo_current_post(): ?WP_Post {
	if ( is_singular() || ( is_front_page() && get_option( 'page_on_front' ) ) ) {
		$post = get_queried_object();
		return $post instanceof WP_Post ? $post : null;
	}
	return null;
}

add_filter( 'pre_get_document_title', static function ( $title ) {
	if ( ! phseo_active() || is_admin() ) {
		return $title;
	}
	$post = phseo_current_post();
	return $post ? phseo_title_for( $post ) : $title;
}, 20 );

add_filter( 'document_title_separator', static fn( $sep ) => phseo_active() ? phseo_separator() : $sep );
add_filter( 'document_title_parts', static function ( $parts ) {
	if ( phseo_active() && isset( $parts['site'] ) ) {
		$parts['site'] = phseo_site_name();
	}
	if ( phseo_active() && isset( $parts['title'] ) && is_front_page() && ! get_option( 'page_on_front' ) ) {
		$parts['title'] = phseo_site_name();
	}
	return $parts;
} );

add_filter( 'wp_robots', static function ( array $robots ): array {
	$post = phseo_active() ? phseo_current_post() : null;
	if ( $post && get_post_meta( $post->ID, PHSEO_META_NOINDEX, true ) ) {
		$robots['noindex'] = true;
		unset( $robots['index'] );
		if ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
	}
	if ( phseo_active() && ( is_search() || is_404() ) ) {
		$robots['noindex'] = true;
	}
	if ( phseo_active() && ! isset( $robots['noindex'] ) && '1' === (string) get_option( 'blog_public' ) ) {
		$robots['max-image-preview'] = 'large';
	}
	return $robots;
} );

// The theme and the blog-migration plugin each print a description of their
// own; this plugin is now the one source, so switch theirs off.
add_filter( 'hello_elementor_description_meta_tag', static fn( $on ) => phseo_active() ? false : $on, 99 );
add_action( 'wp', static function (): void {
	if ( phseo_active() ) {
		remove_action( 'wp_head', 'Poolhall\\BlogMigration\\print_metadata', 8 );
	}
} );

add_action( 'wp_head', static function (): void {
	if ( ! phseo_active() ) {
		return;
	}
	$post  = phseo_current_post();
	$title = $post ? phseo_title_for( $post ) : wp_get_document_title();
	$desc  = $post ? phseo_description_for( $post ) : ( is_front_page() ? (string) phseo_setting( 'home_description' ) : '' );
	if ( ! $desc && ( is_category() || is_tag() || is_tax() || is_post_type_archive() ) ) {
		$desc = phseo_trim( wp_strip_all_tags( get_the_archive_description() ), 158 );
	}
	$url   = $post ? get_permalink( $post ) : ( is_front_page() ? home_url( '/' ) : '' );
	$image = phseo_image_for( $post );
	$is_article = $post && 'post' === $post->post_type;

	$tags = array();
	if ( $desc ) {
		$tags[] = array( 'name', 'description', $desc );
	}
	$tags[] = array( 'property', 'og:locale', 'en_GB' );
	$tags[] = array( 'property', 'og:type', $is_article ? 'article' : 'website' );
	$tags[] = array( 'property', 'og:site_name', phseo_site_name() );
	$tags[] = array( 'property', 'og:title', $title );
	if ( $desc ) {
		$tags[] = array( 'property', 'og:description', $desc );
	}
	if ( $url ) {
		$tags[] = array( 'property', 'og:url', $url );
	}
	if ( $image ) {
		$tags[] = array( 'property', 'og:image', $image[0] );
		$tags[] = array( 'property', 'og:image:width', (string) $image[1] );
		$tags[] = array( 'property', 'og:image:height', (string) $image[2] );
		if ( $image[3] ) {
			$tags[] = array( 'property', 'og:image:alt', $image[3] );
		}
	}
	if ( $is_article ) {
		$tags[] = array( 'property', 'article:published_time', (string) get_post_time( DATE_W3C, true, $post ) );
		$tags[] = array( 'property', 'article:modified_time', (string) get_post_modified_time( DATE_W3C, true, $post ) );
	}
	$tags[] = array( 'name', 'twitter:card', $image ? 'summary_large_image' : 'summary' );

	echo "\n<!-- Poolhall SEO -->\n";
	foreach ( $tags as [ $attr, $key, $value ] ) {
		printf( '<meta %s="%s" content="%s">' . "\n", $attr, esc_attr( $key ), esc_attr( $value ) );
	}
}, 2 );

/** The first paragraph-length piece of text in an Elementor layout, in page order. */
function phseo_elementor_text( int $post_id ): string {
	if ( 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
		return '';
	}
	$data = json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
	if ( ! is_array( $data ) ) {
		return '';
	}
	$found = '';
	$walk  = static function ( array $elements ) use ( &$walk, &$found ): void {
		foreach ( $elements as $el ) {
			if ( '' !== $found || ! is_array( $el ) ) {
				return;
			}
			$type = (string) ( $el['widgetType'] ?? '' );
			if ( in_array( $type, array( 'text-editor', 'e-paragraph', 'theme-post-content' ), true ) || 'e-paragraph' === ( $el['elType'] ?? '' ) ) {
				foreach ( array( 'editor', 'paragraph' ) as $key ) {
					$value = $el['settings'][ $key ] ?? '';
					if ( is_array( $value ) ) {
						$value = is_string( $value['value'] ?? null ) ? $value['value'] : '';
					}
					$text = trim( html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>|</(p|li|div|h[1-6])>#i', ' ', (string) $value ) ), ENT_QUOTES, 'UTF-8' ) );
					if ( mb_strlen( $text ) >= 70 ) {
						$found = $text;
						return;
					}
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( $data );
	return preg_replace( '/\s+/u', ' ', $found );
}
