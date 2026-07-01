<?php
/**
 * Giig payload normalizer.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\SourceException;
use Poolhall\Integration\Source\SourceJob;
use Poolhall\Integration\Support\Salary;
use Poolhall\Integration\Support\SalaryParser;
use Poolhall\Integration\Support\WorkMode;

/**
 * Turns one raw Giig job payload into a SourceJob.
 *
 * Phase 1 contract (live response recorded 2026-07-01 from
 * https://giigapi.com/public/api/v1/job/getjobs, see
 * tests/fixtures/giig-getjobs.live.json): the real API returns PascalCase
 * fields inside a transport envelope (unwrapped by GiigClient). The KEYS map
 * below leads with the confirmed live names; the earlier camelCase guesses
 * are kept so the synthetic fixture and any other source shape still work.
 *
 * Integer enums: the v2 build directive (CLAUDE-CODE-DIRECTIVE.md §2.2 /
 * docs/12 §2.2) publishes the legend for JobType (0=Permanent 1=Temporary),
 * Currency (0=£ 1=$ 2=€) and SalaryPeriod (perm 0=year 1=month 2=week,
 * temp 3=day 4=hour) — now mapped below. Deliberately still NOT mapped:
 * CandidateRemote, Experience, EducationRequirement and DisplaySalary have
 * no published legend (e.g. job #28084 is described as hybrid yet
 * CandidateRemote=0), so they stay unmapped rather than guessed.
 */
final class GiigNormalizer {

	private const JOB_TYPES = array(
		0 => 'Permanent',
		1 => 'Temporary',
	);

	private const CURRENCIES = array(
		0 => 'GBP',
		1 => 'USD',
		2 => 'EUR',
	);

	private const CURRENCY_SYMBOLS = array(
		'GBP' => '£',
		'USD' => '$',
		'EUR' => '€',
	);

	private const SALARY_PERIODS = array(
		0 => 'YEAR',
		1 => 'MONTH',
		2 => 'WEEK',
		3 => 'DAY',
		4 => 'HOUR',
	);

	private const PERIOD_LABELS = array(
		'YEAR'  => 'year',
		'MONTH' => 'month',
		'WEEK'  => 'week',
		'DAY'   => 'day',
		'HOUR'  => 'hour',
	);

	private const KEYS = array(
		'id'          => array( 'JobId', 'jobId', 'id', 'Id' ),
		'company_id'  => array( 'CompanyId', 'companyId' ),
		'title'       => array( 'Title', 'jobTitle', 'title', 'JobTitle', 'name' ),
		'description' => array( 'JobDescription', 'jobDescription', 'description', 'Description' ),
		// Legacy string path; live jobs use SalaryFrom/SalaryTo (see resolve_salary()).
		'salary'      => array( 'salary', 'Salary', 'salaryRange', 'SalaryRange' ),
		// CandidateRemote (live) is an int enum — pending legend, deliberately unmapped.
		'work_mode'   => array( 'remoteOnsite', 'RemoteOnsite', 'workMode', 'remote', 'Remote', 'workplaceType' ),
		'locality'    => array( 'Town', 'city', 'City', 'town' ),
		'region'      => array( 'County', 'state', 'State', 'region', 'county' ),
		'country'     => array( 'Country', 'country' ),
		'location'    => array( 'LocationName', 'location', 'Location', 'jobLocation' ),
		'sector'      => array( 'Industry', 'industry', 'sector' ),
		// JobType (live) is an int enum — pending legend, deliberately unmapped.
		'job_type'    => array( 'jobType', 'employmentType' ),
		// Experience (live) is an int enum — pending legend, deliberately unmapped.
		'experience'  => array( 'experienceRequired', 'ExperienceRequired', 'experience' ),
		// EducationRequirement (live) is an int enum — pending legend, deliberately unmapped.
		'education'   => array( 'educationRequired', 'EducationRequired', 'education' ),
		'date_posted' => array( 'CreatedDate', 'datePosted', 'DatePosted', 'createdAt', 'postedDate', 'created' ),
		'reference'   => array( 'jobReference', 'reference', 'JobReference' ),
		'url'         => array( 'jobUrl', 'url', 'JobUrl', 'applyUrl' ),
	);

	public function __construct(
		private readonly SalaryParser $salary_parser = new SalaryParser(),
	) {}

