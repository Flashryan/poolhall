<?php
/**
 * Passwordless, stage-specific candidate access.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/**
 * Issues reconstructable HMAC links for the profile and onboarding stages,
 * stages a valid emailed link without consuming it, then exchanges it once
 * the candidate explicitly continues into a secure browser session. Form
 * credentials stay out of PDFs, analytics and ordinary page URLs.
 */
final class InviteManager {

	public const ACCESS_ARG = 'access';
	public const HUB_SLUG   = 'candidate-tasks';

	private const TOKEN_VERSION           = 'v1';
	private const INVITE_TTL              = 14 * 86400;
	private const SESSION_TTL             = 86400;
	private const PENDING_TTL             = 3600;
	private const COOKIE                  = '__Host-poolhall_candidate';
	private const COOKIE_FALLBACK         = 'poolhall_candidate';
	private const PENDING_COOKIE          = '__Host-poolhall_candidate_pending';
	private const PENDING_COOKIE_FALLBACK = 'poolhall_candidate_pending';
	private const DOCUMENT_KINDS          = array( 'contract', 'terms', 'starter', 'payroll' );

	public function __construct( private readonly WorkflowState $state = new WorkflowState() ) {
	}

	/** Issue or renew the single emailed link for a stage. */
	public function issue( int $record_id, string $stage, bool $renew = true ): string {
		if ( ! $this->valid_stage( $stage ) || $record_id <= 0 ) {
			return '';
		}
		$nonce  = (string) get_post_meta( $record_id, $this->invite_meta( $stage, 'nonce' ), true );
		$expiry = (int) get_post_meta( $record_id, $this->invite_meta( $stage, 'expires' ), true );
		if ( $renew || '' === $nonce || $expiry <= time() ) {
			$nonce  = $this->base64url( random_bytes( 24 ) );
			$expiry = time() + self::INVITE_TTL;
			update_post_meta( $record_id, $this->invite_meta( $stage, 'nonce' ), $nonce );
			update_post_meta( $record_id, $this->invite_meta( $stage, 'expires' ), (string) $expiry );
			delete_post_meta( $record_id, $this->invite_meta( $stage, 'used_at' ) );
		}
		return $this->build_token( $record_id, $stage, $expiry, $nonce );
	}

	/** Recreate the current unconsumed link for idempotent reminders. */
	public function token_for( int $record_id, string $stage ): string {
		if ( ! $this->valid_stage( $stage ) ) {
			return '';
		}
		$nonce   = (string) get_post_meta( $record_id, $this->invite_meta( $stage, 'nonce' ), true );
		$expiry  = (int) get_post_meta( $record_id, $this->invite_meta( $stage, 'expires' ), true );
		$used_at = (string) get_post_meta( $record_id, $this->invite_meta( $stage, 'used_at' ), true );
		if ( '' === $nonce || $expiry <= time() || '' !== $used_at ) {
			return '';
		}
		return $this->build_token( $record_id, $stage, $expiry, $nonce );
	}

	/**
	 * Stage a valid emailed bearer in an HttpOnly cookie without consuming it.
	 *
	 * Email-security scanners may follow this GET and its redirect. The invite
	 * is deliberately left usable until the candidate submits the clean hub's
	 * explicit continue form.
	 */
	public function prepare_access( string $token ): bool {
		$context = $this->validate_access( $token );
		if ( null === $context ) {
			return false;
		}

		$expires = min( $context['expires'], time() + self::PENDING_TTL );
		return $this->set_named_cookie( self::PENDING_COOKIE, self::PENDING_COOKIE_FALLBACK, $token, $expires );
	}

	/** Whether this browser has a still-valid staged bearer to confirm. */
	public function has_pending_access(): bool {
		$token = $this->pending_token();
		return '' !== $token && null !== $this->validate_access( $token );
	}

	/** Exchange the staged bearer once after the candidate explicitly posts. */
	public function accept_pending(): bool {
		$token    = $this->pending_token();
		$accepted = '' !== $token && $this->accept( $token );
		$this->clear_pending_access();
		return $accepted;
	}

