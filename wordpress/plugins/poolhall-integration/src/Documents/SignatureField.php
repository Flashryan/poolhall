<?php
/**
 * Gravity Forms electronic-signature field (canvas pad + typed fallback).
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

/**
 * Custom `poolhall_signature` Gravity Forms field: a signature_pad.js
 * canvas (vendored locally, works with mouse/touch incl. mobile Safari)
 * with a Clear button and a clearly-labelled typed-name fallback. The
 * submitted value is a compact JSON envelope:
 *
 *   {"method":"drawn","data":"data:image/png;base64,..."} or
 *   {"method":"typed","data":"Full Name"}
 *
 * Validation runs server-side: drawn signatures must be a real non-empty
 * PNG data URL, typed signatures a real name. The GF Signature Add-On is
 * deliberately not required.
 */
final class SignatureField extends \GF_Field {

	public $type = 'poolhall_signature';

	private const MAX_BYTES = 400000;

	public function get_form_editor_field_title() {
		return 'Poolhall e-signature';
	}

	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * @param array        $form  Form meta.
	 * @param string|array $value Submitted value.
	 * @param null|array   $entry Entry.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$id    = (int) $this->id;
		$name  = 'input_' . $id;
		$value = is_string( $value ) ? $value : '';

		return '<div class="ginput_container poolhall-signature-field" data-ph-signature>'
			. '<div class="ph-sig-pad-wrap"><canvas class="ph-sig-pad" height="160" aria-label="Signature pad: draw your signature"></canvas></div>'
			. '<div class="ph-sig-actions">'
			. '<button type="button" class="ph-sig-clear" data-ph-sig-clear>Clear signature</button>'
			. '<span class="ph-sig-hint">Sign above with your mouse or finger.</span>'
			. '</div>'
			. '<div class="ph-sig-typed">'
			. '<label>Can&rsquo;t sign on this device? <span class="ph-sig-typed-label">I am typing my full name as my electronic signature:</span>'
			. '<input type="text" class="ph-sig-typed-input" data-ph-sig-typed autocomplete="name" maxlength="120" placeholder="Type your full legal name" /></label>'
			. '</div>'
			. '<input type="hidden" name="' . esc_attr( $name ) . '" id="input_' . esc_attr( (string) $form['id'] ) . '_' . esc_attr( (string) $id ) . '" value="' . esc_attr( $value ) . '" data-ph-sig-value />'
			. '</div>';
	}

	/**
	 * @param string|array $value Submitted value.
	 * @param array        $form  Form meta.
	 */
	public function validate( $value, $form ) {
		$decoded = self::decode( is_string( $value ) ? $value : '' );
		if ( null === $decoded ) {
			$this->failed_validation  = true;
			$this->validation_message = 'Please sign in the signature box (or type your full name as your electronic signature).';
			return;
		}
		if ( 'typed' === $decoded['method'] && mb_strlen( trim( $decoded['data'] ) ) < 3 ) {
			$this->failed_validation  = true;
			$this->validation_message = 'Please type your full name as your electronic signature.';
		}
	}

	/**
	 * Normalises + sanitises the submitted envelope; null when invalid.
	 *
	 * @return array{method:string,data:string}|null
	 */
	public static function decode( string $value ): ?array {
		if ( '' === $value || strlen( $value ) > self::MAX_BYTES ) {
			return null;
		}
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['method'], $decoded['data'] ) || ! is_string( $decoded['data'] ) ) {
			return null;
		}
		$method = (string) $decoded['method'];
		$data   = $decoded['data'];
		if ( 'drawn' === $method ) {
			if ( ! str_starts_with( $data, 'data:image/png;base64,' ) ) {
				return null;
			}
			$png = base64_decode( substr( $data, 22 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding the signature image the candidate drew, strict mode.
			// A blank/near-blank pad produces a tiny PNG; require real ink.
			if ( false === $png || strlen( $png ) < 1200 ) {
				return null;
			}
			return array(
				'method' => 'drawn',
				'data'   => $data,
			);
		}
		if ( 'typed' === $method ) {
			return array(
				'method' => 'typed',
				'data'   => sanitize_text_field( $data ),
			);
		}
		return null;
	}

	/**
	 * Keep entry-list/detail output safe and small — never echo base64.
	 *
	 * @param string|array $value    Value.
	 * @param string       $currency Currency.
	 * @param bool         $use_text Use text.
	 * @param string       $format   Format.
	 * @param string       $media    Media.
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		$decoded = self::decode( is_string( $value ) ? $value : '' );
		if ( null === $decoded ) {
			return '(no signature)';
		}
		return 'drawn' === $decoded['method'] ? 'Drawn electronic signature (image in PDF)' : 'Typed: ' . esc_html( $decoded['data'] );
	}
}
