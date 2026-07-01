<?php
/**
 * Giig job source adapter.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\JobSource;
use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Source\SourceJob;

/**
 * JobSource implementation for Giig Hire.
 *
 * Endpoints (api-doc.giighire.com, reviewed 2026-06-10):
 *   GET /public/api/v1/job/getjobs?max={n}
 *   GET /public/api/v1/job/get?JobId={id}&CompanyId={id}
 *
 * Poolhall typically lists ~20 jobs (max ~30); MAX_JOBS is far above that
 * and a response that hits the ceiling is treated as potentially truncated,
 * which marks the fetch incomplete so the sync will not expire anything.
 */
final class GiigJobSource implements JobSource {

	private const MAX_JOBS = 200;

	public function __construct(
		private readonly GiigClient $client,
		private readonly GiigNormalizer $normalizer = new GiigNormalizer(),
		private readonly ?string $company_id = null,
	) {}

	public function source_key(): string {
		return 'giig';
	}

	public function fetch_open_jobs(): array {
		$decoded = $this->client->get_json( '/public/api/v1/job/getjobs', array( 'max' => self::MAX_JOBS ) );
		$rows    = $this->extract_rows( $decoded );

		if ( count( $rows ) >= self::MAX_JOBS ) {
			throw new SourceException( 'Giig getjobs response hit the request ceiling; treating as incomplete.', true );
		}

		$jobs = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				throw new SourceException( 'Giig getjobs returned a non-object row.', true );
			}
			$jobs[] = $this->normalizer->normalize( $row );
		}

		return $jobs;
	}

	public function fetch_job( string $source_job_id ): SourceJob {
		$query = array( 'JobId' => $source_job_id );
		if ( null !== $this->company_id ) {
			$query['CompanyId'] = $this->company_id;
		}

		$decoded = $this->client->get_json( '/public/api/v1/job/get', $query );

		// Tolerate both a bare job object and an envelope around one.
		$row = $this->extract_rows( $decoded )[0] ?? $decoded;
		if ( ! is_array( $row ) || array() === $row ) {
			throw new SourceException( sprintf( 'Giig job %s not found or empty response.', $source_job_id ) );
		}

		return $this->normalizer->normalize( $row );
	}

	/**
	 * Find the job list inside the (Phase 1: exact envelope TBC) response.
	 *
	 * @param array<mixed> $decoded Decoded response.
	 * @return array<int,mixed>
	 */
	private function extract_rows( array $decoded ): array {
		// Bare array of jobs.
		if ( array_is_list( $decoded ) ) {
			return $decoded;
		}
		// Common envelope keys.
		foreach ( array( 'jobs', 'data', 'result', 'results', 'items' ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_array( $decoded[ $key ] ) && array_is_list( $decoded[ $key ] ) ) {
				return $decoded[ $key ];
			}
		}
		// Single job object (job/get).
		return array( $decoded );
	}
}
