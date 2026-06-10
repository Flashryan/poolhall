<?php
/**
 * Outbound account email boundary.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

/**
 * Account flows send through this seam so integration verification can
 * capture messages instead of mailing, and a queued/branded sender can
 * replace wp_mail later without touching the services.
 */
interface Mailer {

	public function send( string $to, string $subject, string $message ): bool;
}
