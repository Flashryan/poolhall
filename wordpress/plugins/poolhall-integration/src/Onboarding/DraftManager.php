<?php
/**
 * Secure Gravity Forms draft handling for the candidate profile.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/**
 * Keeps Gravity Forms' bearer resume token behind the passwordless hub.
 *
 * Gravity Forms still owns the incomplete submission. The candidate record
 * stores only an authenticated-encryption envelope and a keyed token digest;
 * neither the native token nor a native resume URL is sent to the browser.
 */
final class DraftManager {

	public const PROFILE_KIND = 'full';
	public const DRAFT_TTL    = 1209600;

	private const FORM_MAP_OPTION = 'poolhall_document_forms';
	private const CIPHER          = 'aes-256-gcm';
	private const ENVELOPE        = 'v1';
	private const KEY_CONTEXT     = 'poolhall-gf-resume-token-v1';

	/** @var (\Closure(int,string,int):void)|null */
	private readonly ?\Closure $link_sender;

	/**
	 * The optional sender receives record id, one-time hub URL and its expiry.
	 * Without one, `poolhall_candidate_profile_resume_link` is dispatched with
	 * those same three arguments so the mail service can be wired separately.
	 */
	public function __construct(
		private readonly InviteManager $invites = new InviteManager(),
		private readonly WorkflowState $state = new WorkflowState(),
		?callable $link_sender = null,
	) {
		$this->link_sender = null === $link_sender ? null : \Closure::fromCallable( $link_sender );
	}

	public function register(): void {
		add_filter( 'gform_pre_render', array( $this, 'configure_form' ) );
		add_filter( 'gform_pre_validation', array( $this, 'configure_form' ) );
		add_filter( 'gform_pre_submission_filter', array( $this, 'configure_form' ) );
		add_filter( 'gform_incomplete_submissions_expiration_days', array( $this, 'expiration_days' ) );
		add_action( 'gform_incomplete_submission_post_save', array( $this, 'saved' ), 10, 4 );
		add_filter( 'gform_incomplete_submission_post_get', array( $this, 'authorize_retrieved_draft' ), 10, 3 );
		add_filter( 'gform_save_and_continue_resume_url', array( $this, 'resume_url' ), 10, 4 );
		add_filter( 'gform_get_form_save_confirmation_filter', array( $this, 'save_confirmation' ), 10, 2 );
		add_filter( 'gform_get_form_save_email_confirmation_filter', array( $this, 'save_confirmation' ), 10, 2 );
		add_filter( 'gform_disable_notification', array( $this, 'disable_native_save_notification' ), 10, 5 );
	}

	/**
	 * Runtime enforcement: only the full/profile form exposes Save & Continue,
	 * and only inside an authorised profile-stage browser session.
	 *
	 * @param array<string,mixed> $form Gravity Forms form object.
	 * @return array<string,mixed>
	 */
	public function configure_form( $form ): array {
		if ( ! is_array( $form ) ) {
			return array();
		}

		$kind = $this->kind_for_form( (int) ( $form['id'] ?? 0 ) );
		if ( '' === $kind ) {
			return $form;
		}

		$authorised   = self::PROFILE_KIND === $kind && $this->invites->record_for_kind( self::PROFILE_KIND ) > 0;
		$form['save'] = array(
			'enabled' => $authorised,
			'button'  => array(
				'type' => 'link',
				'text' => __( 'Save and finish later', 'poolhall-integration' ),
			),
		);

		return $form;
	}

	/** Gravity Forms exposes draft expiry as a global setting. */
	public function expiration_days( int $days ): int {
		unset( $days );
		return 14;
	}

	/**
	 * Capture the native bearer after GF has saved the incomplete submission.
	 *
	 * @param array<string,mixed> $submission Incomplete submission payload.
	 * @param string              $resume_token Native Gravity Forms token.
	 * @param array<string,mixed> $form Gravity Forms form object.
	 * @param array<string,mixed> $entry Draft entry.
	 */
	public function saved( $submission, string $resume_token, $form, $entry ): void {
		unset( $submission, $entry );
		if ( ! is_array( $form ) || ! $this->is_profile_form( (int) ( $form['id'] ?? 0 ) ) || '' === $resume_token ) {
			return;
		}

		$record_id = $this->invites->record_for_kind( self::PROFILE_KIND );
		if ( $record_id <= 0 ) {
			return;
		}

		$envelope = $this->encrypt( $resume_token, $record_id );
		if ( '' === $envelope ) {
			return;
		}

		$expires = time() + self::DRAFT_TTL;
		update_post_meta( $record_id, $this->meta( 'draft_token_ciphertext' ), $envelope );
		update_post_meta( $record_id, $this->meta( 'draft_token_hash' ), $this->token_hash( $resume_token ) );
		update_post_meta( $record_id, $this->meta( 'draft_saved_at' ), gmdate( \DateTimeInterface::ATOM ) );
		update_post_meta( $record_id, $this->meta( 'draft_expires_at' ), (string) $expires );
		$this->state->set_task_status( $record_id, self::PROFILE_KIND, 'in_progress' );

		$this->send_fresh_access_link( $record_id, $expires );
	}

