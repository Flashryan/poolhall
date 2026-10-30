<?php
/**
 * Site-wide SEO settings, stored in one option.
 */

defined( 'ABSPATH' ) || exit;

const PHSEO_SETTINGS = 'phseo_settings';

function phseo_setting_defaults(): array {
	return array(
		'site_name'        => get_bloginfo( 'name' ),
		'separator'        => '|',
		'home_title'       => '',
		'home_description' => '',
		'default_image'    => 0,
		'org_type'         => 'Organization',
		'org_name'         => get_bloginfo( 'name' ),
		'org_logo'         => 0,
		'phone'            => '',
		'email'            => '',
		'street'           => '',
		'locality'         => '',
		'region'           => '',
		'postcode'         => '',
		'country'          => 'GB',
		'same_as'          => '',
	);
}

function phseo_settings(): array {
	$stored = get_option( PHSEO_SETTINGS, array() );
	return array_merge( phseo_setting_defaults(), is_array( $stored ) ? $stored : array() );
}

function phseo_setting( string $key ) {
	return phseo_settings()[ $key ] ?? null;
}

/** Organisation types offered in settings; all are valid schema.org types. */
function phseo_org_types(): array {
	return array(
		'Organization'     => 'Organisation',
		'LocalBusiness'    => 'Local business',
		'EmploymentAgency' => 'Employment agency',
		'ProfessionalService' => 'Professional service',
	);
}

function phseo_sanitize_settings( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$out   = phseo_setting_defaults();
	foreach ( array( 'site_name', 'separator', 'home_title', 'org_name', 'phone', 'street', 'locality', 'region', 'postcode', 'country' ) as $key ) {
		$out[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ?? '' ) );
	}
	$out['home_description'] = sanitize_textarea_field( wp_unslash( $input['home_description'] ?? '' ) );
	$out['email']            = sanitize_email( wp_unslash( $input['email'] ?? '' ) );
	$out['default_image']    = absint( $input['default_image'] ?? 0 );
	$out['org_logo']         = absint( $input['org_logo'] ?? 0 );
	$out['org_type']         = array_key_exists( $input['org_type'] ?? '', phseo_org_types() ) ? $input['org_type'] : 'Organization';
	$urls = array();
	foreach ( preg_split( '/\R/', (string) wp_unslash( $input['same_as'] ?? '' ) ) as $line ) {
		$url = esc_url_raw( trim( $line ) );
		if ( $url ) {
			$urls[] = $url;
		}
	}
	$out['same_as'] = implode( "\n", array_unique( $urls ) );
	if ( '' === $out['separator'] ) {
		$out['separator'] = '|';
	}
	return $out;
}

add_action( 'admin_init', static function (): void {
	register_setting( 'phseo_settings', PHSEO_SETTINGS, array( 'sanitize_callback' => 'phseo_sanitize_settings' ) );
} );
