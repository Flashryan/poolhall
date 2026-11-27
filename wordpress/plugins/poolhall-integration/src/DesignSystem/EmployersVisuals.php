<?php
/**
 * Employers-page visual assets.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\DesignSystem;

/**
 * Loads page-specific visual treatment without affecting other page headers.
 */
final class EmployersVisuals {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 30 );
	}

	public function enqueue(): void {
		if ( ! is_page( 'employers' ) ) {
			return;
		}

		wp_enqueue_style(
			'poolhall-employers',
			POOLHALL_INTEGRATION_URL . 'assets/css/employers.css',
			array( 'hello-elementor-child' ),
			POOLHALL_INTEGRATION_VERSION
		);
	}
}
