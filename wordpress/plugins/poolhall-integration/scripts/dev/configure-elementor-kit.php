<?php
/**
 * Phase 3: configure the Elementor active kit (Site Settings) to the token
 * contract in wordpress/docs/10-ELEMENTOR-DESIGN-SYSTEM.md.
 *
 *   wp eval-file scripts/dev/configure-elementor-kit.php
 *
 * Idempotent: safe to re-run; it overwrites only the keys it owns. This
 * script is the deployment artifact — run it on staging after Elementor
 * is installed there, instead of clicking Site Settings by hand.
 *
 * Editor-storable tokens (colors, families, weights, line heights, static
 * sizes, container, breakpoints) live here. Compound clamp() formulas live
 * in the child theme per the design-system rules.
 *
 * No strict_types: wp eval-file runs through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/configure-elementor-kit.php\n";
	exit( 1 );
}

if ( ! did_action( 'elementor/loaded' ) ) {
	echo "FAIL: Elementor is not active.\n";
	exit( 1 );
}

$kit_id = (int) get_option( 'elementor_active_kit' );
if ( $kit_id <= 0 || 'publish' !== get_post_status( $kit_id ) ) {
	echo "FAIL: no active Elementor kit found.\n";
	exit( 1 );
}

// Backup before any Elementor data change (hard rule 11).
$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
$settings = is_array( $settings ) ? $settings : array();
update_option(
	'poolhall_kit_backup_' . gmdate( 'Ymd_His' ),
	array(
		'kit_id'   => $kit_id,
		'settings' => $settings,
	),
	false
);

$color = static fn( string $id, string $title, string $hex ): array => array(
	'_id'   => $id,
	'title' => $title,
	'color' => $hex,
);

$type = static function ( string $id, string $title, string $family, string $weight, $size_rem, float $line_height, string $spacing = '' ): array {
	$t = array(
		'_id'                        => $id,
		'title'                      => $title,
		'typography_typography'      => 'custom',
		'typography_font_family'     => $family,
		'typography_font_weight'     => $weight,
		'typography_line_height'     => array(
			'unit' => 'em',
			'size' => $line_height,
		),
	);
	if ( null !== $size_rem ) {
		$t['typography_font_size'] = array(
			'unit' => 'rem',
			'size' => $size_rem,
		);
	}
	if ( '' !== $spacing ) {
		$t['typography_letter_spacing'] = array(
			'unit' => 'em',
			'size' => (float) $spacing,
		);
	}
	return $t;
};

// --- System (Elementor's four required slots) -----------------------------
$settings['system_colors'] = array(
	$color( 'primary', 'Primary (Navy 800)', '#14233F' ),
	$color( 'secondary', 'Secondary (Navy 700)', '#1B3052' ),
	$color( 'text', 'Text (Ink)', '#16202F' ),
	$color( 'accent', 'Accent (Orange 500)', '#EC6F1E' ),
);

// --- Full palette from the design system (§3 color variables) -------------
$settings['custom_colors'] = array(
	$color( 'ph_navy_950', 'ph-color-navy-950', '#0B1626' ),
	$color( 'ph_navy_900', 'ph-color-navy-900', '#0F1D33' ),
	$color( 'ph_navy_800', 'ph-color-navy-800', '#14233F' ),
	$color( 'ph_navy_700', 'ph-color-navy-700', '#1B3052' ),
	$color( 'ph_orange_700', 'ph-color-orange-700', '#B9510E' ),
	$color( 'ph_orange_600', 'ph-color-orange-600', '#D45F12' ),
	$color( 'ph_orange_500', 'ph-color-orange-500', '#EC6F1E' ),
	$color( 'ph_orange_50', 'ph-color-orange-50', '#FDF1E7' ),
	$color( 'ph_blue_700', 'ph-color-blue-700', '#155389' ),
	$color( 'ph_blue_600', 'ph-color-blue-600', '#1F6FB2' ),
	$color( 'ph_gold_500', 'ph-color-gold-500', '#C6A052' ),
	$color( 'ph_paper', 'ph-color-paper', '#F7F8FA' ),
	$color( 'ph_mist', 'ph-color-mist', '#EEF1F5' ),
	$color( 'ph_white', 'ph-color-white', '#FFFFFF' ),
	$color( 'ph_border', 'ph-color-border', '#E3E7ED' ),
	$color( 'ph_ink', 'ph-color-ink', '#16202F' ),
	$color( 'ph_muted', 'ph-color-muted', '#586375' ),
	$color( 'ph_success', 'ph-color-success', '#1F8A5B' ),
	$color( 'ph_warning', 'ph-color-warning', '#A56800' ),
	$color( 'ph_error', 'ph-color-error', '#C9382E' ),
	$color( 'ph_focus', 'ph-color-focus', '#1F6FB2' ),
);

// --- Typography. Static fallback sizes here; fluid clamp() sizes are
// applied through child-theme global classes (design system §3 rules).
$settings['system_typography'] = array(
	$type( 'primary', 'Primary (Display serif)', 'Source Serif 4', '500', 3.5, 1.08, '-0.005' ),
	$type( 'secondary', 'Secondary (H2 serif)', 'Source Serif 4', '500', 2.125, 1.15, '-0.005' ),
	$type( 'text', 'Text (Body)', 'Hanken Grotesk', '400', 1.0, 1.6 ),
	$type( 'accent', 'Accent (H4/UI)', 'Hanken Grotesk', '700', 1.125, 1.3 ),
);
$settings['custom_typography'] = array(
	$type( 'ph_type_h1', 'ph-type-h1', 'Source Serif 4', '500', 2.75, 1.12, '-0.005' ),
	$type( 'ph_type_h3', 'ph-type-h3', 'Source Serif 4', '500', 1.5, 1.2, '-0.005' ),
	$type( 'ph_type_body_lg', 'ph-type-body-lg', 'Hanken Grotesk', '400', 1.125, 1.65 ),
	$type( 'ph_type_small', 'ph-type-small', 'Hanken Grotesk', '500', 0.875, 1.45 ),
	$type( 'ph_type_eyebrow', 'ph-type-eyebrow', 'Hanken Grotesk', '700', 0.75, 1.3, '0.14' ),
	$type( 'ph_type_data', 'ph-type-data', 'IBM Plex Mono', '500', 0.8125, 1.4 ),
);

// --- Layout: 72rem container (1152px), fluid gutter approximated by the
// editor-storable default; exact gutter clamp lives in the child theme.
$settings['container_width']   = array(
	'unit' => 'px',
	'size' => 1152,
);
$settings['container_padding'] = array(
	'unit'     => 'px',
	'top'      => '0',
	'right'    => '24',
	'bottom'   => '0',
	'left'     => '24',
	'isLinked' => false,
);

// --- Breakpoints (design system §5): Mobile ≤639, Tablet ≤899, Laptop ≤1199.
$settings['active_breakpoints'] = array(
	'viewport_mobile',
	'viewport_tablet',
	'viewport_laptop',
);
$settings['viewport_mobile']    = 639;
$settings['viewport_tablet']    = 899;
$settings['viewport_laptop']    = 1199;

// --- Body defaults ---------------------------------------------------------
$settings['body_background_background'] = 'classic';
$settings['body_background_color']      = '#F7F8FA';

update_post_meta( $kit_id, '_elementor_page_settings', wp_slash( $settings ) );

// Regenerate Elementor CSS and clear caches (workflow doc requirement).
\Elementor\Plugin::instance()->files_manager->clear_cache();

echo 'OK: kit ' . $kit_id . " configured.\n";
echo '  system colors:   ' . count( $settings['system_colors'] ) . "\n";
echo '  custom colors:   ' . count( $settings['custom_colors'] ) . "\n";
echo '  typography sets: ' . ( count( $settings['system_typography'] ) + count( $settings['custom_typography'] ) ) . "\n";
echo "  container: 1152px, breakpoints: 639/899/1199\n";
