<?php
/**
 * Document form definition + signature envelope tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Documents\FormDefinitions;

/**
 * Shape checks for the three Gravity Forms document definitions.
 */
final class FormDefinitionsTest extends TestCase {

	/** @return array<int,array{0:array<string,mixed>}> */
	public static function forms(): array {
		return array(
			'full'    => array( FormDefinitions::full_registration() ),
			'starter' => array( FormDefinitions::starter_statement() ),
			'terms'   => array( FormDefinitions::candidate_terms() ),
		);
	}

	#[DataProvider( 'forms' )]
	public function test_field_ids_are_unique( array $form ): void {
		$ids = array_map( static fn( array $f ): int => (int) $f['id'], $form['fields'] );
		self::assertSame( count( $ids ), count( array_unique( $ids ) ), 'duplicate field ids in ' . $form['title'] );
	}

	#[DataProvider( 'forms' )]
	public function test_conditional_rules_reference_existing_fields( array $form ): void {
		$ids = array_map( static fn( array $f ): int => (int) $f['id'], $form['fields'] );
		self::assertNotEmpty( $ids );
		foreach ( $form['fields'] as $field ) {
			foreach ( ( $field['conditionalLogic']['rules'] ?? array() ) as $rule ) {
				self::assertContains( (int) $rule['fieldId'], $ids, $form['title'] . ': rule on missing field ' . $rule['fieldId'] );
			}
		}
	}

	#[DataProvider( 'forms' )]
	public function test_every_document_form_has_signature_declaration_and_date( array $form ): void {
		$types = array_count_values( array_map( static fn( array $f ): string => (string) $f['type'], $form['fields'] ) );
		self::assertSame( 1, $types['poolhall_signature'] ?? 0 );
		self::assertGreaterThanOrEqual( 1, $types['date'] ?? 0 );
		self::assertGreaterThanOrEqual( 1, $types['checkbox'] ?? 0 );
		self::assertTrue( (bool) $form['enableHoneypot'] );
		self::assertSame( array(), $form['notifications'], 'workflow sends its own notifications after PDF generation' );
	}

	public function test_full_registration_page_count_matches_progress_steps(): void {
		$form   = FormDefinitions::full_registration();
		$breaks = count( array_filter( $form['fields'], static fn( array $f ): bool => 'page' === $f['type'] ) );
		self::assertSame( count( $form['pagination']['pages'] ), $breaks + 1 );
		self::assertTrue( (bool) $form['save']['enabled'], 'long form must offer save-and-continue' );
	}

	public function test_health_details_show_for_conditions_but_not_none(): void {
		$form    = FormDefinitions::full_registration();
		$details = null;
		foreach ( $form['fields'] as $field ) {
			if ( 36 === (int) $field['id'] ) {
				$details = $field;
			}
		}
		self::assertNotNull( $details );
		$values = array_map( static fn( array $r ): string => (string) $r['value'], $details['conditionalLogic']['rules'] );
		self::assertNotContains( 'None of the above', $values );
		self::assertSame( 'any', $details['conditionalLogic']['logicType'] );
	}
}
