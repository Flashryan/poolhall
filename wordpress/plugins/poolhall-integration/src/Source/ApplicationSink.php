<?php
/**
 * Application submission abstraction (architecture doc §4, §6).
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Write side of an ATS. Mode A only — in Mode B the Apply CTA links to the
 * hosted ATS page and this interface is never invoked.
 */
interface ApplicationSink {

	public function capabilities(): ApplicationCapabilities;

	/**
	 * Submit an application. Implementations must be idempotent per
	 * `$payload->idempotency_key` where the upstream API allows it.
	 *
	 * @throws SourceException On any failure; callers queue and retry
	 *                         (never silently lose an application).
	 */
	public function submit( ApplicationPayload $payload ): ApplicationResult;
}
