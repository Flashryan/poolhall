<?php
/**
 * Screenshot attachments (PNG, JPEG, WebP, up to 2 MB).
 *
 * Uploads are decoded and re-encoded with GD before being stored, which drops
 * embedded metadata (such as camera location) and anything smuggled inside the
 * file. Files live in an unguessable directory under uploads, blocked from
 * direct access where the server honours .htaccess / web.config, with random
 * file names - and they are only ever served through the authorised REST
 * endpoint, so public visitors cannot retrieve them.
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
		$ext   = self::MIMES[ $mime ];
		$name  = $uuid . '.' . $ext;
		$saved = self::reencode( $tmp, $mime, $dir . '/' . $name );
		if ( ! $saved ) {
			return new \WP_Error( 'mnafb_upload_decode', __( 'That image could not be read. Try saving it as PNG or JPEG.', 'mna-feedback' ), array( 'status' => 415 ) );
		}

		$thumb = '';
		if ( $width > self::THUMB_W ) {
			$thumb_name = $uuid . '-thumb.' . $ext;
			if ( self::thumbnail( $dir . '/' . $name, $mime, $dir . '/' . $thumb_name ) ) {
				$thumb = $thumb_name;
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
				'size'        => (int) filesize( $dir . '/' . $name ),
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

	private static function write( \GdImage $image, string $mime, string $dest ): bool {
		return match ( $mime ) {
			'image/png'  => imagepng( $image, $dest, 6 ),
			'image/jpeg' => imagejpeg( $image, $dest, 88 ),
			'image/webp' => function_exists( 'imagewebp' ) && imagewebp( $image, $dest, 88 ),
			default      => false,
		};
	}

	private static function reencode( string $src, string $mime, string $dest ): bool {
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$image = self::load( $src, $mime );
			if ( $image ) {
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
				$ok = self::write( $image, $mime, $dest );
				return $ok && file_exists( $dest );
			}
		}
		// Fallback for servers without GD support for this format.
		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$result = $editor->save( $dest, $mime );
		return ! is_wp_error( $result ) && file_exists( $dest );
	}

	private static function thumbnail( string $src, string $mime, string $dest ): bool {
		if ( function_exists( 'imagescale' ) ) {
			$image = self::load( $src, $mime );
			if ( $image ) {
				$scaled = imagescale( $image, self::THUMB_W, -1, IMG_BILINEAR_FIXED );
				if ( $scaled ) {
					imagealphablending( $scaled, false );
					imagesavealpha( $scaled, true );
					$ok = self::write( $scaled, $mime, $dest );
					return $ok;
				}
			}
		}
		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) || is_wp_error( $editor->resize( self::THUMB_W, null ) ) ) {
			return false;
		}
		return ! is_wp_error( $editor->save( $dest, $mime ) );
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
		$path = self::path( $attachment, $variant );
		if ( ! $path ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: ' . $attachment->mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox" );
		header( 'Content-Disposition: inline; filename="screenshot-' . (int) $attachment->id . '.' . ( self::MIMES[ $attachment->mime ] ?? 'img' ) . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
