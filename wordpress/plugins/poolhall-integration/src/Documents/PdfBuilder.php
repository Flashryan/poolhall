<?php
/**
 * Branded candidate-document PDF builder (FPDF).
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

use Poolhall\Integration\Support\PrivateStorage;

/**
 * Builds the clean, printable PDFs the document workflow emails to the
 * team: branded header, sectioned label/value rows, the signature (drawn
 * image or typed name), and the audit trail. Uses the vendored FPDF
 * (lib/fpdf.php) so no external services or licensed add-ons are needed.
 *
 * Everything renders in core Helvetica; values are transliterated to
 * cp1252, which covers the character set these UK payroll/registration
 * forms actually produce.
 */
final class PdfBuilder {

	private const NAVY  = array( 11, 40, 70 );
	private const GOLD  = array( 224, 163, 63 );
	private const MIST  = array( 91, 102, 112 );
	private const INK   = array( 17, 22, 27 );
	private const OCHRE = array( 138, 94, 18 );

	private \FPDF $pdf;

	public function __construct( private readonly string $title ) {
		if ( ! class_exists( '\FPDF' ) ) {
			define( 'FPDF_FONTPATH', POOLHALL_INTEGRATION_DIR . 'lib/fpdf-font/' );
			require_once POOLHALL_INTEGRATION_DIR . 'lib/fpdf.php';
		}
		$this->pdf = new \FPDF( 'P', 'mm', 'A4' );
		$this->pdf->SetTitle( $this->latin( $title ) );
		$this->pdf->SetAuthor( 'Poolhall Recruitment Limited' );
		$this->pdf->SetAutoPageBreak( true, 22 );
		$this->pdf->SetMargins( 16, 16, 16 );
		$this->pdf->AddPage();
		$this->masthead();
	}

	private function masthead(): void {
		$logo = \Poolhall\Integration\Support\BrandAssets::path( 'mark-document.png' );
		if ( file_exists( $logo ) ) {
			$this->pdf->Image( $logo, 16, 14, 18 );
		}
		$this->pdf->SetXY( 38, 15 );
		$this->pdf->SetFont( 'Helvetica', 'B', 15 );
		$this->pdf->SetTextColor( ...self::NAVY );
		$this->pdf->Cell( 0, 7, $this->latin( $this->title ), 0, 2 );
		$this->pdf->SetX( 38 );
		$this->pdf->SetFont( 'Helvetica', '', 8.5 );
		$this->pdf->SetTextColor( ...self::MIST );
		$this->pdf->Cell( 0, 4.5, 'Poolhall Recruitment Limited - Grosvenor House, 11 St Pauls Square, Birmingham B3 1RB', 0, 2 );
		$this->pdf->Cell( 0, 4.5, 'jobs@poolhallrecruitment.co.uk - 0121 516 3000 - Company No. 13319338', 0, 2 );
		$this->pdf->SetDrawColor( ...self::GOLD );
		$this->pdf->SetLineWidth( 0.8 );
		$this->pdf->Line( 16, 36, 194, 36 );
		$this->pdf->SetY( 41 );
	}

	public function section( string $heading ): void {
		if ( $this->pdf->GetY() > 250 ) {
			$this->pdf->AddPage();
		}
		$this->pdf->Ln( 3 );
		$this->pdf->SetFont( 'Helvetica', 'B', 11 );
		$this->pdf->SetTextColor( ...self::NAVY );
		$this->pdf->SetFillColor( 242, 244, 246 );
		$this->pdf->Cell( 0, 8, '  ' . $this->latin( $heading ), 0, 1, 'L', true );
		$this->pdf->Ln( 1.5 );
	}

	public function row( string $label, string $value ): void {
		$value = trim( $value );
		if ( '' === $value ) {
			$value = '-';
		}
		$this->keep_together( array( array( 'B', 9, 58, $label ), array( '', 10, 118, $value ) ) );
		$this->pdf->SetFont( 'Helvetica', 'B', 9 );
		$this->pdf->SetTextColor( ...self::MIST );
		$y = $this->pdf->GetY();
		$this->pdf->MultiCell( 58, 5.5, $this->latin( $label ), 0, 'L' );
		$after_label = $this->pdf->GetY();
		$this->pdf->SetXY( 76, $y );
		$this->pdf->SetFont( 'Helvetica', '', 10 );
		$this->pdf->SetTextColor( 17, 22, 27 );
		$this->pdf->MultiCell( 118, 5.5, $this->latin( $value ), 0, 'L' );
		$this->pdf->SetY( max( $after_label, $this->pdf->GetY() ) + 1 );
	}

