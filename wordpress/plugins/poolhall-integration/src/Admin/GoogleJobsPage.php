<?php
/**
 * Google Jobs readiness screen.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Admin;

use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Schema\JobFromPost;
use Poolhall\Integration\Schema\JobPostingSchema;
use Poolhall\Integration\Support\Options;

/**
 * Everything staff need to get roles into Google Jobs, on one screen:
 * the site-level prerequisites (crawlable, organisation set, sitemap
 * submitted), the per-role verdict with the exact reason a role would be
 * skipped, and a one-click link to Google's own validator.
 *
 * The plugin deliberately refuses to emit JobPosting markup it cannot
 * stand behind, so without this screen a missing location or salary is
 * invisible. This turns that silence into a to-do list.
 */
final class GoogleJobsPage {

	private const CAPABILITY      = 'manage_options';
	private const SETTINGS_ACTION = 'poolhall_google_jobs_settings';

	public function __construct( private readonly Options $options ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::SETTINGS_ACTION, array( $this, 'handle_settings' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'poolhall-jobs',
			__( 'Google Jobs', 'poolhall-integration' ),
			__( 'Google Jobs', 'poolhall-integration' ),
			self::CAPABILITY,
			'poolhall-google-jobs',
			array( $this, 'render' )
		);
	}

