<?php
/**
 * ReturnUrlPolicy tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Accounts\ReturnUrlPolicy;

final class ReturnUrlPolicyTest extends TestCase {

	private ReturnUrlPolicy $policy;

	protected function setUp(): void {
		$this->policy = new ReturnUrlPolicy();
	}

	public function test_portal_and_jobs_paths_are_preserved(): void {
		self::assertSame( '/candidate/saved-jobs/', $this->policy->sanitize( '/candidate/saved-jobs/' ) );
		self::assertSame( '/jobs/maintenance-engineer-wolverhampton/', $this->policy->sanitize( '/jobs/maintenance-engineer-wolverhampton/' ) );
		self::assertSame( '/jobs/', $this->policy->sanitize( '/jobs/' ) );
		self::assertSame( '/jobs', $this->policy->sanitize( '/jobs' ) );
	}

	public function test_query_strings_on_allowed_paths_survive(): void {
		self::assertSame( '/jobs/?sector=manufacturing', $this->policy->sanitize( '/jobs/?sector=manufacturing' ) );
	}

	public function test_urlencoded_destinations_are_decoded_before_checking(): void {
		self::assertSame( '/candidate/job-alerts/', $this->policy->sanitize( '%2Fcandidate%2Fjob-alerts%2F' ) );
	}

	public function test_empty_and_unlisted_paths_fall_back_to_the_dashboard(): void {
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '' ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '/wp-admin/' ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '/contact/' ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( 'candidate/profile/' ) );
	}

	public function test_absolute_and_protocol_relative_urls_are_rejected(): void {
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( 'https://evil.example/candidate/' ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '//evil.example/candidate/' ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '/\\evil.example/candidate/' ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '/candidate/x?next=https://evil.example' ) );
	}

	public function test_traversal_and_control_characters_are_rejected(): void {
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '/candidate/../wp-admin/' ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( "/candidate/\r\nSet-Cookie:x" ) );
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( "/candidate/\0/" ) );
	}

	public function test_encoded_protocol_relative_form_is_rejected_after_decoding(): void {
		self::assertSame( ReturnUrlPolicy::DASHBOARD, $this->policy->sanitize( '%2F%2Fevil.example%2F' ) );
	}
}
