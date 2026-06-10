<?php
/**
 * ATS abstraction interfaces (architecture doc §4).
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source;

/**
 * Read side of an ATS. Implementations own all endpoint, auth and payload
 * detail; nothing else in the plugin may know it is talking to Giig.
 */
interface JobSource {

	/** Stable identifier stored against every job (e.g. "giig"). */
	public function source_key(): string;

	/**
	 * Fetch all currently open jobs.
	 *
	 * @throws SourceException When the fetch fails or appears incomplete.
	 *                         Callers must treat an exception as "do not
	 *                         expire anything" (hard rule 2).
	 * @return SourceJob[] Complete list of open jobs.
	 */
	public function fetch_open_jobs(): array;

	/**
	 * Fetch a single job by its source ID.
	 *
	 * @throws SourceException On failure or unknown job.
	 */
	public function fetch_job( string $source_job_id ): SourceJob;
}