	/**
	 * Key facts in a box ruled in gold down the left, so the reader's eye
	 * lands there first. Meant for the top of a document, where it cannot be
	 * split by a page break.
	 *
	 * @param array<string,string> $rows Label/value pairs.
	 */
	public function callout( string $heading, array $rows ): void {
		$top = $this->pdf->GetY() + 1;
		$this->pdf->SetXY( 22, $top + 4 );
		$this->pdf->SetFont( 'Helvetica', 'B', 9 );
		$this->pdf->SetTextColor( ...self::OCHRE );
		$this->pdf->Cell( 0, 5, $this->latin( strtoupper( $heading ) ), 0, 1 );
		$this->pdf->Ln( 1 );
		foreach ( $rows as $label => $value ) {
			$y = $this->pdf->GetY();
			$this->pdf->SetX( 22 );
			$this->pdf->SetFont( 'Helvetica', '', 9 );
			$this->pdf->SetTextColor( ...self::MIST );
			$this->pdf->MultiCell( 46, 5.5, $this->latin( $label ), 0, 'L' );
			$after_label = $this->pdf->GetY();
			$this->pdf->SetXY( 70, $y );
			$this->pdf->SetFont( 'Helvetica', 'B', 10 );
			$this->pdf->SetTextColor( ...self::INK );
			$this->pdf->MultiCell( 120, 5.5, $this->latin( '' !== trim( $value ) ? $value : '-' ), 0, 'L' );
			$this->pdf->SetY( max( $after_label, $this->pdf->GetY() ) + 0.8 );
		}
		$bottom = $this->pdf->GetY() + 3;
		$this->pdf->SetDrawColor( 214, 220, 226 );
		$this->pdf->SetLineWidth( 0.3 );
		$this->pdf->Rect( 16, $top, 178, $bottom - $top );
		$this->pdf->SetFillColor( ...self::GOLD );
		$this->pdf->Rect( 16, $top, 2.2, $bottom - $top, 'F' );
		$this->pdf->SetY( $bottom + 3 );
	}

	/**
	 * One numbered question and its answer, laid out like the paper form:
	 * number, question, then the answer in bold on the right.
	 */
	public function question( string $number, string $question, string $answer ): void {
		$this->keep_together( array( array( '', 9.5, 106, $question ), array( 'B', 10, 60, '' !== trim( $answer ) ? $answer : '-' ) ) );
		$y = $this->pdf->GetY();
		$this->pdf->SetX( 16 );
		$this->pdf->SetFont( 'Helvetica', 'B', 9 );
		$this->pdf->SetTextColor( ...self::OCHRE );
		$this->pdf->Cell( 8, 5.5, $this->latin( $number ), 0, 0 );
		$this->pdf->SetFont( 'Helvetica', '', 9.5 );
		$this->pdf->SetTextColor( 44, 54, 64 );
		$this->pdf->MultiCell( 106, 5.5, $this->latin( $question ), 0, 'L' );
		$after_question = $this->pdf->GetY();
		$this->pdf->SetXY( 134, $y );
		$this->pdf->SetFont( 'Helvetica', 'B', 10 );
		$this->pdf->SetTextColor( ...self::INK );
		$this->pdf->MultiCell( 60, 5.5, $this->latin( '' !== trim( $answer ) ? $answer : '-' ), 0, 'L' );
		$this->pdf->SetY( max( $after_question, $this->pdf->GetY() ) + 1.2 );
		$this->pdf->SetDrawColor( 231, 234, 237 );
		$this->pdf->SetLineWidth( 0.2 );
		$this->pdf->Line( 16, $this->pdf->GetY() - 0.6, 194, $this->pdf->GetY() - 0.6 );
		$this->pdf->Ln( 0.8 );
	}

	public function note( string $text ): void {
		$this->pdf->SetFont( 'Helvetica', 'I', 9 );
		$this->pdf->SetTextColor( ...self::MIST );
		$this->pdf->MultiCell( 0, 5, $this->latin( $text ), 0, 'L' );
		$this->pdf->Ln( 1.5 );
	}