	/**
	 * @param array<string,mixed> $raw One job object from the Giig API.
	 * @throws SourceException When required fields are missing.
	 */
	public function normalize( array $raw ): SourceJob {
		$id    = $this->pick( $raw, 'id' );
		$title = $this->pick( $raw, 'title' );

		if ( null === $id || '' === trim( (string) $id ) ) {
			throw new SourceException( 'Giig job payload has no recognizable job ID.', true );
		}
		if ( null === $title || '' === trim( (string) $title ) ) {
			throw new SourceException( sprintf( 'Giig job %s has no recognizable title.', (string) $id ), true );
		}

		$work_mode_raw = $this->str( $raw, 'work_mode' );
		$date_posted   = $this->date( $this->str( $raw, 'date_posted' ) );

		return new SourceJob(
			source: 'giig',
			source_job_id: (string) $id,
			title: trim( (string) $title ),
			description_html: (string) ( $this->pick( $raw, 'description' ) ?? '' ),
			salary: $this->resolve_salary( $raw ),
			work_mode: WorkMode::from_source( $work_mode_raw ),
			work_mode_raw: $work_mode_raw,
			location_display: $this->str( $raw, 'location' ) ?? $this->compose_location( $raw ),
			address_locality: $this->str( $raw, 'locality' ),
			address_region: $this->str( $raw, 'region' ),
			address_country: $this->str( $raw, 'country' ),
			sector: $this->str( $raw, 'sector' ),
			job_type: $this->enum( $raw, 'JobType', self::JOB_TYPES ) ?? $this->str( $raw, 'job_type' ),
			experience_requirement: $this->str( $raw, 'experience' ),
			education_requirement: $this->str( $raw, 'education' ),
			date_posted: $date_posted,
			source_company_id: $this->str( $raw, 'company_id' ),
			source_url: $this->str( $raw, 'url' ),
			job_reference: $this->str( $raw, 'reference' ),
		);
	}

	/**
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function pick( array $raw, string $field ): mixed {
		foreach ( self::KEYS[ $field ] as $key ) {
			if ( array_key_exists( $key, $raw ) && $this->is_present( $raw[ $key ] ) ) {
				return $raw[ $key ];
			}
		}
		return null;
	}

	/**
	 * Whether a raw value counts as a real value. Live Giig serialises empty
	 * fields as the literal strings "null" and "undefined" (e.g. job #27499
	 * has Town/County/Country = "null"), which must be treated as absent so we
	 * never store the word "null" as a region or country.
	 */
	private function is_present( mixed $value ): bool {
		if ( null === $value || '' === $value ) {
			return false;
		}
		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );
			return '' !== $normalized && 'null' !== $normalized && 'undefined' !== $normalized;
		}
		return true;
	}

	/**
	 * Build the salary.
	 *
	 * Live Giig sends numeric SalaryFrom/SalaryTo (strings like "35000.0000")
	 * plus Currency and SalaryPeriod integer enums, decoded per the v2
	 * directive legend (§2.2). With currency and period resolved the salary
	 * is schema-reliable (baseSalary can be emitted). Display follows the
	 * design contract's "£60,000–£75,000 / year" shape — an en-dash inside a
	 * numeric range is allowed by guardrail #3. Other sources still flow
	 * through the display-string parser.
	 *
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function resolve_salary( array $raw ): Salary {
		$from = $this->number( $raw, 'SalaryFrom' );
		$to   = $this->number( $raw, 'SalaryTo' );

		if ( null !== $from || null !== $to ) {
			$min = $from ?? $to;
			$max = $to ?? $from;
			if ( $max < $min ) {
				[ $min, $max ] = array( $max, $min );
			}

			$currency = $this->enum( $raw, 'Currency', self::CURRENCIES );
			$period   = $this->enum( $raw, 'SalaryPeriod', self::SALARY_PERIODS );

			$symbol  = null !== $currency ? ( self::CURRENCY_SYMBOLS[ $currency ] ?? '' ) : '';
			$amount  = number_format( $min ) === number_format( $max )
				? $symbol . number_format( $min )
				: $symbol . number_format( $min ) . "\u{2013}" . $symbol . number_format( $max );
			$display = null !== $period
				? $amount . ' / ' . self::PERIOD_LABELS[ $period ]
				: $amount;

			return new Salary( $display, $currency, $min, $max, $period );
		}

		return $this->salary_parser->parse( $this->str( $raw, 'salary' ) );
	}

	/**
	 * Decode an integer enum via a directive-published legend. Values outside
	 * the legend (or non-numeric) resolve to null — unknown, never guessed.
	 *
	 * @param array<string,mixed>   $raw    Raw payload.
	 * @param array<int,string>     $legend Enum legend.
	 */
	private function enum( array $raw, string $key, array $legend ): ?string {
		if ( ! array_key_exists( $key, $raw ) || ! is_numeric( $raw[ $key ] ) ) {
			return null;
		}
		return $legend[ (int) $raw[ $key ] ] ?? null;
	}

	/**
	 * Read a positive numeric field (Giig sends salaries as decimal strings;
	 * a zero or non-numeric value means "not set").
	 *
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function number( array $raw, string $key ): ?float {
		if ( ! array_key_exists( $key, $raw ) || ! is_numeric( $raw[ $key ] ) ) {
			return null;
		}
		$value = (float) $raw[ $key ];
		return $value > 0 ? $value : null;
	}

	/**
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function str( array $raw, string $field ): ?string {
		$value = $this->pick( $raw, $field );
		if ( null === $value || is_array( $value ) || is_object( $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return '' === $value ? null : $value;
	}

	/**
	 * @param array<string,mixed> $raw Raw payload.
	 */
	private function compose_location( array $raw ): ?string {
		$parts = array_filter(
			array( $this->str( $raw, 'locality' ), $this->str( $raw, 'region' ), $this->str( $raw, 'country' ) )
		);
		return array() === $parts ? null : implode( ', ', $parts );
	}

	private function date( ?string $value ): ?\DateTimeImmutable {
		if ( null === $value ) {
			return null;
		}
		try {
			return new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception ) {
			return null;
		}
	}
}
