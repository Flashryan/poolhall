<?php
/**
 * SessionDescriber tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Accounts\SessionDescriber;

final class SessionDescriberTest extends TestCase {

	private SessionDescriber $describer;

	protected function setUp(): void {
		$this->describer = new SessionDescriber();
	}

	public function test_desktop_chromium_family_browsers_are_told_apart(): void {
		$chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
		$edge   = $chrome . ' Edg/126.0.2592.87';
		$opera  = $chrome . ' OPR/111.0.0.0';

		self::assertSame( array( 'browser' => 'Chrome', 'platform' => 'Windows' ), $this->describer->describe( $chrome ) );
		self::assertSame( 'Microsoft Edge', $this->describer->describe( $edge )['browser'] );
		self::assertSame( 'Opera', $this->describer->describe( $opera )['browser'] );
	}

	public function test_safari_is_not_mistaken_for_chrome(): void {
		$safari = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';

		self::assertSame( array( 'browser' => 'Safari', 'platform' => 'Mac' ), $this->describer->describe( $safari ) );
	}

	public function test_ios_browsers_report_their_real_family_and_device(): void {
		$chrome_iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.54 Mobile/15E148 Safari/604.1';
		$firefox_ipad  = 'Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/127.0 Mobile/15E148 Safari/605.1.15';

		self::assertSame( array( 'browser' => 'Chrome', 'platform' => 'iPhone' ), $this->describer->describe( $chrome_iphone ) );
		self::assertSame( array( 'browser' => 'Firefox', 'platform' => 'iPad' ), $this->describer->describe( $firefox_ipad ) );
	}

	public function test_android_firefox_and_samsung_internet(): void {
		$firefox = 'Mozilla/5.0 (Android 14; Mobile; rv:127.0) Gecko/127.0 Firefox/127.0';
		$samsung = 'Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36';

		self::assertSame( array( 'browser' => 'Firefox', 'platform' => 'Android' ), $this->describer->describe( $firefox ) );
		self::assertSame( 'Samsung Internet', $this->describer->describe( $samsung )['browser'] );
	}

	public function test_unrecognised_agents_stay_empty_for_the_renderer_to_label(): void {
		self::assertSame( array( 'browser' => '', 'platform' => '' ), $this->describer->describe( '' ) );
		self::assertSame( array( 'browser' => '', 'platform' => '' ), $this->describer->describe( 'curl/8.5.0' ) );
	}
}
