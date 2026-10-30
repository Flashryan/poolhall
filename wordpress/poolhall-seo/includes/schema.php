<?php
/**
 * Structured data (JSON-LD) for Google: the organisation and website on the
 * homepage, and an article on each blog post. Job pages already carry
 * JobPosting markup from the jobs integration, so nothing is added there.
 */

defined( 'ABSPATH' ) || exit;

function phseo_org_id(): string {
	return home_url( '/#organization' );
}

function phseo_org_logo(): ?array {
	foreach ( array( (int) phseo_setting( 'org_logo' ), (int) get_option( 'site_icon' ) ) as $id ) {
		if ( $id && 'image/svg+xml' !== get_post_mime_type( $id ) ) {
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( $src ) {
				return array( '@type' => 'ImageObject', 'url' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
			}
		}
	}
	return null;
}

function phseo_org_schema(): array {
	$s   = phseo_settings();
	$org = array(
		'@type' => $s['org_type'],
		'@id'   => phseo_org_id(),
		'name'  => $s['org_name'] ?: phseo_site_name(),
		'url'   => home_url( '/' ),
	);
	$logo = phseo_org_logo();
	if ( $logo ) {
		$org['logo']  = $logo;
		$org['image'] = $logo['url'];
	}
	if ( $s['phone'] ) {
		$org['telephone'] = $s['phone'];
	}
	if ( $s['email'] ) {
		$org['email'] = $s['email'];
	}
	if ( $s['street'] || $s['locality'] || $s['postcode'] ) {
		$org['address'] = array_filter( array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $s['street'],
			'addressLocality' => $s['locality'],
			'addressRegion'   => $s['region'],
			'postalCode'      => $s['postcode'],
			'addressCountry'  => $s['country'],
		) );
	}
	$same = array_values( array_filter( preg_split( '/\R/', (string) $s['same_as'] ) ) );
	if ( $same ) {
		$org['sameAs'] = $same;
	}
	return $org;
}

function phseo_article_schema( WP_Post $post ): array {
	$url  = get_permalink( $post );
	$data = array(
		'@type'            => 'BlogPosting',
		'@id'              => $url . '#article',
		'mainEntityOfPage' => $url,
		'headline'         => wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ),
		'description'      => phseo_description_for( $post ),
		// Posts migrated from Wix keep their original publication time.
		'datePublished'    => (string) ( get_post_meta( $post->ID, '_poolhall_blog_source_published_iso', true ) ?: get_post_time( DATE_W3C, true, $post ) ),
		'dateModified'     => (string) get_post_modified_time( DATE_W3C, true, $post ),
		'author'           => array( '@type' => 'Organization', 'name' => (string) ( get_post_meta( $post->ID, '_poolhall_blog_author', true ) ?: phseo_setting( 'org_name' ) ), 'url' => home_url( '/' ) ),
		'publisher'        => array( '@id' => phseo_org_id() ),
		'inLanguage'       => 'en-GB',
	);
	$image = phseo_image_for( $post );
	if ( $image ) {
		$data['image'] = array( '@type' => 'ImageObject', 'url' => $image[0], 'width' => $image[1], 'height' => $image[2] );
	}
	return $data;
}

add_action( 'wp_head', static function (): void {
	if ( ! phseo_active() ) {
		return;
	}
	$graph = array();
	if ( is_front_page() ) {
		$graph[] = phseo_org_schema();
		$graph[] = array(
			'@type'     => 'WebSite',
			'@id'       => home_url( '/#website' ),
			'url'       => home_url( '/' ),
			'name'      => phseo_site_name(),
			'publisher' => array( '@id' => phseo_org_id() ),
			'inLanguage' => 'en-GB',
		);
	} elseif ( is_singular( 'post' ) ) {
		$graph[] = phseo_article_schema( get_queried_object() );
		$graph[] = phseo_org_schema();
	}
	if ( ! $graph ) {
		return;
	}
	$json = wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
	echo '<script type="application/ld+json">' . $json . "</script>\n";
}, 3 );