	public function handle_settings(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'poolhall-integration' ), '', 403 );
		}
		check_admin_referer( self::SETTINGS_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer above.
		$name = isset( $_POST['hiring_org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hiring_org_name'] ) ) : '';
		$url  = isset( $_POST['hiring_org_url'] ) ? esc_url_raw( wp_unslash( $_POST['hiring_org_url'] ) ) : '';
		$logo = isset( $_POST['hiring_org_logo'] ) ? esc_url_raw( wp_unslash( $_POST['hiring_org_logo'] ) ) : '';
		// phpcs:enable

		update_option( 'poolhall_hiring_org_name', $name );
		update_option( 'poolhall_hiring_org_url', $url );
		update_option( 'poolhall_hiring_org_logo', $logo );

		wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=poolhall-google-jobs' ) ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Google Jobs', 'poolhall-integration' ) . '</h1>';
		echo '<p class="description" style="max-width:70ch">'
			. esc_html__( 'Google Jobs shows roles directly in search results. It reads the structured data this plugin writes into every live job page. Work down this screen: fix anything red, then submit the sitemap once in Search Console.', 'poolhall-integration' )
			. '</p>';

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'poolhall-integration' ) . '</p></div>';
		}

		$this->render_checklist();
		$this->render_settings_form();
		$this->render_job_table();

		echo '</div>';
	}

	// ------------------------------------------------------- site checks --

	/**
	 * Site-level prerequisites. Each is something that silently stops every
	 * role from appearing, no matter how good the individual adverts are.
	 *
	 * @return array<int,array{label:string,ok:bool,detail:string}>
	 */
	public function checks(): array {
		$checks = array();

		$public    = '1' === (string) get_option( 'blog_public' );
		$protected = '1' === (string) get_option( 'password_protected_status', '0' );

		$checks[] = array(
			'label'  => __( 'Search engines can reach the site', 'poolhall-integration' ),
			'ok'     => $public && ! $protected,
			'detail' => $protected
				? __( 'The site is password protected, so Google cannot see any page. Turn this off when you go live.', 'poolhall-integration' )
				: ( $public
					? __( 'The site is visible to search engines.', 'poolhall-integration' )
					: __( 'Settings → Reading is set to discourage search engines. Untick that box to go live.', 'poolhall-integration' ) ),
		);

		$org_name = trim( $this->options->hiring_org_name() );
		$org_url  = trim( $this->options->hiring_org_url() );
		$checks[] = array(
			'label'  => __( 'Hiring organisation is set', 'poolhall-integration' ),
			'ok'     => '' !== $org_name && '' !== $org_url,
			'detail' => '' !== $org_name && '' !== $org_url
				/* translators: %s: organisation name. */
				? sprintf( __( 'Roles are published as %s.', 'poolhall-integration' ), $org_name )
				: __( 'Fill in the organisation details below — without them no role can be listed.', 'poolhall-integration' ),
		);

		$sitemap_on = (bool) apply_filters( 'wp_sitemaps_enabled', true );
		$checks[]   = array(
			'label'  => __( 'Job sitemap is available', 'poolhall-integration' ),
			'ok'     => $sitemap_on,
			'detail' => $sitemap_on
				/* translators: %s: sitemap URL. */
				? sprintf( __( 'Submit %s once in Google Search Console.', 'poolhall-integration' ), home_url( '/wp-sitemap.xml' ) )
				: __( 'The WordPress sitemap is switched off, so Google has no list of your roles to crawl.', 'poolhall-integration' ),
		);

		$live     = $this->live_job_ids();
		$eligible = 0;
		foreach ( $live as $id ) {
			if ( array() === $this->problems_for( (int) $id ) ) {
				++$eligible;
			}
		}
		$checks[] = array(
			'label'  => __( 'Live roles carry Google Jobs data', 'poolhall-integration' ),
			'ok'     => array() !== $live && count( $live ) === $eligible,
			'detail' => array() === $live
				? __( 'No live roles to publish yet.', 'poolhall-integration' )
				: sprintf(
					/* translators: 1: eligible count, 2: total live roles. */
					__( '%1$d of %2$d live roles qualify. Any that do not are listed below with the reason.', 'poolhall-integration' ),
					$eligible,
					count( $live )
				),
		);

		return $checks;
	}

	private function render_checklist(): void {
		echo '<h2>' . esc_html__( 'Before Google can list you', 'poolhall-integration' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $this->checks() as $check ) {
			echo '<tr><td style="width:30px;font-size:18px">' . ( $check['ok'] ? '&#9989;' : '&#10060;' ) . '</td>'
				. '<td style="width:320px"><strong>' . esc_html( $check['label'] ) . '</strong></td>'
				. '<td>' . esc_html( $check['detail'] ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<p><a class="button" href="https://search.google.com/search-console" target="_blank" rel="noopener">'
			. esc_html__( 'Open Google Search Console', 'poolhall-integration' ) . '</a> '
			. '<a class="button" href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'View the sitemap', 'poolhall-integration' ) . '</a></p>';
	}

	private function render_settings_form(): void {
		echo '<h2>' . esc_html__( 'Organisation details', 'poolhall-integration' ) . '</h2>';
		echo '<p class="description" style="max-width:70ch">'
			. esc_html__( 'Google shows this as the employer on every listing. For agency adverts this is Poolhall Recruitment; the client company is named in the advert itself.', 'poolhall-integration' )
			. '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SETTINGS_ACTION ) . '" />';
		wp_nonce_field( self::SETTINGS_ACTION );
		echo '<table class="form-table" role="presentation"><tbody>';
		$fields = array(
			'hiring_org_name' => array( __( 'Organisation name', 'poolhall-integration' ), $this->options->hiring_org_name(), 'text' ),
			'hiring_org_url'  => array( __( 'Website', 'poolhall-integration' ), $this->options->hiring_org_url(), 'url' ),
			'hiring_org_logo' => array( __( 'Logo URL', 'poolhall-integration' ), (string) $this->options->hiring_org_logo(), 'url' ),
		);
		foreach ( $fields as $name => [ $label, $value, $type ] ) {
			echo '<tr><th scope="row"><label for="ph-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>'
				. '<td><input class="regular-text" type="' . esc_attr( $type ) . '" id="ph-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" /></td></tr>';
		}
		echo '</tbody></table>';
		submit_button( __( 'Save organisation details', 'poolhall-integration' ) );
		echo '</form>';
	}

	// -------------------------------------------------------- job checks --

	private function render_job_table(): void {
		$ids = $this->live_job_ids();

		echo '<h2>' . esc_html__( 'Live roles', 'poolhall-integration' ) . '</h2>';
		if ( array() === $ids ) {
			echo '<p>' . esc_html__( 'No live roles right now.', 'poolhall-integration' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>'
			. '<th>' . esc_html__( 'Role', 'poolhall-integration' ) . '</th>'
			. '<th style="width:130px">' . esc_html__( 'Google Jobs', 'poolhall-integration' ) . '</th>'
			. '<th>' . esc_html__( 'What to fix', 'poolhall-integration' ) . '</th>'
			. '<th style="width:150px">' . esc_html__( 'Check', 'poolhall-integration' ) . '</th>'
			. '</tr></thead><tbody>';

		foreach ( $ids as $id ) {
			$id       = (int) $id;
			$problems = $this->problems_for( $id );
			$notes    = $this->recommendations_for( $id );
			$url      = (string) get_permalink( $id );

			echo '<tr><td><strong>' . esc_html( get_the_title( $id ) ) . '</strong><br />'
				. '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'poolhall-integration' ) . '</a></td>';

			echo '<td>' . ( array() === $problems
				? '<span style="color:#046b2d;font-weight:600">' . esc_html__( 'Listed', 'poolhall-integration' ) . '</span>'
				: '<span style="color:#b32d2e;font-weight:600">' . esc_html__( 'Not listed', 'poolhall-integration' ) . '</span>' ) . '</td>';

			echo '<td>';
			if ( array() !== $problems ) {
				echo '<ul style="margin:0 0 6px;list-style:disc;padding-left:18px">';
				foreach ( $problems as $problem ) {
					echo '<li>' . esc_html( $problem ) . '</li>';
				}
				echo '</ul>';
			}
			if ( array() !== $notes ) {
				echo '<details><summary>' . esc_html__( 'Optional improvements', 'poolhall-integration' ) . '</summary><ul style="margin:6px 0 0;list-style:disc;padding-left:18px">';
				foreach ( $notes as $note ) {
					echo '<li>' . esc_html( $note ) . '</li>';
				}
				echo '</ul></details>';
			}
			if ( array() === $problems && array() === $notes ) {
				echo '&mdash;';
			}
			echo '</td>';

			echo '<td><a class="button button-small" target="_blank" rel="noopener" href="'
				. esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( $url ) )
				. '">' . esc_html__( 'Test with Google', 'poolhall-integration' ) . '</a></td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Published, unexpired roles — exactly the set that can be listed.
	 *
	 * @return int[]
	 */
	private function live_job_ids(): array {
		$ids = get_posts(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return array_values(
			array_filter(
				array_map( 'intval', is_array( $ids ) ? $ids : array() ),
				static fn( int $id ): bool => ! JobFromPost::is_expired( $id )
			)
		);
	}

	/** @return string[] */
	private function problems_for( int $post_id ): array {
		$job = JobFromPost::build( $post_id );
		if ( null === $job ) {
			return array( __( 'This role is missing its Giig reference, so it cannot be published to Google.', 'poolhall-integration' ) );
		}
		if ( null === JobFromPost::expires_at( $post_id ) ) {
			return array( __( 'This role has no closing date. Google requires one; re-run the job sync.', 'poolhall-integration' ) );
		}
		return $this->generator()->problems( $job );
	}

	/** @return string[] */
	private function recommendations_for( int $post_id ): array {
		$job = JobFromPost::build( $post_id );
		return null === $job ? array() : $this->generator()->recommendations( $job );
	}

	private function generator(): JobPostingSchema {
		return new JobPostingSchema(
			$this->options->hiring_org_name(),
			$this->options->hiring_org_url(),
			$this->options->hiring_org_logo()
		);
	}
}
