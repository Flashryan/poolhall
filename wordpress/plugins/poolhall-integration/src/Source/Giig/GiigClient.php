<?php
/**
 * Giig HTTP client.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\SourceException;

/**
 * Thin authenticated wrapper over the Giig public API.
 *
 * Auth (api-doc.giighire.com, reviewed 2026-06-10): a Bearer token plus a
 * second header the docs name inconsistently — intro text says
 * `Access-Session-Type`, endpoint tables say `Access-Secret-Key`. The header
 * name is therefore configurable and Phase 1 locks the real one.
 *
 * Secrets come from wp-config constants or environment, never the options
 * table (hard rule 3):
 *   POOLHALL_GIIG_BASE_URL, POOLHALL_GIIG_TOKEN, POOLHALL_GIIG_SECRET,
 *   POOLHALL_GIIG_SECRET_HEADER (optional, default Access-Secret-Key).
 */
final class GiigClient {

	public const DEFAULT_SECRET_HEADER = 'Access-Secret-Key';

	public function __construct(
		private readonly string $base_url,
		private readonly string $token,
		private readonly string $secret,
		private readonly string $secret_header = self::DEFAULT_SECRET_HEADER,
		private readonly int $timeout = 20,
	) {}

	public static function from_environment(): self {
		$base   = self::config( 'POOLHALL_GIIG_BASE_URL' );
		$token  = self::config( 'POOLHALL_GIIG_TOKEN' );
		$secret = self::config( 'POOLHALL_GIIG_SECRET' );
		$header = self::config( 'POOLHALL_GIIG_SECRET_HEADER' ) ?? self::DEFAULT_SECRET_HEADER;

		if ( null === $base || null === $token || null === $secret ) {
			throw new SourceException( 'Giig credentials are not configured (POOLHALL_GIIG_* constants/env).' );
		}
		return new self( rtrim( $base, '/' ), $token, $secret, $header );
	}

	public function is_configured(): bool {
		return '' !== $this->base_url && '' !== $this->token && '' !== $this->secret;
	}

	/**
	 * GET a JSON endpoint.
	 *
	 * @param array<string,string|int> $query Query args.
	 * @return array<mixed> Decoded JSON.
	 * @throws SourceException On transport or contract failure.
	 */
	public function get_json( string $path, array $query = array() ): array {
		$url = $this->base_url . $path;
		if ( array() !== $query ) {
			$url .= '?' . http_build_query( $query );
		}

		$response = \wp_remote_get(
			$url,
			array(
				'timeout' => $this->timeout,
				'headers' => $this->headers(),
			)
		);

		return $this->decode( $response, $path );
	}

	/**
	 * POST a JSON body.
	 *
	 * @param array<string,mixed> $body Request body.
	 * @return array<mixed> Decoded JSON.
	 * @throws SourceException On transport or contract failure.
	 */
	public function post_json( string $path, array $body ): array {
		$response = \wp_remote_post(
			$this->base_url . $path,
			array(
				'timeout' => $this->timeout,
				'headers' => $this->headers( array( 'Content-Type' => 'application/json' ) ),
				'body'    => \wp_json_encode( $body ),
			)
		);

		return $this->decode( $response, $path );
	}

	/**
	 * @param array<string,string> $extra Additional headers.
	 * @return array<string,string>
	 */
	private function headers( array $extra = array() ): array {
		return array_merge(
			array(
				'Authorization'      => 'Bearer ' . $this->token,
				$this->secret_header => $this->secret,
				'Accept'             => 'application/json',
			),
			$extra
		);
	}

	/**
	 * @param array<mixed>|\WP_Error $response WP HTTP response.
	 * @return array<mixed>
	 */
	private function decode( $response, string $path ): array {
		if ( \is_wp_error( $response ) ) {
			throw new SourceException( sprintf( 'Giig request to %s failed: %s', $path, $response->get_error_message() ) );
		}

		$status = (int) \wp_remote_retrieve_response_code( $response );
		$body   = (string) \wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 ) {
			throw new SourceException( sprintf( 'Giig request to %s returned HTTP %d.', $path, $status ) );
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			throw new SourceException( sprintf( 'Giig request to %s returned non-JSON or unexpected JSON.', $path ), true );
		}

		// Live Giig wraps every response in a transport envelope,
		// {"status":<int>,"body":"<json string>"} (recorded 2026-07-01). The
		// inner status carries the API result independently of the HTTP code,
		// so honour it before unwrapping to the real payload.
		if ( isset( $decoded['status'], $decoded['body'] ) && is_numeric( $decoded['status'] ) ) {
			$inner_status = (int) $decoded['status'];
			if ( $inner_status < 200 || $inner_status >= 300 ) {
				throw new SourceException(
					sprintf( 'Giig request to %s returned API status %d.', $path, $inner_status ),
					$inner_status >= 500
				);
			}
		}

		return self::unwrap_envelope( $decoded );
	}

	/**
	 * Unwrap the Giig transport envelope.
	 *
	 * Live responses are shaped {"status":<int>,"body":"<json string>"} where
	 * `body` is a JSON-encoded string holding the real payload. Anything not in
	 * that exact shape passes through unchanged, so non-enveloped or already
	 * unwrapped responses are unaffected.
	 *
	 * @param array<mixed> $decoded Outer decoded JSON.
	 * @return array<mixed> The inner payload, or the input unchanged.
	 */
	public static function unwrap_envelope( array $decoded ): array {
		if ( 2 !== count( $decoded ) || ! array_key_exists( 'status', $decoded ) || ! array_key_exists( 'body', $decoded ) ) {
			return $decoded;
		}

		$inner = $decoded['body'];
		if ( is_array( $inner ) ) {
			return $inner;
		}
		if ( is_string( $inner ) ) {
			$parsed = json_decode( $inner, true );
			if ( is_array( $parsed ) ) {
				return $parsed;
			}
		}

		return $decoded;
	}

	private static function config( string $name ): ?string {
		if ( defined( $name ) ) {
			$value = constant( $name );
			return is_string( $value ) && '' !== $value ? $value : null;
		}
		$env = getenv( $name );
		return false !== $env && '' !== $env ? $env : null;
	}
}
