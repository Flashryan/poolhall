<?php
/**
 * Enquiry form admin-post handler.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Enquiries;

/**
 * Handles `[poolhall_enquiry_form]` submissions over admin-post (no REST,
 * no JavaScript required). Nonce, honeypot and the `poolhall_human_check`
 * Turnstile seam guard every request — the same defenses as the candidate
 * auth flows. Redirects go to the fixed page for the form kind (employers
 * or contact), never to a caller-supplied URL.
 */
final class EnquiryEndpoints {

	public const ACTION         = 'poolhall_enquiry';
	public const HONEYPOT_FIELD = 'poolhall_website';

	public function __construct( private readonly EnquiryService $service ) {
	}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle(): never {
		$kind = $this->field( 'kind' );
		$kind = EnquiryRequest::KIND_HIRING === $kind ? EnquiryRequest::KIND_HIRING : EnquiryRequest::KIND_CONTACT;

		if ( ! $this->request_passes() ) {
			$this->redirect( $kind, array( 'enquiry' => 'failed' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in request_passes().
		$request = EnquiryRequest::from_post( $kind, wp_unslash( $_POST ) );
		$result  = $this->service->submit( $request, $this->network_signal() );

		match ( $result['status'] ) {
			EnquiryService::STATUS_SENT => $this->redirect( $kind, array( 'enquiry' => 'sent' ) ),
			EnquiryService::STATUS_INVALID => $this->redirect(
				$kind,
				array(
					'enquiry'        => 'invalid',
					'enquiry_errors' => implode( ',', $result['errors'] ),
				)
			),
			default => $this->redirect( $kind, array( 'enquiry' => $result['status'] ) ),
		};
	}

	/** Nonce, honeypot and the pluggable human check, in that order. */
	private function request_passes(): bool {
		if ( false === wp_verify_nonce( $this->field( '_wpnonce' ), self::ACTION ) ) {
			return false;
		}
		if ( '' !== $this->field( self::HONEYPOT_FIELD ) ) {
			return false;
		}
		/** This filter is documented in src/Accounts/AuthEndpoints.php */
		return (bool) apply_filters( 'poolhall_human_check', true, self::ACTION );
	}

	private function field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in request_passes() before any state change.
	}

	private function network_signal(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * @param array<string,string> $args Query arguments for the notice state.
	 */
	private function redirect( string $kind, array $args ): never {
		$path = EnquiryRequest::KIND_HIRING === $kind ? '/employers/' : '/contact/';
		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), home_url( $path ) ) . '#enquiry' );
		exit;
	}
}
