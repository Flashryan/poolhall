<?php
/**
 * wp_mail-backed mailer.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Support\BrandedMail;

/**
 * Default production mailer. Callers hand over plain text; it leaves in the
 * branded shell with that same text kept as the plain-text part.
 */
final class WpMailer implements Mailer {

	public function send( string $to, string $subject, string $message ): bool {
		return BrandedMail::send_text( $to, $subject, $message );
	}
}
