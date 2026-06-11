<?php
/**
 * Saved-jobs HTTP endpoints.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Jobs\JobRepository;

/**
 * The save control's backend (portal spec §7, hard rule 6): REST routes for
 * the enhanced toggle and an admin-post fallback so saving works without
 * JavaScript. Every route operates on the current user only — no user
 * identifier is ever accepted from the request, so cross-account access is
 * impossible by construction. Signed-out calls get 401 (the UI opens the
 * login path with a return to the same role); unverified candidates get
 * 403 with a `verification_required` code.
 */
final class SavedJobsController {

	public const REST_NAMESPACE = 'poolhall/v1';

	public function __construct(
		private SavedJobsRepository $saved,
		private JobRepository $jobs,
		private CandidateRepository $candidates,
		private ReturnUrlPolicy $returns,
	) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_post_poolhall_save_job', array( $this, 'handle_form_save' ) );
		add_action( 'admin_post_nopriv_poolhall_save_job', array( $this, 'handle_signed_out_save' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/saved-jobs',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_saved' ),
					'permission_callback' => array( $this, 'permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_saved' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'source'        => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'source_job_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'saved'         => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/saved-jobs/expired',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_expired' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	public function permission() {
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return new \WP_Error( 'not_signed_in', __( 'Sign in to save jobs.', 'poolhall-integration' ), array( 'status' => 401 ) );
		}
		if ( ! CandidateRole::is_candidate( $user_id ) ) {
			return new \WP_Error( 'not_a_candidate', __( 'Saved jobs are available on candidate accounts.', 'poolhall-integration' ), array( 'status' => 403 ) );
		}
		if ( ! $this->candidates->is_verified( $user_id ) ) {
			return new \WP_Error( 'verification_required', __( 'Confirm your email address to save jobs.', 'poolhall-integration' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function list_saved(): \WP_REST_Response {
		$user_id = get_current_user_id();
		$items   = array();
		foreach ( $this->saved->list_for_user( $user_id, $this->jobs ) as $item ) {
			$items[] = array(
				'source'        => $item['source'],
				'source_job_id' => $item['source_job_id'],
				'saved_at'      => $item['saved_at'],
				'status'        => $item['status'],
				'title'         => null !== $item['post_id'] ? get_the_title( $item['post_id'] ) : '',
				'url'           => null !== $item['post_id'] ? get_permalink( $item['post_id'] ) : '',
			);
		}
		return new \WP_REST_Response(
			array(
				'items' => $items,
				'count' => $this->saved->count_for_user( $user_id ),
			)
		);
	}

	/**
	 * Sets the requested state idempotently: repeating a save or unsave is a
	 * success, not an error (spec §7).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_saved( \WP_REST_Request $request ) {
		$source        = (string) $request['source'];
		$source_job_id = (string) $request['source_job_id'];

		// Only jobs that exist locally can be saved; the relationship is
		// still keyed by source identity so it survives recreation.
		if ( null === $this->jobs->find_post_id( $source, $source_job_id ) ) {
			return new \WP_Error( 'unknown_job', __( 'That job is no longer available.', 'poolhall-integration' ), array( 'status' => 404 ) );
		}

		$user_id = get_current_user_id();
		$now     = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		if ( true === (bool) $request['saved'] ) {
			$this->saved->save( $user_id, $source, $source_job_id, $now );
		} else {
			$this->saved->unsave( $user_id, $source, $source_job_id );
		}

		return new \WP_REST_Response(
			array(
				'saved' => $this->saved->is_saved( $user_id, $source, $source_job_id ),
				'count' => $this->saved->count_for_user( $user_id ),
			)
		);
	}

	/** Candidate-confirmed bulk removal of saved jobs that are no longer live. */
	public function clear_expired(): \WP_REST_Response {
		$user_id = get_current_user_id();
		return new \WP_REST_Response(
			array(
				'removed' => $this->saved->clear_not_live_for_user( $user_id, $this->jobs ),
				'count'   => $this->saved->count_for_user( $user_id ),
			)
		);
	}

	/** No-JS form fallback: toggle, then return to the page the control sat on. */
	public function handle_form_save(): never {
		$return = $this->returns->sanitize( $this->field( 'return' ) );

		if ( false === wp_verify_nonce( $this->field( '_wpnonce' ), 'poolhall_save_job' ) ) {
			$this->redirect( $return );
		}

		$user_id = get_current_user_id();
		if ( ! CandidateRole::is_candidate( $user_id ) || ! $this->candidates->is_verified( $user_id ) ) {
			// Unverified candidates are prompted to verify (spec §7).
			$this->redirect( PortalGuard::VERIFY_PATH . '?state=required' );
		}

		$source        = sanitize_key( $this->field( 'source' ) );
		$source_job_id = $this->field( 'source_job_id' );
		if ( null !== $this->jobs->find_post_id( $source, $source_job_id ) ) {
			$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
			if ( 'unsave' === $this->field( 'do' ) ) {
				$this->saved->unsave( $user_id, $source, $source_job_id );
			} else {
				$this->saved->save( $user_id, $source, $source_job_id, $now );
			}
		}
		$this->redirect( $return );
	}

	/** Signed-out save: open the login path and come back to the same role (spec §7). */
	public function handle_signed_out_save(): never {
		$return = $this->returns->sanitize( $this->field( 'return' ) );
		$this->redirect( PortalGuard::LOGIN_PATH . '?return=' . rawurlencode( $return ) );
	}

	private function field( string $name ): string {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in the handler before any state change.
	}

	/** @param string $destination Site-relative destination. */
	private function redirect( string $destination ): never {
		wp_safe_redirect( home_url( $destination ) );
		exit;
	}
}
