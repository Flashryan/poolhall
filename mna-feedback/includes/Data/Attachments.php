<?php
/**
 * Screenshot attachments (PNG, JPEG, WebP, up to 2 MB).
 *
 * Uploads are decoded and re-encoded with GD before being stored, which drops
 * embedded metadata (such as camera location) and anything smuggled inside the
 * file. The result is encrypted (see Crypto::seal) and written under a random
 * name in an unguessable directory that is also blocked where the server
 * honours .htaccess / web.config. Files are only ever decrypted and served by
 * the authorised REST endpoint, so public visitors cannot retrieve them - even
 * on servers such as nginx that ignore .htaccess.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Data;

use MNA\Feedback\Crypto;
use MNA\Feedback\Schema;

defined( 'ABSPATH' ) || exit;

final class Attachments {

	public const MAX_BYTES  = 2097152;
	public const MAX_SIDE   = 12000;
	public const MAX_PIXELS = 40000000;
	public const THUMB_W    = 480;
	public const MIMES      = array(
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
	);

	public static function table(): string {
		return Schema::table( 'attachments' );
	}

	public static function ensure_storage(): void {
		$dir = self::dir();
		if ( ! $dir ) {
			return;
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$files = array(
			'.htaccess'  => "# MNA Feedback: files are served only through the authorised REST endpoint.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
		);
		foreach ( $files as $name => $content ) {
			$path = $dir . '/' . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}

	/**
	 * Absolute path of the private storage directory.
	 */
	public static function dir(): string {
		$suffix = get_option( 'mnafb_storage_dir' );
		if ( ! $suffix ) {
			$suffix = strtolower( wp_generate_password( 16, false, false ) );
			update_option( 'mnafb_storage_dir', $suffix, false );
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) && empty( $uploads['basedir'] ) ) {
			return '';
		}
		return trailingslashit( $uploads['basedir'] ) . 'mna-feedback-' . sanitize_file_name( (string) $suffix );
	}

	public static function delete_storage(): void {
		$suffix = get_option( 'mnafb_storage_dir' );
		if ( ! $suffix ) {
			return;
		}
		$dir = self::dir();
		if ( $dir && is_dir( $dir ) ) {
			foreach ( (array) glob( $dir . '/{,.}*', GLOB_BRACE ) as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param int[] $item_ids
	 * @return array<int, object[]>
	 */
	public static function for_items( array $item_ids ): array {
		if ( ! $item_ids ) {
			return array();
		}
		global $wpdb;
		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id IN ({$placeholders}) ORDER BY id ASC", array_map( 'intval', $item_ids ) ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out          = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->item_id ][] = $row;
		}
		return $out;
	}

	/**
	 * Validates, re-encodes and stores an uploaded image.
	 *
	 * @param array $file An entry from $_FILES / WP_REST_Request::get_file_params().
	 */
	public static function store( array $file, int $item_id, int $reply_id, int $uploader_id ): \stdClass|\WP_Error {
		if ( ! isset( $file['tmp_name'], $file['size'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new \WP_Error( 'mnafb_upload_failed', __( 'The upload did not complete. Please try again.', 'mna-feedback' ), array( 'status' => 400 ) );
		}
		$tmp = (string) $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp ) && ! apply_filters( 'mnafb_allow_non_uploaded_file', false, $tmp ) ) {
			return new \WP_Error( 'mnafb_upload_failed', __( 'The upload did not complete. Please try again.', 'mna-feedback' ), array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES || filesize( $tmp ) > self::MAX_BYTES ) {
			return new \WP_Error( 'mnafb_upload_too_large', __( 'Screenshots can be up to 2 MB.', 'mna-feedback' ), array( 'status' => 413 ) );
		}

		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $info || empty( $info['mime'] ) || ! isset( self::MIMES[ $info['mime'] ] ) ) {
			return new \WP_Error( 'mnafb_upload_type', __( 'Please attach a PNG, JPEG or WebP image.', 'mna-feedback' ), array( 'status' => 415 ) );
		}
		[ $width, $height ] = $info;
		if ( $width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS ) {
			return new \WP_Error( 'mnafb_upload_dimensions', __( 'That image is too large to process.', 'mna-feedback' ), array( 'status' => 413 ) );
		}

		$dir = self::dir();
		self::ensure_storage();
		if ( ! $dir || ! wp_is_writable( $dir ) ) {
			return new \WP_Error( 'mnafb_storage', __( 'Screenshots cannot be saved on this server (uploads folder not writable).', 'mna-feedback' ), array( 'status' => 500 ) );
		}

		$mime  = $info['mime'];
		$uuid  = Crypto::uuid();
		$bytes = self::reencode( $tmp, $mime );
		if ( null === $bytes ) {
			return new \WP_Error( 'mnafb_upload_decode', __( 'That image could not be read. Try saving it as PNG or JPEG.', 'mna-feedback' ), array( 'status' => 415 ) );
		}
		$name = $uuid . '.bin';
		if ( ! self::put( $dir . '/' . $name, $bytes ) ) {
			return new \WP_Error( 'mnafb_storage', __( 'The screenshot could not be saved.', 'mna-feedback' ), array( 'status' => 500 ) );
		}

		$thumb = '';
		if ( $width > self::THUMB_W ) {
			$small = self::thumbnail( $bytes, $mime );
			if ( null !== $small && self::put( $dir . '/' . $uuid . '-thumb.bin', $small ) ) {
				$thumb = $uuid . '-thumb.bin';
			}
		}

		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'uuid'        => $uuid,
				'item_id'     => $item_id,
				'reply_id'    => $reply_id,
				'uploader_id' => $uploader_id,
				'file'        => $name,
				'thumb'       => $thumb,
				'mime'        => $mime,
				'size'        => strlen( $bytes ),
				'width'       => $width,
				'height'      => $height,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		$row = self::get( (int) $wpdb->insert_id );
		return $row ?? new \WP_Error( 'mnafb_storage', __( 'The screenshot could not be recorded.', 'mna-feedback' ), array( 'status' => 500 ) );
	}

	private static function load( string $path, string $mime ): \GdImage|false {
		return match ( $mime ) {
			'image/png'  => function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'image/jpeg' => function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'image/webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			default      => false,
		};
	}

	/** Encodes a GD image in memory. */
	private static function encode( \GdImage $image, string $mime ): ?string {
		ob_start();
		$ok = match ( $mime ) {
			'image/png'  => imagepng( $image, null, 6 ),
			'image/jpeg' => imagejpeg( $image, null, 88 ),
			'image/webp' => function_exists( 'imagewebp' ) && imagewebp( $image, null, 88 ),
			default      => false,
		};
		$bytes = (string) ob_get_clean();
		return $ok && '' !== $bytes ? $bytes : null;
	}

	/** Decodes the upload and re-encodes it, returning the new file contents. */
	private static function reencode( string $src, string $mime ): ?string {
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$image = self::load( $src, $mime );
			if ( $image ) {
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
				return self::encode( $image, $mime );
			}
		}
		// Fallback for servers without GD support for this format.
		return self::via_editor( $src, $mime, 0 );
	}

	private static function thumbnail( string $bytes, string $mime ): ?string {
		if ( function_exists( 'imagecreatefromstring' ) && function_exists( 'imagescale' ) ) {
			$image = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $image ) {
				$scaled = imagescale( $image, self::THUMB_W, -1, IMG_BILINEAR_FIXED );
				if ( $scaled ) {
					imagealphablending( $scaled, false );
					imagesavealpha( $scaled, true );
					return self::encode( $scaled, $mime );
				}
			}
		}
		$tmp = self::temp_file( 'mnafb' );
		if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return null;
		}
		$result = self::via_editor( $tmp, $mime, self::THUMB_W );
		wp_delete_file( $tmp );
		return $result;
	}

	/** WordPress image editor fallback (Imagick), working through a temporary file. */
	private static function via_editor( string $src, string $mime, int $width ): ?string {
		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) || ( $width && is_wp_error( $editor->resize( $width, null ) ) ) ) {
			return null;
		}
		$dest   = self::temp_file( 'mnafb-out' );
		$result = $editor->save( $dest, $mime );
		if ( is_wp_error( $result ) || empty( $result['path'] ) || ! is_file( $result['path'] ) ) {
			return null;
		}
		$bytes = (string) file_get_contents( $result['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $result['path'] );
		if ( $dest !== $result['path'] && is_file( $dest ) ) {
			wp_delete_file( $dest );
		}
		return '' === $bytes ? null : $bytes;
	}

	private static function temp_file( string $prefix ): string {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return (string) wp_tempnam( $prefix );
	}

	/** Writes sealed (encrypted) contents to the storage directory. */
	private static function put( string $path, string $bytes ): bool {
		return false !== file_put_contents( $path, Crypto::seal( $bytes ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/** Decrypted contents of a stored file, or null. */
	public static function contents( object $attachment, string $variant = 'full' ): ?string {
		$path = self::path( $attachment, $variant );
		if ( ! $path ) {
			return null;
		}
		$stored = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return false === $stored ? null : Crypto::unseal( $stored );
	}

	public static function path( object $attachment, string $variant = 'full' ): ?string {
		$name = ( 'thumb' === $variant && $attachment->thumb ) ? $attachment->thumb : $attachment->file;
		$name = basename( (string) $name );
		$path = self::dir() . '/' . $name;
		return ( '' !== $name && is_file( $path ) ) ? $path : null;
	}

	public static function delete( object $attachment ): void {
		foreach ( array( 'full', 'thumb' ) as $variant ) {
			$path = self::path( $attachment, $variant );
			if ( $path ) {
				wp_delete_file( $path );
			}
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $attachment->id ) );
	}

	public static function delete_for_item( int $item_id ): void {
		foreach ( self::for_items( array( $item_id ) )[ $item_id ] ?? array() as $attachment ) {
			self::delete( $attachment );
		}
	}

	/**
	 * Sends the file to an authorised requester and stops.
	 */
	public static function stream( object $attachment, string $variant = 'full' ): void {
		$bytes = self::contents( $attachment, $variant );
		if ( null === $bytes ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: ' . $attachment->mime );
		header( 'Content-Length: ' . strlen( $bytes ) );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox" );
		header( 'Content-Disposition: inline; filename="screenshot-' . (int) $attachment->id . '.' . ( self::MIMES[ $attachment->mime ] ?? 'img' ) . '"' );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Image data served with nosniff and a sandbox CSP.
		exit;
	}
}
