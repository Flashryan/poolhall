<?php
/**
 * Source exception.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Any ATS communication or contract failure. Messages must be safe for
 * structured logs: correlation context yes, candidate PII or secrets never.
 */
final class SourceException extends \RuntimeException {

	/** True when the response was received but looked partial/invalid. */
	public function __construct(
		string $message,
		public readonly bool $response_incomplete = false,
		?\Throwable $previous = null,
	) {
		parent::__construct( $message, 0, $previous );
	}
}
