<?php
/**
 * Candidate registration.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Accounts;

use Poolhall\Integration\Support\Logger;

/**
 * Portal spec §4. The public outcome of every non-invalid registration is
 * the same generic check-your-email response: an address that already holds
 * an account gets an access email (or a verification resend when still
 * unverified) instead of an in-browser disclosure that it exists.
 */
final class RegistrationService {

	public function __construct(
		private CandidateRepository $candidates,
		private PasswordPolicy $passwords,
		private TokenPolicy $tokens,
		private ResendPolicy $resends,
		private Mailer $mailer,
		private Logger $logger,
	) {}

	/**
	 * @param array{first_name?:string,last_name?:string,email?:string,password?:string,accept_terms?:bool,alert_consent?:bool,terms_version?:string,privacy_version?:string,phone?:string,role_title?:string,location?:string,salary_expectations?:string,linkedin?:string,summary?:string} $input Submitted fields.
	 * @return array{status:'check_email'|'invalid',errors:string[]}
	 */
	public function register( array $input, \DateTimeImmutable $now ): array {
		$first_name = sanitize_text_field( $input['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $input['last_name'] ?? '' );
		$email      = EmailAddress::normalize( $input['email'] ?? '' );
		$password   = (string) ( $input['password'] ?? '' );

		// Extended profile (all optional; pushed to Giig once verified).
		$profile = array(
			'phone'               => sanitize_text_field( $input['phone'] ?? '' ),
			'role_title'          => sanitize_text_field( $input['role_title'] ?? '' ),
			'location'            => sanitize_text_field( $input['location'] ?? '' ),
			'salary_expectations' => sanitize_text_field( $input['salary_expectations'] ?? '' ),
			'linkedin'            => sanitize_text_field( $input['linkedin'] ?? '' ),
			'summary'             => sanitize_textarea_field( $input['summary'] ?? '' ),
		);

		$errors = array();
		if ( '' === $first_name ) {
			$errors[] = 'first_name_required';
		}
		if ( '' === $last_name ) {
			$errors[] = 'last_name_required';
		}
		if ( ! EmailAddress::is_valid( $email ) ) {
			$errors[] = 'email_invalid';
		}
		if ( true !== ( $input['accept_terms'] ?? false ) ) {
			$errors[] = 'terms_required';
		}
		$errors = array_merge( $errors, $this->passwords->validate( $password, $email ) );

		if ( array() !== $errors ) {
			return array(
				'status' => 'invalid',
				'errors' => $errors,
			);
		}

		$existing = $this->candidates->find_by_email( $email );
		if ( null !== $existing ) {
			$this->handle_existing_account( $existing, $now );
			return array(
				'status' => 'check_email',
				'errors' => array(),
			);
		}

		$user_id = $this->candidates->create_unverified( $email, $password, $first_name, $last_name );
		$this->candidates->record_consent(
			$user_id,
			array(
				'terms_version'   => (string) ( $input['terms_version'] ?? '' ),
				'privacy_version' => (string) ( $input['privacy_version'] ?? '' ),
				'alert_consent'   => true === ( $input['alert_consent'] ?? false ),
			),
			$now
		);
		// Store the profile and flag for Giig export; the push happens once the
		// candidate verifies their email (CandidateExporter on that action).
		$this->candidates->save_profile( $user_id, $profile );
		$this->candidates->mark_export_pending( $user_id );
		$this->send_verification( $user_id, $email, $now );
		$this->logger->log( 'candidate_registered', array( 'user_id' => $user_id ) );

		return array(
			'status' => 'check_email',
			'errors' => array(),
		);
	}

	/** New token (invalidating any previous), recorded send, mailed link. */
	public function send_verification( int $user_id, string $email, \DateTimeImmutable $now ): void {
		$token = $this->tokens->generate();
		$this->candidates->store_verification_token( $user_id, $this->tokens->hash_token( $token ), $now );

		$sends   = $this->resends->prune( $this->candidates->verification_sends( $user_id ), $now );
		$sends[] = $now;
		$this->candidates->record_verification_sends( $user_id, $sends );

		$link = home_url( '/candidate/verify-email/?token=' . $token );
		$this->mailer->send(
			$email,
			__( 'Verify your email — Poolhall Recruitment', 'poolhall-integration' ),
			sprintf(
				/* translators: %s: single-use verification link. */
				__( "Confirm your email address to activate your Poolhall Recruitment account:\n\n%s\n\nThis link can be used once and expires in 24 hours. If you did not create an account, you can ignore this email.", 'poolhall-integration' ),
				$link
			)
		);
	}

	/**
	 * Existing-address path, never disclosed in the browser: unverified
	 * candidates get the verification resent (within resend limits); any
	 * other account gets an access-help email.
	 */
	private function handle_existing_account( \WP_User $existing, \DateTimeImmutable $now ): void {
		$user_id = (int) $existing->ID;

		if ( CandidateRole::is_candidate( $existing ) && ! $this->candidates->is_verified( $user_id ) ) {
			$reason = $this->resends->denial_reason( $this->candidates->verification_sends( $user_id ), $now );
			if ( '' === $reason ) {
				$this->send_verification( $user_id, (string) $existing->user_email, $now );
			} else {
				$this->logger->log(
					'candidate_resend_limited',
					array(
						'user_id' => $user_id,
						'reason'  => $reason,
					)
				);
			}
			return;
		}

		$this->mailer->send(
			(string) $existing->user_email,
			__( 'Your Poolhall Recruitment account', 'poolhall-integration' ),
			sprintf(
				/* translators: %s: login URL. */
				__( "An account already exists for this email address. You can sign in or reset your password here:\n\n%s\n\nIf this wasn't you, no action is needed.", 'poolhall-integration' ),
				home_url( '/candidate/login/' )
			)
		);
		$this->logger->log( 'candidate_existing_email_register', array( 'user_id' => $user_id ) );
	}
}