	/**
	 * Gravity Forms loads an incomplete submission before rendering it. Reject
	 * the JSON unless the browser session owns the record whose keyed digest
	 * matches this native token. This prevents a leaked GF token from working
	 * outside its passwordless candidate session.
	 *
	 * @param string              $submission_json Stored incomplete-submission JSON.
	 * @param string              $resume_token Native Gravity Forms token.
	 * @param array<string,mixed> $form Gravity Forms form object.
	 */
	public function authorize_retrieved_draft( string $submission_json, string $resume_token, $form ): string {
		if ( ! is_array( $form ) || ! $this->is_profile_form( (int) ( $form['id'] ?? 0 ) ) ) {
			return $submission_json;
		}

		$record_id = $this->invites->record_for_kind( self::PROFILE_KIND );
		if ( $record_id <= 0 || '' === $resume_token ) {
			return '{}';
		}

		$expected = (string) get_post_meta( $record_id, $this->meta( 'draft_token_hash' ), true );
		$expires  = (int) get_post_meta( $record_id, $this->meta( 'draft_expires_at' ), true );
		if ( '' === $expected || $expires <= time() || ! hash_equals( $expected, $this->token_hash( $resume_token ) ) ) {
			return '{}';
		}

		return $submission_json;
	}

	/**
	 * Replace GF's token-bearing URL everywhere, including future accidental
	 * reactivation of its stock Save & Continue notification.
	 *
	 * @param string              $resume_url Native URL.
	 * @param array<string,mixed> $form Gravity Forms form object.
	 * @param string              $token Native token.
	 * @param string|null         $email Requested email. Gravity Forms passes null when its native email control is unused.
	 */
	public function resume_url( string $resume_url, $form, string $token, ?string $email ): string {
		unset( $token, $email );
		if ( ! is_array( $form ) || ! $this->is_profile_form( (int) ( $form['id'] ?? 0 ) ) ) {
			return $resume_url;
		}
		return CandidateHub::task_url( self::PROFILE_KIND );
	}

	/**
	 * Remove all native token, link and arbitrary-email controls from the save
	 * confirmation. The same safe text replaces the email-sent confirmation.
	 *
	 * @param string              $confirmation Native confirmation HTML.
	 * @param array<string,mixed> $form Gravity Forms form object.
	 */
	public function save_confirmation( string $confirmation, $form ): string {
		if ( ! is_array( $form ) || ! $this->is_profile_form( (int) ( $form['id'] ?? 0 ) ) ) {
			return $confirmation;
		}

		return '<div class="poolhall-form-note poolhall-form-final">'
			. '<h3>' . esc_html__( 'Your progress is saved', 'poolhall-integration' ) . '</h3>'
			. '<p>' . esc_html__( 'Your draft is protected for 14 days. Return to your checklist now, or use the most recent personal task link from Poolhall. If the fresh email does not arrive, contact the team.', 'poolhall-integration' ) . '</p>'
			. '<p><a class="button" href="' . esc_url( CandidateHub::hub_url() ) . '">'
			. esc_html__( 'Return to my checklist', 'poolhall-integration' ) . '</a></p></div>';
	}

	/**
	 * Defence in depth: stock save notifications must never disclose a native
	 * resume token even if one is added in the Gravity Forms admin later.
	 *
	 * @param bool                $disabled Existing disabled state.
	 * @param array<string,mixed> $notification Gravity Forms notification.
	 * @param array<string,mixed> $form Gravity Forms form object.
	 * @param array<string,mixed> $entry Entry data.
	 * @param array<string,mixed> $data Notification event data.
	 */
	public function disable_native_save_notification( bool $disabled, $notification, $form, $entry, $data ): bool {
		unset( $entry, $data );
		if ( $disabled || ! is_array( $form ) || ! $this->is_profile_form( (int) ( $form['id'] ?? 0 ) ) ) {
			return $disabled;
		}
		$event = is_array( $notification ) ? sanitize_key( (string) ( $notification['event'] ?? '' ) ) : '';
		return '' !== $event && str_contains( $event, 'save' );
	}

