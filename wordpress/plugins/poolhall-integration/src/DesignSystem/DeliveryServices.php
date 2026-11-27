<?php
/**
 * Recruitment delivery service catalogue.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\DesignSystem;

/**
 * Single source of truth for the five employer delivery options.
 */
final class DeliveryServices {

	/**
	 * @return array<int,array{0:string,1:string,2:string,3:string,4:string}>
	 */
	public static function all(): array {
		return array(
			array( '01', 'users', 'Temporary', 'Flexible, compliant workforce when you need to scale up fast. Vetted, paid and managed by us.', 'employers-service-temporary.webp' ),
			array( '02', 'user-check', 'Permanent', 'Full end-to-end search for permanent hires, sourced, screened and shortlisted to a brief.', 'employers-service-permanent.webp' ),
			array( '03', 'trending-up', 'Scale', 'Volume and project recruitment for growth phases, multiple hires through one accountable partner.', 'employers-service-scale.webp' ),
			array( '04', 'calendar-clock', 'Pay Monthly', 'Spread the cost of a permanent placement across manageable monthly payments.', 'employers-service-pay-monthly.webp' ),
			array( '05', 'hard-hat', 'On-Site', 'A fully managed on-site service for high-volume, single-location requirements.', 'employers-service-onsite.webp' ),
		);
	}
}
