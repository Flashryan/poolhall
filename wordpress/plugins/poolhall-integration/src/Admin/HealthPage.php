<?php
/**
 * Admin health screen.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Admin;

use Poolhall\Integration\Accounts\PortalPages;
use Poolhall\Integration\Jobs\SyncService;
use Poolhall\Integration\Support\Logger;

/**
 * "Poolhall Jobs" admin area (architecture doc §13): health summary, recent
 * redacted log events, a nonce-protected Sync now action, and the Site
 * setup runner — the same idempotent scripts wp-cli runs locally, exposed
 * for hosts without shell access (managed staging has no SSH via the
 * hosting API). Staff must be able to understand sync health without
 * reading logs.
 */
final class HealthPage {

	private const CAPABILITY   = 'manage_options';
	private const SYNC_ACTION  = 'poolhall_sync_now';
	private const SETUP_ACTION = 'poolhall_run_setup';
	private const DEMO_ACTION  = 'poolhall_seed_demo';

	public function __construct(
		private readonly SyncService $sync,
		private readonly Logger $logger,
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::SYNC_ACTION, array( $this, 'handle_sync_now' ) );
		add_action( 'admin_post_' . self::SETUP_ACTION, array( $this, 'handle_run_setup' ) );
		add_action( 'admin_post_' . self::DEMO_ACTION, array( $this, 'handle_seed_demo' ) );
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

