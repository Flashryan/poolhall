<?php
/**
 * Candidate session listing and revocation.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Portal spec §5: list active sessions and revoke one other session or all
 * other sessions. Core's `WP_Session_Tokens` API covers destroy-others but
 * not addressing a single *other* session, so listing and single revocation
 * read the `session_tokens` user meta directly — the same verifier-keyed
 * structure `WP_User_Meta_Session_Tokens` maintains (stable since WP 4.0;
 * exercised by the verify-accounts integration checks so a core format
 * change fails loudly). Verifiers are SHA-256 digests of the cookie token:
 * safe to surface to the session's own user, never reversible to a cookie.
 */
final class SessionRegistry {

	private const META_KEY = 'session_tokens';

	/**
	 * Active (unexpired) sessions, current first, then most recent sign-in.
	 *
	 * @param string $current_token Raw session token from the caller's auth cookie.
	 * @return array<int,array{verifier:string,login:int,expiration:int,ip:string,ua:string,current:bool}>
	 */
	public function list_sessions( int $user_id, string $current_token ): array {
		$current_verifier = $this->verifier( $current_token );
		$now              = time();

		$sessions = array();
		foreach ( $this->read( $user_id ) as $verifier => $session ) {
			if ( ! is_array( $session ) || (int) ( $session['expiration'] ?? 0 ) < $now ) {
				continue;
			}
			$sessions[] = array(
				'verifier'   => (string) $verifier,
				'login'      => (int) ( $session['login'] ?? 0 ),
				'expiration' => (int) ( $session['expiration'] ?? 0 ),
				'ip'         => (string) ( $session['ip'] ?? '' ),
				'ua'         => (string) ( $session['ua'] ?? '' ),
				'current'    => hash_equals( $current_verifier, (string) $verifier ),
			);
		}

		usort(
			$sessions,
			static function ( array $a, array $b ): int {
				if ( $a['current'] !== $b['current'] ) {
					return $a['current'] ? -1 : 1;
				}
				return $b['login'] <=> $a['login'];
			}
		);
		return $sessions;
	}

	/**
	 * Revoke one *other* session by verifier. The current session is refused
	 * (spec §5 wording — signing out happens through the sign-out form) and
	 * ownership holds by construction: only $user_id's meta is touched.
	 */
	public function revoke( int $user_id, string $verifier, string $current_token ): bool {
		if ( '' === $verifier || hash_equals( $this->verifier( $current_token ), $verifier ) ) {
			return false;
		}
		$sessions = $this->read( $user_id );
		if ( ! array_key_exists( $verifier, $sessions ) ) {
			return false;
		}
		unset( $sessions[ $verifier ] );
		update_user_meta( $user_id, self::META_KEY, $sessions );
		return true;
	}

	/** @return int Number of sessions revoked. */
	public function revoke_others( int $user_id, string $current_token ): int {
		$active = count( $this->list_sessions( $user_id, $current_token ) );
		\WP_Session_Tokens::get_instance( $user_id )->destroy_others( $current_token );
		return max( 0, $active - 1 );
	}

	/** @return array<string,mixed> Verifier-keyed sessions as core stores them. */
	private function read( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $raw ) ? $raw : array();
	}

	/** Mirrors WP_Session_Tokens::hash_token(): the stored key for a raw token. */
	private function verifier( string $token ): string {
		return hash( 'sha256', $token );
	}
}
