<?php
/**
 * Salary value object.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Support;

/**
 * Parsed salary. `display` always holds the original source string so the
 * site never shows a lossy reconstruction; numeric fields feed schema
 * `baseSalary` and the salary filter, and are null when parsing was not
 * reliable (schema rule: only emit baseSalary when reliable).
 */
final class Salary {
	public function __construct(
		public readonly string $display,
		public readonly ?string $currency = null,
		public readonly ?float $min = null,
		public readonly ?float $max = null,
		public readonly ?string $period = null,
	) {}

	public function is_reliable(): bool {
		return null !== $this->currency && null !== $this->min && null !== $this->period;
	}
}
