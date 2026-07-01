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
	public const APPLY_CHANNEL    = 'poolhall_apply_channel';

	/** Mode values: 'unset' until Phase 1 proves the contract; then 'a' or 'b'. */
	public function application_mode(): string {
		$mode = (string) get_option( self::APPLICATION_MODE, 'unset' );
		return in_array( $mode, array( 'a', 'b' ), true ) ? $mode : 'unset';
	}

	/**
	 * How the Apply CTA behaves. `email` = first-party popup that emails the
	 * application (with CV) to Poolhall; `contact` = route to the contact
	 * page (the safe default before the channel is switched on). Separate
	 * from the Giig `application_mode`: emailing Poolhall never proxies
	 * Giig's hosted form (hard rule 5) and leaves schema directApply off.
	 */
	public function apply_channel(): string {
		$channel = (string) get_option( self::APPLY_CHANNEL, 'contact' );
		return in_array( $channel, array( 'email', 'contact' ), true ) ? $channel : 'contact';
	}

	public function apply_to_poolhall_enabled(): bool {
		return 'email' === $this->apply_channel();
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
