<?php
/**
 * Candidate profile and onboarding invitation email delivery.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Source\CandidateSink;
use Poolhall\Integration\Support\BrandedMail;

/**
 * Sends one passwordless Candidate Tasks link and records the immediate mail
 * hand-off separately from the candidate's workflow/task state.
 */
final class InviteMailer {

	private const STAGE_TASKS = array(
		'profile'       => array( 'full' ),
		'onboarding'    => array( 'contract', 'terms', 'starter', 'payroll' ),
		'task_contract' => array( 'contract' ),
		'task_terms'    => array( 'terms' ),
		'task_starter'  => array( 'starter' ),
		'task_payroll'  => array( 'payroll' ),
	);

	private const MAILABLE_TASK_STATUSES = array( 'awaiting', 'in_progress', 'needs_correction' );

	/** @var \Closure(string,string,string,array<int,string>):bool */
	private readonly \Closure $transport;

	/**
	 * The optional transport makes immediate acceptance/failure testable while
	 * production continues to use WordPress' configured mail transport.
	 *
	 * @param \Closure(string,string,string,array<int,string>):bool|null $transport
	 */
	public function __construct(
		private readonly InviteManager $invites,
		private readonly WorkflowState $state,
		private readonly ?CandidateSink $candidate_sink = null,
		?\Closure $transport = null,
	) {
		$this->transport = $transport
			?? static fn( string $to, string $subject, string $message, array $headers ): bool => (bool) wp_mail(
				$to,
				$subject,
				$message,
				$headers
			);
	}

	/**
	 * Send or safely resend a stage invitation.
	 *
	 * An unconsumed link is reconstructed rather than rotated. If the candidate
	 * already exchanged it for a browser session, a fresh stage link is issued;
	 * no Gravity Forms credential is ever placed in this email.
	 */
	public function send_stage( int $record_id, string $stage, bool $reminder = false ): bool {
		$stage = sanitize_key( $stage );
		if ( ! $this->valid_record( $record_id ) ) {
			return false;
		}
		$tasks = $this->mail_tasks( $record_id, $stage, $reminder );
		if ( array() === $tasks ) {
			$this->record_attempt( $record_id, $stage, $tasks, false, 'invalid_stage_or_no_pending_tasks', $reminder );
			return false;
		}

		$email = sanitize_email( (string) get_post_meta( $record_id, 'applicant_email', true ) );
		if ( '' === $email || ! is_email( $email ) ) {
			$this->record_attempt( $record_id, $stage, $tasks, false, 'invalid_recipient', $reminder );
			return false;
		}

		try {
			$token = $this->invites->token_for( $record_id, $stage );
			if ( '' === $token ) {
				// Missing, expired or already exchanged: create one fresh access link.
				$token = $this->invites->issue( $record_id, $stage, true );
			}
			$link = '' !== $token ? $this->invites->link( $token ) : '';
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			$token = '';
			$link  = '';
		}
		if ( '' === $token ) {
			$this->record_attempt( $record_id, $stage, $tasks, false, 'link_unavailable', $reminder );
			return false;
		}
		if ( '' === $link ) {
			$this->record_attempt( $record_id, $stage, $tasks, false, 'link_unavailable', $reminder );
			return false;
		}

		$first   = sanitize_text_field( (string) get_post_meta( $record_id, 'applicant_first', true ) );
		$subject = $this->subject( $stage, $reminder );
		$message = $this->message( $first, $stage, $link, $reminder );
		$error   = '';
		try {
			$sent = BrandedMail::send(
				$email,
				$subject,
				$this->html( $first, $stage, $link, $reminder ),
				$message,
				array(),
				$this->transport
			);
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			$sent  = false;
			$error = 'transport_exception';
		}

		$this->record_attempt( $record_id, $stage, $tasks, $sent, $sent ? '' : ( '' !== $error ? $error : 'mail_not_accepted' ), $reminder );
		if ( $sent && ! $reminder ) {
			$this->add_giig_note( $record_id, $stage );
		}
		return $sent;
	}

	/** Send an idempotent reminder without adding routine chase noise to Giig. */
	public function send_reminder( int $record_id, string $stage ): bool {
		return $this->send_stage( $record_id, $stage, true );
	}

