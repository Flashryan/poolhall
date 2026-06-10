<?php
/**
 * Plugin options.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Support;

/**
 * Non-secret plugin configuration. Secrets never live here (hard rule 3) —
 * they come from wp-config constants/environment via GiigClient.
 */
final class Options {

	public const APPLICATION_MODE = 'poolhall_application_mode';

	/** Mode values: 'unset' until Phase 1 proves the contract; then 'a' or 'b'. */
	public function application_mode(): string {
		$mode = (string) get_option( self::APPLICATION_MODE, 'unset' );
		return in_array( $mode, array( 'a', 'b' ), true ) ? $mode : 'unset';
	}

	/** First-party application UI may only render in proven Mode A. */
	public function first_party_apply_enabled(): bool {
		return 'a' === $this->application_mode();
	}

	/** schema.org directApply may only be true in Mode A. */
	public function direct_apply(): bool {
		return 'a' === $this->application_mode();
	}

	public function hiring_org_name(): string {
		return (string) get_option( 'poolhall_hiring_org_name', get_bloginfo( 'name' ) );
	}

	public function hiring_org_url(): string {
		return (string) get_option( 'poolhall_hiring_org_url', home_url( '/' ) );
	}

	public function hiring_org_logo(): ?string {
		$logo = (string) get_option( 'poolhall_hiring_org_logo', '' );
		return '' === $logo ? null : $logo;
	}
}