	/**
	 * Run every setup step whose requirements are met, in dependency order
	 * (the jobs templates need the pages the theme shell creates). Steps
	 * are the same idempotent scripts wp-cli runs; their output is captured
	 * and shown back on the health screen.
	 */
	public function handle_run_setup(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'poolhall-integration' ), '', 403 );
		}
		check_admin_referer( self::SETUP_ACTION );

		$summary = array();
		foreach ( $this->setup_steps() as $step ) {
			if ( ! $step['runnable'] ) {
				$summary[] = array(
					'label'  => $step['label'],
					'state'  => 'skipped',
					'output' => $step['reason'],
				);
				continue;
			}

			if ( 'portal-pages' === $step['key'] ) {
				try {
					$pages  = PortalPages::ensure();
					$output = sprintf(
						/* translators: %d: number of portal pages. */
						__( '%d portal pages in place.', 'poolhall-integration' ),
						count( $pages )
					);
					$state = 'ok';
				} catch ( \RuntimeException $e ) {
					$output = $e->getMessage();
					$state  = 'failed';
				}
			} else {
				$output = $this->run_script( $step['key'] );
				$state  = str_contains( $output, 'FAIL' ) ? 'failed' : 'ok';
			}

			$summary[] = array(
				'label'  => $step['label'],
				'state'  => $state,
				'output' => trim( $output ),
			);
		}

		set_transient( $this->setup_transient_key(), $summary, 2 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'setup', '1', admin_url( 'admin.php?page=poolhall-jobs' ) ) );
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

		if ( isset( $_GET['setup'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
			$this->render_setup_summary();
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

		$this->render_setup_section();

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

	/** Outcome notices for the last Site setup run, then the stash clears. */
	private function render_setup_summary(): void {
		$summary = get_transient( $this->setup_transient_key() );
		delete_transient( $this->setup_transient_key() );
		if ( ! is_array( $summary ) ) {
			return;
		}

		foreach ( $summary as $step ) {
			$class = match ( $step['state'] ) {
				'ok'      => 'notice-success',
				'skipped' => 'notice-warning',
				default   => 'notice-error',
			};
			$lines = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $step['output'] ) ) ) );
			$brief = array() === $lines ? '' : end( $lines );

			echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>' . esc_html( (string) $step['label'] ) . ':</strong> ' . esc_html( $brief ) . '</p>';
			if ( count( $lines ) > 1 ) {
				echo '<details style="margin-bottom:8px"><summary>' . esc_html__( 'Details', 'poolhall-integration' ) . '</summary><pre style="font-size:11px">' . esc_html( (string) $step['output'] ) . '</pre></details>';
			}
			echo '</div>';
		}
	}

	private function render_setup_section(): void {
		echo '<h2>' . esc_html__( 'Site setup', 'poolhall-integration' ) . '</h2>';
		echo '<p style="max-width:760px">' . esc_html__( 'Runs the idempotent setup scripts — the same ones wp-cli runs locally — for hosts without shell access. Safe to re-run; steps whose requirements are missing are skipped and say why.', 'poolhall-integration' ) . '</p>';

		echo '<ul style="max-width:760px">';
		foreach ( $this->setup_steps() as $step ) {
			echo '<li>' . esc_html( $step['label'] ) . ' — '
				. ( $step['runnable']
					? '<strong>' . esc_html__( 'will run', 'poolhall-integration' ) . '</strong>'
					: esc_html( sprintf( /* translators: %s: requirement explanation. */ __( 'skipped: %s', 'poolhall-integration' ), $step['reason'] ) ) )
				. '</li>';
		}
		echo '</ul>';

		$setup_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::SETUP_ACTION ), self::SETUP_ACTION );
		echo '<p><a href="' . esc_url( $setup_url ) . '" class="button">' . esc_html__( 'Run site setup', 'poolhall-integration' ) . '</a></p>';

		// Staging-only demo content: the action and the script both refuse
		// to run on the production domain (directive guardrail 5).
		if ( ! $this->is_production_domain() ) {
			echo '<h2>' . esc_html__( 'Staging demo content', 'poolhall-integration' ) . '</h2>';
			echo '<p style="max-width:760px">' . esc_html__( 'Seeds sample jobs (source=demo) and demo review quotes from the approved prototype so the featured carousel, jobs archive and reviews section can be previewed before the Giig and Places integrations go live. Staging only.', 'poolhall-integration' ) . '</p>';
			$demo_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DEMO_ACTION ), self::DEMO_ACTION );
			echo '<p><a href="' . esc_url( $demo_url ) . '" class="button">' . esc_html__( 'Seed demo content', 'poolhall-integration' ) . '</a></p>';
		}
	}

	public function handle_seed_demo(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'poolhall-integration' ), '', 403 );
		}
		check_admin_referer( self::DEMO_ACTION );
		if ( $this->is_production_domain() ) {
			wp_die( esc_html__( 'Demo content never runs on the production domain.', 'poolhall-integration' ), '', 403 );
		}

		$summary = array(
			array(
				'label'  => __( 'Staging demo content', 'poolhall-integration' ),
				'state'  => 'ok',
				'output' => trim( $this->run_script( 'seed-demo-content.php' ) ),
			),
		);
		set_transient( $this->setup_transient_key(), $summary, 2 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'setup', '1', admin_url( 'admin.php?page=poolhall-jobs' ) ) );
		exit;
	}

	private function is_production_domain(): bool {
		return str_contains( (string) home_url(), 'poolhallrecruitment.co.uk' );
	}

	/**
	 * Steps in dependency order. The Elementor scripts keep their own
	 * requirement guards as a backstop; checking here turns a hard exit
	 * into a friendly skip.
	 *
	 * @return array<int,array{key:string,label:string,runnable:bool,reason:string}>
	 */
	private function setup_steps(): array {
		$elementor = (bool) did_action( 'elementor/loaded' );
		$pro       = $elementor && defined( 'ELEMENTOR_PRO_VERSION' );

		return array(
			array(
				'key'      => 'portal-pages',
				'label'    => __( 'Candidate portal pages', 'poolhall-integration' ),
				'runnable' => true,
				'reason'   => '',
			),
			array(
				'key'      => 'configure-elementor-kit.php',
				'label'    => __( 'Elementor kit Site Settings (design tokens)', 'poolhall-integration' ),
				'runnable' => $elementor,
				'reason'   => __( 'Elementor is not active.', 'poolhall-integration' ),
			),
			array(
				'key'      => 'create-theme-shell.php',
				'label'    => __( 'Theme shell (core pages, menus, header and footer)', 'poolhall-integration' ),
				'runnable' => $pro,
				'reason'   => __( 'Elementor Pro is not active.', 'poolhall-integration' ),
			),
			array(
				'key'      => 'create-jobs-templates.php',
				'label'    => __( 'Jobs loop items, archive and single-job template', 'poolhall-integration' ),
				'runnable' => $pro,
				'reason'   => __( 'Elementor Pro is not active.', 'poolhall-integration' ),
			),
			array(
				'key'      => 'create-team-page.php',
				'label'    => __( 'Meet the Team page content', 'poolhall-integration' ),
				// Needs Elementor plus the /team/ page; the theme shell step
				// creates that page earlier in this same run when Pro is active.
				'runnable' => $elementor && ( $pro || get_page_by_path( 'team' ) instanceof \WP_Post ),
				'reason'   => __( 'Elementor (or the theme shell /team/ page) is missing.', 'poolhall-integration' ),
			),
			array(
				'key'      => 'create-home-page.php',
				// Needs the featured-card loop item the jobs-templates step
				// records earlier in this same run.
				'label'    => __( 'Home page (hero, search, featured carousel)', 'poolhall-integration' ),
				'runnable' => $pro,
				'reason'   => __( 'Elementor Pro is not active.', 'poolhall-integration' ),
			),
			array(
				'key'      => 'create-marketing-pages.php',
				'label'    => __( 'Marketing pages (employers, sectors, services, contact, join our team)', 'poolhall-integration' ),
				// Needs Elementor plus the theme-shell pages, which the shell
				// step creates earlier in this same run when Pro is active.
				'runnable' => $elementor && ( $pro || get_page_by_path( 'employers' ) instanceof \WP_Post ),
				'reason'   => __( 'Elementor (or the theme shell pages) are missing.', 'poolhall-integration' ),
			),
		);
	}

	/** Capture a dev script's printed report. The scripts are idempotent. */
	private function run_script( string $filename ): string {
		ob_start();
		include POOLHALL_INTEGRATION_DIR . 'scripts/dev/' . $filename;
		return (string) ob_get_clean();
	}

	private function setup_transient_key(): string {
		return 'poolhall_setup_summary_' . get_current_user_id();
	}
}
