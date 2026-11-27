<?php
/**
 * Signed Agency Worker Contract PDF builder.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

use Poolhall\Integration\Support\PrivateStorage;
use setasign\Fpdi\Fpdi;

/**
 * Imports the client-supplied legal PDF without reflowing its wording, then
 * stamps the candidate details on page one and their electronic signature on
 * the existing execution block on the final page.
 */
final class ContractPdfBuilder {

	public const SOURCE_VERSION = 'WTT Contract for Services v5.0 - 062026';
	public const SOURCE_SHA256  = '8eae2860ca60fb3247ab73cfc7be478723b96495b17806291af21edc1604daa1';

	private const SOURCE_FILENAME = 'poolhall-agency-worker-contract-v5.0-062026.pdf';
	private const EXPECTED_PAGES  = 12;

	/** Absolute path to the retained legal source document. */
	public static function source_path(): string {
		return POOLHALL_INTEGRATION_DIR . 'assets/documents/' . self::SOURCE_FILENAME;
	}

	/** Fail closed if the approved source is missing or has changed. */
	public static function source_ready(): bool {
		$path = self::source_path();
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}
		$hash = hash_file( 'sha256', $path );
		return is_string( $hash ) && hash_equals( self::SOURCE_SHA256, $hash );
	}

	/**
	 * @param array{full_name:string,effective_date:string,address_line_1:string,address_line_2:string,town_city:string,county:string,postcode:string} $details Candidate details.
	 * @param array{method:string,data:string,signer:string,date:string} $signature Electronic signature envelope.
	 */
	public function save( array $details, array $signature ): string {
		if ( ! self::source_ready() ) {
			throw new \RuntimeException( 'The approved Agency Worker Contract source is missing or has changed.' );
		}
		$this->validate( $details, $signature );
		$this->load_libraries();

		$pdf = new Fpdi( 'P', 'mm', 'A4' );
		$pdf->SetTitle( $this->latin( 'Poolhall Agency Worker Contract - ' . $details['full_name'] ) );
		$pdf->SetAuthor( 'Poolhall Recruitment Limited' );
		$pdf->SetSubject( $this->latin( 'Electronically signed Agency Worker Contract' ) );
		$pdf->SetKeywords( 'Source SHA-256 ' . self::SOURCE_SHA256 );
		$pdf->SetAutoPageBreak( false );

		$page_count = $pdf->setSourceFile( self::source_path() );
		if ( self::EXPECTED_PAGES !== $page_count ) {
			throw new \RuntimeException( 'The approved Agency Worker Contract page count is invalid.' );
		}

		for ( $page_number = 1; $page_number <= $page_count; ++$page_number ) {
			$template = $pdf->importPage( $page_number );
			$size     = $pdf->getTemplateSize( $template );
			$pdf->AddPage( (string) $size['orientation'], array( (float) $size['width'], (float) $size['height'] ) );
			$pdf->useTemplate( $template, 0, 0, (float) $size['width'], (float) $size['height'] );

			if ( 1 === $page_number ) {
				$this->stamp_front_page( $pdf, $details );
			}
			if ( self::EXPECTED_PAGES === $page_number ) {
				$this->stamp_signature_page( $pdf, $signature );
			}
		}

		$path = PrivateStorage::opaque_path( 'documents', 'agency-worker-contract', 'pdf' );
		try {
			$pdf->Output( 'F', $path );
			return PrivateStorage::protect_file( $path );
		} catch ( \Throwable $throwable ) {
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
			throw $throwable;
		}
	}

	/** @param array<string,string> $details @param array<string,string> $signature */
	private function validate( array $details, array $signature ): void {
		foreach ( array( 'full_name', 'effective_date', 'address_line_1', 'town_city', 'postcode' ) as $key ) {
			if ( '' === trim( (string) ( $details[ $key ] ?? '' ) ) ) {
				throw new \RuntimeException( 'A required contract detail is missing.' );
			}
		}
		if ( ! in_array( (string) ( $signature['method'] ?? '' ), array( 'drawn', 'typed' ), true ) ) {
			throw new \RuntimeException( 'The contract signature method is invalid.' );
		}
		foreach ( array( 'data', 'signer', 'date' ) as $key ) {
			if ( '' === trim( (string) ( $signature[ $key ] ?? '' ) ) ) {
				throw new \RuntimeException( 'The contract signature is incomplete.' );
			}
		}
	}

	private function load_libraries(): void {
		if ( ! class_exists( '\FPDF' ) ) {
			if ( ! defined( 'FPDF_FONTPATH' ) ) {
				define( 'FPDF_FONTPATH', POOLHALL_INTEGRATION_DIR . 'lib/fpdf-font/' );
			}
			require_once POOLHALL_INTEGRATION_DIR . 'lib/fpdf.php';
		}
		if ( ! class_exists( Fpdi::class ) ) {
			require_once POOLHALL_INTEGRATION_DIR . 'lib/fpdi/autoload.php';
		}
	}

	/** @param array<string,string> $details */
	private function stamp_front_page( Fpdi $pdf, array $details ): void {
		$pdf->SetTextColor( 17, 22, 27 );
		$this->fitted_text( $pdf, (string) $details['effective_date'], 76.2, 36.6, 14.2, 8.0, 6.5 );
		$this->fitted_text( $pdf, (string) $details['full_name'], 62.0, 61.2, 119.0, 8.5, 6.5, 'B' );

		$address = implode(
			', ',
			array_filter(
				array_map(
					'sanitize_text_field',
					array(
						(string) ( $details['address_line_1'] ?? '' ),
						(string) ( $details['address_line_2'] ?? '' ),
						(string) ( $details['town_city'] ?? '' ),
						(string) ( $details['county'] ?? '' ),
						(string) ( $details['postcode'] ?? '' ),
					)
				)
			)
		);
		$this->fitted_text( $pdf, $address, 59.4, 68.0, 62.0, 8.0, 5.5 );
	}

	/** @param array<string,string> $signature */
	private function stamp_signature_page( Fpdi $pdf, array $signature ): void {
		$method = (string) $signature['method'];
		$data   = (string) $signature['data'];
		$signer = trim( (string) $signature['signer'] );
		$date   = trim( (string) $signature['date'] );

		if ( 'drawn' === $method ) {
			if ( ! str_starts_with( $data, 'data:image/png;base64,' ) ) {
				throw new \RuntimeException( 'Drawn contract signature data is invalid.' );
			}
			$png = base64_decode( substr( $data, strlen( 'data:image/png;base64,' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- strict decoding of candidate signature image.
			if ( false === $png || '' === $png ) {
				throw new \RuntimeException( 'Drawn contract signature image could not be decoded.' );
			}

			$temporary = PrivateStorage::opaque_path( 'documents', 'contract-signature', 'png' );
			try {
				$written = file_put_contents( $temporary, $png, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- controlled private temporary signature file.
				if ( false === $written || strlen( $png ) !== $written ) {
					throw new \RuntimeException( 'Drawn contract signature image could not be stored.' );
				}
				PrivateStorage::protect_file( $temporary );
				$pdf->Image( $temporary, 116.0, 170.2, 68.0, 10.0 );
			} finally {
				if ( is_file( $temporary ) ) {
					wp_delete_file( $temporary );
				}
			}
		} else {
			$this->fitted_text( $pdf, trim( $data ), 116.0, 179.3, 68.0, 14.0, 9.0, 'I' );
		}

		$this->fitted_text( $pdf, $signer, 116.0, 188.2, 68.0, 9.0, 7.0 );
		$this->fitted_text( $pdf, $date, 116.0, 196.3, 68.0, 9.0, 7.0 );
	}

	private function fitted_text( Fpdi $pdf, string $text, float $x, float $baseline_y, float $max_width, float $font_size, float $minimum_size, string $style = '' ): void {
		$text = $this->latin( trim( $text ) );
		$size = $font_size;
		do {
			$pdf->SetFont( 'Helvetica', $style, $size );
			if ( $pdf->GetStringWidth( $text ) <= $max_width || $size <= $minimum_size ) {
				break;
			}
			$size -= 0.25;
		} while ( $size >= $minimum_size );

		$pdf->Text( $x, $baseline_y, $text );
	}

	private function latin( string $value ): string {
		$out = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- fallback handles odd input bytes.
		return false === $out ? preg_replace( '/[^\x20-\x7E]/', '?', $value ) ?? '' : $out;
	}
}
