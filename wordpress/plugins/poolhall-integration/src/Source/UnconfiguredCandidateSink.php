<?php
/**
 * Null candidate sink used until the ATS is configured.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Stand-in when no ATS credentials (or base URL) are present. Every push
 * fails loudly so the exporter takes its never-lose path: keep the record
 * and notify Poolhall by email until the live Giig endpoint is configured.
 */
final class UnconfiguredCandidateSink implements CandidateSink {

	public function is_configured(): bool {
		return false;
	}

	public function register_candidate( CandidatePayload $payload ): CandidateResult {
		throw new SourceException( 'Candidate sink is not configured (no ATS base URL/credentials).' );
	}
}