	/** Exchange an emailed bearer once for an HttpOnly signed session. */
	public function accept( string $token ): bool {
		$context = $this->validate_access( $token );
		if ( null === $context ) {
			return false;
		}

		$record_id = $context['record_id'];
		$stage     = $context['stage'];
		$used_at   = gmdate( \DateTimeInterface::ATOM );
		$consumed  = add_post_meta( $record_id, $this->invite_meta( $stage, 'used_at' ), $used_at, true );
		if ( false === $consumed ) {
			return false;
		}

		$nonce        = $this->base64url( random_bytes( 24 ) );
		$expires      = time() + self::SESSION_TTL;
		$session_meta = $this->session_meta( $stage );
		$stored       = update_post_meta( $record_id, $session_meta, $this->digest( $nonce ) );
		if ( false === $stored ) {
			delete_post_meta( $record_id, $this->invite_meta( $stage, 'used_at' ), $used_at );
			return false;
		}
		$payload = $this->base64url(
			wp_json_encode(
				array(
					'record' => $record_id,
					'stage'  => $stage,
					'exp'    => $expires,
					'nonce'  => $nonce,
				)
			)
		);
		$value   = $payload . '.' . $this->signature( $payload );
		if ( ! $this->set_cookie( $value, $expires ) ) {
			delete_post_meta( $record_id, $session_meta );
			delete_post_meta( $record_id, $this->invite_meta( $stage, 'used_at' ), $used_at );
			return false;
		}
		return true;
	}

