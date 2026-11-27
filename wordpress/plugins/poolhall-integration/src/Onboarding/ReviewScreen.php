<?php
/**
 * Recruiter cockpit for candidate review and staged onboarding.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Applications\ApplicationRecord;
use Poolhall\Integration\Applications\CandidateIdentity;
use Poolhall\Integration\Documents\ContractPdfBuilder;
use Poolhall\Integration\Documents\DocumentWorkflow;
use Poolhall\Integration\Documents\FormDefinitions;
use Poolhall\Integration\Documents\PdfBuilder;
use Poolhall\Integration\Support\BrandedMail;
use Poolhall\Integration\Support\PrivateStorage;

/** One queue and one next action for every website candidate entry route. */
final class ReviewScreen {

	private const CAP                    = 'manage_options';
	private const PAGE                   = 'poolhall-candidates';
	private const PER_PAGE               = 25;
	private const CHASE_DAYS             = 7;
	private const ACTIVE_WORKFLOW_STAGES = array( 'awaiting_profile', 'registration_review', 'registered', 'onboarding', 'onboarding_review' );

	public function __construct(
		private readonly InviteManager $invites,
		private readonly WorkflowState $state,
		private readonly InviteMailer $mailer,
	) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_poolhall_review_action', array( $this, 'handle_action' ) );
		add_action( 'admin_post_poolhall_add_candidate', array( $this, 'handle_add' ) );
		add_action( 'admin_post_poolhall_bulk_candidates', array( $this, 'handle_bulk' ) );
		add_action( 'admin_post_poolhall_export_candidates', array( $this, 'handle_export' ) );
		add_action( 'admin_post_poolhall_cv_download', array( $this, 'download_cv' ) );
		add_action( 'admin_post_nopriv_poolhall_cv_download', 'auth_redirect' );
		add_action( 'admin_post_poolhall_document_download', array( $this, 'download_document' ) );
		add_action( 'admin_post_poolhall_task_upload_download', array( $this, 'download_task_upload' ) );
	}

	public function menu(): void {
		add_menu_page( 'Candidates', 'Candidates', self::CAP, self::PAGE, array( $this, 'render' ), 'dashicons-groups', 26 );
	}

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$record = isset( $_GET['record'] ) ? (int) $_GET['record'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only detail selection.
		if ( $this->is_candidate_record( $record ) ) {
			$this->render_detail( $record );
			return;
		}
		$this->render_list();
	}

	private function render_list(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filtering.
		$paged  = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$stage_filter  = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';
		$source_filter = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$flag   = isset( $_GET['flag'] ) ? sanitize_key( wp_unslash( $_GET['flag'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$everything = $this->filtered_records( '', '', '', '' );
		$matching   = $this->filtered_records( $search, $stage_filter, $source_filter, $flag );
		$total      = count( $matching );
		$pages      = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged      = min( $paged, $pages );
		$page_ids   = array_slice( $matching, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );

		echo '<div class="wrap"><h1>Candidate workflow</h1>';
		echo '<p>Pre-registrations and job applicants share one candidate identity and workflow queue. Open a record to take the single next action, review submitted forms or start assignment onboarding.</p>';
		$this->render_notice();
		$this->render_stat_tiles( $everything, $flag );
		$this->render_add_form();
		$this->render_filters( $search, $stage_filter, $source_filter, $flag );

		$export = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'poolhall_export_candidates',
					's'      => $search,
					'stage'  => $stage_filter,
					'source' => $source_filter,
					'flag'   => $flag,
				),
				admin_url( 'admin-post.php' )
			),
			'poolhall_export_candidates'
		);
		echo '<p>' . esc_html( sprintf( '%d candidate%s', $total, 1 === $total ? '' : 's' ) )
			. ' &middot; <a href="' . esc_url( $export ) . '">Export this view to CSV</a></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="poolhall_bulk_candidates">';
		wp_nonce_field( 'poolhall_bulk_candidates', '_wpnonce' );

		echo '<table class="widefat striped"><thead><tr>';
		echo '<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll(\'input[name=&quot;records[]&quot;]\').forEach(b=>b.checked=this.checked)" aria-label="Select all"></td>';
		echo '<th>Candidate</th><th>Source</th><th>Stage</th><th>At stage</th><th>Giig</th><th>Checklist</th><th>Next step</th></tr></thead><tbody>';
		if ( array() === $page_ids ) {
			echo '<tr><td colspan="8">No candidates match this view.</td></tr>';
		}
		foreach ( $page_ids as $id ) {
			$id      = (int) $id;
			$kind    = (string) get_post_meta( $id, 'kind', true );
			$email   = (string) get_post_meta( $id, 'applicant_email', true );
			$giig    = (string) get_post_meta( $id, 'giig_candidate_id', true );
			$stage   = $this->state->stage( $id );
			$days    = $this->days_in_stage( $id );
			$overdue = $this->is_overdue( $id, $stage );
			$detail  = add_query_arg(
				array(
					'page'   => self::PAGE,
					'record' => $id,
				),
				admin_url( 'admin.php' )
			);

			echo '<tr><th scope="row" class="check-column"><input type="checkbox" name="records[]" value="' . esc_attr( (string) $id ) . '" aria-label="Select candidate"></th>';
			echo '<td><strong>' . esc_html( get_the_title( $id ) ) . '</strong>';
			if ( '' !== (string) get_post_meta( $id, 'added_manually', true ) ) {
				echo ' <span class="dashicons dashicons-admin-users" title="Added by hand" style="font-size:15px;width:15px;height:15px;color:#646970"></span>';
			}
			echo '<br><span style="color:#646970">' . esc_html( $email ) . '</span></td>';
			echo '<td>' . esc_html( 'job_application' === $kind ? 'Job application' : 'Pre-registration' );
			if ( 'job_application' === $kind ) {
				echo '<br><small>' . esc_html( (string) get_post_meta( $id, 'job_title', true ) ) . '</small>';
			}
			echo '</td>';
			echo '<td><code>' . esc_html( $this->label( $stage ) ) . '</code></td>';
			echo '<td' . ( $overdue ? ' style="color:#b32d2e;font-weight:600"' : '' ) . '>'
				. esc_html( 0 === $days ? 'today' : sprintf( '%d day%s', $days, 1 === $days ? '' : 's' ) )
				. ( $overdue ? '<br><small>chase</small>' : '' ) . '</td>';
			echo '<td>' . ( '' !== $giig ? '<a href="https://app.giighire.com/candidates/' . esc_attr( $giig ) . '" target="_blank" rel="noopener">#' . esc_html( $giig ) . '</a>' : '&mdash;' ) . '</td>';
			echo '<td>' . wp_kses_post( $this->compact_checklist( $this->state->checklist( $id ) ) ) . '</td>';
			echo '<td><a class="button button-primary" href="' . esc_url( $detail ) . '">' . esc_html( $this->next_action_label( $this->state->next_action( $id ) ) ) . '</a></td></tr>';
		}
		echo '</tbody></table>';

		echo '<div style="margin:12px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		echo '<label class="screen-reader-text" for="ph-bulk">Bulk action</label>';
		echo '<select id="ph-bulk" name="bulk_do"><option value="">Bulk actions</option>';
		echo '<option value="close">Close selected workflows</option>';
		echo '<option value="delete">Delete selected permanently</option></select>';
		echo '<input name="bulk_confirm" type="text" placeholder="Type DELETE to delete" autocomplete="off" style="width:180px">';
		echo '<button type="submit" class="button">Apply</button>';
		echo '<span class="description">Closing keeps the paperwork. Deleting erases entries, PDFs, uploads and the CV.</span>';
		echo '</div></form>';

		$links = paginate_links(
			array(
				'base'    => add_query_arg(
					array(
						'page'   => self::PAGE,
						's'      => $search,
						'stage'  => $stage_filter,
						'source' => $source_filter,
						'flag'   => $flag,
						'paged'  => '%#%',
					),
					admin_url( 'admin.php' )
				),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
				'type'    => 'list',
			)
		);
		if ( is_string( $links ) ) {
			echo '<nav aria-label="Candidate pages">' . wp_kses_post( $links ) . '</nav>';
		}
		echo '</div>';
	}

	/**
	 * Bucket a stage by who it is waiting on. Drives the summary tiles: the
	 * question a recruiter opens this screen with is "what needs me today",
	 * not "how many candidates exist".
	 */
	private function stage_bucket( string $stage ): string {
		return match ( $stage ) {
			'new', 'registration_review', 'registered', 'onboarding_review' => 'us',
			'awaiting_profile', 'onboarding' => 'them',
			'ready'  => 'done',
			'closed' => 'closed',
			default  => 'us',
		};
	}

	/** Whole days the record has sat at its current stage. */
	private function days_in_stage( int $record ): int {
		$stamp = (string) get_post_meta( $record, 'workflow_stage_updated_at', true );
		$since = '' !== $stamp ? strtotime( $stamp ) : (int) get_post_time( 'U', true, $record );
		if ( ! is_int( $since ) || $since <= 0 ) {
			return 0;
		}
		return (int) floor( ( time() - $since ) / DAY_IN_SECONDS );
	}

	/** Waiting on the candidate and gone quiet for over a week. */
	private function is_overdue( int $record, string $stage ): bool {
		return 'them' === $this->stage_bucket( $stage ) && $this->days_in_stage( $record ) >= self::CHASE_DAYS;
	}

	/**
	 * Every candidate record, already filtered. The queue is a recruitment
	 * pipeline rather than an unbounded table, so filtering in PHP keeps stage
	 * handling identical to the rest of the screen — stage() falls back to
	 * legacy meta, which a meta_query alone would miss.
	 *
	 * @return int[]
	 */
	private function filtered_records( string $search, string $stage_filter, string $source_filter, string $flag ): array {
		$ids = get_posts(
			array(
				'post_type'      => ApplicationRecord::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- private, bounded operations queue.
					array(
						'key'     => 'kind',
						'value'   => array( 'registration', 'job_application' ),
						'compare' => 'IN',
					),
				),
			)
		);

		$needle = strtolower( trim( $search ) );
		$out    = array();
		foreach ( (array) $ids as $id ) {
			$id    = (int) $id;
			$stage = $this->state->stage( $id );
			$kind  = (string) get_post_meta( $id, 'kind', true );

			if ( '' !== $stage_filter && $stage !== $stage_filter ) {
				continue;
			}
			if ( '' !== $source_filter && $kind !== $source_filter ) {
				continue;
			}
			if ( 'overdue' === $flag && ! $this->is_overdue( $id, $stage ) ) {
				continue;
			}
			if ( '' !== $flag && 'overdue' !== $flag && $this->stage_bucket( $stage ) !== $flag ) {
				continue;
			}
			if ( '' !== $needle ) {
				$haystack = strtolower(
					get_the_title( $id ) . ' '
					. (string) get_post_meta( $id, 'applicant_email', true ) . ' '
					. (string) get_post_meta( $id, 'applicant_phone', true ) . ' '
					. (string) get_post_meta( $id, 'giig_candidate_id', true )
				);
				if ( ! str_contains( $haystack, $needle ) ) {
					continue;
				}
			}
			$out[] = $id;
		}
		return $out;
	}

	/** @param int[] $all */
	private function render_stat_tiles( array $all, string $flag ): void {
		$counts = array(
			'us'      => 0,
			'them'    => 0,
			'done'    => 0,
			'closed'  => 0,
			'overdue' => 0,
		);
		foreach ( $all as $id ) {
			$stage = $this->state->stage( $id );
			++$counts[ $this->stage_bucket( $stage ) ];
			if ( $this->is_overdue( $id, $stage ) ) {
				++$counts['overdue'];
			}
		}
		$tiles = array(
			'us'      => array( 'Waiting on you', '#b32d2e' ),
			'them'    => array( 'With the candidate', '#996800' ),
			'overdue' => array( 'Gone quiet (' . self::CHASE_DAYS . '+ days)', '#8a2be2' ),
			'done'    => array( 'Ready to work', '#008a20' ),
			'closed'  => array( 'Closed', '#646970' ),
		);
		echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">';
		foreach ( $tiles as $key => $tile ) {
			$url    = add_query_arg(
				array(
					'page' => self::PAGE,
					'flag' => $flag === $key ? '' : $key,
				),
				admin_url( 'admin.php' )
			);
			$active = $flag === $key;
			echo '<a href="' . esc_url( $url ) . '" style="flex:1 1 150px;min-width:150px;text-decoration:none;'
				. 'background:#fff;border:1px solid ' . ( $active ? esc_attr( $tile[1] ) : '#c3c4c7' ) . ';'
				. ( $active ? 'box-shadow:0 0 0 1px ' . esc_attr( $tile[1] ) . ';' : '' )
				. 'padding:12px 14px;display:block">';
			echo '<span style="display:block;font-size:26px;font-weight:600;line-height:1.1;color:' . esc_attr( $tile[1] ) . '">'
				. esc_html( (string) $counts[ $key ] ) . '</span>';
			echo '<span style="display:block;color:#3c434a;font-size:12px;margin-top:2px">' . esc_html( $tile[0] ) . '</span></a>';
		}
		echo '</div>';
		if ( '' !== $flag ) {
			$clear = add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) );
			echo '<p><a href="' . esc_url( $clear ) . '">Clear filter</a></p>';
		}
	}

	private function render_filters( string $search, string $stage_filter, string $source_filter, string $flag ): void {
		$stages = array(
			'new'                 => 'New',
			'awaiting_profile'    => 'Awaiting profile',
			'registration_review' => 'Profile to review',
			'registered'          => 'Registered',
			'onboarding'          => 'Onboarding',
			'onboarding_review'   => 'Onboarding to review',
			'ready'               => 'Ready',
			'closed'              => 'Closed',
		);
		echo '<form method="get" style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '">';
		if ( '' !== $flag ) {
			echo '<input type="hidden" name="flag" value="' . esc_attr( $flag ) . '">';
		}
		echo '<label class="screen-reader-text" for="ph-search">Search candidates</label>';
		echo '<input id="ph-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="Name, email, phone or Giig ID" style="min-width:260px">';
		echo '<label class="screen-reader-text" for="ph-stage">Stage</label><select id="ph-stage" name="stage"><option value="">All stages</option>';
		foreach ( $stages as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $stage_filter, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<label class="screen-reader-text" for="ph-source">Source</label><select id="ph-source" name="source"><option value="">All sources</option>';
		echo '<option value="registration"' . selected( $source_filter, 'registration', false ) . '>Pre-registration</option>';
		echo '<option value="job_application"' . selected( $source_filter, 'job_application', false ) . '>Job application</option>';
		echo '</select>';
		echo '<button type="submit" class="button">Filter</button>';
		if ( '' !== $search || '' !== $stage_filter || '' !== $source_filter ) {
			echo ' <a class="button-link" href="' . esc_url( add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) ) . '">Reset</a>';
		}
		echo '</form>';
	}

	/** Bulk close or delete from the queue. */
	public function handle_bulk(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( $nonce, 'poolhall_bulk_candidates' ) ) {
			wp_die( 'Not allowed.' );
		}
		$do      = isset( $_POST['bulk_do'] ) ? sanitize_key( wp_unslash( $_POST['bulk_do'] ) ) : '';
		$confirm = isset( $_POST['bulk_confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_confirm'] ) ) : '';
		$raw     = isset( $_POST['records'] ) && is_array( $_POST['records'] ) ? wp_unslash( $_POST['records'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast below.
		$ids     = array_values( array_filter( array_map( 'intval', $raw ), fn( int $id ): bool => $this->is_candidate_record( $id ) ) );

		$result = 'bulk_none';
		$count  = 0;
		if ( array() !== $ids ) {
			if ( 'close' === $do ) {
				foreach ( $ids as $id ) {
					$this->invites->revoke_all( $id );
					update_post_meta( $id, 'workflow_stage', 'closed' );
					update_post_meta( $id, 'workflow_stage_updated_at', gmdate( \DateTimeInterface::ATOM ) );
					update_post_meta( $id, 'review_status', 'rejected' );
					++$count;
				}
				$result = 'bulk_closed';
			} elseif ( 'delete' === $do ) {
				if ( 'DELETE' !== strtoupper( trim( $confirm ) ) ) {
					$result = 'delete_unconfirmed';
				} else {
					foreach ( $ids as $id ) {
						$this->invites->revoke_all( $id );
						$this->erase_record_files( $id );
						if ( wp_delete_post( $id, true ) ) {
							++$count;
						}
					}
					$result = 'bulk_deleted';
				}
			}
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => self::PAGE,
					'result' => $result,
					'n'      => $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * CSV of the current queue. Deliberately limited to pipeline fields — no
	 * bank details, NI numbers, health answers or signature data ever leave
	 * the protected store through here.
	 */
	public function handle_export(): void {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( $nonce, 'poolhall_export_candidates' ) ) {
			wp_die( 'Not allowed.' );
		}
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$stage  = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$flag   = isset( $_GET['flag'] ) ? sanitize_key( wp_unslash( $_GET['flag'] ) ) : '';
		$ids    = $this->filtered_records( $search, $stage, $source, $flag );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=poolhall-candidates-' . gmdate( 'Y-m-d' ) . '.csv' );
		$fh = fopen( 'php://output', 'w' );
		if ( false === $fh ) {
			exit;
		}
		fputcsv( $fh, array( 'Record', 'Name', 'Email', 'Phone', 'Source', 'Role', 'Stage', 'Days at stage', 'Next step', 'Giig ID', 'Added' ) );
		foreach ( $ids as $id ) {
			$stage_now = $this->state->stage( $id );
			fputcsv(
				$fh,
				array(
					$id,
					html_entity_decode( get_the_title( $id ) ),
					(string) get_post_meta( $id, 'applicant_email', true ),
					(string) get_post_meta( $id, 'applicant_phone', true ),
					'job_application' === (string) get_post_meta( $id, 'kind', true ) ? 'Job application' : 'Pre-registration',
					(string) get_post_meta( $id, 'job_title', true ),
					$this->label( $stage_now ),
					$this->days_in_stage( $id ),
					$this->next_action_label( $this->state->next_action( $id ) ),
					(string) get_post_meta( $id, 'giig_candidate_id', true ),
					get_the_date( 'Y-m-d H:i', $id ),
				)
			);
		}
		fclose( $fh );
		exit;
	}

	private function render_detail( int $record ): void {
		$back        = add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) );
		$stage       = $this->state->stage( $record );
		$kind        = (string) get_post_meta( $record, 'kind', true );
		$giig        = (string) get_post_meta( $record, 'giig_candidate_id', true );
		$cv_path     = (string) get_post_meta( $record, 'cv_path', true );
		$form_map    = array_map( 'intval', (array) get_option( DocumentWorkflow::FORM_MAP_OPTION, array() ) );
		$terms_ok    = TaskPolicy::archived( 'terms' ) || FormDefinitions::candidate_terms_ready();
		$contract_ok = ContractPdfBuilder::source_ready();
		$checklist   = $this->state->checklist( $record );

		echo '<div class="wrap"><p><a href="' . esc_url( $back ) . '">&larr; All candidates</a></p><h1>' . esc_html( get_the_title( $record ) ) . '</h1>';
		$this->render_notice();
		if ( ! $terms_ok ) {
			$settings = admin_url( 'admin.php?page=poolhall-candidate-terms' );
			echo '<div class="notice notice-warning inline"><p><strong>Candidate Terms are still draft.</strong> The full onboarding pack and individual Terms invitation are blocked until approved, versioned legal wording is saved. <a href="' . esc_url( $settings ) . '">Open Candidate terms</a>.</p></div>';
		}
		if ( ! $contract_ok ) {
			echo '<div class="notice notice-warning inline"><p><strong>The Agency Worker Contract is unavailable.</strong> Restore the approved, hash-verified source PDF before issuing a temporary-worker or contractor post-offer pack.</p></div>';
		}
		if ( CandidateIdentity::review_required( $record ) ) {
			echo '<div class="notice notice-warning inline"><p><strong>Identity review required.</strong> No candidate or Giig records were silently merged. Reasons: '
				. esc_html( implode( ', ', CandidateIdentity::review_reasons( $record ) ) ) . '.</p></div>';
		}

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin:18px 0">';
		$this->summary_card(
			'Candidate',
			array(
				'Email' => (string) get_post_meta( $record, 'applicant_email', true ),
				'Phone' => (string) get_post_meta( $record, 'applicant_phone', true ),
				'Role'  => (string) ( get_post_meta( $record, 'role_title', true ) ?: get_post_meta( $record, 'job_title', true ) ),
			)
		);
		$this->summary_card(
			'Source',
			array(
				'Type'              => 'job_application' === $kind ? 'Job application' : 'Pre-registration',
				'Job'               => (string) get_post_meta( $record, 'job_title', true ),
				'Reference'         => (string) get_post_meta( $record, 'job_reference', true ),
				'Poolhall email'    => $this->label( (string) get_post_meta( $record, 'admin_mail_status', true ) ),
				'Candidate receipt' => $this->label( (string) get_post_meta( $record, 'receipt_mail_status', true ) ),
			)
		);
		$this->summary_card(
			'Workflow',
			array(
				'Stage'       => $this->label( $stage ),
				'Giig'        => '' !== $giig ? '#' . $giig : 'Not linked',
				'Last update' => (string) ( get_post_meta( $record, 'workflow_stage_updated_at', true ) ?: get_post_meta( $record, 'workflow_stage_started_at', true ) ?: get_post_meta( $record, 'submitted_at', true ) ),
			)
		);
		echo '</div>';

		CandidateGiigPanel::render( $record );
		echo '<p>';
		if ( '' !== $giig ) {
			echo '<a class="button" href="https://app.giighire.com/candidates/' . esc_attr( $giig ) . '" target="_blank" rel="noopener">Open in Giig</a> ';
		}
		if ( '' !== $cv_path && is_file( $cv_path ) ) {
			$cv_url = wp_nonce_url( admin_url( 'admin-post.php?action=poolhall_cv_download&record=' . $record ), 'poolhall_cv_' . $record );
			echo '<a class="button" href="' . esc_url( $cv_url ) . '">Download original CV</a>';
		}
		echo '</p>';
		if ( 'new' === $stage ) {
			$matches = $this->matching_email_records( $record );
			if ( array() !== $matches ) {
				echo '<div class="notice notice-warning inline"><p><strong>Possible existing candidate.</strong> The same email appears on another website record. Review both records, then explicitly use the existing workflow if they are the same person. Nothing is merged automatically.</p>';
				foreach ( $matches as $match ) {
					$link = add_query_arg(
						array(
							'page'   => self::PAGE,
							'record' => $match,
						),
						admin_url( 'admin.php' )
					);
					echo '<p><a href="' . esc_url( $link ) . '">Review record #' . esc_html( (string) $match ) . ': ' . esc_html( get_the_title( $match ) ) . '</a> ';
					echo $this->action_form( $record, 'merge_identity', 'Use this existing workflow', array( 'target' => (string) $match ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped admin form.
					echo '</p>';
				}
				echo '</div>';
			}
		}

		echo '<h2>Checklist</h2><table class="widefat striped"><thead><tr><th>Task</th><th>Status</th><th>Email hand-off</th><th>Entry / document</th><th>Review action</th></tr></thead><tbody>';
		foreach ( WorkflowState::TASKS as $task ) {
			$status   = (string) $checklist[ $task ];
			$entry_id = (int) get_post_meta( $record, $this->state->meta( $task, 'entry_id' ), true );
			$pdf_path = (string) get_post_meta( $record, $this->state->meta( $task, 'pdf_path' ), true );
			$archived = TaskPolicy::archived( $task );
			if ( $archived && $entry_id <= 0 && '' === $pdf_path ) {
				continue;
			}
			$uploads  = json_decode( (string) get_post_meta( $record, $this->state->meta( $task, 'protected_uploads' ), true ), true );
			echo '<tr><td><strong>' . esc_html( $this->task_label( $task ) . ( $archived ? ' (archived)' : '' ) ) . '</strong></td><td><code>' . esc_html( $this->label( $status ) ) . '</code></td>';
			echo '<td><small>Task link: ' . esc_html( $this->label( $this->state->mail_status( $record, $task ) ) ) . '<br>';
			echo 'Poolhall alert: ' . esc_html( $this->label( (string) get_post_meta( $record, $this->state->meta( $task, 'document_mail_status' ), true ) ) ) . '<br>';
			echo 'Candidate receipt: ' . esc_html( $this->label( (string) get_post_meta( $record, $this->state->meta( $task, 'receipt_mail_status' ), true ) ) ) . '</small></td><td>';
			if ( $entry_id > 0 && isset( $form_map[ $task ] ) ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $form_map[ $task ] . '&lid=' . $entry_id ) ) . '">Entry #' . esc_html( (string) $entry_id ) . '</a> ';
			}
			if ( '' !== $pdf_path && is_file( $pdf_path ) ) {
				$doc_url = wp_nonce_url( admin_url( 'admin-post.php?action=poolhall_document_download&record=' . $record . '&task=' . $task ), 'poolhall_document_' . $record . '_' . $task );
				echo '<a href="' . esc_url( $doc_url ) . '">Submission PDF</a>';
			}
			if ( is_array( $uploads ) ) {
				foreach ( $uploads as $index => $upload ) {
					if ( ! is_array( $upload ) || empty( $upload['path'] ) || ! is_file( (string) $upload['path'] ) ) {
						continue;
					}
					$upload_url = wp_nonce_url(
						admin_url( 'admin-post.php?action=poolhall_task_upload_download&record=' . $record . '&task=' . $task . '&file=' . (int) $index ),
						'poolhall_task_upload_' . $record . '_' . $task . '_' . (int) $index
					);
					echo ' <a href="' . esc_url( $upload_url ) . '">' . esc_html( (string) ( $upload['label'] ?? 'Uploaded file' ) ) . '</a>';
				}
			}
			$summary = (string) get_post_meta( $record, $this->state->meta( $task, 'summary' ), true );
			if ( '' !== $summary ) {
				echo '<br><small>' . esc_html( $summary ) . '</small>';
			}
			echo '</td><td>';
			$can_review = ! $archived && ( ( 'full' === $task && 'registration_review' === $stage )
				|| ( 'full' !== $task && 'closed' !== $stage ) );
			if ( $can_review && 'submitted' === $status && ! $this->evidence_ready( $record, $task ) ) {
				echo '<strong>Protected evidence is unavailable.</strong> Do not accept this task. ';
				if ( 'full' === $task ) {
					echo $this->action_form( $record, 'correct_full', 'Request a fresh submission' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped admin form.
				} else {
					echo $this->action_form( $record, 'correct_task', 'Request a fresh submission', array( 'task' => $task ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped admin form.
				}
			} elseif ( $can_review && 'submitted' === $status && 'full' === $task ) {
				echo $this->action_form( $record, 'accept_full', 'Accept', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->action_form( $record, 'correct_full', 'Request correction' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $can_review && 'submitted' === $status ) {
				echo $this->action_form( $record, 'accept_task', 'Accept', array( 'task' => $task ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped admin form.
				echo $this->action_form( $record, 'correct_task', 'Request correction', array( 'task' => $task ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '&mdash;';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Next action</h2><div style="background:#fff;border:1px solid #dcdcde;padding:18px;max-width:850px">';
		$this->render_next_action( $record, $stage, $terms_ok, $contract_ok );
		if ( 'closed' !== $stage && 0 === $this->duplicate_active_workflow( $record ) ) {
			$this->render_individual_documents( $record );
		}
		echo '</div></div>';
	}

	private function render_next_action( int $record, string $stage, bool $terms_ok, bool $contract_ok ): void {
		$active_record = $this->duplicate_active_workflow( $record );
		if ( $active_record > 0 ) {
			$link = add_query_arg(
				array(
					'page'   => self::PAGE,
					'record' => $active_record,
				),
				admin_url( 'admin.php' )
			);
			echo '<p><strong>This submission belongs to a candidate who already has an active website workflow.</strong> Keep this record as application history and continue the existing workflow so the candidate does not receive duplicate forms or reminders.</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( $link ) . '">Open active workflow #' . esc_html( (string) $active_record ) . '</a></p>';
			if ( 'closed' !== $stage ) {
				echo '<hr><details><summary>Close this duplicate workflow</summary><p>This revokes access for this record only; it does not close the active workflow linked above.</p>';
				echo $this->action_form( $record, 'reject', 'Close duplicate workflow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</details>';
			}
			return;
		}

		$full = $this->state->task_status( $record, 'full' );
		if ( 'new' === $stage ) {
			if ( $this->workflow_ready( 'profile' ) ) {
				echo '<p>Review the CV and Giig record. If suitable, send the candidate profile invitation.</p>';
				echo $this->action_form( $record, 'approve_profile', 'Approve & send profile', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p><strong>Candidate workflow setup is incomplete.</strong> Run Site setup and resolve the readiness checks before sending an invitation.</p>';
			}
		} elseif ( 'submitted' === $full ) {
			echo '<p>Review the submitted profile before starting onboarding.</p>';
			echo $this->action_form( $record, 'accept_full', 'Accept candidate profile', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->action_form( $record, 'correct_full', 'Request profile correction' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( in_array( $full, array( 'awaiting', 'in_progress', 'needs_correction' ), true ) ) {
			if ( $this->workflow_ready( 'profile' ) ) {
				echo '<p>The candidate has an outstanding profile task. Resending rotates an expired or already-used access link safely.</p>';
				echo $this->action_form( $record, 'retry_profile', 'Resend secure profile link', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p><strong>Resend blocked:</strong> restore the candidate task page, profile form and private storage first.</p>';
			}
		} elseif ( 'accepted' === $full && 'registered' === $stage ) {
			$onboarding_ready = $terms_ok && $contract_ok && $this->workflow_ready( 'onboarding', TaskPolicy::documents() );
			if ( ! $onboarding_ready ) {
				echo '<p><strong>Onboarding is not ready to issue.</strong> Complete the required form and workflow readiness checks first.</p>';
			}
			$this->render_assignment_form( $record, $onboarding_ready );
		} elseif ( 'onboarding' === $stage ) {
			if ( '' === (string) get_post_meta( $record, 'invite_onboarding_mail_sent_at', true ) ) {
				echo '<p>Individual documents are in progress. Use "Send one document" below to resend a specific link.</p>';
			} elseif ( $this->workflow_ready( 'onboarding', $this->required_onboarding_tasks( $record ) ) ) {
				echo '<p>Onboarding tasks are still outstanding.</p>';
				echo $this->action_form( $record, 'retry_onboarding', 'Resend secure onboarding link', array(), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p><strong>Resend blocked:</strong> restore the candidate task page, required forms and private storage first.</p>';
			}
			$this->render_remaining_pack( $record, $terms_ok );
		} elseif ( 'onboarding_review' === $stage ) {
			echo '<p>All required onboarding forms have been submitted. Review and accept each item in the checklist above.</p>';
			$this->render_remaining_pack( $record, $terms_ok );
		} elseif ( 'ready' === $stage ) {
			echo '<p><strong>All requested documents accepted.</strong> Send another document or the remaining pack if needed.</p>';
			$this->render_remaining_pack( $record, $terms_ok );
			if ( '' !== (string) get_post_meta( $record, 'invite_onboarding_mail_sent_at', true ) ) {
				$completion = (string) get_post_meta( $record, 'completion_mail_status', true );
				echo '<p>Completion email to the candidate: <code>' . esc_html( $this->label( '' !== $completion ? $completion : 'not_sent' ) ) . '</code></p>';
				echo $this->action_form( $record, 'resend_completion', 'accepted' === $completion ? 'Resend completion email' : 'Send completion email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} elseif ( 'closed' === $stage ) {
			echo '<p>This candidate workflow is closed.</p>';
		} else {
			echo '<p>The candidate is waiting for Poolhall review.</p>';
		}
		if ( 'closed' !== $stage ) {
			echo '<hr><details><summary>Close this workflow</summary><p>Closing revokes every outstanding access link and browser session.</p>';
			echo $this->action_form( $record, 'reject', 'Close / reject workflow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</details>';
		}
		$this->render_delete_block( $record );
	}

	private function render_assignment_form( int $record, bool $enabled, string $action = 'start_onboarding' ): void {
		$role       = (string) ( get_post_meta( $record, 'onboarding_role', true ) ?: get_post_meta( $record, 'role_title', true ) );
		$start      = (string) get_post_meta( $record, 'onboarding_start_date', true );
		$rate       = (string) get_post_meta( $record, 'onboarding_rate', true );
		$engagement = (string) ( get_post_meta( $record, 'onboarding_engagement', true ) ?: 'temporary' );
		$reference  = (string) ( get_post_meta( $record, 'onboarding_job_reference', true ) ?: get_post_meta( $record, 'job_reference', true ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="poolhall_review_action"><input type="hidden" name="do" value="' . esc_attr( $action ) . '"><input type="hidden" name="record" value="' . esc_attr( (string) $record ) . '">';
		echo wp_nonce_field( 'poolhall_review_' . $record, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<table class="form-table" role="presentation"><tr><th><label for="onboarding-role">Confirmed role</label></th><td><input class="regular-text" id="onboarding-role" name="onboarding_role" required value="' . esc_attr( $role ) . '"></td></tr>';
		echo '<tr><th><label for="onboarding-start">Start date</label></th><td><input type="date" id="onboarding-start" name="onboarding_start_date" required value="' . esc_attr( $start ) . '"></td></tr>';
		echo '<tr><th><label for="onboarding-rate">Rate / salary</label></th><td><input class="regular-text" id="onboarding-rate" name="onboarding_rate" value="' . esc_attr( $rate ) . '"></td></tr>';
		echo '<tr><th><label for="onboarding-engagement">Engagement</label></th><td><select id="onboarding-engagement" name="onboarding_engagement">';
		foreach ( array(
			'temporary'  => 'Temporary worker',
			'contractor' => 'Contractor',
			'permanent'  => 'Permanent placement',
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $engagement, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr><tr><th><label for="onboarding-ref">Job reference</label></th><td><input class="regular-text" id="onboarding-ref" name="onboarding_job_reference" value="' . esc_attr( $reference ) . '"></td></tr>';
		echo '<tr><th>Post-offer tasks</th><td><strong>Agency Worker Contract:</strong> issued automatically for temporary workers and contractors; not issued for permanent placements.<br>';
		if ( ! TaskPolicy::archived( 'terms' ) ) {
			echo '<label><input type="checkbox" name="tasks[]" value="terms" checked disabled> Candidate Terms (always required)</label><input type="hidden" name="tasks[]" value="terms"><br>';
		}
		echo '<label><input type="checkbox" name="tasks[]" value="starter" checked> HMRC starter checklist (with P45 upload if they have one)</label><br><label><input type="checkbox" name="tasks[]" value="payroll" checked> Payroll details</label></td></tr></table>';
		echo '<p><button class="button button-primary" type="submit"' . ( $enabled ? '' : ' disabled' ) . '>' . esc_html( 'extend_onboarding' === $action ? 'Send remaining onboarding pack' : 'Confirm accepted offer & send one secure link' ) . '</button></p></form>';
	}

	private function render_remaining_pack( int $record, bool $terms_ok ): void {
		if ( array() === array_filter( TaskPolicy::documents(), fn( string $kind ): bool => 'not_required' === $this->state->task_status( $record, $kind ) ) ) {
			return;
		}
		echo '<hr><details><summary>Send the rest of the standard onboarding pack</summary><p>Already requested or completed documents are retained; only missing tasks are added.</p>';
		$this->render_assignment_form( $record, $terms_ok && $this->workflow_ready( 'onboarding', TaskPolicy::documents() ), 'extend_onboarding' );
		echo '</details>';
	}

	private function render_individual_documents( int $record ): void {
		$options = '';
		foreach ( TaskPolicy::documents() as $kind ) {
			$status = $this->state->task_status( $record, $kind );
			$ready  = $this->workflow_ready( 'onboarding', array( $kind ) ) && ( 'terms' !== $kind || FormDefinitions::candidate_terms_ready() );
			if ( ! $ready || ! in_array( $status, array( 'not_required', 'awaiting', 'in_progress', 'needs_correction' ), true ) ) {
				continue;
			}
			$options .= '<option value="' . esc_attr( $kind ) . '">' . esc_html( $this->task_label( $kind ) . ( 'not_required' === $status ? '' : ' — resend link' ) ) . '</option>';
		}
		if ( '' === $options ) {
			return;
		}
		echo '<hr><h3>Send one document</h3><p>Issue a personal link for just the selected form. This does not reset the candidate profile or any other document. The standard full-onboarding option remains available.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="poolhall_review_action"><input type="hidden" name="do" value="send_document"><input type="hidden" name="record" value="' . esc_attr( (string) $record ) . '">';
		echo wp_nonce_field( 'poolhall_review_' . $record, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label for="poolhall-document-kind">Document</label> <select id="poolhall-document-kind" name="task" required>' . $options . '</select> <button class="button button-secondary" type="submit">Send secure document link</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each option was escaped above.
	}

	public function handle_action(): void {
		$record = isset( $_POST['record'] ) ? (int) $_POST['record'] : 0;
		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$nonce  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( $nonce, 'poolhall_review_' . $record ) || ! $this->is_candidate_record( $record ) ) {
			wp_die( 'Not allowed.' );
		}

		$result          = 'no_change';
		$stage           = $this->state->stage( $record );
		$redirect_record = $record;
		if ( 'merge_identity' === $do ) {
			$target = isset( $_POST['target'] ) ? (int) $_POST['target'] : 0;
			if ( 'new' === $stage && $this->is_candidate_record( $target ) && CandidateIdentity::merge_after_staff_review( $record, $target ) ) {
				$redirect_record = CandidateIdentity::workflow_owner( $target );
				$result          = 'identity_merged';
			} else {
				$result = 'identity_merge_failed';
			}
		} elseif ( ! in_array( $do, array( 'reject', 'delete_record' ), true ) && $this->duplicate_active_workflow( $record ) > 0 ) {
			$result = 'duplicate_workflow';
		} elseif ( ! $this->action_allowed( $do, $stage ) ) {
			$result = 'stale_action';
		} else {
			switch ( $do ) {
				case 'approve_profile':
					if ( ! $this->workflow_ready( 'profile' ) ) {
						$result = 'setup_required';
					} elseif ( $this->state->begin_stage( $record, 'profile', array( 'full' ) ) ) {
						$result = $this->mailer->send_stage( $record, 'profile' ) ? 'profile_sent' : 'mail_failed';
						update_post_meta( $record, 'review_status', 'profile_sent' === $result ? 'invite_sent' : 'invite_failed' );
					} else {
						$result = 'stale_action';
					}
					break;
				case 'retry_profile':
					if ( ! $this->workflow_ready( 'profile' ) ) {
						$result = 'setup_required';
					} elseif ( 'awaiting_profile' === $this->state->stage( $record ) && in_array( $this->state->task_status( $record, 'full' ), array( 'awaiting', 'in_progress', 'needs_correction' ), true ) ) {
						$result = $this->mailer->send_stage( $record, 'profile' ) ? 'profile_sent' : 'mail_failed';
					}
					break;
				case 'accept_full':
					if ( 'submitted' === $this->state->task_status( $record, 'full' ) && $this->evidence_ready( $record, 'full' ) ) {
						$this->state->mark_accepted( $record, 'full' );
						update_post_meta( $record, 'review_status', 'approved' );
						do_action( CandidateGiigSync::APPROVED_HOOK, $record );
						$result = 'profile_accepted';
					} else {
						$result = 'evidence_missing';
					}
					break;
				case 'sync_giig':
				case 'link_giig':
					if ( 'link_giig' === $do ) {
						$id = isset( $_POST['giig_candidate_id'] ) && is_string( $_POST['giig_candidate_id'] ) ? trim( wp_unslash( $_POST['giig_candidate_id'] ) ) : '';
						$verified = isset( $_POST['giig_details_verified'] ) && '1' === $_POST['giig_details_verified'];
						$result = \Poolhall\Integration\Plugin::instance()->candidate_giig_link()->link( $record, $id, $verified );
						break;
					}
					if ( 'accepted' === $this->state->task_status( $record, 'full' ) ) {
						do_action( CandidateGiigSync::RETRY_HOOK, $record );
						$result = 'giig_sync_checked';
					} else {
						$result = 'profile_required';
					}
					break;
				case 'correct_full':
					if ( 'submitted' === $this->state->task_status( $record, 'full' ) ) {
						$this->state->mark_needs_correction( $record, 'full' );
						$result = $this->workflow_ready( 'profile' )
							? ( $this->mailer->send_stage( $record, 'profile' ) ? 'correction_sent' : 'mail_failed' )
							: 'setup_required';
					}
					break;
				case 'start_onboarding':
					$result = $this->start_onboarding( $record );
					break;
				case 'extend_onboarding':
					$result = $this->start_onboarding( $record, true );
					break;
				case 'send_document':
					$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
					if ( ! in_array( $task, TaskPolicy::documents(), true ) || ( 'terms' === $task && ! FormDefinitions::candidate_terms_ready() ) || ! $this->workflow_ready( 'onboarding', array( $task ) ) ) {
						$result = 'setup_required';
						break;
					}
					$status = $this->state->task_status( $record, $task );
					if ( 'not_required' === $status && ! $this->state->assign_document( $record, $task ) ) {
						$result = 'stale_action';
						break;
					}
					$result = in_array( $this->state->task_status( $record, $task ), array( 'awaiting', 'in_progress', 'needs_correction' ), true )
						? ( $this->mailer->send_document( $record, $task ) ? 'document_sent' : 'mail_failed' )
						: 'stale_action';
					break;
				case 'retry_onboarding':
					if ( ! $this->workflow_ready( 'onboarding', $this->required_onboarding_tasks( $record ) ) ) {
						$result = 'setup_required';
					} elseif ( 'onboarding' === $this->state->stage( $record ) ) {
						$result = $this->mailer->send_stage( $record, 'onboarding' ) ? 'onboarding_sent' : 'mail_failed';
					}
					break;
				case 'accept_task':
				case 'correct_task':
					$task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
					if ( in_array( $task, TaskPolicy::documents(), true ) && 'submitted' === $this->state->task_status( $record, $task ) ) {
						if ( 'accept_task' === $do ) {
							if ( $this->evidence_ready( $record, $task ) ) {
								$this->state->mark_accepted( $record, $task );
								do_action( CandidateGiigSync::DOCUMENT_HOOK, $record );
								$result = 'task_accepted';
								// Accepting the last outstanding task is what ends the
								// journey, so tell the candidate here rather than leaving
								// them wondering whether anything else is coming.
								if ( 'ready' === $this->state->stage( $record ) && '' !== (string) get_post_meta( $record, 'invite_onboarding_mail_sent_at', true ) ) {
									$result = $this->send_completion_email( $record ) ? 'completed' : 'completed_no_mail';
								}
							} else {
								$result = 'evidence_missing';
							}
						} else {
							$this->state->mark_needs_correction( $record, $task );
							$individual = '' !== (string) get_post_meta( $record, 'invite_task_' . $task . '_mail_sent_at', true );
							$result     = $this->workflow_ready( 'onboarding', array( $task ) )
								? ( ( $individual ? $this->mailer->send_document( $record, $task ) : $this->mailer->send_stage( $record, 'onboarding' ) ) ? 'correction_sent' : 'mail_failed' )
								: 'setup_required';
						}
					}
					break;
				case 'resend_completion':
					$result = $this->send_completion_email( $record, true ) ? 'completed' : 'completed_no_mail';
					break;
				case 'delete_record':
					$confirm = isset( $_POST['confirm_delete'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_delete'] ) ) : '';
					if ( 'DELETE' !== strtoupper( trim( $confirm ) ) ) {
						$result = 'delete_unconfirmed';
						break;
					}
					$this->invites->revoke_all( $record );
					$this->erase_record_files( $record );
					$result          = wp_delete_post( $record, true ) ? 'record_deleted' : 'delete_failed';
					$redirect_record = 0;
					break;
				case 'reject':
					$this->invites->revoke_all( $record );
					update_post_meta( $record, 'workflow_stage', 'closed' );
					update_post_meta( $record, 'workflow_stage_updated_at', gmdate( \DateTimeInterface::ATOM ) );
					update_post_meta( $record, 'review_status', 'rejected' );
					$result = 'closed';
					break;
			}
		}

		$url = add_query_arg(
			array(
				'page'   => self::PAGE,
				'record' => $redirect_record,
				'result' => $result,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	private function start_onboarding( int $record, bool $extend = false ): string {
		if ( 'accepted' !== $this->state->task_status( $record, 'full' ) || ! in_array( $this->state->stage( $record ), $extend ? array( 'onboarding', 'onboarding_review', 'ready' ) : array( 'registered' ), true ) ) {
			return 'profile_required';
		}
		if ( ! TaskPolicy::archived( 'terms' ) && ! FormDefinitions::candidate_terms_ready() ) {
			return 'legal_pending';
		}
		// The admin nonce is verified in handle_action() immediately before this helper is called.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$role       = isset( $_POST['onboarding_role'] ) ? sanitize_text_field( wp_unslash( $_POST['onboarding_role'] ) ) : '';
		$start      = isset( $_POST['onboarding_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['onboarding_start_date'] ) ) : '';
		$rate       = isset( $_POST['onboarding_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['onboarding_rate'] ) ) : '';
		$engagement = isset( $_POST['onboarding_engagement'] ) ? sanitize_key( wp_unslash( $_POST['onboarding_engagement'] ) ) : '';
		$reference  = isset( $_POST['onboarding_job_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['onboarding_job_reference'] ) ) : '';
		if ( '' === $role || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || ! in_array( $engagement, array( 'temporary', 'contractor', 'permanent' ), true ) ) {
			return 'assignment_invalid';
		}
		$requested = isset( $_POST['tasks'] ) && is_array( $_POST['tasks'] )
			? array_filter(
				array_map(
					static fn( mixed $task ): string => is_scalar( $task ) ? sanitize_key( (string) $task ) : '',
					wp_unslash( $_POST['tasks'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every scalar is sanitized by the callback.
				)
			)
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$mandatory = TaskPolicy::archived( 'terms' ) ? array() : array( 'terms' );
		if ( in_array( $engagement, array( 'temporary', 'contractor' ), true ) ) {
			$mandatory[] = 'contract';
		}
		$tasks = array_values(
			array_unique(
				array_intersect(
					TaskPolicy::documents(),
					array_merge( $mandatory, $requested )
				)
			)
		);
		if ( array() === $tasks ) {
			return 'assignment_invalid';
		}
		if ( ! $this->workflow_ready( 'onboarding', $tasks ) ) {
			return 'setup_required';
		}
		if ( ! ( $extend ? $this->state->extend_onboarding( $record, $tasks ) : $this->state->begin_stage( $record, 'onboarding', $tasks ) ) ) {
			return 'stale_action';
		}
		foreach ( array(
			'onboarding_role'          => $role,
			'onboarding_start_date'    => $start,
			'onboarding_rate'          => $rate,
			'onboarding_engagement'    => $engagement,
			'onboarding_job_reference' => $reference,
			'offer_accepted_at'        => gmdate( \DateTimeInterface::ATOM ),
		) as $key => $value ) {
			update_post_meta( $record, $key, $value );
		}
		return $this->mailer->send_stage( $record, 'onboarding' ) ? 'onboarding_sent' : 'mail_failed';
	}

	public function download_cv(): void {
		$record = isset( $_GET['record'] ) ? (int) $_GET['record'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the nonce.
		$key    = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- stable key still requires admin capability.
		$stable = (string) get_post_meta( $record, 'cv_key', true );
		$valid  = false !== wp_verify_nonce( $nonce, 'poolhall_cv_' . $record ) || ( '' !== $stable && '' !== $key && hash_equals( $stable, $key ) );
		if ( ! current_user_can( self::CAP ) || ! $this->is_candidate_record( $record ) || ! $valid ) {
			wp_die( 'Not allowed.' );
		}
		$path = (string) get_post_meta( $record, 'cv_path', true );
		$this->serve_file( $path, $this->cv_allowed_directory( $path ), 'application/octet-stream', (string) get_post_meta( $record, 'cv_filename', true ) );
	}

	public function download_document(): void {
		$record = isset( $_GET['record'] ) ? (int) $_GET['record'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		$task   = isset( $_GET['task'] ) ? sanitize_key( wp_unslash( $_GET['task'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the nonce.
		if ( ! current_user_can( self::CAP ) || ! $this->is_candidate_record( $record ) || ! in_array( $task, WorkflowState::TASKS, true ) || ! wp_verify_nonce( $nonce, 'poolhall_document_' . $record . '_' . $task ) ) {
			wp_die( 'Not allowed.' );
		}
		$this->serve_file(
			(string) get_post_meta( $record, $this->state->meta( $task, 'pdf_path' ), true ),
			PdfBuilder::storage_dir(),
			'application/pdf',
			(string) get_post_meta( $record, $this->state->meta( $task, 'pdf_download_name' ), true )
		);
	}

	public function download_task_upload(): void {
		$record = isset( $_GET['record'] ) ? (int) $_GET['record'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		$task   = isset( $_GET['task'] ) ? sanitize_key( wp_unslash( $_GET['task'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		$index  = isset( $_GET['file'] ) ? (int) $_GET['file'] : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below.
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the nonce.
		if ( ! current_user_can( self::CAP ) || ! $this->is_candidate_record( $record ) || ! in_array( $task, WorkflowState::TASKS, true ) || $index < 0 || ! wp_verify_nonce( $nonce, 'poolhall_task_upload_' . $record . '_' . $task . '_' . $index ) ) {
			wp_die( 'Not allowed.' );
		}
		$uploads  = json_decode( (string) get_post_meta( $record, $this->state->meta( $task, 'protected_uploads' ), true ), true );
		$path     = is_array( $uploads ) && isset( $uploads[ $index ]['path'] ) ? (string) $uploads[ $index ]['path'] : '';
		$filename = is_array( $uploads ) && isset( $uploads[ $index ]['original'] ) ? (string) $uploads[ $index ]['original'] : '';
		$this->serve_file( $path, PdfBuilder::storage_dir(), 'application/octet-stream', $filename );
	}

	private function serve_file( string $path, string $allowed_dir, string $type, string $download_name = '' ): never {
		$real = '' !== $path ? realpath( $path ) : false;
		$base = realpath( $allowed_dir );
		if ( false === $real || false === $base || ! str_starts_with( $real, trailingslashit( $base ) ) || ! is_file( $real ) ) {
			wp_die( 'The protected file is not available.' );
		}
		nocache_headers();
		header( 'Content-Type: ' . $type );
		$name = sanitize_file_name( '' !== trim( $download_name ) ? $download_name : basename( $real ) );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) filesize( $real ) );
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- capability, nonce/key and canonical protected directory checked.
		exit;
	}

	/** New CVs live outside ABSPATH; retain read-only access to a migration failure. */
	private function cv_allowed_directory( string $path ): string {
		try {
			$private = PrivateStorage::directory( 'cvs' );
			$real    = '' !== $path ? realpath( $path ) : false;
			$base    = realpath( $private );
			if ( false !== $real && false !== $base && str_starts_with( $real, trailingslashit( $base ) ) ) {
				return $private;
			}
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
		}
		$uploads = wp_upload_dir();
		return trailingslashit( (string) $uploads['basedir'] ) . 'poolhall-applications';
	}

	/** @param array<string,string> $rows */
	private function summary_card( string $title, array $rows ): void {
		echo '<section style="background:#fff;border:1px solid #dcdcde;padding:16px"><h2 style="margin-top:0">' . esc_html( $title ) . '</h2><dl style="margin:0">';
		foreach ( $rows as $label => $value ) {
			if ( '' !== trim( $value ) ) {
				echo '<dt style="font-weight:600">' . esc_html( $label ) . '</dt><dd style="margin:0 0 8px">' . esc_html( $value ) . '</dd>';
			}
		}
		echo '</dl></section>';
	}

	/** @param array<string,string> $fields */
	private function action_form( int $record, string $action_name, string $label, array $fields = array(), bool $primary = false ): string {
		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 6px 6px 0">';
		$html .= '<input type="hidden" name="action" value="poolhall_review_action"><input type="hidden" name="record" value="' . esc_attr( (string) $record ) . '"><input type="hidden" name="do" value="' . esc_attr( $action_name ) . '">';
		foreach ( $fields as $key => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		$html .= wp_nonce_field( 'poolhall_review_' . $record, '_wpnonce', true, false );
		$html .= '<button class="button' . ( $primary ? ' button-primary' : '' ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
		return $html;
	}

	/** @param array<string,string> $checklist */
	private function compact_checklist( array $checklist ): string {
		$parts = array();
		foreach ( $checklist as $task => $status ) {
			if ( 'not_required' !== $status ) {
				$parts[] = esc_html( $this->task_label( $task ) . ': ' . $this->label( $status ) );
			}
		}
		return array() === $parts ? '&mdash;' : implode( '<br>', $parts );
	}

	private function render_notice(): void {
		$result   = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status after redirect.
		$messages = array(
			'profile_sent'          => array( 'success', 'The secure candidate-profile link was accepted by the site mailer.' ),
			'profile_accepted'      => array( 'success', 'The candidate profile has been accepted. Check the separate Giig integration status below for the queued sync.' ),
			'giig_sync_checked'     => array( 'info', 'Giig sync checked. See the integration status below; forms and email routing were not changed.' ),
			'giig_linked_api'       => array( 'success', 'Existing Giig candidate verified and linked. Future forms use this same ID; check the separate sync status below.' ),
			'giig_linked_staff'     => array( 'success', 'Existing Giig candidate linked using your recorded manual verification. Giig blocked the API read; profile updates still depend on Giig permissions.' ),
			'giig_link_blocked'     => array( 'warning', 'Giig link was not changed. See the integration panel for the reason.' ),
			'giig_link_busy'        => array( 'info', 'Another Giig operation is in progress. Wait briefly, then retry linking.' ),
			'giig_link_not_allowed' => array( 'warning', 'This candidate cannot be linked until its workflow or identity conflict is resolved.' ),
			'document_sent'         => array( 'success', 'The selected document link was accepted by the site mailer. Other tasks were not changed.' ),
			'onboarding_sent'       => array( 'success', 'The accepted-offer details were saved and one secure post-offer task link was accepted by the site mailer.' ),
			'correction_sent'       => array( 'success', 'The correction request link was accepted by the site mailer.' ),
			'task_accepted'         => array( 'success', 'The submitted task has been accepted.' ),
			'closed'                => array( 'success', 'The workflow was closed and outstanding candidate access was revoked.' ),
			'added'                 => array( 'success', 'Candidate added. Send the profile invitation when you are ready.' ),
			'add_invalid'           => array( 'error', 'Add a name and a valid email address.' ),
			'add_duplicate'         => array( 'warning', 'That email already has a candidate record — opened it below rather than creating a second one.' ),
			'add_failed'            => array( 'error', 'The candidate record could not be created.' ),
			'record_deleted'        => array( 'success', 'Candidate deleted. Their forms, signed PDFs, uploads and CV were erased with the record.' ),
			'bulk_closed'           => array( 'success', 'Selected workflows were closed and their access revoked.' ),
			'bulk_deleted'          => array( 'success', 'Selected candidates were deleted along with their forms, PDFs, uploads and CVs.' ),
			'bulk_none'             => array( 'warning', 'Nothing happened — select at least one candidate and choose a bulk action.' ),
			'completed'             => array( 'success', 'Onboarding complete. The candidate has been emailed to say we will be in touch.' ),
			'completed_no_mail'     => array( 'warning', 'Onboarding is complete, but the completion email was not accepted by the mailer. Resend it from the record.' ),
			'delete_unconfirmed'    => array( 'warning', 'Nothing was deleted — type DELETE in the confirmation box to erase a candidate.' ),
			'delete_failed'         => array( 'error', 'The candidate record could not be deleted.' ),
			'mail_failed'           => array( 'error', 'WordPress did not accept the email. The task remains visible for a manual retry; check mail configuration.' ),
			'legal_pending'         => array( 'warning', 'Onboarding was not sent because the Candidate Terms are still draft.' ),
			'assignment_invalid'    => array( 'error', 'Add a confirmed role, valid start date and engagement type before issuing onboarding.' ),
			'profile_required'      => array( 'error', 'Accept the candidate profile before starting onboarding.' ),
			'duplicate_workflow'    => array( 'warning', 'This candidate already has an active website workflow. Continue that record to avoid duplicate forms and reminders.' ),
			'identity_merged'       => array( 'success', 'The submission was linked to the existing candidate workflow after staff review. Conflicting Giig IDs, if any, remain flagged for manual reconciliation.' ),
			'identity_merge_failed' => array( 'error', 'The records were not linked. Confirm they have the same email and that this submission has not already started a workflow.' ),
			'evidence_missing'      => array( 'error', 'This task cannot be accepted because its protected evidence file is unavailable. Request a fresh submission.' ),
			'setup_required'        => array( 'error', 'No invitation was sent because the candidate task page, required form or private storage is not ready. Run Site setup and review workflow readiness.' ),
			'stale_action'          => array( 'warning', 'The workflow changed after this page was opened. No changes were made; review the current next action below.' ),
		);
		if ( isset( $messages[ $result ] ) ) {
			[ $type, $message ] = $messages[ $result ];
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Tell the candidate their onboarding is finished. Sent once when the last
	 * task is accepted; `$force` re-sends it from the record if it bounced or
	 * they ask again. Carries no link and no personal data — there is nothing
	 * left for them to do, so there is nothing to secure.
	 */
	private function send_completion_email( int $record, bool $force = false ): bool {
		$already = (string) get_post_meta( $record, 'completion_mail_status', true );
		if ( ! $force && 'accepted' === $already ) {
			return true;
		}

		$email = sanitize_email( (string) get_post_meta( $record, 'applicant_email', true ) );
		if ( '' === $email || ! is_email( $email ) ) {
			update_post_meta( $record, 'completion_mail_status', 'invalid_recipient' );
			return false;
		}

		$first    = sanitize_text_field( (string) get_post_meta( $record, 'applicant_first', true ) );
		$greeting = '' !== $first ? 'Hi ' . $first . ',' : 'Hello,';
		$role     = sanitize_text_field( (string) get_post_meta( $record, 'onboarding_role', true ) );

		$done = 'Thank you — everything is now complete and your paperwork is with us.'
			. ( '' !== $role ? ' That covers your ' . $role . ' assignment.' : '' );
		$next = 'There is nothing else for you to do. One of the team will be in touch with you directly about next steps.';
		$talk = 'If you would like to speak to us before then, or anything changes in the meantime, call 0121 516 3000 or email jobs@poolhallrecruitment.co.uk and we will pick it up.';

		$text = $greeting . "\n\n" . $done . "\n\n" . $next . "\n\n" . $talk . "\n\n"
			. 'Thanks again,' . "\n"
			. 'Poolhall Recruitment Limited' . "\n"
			. '0121 516 3000 | jobs@poolhallrecruitment.co.uk';

		$content = BrandedMail::heading( 'You&rsquo;re all set' )
			. BrandedMail::paragraph( esc_html( $greeting ) )
			. BrandedMail::paragraph( esc_html( $done ) )
			. BrandedMail::paragraph( esc_html( $next ) )
			. BrandedMail::callout(
				'Need to speak to us before then?',
				'Call ' . BrandedMail::link( 'tel:+441215163000', '0121 516 3000' )
				. ' or email ' . BrandedMail::link( 'mailto:jobs@poolhallrecruitment.co.uk', 'jobs@poolhallrecruitment.co.uk' )
				. ' and we will pick it up.'
			)
			. BrandedMail::paragraph( 'Thanks again,<br /><strong style="color:#0B2846;">The Poolhall team</strong>' );

		try {
			$sent = BrandedMail::send( $email, 'You are all set with Poolhall Recruitment', $content, $text );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			$sent = false;
		}

		update_post_meta( $record, 'completion_mail_status', $sent ? 'accepted' : 'failed' );
		update_post_meta( $record, 'completion_mail_attempted_at', gmdate( \DateTimeInterface::ATOM ) );
		return $sent;
	}

	/**
	 * Remove everything a record owns outside the post table: signed PDFs,
	 * protected uploads, the stored CV and the Gravity Forms entries. Deleting
	 * only the post would leave bank details and signatures on disk, so this
	 * runs before the record itself goes.
	 */
	private function erase_record_files( int $record ): void {
		foreach ( WorkflowState::TASKS as $task ) {
			$pdf = (string) get_post_meta( $record, $this->state->meta( $task, 'pdf_path' ), true );
			if ( '' !== $pdf && is_file( $pdf ) ) {
				wp_delete_file( $pdf );
			}
			$uploads = json_decode( (string) get_post_meta( $record, $this->state->meta( $task, 'protected_uploads' ), true ), true );
			if ( is_array( $uploads ) ) {
				foreach ( $uploads as $upload ) {
					if ( is_array( $upload ) && ! empty( $upload['path'] ) && is_file( (string) $upload['path'] ) ) {
						wp_delete_file( (string) $upload['path'] );
					}
				}
			}
			$entry_id = (int) get_post_meta( $record, $this->state->meta( $task, 'entry_id' ), true );
			if ( $entry_id > 0 && class_exists( '\GFAPI' ) ) {
				\GFAPI::delete_entry( $entry_id );
			}
		}
		$cv = (string) get_post_meta( $record, 'cv_path', true );
		if ( '' !== $cv && is_file( $cv ) ) {
			wp_delete_file( $cv );
		}
	}

	/**
	 * Create a candidate by hand, for someone who came in by phone or referral
	 * rather than through the website. The record lands at the same starting
	 * point a website registration does, so the rest of the workflow is
	 * identical from here on.
	 */
	public function handle_add(): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( $nonce, 'poolhall_add_candidate' ) ) {
			wp_die( 'Not allowed.' );
		}

		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$note  = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

		$name   = trim( $first . ' ' . $last );
		$result = 'added';
		$record = 0;

		if ( '' === $name || '' === $email || ! is_email( $email ) ) {
			$result = 'add_invalid';
		} else {
			// One candidate identity per email: point at the existing record
			// rather than opening a second workflow for the same person.
			$existing = get_posts(
				array(
					'post_type'      => ApplicationRecord::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => 'applicant_email_normalized', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- indexed lookup on a private, bounded queue.
					'meta_value'     => CandidateIdentity::normalize_email( $email ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( is_array( $existing ) && array() !== $existing ) {
				$result = 'add_duplicate';
				$record = (int) $existing[0];
			} else {
				$record = ApplicationRecord::store(
					$name . ' — added by hand',
					array(
						'kind'            => 'registration',
						'applicant_name'  => $name,
						'applicant_first' => $first,
						'applicant_last'  => $last,
						'applicant_email' => $email,
						'applicant_phone' => $phone,
						'manual_note'     => $note,
						'added_manually'  => '1',
						'added_by'        => (string) get_current_user_id(),
						'review_status'   => 'new',
						'submitted_at'    => gmdate( \DateTimeInterface::ATOM ),
					)
				);
				if ( $record > 0 ) {
					CandidateIdentity::attach( $record, $email );
				} else {
					$result = 'add_failed';
				}
			}
		}

		$args = array(
			'page'   => self::PAGE,
			'result' => $result,
		);
		if ( $record > 0 ) {
			$args['record'] = $record;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Collapsed "add a candidate" form above the queue. */
	private function render_add_form(): void {
		echo '<details style="margin:14px 0;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;max-width:850px">';
		echo '<summary style="cursor:pointer;font-weight:600">Add a candidate by hand</summary>';
		echo '<p class="description">For someone who came in by phone or referral. They start at the same point as a website registration, so you still send the profile invitation from their record.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="poolhall_add_candidate">';
		wp_nonce_field( 'poolhall_add_candidate' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="ph-first">First name</label></th><td><input required id="ph-first" name="first_name" type="text" class="regular-text"></td></tr>';
		echo '<tr><th><label for="ph-last">Last name</label></th><td><input required id="ph-last" name="last_name" type="text" class="regular-text"></td></tr>';
		echo '<tr><th><label for="ph-email">Email</label></th><td><input required id="ph-email" name="email" type="email" class="regular-text"><p class="description">Their secure task links go here. One record per email address.</p></td></tr>';
		echo '<tr><th><label for="ph-phone">Phone</label></th><td><input id="ph-phone" name="phone" type="tel" class="regular-text"><p class="description">Optional.</p></td></tr>';
		echo '<tr><th><label for="ph-note">Where did they come from?</label></th><td><input id="ph-note" name="note" type="text" class="regular-text" placeholder="Referral, phone enquiry, jobs fair…"><p class="description">Optional. Kept on the record for your reference.</p></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">Add candidate</button></p>';
		echo '</form></details>';
	}

	/** Permanent erasure, kept behind a typed confirmation. */
	private function render_delete_block( int $record ): void {
		echo '<hr><details style="margin-top:18px"><summary style="cursor:pointer;color:#b32d2e">Delete this candidate permanently</summary>';
		echo '<p class="description" style="max-width:640px">Erases the record and everything attached to it: submitted form entries, signed PDFs, uploaded files such as a P45, and the stored CV. This cannot be undone. To keep the paperwork but stop the workflow, close it instead.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="poolhall_review_action">';
		echo '<input type="hidden" name="record" value="' . esc_attr( (string) $record ) . '">';
		echo '<input type="hidden" name="do" value="delete_record">';
		wp_nonce_field( 'poolhall_review_' . $record, '_wpnonce' );
		echo '<p><label for="ph-confirm-delete">Type <code>DELETE</code> to confirm</label> ';
		echo '<input id="ph-confirm-delete" name="confirm_delete" type="text" autocomplete="off" style="width:120px" required></p>';
		echo '<button class="button button-link-delete" type="submit">Delete candidate and all their documents</button>';
		echo '</form></details>';
	}

	private function is_candidate_record( int $record ): bool {
		return $record > 0
			&& ApplicationRecord::POST_TYPE === get_post_type( $record )
			&& in_array( (string) get_post_meta( $record, 'kind', true ), array( 'registration', 'job_application' ), true );
	}

	private function action_allowed( string $action, string $stage ): bool {
		return match ( $action ) {
			'merge_identity'                 => 'new' === $stage,
			'approve_profile'                => 'new' === $stage,
			'retry_profile'                  => 'awaiting_profile' === $stage,
			'accept_full', 'correct_full'    => 'registration_review' === $stage,
			'start_onboarding'               => 'registered' === $stage,
			'extend_onboarding'              => in_array( $stage, array( 'onboarding', 'onboarding_review', 'ready' ), true ),
			'send_document'                  => 'closed' !== $stage,
			'sync_giig'                     => 'closed' !== $stage,
			'link_giig'                     => 'closed' !== $stage,
			'retry_onboarding'               => 'onboarding' === $stage,
			'accept_task', 'correct_task'     => 'closed' !== $stage,
			'reject'                         => 'closed' !== $stage,
			// Erasure has to work from any stage: a candidate asking to be
			// removed is not waiting for their workflow to reach a tidy point.
			'delete_record'                  => true,
			'resend_completion'              => 'ready' === $stage,
			default                          => false,
		};
	}

	/** @return int[] Candidate records sharing contact email, for staff review only. */
	private function matching_email_records( int $record ): array {
		$email = CandidateIdentity::normalize_email( (string) get_post_meta( $record, 'applicant_email', true ) );
		if ( '' === $email ) {
			return array();
		}
		$records = get_posts(
			array(
				'post_type'      => ApplicationRecord::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'exclude'        => array( $record ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small admin-only collision-review query.
					'relation' => 'AND',
					array(
						'key'     => 'kind',
						'value'   => array( 'registration', 'job_application' ),
						'compare' => 'IN',
					),
					array(
						'relation' => 'OR',
						array(
							'key'   => 'applicant_email_normalized',
							'value' => $email,
						),
						array(
							'key'   => 'applicant_email',
							'value' => $email,
						),
					),
				),
			)
		);
		return array_values(
			array_filter(
				array_map( 'intval', $records ),
				fn( int $candidate_record ): bool => 'closed' !== $this->state->stage( $candidate_record )
			)
		);
	}

	/** Return the explicitly established workflow owner for a linked record. */
	private function duplicate_active_workflow( int $record ): int {
		$current_stage = $this->state->stage( $record );
		if ( 'new' !== $current_stage && ! in_array( $current_stage, self::ACTIVE_WORKFLOW_STAGES, true ) ) {
			return 0;
		}
		$owner = CandidateIdentity::workflow_owner( $record );
		return $owner > 0 && $owner !== $record && in_array( $this->state->stage( $owner ), self::ACTIVE_WORKFLOW_STAGES, true ) ? $owner : 0;
	}

	/** @return string[] Tasks currently required for this candidate's post-offer stage. */
	private function required_onboarding_tasks( int $record ): array {
		return array_values(
			array_filter(
				TaskPolicy::documents(),
				fn( string $task ): bool => 'not_required' !== $this->state->task_status( $record, $task )
			)
		);
	}

	/** Required forms, task page and private storage must exist before email. */
	private function workflow_ready( string $stage, array $tasks = array() ): bool {
		if ( ! class_exists( '\\GFAPI' ) ) {
			return false;
		}
		$map    = array_map( 'intval', (array) get_option( DocumentWorkflow::FORM_MAP_OPTION, array() ) );
		$needed = 'profile' === $stage ? array( 'full' ) : array_values( array_unique( $tasks ) );
		if ( in_array( 'contract', $needed, true ) && ! ContractPdfBuilder::source_ready() ) {
			return false;
		}
		foreach ( $needed as $kind ) {
			if ( TaskPolicy::archived( $kind ) ) {
				return false;
			}
			if ( ! isset( $map[ $kind ] ) ) {
				return false;
			}
			$form = \GFAPI::get_form( $map[ $kind ] );
			if ( ! is_array( $form ) || '0' === (string) ( $form['is_active'] ?? '1' ) ) {
				return false;
			}
		}

		$page = get_page_by_path( InviteManager::HUB_SLUG );
		if ( ! $page instanceof \WP_Post || 'publish' !== $page->post_status ) {
			return false;
		}
		$page_markup = (string) $page->post_content . ' ' . (string) get_post_meta( $page->ID, '_elementor_data', true );
		if ( ! CandidateHub::is_embedded( $page_markup ) ) {
			return false;
		}

		try {
			PrivateStorage::directory( 'documents' );
			return true;
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return false;
		}
	}

	/** A recruiter may accept a task only when its protected PDF still exists. */
	private function evidence_ready( int $record, string $task ): bool {
		$path = (string) get_post_meta( $record, $this->state->meta( $task, 'pdf_path' ), true );
		if ( '' === $path || ! is_file( $path ) ) {
			return false;
		}
		try {
			$real = realpath( $path );
			$base = realpath( PdfBuilder::storage_dir() );
			return false !== $real && false !== $base && str_starts_with( $real, trailingslashit( $base ) );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return false;
		}
	}

	private function next_action_label( string $action ): string {
		return match ( $action ) {
			'approve_profile'   => 'Review & invite',
			'retry_profile'     => 'Retry profile email',
			'review_profile'    => 'Review profile',
			'start_onboarding'  => 'Start onboarding',
			'retry_onboarding'  => 'Retry onboarding email',
			'review_onboarding' => 'Review onboarding',
			'awaiting_candidate'=> 'View progress',
			default             => 'View candidate',
		};
	}

	private function task_label( string $task ): string {
		return match ( $task ) {
			'full'     => 'Candidate profile',
			'contract' => 'Agency Worker Contract',
			'terms'    => 'Candidate Terms',
			'starter'  => 'Starter checklist',
			'payroll'  => 'Payroll details',
			default    => ucfirst( str_replace( '_', ' ', $task ) ),
		};
	}

	private function label( string $value ): string {
		return ucfirst( str_replace( '_', ' ', '' !== $value ? $value : 'not sent' ) );
	}
}
