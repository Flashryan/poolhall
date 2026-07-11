<?php
/**
 * Candidate document workflow: forms, PDFs, notifications.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

use Poolhall\Integration\Onboarding\InviteManager;
use Poolhall\Integration\Source\Giig\GiigCandidateSink;
use Poolhall\Integration\Support\Logger;

/**
 * Glues Gravity Forms to the document pipeline: registers the custom
 * signature field, creates/updates the three document forms, and on
 * submission builds the branded PDF, emails it (plus safe uploads) to
 * the admin inbox, sends the candidate a data-free receipt, and records
 * an entry note either way. Admin notifications on these forms are
 * custom wp_mail sends AFTER PDF generation — the ordering Gravity
 * Forms' own notifications can't guarantee for attachments.
 *
 * Never logged or emailed to candidates: bank details, NI numbers,
 * health answers, signature data, or any full form payload.
 */
final class DocumentWorkflow {

	public const FORM_MAP_OPTION     = 'poolhall_document_forms';
	public const HIDDEN_PAGES_OPTION = 'poolhall_hidden_doc_pages';
	private const ADMIN_EMAIL        = 'jobs@poolhallrecruitment.co.uk';

	public function __construct(
		private readonly Logger $logger,
		private readonly InviteManager $invites = new InviteManager(),
	) {
	}