	/** A single document gets its own scoped invite and mail status. */
	public function send_document( int $record_id, string $kind ): bool {
		if ( ! in_array( $kind, TaskPolicy::documents(), true ) ) {
			return false;
		}
		return $this->send_stage( $record_id, 'task_' . $kind );
	}

	/** Unix timestamp of the first mail hand-off accepted for this stage. */
	public function first_sent_at( int $record_id, string $stage ): int {
		return $this->timestamp_meta( $record_id, $this->stage_meta( $stage, 'mail_sent_at' ) );
	}

	/** Latest successful initial/manual/resume mail, used as the reminder cooldown baseline. */
	public function reminder_baseline_at( int $record_id, string $stage ): int {
		$last = $this->timestamp_meta( $record_id, $this->stage_meta( $stage, 'mail_last_non_reminder_at' ) );
		return $last > 0 ? $last : $this->first_sent_at( $record_id, $stage );
	}

	/** @return string[] */
	private function mail_tasks( int $record_id, string $stage, bool $reminder ): array {
		$available = array_intersect( self::STAGE_TASKS[ $stage ] ?? array(), TaskPolicy::tasks() );
		$statuses  = $reminder ? array( 'awaiting', 'in_progress', 'needs_correction' ) : self::MAILABLE_TASK_STATUSES;
		return array_values(
			array_filter(
				$available,
				fn( string $kind ): bool => in_array( $this->state->task_status( $record_id, $kind ), $statuses, true )
			)
		);
	}

	private function valid_record( int $record_id ): bool {
		return $record_id > 0
			&& 'poolhall_application' === get_post_type( $record_id )
			&& in_array( (string) get_post_meta( $record_id, 'kind', true ), array( 'registration', 'job_application' ), true );
	}

	private function subject( string $stage, bool $reminder ): string {
		$prefix = $reminder ? 'Reminder: ' : '';
		if ( str_starts_with( $stage, 'task_' ) ) {
			return $prefix . 'complete your Poolhall ' . $this->document_label( substr( $stage, 5 ) );
		}
		return $prefix . ( 'profile' === $stage
			? 'complete your Poolhall candidate profile'
			: 'complete your Poolhall onboarding tasks' );
	}

	private function message( string $first, string $stage, string $link, bool $reminder ): string {
		// Exactly one candidate-facing link: the hub exchanges the stage token
		// for a secure session before it exposes any managed form.
		return $this->greeting( $first ) . "\n\n"
			. $this->opening( $stage, $reminder ) . "\n\n"
			. 'Continue securely:' . "\n"
			. $link . "\n\n"
			. 'This personal link is time-limited. If you need help, call 0121 516 3000.' . "\n\n"
			. 'Poolhall Recruitment Limited';
	}

	/** The same message in the branded shell; the link becomes the button. */
	private function html( string $first, string $stage, string $link, bool $reminder ): string {
		return BrandedMail::heading( esc_html( ucfirst( $this->subject( $stage, $reminder ) ) ) )
			. BrandedMail::paragraph( esc_html( $this->greeting( $first ) ) )
			. BrandedMail::paragraph( esc_html( $this->opening( $stage, $reminder ) ) )
			. BrandedMail::button( $link, 'Continue securely' )
			. BrandedMail::paragraph(
				'This personal link is time-limited. If you need help, call '
				. BrandedMail::link( 'tel:+441215163000', '0121 516 3000' ) . '.'
			)
			. BrandedMail::paragraph( 'Thanks,<br /><strong style="color:#0B2846;">The Poolhall team</strong>' );
	}

	private function greeting( string $first ): string {
		return '' !== $first ? 'Hi ' . $first . ',' : 'Hello,';
	}

	private function opening( string $stage, bool $reminder ): string {
		$what = 'profile' === $stage ? 'candidate profile' : ( str_starts_with( $stage, 'task_' ) ? $this->document_label( substr( $stage, 5 ) ) : 'onboarding tasks' );
		if ( ! $reminder ) {
			return 'Please complete your ' . $what
				. ' using your secure Candidate Tasks page.';
		}
		return 'This is a reminder that your ' . $what . ( 'onboarding' === $stage ? ' are' : ' is' ) . ' still waiting to be completed.';
	}

	private function document_label( string $kind ): string {
		return match ( $kind ) {
			'contract' => 'Agency Worker Contract',
			'terms'    => 'Candidate Terms',
			'starter'  => 'Starter Checklist',
			'payroll'  => 'Payroll Details',
			default    => 'document',
		};
	}

