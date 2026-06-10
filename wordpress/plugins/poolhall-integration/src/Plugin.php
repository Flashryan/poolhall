<?php
/**
 * Plugin bootstrap and wiring.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration;

use Poolhall\Integration\Admin\HealthPage;
use Poolhall\Integration\Cron\Scheduler;
use Poolhall\Integration\Jobs\ExpiryPolicy;
use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Jobs\JobRepository;
use Poolhall\Integration\Jobs\SyncPlanner;
use Poolhall\Integration\Jobs\SyncService;
use Poolhall\Integration\Schema\SchemaOutput;
use Poolhall\Integration\Source\Giig\GiigClient;
use Poolhall\Integration\Source\Giig\GiigJobSource;
use Poolhall\Integration\Source\JobSource;
use Poolhall\Integration\Support\Logger;
use Poolhall\Integration\Support\Options;

/**
 * Wires services and hooks. No business logic lives here.
 */
final class Plugin {

	private const DB_VERSION_OPTION = 'poolhall_db_version';
	private const DB_VERSION        = 1;

	private static ?self $instance = null;

	private ?SyncService $sync_service = null;

	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = new self();
		self::$instance->register();
	}

	public static function instance(): ?self {
		return self::$instance;
	}

	public static function activate(): void {
		Logger::install();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		( new JobPostType() )->register();
		Scheduler::activate();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		Scheduler::deactivate();
		flush_rewrite_rules();
	}

	private function register(): void {
		$post_type = new JobPostType();
		add_action( 'init', array( $post_type, 'register' ) );

		// Run pending migrations after plugin updates deployed via Git.
		add_action(
			'init',
			static function (): void {
				if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) < self::DB_VERSION ) {
					Logger::install();
					update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
				}
			},
			5
		);

		$scheduler = new Scheduler();
		$scheduler->register();
		add_action( Scheduler::HOOK, array( $this, 'run_scheduled_sync' ) );

		$options = new Options();
		( new SchemaOutput( $options ) )->register();

		if ( is_admin() ) {
			( new HealthPage( $this->sync_service(), new Logger() ) )->register();
		}
	}

	public function run_scheduled_sync(): void {
		$this->sync_service()->run( 'cron' );
		( new Logger() )->prune();
	}

	/**
	 * Lazily build the sync service. The job source is filterable so staging
	 * tests can substitute a fixture source without touching Giig
	 * (`poolhall_job_source` filter).
	 */
	public function sync_service(): SyncService {
		if ( null !== $this->sync_service ) {
			return $this->sync_service;
		}

		$source = apply_filters( 'poolhall_job_source', null );
		if ( ! $source instanceof JobSource ) {
			try {
				$source = new GiigJobSource( GiigClient::from_environment() );
			} catch ( \Poolhall\Integration\Source\SourceException ) {
				$source = new \Poolhall\Integration\Source\UnconfiguredJobSource();
			}
		}

		$this->sync_service = new SyncService(
			$source,
			new JobRepository(),
			new SyncPlanner(),
			new ExpiryPolicy(),
			new Logger()
		);

		return $this->sync_service;
	}
}
