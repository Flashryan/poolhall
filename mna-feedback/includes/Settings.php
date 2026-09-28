<?php
/**
 * Plugin settings (branding, behaviour, integrations).
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'mnafb_settings';

	public const DEFAULTS = array(
		'enabled'            => true,
		'display_name'       => 'Feedback',
		'logo_id'            => 0,
		'accent'             => '#4F46E5',
		'poll_interval'      => 15,
		'page_comments'      => true,
		'guest_uploads'      => true,
		'session_days'       => 30,
		'abilities_enabled'  => false,
		'purge_on_uninstall' => false,
	);

	/** @var array<int, array> Settings already loaded, by site (blog ID). */
	private static array $cache = array();

	public static function ensure_defaults(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::DEFAULTS, '', false );
		}
		unset( self::$cache[ get_current_blog_id() ] );
	}

	public static function all(): array {
		$site = get_current_blog_id();
		if ( ! isset( self::$cache[ $site ] ) ) {
			$stored               = get_option( self::OPTION, array() );
			self::$cache[ $site ] = array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : array() );
		}
		return self::$cache[ $site ];
	}

	public static function get( string $key ): mixed {
		$all = self::all();
		return $all[ $key ] ?? ( self::DEFAULTS[ $key ] ?? null );
	}

	/**
	 * Validates and stores a partial update. Unknown keys are ignored.
	 */
	public static function update( array $input ): array {
		$next = self::all();
		foreach ( $input as $key => $value ) {
			if ( ! array_key_exists( $key, self::DEFAULTS ) ) {
				continue;
			}
			$next[ $key ] = self::sanitize( $key, $value, $next[ $key ] );
		}
		update_option( self::OPTION, $next, false );
		unset( self::$cache[ get_current_blog_id() ] );
		return self::all();
	}

	private static function sanitize( string $key, mixed $value, mixed $current ): mixed {
		switch ( $key ) {
			case 'enabled':
			case 'page_comments':
			case 'guest_uploads':
			case 'abilities_enabled':
			case 'purge_on_uninstall':
				return (bool) rest_sanitize_boolean( $value );
			case 'display_name':
				$name = trim( sanitize_text_field( (string) $value ) );
				return '' === $name ? self::DEFAULTS['display_name'] : mb_substr( $name, 0, 60 );
			case 'logo_id':
				$id = absint( $value );
				return ( 0 === $id || wp_attachment_is_image( $id ) ) ? $id : $current;
			case 'accent':
				$hex = sanitize_hex_color( (string) $value );
				return $hex ? strtoupper( $hex ) : $current;
			case 'poll_interval':
				return max( 5, min( 120, absint( $value ) ) );
			case 'session_days':
				return max( 1, min( 365, absint( $value ) ) );
		}
		return $current;
	}

	/**
	 * Public branding shown to anyone who can open the review interface.
	 */
	public static function branding(): array {
		$logo = '';
		$id   = (int) self::get( 'logo_id' );
		if ( $id ) {
			$src  = wp_get_attachment_image_src( $id, 'medium' );
			$logo = $src ? (string) $src[0] : '';
		}
		return array(
			'name'          => (string) self::get( 'display_name' ),
			'logo'          => $logo,
			'accent'        => (string) self::get( 'accent' ),
			'poll_interval' => (int) self::get( 'poll_interval' ),
			'page_comments' => (bool) self::get( 'page_comments' ),
			'guest_uploads' => (bool) self::get( 'guest_uploads' ),
			'max_upload'    => Data\Attachments::MAX_BYTES,
		);
	}
}
