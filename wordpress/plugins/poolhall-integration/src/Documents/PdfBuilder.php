<?php
/**
 * Branded candidate-document PDF builder (FPDF).
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

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

	private const NAVY = array( 11, 40, 70 );
	private const GOLD = array( 224, 163, 63 );
	private const MIST = array( 91, 102, 112 );

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
		$logo = POOLHALL_INTEGRATION_DIR . 'assets/img/content/poolhall-logo.png';
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
		if ( 'drawn' === $method && str_starts_with( $data, 'data:image/png;base64,' ) ) {
			$png = base64_decode( substr( $data, strlen( 'data:image/png;base64,' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding the signature image the candidate drew, strict mode.
			if ( false !== $png && '' !== $png ) {
				// wp_tempnam() is admin-only; use the protected documents dir.
				$sig = self::storage_dir() . '/sig-' . wp_generate_password( 16, false ) . '.png';
				file_put_contents( $sig, $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- controlled temp file, deleted below.
				$this->pdf->Image( $sig, 16, $this->pdf->GetY(), 70 );
				wp_delete_file( $sig );
				$this->pdf->Ln( 32 );
			}
			$this->row( 'Signature method', 'Drawn electronic signature' );
		} else {
			$this->pdf->SetFont( 'Helvetica', 'I', 14 );
			$this->pdf->SetTextColor( 17, 22, 27 );
			$this->pdf->Cell( 0, 10, $this->latin( $signer ), 0, 1 );
			$this->row( 'Signature method', 'Typed full name as electronic signature' );
		}
		$this->row( 'Signed by', $signer );
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

	/** Writes the finished document into the protected documents folder. */
	public function save( string $filename ): string {
		$dir  = self::storage_dir();
		$path = $dir . '/' . sanitize_file_name( $filename );
		$this->pdf->SetAutoPageBreak( false );
		$this->pdf->SetY( -14 );
		$this->pdf->SetFont( 'Helvetica', '', 7.5 );
		$this->pdf->SetTextColor( ...self::MIST );
		$this->pdf->Cell( 0, 4, $this->latin( 'Generated ' . gmdate( 'j M Y H:i' ) . ' UTC - Poolhall Recruitment candidate document' ), 0, 0, 'C' );
		$this->pdf->Output( 'F', $path );
		return $path;
	}

	/** Protected (deny-all) folder for generated documents and nothing else. */
	public static function storage_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = $uploads['basedir'] . '/poolhall-documents';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$guard = $dir . '/.htaccess';
		if ( ! file_exists( $guard ) ) {
			file_put_contents( $guard, "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing the access guard itself.
		}
		$index = $dir . '/index.html';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- directory-listing guard.
		}
		return $dir;
	}

	private function latin( string $value ): string {
		$out = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- iconv notice on odd bytes; fallback below.
		return false === $out ? preg_replace( '/[^\x20-\x7E]/', '?', $value ) ?? '' : $out;
	}
}
