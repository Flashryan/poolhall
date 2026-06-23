<?php
/**
 * CV upload validation.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

/**
 * Pure validation of an uploaded CV's reported name, size and MIME against
 * the allowed types and the 25 MB cap (spec §8: PDF/DOC/DOCX, max
 * 25 MB). Kept free of WordPress and the filesystem so the rules are
 * unit-tested; the endpoint performs the real move and a server-side
 * filetype re-check on top of this.
 */
final class CvUpload {

	public const MAX_BYTES = 25 * 1024 * 1024;

	/** Allowed extension => acceptable reported MIME types. */
	private const ALLOWED = array(
		'pdf'  => array( 'application/pdf' ),
		'doc'  => array( 'application/msword' ),
		'docx' => array(
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/zip', // some browsers report .docx as zip; the server re-check catches mismatches.
		),
	);

	/**
	 * @return string[] Error codes; empty when the upload looks acceptable.
	 */
	public static function validate( string $original_name, int $size_bytes, int $php_upload_error ): array {
		if ( UPLOAD_ERR_NO_FILE === $php_upload_error ) {
			return array( 'cv_required' );
		}
		if ( UPLOAD_ERR_OK !== $php_upload_error ) {
			return array( 'cv_upload_failed' );
		}
		if ( $size_bytes <= 0 ) {
			return array( 'cv_required' );
		}
		if ( $size_bytes > self::MAX_BYTES ) {
			return array( 'cv_too_large' );
		}
		if ( ! self::extension_allowed( $original_name ) ) {
			return array( 'cv_wrong_type' );
		}
		return array();
	}

	public static function extension_allowed( string $original_name ): bool {
		return array_key_exists( self::extension( $original_name ), self::ALLOWED );
	}

	public static function extension( string $original_name ): string {
		return strtolower( (string) pathinfo( $original_name, PATHINFO_EXTENSION ) );
	}

	/** @return string[] Acceptable MIME types for the file's extension. */
	public static function allowed_mimes( string $original_name ): array {
		return self::ALLOWED[ self::extension( $original_name ) ] ?? array();
	}
}
