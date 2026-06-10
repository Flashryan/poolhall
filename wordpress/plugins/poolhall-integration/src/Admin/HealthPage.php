<?php
/**
 * Admin health screen.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Admin;

use Poolhall\Integration\Jobs\SyncService;
use Poolhall\Integration\Support\Logger;

/**
 * "Poolhall Jobs" admin area (architecture doc §13): health summary, recent
 * redacted log events and a nonce-protected Sync now action. Staff must be
 * able to understand sync health without reading logs.
 */
final class HealthPage {

	private const CAPABILITY  = 'manage_options';
	private const SYNC_ACTION = 'poolhall_sync_now';

	public function __construct(
		private readonly SyncService $sync,
		private readonly Logger $logger,
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::SYNC_ACTION, array( $this, 'handle_sync_now' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Poolhall Jobs', 'poolhall-integration' ),
			__( 'Poolhall Jobs', 'poolhall-integration' ),
			self::CAPABILITY,
			'poolhall-jobs',
			array( $this, 'render' ),
			'dashicons-chart-area',
			58
		);
	}

	public function handle_sync_now(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'poolhall-integration' ), '', 403 );
		}
		check_admin_referer( self::SYNC_ACTION );

		$this->sync->run( 'manual' );

		wp_safe_redirect( add_query_arg( 'synced', '1', admin_url( 'admin.php?page=poolhall-jobs' ) ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$state    = $this->sync->state();
		$last     = $state['last_run'] ?? null;
		$success  = $state['last_success'] ?? null;
		$failures = (int) ( $state['consecutive_failures'] ?? 0 );
		$next     = wp_next_scheduled( \Poolhall\Integration\Cron\Scheduler::HOOK );

		echo '<div class="wrap"><h1>' . esc_html__( 'Poolhall Jobs — Health', 'poolhall-integration' ) . '</h1>';

		if ( isset( $_GET['synced'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Sync completed. Summary below.', 'poolhall-integration' ) . '</p></div>';
		}

		if ( $failures >= 3 ) {
			echo '<div class="notice notice-error"><p><strong>'
				. esc_html( sprintf( /* translators: %d: failure count */ __( 'The job sync has failed %d times in a row. Existing jobs are preserved.', 'poolhall-integration' ), $failures ) )
				. '</strong></p></div>';
		}

		echo '<table class="widefat striped" style="max-width:760px"><tbody>';
		$rows = array(
			__( 'Last run status', 'poolhall-integration' )      => esc_html( (string) ( $last['status'] ?? 'never run' ) ),
			__( 'Last successful sync', 'poolhall-integration' ) => esc_html( (string) ( $success['completed_at'] ?? '—' ) ),
			__( 'Next scheduled sync', 'poolhall-integration' )  => $next ? esc_html( gmdate( 'Y-m-d H:i', $next ) . ' UTC' ) : esc_html__( 'not scheduled', 'poolhall-integration' ),
			__( 'Created / updated / unpublished / expired (last success)', 'poolhall-integration' ) => esc_html(
				sprintf(
					'%s / %s / %s / %s',
					$success['created'] ?? '—',
					$success['updated'] ?? '—',
					$success['unpublished'] ?? '—',
					$success['expired'] ?? '—'
				)
			),
			__( 'Consecutive failures', 'poolhall-integration' ) => esc_html( (string) $failures ),
		);
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row" style="width:340px">' . esc_html( $label ) . '</th><td>' . wp_kses_post( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';

		if ( ! empty( $last['guard_reason'] ) ) {
			echo '<div class="notice notice-warning inline" style="margin-top:12px;max-width:760px"><p><strong>'
				. esc_html__( 'Safety guard:', 'poolhall-integration' ) . '</strong> '
				. esc_html( (string) $last['guard_reason'] ) . '</p></div>';
		}

		$sync_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::SYNC_ACTION ), self::SYNC_ACTION );
		echo '<p style="margin-top:16px"><a href="' . esc_url( $sync_url ) . '" class="button button-primary">'
			. esc_html__( 'Sync now', 'poolhall-integration' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Recent events', 'poolhall-integration' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time (UTC)', 'poolhall-integration' )
			. '</th><th>' . esc_html__( 'Event', 'poolhall-integration' )
			. '</th><th>' . esc_html__( 'Details', 'poolhall-integration' ) . '</th></tr></thead><tbody>';
		foreach ( $this->logger->recent( 20 ) as $row ) {
			echo '<tr><td>' . esc_html( $row->created_at ) . '</td><td><code>' . esc_html( $row->event ) . '</code></td><td><code style="font-size:11px">'
				. esc_html( (string) $row->context ) . '</code></td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
