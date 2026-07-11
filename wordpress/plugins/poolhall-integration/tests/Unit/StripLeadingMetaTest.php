<?php
/**
 * V2Fragments::strip_leading_meta tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\DesignSystem\V2Fragments;

final class StripLeadingMetaTest extends TestCase {

	public function test_leading_meta_lines_are_removed_keeping_the_title_line(): void {
		$text = "HVAC / Refrigeration Engineer - West Midlands Field-based role\n"
			. "Location: West Midlands, UK\n"
			. "Travel: Regional\n"
			. "Job Type: Full-time\n"
			. "Salary: £50,000 - £55,000 per annum (depending on experience)\n"
			. "Start Date: ASAP\n"
			. "\n"
			. 'We are seeking an experienced engineer to join our friendly team.';

		$result = V2Fragments::strip_leading_meta( $text );

		self::assertStringContainsString( 'HVAC / Refrigeration Engineer', $result );
		self::assertStringContainsString( 'We are seeking an experienced engineer', $result );
		self::assertStringNotContainsString( 'Location: West Midlands', $result );
		self::assertStringNotContainsString( 'Salary: £50,000', $result );
		self::assertStringNotContainsString( 'Start Date:', $result );
	}

	public function test_a_single_meta_looking_line_is_left_alone(): void {
		$text = "Location: our client has sites nationwide, so travel matters.\n\nThe role itself is field-based.";

		self::assertSame( $text, V2Fragments::strip_leading_meta( $text ) );
	}

	public function test_meta_deep_in_the_body_is_untouched(): void {
		$lines = array_fill( 0, 14, 'A paragraph of real description content that keeps going.' );
		$text  = implode( "\n", $lines ) . "\nLocation: London\nSalary: £40,000";

		self::assertSame( $text, V2Fragments::strip_leading_meta( $text ) );
	}

	public function test_single_block_html_without_newlines_is_untouched(): void {
		$text = '<p>A proper paragraph that mentions Location: London and Salary: competitive mid-sentence.</p>';

		self::assertSame( $text, V2Fragments::strip_leading_meta( $text ) );
	}

	public function test_paragraph_block_html_is_stripped_like_giig_sends_it(): void {
		$text = '<p>HVAC / Refrigeration Engineer – West Midlands Field-based role</p>'
			. '<p>Location: West Midlands, UK</p><p>Travel: Regional&nbsp;</p>'
			. '<p>Job Type: Full-time</p><p>Salary: £50,000 – £55,000 per annum</p>'
			. '<p>Start Date: ASAP</p><p><br></p>'
			. '<p>Join a Trusted and Growing Engineering Team</p>';

		$result = V2Fragments::strip_leading_meta( $text );

		self::assertStringContainsString( '<p>HVAC / Refrigeration Engineer – West Midlands Field-based role</p>', $result );
		self::assertStringContainsString( 'Join a Trusted and Growing Engineering Team', $result );
		self::assertStringNotContainsString( 'Location:', $result );
		self::assertStringNotContainsString( 'Job Type:', $result );
		self::assertStringNotContainsString( 'Start Date:', $result );
	}
}
