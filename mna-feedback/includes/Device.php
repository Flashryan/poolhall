<?php
/**
 * Works out which device a comment or reply was left on: phone, tablet or
 * desktop, the operating system, the browser and the screen size.
 *
 * The browser's user-agent string is parsed here. The review tool adds what
 * only the browser itself knows: screen size, pixel ratio and touch support,
 * plus Client Hints where the browser offers them (the real Android version
 * and phone model, Windows 11 rather than 10), because modern browsers freeze
 * those parts of the user-agent string. Everything the browser sends is
 * treated as untrusted and cleaned before it is stored.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Device {

	public const TYPES = array( 'phone', 'tablet', 'desktop' );

	/** Checked in order: several browsers include "Chrome" or "Safari" in their string. */
	private const BROWSERS = array(
		'Edge'             => '#\bEdg(?:e|A|iOS)?/(\d+)#',
		'Opera'            => '#\b(?:OPR|OPiOS|OPT)/(\d+)#',
		'Samsung Internet' => '#\bSamsungBrowser/(\d+)#',
		'Facebook app'     => '#\bFBAV/(\d+)#',
		'Instagram app'    => '#\bInstagram (\d+)#',
		'Google app'       => '#\bGSA/(\d+)#',
		'Firefox'          => '#\b(?:Firefox|FxiOS)/(\d+)#',
		'Chrome'           => '#\b(?:Headless)?(?:Chrome|CriOS)/(\d+)#',
		'Safari'           => '#\bVersion/(\d+)(?:\.\d+)*.*\bSafari/#',
	);

	/**
	 * Device details for a new comment or reply.
	 *
	 * @param string $user_agent The request's User-Agent header.
	 * @param mixed  $hints      What the review tool measured (untrusted).
	 * @param array  $viewport   Window size ['w' => int, 'h' => int], used when the hints lack it.
	 */
	public static function detect( string $user_agent, $hints, array $viewport = array() ): array {
		$ua    = self::clean_ua( $user_agent );
		$hints = is_array( $hints ) ? $hints : array();

		$touch       = max( 0, min( 20, (int) ( $hints['touch'] ?? 0 ) ) );
		$ch_platform = self::text( $hints['platform'] ?? '', 30 );
		$ch_version  = preg_match( '/^\d{1,3}(\.\d{1,5}){0,3}$/', (string) ( $hints['platformVersion'] ?? '' ) ) ? (string) $hints['platformVersion'] : '';
		$ch_model    = self::text( $hints['model'] ?? '', 40 );
		$ch_mobile   = ! empty( $hints['mobile'] );
		$view        = self::size( is_array( $hints['viewport'] ?? null ) ? $hints['viewport'] : $viewport );
		$screen      = self::size( is_array( $hints['screen'] ?? null ) ? $hints['screen'] : array() );

		$type       = '';
		$os         = '';
		$os_version = '';
		$model      = '';

		if ( preg_match( '#\b(iPhone|iPod)\b#', $ua, $m ) ) {
			$type       = 'phone';
			$os         = 'iOS';
			$model      = $m[1];
			$os_version = self::apple_version( $ua );
		} elseif ( str_contains( $ua, 'iPad' ) || ( str_contains( $ua, 'Macintosh' ) && $touch > 1 ) ) {
			// iPadOS 13+ presents itself as a Mac; only touch support gives it away.
			$type       = 'tablet';
			$os         = 'iPadOS';
			$model      = 'iPad';
			$os_version = self::apple_version( $ua );
		} elseif ( str_contains( $ua, 'Android' ) ) {
			$os   = 'Android';
			$type = ( str_contains( $ua, 'Mobile' ) || $ch_mobile ) ? 'phone' : 'tablet';
			// Chrome reports "Android 10; K" for every device; Client Hints carry the real values.
			if ( 'Android' === $ch_platform && $ch_version ) {
				$os_version = self::major( $ch_version );
			} else {
				$os_version = self::match( '#\bAndroid (\d+(?:\.\d+)?)#', $ua );
			}
			$model = $ch_model;
			if ( '' === $model && preg_match( '#Android [\d.]+; (?:[a-z]{2}[-_][a-z]{2}; )?([^;)]+?)(?: Build/[^;)]*)?(?:; [^)]*)?\)#i', $ua, $m ) && ! in_array( strtoupper( trim( $m[1] ) ), array( 'K', 'MOBILE', 'TABLET', 'LINUX' ), true ) ) {
				$model = self::text( $m[1], 40 );
			}
		} elseif ( str_contains( $ua, 'CrOS' ) ) {
			$type = 'desktop';
			$os   = 'ChromeOS';
		} elseif ( str_contains( $ua, 'Windows' ) ) {
			$type = 'desktop';
			$os   = 'Windows';
			// "Windows NT 10.0" covers both 10 and 11; Client Hints tell them apart.
			if ( 'Windows' === $ch_platform && $ch_version ) {
				$os_version = (int) self::major( $ch_version ) >= 13 ? '11' : '10';
			} elseif ( preg_match( '#Windows NT (\d+\.\d+)#', $ua, $m ) ) {
				$os_version = array(
					'6.1' => '7',
					'6.2' => '8',
					'6.3' => '8.1',
				)[ $m[1] ] ?? '';
			}
		} elseif ( str_contains( $ua, 'Macintosh' ) || str_contains( $ua, 'Mac OS X' ) ) {
			$type = 'desktop';
			$os   = 'macOS';
			// The user-agent string is frozen at 10.15, so only Client Hints are trusted here.
			if ( 'macOS' === $ch_platform && $ch_version ) {
				$os_version = implode( '.', array_slice( explode( '.', $ch_version ), 0, 2 ) );
			}
		} elseif ( str_contains( $ua, 'Linux' ) ) {
			// Chrome on Android reports desktop Linux when it asks for the desktop
			// site (the default on large tablets); a multi-touch screen is the
			// tell, and the screen's short side separates phones from tablets.
			if ( $touch > 1 ) {
				$os    = 'Android';
				$short = $screen['w'] ? min( $screen['w'], $screen['h'] ?: $screen['w'] ) : $view['w'];
				$type  = ( $short && $short < 600 ) ? 'phone' : 'tablet';
			} else {
				$os   = 'Linux';
				$type = 'desktop';
			}
		}

		if ( '' === $type ) {
			if ( $ch_mobile || ( $touch && $view['w'] && $view['w'] < 600 ) ) {
				$type = 'phone';
			} elseif ( $touch && $view['w'] && $view['w'] <= 1100 ) {
				$type = 'tablet';
			} else {
				$type = 'desktop';
			}
		}

		$browser         = '';
		$browser_version = '';
		foreach ( self::BROWSERS as $name => $pattern ) {
			if ( preg_match( $pattern, $ua, $m ) ) {
				$browser         = $name;
				$browser_version = $m[1];
				break;
			}
		}
		// A web view inside another app (mail, messaging) rather than a browser.
		if ( ( '' === $browser && in_array( $os, array( 'iOS', 'iPadOS' ), true ) && str_contains( $ua, 'AppleWebKit' ) ) || ( 'Chrome' === $browser && str_contains( $ua, '; wv)' ) ) ) {
			$browser         = 'In-app browser';
			$browser_version = '';
		}

		$dpr = (float) ( $hints['dpr'] ?? 0 );
		$dpr = $dpr > 0 ? round( max( 0.5, min( 8, $dpr ) ), 2 ) : 0;

		return array(
			'type'            => $type,
			'os'              => $os,
			'os_version'      => $os_version,
			'browser'         => $browser,
			'browser_version' => $browser_version,
			'model'           => $model,
			'screen'          => $screen,
			'viewport'        => $view,
			'dpr'             => $dpr,
			'touch'           => $touch > 0,
			// Without the review tool's measurements (older interface, or a comment
			// made before devices were recorded) the details come from the
			// user-agent string alone: an iPad then looks like a Mac.
			'estimated'       => empty( $hints['screen'] ),
			'ua'              => $ua,
		);
	}

	/**
	 * Stored device JSON as returned by the API, with readable summaries.
	 */
	public static function for_output( ?string $json ): ?array {
		if ( ! $json ) {
			return null;
		}
		$d = json_decode( $json, true );
		if ( ! is_array( $d ) || ! in_array( $d['type'] ?? '', self::TYPES, true ) ) {
			return null;
		}
		$d = array_merge(
			array(
				'os'              => '',
				'os_version'      => '',
				'browser'         => '',
				'browser_version' => '',
				'model'           => '',
				'screen'          => array( 'w' => 0, 'h' => 0 ),
				'viewport'        => array( 'w' => 0, 'h' => 0 ),
				'dpr'             => 0,
				'touch'           => false,
				'estimated'       => false,
				'ua'              => '',
			),
			$d
		);
		$d['summary'] = self::summary( $d );
		$d['details'] = self::details( $d );
		return $d;
	}

	/** "iPhone · Safari 18", "Pixel 8 · Chrome 129", "Windows PC · Edge 129". */
	public static function summary( array $d ): string {
		$browser = trim( ( $d['browser'] ?? '' ) . ' ' . ( $d['browser_version'] ?? '' ) );
		return $browser ? self::name( $d ) . ' · ' . $browser : self::name( $d );
	}

	/** "iOS 18.1 · screen 393×852 · window 393×659 · 3× · touch". */
	public static function details( array $d ): string {
		$parts = array();
		$os    = trim( ( $d['os'] ?? '' ) . ' ' . ( $d['os_version'] ?? '' ) );
		if ( $os ) {
			$parts[] = $os;
		}
		if ( ! empty( $d['screen']['w'] ) ) {
			/* translators: %s: screen size such as 393×852 */
			$parts[] = sprintf( __( 'screen %s', 'mna-feedback' ), $d['screen']['w'] . '×' . $d['screen']['h'] );
		}
		if ( ! empty( $d['viewport']['w'] ) ) {
			/* translators: %s: browser window size such as 393×659 */
			$parts[] = sprintf( __( 'window %s', 'mna-feedback' ), $d['viewport']['w'] . '×' . $d['viewport']['h'] );
		}
		if ( ! empty( $d['dpr'] ) && 1.0 !== (float) $d['dpr'] ) {
			$parts[] = rtrim( rtrim( number_format( (float) $d['dpr'], 2, '.', '' ), '0' ), '.' ) . '×';
		}
		if ( ! empty( $d['touch'] ) ) {
			$parts[] = __( 'touch', 'mna-feedback' );
		}
		return implode( ' · ', $parts );
	}

	/** Friendly device name: the model where known, otherwise the platform. */
	public static function name( array $d ): string {
		if ( ! empty( $d['model'] ) ) {
			return (string) $d['model'];
		}
		$type = $d['type'] ?? '';
		switch ( $d['os'] ?? '' ) {
			case 'Android':
				return 'tablet' === $type ? __( 'Android tablet', 'mna-feedback' ) : __( 'Android phone', 'mna-feedback' );
			case 'Windows':
				return __( 'Windows PC', 'mna-feedback' );
			case 'macOS':
				return __( 'Mac', 'mna-feedback' );
			case 'ChromeOS':
				return __( 'Chromebook', 'mna-feedback' );
			case 'Linux':
				return __( 'Linux PC', 'mna-feedback' );
		}
		return self::type_label( (string) $type );
	}

	public static function type_label( string $type ): string {
		return match ( $type ) {
			'phone'  => __( 'Phone', 'mna-feedback' ),
			'tablet' => __( 'Tablet', 'mna-feedback' ),
			default  => __( 'Desktop', 'mna-feedback' ),
		};
	}

	/**
	 * Best guess for a comment saved before devices were recorded, from the
	 * user-agent string and pixel ratio it kept in its context.
	 */
	public static function from_context( ?string $context_json, int $viewport_w, int $viewport_h ): ?array {
		$context = $context_json ? json_decode( $context_json, true ) : null;
		if ( ! is_array( $context ) || empty( $context['ua'] ) || ! is_string( $context['ua'] ) ) {
			return null;
		}
		return self::detect(
			$context['ua'],
			array( 'dpr' => $context['dpr'] ?? 0 ),
			array(
				'w' => $viewport_w,
				'h' => $viewport_h,
			)
		);
	}

	private static function clean_ua( string $ua ): string {
		$ua = preg_replace( '/[^\x20-\x7E]/', '', $ua ) ?? '';
		return substr( trim( $ua ), 0, 400 );
	}

	private static function text( $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( preg_replace( '/[^\p{L}\p{N} ._()+-]/u', '', $value ) ?? '' );
		return mb_substr( $value, 0, $max );
	}

	/**
	 * @return array{w: int, h: int}
	 */
	private static function size( array $size ): array {
		return array(
			'w' => max( 0, min( 20000, (int) ( $size['w'] ?? 0 ) ) ),
			'h' => max( 0, min( 20000, (int) ( $size['h'] ?? 0 ) ) ),
		);
	}

	/** First capture group (joined with a dot when $pair is set and a second group exists). */
	private static function match( string $pattern, string $subject, bool $pair = false ): string {
		if ( ! preg_match( $pattern, $subject, $m ) ) {
			return '';
		}
		return ( $pair && isset( $m[2] ) && '0' !== $m[2] ) ? $m[1] . '.' . $m[2] : $m[1];
	}

	/**
	 * iOS / iPadOS version. From iOS 26, Safari's string always says "OS 18_6"
	 * and carries the real version as "Version/26.x"; other iOS browsers only
	 * have the frozen value, which is then reported as unknown.
	 */
	private static function apple_version( string $ua ): string {
		$os     = self::match( '#\bOS (\d+)[_.](\d+)#', $ua, true );
		$safari = self::match( '#\bVersion/(\d+)\.(\d+)#', $ua, true );
		if ( '' === $os || ( $safari && (int) $safari > (int) $os ) ) {
			return $safari;
		}
		return ( '18.6' === $os && '' === $safari ) ? '' : $os;
	}

	private static function major( string $version ): string {
		return explode( '.', $version )[0];
	}
}
