<?php
/**
 * Expired roles must leave the jobs sitemap.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Schema\SchemaOutput;
use Poolhall\Integration\Support\Options;

final class SitemapExpiryTest extends TestCase {

	private function schema_output(): SchemaOutput {
		return new SchemaOutput( new Options() );
	}

	public function test_job_sitemap_query_gains_an_unexpired_clause(): void {
		$args = $this->schema_output()->exclude_expired_from_sitemap( array(), JobPostType::POST_TYPE );

		self::assertArrayHasKey( 'meta_query', $args );
		self::assertCount( 1, $args['meta_query'] );

		$clause = $args['meta_query'][0];
		self::assertSame( 'expires_at', $clause['key'] );
		self::assertSame( '>', $clause['compare'] );
		// A plain string compare on ATOM — a DATETIME cast would choke on 'T'.
		self::assertArrayNotHasKey( 'type', $clause );
		self::assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $clause['value'] );
	}

	public function test_existing_clauses_are_preserved(): void {
		$existing = array(
			array(
				'key'   => 'is_featured',
				'value' => '1',
			),
		);

		$args = $this->schema_output()->exclude_expired_from_sitemap( array( 'meta_query' => $existing ), JobPostType::POST_TYPE );

		self::assertCount( 2, $args['meta_query'] );
		self::assertSame( 'is_featured', $args['meta_query'][0]['key'] );
		self::assertSame( 'expires_at', $args['meta_query'][1]['key'] );
	}

	public function test_other_post_types_are_untouched(): void {
		self::assertSame( array(), $this->schema_output()->exclude_expired_from_sitemap( array(), 'page' ) );
		self::assertSame( array(), $this->schema_output()->exclude_expired_from_sitemap( array(), 'post' ) );
	}
}
