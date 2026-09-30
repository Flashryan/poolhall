<?php
/**
 * Unit tests for device detection. Runs without WordPress:
 *
 *     php tests/php/device_test.php
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text, $domain = null ) { // phpcs:ignore
	return $text;
}

require __DIR__ . '/../../includes/Device.php';

use MNA\Feedback\Device;

$phone_hints  = array(
	'screen'   => array( 'w' => 393, 'h' => 852 ),
	'viewport' => array( 'w' => 393, 'h' => 659 ),
	'dpr'      => 3,
	'touch'    => 5,
);
$tablet_hints = array(
	'screen'   => array( 'w' => 1024, 'h' => 1366 ),
	'viewport' => array( 'w' => 1024, 'h' => 1292 ),
	'dpr'      => 2,
	'touch'    => 5,
);
$desk_hints   = array(
	'screen'   => array( 'w' => 1920, 'h' => 1080 ),
	'viewport' => array( 'w' => 1903, 'h' => 937 ),
	'dpr'      => 1,
	'touch'    => 0,
);

$cases = array(
	array(
		'iPhone, Safari 17',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
		$phone_hints,
		array( 'type' => 'phone', 'os' => 'iOS', 'os_version' => '17.4', 'browser' => 'Safari', 'browser_version' => '17', 'summary' => 'iPhone · Safari 17' ),
	),
	array(
		'iPhone, Safari 26 (frozen OS 18_6)',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1',
		$phone_hints,
		array( 'type' => 'phone', 'os_version' => '26', 'browser_version' => '26' ),
	),
	array(
		'iPhone, Chrome (frozen OS, no Safari version)',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/140.0.7339.101 Mobile/15E148 Safari/604.1',
		$phone_hints,
		array( 'type' => 'phone', 'os_version' => '', 'browser' => 'Chrome', 'browser_version' => '140' ),
	),
	array(
		'iPhone, Firefox',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/124.0 Mobile/15E148 Safari/605.1.15',
		$phone_hints,
		array( 'browser' => 'Firefox', 'browser_version' => '124' ),
	),
	array(
		'iPhone, Edge',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 EdgiOS/122.0.2365.99 Mobile/15E148 Safari/605.1.15',
		$phone_hints,
		array( 'browser' => 'Edge', 'browser_version' => '122' ),
	),
	array(
		'iPhone, in-app web view',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
		$phone_hints,
		array( 'browser' => 'In-app browser', 'summary' => 'iPhone · In-app browser' ),
	),
	array(
		'iPhone, Facebook app',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/455.0.0.36.109;FBBV/588139412]',
		$phone_hints,
		array( 'browser' => 'Facebook app', 'browser_version' => '455' ),
	),
	array(
		'iPad (old style)',
		'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
		$tablet_hints,
		array( 'type' => 'tablet', 'os' => 'iPadOS', 'os_version' => '16.6', 'model' => 'iPad', 'summary' => 'iPad · Safari 16' ),
	),
	array(
		'iPad presenting as a Mac (touch gives it away)',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
		$tablet_hints,
		array( 'type' => 'tablet', 'os' => 'iPadOS', 'os_version' => '17.4', 'estimated' => false ),
	),
	array(
		'Mac, Safari',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
		$desk_hints,
		array( 'type' => 'desktop', 'os' => 'macOS', 'os_version' => '', 'browser' => 'Safari', 'summary' => 'Mac · Safari 17' ),
	),
	array(
		'Mac, Chrome with Client Hints',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		$desk_hints + array( 'platform' => 'macOS', 'platformVersion' => '15.1.0' ),
		array( 'os' => 'macOS', 'os_version' => '15.1', 'browser' => 'Chrome', 'browser_version' => '140' ),
	),
	array(
		'Windows 11, Chrome with Client Hints',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		$desk_hints + array( 'platform' => 'Windows', 'platformVersion' => '15.0.0' ),
		array( 'type' => 'desktop', 'os' => 'Windows', 'os_version' => '11', 'summary' => 'Windows PC · Chrome 140' ),
	),
	array(
		'Windows 10, Edge with Client Hints',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0',
		$desk_hints + array( 'platform' => 'Windows', 'platformVersion' => '10.0.0' ),
		array( 'os_version' => '10', 'browser' => 'Edge', 'browser_version' => '140' ),
	),
	array(
		'Windows, Firefox (no Client Hints)',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
		$desk_hints,
		array( 'os' => 'Windows', 'os_version' => '', 'browser' => 'Firefox', 'browser_version' => '131' ),
	),
	array(
		'Windows, Opera',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/124.0.0.0',
		$desk_hints,
		array( 'browser' => 'Opera', 'browser_version' => '124' ),
	),
	array(
		'Android phone, Chrome reduced string + Client Hints',
		'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36',
		$phone_hints + array( 'platform' => 'Android', 'platformVersion' => '15.0.0', 'model' => 'Pixel 8', 'mobile' => true ),
		array( 'type' => 'phone', 'os' => 'Android', 'os_version' => '15', 'model' => 'Pixel 8', 'summary' => 'Pixel 8 · Chrome 140' ),
	),
	array(
		'Android phone, Chrome reduced string without hints',
		'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36',
		$phone_hints,
		array( 'type' => 'phone', 'model' => '', 'summary' => 'Android phone · Chrome 140' ),
	),
	array(
		'Android phone, Samsung Internet with model',
		'Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36',
		$phone_hints,
		array( 'type' => 'phone', 'os_version' => '14', 'model' => 'SAMSUNG SM-S918B', 'browser' => 'Samsung Internet', 'browser_version' => '26' ),
	),
	array(
		'Android phone, Firefox',
		'Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0',
		$phone_hints,
		array( 'type' => 'phone', 'os' => 'Android', 'model' => '', 'browser' => 'Firefox' ),
	),
	array(
		'Android phone, Gmail web view',
		'Mozilla/5.0 (Linux; Android 14; Pixel 7 Build/AP1A.240305.019; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/122.0.6261.119 Mobile Safari/537.36',
		$phone_hints,
		array( 'type' => 'phone', 'model' => 'Pixel 7', 'browser' => 'In-app browser' ),
	),
	array(
		'Android tablet',
		'Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		$tablet_hints,
		array( 'type' => 'tablet', 'os' => 'Android', 'model' => 'SM-X700' ),
	),
	array(
		'Android tablet asking for desktop sites',
		'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		$tablet_hints,
		array( 'type' => 'tablet', 'os' => 'Android', 'summary' => 'Android tablet · Chrome 140' ),
	),
	array(
		'Android phone asking for the desktop site',
		'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		$phone_hints,
		array( 'type' => 'phone', 'os' => 'Android', 'summary' => 'Android phone · Chrome 140' ),
	),
	array(
		'Headless Chrome counts as Chrome',
		'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/141.0.0.0 Safari/537.36',
		$desk_hints,
		array( 'type' => 'desktop', 'os' => 'Linux', 'browser' => 'Chrome', 'browser_version' => '141' ),
	),
	array(
		'Linux desktop, Firefox',
		'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
		$desk_hints,
		array( 'type' => 'desktop', 'os' => 'Linux', 'summary' => 'Linux PC · Firefox 131' ),
	),
	array(
		'Chromebook',
		'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		$desk_hints,
		array( 'type' => 'desktop', 'os' => 'ChromeOS', 'summary' => 'Chromebook · Chrome 140' ),
	),
	array(
		'Unknown string: small touch screen counts as a phone',
		'SomeBrowser/1.0',
		array( 'screen' => array( 'w' => 360, 'h' => 780 ), 'viewport' => array( 'w' => 360, 'h' => 700 ), 'touch' => 2 ),
		array( 'type' => 'phone', 'os' => '', 'browser' => '' ),
	),
	array(
		'No hints at all: marked as estimated',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
		null,
		array( 'type' => 'desktop', 'estimated' => true, 'screen' => array( 'w' => 0, 'h' => 0 ) ),
	),
	array(
		'Hostile hints are cleaned',
		'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36',
		array( 'screen' => array( 'w' => 99999, 'h' => -5 ), 'dpr' => 400, 'touch' => 'lots', 'model' => '<script>alert(1)</script>', 'platform' => 'Android', 'platformVersion' => '1; DROP TABLE' ),
		array( 'screen' => array( 'w' => 20000, 'h' => 0 ), 'dpr' => 8.0, 'touch' => false, 'model' => 'scriptalert(1)script', 'os_version' => '10' ),
	),
);

$pass = 0;
foreach ( $cases as [ $name, $ua, $hints, $expect ] ) {
	$d      = Device::detect( $ua, $hints );
	$d     += array( 'summary' => Device::summary( $d ) );
	$failed = array();
	foreach ( $expect as $key => $value ) {
		if ( $d[ $key ] !== $value ) {
			$failed[] = sprintf( '%s: expected %s, got %s', $key, var_export( $value, true ), var_export( $d[ $key ], true ) );
		}
	}
	if ( $failed ) {
		echo "[FAIL] {$name} — " . implode( '; ', $failed ) . "\n";
	} else {
		++$pass;
		echo "[PASS] {$name}\n";
	}
}

// Stored JSON round trip, and a comment saved before devices were recorded.
$out = Device::for_output( json_encode( Device::detect( $cases[0][1], $phone_hints ) ) );
$ok  = $out && 'iPhone · Safari 17' === $out['summary'] && 'iOS 17.4 · screen 393×852 · window 393×659 · 3× · touch' === $out['details'];
echo ( $ok ? '[PASS]' : '[FAIL]' ) . ' Output adds summary and details' . ( $ok ? '' : ' — ' . json_encode( $out ) ) . "\n";
$pass += (int) $ok;

$legacy = Device::from_context( json_encode( array( 'ua' => $cases[15][1], 'dpr' => 2.625 ) ), 412, 780 );
$ok     = $legacy && 'phone' === $legacy['type'] && true === $legacy['estimated'] && 412 === $legacy['viewport']['w'] && 2.63 === $legacy['dpr'];
echo ( $ok ? '[PASS]' : '[FAIL]' ) . ' Older comments get an estimate from their saved context' . ( $ok ? '' : ' — ' . json_encode( $legacy ) ) . "\n";
$pass += (int) $ok;

$ok    = null === Device::for_output( null ) && null === Device::for_output( '{"type":"toaster"}' ) && null === Device::from_context( '{"scroll":{"x":0}}', 0, 0 );
echo ( $ok ? '[PASS]' : '[FAIL]' ) . " Missing or invalid data gives no device\n";
$pass += (int) $ok;

$total = count( $cases ) + 3;
echo "\n{$pass}/{$total} checks passed\n";
exit( $pass === $total ? 0 : 1 );
