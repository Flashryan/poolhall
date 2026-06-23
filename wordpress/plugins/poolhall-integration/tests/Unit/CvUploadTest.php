<?php
/**
 * CvUpload tests.
 *
 * @package Poolhall\Integration\Tests
 */

declare(strict_types=1);

namespace Poolhall\Integration\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Poolhall\Integration\Applications\CvUpload;

final class CvUploadTest extends TestCase {

	private const ONE_MB = 1024 * 1024;

	public function test_valid_pdf_has_no_errors(): void {
		self::assertSame( array(), CvUpload::validate( 'jane-smith-cv.pdf', self::ONE_MB, UPLOAD_ERR_OK ) );
	}

	public function test_doc_and_docx_are_allowed(): void {
		self::assertSame( array(), CvUpload::validate( 'cv.doc', self::ONE_MB, UPLOAD_ERR_OK ) );
		self::assertSame( array(), CvUpload::validate( 'cv.docx', self::ONE_MB, UPLOAD_ERR_OK ) );
	}

	public function test_no_file_is_required_error(): void {
		self::assertSame( array( 'cv_required' ), CvUpload::validate( '', 0, UPLOAD_ERR_NO_FILE ) );
	}

	public function test_zero_byte_upload_is_required_error(): void {
		self::assertSame( array( 'cv_required' ), CvUpload::validate( 'cv.pdf', 0, UPLOAD_ERR_OK ) );
	}

	public function test_php_upload_error_is_reported_as_failed(): void {
		self::assertSame( array( 'cv_upload_failed' ), CvUpload::validate( 'cv.pdf', self::ONE_MB, UPLOAD_ERR_PARTIAL ) );
	}

	public function test_max_bytes_is_25_mb(): void {
		self::assertSame( 25 * 1024 * 1024, CvUpload::MAX_BYTES );
	}

	public function test_file_at_the_cap_is_allowed(): void {
		self::assertSame( array(), CvUpload::validate( 'cv.pdf', CvUpload::MAX_BYTES, UPLOAD_ERR_OK ) );
	}

	public function test_file_over_the_cap_is_too_large(): void {
		self::assertSame( array( 'cv_too_large' ), CvUpload::validate( 'cv.pdf', CvUpload::MAX_BYTES + 1, UPLOAD_ERR_OK ) );
	}

	public function test_disallowed_extension_is_wrong_type(): void {
		self::assertSame( array( 'cv_wrong_type' ), CvUpload::validate( 'cv.exe', self::ONE_MB, UPLOAD_ERR_OK ) );
		self::assertSame( array( 'cv_wrong_type' ), CvUpload::validate( 'photo.png', self::ONE_MB, UPLOAD_ERR_OK ) );
	}

	public function test_extension_is_lowercased(): void {
		self::assertSame( 'pdf', CvUpload::extension( 'JANE-CV.PDF' ) );
		self::assertTrue( CvUpload::extension_allowed( 'JANE-CV.PDF' ) );
	}

	public function test_allowed_mimes_match_the_extension(): void {
		self::assertContains( 'application/pdf', CvUpload::allowed_mimes( 'cv.pdf' ) );
		self::assertContains( 'application/msword', CvUpload::allowed_mimes( 'cv.doc' ) );
		self::assertSame( array(), CvUpload::allowed_mimes( 'cv.exe' ) );
	}

	public function test_size_is_checked_before_type(): void {
		// An oversized file with a bad extension reports size first.
		self::assertSame( array( 'cv_too_large' ), CvUpload::validate( 'cv.exe', CvUpload::MAX_BYTES + 1, UPLOAD_ERR_OK ) );
	}
}
