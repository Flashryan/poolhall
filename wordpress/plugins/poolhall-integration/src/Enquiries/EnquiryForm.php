<?php
/**
 * Server-rendered enquiry forms.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Enquiries;

/**
 * `[poolhall_enquiry_form kind="hiring|contact"]` renders the hiring
 * enquiry (spec 01 §7) or general contact form with design-system markup,
 * honeypot + nonce + the `poolhall_human_check` Turnstile seam, required
 * privacy consent, and no-JS submission through admin-post. Outcome
 * notices read the `enquiry`/`enquiry_errors` query state the endpoint
 * redirects back with. The future Elementor widget wraps this renderer.
 */
final class EnquiryForm {

	public const SHORTCODE = 'poolhall_enquiry_form';

	private const MESSAGES = array(
		'name_required'    => 'Please tell us your name.',
		'company_required' => 'Please tell us your company.',
		'email_invalid'    => 'Please enter a valid email address.',
		'type_required'    => 'Please choose what your enquiry is about.',
		'message_required' => 'Please add a short message.',
		'consent_required' => 'Please confirm you consent to us storing your details.',
	);

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * @param array<string,string>|string $atts Shortcode attributes.
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts( array( 'kind' => EnquiryRequest::KIND_CONTACT ), is_array( $atts ) ? $atts : array() );
		$kind = EnquiryRequest::KIND_HIRING === $atts['kind'] ? EnquiryRequest::KIND_HIRING : EnquiryRequest::KIND_CONTACT;