	/** @param string[] $tasks */
	private function record_attempt( int $record_id, string $stage, array $tasks, bool $accepted, string $error, bool $reminder ): void {
		if ( $record_id <= 0 || ! isset( self::STAGE_TASKS[ $stage ] ) ) {
			return;
		}
		$now        = gmdate( \DateTimeInterface::ATOM );
		$attempts   = (int) get_post_meta( $record_id, $this->stage_meta( $stage, 'mail_attempts' ), true );
		$mail_class = $reminder ? 'reminder' : 'non_reminder';
		update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_attempts' ), (string) ( $attempts + 1 ) );
		update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_status' ), $accepted ? 'accepted' : 'failed' );
		update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_attempted_at' ), $now );
		update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_last_' . $mail_class . '_attempted_at' ), $now );

		if ( $accepted ) {
			if ( '' === (string) get_post_meta( $record_id, $this->stage_meta( $stage, 'mail_sent_at' ), true ) ) {
				update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_sent_at' ), $now );
			}
			update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_last_accepted_at' ), $now );
			update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_last_' . $mail_class . '_at' ), $now );
			delete_post_meta( $record_id, $this->stage_meta( $stage, 'mail_error' ) );
		} else {
			update_post_meta( $record_id, $this->stage_meta( $stage, 'mail_error' ), sanitize_key( $error ) );
		}

		foreach ( $tasks as $kind ) {
			$this->state->set_mail_status( $record_id, $kind, $accepted );
			$key = $this->state->meta( $kind, 'mail_attempts' );
			update_post_meta( $record_id, $key, (string) ( (int) get_post_meta( $record_id, $key, true ) + 1 ) );
			update_post_meta( $record_id, $this->state->meta( $kind, 'mail_last_' . $mail_class . '_attempted_at' ), $now );
			if ( $accepted ) {
				update_post_meta( $record_id, $this->state->meta( $kind, 'mail_last_accepted_at' ), $now );
				update_post_meta( $record_id, $this->state->meta( $kind, 'mail_last_' . $mail_class . '_at' ), $now );
				delete_post_meta( $record_id, $this->state->meta( $kind, 'mail_error' ) );
			} else {
				update_post_meta( $record_id, $this->state->meta( $kind, 'mail_error' ), sanitize_key( $error ) );
			}
		}
	}

	/** A successful email is the prerequisite for the non-fatal ATS note. */
	private function add_giig_note( int $record_id, string $stage ): void {
		if ( null === $this->candidate_sink || '' !== (string) get_post_meta( $record_id, $this->stage_meta( $stage, 'giig_noted_at' ), true ) ) {
			return;
		}
		$candidate_id = (string) get_post_meta( $record_id, 'giig_candidate_id', true );
		if ( '' === $candidate_id || ! is_callable( array( $this->candidate_sink, 'add_note' ) ) ) {
			return;
		}

		try {
			if ( ! $this->candidate_sink->is_configured() ) {
				return;
			}
			$callback = array( $this->candidate_sink, 'add_note' );
			$callback(
				$candidate_id,
				'profile' === $stage
					? 'Website: candidate profile invitation emailed successfully.'
					: ( str_starts_with( $stage, 'task_' )
						? 'Website: ' . $this->document_label( substr( $stage, 5 ) ) . ' invitation emailed successfully.'
						: 'Website: onboarding tasks invitation emailed successfully.' )
			);
			update_post_meta( $record_id, $this->stage_meta( $stage, 'giig_noted_at' ), gmdate( \DateTimeInterface::ATOM ) );
			update_post_meta( $record_id, $this->stage_meta( $stage, 'giig_note_status' ), 'accepted' );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			update_post_meta( $record_id, $this->stage_meta( $stage, 'giig_note_status' ), 'failed' );
		}
	}

	private function stage_meta( string $stage, string $suffix ): string {
		return 'invite_' . sanitize_key( $stage ) . '_' . sanitize_key( $suffix );
	}

	private function timestamp_meta( int $record_id, string $key ): int {
		$value = (string) get_post_meta( $record_id, $key, true );
		$time  = '' !== $value ? strtotime( $value ) : false;
		return false === $time ? 0 : $time;
	}
}