	/** @return array{record_id:int,stage:string}|null */
	public function session_context(): ?array {
		$value = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( '' === $value && isset( $_COOKIE[ self::COOKIE_FALLBACK ] ) ) {
			$value = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE_FALLBACK ] ) );
		}
		$parts = explode( '.', $value );
		if ( 2 !== count( $parts ) || ! hash_equals( $this->signature( $parts[0] ), $parts[1] ) ) {
			return null;
		}
		$decoded = $this->base64url_decode( $parts[0] );
		$data    = is_string( $decoded ) ? json_decode( $decoded, true ) : null;
		if ( ! is_array( $data ) ) {
			return null;
		}
		$record_id = (int) ( $data['record'] ?? 0 );
		$stage     = sanitize_key( (string) ( $data['stage'] ?? '' ) );
		$expires   = (int) ( $data['exp'] ?? 0 );
		$nonce     = (string) ( $data['nonce'] ?? '' );
		if ( $record_id <= 0 || $expires <= time() || ! $this->valid_stage( $stage ) ) {
			return null;
		}
		$stored = (string) get_post_meta( $record_id, $this->session_meta( $stage ), true );
		if ( '' === $stored || ! hash_equals( $stored, $this->digest( $nonce ) ) ) {
			return null;
		}
		return array(
			'record_id' => $record_id,
			'stage'     => $stage,
		);
	}

	/** Candidate record authorised for a managed form kind. */
	public function record_for_kind( string $kind ): int {
		$context = $this->session_context();
		if ( null === $context || ! $this->stage_allows( $context['stage'], $kind ) ) {
			return 0;
		}
		$status = $this->state->task_status( $context['record_id'], $kind );
		return in_array( $status, array( 'awaiting', 'in_progress', 'needs_correction' ), true ) ? $context['record_id'] : 0;
	}

	public function stage_allows( string $stage, string $kind ): bool {
		if ( TaskPolicy::archived( $kind ) ) {
			return false;
		}
		return ( 'profile' === $stage && 'full' === $kind )
			|| ( 'onboarding' === $stage && in_array( $kind, self::DOCUMENT_KINDS, true ) )
			|| ( in_array( $kind, self::DOCUMENT_KINDS, true ) && 'task_' . $kind === $stage );
	}

	private function valid_stage( string $stage ): bool {
		return in_array( $stage, array_merge( array( 'profile', 'onboarding' ), array_map( static fn( string $kind ): string => 'task_' . $kind, TaskPolicy::documents() ) ), true );
	}

	public function link( string $token ): string {
		return add_query_arg( self::ACCESS_ARG, rawurlencode( $token ), home_url( '/' . self::HUB_SLUG . '/' ) );
	}

	public function revoke_all( int $record_id ): void {
		foreach ( array_merge( array( 'profile', 'onboarding' ), array_map( static fn( string $kind ): string => 'task_' . $kind, self::DOCUMENT_KINDS ) ) as $stage ) {
			delete_post_meta( $record_id, $this->invite_meta( $stage, 'nonce' ) );
			delete_post_meta( $record_id, $this->invite_meta( $stage, 'expires' ) );
			delete_post_meta( $record_id, $this->invite_meta( $stage, 'used_at' ) );
			delete_post_meta( $record_id, $this->session_meta( $stage ) );
		}
	}

	/** Revoke just one archived document's access, retaining the full-pack link. */
	public function revoke_document( int $record_id, string $kind ): void {
		if ( ! in_array( $kind, self::DOCUMENT_KINDS, true ) ) {
			return;
		}
		$stage = 'task_' . $kind;
		foreach ( array( 'nonce', 'expires', 'used_at' ) as $suffix ) {
			delete_post_meta( $record_id, $this->invite_meta( $stage, $suffix ) );
		}
		delete_post_meta( $record_id, $this->session_meta( $stage ) );
	}

	/** Clear this browser's candidate session without exposing record state. */
	public function clear_browser_session(): void {
		$this->clear_cookie_pair( self::COOKIE, self::COOKIE_FALLBACK );
	}

	/** Clear the staged bearer from this browser. */
	public function clear_pending_access(): void {
		$this->clear_cookie_pair( self::PENDING_COOKIE, self::PENDING_COOKIE_FALLBACK );
	}

	/** @return array{record_id:int,stage:string,expires:int}|null */
	private function validate_access( string $token ): ?array {
		$parts = explode( '.', sanitize_text_field( $token ) );
		if ( 6 !== count( $parts ) || self::TOKEN_VERSION !== $parts[0] ) {
			return null;
		}
		[ $version, $record, $stage, $expiry, $nonce, $signature ] = $parts;
		$record_id = (int) $record;
		$expires   = (int) $expiry;
		$unsigned  = implode( '.', array( $version, $record, $stage, $expiry, $nonce ) );
		if ( $record_id <= 0 || $expires <= time() || ! hash_equals( $this->signature( $unsigned ), $signature ) ) {
			return null;
		}
		if ( ! $this->valid_stage( $stage ) ) {
			return null;
		}
		$stored_nonce  = (string) get_post_meta( $record_id, $this->invite_meta( $stage, 'nonce' ), true );
		$stored_expiry = (int) get_post_meta( $record_id, $this->invite_meta( $stage, 'expires' ), true );
		$used_at       = (string) get_post_meta( $record_id, $this->invite_meta( $stage, 'used_at' ), true );
		if ( '' !== $used_at || $stored_expiry !== $expires || ! hash_equals( $stored_nonce, $nonce ) ) {
			return null;
		}
		return array(
			'record_id' => $record_id,
			'stage'     => $stage,
			'expires'   => $expires,
		);
	}

	private function build_token( int $record_id, string $stage, int $expiry, string $nonce ): string {
		$unsigned = implode( '.', array( self::TOKEN_VERSION, $record_id, $stage, $expiry, $nonce ) );
		return $unsigned . '.' . $this->signature( $unsigned );
	}

	private function set_cookie( string $value, int $expires ): bool {
		return $this->set_named_cookie( self::COOKIE, self::COOKIE_FALLBACK, $value, $expires );
	}

	private function set_named_cookie( string $secure_name, string $fallback_name, string $value, int $expires ): bool {
		$secure = is_ssl();
		$name   = $secure ? $secure_name : $fallback_name;
		$stored = setcookie(
			$name,
			$value,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		if ( ! $stored ) {
			return false;
		}
		$_COOKIE[ $name ] = $value;
		return true;
	}

	private function clear_cookie_pair( string $secure_name, string $fallback_name ): void {
		foreach (
			array(
				$secure_name   => true,
				$fallback_name => false,
			) as $name => $secure
		) {
			setcookie(
				$name,
				'',
				array(
					'expires'  => time() - 3600,
					'path'     => '/',
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
			unset( $_COOKIE[ $name ] );
		}
	}

	private function pending_token(): string {
		$value = isset( $_COOKIE[ self::PENDING_COOKIE ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::PENDING_COOKIE ] ) ) : '';
		if ( '' === $value && isset( $_COOKIE[ self::PENDING_COOKIE_FALLBACK ] ) ) {
			$value = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::PENDING_COOKIE_FALLBACK ] ) );
		}
		return $value;
	}

	private function invite_meta( string $stage, string $suffix ): string {
		return 'invite_' . sanitize_key( $stage ) . '_' . sanitize_key( $suffix );
	}

	private function session_meta( string $stage ): string {
		return 'candidate_session_' . sanitize_key( $stage );
	}

	private function signature( string $value ): string {
		return $this->base64url( hash_hmac( 'sha256', $value, wp_salt( 'auth' ), true ) );
	}

	private function digest( string $value ): string {
		return hash_hmac( 'sha256', $value, wp_salt( 'secure_auth' ) );
	}

	private function base64url( string|false $value ): string {
		if ( false === $value ) {
			return '';
		}
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe transport encoding, not obfuscation.
	}

	private function base64url_decode( string $value ): string|false {
		$padding = strlen( $value ) % 4;
		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}
		return base64_decode( strtr( $value, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- signed transport payload.
	}
}
