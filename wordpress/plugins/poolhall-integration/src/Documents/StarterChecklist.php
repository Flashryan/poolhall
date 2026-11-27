<?php
/**
 * HMRC Starter Checklist: field map, statement logic and official wording.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

/**
 * The starter task is HMRC's Starter checklist (HMRC 09/22) completed
 * online. This class owns the Gravity Forms field numbers, the HMRC rule that
 * turns questions 8-10 into statement A, B or C (so the candidate never has
 * to pick one), the HMRC wording reproduced in the PDF, and the one-line
 * payroll summary shown in the recruiter cockpit.
 *
 * Field numbers start at 20 so entries made on the earlier Starter Statement
 * form keep their meaning. Only the declaration block (14-17) is shared,
 * because it means the same thing on both.
 *
 * Kept free of WordPress calls so the logic can be tested on its own.
 */
final class StarterChecklist {

	public const INTRO       = 20;
	public const LAST_NAME   = 21;
	public const FIRST_NAMES = 22;
	public const SEX         = 23;
	public const BIRTH_DATE  = 24;
	public const NI_NUMBER   = 25;
	public const START_DATE  = 26;
	public const ADDRESS_1   = 28;
	public const ADDRESS_2   = 29;
	public const TOWN        = 30;
	public const COUNTY      = 31;
	public const POSTCODE    = 32;
	public const COUNTRY     = 33;
	public const ANOTHER_JOB = 36;
	public const PENSION     = 37;
	public const SINCE_APRIL = 38;
	public const STATEMENT   = 42;
	public const P45         = 44;
	public const P45_UPLOAD  = 45;
	public const HAS_LOAN    = 49;
	public const LOAN_EXEMPT = 50;
	public const LOAN_TYPES  = 52;

	public const P45_UPLOADED = 'Yes - uploaded';
	public const P45_LATER    = 'Yes - will provide later';
	public const P45_NONE     = 'No';

	public const LOAN_NOT_SURE = 'Not sure';

	/**
	 * Student loan tick boxes: stored value => help shown under the box. Plan 5
	 * post-dates HMRC's 09/22 form but is collected through PAYE, so it is
	 * offered alongside the plans the form lists.
	 */
	public const LOAN_CHOICES = array(
		'Plan 1'            => 'You lived in Northern Ireland when you started your course, or you lived in England or Wales and started your course before 1 September 2012.',
		'Plan 2'            => 'You lived in England and started your course between 1 September 2012 and 31 July 2023, or you lived in Wales and started your course on or after 1 September 2012.',
		'Plan 4'            => 'You lived in Scotland and applied through the Student Awards Agency Scotland (SAAS) when you started your course.',
		'Plan 5'            => 'You lived in England and started your course on or after 1 August 2023.',
		'Postgraduate loan' => 'England and Wales only. You started a master\'s course on or after 1 August 2016 (England) or 1 August 2017 (Wales), or a doctoral course on or after 1 August 2018.',
		'Not sure'          => 'You can check which plan you are on in your Student Loans Company account.',
	);

	/** HMRC's statement wording, word for word. */
	public const STATEMENTS = array(
		'A' => 'This is my first job since 6 April and since the 6 April I have not received payments from any of the following: Jobseeker\'s Allowance, Employment and Support Allowance, Incapacity Benefit.',
		'B' => 'Since 6 April I have had another job but I do not have a P45. And/or since the 6 April I have received payments from any of the following: Jobseeker\'s Allowance, Employment and Support Allowance, Incapacity Benefit.',
		'C' => 'I have another job and/or I am in receipt of a State, workplace or private pension.',
	);

	/** What HMRC tells the employer to do for each statement. */
	public const TAX_CODES = array(
		'A' => 'use the current personal allowance',
		'B' => 'use the current personal allowance on a week 1/month 1 basis',
		'C' => 'use tax code BR',
	);

