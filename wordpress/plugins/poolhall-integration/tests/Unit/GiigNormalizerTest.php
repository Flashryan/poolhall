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
}
