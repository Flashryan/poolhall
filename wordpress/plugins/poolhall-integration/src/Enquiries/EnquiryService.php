<?php
/**
 * Enquiry delivery: throttle, notify, log.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Enquiries;

use Poolhall\Integration\Accounts\Mailer;
use Poolhall\Integration\Support\Logger;

/**
 * Validates and delivers hiring/contact enquiries (spec 01 §7): network
 * rate limiting, email notification to the configurable inbox
 * (`poolhall_enquiry_inbox` option, default the site admin email) and
 * redacted log events. A failed send is reported to the visitor — an
 * enquiry is never silently dropped.
 */
final class EnquiryService {

	public const STATUS_SENT         = 'sent';
	public const STATUS_INVALID      = 'invalid';
	public const STATUS_RATE_LIMITED = 'rate_limited';
	public const STATUS_MAIL_FAILED  = 'mail_failed';

	public const INBOX_OPTION = 'poolhall_enquiry_inbox';

	private const RATE_WINDOW_SECONDS = HOUR_IN_SECONDS;
	private const RATE_MAX_PER_WINDOW = 5;

	public function __construct(
		private readonly Mailer $mailer,
		private readonly Logger $logger
	) {
	}

	/**
	 * @return array{status:string,errors:string[]}
	 */
	public function submit( EnquiryRequest $request, string $network_signal ): array {
		$errors = $request->validation_errors();
		if ( array() !== $errors ) {
			return array(
				'status' => self::STATUS_INVALID,
				'errors' => $errors,
			);
		}

		if ( ! $this->within_rate_limit( $network_signal ) ) {
			$this->logger->log( 'enquiry_rate_limited', array( 'kind' => $request->kind ) );
			return array(
				'status' => self::STATUS_RATE_LIMITED,
				'errors' => array(),
			);
		}

		// Record employer leads first (never-lose, same as applications):
		// the review screen offers one-click Giig company creation, and the
		// lead survives even if the notification email fails below.
		if ( $request->is_employer() ) {
			$this->store_employer_lead( $request );
		}

		if ( ! $this->mailer->send( $this->inbox( $request->kind ), $this->subject( $request ), $this->body( $request ) ) ) {
			$this->logger->log( 'enquiry_mail_failed', array( 'kind' => $request->kind ) );
			return array(
				'status' => self::STATUS_MAIL_FAILED,
				'errors' => array(),
			);
		}

		$this->logger->log( 'enquiry_sent', array( 'kind' => $request->kind ) );
		return array(
			'status' => self::STATUS_SENT,
			'errors' => array(),
		);
	}

	private function store_employer_lead( EnquiryRequest $request ): void {
		\Poolhall\Integration\Applications\ApplicationRecord::store(
			sprintf(
				/* translators: %s: company name (or contact name when no company given). */
				__( '%s — employer enquiry', 'poolhall-integration' ),
				'' !== $request->company ? $request->company : $request->name
			),
			array(
				'kind'          => 'enquiry',
				'company_name'  => $request->company,
				'contact_name'  => $request->name,
				'contact_email' => $request->email,
				'contact_phone' => $request->phone,
				'message'       => $request->message,
				'enquiry_kind'  => $request->kind,
				'review_status' => 'new',
				'submitted_at'  => gmdate( \DateTimeInterface::ATOM ),
			)
		);
	}

	private function inbox( string $kind ): string {
		$inbox = (string) get_option( self::INBOX_OPTION, '' );
		if ( '' === $inbox ) {
			$inbox = (string) get_option( 'admin_email' );
		}
		/**
		 * Destination inbox for enquiry notifications (spec 01 §7).
		 *
		 * @param string $inbox Email address.
		 * @param string $kind  `hiring` or `contact`.
		 */
		return (string) apply_filters( 'poolhall_enquiry_inbox', $inbox, $kind );
	}

	private function subject( EnquiryRequest $request ): string {
		if ( EnquiryRequest::KIND_HIRING === $request->kind ) {
			/* translators: %s: company name. */
			return sprintf( __( 'New hiring enquiry — %s', 'poolhall-integration' ), $request->company );
		}
		/* translators: %s: sender name. */
		return sprintf( __( 'New website message — %s', 'poolhall-integration' ), $request->name );
	}

	private function body( EnquiryRequest $request ): string {
		$lines = array(
			'Name: ' . $request->name,
		);
		if ( '' !== $request->company ) {
			$lines[] = 'Company: ' . $request->company;
		}
		$lines[] = 'Email: ' . $request->email;
		if ( '' !== $request->phone ) {
			$lines[] = 'Phone: ' . $request->phone;
		}
		if ( '' !== $request->enquirer_type ) {
			$lines[] = 'Enquiry type: ' . ( EnquiryRequest::ENQUIRER_TYPES[ $request->enquirer_type ] ?? $request->enquirer_type );
		}
		$lines[] = '';
		$lines[] = $request->message;
		$lines[] = '';
		$lines[] = 'Submitted from: ' . home_url( EnquiryRequest::KIND_HIRING === $request->kind ? '/employers/' : '/contact/' );
		$lines[] = 'Consent to store details: yes';

		return implode( "\n", $lines );
	}

	private function within_rate_limit( string $network_signal ): bool {
		if ( '' === $network_signal ) {
			return true;
		}
		$key   = 'poolhall_enquiry_rate_' . md5( $network_signal );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_MAX_PER_WINDOW ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW_SECONDS );
		return true;
	}
}