	public function register(): void {
		// gform_loaded may already have fired by the time this runs, so
		// also register on init (register_field is idempotent either way).
		add_action( 'gform_loaded', array( $this, 'register_field' ), 5 );
		add_action( 'init', array( $this, 'register_field' ), 5 );
		add_action( 'gform_after_submission', array( $this, 'handle_submission' ), 10, 2 );
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 1 );
		add_filter( 'gform_get_form_filter', array( $this, 'gate_form' ), 10, 2 );
		add_filter( 'gform_pre_render', array( $this, 'prefill_form' ) );
		add_filter( 'wp_robots', array( $this, 'noindex_hidden_pages' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_hidden_from_sitemap' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'exclude_hidden_from_search' ) );
	}

	/**
	 * @param array  $args      Sitemap query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function exclude_hidden_from_sitemap( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$hidden = array_map( 'intval', (array) get_option( self::HIDDEN_PAGES_OPTION, array() ) );
			if ( array() !== $hidden ) {
				$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), $hidden );
			}
		}
		return $args;
	}

	/**
	 * @param \WP_Query $query Query.
	 */
	public function exclude_hidden_from_search( $query ): void {
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			$hidden = array_map( 'intval', (array) get_option( self::HIDDEN_PAGES_OPTION, array() ) );
			if ( array() !== $hidden ) {
				$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $hidden ) );
			}
		}
	}

	public function register_field(): void {
		if ( class_exists( '\GF_Fields' ) && ! \GF_Fields::exists( 'poolhall_signature' ) ) {
			\GF_Fields::register( new SignatureField() );
		}
	}

	/**
	 * Idempotent create-or-update of the three document forms; returns the
	 * title=>id map (also persisted for the submission dispatcher).
	 *
	 * @return array<string,int>
	 */
	public function setup_forms(): array {
		if ( ! class_exists( '\GFAPI' ) ) {
			return array();
		}
		$this->register_field();

		$definitions = array(
			'full'    => FormDefinitions::full_registration(),
			'starter' => FormDefinitions::starter_statement(),
			'terms'   => FormDefinitions::candidate_terms(),
		);

		$map = (array) get_option( self::FORM_MAP_OPTION, array() );
		foreach ( $definitions as $key => $definition ) {
			$existing_id = isset( $map[ $key ] ) ? (int) $map[ $key ] : 0;
			if ( $existing_id > 0 && is_array( \GFAPI::get_form( $existing_id ) ) ) {
				$definition['id'] = $existing_id;
				\GFAPI::update_form( $definition, $existing_id );
				continue;
			}
			$created = \GFAPI::add_form( $definition );
			if ( is_int( $created ) && $created > 0 ) {
				$map[ $key ] = $created;
			}
		}
		update_option( self::FORM_MAP_OPTION, $map, false );
		return array_map( 'intval', $map );
	}


	/** Which document kind a form id belongs to; '' when not ours. */
	private function kind_for_form( int $form_id ): string {
		$map  = array_map( 'intval', (array) get_option( self::FORM_MAP_OPTION, array() ) );
		$kind = array_search( $form_id, $map, true );
		return false === $kind ? '' : (string) $kind;
	}

	private function current_token(): string {
		return isset( $_GET[ InviteManager::QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ InviteManager::QUERY_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token itself is the credential.
	}

	/**
	 * Document forms only render for a valid, unexpired, unused invite —
	 * an unlinked URL is not access control.
	 *
	 * @param string $form_string Rendered form HTML.
	 * @param array  $form        Form meta.
	 */
	public function gate_form( $form_string, $form ) {
		$kind = is_array( $form ) ? $this->kind_for_form( (int) $form['id'] ) : '';
		if ( '' === $kind ) {
			return $form_string;
		}
		if ( 0 === $this->invites->validate( $this->current_token(), $kind ) ) {
			return '<div class="poolhall-form-note poolhall-form-final"><p><strong>This form needs a personal invitation link.</strong> '
				. 'Links are sent by the Poolhall team, are unique to you, and expire after 14 days. '
				. 'If yours has expired or already been used, call us on 0121 516 3000 or email jobs@poolhallrecruitment.co.uk and we&rsquo;ll send a fresh one.</p></div>';
		}
		return $form_string;
	}

	/**
	 * Prefills the safe basics (never bank/NI/health) from the invited
	 * candidate's pre-registration record, shown for correction.
	 *
	 * @param array $form Form meta.
	 * @return array
	 */
	public function prefill_form( $form ) {
		$kind = is_array( $form ) ? $this->kind_for_form( (int) $form['id'] ) : '';
		if ( '' === $kind ) {
			return $form;
		}
		$record = $this->invites->validate( $this->current_token(), $kind );
		if ( 0 === $record ) {
			return $form;
		}
		$data = $this->invites->prefill( $record );
		$map  = 'full' === $kind
			? array(
				2  => 'first_name',
				3  => 'last_name',
				11 => 'email',
				12 => 'phone',
				91 => 'full_name',
				92 => 'role_title',
			)
			: array(
				1 => 'full_name',
				2 => 'email',
				3 => 'role_title',
			);
		$full = trim( $data['first_name'] . ' ' . $data['last_name'] );
		foreach ( $form['fields'] as $field ) {
			$key = $map[ (int) $field->id ] ?? '';
			if ( '' === $key ) {
				continue;
			}
			$value = 'full_name' === $key ? $full : (string) ( $data[ $key ] ?? '' );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms' own property name.
			if ( '' !== $value && '' === (string) $field->defaultValue ) {
				$field->defaultValue = $value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms' own property name.
			}
		}
		return $form;
	}

	// -------------------------------------------------------- submission --

	/**
	 * @param array $entry Gravity Forms entry.
	 * @param array $form  Gravity Forms form meta.
	 */
	public function handle_submission( $entry, $form ): void {
		$map  = array_map( 'intval', (array) get_option( self::FORM_MAP_OPTION, array() ) );
		$kind = array_search( (int) $form['id'], $map, true );
		if ( false === $kind ) {
			return;
		}

		$record = $this->invites->validate( $this->current_token(), (string) $kind );
		if ( $record > 0 ) {
			$this->invites->mark_used( $record, (string) $kind );
			if ( 'full' === $kind ) {
				$this->update_giig_candidate( $record, $entry );
			}
			$this->giig_timeline_note( $record, $entry, (string) $kind );
		}

		try {
			$this->process( (string) $kind, $entry, $form );
		} catch ( \Throwable $t ) {
			// Entry is already stored by Gravity Forms — nothing is lost.
			$this->note( $entry, 'Document pipeline error: ' . $t->getMessage() );
			$this->logger->log( 'document_pipeline_error', array( 'entry' => (string) ( $entry['id'] ?? '' ) ) );
		}
	}


	/**
	 * Updates the pre-registration's existing Giig candidate (basics only)
	 * instead of creating a duplicate. Requires Giig's sensitive-endpoint
	 * access for /candidate/update; failures are noted, never fatal.
	 */
	private function update_giig_candidate( int $record, array $entry ): void {
		$candidate_id = (string) get_post_meta( $record, 'giig_candidate_id', true );
		if ( '' === $candidate_id ) {
			return;
		}
		try {
			$sink = \Poolhall\Integration\Plugin::instance()->candidate_sink_public();
			if ( ! $sink instanceof GiigCandidateSink ) {
				return;
			}
			$sink->update_candidate(
				$candidate_id,
				array(
					'FirstName'    => trim( (string) ( $entry['2'] ?? '' ) ),
					'LastName'     => trim( (string) ( $entry['3'] ?? '' ) ),
					'EmailAddress' => trim( (string) ( $entry['11'] ?? '' ) ),
					'PhoneNumber'  => trim( (string) ( $entry['12'] ?? '' ) ),
					'RoleTitle'    => trim( (string) ( $entry['92'] ?? '' ) ),
				)
			);
			$this->note( $entry, 'Giig candidate #' . $candidate_id . ' updated from the full registration (no duplicate created).' );
		} catch ( \Throwable $t ) {
			$this->note( $entry, 'Giig candidate update skipped: ' . $t->getMessage() . ' (candidate remains #' . $candidate_id . ').' );
		}
	}

	/**
	 * Logs the completed document on the candidate's Giig timeline so the
	 * recruiter sees onboarding progress inside the ATS. Only the fact of
	 * completion is sent — never form content (hard GDPR rule). Non-fatal.
	 */
	private function giig_timeline_note( int $record, array $entry, string $kind ): void {
		$candidate_id = (string) get_post_meta( $record, 'giig_candidate_id', true );
		if ( '' === $candidate_id ) {
			return;
		}
		$what = match ( $kind ) {
			'full'    => 'Full Candidate Registration',
			'starter' => 'Starter Statement & P45 form',
			'terms'   => 'Candidate Terms',
			default   => '',
		};
		if ( '' === $what ) {
			return;
		}
		try {
			$sink = \Poolhall\Integration\Plugin::instance()->candidate_sink_public();
			if ( ! $sink instanceof GiigCandidateSink ) {
				return;
			}
			$sink->add_note(
				$candidate_id,
				'Candidate completed and signed the ' . $what . ' on the website.'
				. ' The signed PDF is on its way to ' . self::ADMIN_EMAIL . ' (entry #' . (string) ( $entry['id'] ?? '' ) . ').'
			);
			$this->note( $entry, 'Logged on Giig candidate #' . $candidate_id . ' timeline.' );
		} catch ( \Throwable $t ) {
			$this->logger->log( 'giig_note_failed', array( 'entry' => (string) ( $entry['id'] ?? '' ) ) );
		}
	}

	private function process( string $kind, array $entry, array $form ): void {
		$entry_id = (int) $entry['id'];

		[ $title, $subject, $filename, $person, $email ] = $this->headline( $kind, $entry );

		$pdf = new PdfBuilder( $title );
		$this->render_answers( $pdf, $entry, $form );
		$this->render_signature( $pdf, $entry, $form );
		$pdf->audit(
			array(
				'Candidate name'       => $person,
				'Candidate email'      => $email,
				'Form'                 => (string) $form['title'],
				'Form ID'              => (string) $form['id'],
				'Entry ID'             => (string) $entry_id,
				'Declaration'          => 'Confirmed by candidate',
				'Signature timestamp'  => (string) ( $entry['date_created'] ?? '' ) . ' UTC',
				'Submission timestamp' => (string) ( $entry['date_created'] ?? '' ) . ' UTC',
				'IP address'           => (string) ( $entry['ip'] ?? '' ),
				'Browser / user agent' => (string) ( $entry['user_agent'] ?? '' ),
				'Page URL'             => (string) ( $entry['source_url'] ?? '' ),
			)
		);
		$path = $pdf->save( $filename );

		$attachments = array_merge( array( $path ), $this->upload_attachments( $entry, $form ) );

		$body = 'A signed candidate document has been submitted on the Poolhall website.' . "\n\n"
			. 'Form: ' . $form['title'] . "\n"
			. 'Candidate: ' . $person . "\n"
			. 'Entry ID: ' . $entry_id . "\n\n"
			. 'The completed, signed PDF is attached' . ( count( $attachments ) > 1 ? ' along with the candidate\'s uploaded file(s).' : '.' ) . "\n"
			. 'Entry record: ' . admin_url( 'admin.php?page=gf_entries&view=entry&id=' . (int) $form['id'] . '&lid=' . $entry_id );

		$sent = wp_mail( self::ADMIN_EMAIL, $subject, $body, array(), $attachments );

		if ( $sent ) {
			$this->note( $entry, 'Signed PDF emailed to ' . self::ADMIN_EMAIL . ' (' . basename( $path ) . ').' );
			wp_delete_file( $path );
		} else {
			$this->note( $entry, 'EMAIL FAILED - PDF retained at ' . basename( $path ) . ' in the protected documents folder. Check mail configuration.' );
			$this->logger->log( 'document_email_failed', array( 'entry' => (string) $entry_id ) );
		}

		if ( '' !== $email && is_email( $email ) ) {
			wp_mail(
				$email,
				'Poolhall Recruitment - form received',
				'Thank you. Your form has been submitted to Poolhall Recruitment. A member of the team will contact you if anything else is required.' . "\n\n"
				. 'Poolhall Recruitment Limited' . "\n" . '0121 516 3000 - jobs@poolhallrecruitment.co.uk'
			);
		}
	}

	/**
	 * Per-kind title, admin subject, PDF filename, person name and email.
	 *
	 * @return array{0:string,1:string,2:string,3:string,4:string}
	 */
	private function headline( string $kind, array $entry ): array {
		$entry_id = (int) $entry['id'];
		if ( 'full' === $kind ) {
			$forename = trim( (string) ( $entry['2'] ?? '' ) );
			$surname  = trim( (string) ( $entry['3'] ?? '' ) );
			$person   = trim( $forename . ' ' . $surname );
			return array(
				'Full Candidate Registration',
				'New Full Candidate Registration - ' . $person . ' - Entry ' . $entry_id,
				'Poolhall-Full-Registration-' . $surname . '-' . $forename . '-' . $entry_id . '.pdf',
				$person,
				trim( (string) ( $entry['11'] ?? '' ) ),
			);
		}
		if ( 'starter' === $kind ) {
			$person = trim( (string) ( $entry['1'] ?? '' ) );
			return array(
				'Starter Statement / P45 Declaration',
				'New Starter Statement / P45 Declaration - ' . $person . ' - Entry ' . $entry_id,
				'Poolhall-Starter-Statement-' . $person . '-' . $entry_id . '.pdf',
				$person,
				trim( (string) ( $entry['2'] ?? '' ) ),
			);
		}
		$person = trim( (string) ( $entry['1'] ?? '' ) );
		return array(
			'Candidate Terms of Service',
			'Candidate Terms Signed - ' . $person . ' - Entry ' . $entry_id,
			'Poolhall-Candidate-Terms-' . $person . '-' . $entry_id . '.pdf',
			$person,
			trim( (string) ( $entry['2'] ?? '' ) ),
		);
	}

	/** Walks the form fields in page order, writing sectioned label/value rows. */
	private function render_answers( PdfBuilder $pdf, array $entry, array $form ): void {
		$pages      = isset( $form['pagination']['pages'] ) && is_array( $form['pagination']['pages'] ) ? $form['pagination']['pages'] : array();
		$page_index = 0;
		$pdf->section( array() !== $pages ? (string) $pages[0] : 'Submitted details' );

		foreach ( $form['fields'] as $field ) {
			$type = (string) $field->type;
			if ( 'page' === $type ) {
				++$page_index;
				$pdf->section( (string) ( $pages[ $page_index ] ?? 'Section ' . ( $page_index + 1 ) ) );
				continue;
			}
			if ( in_array( $type, array( 'html', 'poolhall_signature', 'honeypot', 'captcha' ), true ) ) {
				continue;
			}

			$label = (string) $field->label;
			if ( 'checkbox' === $type ) {
				$values = array();
				foreach ( (array) $field->inputs as $input ) {
					$v = (string) ( $entry[ (string) $input['id'] ] ?? '' );
					if ( '' !== $v ) {
						$values[] = $v;
					}
				}
				$pdf->row( $label, implode( "\n", $values ) );
				continue;
			}
			if ( 'fileupload' === $type ) {
				$url = (string) ( $entry[ (string) $field->id ] ?? '' );
				$pdf->row( $label, '' === $url ? 'Not provided' : basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) . ' (attached to the notification email)' );
				continue;
			}
			$pdf->row( $label, (string) ( $entry[ (string) $field->id ] ?? '' ) );
		}
	}

	private function render_signature( PdfBuilder $pdf, array $entry, array $form ): void {
		foreach ( $form['fields'] as $field ) {
			if ( 'poolhall_signature' !== (string) $field->type ) {
				continue;
			}
			$decoded = SignatureField::decode( (string) ( $entry[ (string) $field->id ] ?? '' ) );
			if ( null === $decoded ) {
				return;
			}
			$signer = $this->signer_name( $entry, $form );
			$date   = $this->signed_date( $entry, $form );
			$pdf->note( FormDefinitions::DECLARATION );
			$pdf->signature( $decoded['method'], $decoded['data'], $signer, $date );
			return;
		}
	}

	private function signer_name( array $entry, array $form ): string {
		// The field labelled Full name / Candidate full name nearest the signature.
		foreach ( $form['fields'] as $field ) {
			if ( str_contains( strtolower( (string) $field->label ), 'full name' ) ) {
				$value = trim( (string) ( $entry[ (string) $field->id ] ?? '' ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}
		return trim( ( (string) ( $entry['2'] ?? '' ) ) . ' ' . ( (string) ( $entry['3'] ?? '' ) ) );
	}

	private function signed_date( array $entry, array $form ): string {
		foreach ( $form['fields'] as $field ) {
			if ( 'date' === (string) $field->type && str_contains( strtolower( (string) $field->label ), 'signed' ) ) {
				$value = trim( (string) ( $entry[ (string) $field->id ] ?? '' ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}
		return (string) ( $entry['date_created'] ?? '' );
	}

	/**
	 * Local paths of candidate uploads (CV / P45) that are safe to attach.
	 *
	 * @return string[]
	 */
	private function upload_attachments( array $entry, array $form ): array {
		$paths = array();
		foreach ( $form['fields'] as $field ) {
			if ( 'fileupload' !== (string) $field->type ) {
				continue;
			}
			$url = (string) ( $entry[ (string) $field->id ] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$uploads = wp_upload_dir();
			$path    = str_replace( $uploads['baseurl'], $uploads['basedir'], $url );
			if ( $path !== $url && file_exists( $path ) && filesize( $path ) <= 12 * 1024 * 1024 ) {
				$paths[] = $path;
			}
		}
		return $paths;
	}

	private function note( array $entry, string $text ): void {
		if ( class_exists( '\GFAPI' ) && method_exists( '\GFAPI', 'add_note' ) ) {
			\GFAPI::add_note( (int) $entry['id'], 0, 'Poolhall workflow', $text );
		}
	}

	// ------------------------------------------------------------ assets --

	/**
	 * @param array $form Gravity Forms form meta.
	 */
	public function enqueue_assets( $form ): void {
		if ( ! is_array( $form ) || ! str_contains( (string) ( $form['cssClass'] ?? '' ), 'poolhall-document-form' ) ) {
			return;
		}
		wp_enqueue_script(
			'poolhall-signature-pad',
			POOLHALL_INTEGRATION_URL . 'assets/js/vendor/signature_pad.umd.min.js',
			array(),
			'5.0.4',
			true
		);
		wp_enqueue_script(
			'poolhall-documents',
			POOLHALL_INTEGRATION_URL . 'assets/js/documents.js',
			array( 'poolhall-signature-pad' ),
			POOLHALL_INTEGRATION_VERSION,
			true
		);
		wp_enqueue_style(
			'poolhall-documents',
			POOLHALL_INTEGRATION_URL . 'assets/css/documents.css',
			array(),
			POOLHALL_INTEGRATION_VERSION
		);
	}

	/**
	 * @param array<string,bool> $robots Robots directives.
	 * @return array<string,bool>
	 */
	public function noindex_hidden_pages( $robots ) {
		$hidden = array_map( 'intval', (array) get_option( self::HIDDEN_PAGES_OPTION, array() ) );
		if ( array() !== $hidden && is_page( $hidden ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}
}
