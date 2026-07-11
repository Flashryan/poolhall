<?php
/**
 * Recruiter review screen for employer enquiries.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Enquiries;

use Poolhall\Integration\Source\Giig\GiigCompanySink;
use Poolhall\Integration\Source\SourceException;

/**
 * The approve gate between the employers/contact forms and the Giig CRM:
 * employer leads are stored locally on submission, and only the explicit
 * "Create company in Giig" action here writes to the ATS — so spam never
 * becomes a CRM record. Lives as a submenu of the Candidates screen.
 */
final class EnquiryReviewScreen {

	private const CAP = 'manage_options';

	public function __construct( private readonly ?GiigCompanySink $sink ) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_poolhall_enquiry_review', array( $this, 'handle_action' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'poolhall-candidates',
			'Employer enquiries',
			'Employer enquiries',
			self::CAP,
			'poolhall-enquiries',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$records = get_posts(
			array(
				'post_type'      => 'poolhall_application',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_key'       => 'kind', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small admin list.
				'meta_value'     => 'enquiry', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		echo '<div class="wrap"><h1>Employer enquiries</h1>';
		echo '<p>Hiring enquiries from the website. Creating a company here adds it to Giig as a client record — nothing is sent to Giig until you approve it.</p>';
		if ( null === $this->sink || ! $this->sink->is_configured() ) {
			echo '<div class="notice notice-warning"><p>Giig is not configured, so companies cannot be created from here yet.</p></div>';
		}
		echo '<table class="widefat striped"><thead><tr><th>Company</th><th>Contact</th><th>Received</th><th>Message</th><th>Giig</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $records as $r ) {
			$id      = (int) $r->ID;
			$status  = (string) ( get_post_meta( $id, 'review_status', true ) ?: 'new' );
			$giig    = (string) get_post_meta( $id, 'giig_company_id', true );
			$error   = (string) get_post_meta( $id, 'giig_push_error', true );
			$message = (string) get_post_meta( $id, 'message', true );

			$website = (string) get_post_meta( $id, 'company_website', true );
			echo '<tr><td><strong>' . esc_html( (string) get_post_meta( $id, 'company_name', true ) ) . '</strong>'
				. ( '' !== $website ? '<br /><a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html( $website ) . '</a>' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) get_post_meta( $id, 'contact_name', true ) )
				. '<br /><span style="color:#666">' . esc_html( (string) get_post_meta( $id, 'contact_email', true ) ) . '</span>'
				. '<br /><span style="color:#666">' . esc_html( (string) get_post_meta( $id, 'contact_phone', true ) ) . '</span></td>';
			echo '<td>' . esc_html( get_the_date( 'j M Y H:i', $r ) ) . '</td>';
			echo '<td>' . esc_html( mb_substr( $message, 0, 160 ) . ( mb_strlen( $message ) > 160 ? '…' : '' ) ) . '</td>';
			$contact_id = (string) get_post_meta( $id, 'giig_contact_id', true );
			echo '<td>' . ( '' !== $giig
				? '<a href="https://app.giighire.com/companies/' . esc_attr( $giig ) . '" target="_blank" rel="noopener">#' . esc_html( $giig ) . '</a>'
				: '&mdash;' )
				. ( '' !== $contact_id ? '<br /><span style="color:#666">contact #' . esc_html( $contact_id ) . '</span>' : '' ) . '</td>';
			echo '<td><code>' . esc_html( str_replace( '_', ' ', $status ) ) . '</code>'
				. ( '' !== $error && 'company_created' !== $status ? '<br /><span style="color:#b32d2e">' . esc_html( $error ) . '</span>' : '' ) . '</td>';
			echo '<td>';
			if ( '' === $giig ) {
				foreach ( array(
					'create_company' => 'Create company in Giig',
					'dismiss'        => 'Dismiss',
				) as $act => $label ) {
					echo '<a class="button button-small" style="margin:0 4px 4px 0" href="'
						. esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=poolhall_enquiry_review&do=' . $act . '&record=' . $id ), 'poolhall_enquiry_' . $id ) )
						. '">' . esc_html( $label ) . '</a>';
				}
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function handle_action(): void {
		$record = isset( $_GET['record'] ) ? (int) $_GET['record'] : 0;
		$do     = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'poolhall_enquiry_' . $record ) ) {
			wp_die( 'Not allowed.' );
		}

		if ( 'dismiss' === $do ) {
			update_post_meta( $record, 'review_status', 'dismissed' );
		} elseif ( 'create_company' === $do ) {
			$this->create_company( $record );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=poolhall-enquiries' ) );
		exit;
	}

	private function create_company( int $record ): void {
		if ( null === $this->sink || ! $this->sink->is_configured() ) {
			update_post_meta( $record, 'giig_push_error', 'Giig is not configured.' );
			return;
		}
		$company = (string) get_post_meta( $record, 'company_name', true );
		if ( '' === $company ) {
			// Giig requires Name; a contact-form employer lead may not have one.
			$company = trim( (string) get_post_meta( $record, 'contact_name', true ) );
		}

		try {
			$giig_id = $this->sink->create_company(
				$company,
				array(
					'Website'      => (string) get_post_meta( $record, 'company_website', true ),
					'EmailAddress' => (string) get_post_meta( $record, 'contact_email', true ),
					'PhoneNumber'  => (string) get_post_meta( $record, 'contact_phone', true ),
					'Notes'        => 'Contact: ' . (string) get_post_meta( $record, 'contact_name', true )
						. "\n\n" . (string) get_post_meta( $record, 'message', true )
						. "\n\nCreated from a website hiring enquiry (record #" . $record . ').',
				)
			);
			update_post_meta( $record, 'giig_company_id', $giig_id );
			update_post_meta( $record, 'review_status', 'company_created' );
			delete_post_meta( $record, 'giig_push_error' );
		} catch ( SourceException $e ) {
			update_post_meta( $record, 'giig_push_error', $e->getMessage() );
			return;
		}

		$this->create_contact( $record, $company );
	}

	/**
	 * The person behind the approved enquiry becomes a Giig contact attached
	 * to the new company, with the enquiry message on their timeline. The
	 * company is the primary outcome, so contact failures are recorded but
	 * never undo the company_created status. Giig dedupes contacts by email,
	 * so re-approving a repeat enquiry updates rather than duplicates.
	 */
	private function create_contact( int $record, string $company ): void {
		$name = trim( (string) get_post_meta( $record, 'contact_name', true ) );
		if ( '' === $name || null === $this->sink ) {
			return;
		}
		$parts = preg_split( '/\s+/', $name, 2 );
		$first = is_array( $parts ) && '' !== ( $parts[0] ?? '' ) ? $parts[0] : $name;
		$last  = is_array( $parts ) && isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : '-';

		try {
			$contact_id = $this->sink->create_contact(
				$first,
				$last,
				array(
					'Email'         => (string) get_post_meta( $record, 'contact_email', true ),
					'ContactNumber' => (string) get_post_meta( $record, 'contact_phone', true ),
					'Company'       => $company,
				)
			);
			update_post_meta( $record, 'giig_contact_id', $contact_id );

			$message = (string) get_post_meta( $record, 'message', true );
			$this->sink->add_contact_note(
				$contact_id,
				'Hiring enquiry via the website (record #' . $record . "):\n\n" . $message
			);
		} catch ( SourceException $e ) {
			update_post_meta( $record, 'giig_push_error', 'Company created; contact step failed: ' . $e->getMessage() );
		}
	}
}
