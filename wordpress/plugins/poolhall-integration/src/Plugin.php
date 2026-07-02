<?php
/**
 * Plugin bootstrap and wiring.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration;

use Poolhall\Integration\Accounts\AuthEndpoints;
use Poolhall\Integration\Accounts\AuthForms;
use Poolhall\Integration\Accounts\CandidateRepository;
use Poolhall\Integration\Accounts\CandidateRole;
use Poolhall\Integration\Accounts\FailedLoginStore;
use Poolhall\Integration\Accounts\LoginRateLimiter;
use Poolhall\Integration\Accounts\LoginService;
use Poolhall\Integration\Accounts\PasswordPolicy;
use Poolhall\Integration\Accounts\PasswordRecoveryService;
use Poolhall\Integration\Accounts\PortalGuard;
use Poolhall\Integration\Accounts\RegistrationService;
use Poolhall\Integration\Accounts\ResendPolicy;
use Poolhall\Integration\Accounts\ReturnUrlPolicy;
use Poolhall\Integration\Accounts\SavedJobsController;
use Poolhall\Integration\Accounts\SavedJobsRepository;
use Poolhall\Integration\Accounts\SecurityEndpoints;
use Poolhall\Integration\Accounts\SecurityService;
use Poolhall\Integration\Accounts\SessionDescriber;
use Poolhall\Integration\Accounts\SessionRegistry;
use Poolhall\Integration\Accounts\TokenPolicy;
use Poolhall\Integration\Accounts\VerificationService;
use Poolhall\Integration\Accounts\WpMailer;
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
	private const DB_VERSION        = 2;

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
		SavedJobsRepository::install();
		CandidateRole::install();
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
					SavedJobsRepository::install();
					CandidateRole::install();
					update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
				}
			},
			5
		);

		( new CandidateRole() )->register();
		$this->wire_accounts();
		( new \Poolhall\Integration\DesignSystem\StyleGuide() )->register();
		( new \Poolhall\Integration\DesignSystem\HeaderControls() )->register();
		( new \Poolhall\Integration\Jobs\ArchiveQuery() )->register();
		$featured_query = new \Poolhall\Integration\Jobs\FeaturedQuery();
		$featured_query->register();
		( new \Poolhall\Integration\DesignSystem\V2Fragments( $featured_query ) )->register();
		( new \Poolhall\Integration\Jobs\SearchForm() )->register();
		( new \Poolhall\Integration\Jobs\JobCardBits() )->register();
		( new \Poolhall\Integration\Jobs\JobsArchive(
			new \Poolhall\Integration\Jobs\ArchiveQuery(),
			new \Poolhall\Integration\Jobs\JobFacets(),
			new \Poolhall\Integration\Jobs\JobCardBits()
		) )->register();

		$enquiries = new \Poolhall\Integration\Enquiries\EnquiryService( new WpMailer(), new Logger() );
		( new \Poolhall\Integration\Enquiries\EnquiryEndpoints( $enquiries ) )->register();
		( new \Poolhall\Integration\Enquiries\EnquiryForm() )->register();

		// Candidate application popup: first-party capture emailed to Poolhall
		// (build reference applypopupjourney.html). Not a Giig push.
		( new \Poolhall\Integration\Applications\ApplicationRecord() )->register();
		( new \Poolhall\Integration\Applications\ApplicationEndpoints(
			$this->application_service()
		) )->register();
		( new \Poolhall\Integration\Applications\ApplicationForm( new Options() ) )->register();

		// Marketing components consumed by Elementor templates (§11.6 tiers).
		( new \Poolhall\Integration\Marketing\ServiceTiers() )->register();

		$scheduler = new Scheduler();
		$scheduler->register();
		add_action( Scheduler::HOOK, array( $this, 'run_scheduled_sync' ) );

		$options = new Options();
		( new SchemaOutput( $options ) )->register();
		( new \Poolhall\Integration\Reviews\ReviewsCarousel( $this->reviews_service() ) )->register();

		if ( is_admin() ) {
			( new HealthPage( $this->sync_service(), new Logger() ) )->register();
		}
	}

	/** Candidate accounts: auth flows, portal guard and the saved-jobs endpoints. */
	private function wire_accounts(): void {
		$logger     = new Logger();
		$candidates = new CandidateRepository();
		$tokens     = new TokenPolicy();
		$resends    = new ResendPolicy();
		$passwords  = new PasswordPolicy();
		$mailer     = new WpMailer();
		$returns    = new ReturnUrlPolicy();

		$limiter      = new LoginRateLimiter();
		$failures     = new FailedLoginStore( $limiter );
		$registration = new RegistrationService( $candidates, $passwords, $tokens, $resends, $mailer, $logger );
		$verification = new VerificationService( $candidates, $tokens, $resends, $registration, $logger );
		$login        = new LoginService( $candidates, $limiter, $failures, $logger );
		$recovery     = new PasswordRecoveryService( $candidates, $passwords, $tokens, $resends, $mailer, $logger );
		$security     = new SecurityService( $candidates, $passwords, $tokens, $resends, $limiter, $failures, $mailer, $logger );
		$sessions     = new SessionRegistry();

		// Push verified candidates into Giig (never-lose: emails Poolhall when
		// the live endpoint is not configured). Hooked to verification.
		$exporter = new \Poolhall\Integration\Accounts\CandidateExporter( $candidates, $this->candidate_sink(), $mailer, $logger );
		add_action( 'poolhall_candidate_verified', array( $exporter, 'export' ) );

		( new PortalGuard( $candidates, $returns ) )->register();
		( new AuthEndpoints( $login, $registration, $verification, $recovery, $returns ) )->register();
		( new SecurityEndpoints( $security, $sessions, $candidates ) )->register();
		( new AuthForms( $verification, $security, $sessions, new SessionDescriber(), new SavedJobsRepository(), new JobRepository() ) )->register();
		( new SavedJobsController( new SavedJobsRepository(), new JobRepository(), $candidates, $returns ) )->register();
	}

	/**
	 * Build the candidate sink. Filterable (`poolhall_candidate_sink`) so a
	 * fixture can stand in on staging; otherwise the Giig sink when configured,
	 * else the null sink that triggers the email fallback.
	 */
	private function candidate_sink(): \Poolhall\Integration\Source\CandidateSink {
		$sink = apply_filters( 'poolhall_candidate_sink', null );
		if ( $sink instanceof \Poolhall\Integration\Source\CandidateSink ) {
			return $sink;
		}
		try {
			$company = defined( 'POOLHALL_GIIG_COMPANY_ID' ) ? (string) constant( 'POOLHALL_GIIG_COMPANY_ID' ) : null;
			return new \Poolhall\Integration\Source\Giig\GiigCandidateSink( GiigClient::from_environment(), $company );
		} catch ( \Poolhall\Integration\Source\SourceException ) {
			return new \Poolhall\Integration\Source\UnconfiguredCandidateSink();
		}
	}

	/**
	 * Application delivery service with the Giig push sink attached when the
	 * credentials are configured (directive §8: two-step candidate+applicant
	 * write; CV stays local+email, no Giig upload endpoint).
	 */
	public function application_service(): \Poolhall\Integration\Applications\ApplicationService {
		$sink = null;
		try {
			$company = defined( 'POOLHALL_GIIG_COMPANY_ID' ) ? (string) constant( 'POOLHALL_GIIG_COMPANY_ID' ) : null;
			// Short per-call timeout: the two-step push runs inside the
			// candidate's own request, which must never outlive PHP's 30s
			// budget (a timeout mid-request would break the modal's JSON).
			$sink = new \Poolhall\Integration\Source\Giig\GiigApplicationSink( GiigClient::from_environment( 8 ), $company );
		} catch ( \Poolhall\Integration\Source\SourceException ) {
			$sink = null; // Credentials absent: first-party capture only.
		}
		return new \Poolhall\Integration\Applications\ApplicationService( new Logger(), $sink );
	}

	public function run_scheduled_sync(): void {
		$this->sync_service()->run( 'cron' );
		// Retry any applications whose Giig push failed (directive §8/§12).
		$this->application_service()->retry_queued_pushes();
		// CachePolicy makes this a no-op unless the snapshot is >24h old.
		$this->reviews_service()->refresh();
		( new Logger() )->prune();
	}

	public function reviews_service(): \Poolhall\Integration\Reviews\ReviewsService {
		return new \Poolhall\Integration\Reviews\ReviewsService(
			static fn() => \Poolhall\Integration\Reviews\PlacesClient::from_environment()->fetch_snapshot(),
			new \Poolhall\Integration\Reviews\CachePolicy(),
			new Logger()
		);
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
				// CompanyId is required on some Giig calls (single-job refresh);
				// same constant GiigCandidateSink already reads, kept consistent.
				$company = defined( 'POOLHALL_GIIG_COMPANY_ID' ) ? (string) constant( 'POOLHALL_GIIG_COMPANY_ID' ) : null;
				$source  = new GiigJobSource( GiigClient::from_environment(), company_id: $company );
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
