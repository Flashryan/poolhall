<?php
/**
 * wp_mail-backed mailer.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Default production mailer. Plain text; branded HTML templates arrive with
 * the email-inventory work (portal spec §14).
 */
final class WpMailer implements Mailer {

	public function send( string $to, string $subject, string $message ): bool {
		return wp_mail( $to, $subject, $message );
	}
}
