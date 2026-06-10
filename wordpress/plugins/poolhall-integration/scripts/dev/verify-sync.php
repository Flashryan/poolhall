<?php
/**
 * Phase 2 exit-gate verification. Run inside WordPress:
 *
 *   wp eval-file scripts/dev/verify-sync.php
 *
 * Exercises the real sync pipeline (CPT writes, meta, taxonomies, expiry,
 * lock, logging) against the SYNTHETIC fixture source — no Giig credentials,
 * no real candidate data. Asserts the gate conditions:
 *   1. Fixture jobs land as published poolhall_job posts with correct fields.
 *   2. Repeated sync creates no duplicates.
 *   3. A changed job is updated in place.
 *   4. A removed job is unpublished (small-catalogue path).
 *   5. A forced API failure preserves all existing jobs.
 *   6. The mass-unpublish guard suppresses a suspicious empty response.
 *
 * No strict_types declaration: wp eval-file runs this through eval(), where
 * declare() cannot be the first statement.
 *
 * @package Poolhall\Integration
 */

use Poolhall\Integration\Jobs\ExpiryPolicy;
use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Jobs\JobRepository;
use Poolhall\Integration\Jobs\SyncPlanner;
use Poolhall\Integration\Jobs\SyncService;
use Poolhall\Integration\Source\Giig\GiigNormalizer;
use Poolhall\Integration\Source\JobSource;
use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/verify-sync.php\n";
	exit( 1 );
}

/** Fixture-backed source. */
final class Poolhall_Fixture_Job_Source implements JobSource {
	/** @param array<int,array<string,mixed>> $rows Raw fixture rows. */
	public function __construct( private array $rows ) {}

	public function source_key(): string {
		return 'giig';
	}

	public function fetch_open_jobs(): array {
		$normalizer = new GiigNormalizer();
		return array_map( fn( array $row ): SourceJob => $normalizer->normalize( $row ), $this->rows );
	}

	public function fetch_job( string $source_job_id ): SourceJob {
		foreach ( $this->fetch_open_jobs() as $job ) {
			if ( $job->source_job_id === $source_job_id ) {
				return $job;
			}
		}
		throw new SourceException( 'Not in fixture: ' . $source_job_id );
	}
}

/** Source that always fails, to prove the no-expiry rule. */
final class Poolhall_Failing_Job_Source implements JobSource {
	public function source_key(): string {
		return 'giig';
	}

	public function fetch_open_jobs(): array {
		throw new SourceException( 'Simulated API outage.' );
	}

	public function fetch_job( string $source_job_id ): SourceJob {
		throw new SourceException( 'Simulated API outage.' );
	}
}

$fixture_path = __DIR__ . '/../../tests/fixtures/giig-getjobs.sample.json';
$decoded      = json_decode( (string) file_get_contents( $fixture_path ), true );
$rows         = $decoded['jobs'];

$checks  = array();
$check   = function ( string $name, bool $pass, string $detail = '' ) use ( &$checks ): void {
	$checks[] = array( $name, $pass, $detail );
	printf( "[%s] %s%s\n", $pass ? 'PASS' : 'FAIL', $name, '' === $detail ? '' : ' — ' . $detail );
};
$service = fn( JobSource $source ): SyncService => new SyncService(
	$source,
	new JobRepository(),
	new SyncPlanner(),
	new ExpiryPolicy(),
	new Logger()
);
$published_count = function (): int {
	$q = new WP_Query(
		array(
			'post_type'      => JobPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return count( $q->posts );
};

// Clean slate: remove any prior verification posts.
$all = get_posts(
	array(
		'post_type'      => JobPostType::POST_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $all as $old_id ) {
	wp_delete_post( $old_id, true );
}
delete_option( SyncService::STATE_OPTION );

// 1. First sync creates everything.
$summary = $service( new Poolhall_Fixture_Job_Source( $rows ) )->run( 'verify' );
$check( 'first sync succeeds', 'success' === $summary['status'], wp_json_encode( $summary ) );
$check( 'creates all fixture jobs', 4 === $summary['created'] && 4 === $published_count() );

$repo    = new JobRepository();
$post_id = $repo->find_post_id( 'giig', '27499' );
$check( 'job is findable by source identity', null !== $post_id );
$check( 'salary meta parsed', '55000' === get_post_meta( (int) $post_id, 'salary_min', true ) );
$check( 'expiry set to datePosted + 30d', str_starts_with( (string) get_post_meta( (int) $post_id, 'expires_at', true ), '2026-06-27' ) );
$work_modes = wp_get_object_terms( (int) $post_id, JobPostType::TAX_WORK_MODE, array( 'fields' => 'names' ) );
$check( 'work mode taxonomy assigned', array( 'Onsite' ) === $work_modes );

// 2. Re-run: idempotent, no duplicates.
$summary = $service( new Poolhall_Fixture_Job_Source( $rows ) )->run( 'verify' );
$check( 'repeat sync is a no-op', 0 === $summary['created'] && 0 === $summary['updated'] && 4 === $summary['unchanged'] );
$check( 'no duplicate posts', 4 === $published_count() );

// 3. Changed job updates in place.
$rows[0]['salary'] = '£60,000 - £70,000/Year';
$summary           = $service( new Poolhall_Fixture_Job_Source( $rows ) )->run( 'verify' );
$check( 'changed job is updated, not duplicated', 1 === $summary['updated'] && 4 === $published_count() );
$check( 'updated salary stored', '60000' === get_post_meta( (int) $post_id, 'salary_min', true ) );

// 4. Removed job unpublishes (4 jobs is under the mass-guard minimum of 5).
$removed = array_slice( $rows, 0, 3 );
$summary = $service( new Poolhall_Fixture_Job_Source( $removed ) )->run( 'verify' );
$check( 'missing job is unpublished', 1 === $summary['unpublished'] && 3 === $published_count() );
$gone_id = $repo->find_post_id( 'giig', '27544' );
$check( 'unpublished job kept as draft with reason', 'draft' === get_post_status( (int) $gone_id ) && 'removed_from_source' === get_post_meta( (int) $gone_id, 'source_status', true ) );

// 5. Forced failure: nothing expires, failure is recorded.
$before  = $published_count();
$summary = $service( new Poolhall_Failing_Job_Source() )->run( 'verify' );
$check( 'failed fetch reports failure', 'failed' === $summary['status'] );
$check( 'failed fetch preserves all jobs', $before === $published_count() );

// 6. Mass-unpublish guard: restore to 5+ jobs, then feed an empty response.
$more = $rows;
foreach ( range( 90001, 90003 ) as $i ) {
	$extra              = $rows[0];
	$extra['jobId']     = $i;
	$extra['jobTitle']  = 'Guard Test Role ' . $i;
	$extra['jobReference'] = 'PH-' . $i;
	$more[]             = $extra;
}
$service( new Poolhall_Fixture_Job_Source( $more ) )->run( 'verify' );
$before  = $published_count();
$summary = $service( new Poolhall_Fixture_Job_Source( array() ) )->run( 'verify' );
$check( 'guard suppresses suspicious mass unpublish', true === $summary['unpublish_suppressed'] && $before === $published_count(), (string) ( $summary['guard_reason'] ?? '' ) );

$failed = array_filter( $checks, static fn( array $c ): bool => ! $c[1] );
printf( "\n%d/%d checks passed.\n", count( $checks ) - count( $failed ), count( $checks ) );
exit( array() === $failed ? 0 : 1 );
