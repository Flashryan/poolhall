<?php
/**
 * GiigNormalizer tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Source\Giig\GiigNormalizer;
use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Support\WorkMode;

final class GiigNormalizerTest extends TestCase {

	private GiigNormalizer $normalizer;

	/** @var array<int,array<string,mixed>> */
	private array $fixture_jobs;

	protected function setUp(): void {
		$this->normalizer = new GiigNormalizer();
		$decoded          = json_decode(
			(string) file_get_contents( __DIR__ . '/../fixtures/giig-getjobs.sample.json' ),
			true
		);
		$this->fixture_jobs = $decoded['jobs'];
	}

	public function test_normalizes_full_onsite_job(): void {
		$job = $this->normalizer->normalize( $this->fixture_jobs[0] );

		self::assertSame( 'giig', $job->source );
		self::assertSame( '27499', $job->source_job_id );
		self::assertSame( '1182', $job->source_company_id );
		self::assertSame( 'Site Manager', $job->title );
		self::assertSame( WorkMode::Onsite, $job->work_mode );
		self::assertSame( 'Must Be Onsite', $job->work_mode_raw );
		self::assertSame( 'Birmingham', $job->address_locality );
		self::assertSame( 'West Midlands', $job->address_region );
		self::assertSame( 'Construction', $job->sector );
		self::assertSame( 'Full Time', $job->job_type );
		self::assertSame( 55000.0, $job->salary->min );
		self::assertSame( 65000.0, $job->salary->max );
		self::assertSame( '2026-05-28', $job->date_posted?->format( 'Y-m-d' ) );
		self::assertSame( 'PH-27499', $job->job_reference );
	}

	public function test_composes_location_display_from_parts(): void {
		$job = $this->normalizer->normalize( $this->fixture_jobs[0] );

		self::assertSame( 'Birmingham, West Midlands, United Kingdom', $job->location_display );
	}

	public function test_remote_job_with_empty_city_has_no_locality(): void {
		$job = $this->normalizer->normalize( $this->fixture_jobs[3] );

		self::assertSame( WorkMode::Remote, $job->work_mode );
		self::assertNull( $job->address_locality );
		self::assertFalse( $job->salary->is_reliable() );
		self::assertSame( 'Competitive', $job->salary->display );
	}

	public function test_missing_id_is_rejected_as_incomplete(): void {
		$this->expectException( SourceException::class );

		$this->normalizer->normalize( array( 'jobTitle' => 'No ID here' ) );
	}

	public function test_missing_title_is_rejected_as_incomplete(): void {
		$this->expectException( SourceException::class );

		$this->normalizer->normalize( array( 'jobId' => 99 ) );
	}

	public function test_content_hash_is_stable_and_change_sensitive(): void {
		$a = $this->normalizer->normalize( $this->fixture_jobs[0] );
		$b = $this->normalizer->normalize( $this->fixture_jobs[0] );

		self::assertSame( $a->content_hash(), $b->content_hash() );

		$changed              = $this->fixture_jobs[0];
		$changed['salary']    = '£60,000 - £70,000/Year';
		$c                    = $this->normalizer->normalize( $changed );

		self::assertNotSame( $a->content_hash(), $c->content_hash() );
	}

	// --- Live contract (tests/fixtures/giig-getjobs.live.json, recorded 2026-07-01) ---

	public function test_normalizes_live_getjobs_job(): void {
		$job = $this->normalizer->normalize( $this->live_job( 28084 ) );

		self::assertSame( '28084', $job->source_job_id );
		self::assertSame( '242493', $job->source_company_id );
		self::assertSame( 'CAD Technician', $job->title );
		self::assertStringContainsString( 'CAD Technician', $job->description_html );
		self::assertSame( 'West Midlands, UK', $job->location_display );
		self::assertSame( 'Construction & Skilled Trade', $job->sector );
		self::assertSame( 'United Kingdom', $job->address_country );
		self::assertSame( '2026-06-15', $job->date_posted?->format( 'Y-m-d' ) );
	}

	public function test_live_salary_range_is_numeric_but_not_reliable(): void {
		$range = $this->normalizer->normalize( $this->live_job( 27499 ) );
		self::assertSame( 55000.0, $range->salary->min );
		self::assertSame( 65000.0, $range->salary->max );
		self::assertSame( '55,000 - 65,000', $range->salary->display );
		// Currency and period are unmapped enums, so the salary must not be
		// treated as reliable (no schema baseSalary from assumptions).
		self::assertFalse( $range->salary->is_reliable() );

		$single = $this->normalizer->normalize( $this->live_job( 28084 ) );
		self::assertSame( 35000.0, $single->salary->min );
		self::assertSame( 35000.0, $single->salary->max );
		self::assertSame( '35,000', $single->salary->display );
	}

	public function test_live_null_string_fields_are_treated_as_absent(): void {
		// Job 27499's Town/County/Country are the literal string "null".
		$job = $this->normalizer->normalize( $this->live_job( 27499 ) );

		self::assertNull( $job->address_locality );
		self::assertNull( $job->address_region );
		self::assertNull( $job->address_country );
		// LocationName is still real and drives the display.
		self::assertSame( 'Windsor', $job->location_display );
	}

	public function test_live_enum_fields_are_not_guessed(): void {
		$job = $this->normalizer->normalize( $this->live_job( 28084 ) );

		// CandidateRemote / JobType / Experience / EducationRequirement are
		// integer enums with no published legend — they must not be mapped
		// to fabricated labels.
		self::assertSame( WorkMode::Unknown, $job->work_mode );
		self::assertNull( $job->work_mode_raw );
		self::assertNull( $job->job_type );
		self::assertNull( $job->experience_requirement );
		self::assertNull( $job->education_requirement );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function live_job( int $id ): array {
		$decoded = json_decode(
			(string) file_get_contents( __DIR__ . '/../fixtures/giig-getjobs.live.json' ),
			true
		);
		foreach ( $decoded['jobs'] as $job ) {
			if ( (int) $job['JobId'] === $id ) {
				return $job;
			}
		}
		self::fail( sprintf( 'Live fixture is missing job %d.', $id ) );
	}
}