	/**
	 * Return a verified native token to trusted server-side callers only.
	 * Empty means no usable draft; callers should render a fresh prefilled form.
	 */
	public function resume_token( int $record_id ): string {
		$expires = (int) get_post_meta( $record_id, $this->meta( 'draft_expires_at' ), true );
		if ( $expires <= time() ) {
			$this->clear( $record_id );
			return '';
		}

		$envelope = (string) get_post_meta( $record_id, $this->meta( 'draft_token_ciphertext' ), true );
		$expected = (string) get_post_meta( $record_id, $this->meta( 'draft_token_hash' ), true );
		$token    = $this->decrypt( $envelope, $record_id );
		if ( '' === $token || '' === $expected || ! hash_equals( $expected, $this->token_hash( $token ) ) ) {
			$this->clear( $record_id );
			return '';
		}

		return $token;
	}

	public function has_draft( int $record_id ): bool {
		return '' !== $this->resume_token( $record_id );
	}

	/** Delete only this integration's encrypted pointer to the GF draft. */
	public function clear( int $record_id ): void {
		foreach ( array( 'draft_token_ciphertext', 'draft_token_hash', 'draft_saved_at', 'draft_expires_at' ) as $suffix ) {
			delete_post_meta( $record_id, $this->meta( $suffix ) );
		}
	}

	public function profile_form_id(): int {
		$map = (array) get_option( self::FORM_MAP_OPTION, array() );
		return isset( $map[ self::PROFILE_KIND ] ) ? (int) $map[ self::PROFILE_KIND ] : 0;
	}

	private function is_profile_form( int $form_id ): bool {
		return $form_id > 0 && $form_id === $this->profile_form_id();
	}

	private function kind_for_form( int $form_id ): string {
		if ( $form_id <= 0 ) {
			return '';
		}

		$map  = array_map( 'intval', (array) get_option( self::FORM_MAP_OPTION, array() ) );
		$kind = array_search( $form_id, $map, true );
		return false === $kind ? '' : (string) $kind;
	}

	private function send_fresh_access_link( int $record_id, int $expires ): void {
		$token = $this->invites->issue( $record_id, 'profile', true );
		if ( '' === $token ) {
			return;
		}
		$url = $this->invites->link( $token );
		if ( null !== $this->link_sender ) {
			( $this->link_sender )( $record_id, $url, $expires );
			return;
		}

		/**
		 * Send the fresh one-time profile link through the site's mail service.
		 *
		 * @param int    $record_id Candidate application record.
		 * @param string $url One-time passwordless hub URL.
		 * @param int    $expires Unix expiry timestamp.
		 */
		do_action( 'poolhall_candidate_profile_resume_link', $record_id, $url, $expires );
	}

	private function encrypt( string $token, int $record_id ): string {
		$iv         = random_bytes( 12 );
		$tag        = '';
		$ciphertext = openssl_encrypt(
			$token,
			self::CIPHER,
			$this->encryption_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			$this->additional_data( $record_id ),
			16
		);
		if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
			return '';
		}

		return implode( '.', array( self::ENVELOPE, $this->base64url( $iv ), $this->base64url( $tag ), $this->base64url( $ciphertext ) ) );
	}

	private function decrypt( string $envelope, int $record_id ): string {
		$parts = explode( '.', $envelope );
		if ( 4 !== count( $parts ) || self::ENVELOPE !== $parts[0] ) {
			return '';
		}
		$iv         = $this->base64url_decode( $parts[1] );
		$tag        = $this->base64url_decode( $parts[2] );
		$ciphertext = $this->base64url_decode( $parts[3] );
		if ( ! is_string( $iv ) || ! is_string( $tag ) || ! is_string( $ciphertext ) || 12 !== strlen( $iv ) || 16 !== strlen( $tag ) ) {
			return '';
		}

		$token = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$this->encryption_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			$this->additional_data( $record_id )
		);

		return is_string( $token ) ? $token : '';
	}

	private function encryption_key(): string {
		return hash_hkdf( 'sha256', wp_salt( 'secure_auth' ), 32, self::KEY_CONTEXT );
	}

	private function token_hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	private function additional_data( int $record_id ): string {
		return self::KEY_CONTEXT . ':' . $record_id . ':' . self::PROFILE_KIND;
	}

	private function meta( string $suffix ): string {
		return $this->state->meta( self::PROFILE_KIND, $suffix );
	}

	private function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary envelope encoding, not obfuscation.
	}

	private function base64url_decode( string $value ): string|false {
		$padding = strlen( $value ) % 4;
		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}
		return base64_decode( strtr( $value, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary envelope decoding.
	}
}
