<?php
/**
 * SyncPlanner tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Jobs\SyncPlanner;
use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\Salary;
use Poolhall\Integration\Support\WorkMode;

final class SyncPlannerTest extends TestCase {

	private SyncPlanner $planner;

	protected function setUp(): void {
		$this->planner = new SyncPlanner();
	}

	private function job( string $id, string $title = 'Role' ): SourceJob {
		return new SourceJob(
			source: 'giig',
			source_job_id: $id,
			title: $title,
			description_html: '<p>Description</p>',
			salary: new Salary( '£30,000/Year', 'GBP', 30000.0, 30000.0, 'YEAR' ),
			work_mode: WorkMode::Onsite,
			work_mode_raw: 'Must Be Onsite',
			location_display: 'Birmingham',
			address_locality: 'Birmingham',
			address_region: 'West Midlands',
			address_country: 'United Kingdom',
			sector: 'Construction',
			job_type: 'Full Time',
			experience_requirement: null,
			education_requirement: null,
			date_posted: new \DateTimeImmutable( '2026-06-01', new \DateTimeZone( 'UTC' ) ),
		);
	}

	public function test_new_jobs_are_created(): void {
		$plan = $this->planner->plan( array(), array( $this->job( '1' ), $this->job( '2' ) ) );

		self::assertCount( 2, $plan->create );
		self::assertSame( array(), $plan->update );
		self::assertSame( array(), $plan->unpublish );
	}

	public function test_unchanged_jobs_are_skipped(): void {
		$job  = $this->job( '1' );
		$plan = $this->planner->plan( array( '1' => $job->content_hash() ), array( $job ) );

		self::assertSame( array(), $plan->create );
		self::assertSame( array(), $plan->update );
		self::assertSame( array( '1' ), $plan->unchanged );
		self::assertTrue( $plan->is_noop() );
	}

	public function test_changed_jobs_are_updated(): void {
		$plan = $this->planner->plan( array( '1' => 'old-hash' ), array( $this->job( '1' ) ) );

		self::assertSame( array(), $plan->create );
		self::assertArrayHasKey( '1', $plan->update );
	}

	public function test_duplicate_source_ids_in_one_response_are_deduplicated(): void {
		$plan = $this->planner->plan( array(), array( $this->job( '1', 'First' ), $this->job( '1', 'Second copy' ) ) );

		self::assertCount( 1, $plan->create );
		self::assertSame( 'First', $plan->create[0]->title );
	}

	public function test_missing_job_is_unpublished_from_complete_response(): void {
		$kept = $this->job( '1' );
		$plan = $this->planner->plan(
			array(
				'1' => $kept->content_hash(),
				'2' => 'hash-of-removed-job',
			),
			array( $kept )
		);

		self::assertSame( array( '2' ), $plan->unpublish );
		self::assertFalse( $plan->unpublish_suppressed );
	}

	public function test_mass_unpublish_is_suppressed(): void {
		// 10 local jobs, source suddenly returns only 1 — guard must fire.
		$local = array();
		foreach ( range( 1, 10 ) as $i ) {
			$local[ (string) $i ] = 'hash-' . $i;
		}

		$survivor = $this->job( '1' );
		$plan     = $this->planner->plan( array( '1' => $survivor->content_hash() ) + $local, array( $survivor ) );

		self::assertSame( array(), $plan->unpublish );
		self::assertTrue( $plan->unpublish_suppressed );
		self::assertNotNull( $plan->guard_reason );
	}

	public function test_empty_source_with_existing_jobs_is_suppressed(): void {
		$local = array(
			'1' => 'h1',
			'2' => 'h2',
			'3' => 'h3',
			'4' => 'h4',
			'5' => 'h5',
		);

		$plan = $this->planner->plan( $local, array() );

		self::assertSame( array(), $plan->unpublish );
		self::assertTrue( $plan->unpublish_suppressed );
	}

	public function test_small_catalogues_may_legitimately_empty(): void {
		// Below the guard minimum (5 local jobs) normal churn applies.
		$plan = $this->planner->plan(
			array(
				'1' => 'h1',
				'2' => 'h2',
			),
			array()
		);

		self::assertSame( array( '1', '2' ), $plan->unpublish );
		self::assertFalse( $plan->unpublish_suppressed );
	}
}
