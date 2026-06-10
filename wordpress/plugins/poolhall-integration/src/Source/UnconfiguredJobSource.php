<?php
/**
 * Null job source for unconfigured environments.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Stands in when Giig credentials are absent (fresh local install, secrets
 * not yet provisioned). Every fetch fails loudly and safely: the sync
 * records a clear failure and — by hard rule 2 — expires nothing.
 */
final class UnconfiguredJobSource implements JobSource {

	public function source_key(): string {
		return 'giig';
	}

	public function fetch_open_jobs(): array {
		throw new SourceException( 'Giig credentials are not configured (set the POOLHALL_GIIG_* constants in wp-config or environment).' );
	}

	public function fetch_job( string $source_job_id ): SourceJob {
		throw new SourceException( 'Giig credentials are not configured (set the POOLHALL_GIIG_* constants in wp-config or environment).' );
	}
}
