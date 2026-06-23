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

// --- System (Elementor's four required slots). v2 "Engineered" palette
// (docs/12 §11.1): brand navy, gold accent (gold fills carry navy text). ----
$settings['system_colors'] = array(
	$color( 'primary', 'Primary (Brand navy)', '#0B2846' ),
	$color( 'secondary', 'Secondary (Navy 700)', '#1B4068' ),
	$color( 'text', 'Text (Ink)', '#11161B' ),
	$color( 'accent', 'Accent (Gold)', '#FDBB5D' ),
);

// --- Full palette from the v2 design system (§11.1). Navy-slate ground,
// gold action, steel secondary. Orange/serif retired. ----------------------
$settings['custom_colors'] = array(
	$color( 'ph_navy_950', 'ph-color-navy-950', '#06182B' ),
	$color( 'ph_navy_900', 'ph-color-navy-900', '#0B2846' ),
	$color( 'ph_navy_800', 'ph-color-navy-800', '#123255' ),
	$color( 'ph_navy_700', 'ph-color-navy-700', '#1B4068' ),
	$color( 'ph_navy_600', 'ph-color-navy-600', '#2A5078' ),
	$color( 'ph_gold_600', 'ph-color-gold-600', '#E0A33F' ),
	$color( 'ph_gold_500', 'ph-color-gold-500', '#FDBB5D' ),
	$color( 'ph_gold_400', 'ph-color-gold-400', '#FECF87' ),
	$color( 'ph_gold_50', 'ph-color-gold-50', '#FEF8EC' ),
	$color( 'ph_gold_ink', 'ph-color-gold-ink', '#8A5E12' ),
	$color( 'ph_steel_700', 'ph-color-steel-700', '#2C566F' ),
	$color( 'ph_steel_600', 'ph-color-steel-600', '#345D79' ),
	$color( 'ph_steel_500', 'ph-color-steel-500', '#3E6E8E' ),
	$color( 'ph_steel_200', 'ph-color-steel-200', '#AFC8D6' ),
	$color( 'ph_steel_50', 'ph-color-steel-50', '#EDF3F6' ),
	$color( 'ph_paper', 'ph-color-paper', '#F7F8FA' ),
	$color( 'ph_mist', 'ph-color-mist', '#F2F4F6' ),
	$color( 'ph_white', 'ph-color-white', '#FFFFFF' ),
	$color( 'ph_border', 'ph-color-border', '#E6E9ED' ),
	$color( 'ph_border_strong', 'ph-color-border-strong', '#D4DAE0' ),
	$color( 'ph_ink', 'ph-color-ink', '#11161B' ),
	$color( 'ph_muted', 'ph-color-muted', '#4A555F' ),
	$color( 'ph_success', 'ph-color-success', '#1E7A52' ),
	$color( 'ph_warning', 'ph-color-warning', '#B5740C' ),
	$color( 'ph_error', 'ph-color-error', '#C23A2B' ),
	$color( 'ph_focus', 'ph-color-focus', '#3E6E8E' ),
);

// --- Typography. Static fallback sizes here; fluid clamp() sizes are
// applied through child-theme global classes (design system §3 rules).
// Display/headings = Archivo (800/700), body = Source Sans 3, mono = IBM
// Plex Mono (§11.2). 17px body. Eyebrow uses the mono "data" treatment.
$settings['system_typography'] = array(
	$type( 'primary', 'Primary (Display Archivo)', 'Archivo', '800', 3.5, 1.08, '-0.02' ),
	$type( 'secondary', 'Secondary (H2 Archivo)', 'Archivo', '800', 2.125, 1.15, '-0.015' ),
	$type( 'text', 'Text (Body Source Sans 3)', 'Source Sans 3', '400', 1.0625, 1.6 ),
	$type( 'accent', 'Accent (H4/UI Archivo)', 'Archivo', '700', 1.125, 1.3 ),
);
$settings['custom_typography'] = array(
	$type( 'ph_type_h1', 'ph-type-h1', 'Archivo', '800', 2.75, 1.12, '-0.02' ),
	$type( 'ph_type_h3', 'ph-type-h3', 'Archivo', '700', 1.5, 1.2, '-0.01' ),
	$type( 'ph_type_body_lg', 'ph-type-body-lg', 'Source Sans 3', '400', 1.1875, 1.65 ),
	$type( 'ph_type_small', 'ph-type-small', 'Source Sans 3', '500', 0.875, 1.45 ),
	$type( 'ph_type_eyebrow', 'ph-type-eyebrow', 'IBM Plex Mono', '600', 0.75, 1.3, '0.12' ),
	$type( 'ph_type_data', 'ph-type-data', 'IBM Plex Mono', '500', 0.8125, 1.4 ),
);

// --- Layout: 72rem container (1152px), fluid gutter approximated by the
// editor-storable default; exact gutter clamp lives in the child theme.
$settings['container_width']   = array(
	'unit' => 'px',
	'size' => 1240,
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
echo "  container: 1240px, breakpoints: 639/899/1199\n";
