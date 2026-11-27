<?php
/**
 * Fail-closed private file storage.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Support;

/**
 * Allocates opaque files beneath a non-public storage root.
 *
 * The default is a sibling of ABSPATH so candidate documents are not served
 * by the WordPress web root. Hosts with a different filesystem layout may
 * define POOLHALL_PRIVATE_STORAGE_DIR to an absolute writable directory.
 */
final class PrivateStorage {

	private const DEFAULT_DIRECTORY = 'poolhall-private-storage';

	/** Return the verified private-storage root. */
	public static function root(): string {
		$explicit = defined( 'POOLHALL_PRIVATE_STORAGE_DIR' );
		$path     = $explicit
			? trim( (string) constant( 'POOLHALL_PRIVATE_STORAGE_DIR' ) )
			: dirname( rtrim( (string) ABSPATH, '/\\' ) ) . DIRECTORY_SEPARATOR . self::DEFAULT_DIRECTORY;

		if ( '' === $path || ! self::is_absolute_path( $path ) ) {
			throw new \RuntimeException( 'Private storage must use an absolute filesystem path.' );
		}

		$wordpress = realpath( (string) ABSPATH );
		if ( false === $wordpress ) {
			throw new \RuntimeException( 'ABSPATH could not be resolved for private storage validation.' );
		}
		$prospective = rtrim( str_replace( '\\', '/', $path ), '/' );
		if ( self::is_within( $prospective, $wordpress ) || self::is_within( $wordpress, $prospective ) ) {
			throw new \RuntimeException( 'Private storage must be outside ABSPATH.' );
		}
		$resolved = realpath( $path );
		if ( false !== $resolved && ( self::is_within( $resolved, $wordpress ) || self::is_within( $wordpress, $resolved ) ) ) {
			throw new \RuntimeException( 'Private storage must be outside ABSPATH.' );
		}

		$root = self::ensure_directory( $path );
		if ( self::is_within( $root, $wordpress ) || self::is_within( $wordpress, $root ) ) {
			throw new \RuntimeException( 'Private storage must be outside ABSPATH.' );
		}

		self::write_guards( $root );
		return $root;
	}

	/** Return a verified, single-level storage area such as documents or cvs. */
	public static function directory( string $subdirectory ): string {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,49}$/', $subdirectory ) ) {
			throw new \InvalidArgumentException( 'Invalid private storage subdirectory.' );
		}

		$root      = self::root();
		$directory = self::ensure_directory( $root . DIRECTORY_SEPARATOR . $subdirectory );
		if ( ! self::is_within( $directory, $root ) || $directory === $root ) {
			throw new \RuntimeException( 'Private storage subdirectory escaped its root.' );
		}

		self::write_guards( $directory );
		return $directory;
	}

	/** Allocate an unguessable path; the caller remains responsible for writing it. */
	public static function opaque_path( string $subdirectory, string $prefix, string $extension = '' ): string {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]{0,49}$/', $prefix ) ) {
			throw new \InvalidArgumentException( 'Invalid private storage filename prefix.' );
		}

		$extension = strtolower( ltrim( $extension, '.' ) );
		if ( '' !== $extension && 1 !== preg_match( '/^[a-z0-9]{1,10}$/', $extension ) ) {
			throw new \InvalidArgumentException( 'Invalid private storage file extension.' );
		}

		$filename = $prefix . '-' . wp_generate_uuid4() . ( '' === $extension ? '' : '.' . $extension );
		return self::directory( $subdirectory ) . DIRECTORY_SEPARATOR . $filename;
	}

	/** Verify that a completed file is non-empty, contained and owner-readable only. */
	public static function protect_file( string $path ): string {
		$root = self::root();
		$real = realpath( $path );
		if ( false === $real || ! is_file( $real ) || ! self::is_within( $real, $root ) || $real === $root ) {
			throw new \RuntimeException( 'Private file is missing or outside the storage root.' );
		}

		$size = filesize( $real );
		if ( false === $size || $size <= 0 ) {
			throw new \RuntimeException( 'Private file is empty.' );
		}

		chmod( $real, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- candidate files must be owner-readable only.
		clearstatcache( true, $real );
		$permissions = fileperms( $real );
		if ( false === $permissions || 0 !== ( $permissions & 0077 ) ) {
			throw new \RuntimeException( 'Private file permissions could not be restricted.' );
		}
		return $real;
	}

	private static function ensure_directory( string $path ): string {
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			throw new \RuntimeException( 'Unable to create private storage directory.' );
		}

		$real = realpath( $path );
		if ( false === $real || ! is_dir( $real ) ) {
			throw new \RuntimeException( 'Private storage directory could not be resolved.' );
		}
		if ( ! is_writable( $real ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- explicit fail-closed storage check.
			throw new \RuntimeException( 'Private storage directory is not writable.' );
		}

		chmod( $real, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- private application storage requires owner-only permissions.
		clearstatcache( true, $real );
		$permissions = fileperms( $real );
		if ( false === $permissions || 0 !== ( $permissions & 0077 ) ) {
			throw new \RuntimeException( 'Private storage permissions could not be restricted.' );
		}
		return $real;
	}

	private static function write_guards( string $directory ): void {
		$guards = array(
			'.htaccess'  => "# Poolhall private storage - do not serve.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\nhttp_response_code( 404 );\nexit;\n",
		);

		foreach ( $guards as $filename => $contents ) {
			$path    = $directory . DIRECTORY_SEPARATOR . $filename;
			$current = is_file( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local guard verification, not an HTTP request.
			if ( $contents !== $current ) {
				$written = file_put_contents( $path, $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fail-closed local access guards.
				if ( false === $written || strlen( $contents ) !== $written ) {
					throw new \RuntimeException( 'Unable to write private storage access guards.' );
				}
			}
			$verified = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local guard verification, not an HTTP request.
			if ( $contents !== $verified ) {
				throw new \RuntimeException( 'Private storage access guard verification failed.' );
			}
			chmod( $path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- access guards contain no public data and remain owner-only.
			clearstatcache( true, $path );
			$permissions = fileperms( $path );
			if ( false === $permissions || 0 !== ( $permissions & 0077 ) ) {
				throw new \RuntimeException( 'Private storage access guard permissions could not be restricted.' );
			}
		}
	}

	private static function is_absolute_path( string $path ): bool {
		return str_starts_with( $path, '/' ) || 1 === preg_match( '/^[A-Za-z]:[\\\\\/]/', $path );
	}

	private static function is_within( string $path, string $ancestor ): bool {
		$path     = rtrim( str_replace( '\\', '/', $path ), '/' );
		$ancestor = rtrim( str_replace( '\\', '/', $ancestor ), '/' );
		return $path === $ancestor || str_starts_with( $path, $ancestor . '/' );
	}
}