		// Read-only outcome state from the redirect; no actions are taken from it.
		$state = isset( $_GET['enquiry'] ) ? sanitize_key( wp_unslash( $_GET['enquiry'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sent' === $state ) {
			return '<div class="ph-card ph-stack-sm" id="enquiry">'
				. '<h3 class="ph-h3">' . esc_html__( 'Thanks, we&rsquo;ll be in touch', 'poolhall-integration' ) . '</h3>'
				. '<p class="ph-body">' . esc_html__( 'One of the team will reply within one working day.', 'poolhall-integration' ) . '</p>'
				. '</div>';
		}

		$codes = array();
		if ( 'invalid' === $state && isset( $_GET['enquiry_errors'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$codes = array_values( array_intersect( explode( ',', sanitize_text_field( wp_unslash( $_GET['enquiry_errors'] ) ) ), array_keys( self::MESSAGES ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$html  = '<div class="ph-card" id="enquiry">';
		$html .= $this->notice( $state, $codes );
		$html .= '<form class="ph-form ph-stack-sm" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( EnquiryEndpoints::ACTION ) . '" />';
		$html .= '<input type="hidden" name="kind" value="' . esc_attr( $kind ) . '" />';
		$html .= wp_nonce_field( EnquiryEndpoints::ACTION, '_wpnonce', false, false );
		$html .= '<div class="ph-visually-hidden" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'poolhall-integration' )
			. '<input type="text" name="' . esc_attr( EnquiryEndpoints::HONEYPOT_FIELD ) . '" tabindex="-1" autocomplete="off" value="" /></label></div>';

		$html .= '<div class="ph-form-grid">';
		$html .= $this->text_field( 'name', __( 'Your name', 'poolhall-integration' ), 'text', 'name', true, $codes, array( 'name_required' ) );
		if ( EnquiryRequest::KIND_HIRING === $kind ) {
			$html .= $this->text_field( 'company', __( 'Company', 'poolhall-integration' ), 'text', 'organization', true, $codes, array( 'company_required' ) );
		}
		$html .= $this->text_field( 'email', __( 'Email', 'poolhall-integration' ), 'email', 'email', true, $codes, array( 'email_invalid' ) );
		$html .= $this->text_field( 'phone', __( 'Phone', 'poolhall-integration' ), 'tel', 'tel', false, $codes, array() );
		$html .= '</div>';

		if ( EnquiryRequest::KIND_CONTACT === $kind ) {
			$invalid = in_array( 'type_required', $codes, true );
			$html   .= '<div class="ph-field' . ( $invalid ? ' ph-field--invalid' : '' ) . '">'
				. '<label class="ph-field__label" for="ph-enquiry-type">' . esc_html__( 'I&rsquo;m a&hellip;', 'poolhall-integration' ) . '</label>'
				. '<select class="ph-field__control" id="ph-enquiry-type" name="enquirer_type" required' . ( $invalid ? ' aria-invalid="true"' : '' ) . '>'
				. '<option value="">' . esc_html__( 'Please choose', 'poolhall-integration' ) . '</option>';
			foreach ( EnquiryRequest::ENQUIRER_TYPES as $value => $label ) {
				$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
			}
			$html .= '</select>';
			if ( $invalid ) {
				$html .= '<p class="ph-field__error">' . esc_html( self::MESSAGES['type_required'] ) . '</p>';
			}
			$html .= '</div>';
		}

		$message_label   = EnquiryRequest::KIND_HIRING === $kind
			? __( 'What are you hiring for?', 'poolhall-integration' )
			: __( 'Message', 'poolhall-integration' );
		$message_invalid = in_array( 'message_required', $codes, true );
		$html           .= '<div class="ph-field' . ( $message_invalid ? ' ph-field--invalid' : '' ) . '">'
			. '<label class="ph-field__label" for="ph-enquiry-message">' . esc_html( $message_label ) . '</label>'
			. '<textarea class="ph-field__control" id="ph-enquiry-message" name="message" required' . ( $message_invalid ? ' aria-invalid="true"' : '' )
			. ' placeholder="' . esc_attr( EnquiryRequest::KIND_HIRING === $kind ? __( 'Roles, sectors, timescales…', 'poolhall-integration' ) : __( 'How can we help?', 'poolhall-integration' ) ) . '"></textarea>';
		if ( $message_invalid ) {
			$html .= '<p class="ph-field__error">' . esc_html( self::MESSAGES['message_required'] ) . '</p>';
		}
		$html .= '</div>';

		$consent_invalid = in_array( 'consent_required', $codes, true );
		$html           .= '<label class="ph-checkbox' . ( $consent_invalid ? ' ph-field--invalid' : '' ) . '">'
			. '<input type="checkbox" name="consent" value="1" required />'
			. '<span>' . wp_kses_post( $this->consent_text() ) . '</span>'
			. '</label>';
		if ( $consent_invalid ) {
			$html .= '<p class="ph-field__error">' . esc_html( self::MESSAGES['consent_required'] ) . '</p>';
		}

		$submit = EnquiryRequest::KIND_HIRING === $kind ? __( 'Send enquiry', 'poolhall-integration' ) : __( 'Send message', 'poolhall-integration' );
		$html  .= '<button class="ph-button ph-button--primary ph-button--lg ph-button--full" type="submit">' . esc_html( $submit ) . '</button>';
		$html  .= '</form></div>';

		return $html;
	}

	/**
	 * @param string[] $codes Active validation error codes.
	 */
	private function notice( string $state, array $codes ): string {
		if ( 'invalid' === $state && array() !== $codes ) {
			$items = '';
			foreach ( $codes as $code ) {
				$items .= '<li>' . esc_html( self::MESSAGES[ $code ] ) . '</li>';
			}
			return '<div class="ph-alert ph-alert--error" role="alert"><p>' . esc_html__( 'Please check the form:', 'poolhall-integration' ) . '</p><ul>' . $items . '</ul></div>';
		}
		if ( 'rate_limited' === $state ) {
			return '<div class="ph-alert ph-alert--warning" role="alert"><p>' . esc_html__( 'Too many enquiries from this connection — please try again in an hour, or call us on 0121 516 3000.', 'poolhall-integration' ) . '</p></div>';
		}
		if ( 'mail_failed' === $state || 'failed' === $state ) {
			return '<div class="ph-alert ph-alert--error" role="alert"><p>' . esc_html__( 'Sorry, your message could not be sent. Please call 0121 516 3000 or email jobs@poolhallrecruitment.co.uk.', 'poolhall-integration' ) . '</p></div>';
		}
		return '';
	}

	private function consent_text(): string {
		$policy_url = get_privacy_policy_url();
		$policy     = get_option( 'wp_page_for_privacy_policy' );
		$published  = $policy && 'publish' === get_post_status( (int) $policy ) && '' !== $policy_url;

		if ( $published ) {
			return sprintf(
				/* translators: %s: privacy policy URL. */
				__( 'I consent to Poolhall Recruitment storing my details to respond to this enquiry, per the <a href="%s">Privacy Policy</a>.', 'poolhall-integration' ),
				esc_url( $policy_url )
			);
		}
		return __( 'I consent to Poolhall Recruitment storing my details to respond to this enquiry.', 'poolhall-integration' );
	}

	private function text_field( string $name, string $label, string $type, string $autocomplete, bool $required, array $codes, array $field_errors ): string {
		$active = array_values( array_intersect( $codes, $field_errors ) );
		$id     = 'ph-enquiry-' . $name;
		$html   = '<div class="ph-field' . ( array() !== $active ? ' ph-field--invalid' : '' ) . '">'
			. '<label class="ph-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>'
			. '<input class="ph-field__control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" autocomplete="' . esc_attr( $autocomplete ) . '"' . ( $required ? ' required' : '' ) . ( array() !== $active ? ' aria-invalid="true"' : '' ) . ' />';
		foreach ( $active as $code ) {
			$html .= '<p class="ph-field__error">' . esc_html( self::MESSAGES[ $code ] ) . '</p>';
		}
		return $html . '</div>';
	}
}
