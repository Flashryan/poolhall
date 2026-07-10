<?php
/**
 * Recruiter review screen for website registrations.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/**
 * The approve/reject/invite stage the flow was missing: an admin-only
 * list of pre-registrations with review statuses (new, approved,
 * rejected, invite sent, completed), a secure capability-checked CV
 * download (no public URLs), the Giig candidate link, and one-click
 * "approve & send full registration" which issues the expiring
 * single-use invite and emails it to the candidate.
 */
final class ReviewScreen {

	private const CAP = 'manage_options';

	public function __construct( private readonly InviteManager $invites ) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_poolhall_review_action', array( $this, 'handle_action' ) );
		add_action( 'admin_post_poolhall_cv_download', array( $this, 'download_cv' ) );
	}

	public function menu(): void {
		add_menu_page( 'Candidates', 'Candidates', self::CAP, 'poolhall-candidates', array( $this, 'render' ), 'dashicons-groups', 26 );
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
				'meta_value'     => 'registration', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		echo '<div class="wrap"><h1>Website registrations</h1>';
		echo '<p>Review new candidates, then approve to send the full registration invite (expiring, single-use link). Starter Statement and Terms invites are issued the same way once someone is being onboarded.</p>';
		echo '<table class="widefat striped"><thead><tr><th>Candidate</th><th>Submitted</th><th>Looking for</th><th>Giig</th><th>CV</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $records as $r ) {
			$id     = (int) $r->ID;
			$status = (string) ( get_post_meta( $id, 'review_status', true ) ?: 'new' );
			$giig   = (string) get_post_meta( $id, 'giig_candidate_id', true );
			$cvpath = (string) get_post_meta( $id, 'cv_path', true );
			$email  = (string) get_post_meta( $id, 'applicant_email', true );

			echo '<tr><td><strong>' . esc_html( get_the_title( $r ) ) . '</strong><br /><span style="color:#666">' . esc_html( $email ) . '</span></td>';
			echo '<td>' . esc_html( get_the_date( 'j M Y H:i', $r ) ) . '</td>';
			echo '<td>' . esc_html( (string) get_post_meta( $id, 'role_title', true ) ) . '</td>';
			echo '<td>' . ( '' !== $giig ? '<a href="https://app.giighire.com/candidates/' . esc_attr( $giig ) . '" target="_blank" rel="noopener">#' . esc_html( $giig ) . '</a>' : '&mdash;' ) . '</td>';
			echo '<td>' . ( '' !== $cvpath && file_exists( $cvpath )
				? '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=poolhall_cv_download&record=' . $id ), 'poolhall_cv_' . $id ) ) . '">Download</a>'
				: '<span title="CV was emailed to the team at submission">emailed</span>' ) . '</td>';
			echo '<td><code>' . esc_html( str_replace( '_', ' ', $status ) ) . '</code></td>';
			echo '<td>';
			foreach ( array(
				'approve_full' => 'Approve &amp; send full registration',
				'send_starter' => 'Send starter + terms',
				'reject'       => 'Reject',
			) as $act => $label ) {
				echo '<a class="button button-small" style="margin:0 4px 4px 0" href="'
					. esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=poolhall_review_action&do=' . $act . '&record=' . $id ), 'poolhall_review_' . $id ) )
					. '">' . wp_kses( $label, array() ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function handle_action(): void {
		$record = isset( $_GET['record'] ) ? (int) $_GET['record'] : 0;
		$do     = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'poolhall_review_' . $record ) ) {
			wp_die( 'Not allowed.' );
		}
		$email = (string) get_post_meta( $record, 'applicant_email', true );
		$first = (string) get_post_meta( $record, 'applicant_first', true );

		if ( 'reject' === $do ) {
			update_post_meta( $record, 'review_status', 'rejected' );
		} elseif ( 'approve_full' === $do ) {
			$token = $this->invites->create( $record, array( 'full' ) );
			$link  = $this->invites->link( $token, 'candidate-registration' );
			$this->send_invite( $email, $first, 'complete your full registration', $link );
			update_post_meta( $record, 'review_status', 'invite_sent' );
		} elseif ( 'send_starter' === $do ) {
			$token = $this->invites->create( $record, array( 'starter', 'terms' ) );
			$this->send_invite(
				$email,
				$first,
				'complete your starter statement and terms',
				$this->invites->link( $token, 'candidate-starter-statement' ) . "\n" . $this->invites->link( $token, 'candidate-terms' )
			);
			update_post_meta( $record, 'review_status', 'invite_sent' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=poolhall-candidates' ) );
		exit;
	}

	private function send_invite( string $email, string $first, string $what, string $links ): void {
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}
		wp_mail(
			$email,
			'Poolhall Recruitment - your next step',
			'Hi ' . $first . ",\n\nPlease use your personal link below to " . $what . ". The link is unique to you and expires in 14 days.\n\n"
			. $links . "\n\nAny questions, call us on 0121 516 3000.\n\nPoolhall Recruitment Limited"
		);
	}

	public function download_cv(): void {
		$record = isset( $_GET['record'] ) ? (int) $_GET['record'] : 0;
		if ( ! current_user_can( self::CAP ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'poolhall_cv_' . $record ) ) {
			wp_die( 'Not allowed.' );
		}
		$path = (string) get_post_meta( $record, 'cv_path', true );
		if ( '' === $path || ! file_exists( $path ) ) {
			wp_die( 'CV not stored on the server for this record.' );
		}
		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- capability + nonce checked, protected path.
		exit;
	}
}
