<?php
/**
 * Failed login attempt history.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Transient-backed failure timestamps per rate-limit key. Keys arrive
 * already hashed (LoginService never hands raw emails or addresses to the
 * options table) and entries expire with the rate-limit window, so a quiet
 * key cleans itself up.
 */
final class FailedLoginStore {

	private const PREFIX = 'poolhall_login_fail_';

	public function __construct( private LoginRateLimiter $limiter ) {}

	/** @return \DateTimeImmutable[] */
	public function failures( string $key ): array {
		$raw     = get_transient( self::PREFIX . $key );
		$decoded = is_string( $raw ) ? (array) json_decode( $raw, true ) : array();
		$history = array();
		foreach ( $decoded as $timestamp ) {
			if ( is_string( $timestamp ) ) {
				$history[] = new \DateTimeImmutable( $timestamp );
			}
		}
		return $history;
	}

	public function record_failure( string $key, \DateTimeImmutable $now ): void {
		$history   = $this->limiter->prune( $this->failures( $key ), $now );
		$history[] = $now;
		set_transient(
			self::PREFIX . $key,
			wp_json_encode(
				array_map(
					static fn( \DateTimeImmutable $failed_at ): string => $failed_at->format( \DateTimeInterface::ATOM ),
					$history
				)
			),
			LoginRateLimiter::WINDOW_SECONDS
		);
	}

	public function clear( string $key ): void {
		delete_transient( self::PREFIX . $key );
	}
}
