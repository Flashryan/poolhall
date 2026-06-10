<?php
/**
 * JobPostingSchema tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Schema\JobPostingSchema;
use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\Salary;
use Poolhall\Integration\Support\WorkMode;

final class JobPostingSchemaTest extends TestCase {

	private JobPostingSchema $generator;

	protected function setUp(): void {
		$this->generator = new JobPostingSchema(
			'Poolhall Recruitment Ltd',
			'https://www.poolhallrecruitment.co.uk/',
			'https://www.poolhallrecruitment.co.uk/logo.png'
		);
	}

	private function job( array $overrides = array() ): SourceJob {
		$defaults = array(
			'source'                 => 'giig',
			'source_job_id'          => '27499',
			'title'                  => 'Site Manager',
			'description_html'       => '<p>Run residential sites.</p>',
			'salary'                 => new Salary( '£55,000 - £65,000/Year', 'GBP', 55000.0, 65000.0, 'YEAR' ),
			'work_mode'              => WorkMode::Onsite,
			'work_mode_raw'          => 'Must Be Onsite',
			'location_display'       => 'Birmingham, West Midlands',
			'address_locality'       => 'Birmingham',
			'address_region'         => 'West Midlands',
			'address_country'        => 'United Kingdom',
			'sector'                 => 'Construction',
			'job_type'               => 'Full Time',
			'experience_requirement' => '5+ years',
			'education_requirement'  => null,
			'date_posted'            => new \DateTimeImmutable( '2026-05-28', new \DateTimeZone( 'UTC' ) ),
			'source_company_id'      => '1182',
			'source_url'             => null,
			'job_reference'          => 'PH-27499',
		);
		return new SourceJob( ...array_merge( $defaults, $overrides ) );
	}

	private function build( SourceJob $job, bool $direct_apply = false ): ?array {
		return $this->generator->build(
			$job,
			'https://www.poolhallrecruitment.co.uk/jobs/site-manager-27499/',
			new \DateTimeImmutable( '2026-06-27T09:14:00Z' ),
			$direct_apply
		);
	}

	public function test_onsite_job_has_place_address_and_salary(): void {
		$schema = $this->build( $this->job() );

		self::assertNotNull( $schema );
		self::assertSame( 'JobPosting', $schema['@type'] );
		self::assertSame( 'Site Manager', $schema['title'] );
		self::assertSame( '2026-05-28', $schema['datePosted'] );
		self::assertSame( 'FULL_TIME', $schema['employmentType'] );
		self::assertSame( 'Birmingham', $schema['jobLocation']['address']['addressLocality'] );
		self::assertArrayNotHasKey( 'jobLocationType', $schema );
		self::assertSame( 'GBP', $schema['baseSalary']['currency'] );
		self::assertSame( 55000.0, $schema['baseSalary']['value']['minValue'] );
		self::assertSame( 65000.0, $schema['baseSalary']['value']['maxValue'] );
		self::assertSame( 'YEAR', $schema['baseSalary']['value']['unitText'] );
		self::assertFalse( $schema['directApply'] );
		self::assertSame( 'PH-27499', $schema['identifier']['value'] );
	}

	public function test_hybrid_job_keeps_physical_location_without_telecommute(): void {
		// v1 rule: hybrid = physical location only (remote fields need known
		// applicant geography, which Giig does not supply).
		$schema = $this->build( $this->job( array( 'work_mode' => WorkMode::Hybrid, 'work_mode_raw' => 'Part Remote' ) ) );

		self::assertNotNull( $schema );
		self::assertArrayHasKey( 'jobLocation', $schema );
		self::assertArrayNotHasKey( 'jobLocationType', $schema );
	}

	public function test_remote_job_is_telecommute_with_country(): void {
		$schema = $this->build(
			$this->job(
				array(
					'work_mode'        => WorkMode::Remote,
					'work_mode_raw'    => 'Fully Remote',
					'address_locality' => null,
					'address_region'   => null,
					'location_display' => null,
				)
			)
		);

		self::assertNotNull( $schema );
		self::assertSame( 'TELECOMMUTE', $schema['jobLocationType'] );
		self::assertSame( 'GB', $schema['applicantLocationRequirements']['name'] );
		self::assertArrayNotHasKey( 'jobLocation', $schema );
	}

	public function test_unreliable_salary_is_omitted(): void {
		$schema = $this->build( $this->job( array( 'salary' => new Salary( 'Competitive' ) ) ) );

		self::assertNotNull( $schema );
		self::assertArrayNotHasKey( 'baseSalary', $schema );
	}

	public function test_single_value_salary_uses_value_not_range(): void {
		$schema = $this->build( $this->job( array( 'salary' => new Salary( '£30,000/Year', 'GBP', 30000.0, 30000.0, 'YEAR' ) ) ) );

		self::assertSame( 30000.0, $schema['baseSalary']['value']['value'] );
		self::assertArrayNotHasKey( 'minValue', $schema['baseSalary']['value'] );
	}

	public function test_direct_apply_reflects_mode_a(): void {
		$schema = $this->build( $this->job(), true );

		self::assertTrue( $schema['directApply'] );
	}

	public function test_gate_refuses_when_hiring_org_missing(): void {
		$gated = new JobPostingSchema( '', '' );

		self::assertNull(
			$gated->build( $this->job(), 'https://example.test/job/', new \DateTimeImmutable( '2026-06-27' ), false )
		);
	}

	public function test_gate_refuses_job_without_date_posted(): void {
		self::assertNull( $this->build( $this->job( array( 'date_posted' => null ) ) ) );
	}

	public function test_gate_refuses_onsite_job_without_any_location(): void {
		self::assertNull(
			$this->build(
				$this->job(
					array(
						'address_locality' => null,
						'address_region'   => null,
						'location_display' => null,
					)
				)
			)
		);
	}

	public function test_unknown_job_type_omits_employment_type(): void {
		$schema = $this->build( $this->job( array( 'job_type' => 'Bespoke Arrangement' ) ) );

		self::assertNotNull( $schema );
		self::assertArrayNotHasKey( 'employmentType', $schema );
	}
}