	/**
	 * Renders the signature block: a drawn PNG (from the pad's data URL)
	 * or the typed-name fallback, plus the signer name and date.
	 */
	public function signature( string $method, string $data, string $signer, string $date ): void {
		$this->section( 'Signature' );
		$signed_by = trim( $signer );
		if ( 'drawn' === $method ) {
			if ( ! str_starts_with( $data, 'data:image/png;base64,' ) ) {
				throw new \RuntimeException( 'Drawn signature data is invalid.' );
			}
			$png = base64_decode( substr( $data, strlen( 'data:image/png;base64,' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding the signature image the candidate drew, strict mode.
			if ( false === $png || '' === $png ) {
				throw new \RuntimeException( 'Drawn signature image could not be decoded.' );
			}

			$sig = PrivateStorage::opaque_path( 'documents', 'signature', 'png' );
			try {
				$written = file_put_contents( $sig, $png, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- controlled private temp file, deleted below.
				if ( false === $written || strlen( $png ) !== $written ) {
					throw new \RuntimeException( 'Drawn signature image could not be stored privately.' );
				}
				PrivateStorage::protect_file( $sig );
				$this->pdf->Image( $sig, 16, $this->pdf->GetY(), 70 );
			} finally {
				if ( is_file( $sig ) ) {
					wp_delete_file( $sig );
				}
			}
			$this->pdf->Ln( 32 );
			$this->row( 'Signature method', 'Drawn electronic signature' );
		} elseif ( 'typed' === $method ) {
			$typed = trim( $data );
			if ( '' === $typed ) {
				throw new \RuntimeException( 'Typed signature is empty.' );
			}
			$signed_by = $typed;
			$this->pdf->SetFont( 'Helvetica', 'I', 14 );
			$this->pdf->SetTextColor( 17, 22, 27 );
			$this->pdf->Cell( 0, 10, $this->latin( $typed ), 0, 1 );
			$this->row( 'Signature method', 'Typed full name as electronic signature' );
			$typed_comparison  = strtolower( (string) preg_replace( '/\s+/u', ' ', $typed ) );
			$signer_comparison = strtolower( (string) preg_replace( '/\s+/u', ' ', trim( $signer ) ) );
			if ( '' !== $signer_comparison && $typed_comparison !== $signer_comparison ) {
				$this->row( 'Candidate name on form', $signer );
			}
		} else {
			throw new \RuntimeException( 'Electronic signature method is unsupported.' );
		}
		$this->row( 'Signed by', $signed_by );
		$this->row( 'Date signed', $date );
	}

	/**
	 * @param array<string,string> $rows Audit label/value pairs.
	 */
	public function audit( array $rows ): void {
		$this->section( 'Audit trail' );
		foreach ( $rows as $label => $value ) {
			$this->row( $label, $value );
		}
	}

	/** Writes the finished document to an opaque path in private storage. */
	public function save( string $filename ): string {
		// Retained for backwards-compatible call sites; private names never use
		// candidate-supplied or human-readable display filenames.
		unset( $filename );
		$path = PrivateStorage::opaque_path( 'documents', 'candidate-document', 'pdf' );
		$this->pdf->SetAutoPageBreak( false );
		$this->pdf->SetY( -14 );
		$this->pdf->SetFont( 'Helvetica', '', 7.5 );
		$this->pdf->SetTextColor( ...self::MIST );
		$this->pdf->Cell( 0, 4, $this->latin( 'Generated ' . gmdate( 'j M Y H:i' ) . ' UTC - Poolhall Recruitment candidate document' ), 0, 0, 'C' );
		try {
			$this->pdf->Output( 'F', $path );
			return PrivateStorage::protect_file( $path );
		} catch ( \Throwable $throwable ) {
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
			throw $throwable;
		}
	}

	/**
	 * Start a new page first if a two-column row would not fit, so a label
	 * and its value are never split across pages.
	 *
	 * @param array<int,array{0:string,1:float|int,2:float|int,3:string}> $columns Style, size, width, text.
	 */
	private function keep_together( array $columns ): void {
		$lines = 1;
		foreach ( $columns as [ $style, $size, $width, $text ] ) {
			$this->pdf->SetFont( 'Helvetica', $style, $size );
			$lines = max( $lines, $this->line_count( (float) $width, $text ) );
		}
		if ( $this->pdf->GetY() + $lines * 5.5 > 297 - 22 ) {
			$this->pdf->AddPage();
		}
	}

	/** Lines MultiCell will need for $text at $width in the current font. */
	private function line_count( float $width, string $text ): int {
		$lines = 0;
		foreach ( explode( "\n", $this->latin( $text ) ) as $paragraph ) {
			$line  = '';
			$count = 1;
			foreach ( explode( ' ', $paragraph ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( '' !== $line && $this->pdf->GetStringWidth( $try ) > $width - 2 ) {
					++$count;
					$line = $word;
				} else {
					$line = $try;
				}
			}
			$lines += $count;
		}
		return max( 1, $lines );
	}

	/** Private folder for generated candidate documents and nothing else. */
	public static function storage_dir(): string {
		return PrivateStorage::directory( 'documents' );
	}

	private function latin( string $value ): string {
		$out = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- iconv notice on odd bytes; fallback below.
		return false === $out ? preg_replace( '/[^\x20-\x7E]/', '?', $value ) ?? '' : $out;
	}
}
