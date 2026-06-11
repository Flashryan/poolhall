<?php
/**
 * Approximate session device descriptions.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Portal spec §5: the security page shows active sessions with an
 * *approximate* device/browser label. Coarse user-agent family matching is
 * deliberate — no fingerprinting library, no version numbers, nothing a
 * candidate could mistake for tracking. Pure string logic so the families
 * are unit-tested.
 */
final class SessionDescriber {

	/** Match order matters: Chromium UAs also contain "Chrome" and "Safari". */
	private const BROWSERS = array(
		'Edg'            => 'Microsoft Edge',
		'OPR'            => 'Opera',
		'SamsungBrowser' => 'Samsung Internet',
		'FxiOS'          => 'Firefox',
		'Firefox'        => 'Firefox',
		'CriOS'          => 'Chrome',
		'Chrome'         => 'Chrome',
		'Safari'         => 'Safari',
	);

	private const PLATFORMS = array(
		'Windows'   => 'Windows',
		'iPhone'    => 'iPhone',
		'iPad'      => 'iPad',
		'Android'   => 'Android',
		'CrOS'      => 'ChromeOS',
		'Macintosh' => 'Mac',
		'Mac OS X'  => 'Mac',
		'Linux'     => 'Linux',
	);

	/**
	 * @return array{browser:string,platform:string} Family names, or '' when unrecognised.
	 */
	public function describe( string $user_agent ): array {
		return array(
			'browser'  => $this->first_match( $user_agent, self::BROWSERS ),
			'platform' => $this->first_match( $user_agent, self::PLATFORMS ),
		);
	}

	/**
	 * @param array<string,string> $families Marker => family label, in priority order.
	 */
	private function first_match( string $user_agent, array $families ): string {
		foreach ( $families as $marker => $label ) {
			if ( str_contains( $user_agent, $marker ) ) {
				return $label;
			}
		}
		return '';
	}
}