	/**
	 * HMRC questions 8-10. "Yes" to 8 or 9 is statement C; otherwise
	 * question 10 decides between B and A. Answers to later questions are
	 * ignored once an earlier one has decided, which is also what makes the
	 * result safe against stale values from fields the form has hidden.
	 */
	public static function statement( string $another_job, string $pension, string $since_april ): string {
		if ( 'Yes' === $another_job ) {
			return 'C';
		}
		if ( 'No' !== $another_job ) {
			return '';
		}
		if ( 'Yes' === $pension ) {
			return 'C';
		}
		if ( 'No' !== $pension ) {
			return '';
		}
		return match ( $since_april ) {
			'Yes'   => 'B',
			'No'    => 'A',
			default => '',
		};
	}

	/** @param array<int|string,mixed> $entry Gravity Forms entry. */
	public static function statement_for_entry( array $entry ): string {
		return self::statement(
			self::value( $entry, self::ANOTHER_JOB ),
			self::value( $entry, self::PENSION ),
			self::value( $entry, self::SINCE_APRIL )
		);
	}

	/** @param array<int|string,mixed> $entry Gravity Forms entry. */
	public static function value( array $entry, int|string $id ): string {
		$value = $entry[ (string) $id ] ?? '';
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Ticked student loans, in the order the form lists them.
	 *
	 * @param array<int|string,mixed> $entry Gravity Forms entry.
	 * @return string[]
	 */
	public static function loan_types( array $entry ): array {
		$types = array();
		$count = count( self::LOAN_CHOICES );
		for ( $i = 1; $i <= $count; $i++ ) {
			$value = self::value( $entry, self::LOAN_TYPES . '.' . $i );
			if ( '' !== $value ) {
				$types[] = $value;
			}
		}
		return $types;
	}

	/**
	 * What payroll should deduct for student loans, in plain words.
	 *
	 * @param array<int|string,mixed> $entry Gravity Forms entry.
	 */
	public static function loan_summary( array $entry ): string {
		$has = self::value( $entry, self::HAS_LOAN );
		if ( 'No' === $has ) {
			return 'None - no student or postgraduate loan';
		}
		if ( 'Yes' !== $has ) {
			return 'Not answered';
		}
		if ( 'Yes' === self::value( $entry, self::LOAN_EXEMPT ) ) {
			return 'None for now - has a loan, but question 12 applies (still studying, left the course after 6 April, repaid in full, or paying the SLC by Direct Debit)';
		}
		$types = self::loan_types( $entry );
		if ( array() === $types ) {
			return 'Not answered';
		}
		$known = array_values( array_diff( $types, array( self::LOAN_NOT_SURE ) ) );
		if ( count( $known ) === count( $types ) ) {
			return implode( ', ', $known );
		}
		return ( array() === $known ? 'Has a loan but is not sure of the plan type' : implode( ', ', $known ) . ' - and is not sure of any other plan type' )
			. ' - confirm with the candidate';
	}

	/** @param array<int|string,mixed> $entry Gravity Forms entry. */
	public static function p45_summary( array $entry ): string {
		return match ( self::value( $entry, self::P45 ) ) {
			self::P45_UPLOADED => 'Uploaded - stored securely with this record',
			self::P45_LATER    => 'Has a P45 and will provide it later',
			self::P45_NONE     => 'No P45',
			default            => 'Not answered',
		};
	}

	/**
	 * The recruiter cockpit's one-line view of the checklist. Contains no
	 * identity data: no name, date of birth, NI number or address.
	 *
	 * @param array<int|string,mixed> $entry Gravity Forms entry.
	 */
	public static function summary_line( array $entry ): string {
		$statement = self::statement_for_entry( $entry );
		$parts     = array(
			'' !== $statement ? 'Statement ' . $statement . ' (' . self::TAX_CODES[ $statement ] . ')' : 'Statement not determined',
			'Student loan: ' . self::short_loan( $entry ),
			'P45: ' . match ( self::value( $entry, self::P45 ) ) {
				self::P45_UPLOADED => 'uploaded',
				self::P45_LATER    => 'to follow',
				self::P45_NONE     => 'none',
				default            => 'not answered',
			},
		);
		return implode( ' · ', $parts );
	}

	/** @param array<int|string,mixed> $entry Gravity Forms entry. */
	private static function short_loan( array $entry ): string {
		if ( 'No' === self::value( $entry, self::HAS_LOAN ) ) {
			return 'none';
		}
		if ( 'Yes' === self::value( $entry, self::LOAN_EXEMPT ) ) {
			return 'no deductions for now (Q12)';
		}
		$types = self::loan_types( $entry );
		if ( array() === $types ) {
			return 'not answered';
		}
		return str_replace( self::LOAN_NOT_SURE, 'plan type unknown', implode( ', ', $types ) );
	}

	/**
	 * First names then last name, as entered.
	 *
	 * @param array<int|string,mixed> $entry Gravity Forms entry.
	 */
	public static function full_name( array $entry ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', self::value( $entry, self::FIRST_NAMES ) . ' ' . self::value( $entry, self::LAST_NAME ) ) );
	}

	/**
	 * HMRC asks for the declaration name in capital letters.
	 *
	 * @param array<int|string,mixed> $entry Gravity Forms entry.
	 */
	public static function signer( array $entry ): string {
		return mb_strtoupper( self::full_name( $entry ), 'UTF-8' );
	}

	/**
	 * @param array<int|string,mixed> $entry Gravity Forms entry.
	 * @return string[]
	 */
	public static function address_lines( array $entry ): array {
		$lines = array();
		foreach ( array( self::ADDRESS_1, self::ADDRESS_2, self::TOWN, self::COUNTY, self::POSTCODE, self::COUNTRY ) as $id ) {
			$line = self::value( $entry, $id );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/** Upper-case letters and digits only, the way HMRC compares NI numbers. */
	public static function compact_ni( string $value ): string {
		return strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', $value ) );
	}

	/** HMRC's published National Insurance number format; temporary numbers are rejected. */
	public static function valid_ni( string $value ): bool {
		return 1 === preg_match( '/^(?!BG|GB|KN|NK|NT|TN|ZZ)[A-CEGHJ-PR-TW-Z][A-CEGHJ-NPR-TW-Z][0-9]{6}[A-D]$/', self::compact_ni( $value ) );
	}

	/** "qq123456c" becomes "QQ 12 34 56 C"; anything unrecognised is returned trimmed. */
	public static function format_ni( string $value ): string {
		if ( ! self::valid_ni( $value ) ) {
			return trim( $value );
		}
		$c = self::compact_ni( $value );
		return implode( ' ', array( substr( $c, 0, 2 ), substr( $c, 2, 2 ), substr( $c, 4, 2 ), substr( $c, 6, 2 ), substr( $c, 8, 1 ) ) );
	}

	/** "b31rb" becomes "B3 1RB"; non-UK postcodes are left as typed. */
	public static function format_postcode( string $value ): string {
		$compact = strtoupper( (string) preg_replace( '/\s+/', '', $value ) );
		if ( 1 === preg_match( '/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/', $compact ) ) {
			return substr( $compact, 0, -3 ) . ' ' . substr( $compact, -3 );
		}
		return trim( $value );
	}

	/** Gravity Forms stores dates as Y-m-d; show them the UK way. */
	public static function uk_date( string $value ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', trim( $value ) );
		return false === $date ? trim( $value ) : $date->format( 'd/m/Y' );
	}

	/** 6 April that starts the UK tax year containing $at (UK time). */
	public static function tax_year_start( ?\DateTimeImmutable $at = null ): \DateTimeImmutable {
		$zone  = new \DateTimeZone( 'Europe/London' );
		$at    = ( $at ?? new \DateTimeImmutable( 'now', $zone ) )->setTimezone( $zone );
		$start = new \DateTimeImmutable( $at->format( 'Y' ) . '-04-06 00:00:00', $zone );
		return $at < $start ? $start->modify( '-1 year' ) : $start;
	}

	public static function tax_year_label( ?\DateTimeImmutable $at = null ): string {
		$year = (int) self::tax_year_start( $at )->format( 'Y' );
		return $year . ' to ' . ( $year + 1 );
	}

	/**
	 * When the entry was made; Gravity Forms records it in UTC.
	 *
	 * @param array<int|string,mixed> $entry Gravity Forms entry.
	 */
	public static function submitted_at( array $entry ): ?\DateTimeImmutable {
		$created = self::value( $entry, 'date_created' );
		if ( '' === $created ) {
			return null;
		}
		try {
			return new \DateTimeImmutable( $created, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $exception ) {
			unset( $exception );
			return null;
		}
	}
}
