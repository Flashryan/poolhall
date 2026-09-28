<?php
/**
 * wp-admin screens: overview, share links, people, Trash and settings.
 *
 * Forms post to admin-post.php and call the same classes as the REST API.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Admin;

use MNA\Feedback\Abilities;
use MNA\Feedback\Access\Auth;
use MNA\Feedback\Access\Links;
use MNA\Feedback\Access\Sessions;
use MNA\Feedback\Capabilities;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Replies;
use MNA\Feedback\Data\Reviewers;
use MNA\Feedback\Export;
use MNA\Feedback\Formatter;
use MNA\Feedback\Installer;
use MNA\Feedback\Rest\AdminController;
use MNA\Feedback\Settings;
use MNA\Feedback\Team;
use MNA\Feedback\Url;
use MNA\Feedback\Workflow;

defined( 'ABSPATH' ) || exit;

final class Admin {

	private const PAGES = array(
		'mna-feedback'          => 'overview',
		'mna-feedback-links'    => 'links',
		'mna-feedback-people'   => 'people',
		'mna-feedback-trash'    => 'trash',
		'mna-feedback-settings' => 'settings',
	);

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_post_mnafb_admin', array( self::class, 'handle' ) );
		add_action( 'admin_post_mnafb_export', array( self::class, 'download' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MNAFB_FILE ), array( self::class, 'plugin_links' ) );
	}

	public static function menu(): void {
		add_menu_page(
			__( 'Feedback', 'mna-feedback' ),
			__( 'Feedback', 'mna-feedback' ),
			Capabilities::REVIEW,
			'mna-feedback',
			array( self::class, 'render' ),
			'dashicons-format-chat',
			58
		);
		add_submenu_page( 'mna-feedback', __( 'Feedback overview', 'mna-feedback' ), __( 'Overview', 'mna-feedback' ), Capabilities::REVIEW, 'mna-feedback', array( self::class, 'render' ) );
		add_submenu_page( 'mna-feedback', __( 'Share links', 'mna-feedback' ), __( 'Share links', 'mna-feedback' ), Capabilities::MANAGE, 'mna-feedback-links', array( self::class, 'render' ) );
		add_submenu_page( 'mna-feedback', __( 'People', 'mna-feedback' ), __( 'People', 'mna-feedback' ), Capabilities::MANAGE, 'mna-feedback-people', array( self::class, 'render' ) );
		add_submenu_page( 'mna-feedback', __( 'Feedback Trash', 'mna-feedback' ), __( 'Trash', 'mna-feedback' ), Capabilities::MANAGE, 'mna-feedback-trash', array( self::class, 'render' ) );
		add_submenu_page( 'mna-feedback', __( 'Feedback settings', 'mna-feedback' ), __( 'Settings', 'mna-feedback' ), Capabilities::MANAGE, 'mna-feedback-settings', array( self::class, 'render' ) );
	}

	public static function plugin_links( array $links ): array {
		if ( current_user_can( Capabilities::MANAGE ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mna-feedback-settings' ) ) . '">' . esc_html__( 'Settings', 'mna-feedback' ) . '</a>' );
		}
		return $links;
	}

	public static function assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( self::PAGES[ $page ] ) ) {
			return;
		}
		wp_enqueue_style( 'mna-feedback-admin', MNAFB_URL . 'assets/admin.css', array(), MNAFB_VERSION );
		wp_enqueue_script( 'mna-feedback-admin', MNAFB_URL . 'assets/admin.js', array(), MNAFB_VERSION, true );
		wp_localize_script(
			'mna-feedback-admin',
			'mnafbAdmin',
			array(
				'copied'      => __( 'Copied', 'mna-feedback' ),
				'copy'        => __( 'Copy', 'mna-feedback' ),
				'chooseLogo'  => __( 'Choose a logo', 'mna-feedback' ),
				'useLogo'     => __( 'Use this image', 'mna-feedback' ),
				'confirmRevoke' => __( 'Revoke this link? Everyone who joined through it loses access straight away.', 'mna-feedback' ),
				'confirmRotate' => __( 'Replace this link? The current address stops working; people already reviewing keep access.', 'mna-feedback' ),
				'confirmPurge'  => __( 'Delete permanently? This cannot be undone.', 'mna-feedback' ),
				'confirmEmpty'  => __( 'Permanently delete everything in Trash? This cannot be undone.', 'mna-feedback' ),
				'confirmBlock'  => __( 'Remove this reviewer\'s access? Their comments stay on the board.', 'mna-feedback' ),
			)
		);
		if ( 'settings' === self::PAGES[ $page ] ) {
			wp_enqueue_media();
		}
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	public static function render(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'mna-feedback'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view = self::PAGES[ $page ] ?? 'overview';
		if ( 'overview' !== $view && ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Only feedback managers can open this page.', 'mna-feedback' ), 403 );
		}

		echo '<div class="wrap mnafb-admin">';
		echo '<h1 class="wp-heading-inline">' . esc_html( (string) Settings::get( 'display_name' ) ) . '</h1> ';
		echo '<a class="page-title-action" href="' . esc_url( add_query_arg( Links::QUERY_JOIN, 'on', home_url( '/' ) ) ) . '">' . esc_html__( 'Open review mode', 'mna-feedback' ) . '</a>';
		echo '<hr class="wp-header-end">';
		self::notices();
		self::tabs( $page );

		match ( $view ) {
			'links'    => self::view_links(),
			'people'   => self::view_people(),
			'trash'    => self::view_trash(),
			'settings' => self::view_settings(),
			default    => self::view_overview(),
		};
		echo '</div>';
	}

	private static function tabs( string $current ): void {
		$labels = array(
			'mna-feedback'          => __( 'Overview', 'mna-feedback' ),
			'mna-feedback-links'    => __( 'Share links', 'mna-feedback' ),
			'mna-feedback-people'   => __( 'People', 'mna-feedback' ),
			'mna-feedback-trash'    => __( 'Trash', 'mna-feedback' ),
			'mna-feedback-settings' => __( 'Settings', 'mna-feedback' ),
		);
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		echo '<nav class="nav-tab-wrapper mnafb-tabs" aria-label="' . esc_attr__( 'Feedback sections', 'mna-feedback' ) . '">';
		foreach ( $labels as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s"%s>%s</a>',
				esc_url( admin_url( 'admin.php?page=' . $slug ) ),
				$slug === $current ? ' nav-tab-active' : '',
				$slug === $current ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	private static function notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$notice = isset( $_GET['mnafb_notice'] ) ? sanitize_key( wp_unslash( $_GET['mnafb_notice'] ) ) : '';
		$error  = isset( $_GET['mnafb_error'] ) ? sanitize_text_field( wp_unslash( $_GET['mnafb_error'] ) ) : '';
		// phpcs:enable
		$messages = array(
			'link_created'   => __( 'Share link created. Copy it from the table below and send it to your reviewers.', 'mna-feedback' ),
			'link_updated'   => __( 'Link updated.', 'mna-feedback' ),
			'link_revoked'   => __( 'Link revoked. Everyone who joined through it has lost access.', 'mna-feedback' ),
			'link_rotated'   => __( 'Link replaced. Send the new address to anyone who still needs to join.', 'mna-feedback' ),
			'link_restored'  => __( 'Link switched back on.', 'mna-feedback' ),
			'team_updated'   => __( 'Team updated.', 'mna-feedback' ),
			'reviewer_saved' => __( 'Reviewer updated.', 'mna-feedback' ),
			'restored'       => __( 'Restored from Trash.', 'mna-feedback' ),
			'purged'         => __( 'Deleted permanently.', 'mna-feedback' ),
			'emptied'        => __( 'Trash emptied.', 'mna-feedback' ),
			'settings_saved' => __( 'Settings saved.', 'mna-feedback' ),
			'all_purged'     => __( 'All feedback records, screenshots and links for this site have been permanently deleted.', 'mna-feedback' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
		if ( '' !== $error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	private static function view_overview(): void {
		$counts = array(
			'open'        => Items::count( array( 'status' => array( 'open' ) ) ),
			'in_progress' => Items::count( array( 'status' => array( 'in_progress' ) ) ),
			'done'        => Items::count( array( 'status' => array( 'done' ) ) ),
		);
		$labels = array(
			'open'        => __( 'Open', 'mna-feedback' ),
			'in_progress' => __( 'In progress', 'mna-feedback' ),
			'done'        => __( 'Done', 'mna-feedback' ),
		);
		echo '<div class="mnafb-stats">';
		foreach ( $counts as $status => $count ) {
			printf( '<div class="mnafb-stat mnafb-stat--%1$s"><span class="mnafb-stat__n">%2$d</span><span class="mnafb-stat__l">%3$s</span></div>', esc_attr( $status ), (int) $count, esc_html( $labels[ $status ] ) );
		}
		echo '</div>';

		echo '<div class="mnafb-card">';
		echo '<h2>' . esc_html__( 'How it works', 'mna-feedback' ) . '</h2>';
		echo '<ol class="mnafb-steps">';
		if ( current_user_can( Capabilities::MANAGE ) ) {
			echo '<li>' . wp_kses( sprintf( /* translators: %s: link to Share links */ __( 'Create a share link on the %s tab and send it to your stakeholders. They enter their name once — no account needed.', 'mna-feedback' ), '<a href="' . esc_url( admin_url( 'admin.php?page=mna-feedback-links' ) ) . '">' . esc_html__( 'Share links', 'mna-feedback' ) . '</a>' ), array( 'a' => array( 'href' => array() ) ) ) . '</li>';
		}
		echo '<li>' . esc_html__( 'Reviewers switch to Comment mode, click any part of a page and describe the change. Each comment is pinned to the element it is about.', 'mna-feedback' ) . '</li>';
		echo '<li>' . esc_html__( 'Your team works through the board — Open, In progress, Done — replying, assigning and adding notes. Reviewers can reopen anything that is not right yet.', 'mna-feedback' ) . '</li>';
		echo '</ol>';
		echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( Links::QUERY_JOIN, 'on', home_url( '/' ) ) ) . '">' . esc_html__( 'Open review mode on the site', 'mna-feedback' ) . '</a></p>';
		echo '</div>';

		$recent = Items::query(
			array(
				'order' => 'updated',
				'limit' => 12,
			)
		);
		echo '<h2>' . esc_html__( 'Recently updated', 'mna-feedback' ) . '</h2>';
		if ( ! $recent ) {
			echo '<p class="description">' . esc_html__( 'No feedback yet.', 'mna-feedback' ) . '</p>';
			return;
		}
		$people = array();
		foreach ( $recent as $row ) {
			$people[] = (int) $row->author_id;
			$people[] = (int) $row->assignee_id;
		}
		Reviewers::get_many( $people );
		echo '<table class="widefat striped mnafb-table"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Feedback', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Status', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Page', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'From', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Assigned to', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Updated', 'mna-feedback' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $recent as $row ) {
			$author   = Reviewers::get( (int) $row->author_id );
			$assignee = Reviewers::get( (int) $row->assignee_id );
			$open_url = add_query_arg( Links::QUERY_JOIN, 'on', (string) $row->page_url ) . '#mnafb-item-' . (int) $row->id;
			echo '<tr>';
			echo '<td><a href="' . esc_url( $open_url ) . '"><strong>' . esc_html( (string) $row->title ) . '</strong></a>' . ( 'high' === $row->priority || 'urgent' === $row->priority ? ' <span class="mnafb-pill mnafb-pill--' . esc_attr( (string) $row->priority ) . '">' . esc_html( ucfirst( (string) $row->priority ) ) . '</span>' : '' ) . '</td>';
			echo '<td><span class="mnafb-status mnafb-status--' . esc_attr( (string) $row->status ) . '">' . esc_html( Export::status_label( (string) $row->status ) ) . '</span></td>';
			echo '<td>' . esc_html( (string) ( $row->page_title ?: self::short_url( (string) $row->page_url ) ) ) . '</td>';
			echo '<td>' . esc_html( $author ? (string) $author->display_name : '—' ) . '</td>';
			echo '<td>' . esc_html( $assignee ? (string) $assignee->display_name : '—' ) . '</td>';
			echo '<td>' . esc_html( self::ago( (string) $row->updated_at ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function view_links(): void {
		$links  = Links::all();
		$counts = Links::reviewer_counts();

		echo '<div class="mnafb-card">';
		echo '<h2>' . esc_html__( 'Create a share link', 'mna-feedback' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Anyone with the link can join as a reviewer after entering their name. Revoke or replace it at any time.', 'mna-feedback' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mnafb-form-grid">';
		self::form_fields( 'link_create' );
		echo '<p><label for="mnafb-label">' . esc_html__( 'Label', 'mna-feedback' ) . '</label><input type="text" id="mnafb-label" name="label" class="regular-text" maxlength="120" placeholder="' . esc_attr__( 'e.g. Client review — October', 'mna-feedback' ) . '"></p>';
		echo '<p><label for="mnafb-landing">' . esc_html__( 'Starting page', 'mna-feedback' ) . '</label><input type="url" id="mnafb-landing" name="landing_url" class="regular-text" placeholder="' . esc_attr( home_url( '/' ) ) . '"><span class="description">' . esc_html__( 'Optional. Where reviewers land when they open the link.', 'mna-feedback' ) . '</span></p>';
		echo '<p><label for="mnafb-expires">' . esc_html__( 'Expires', 'mna-feedback' ) . '</label><input type="date" id="mnafb-expires" name="expires_at" min="' . esc_attr( wp_date( 'Y-m-d' ) ) . '"><span class="description">' . esc_html__( 'Optional. The link stops working at the end of this day.', 'mna-feedback' ) . '</span></p>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Create link', 'mna-feedback' ) . '</button></p>';
		echo '</form></div>';

		echo '<h2>' . esc_html__( 'Share links', 'mna-feedback' ) . '</h2>';
		if ( ! $links ) {
			echo '<p class="description">' . esc_html__( 'No links yet.', 'mna-feedback' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped mnafb-table mnafb-links"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Label', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Link', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Status', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Reviewers', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Expires', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Actions', 'mna-feedback' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $links as $link ) {
			$status = Links::status( $link );
			$url    = Links::url( $link );
			$id     = (int) $link->id;
			echo '<tr>';
			echo '<td><strong>' . esc_html( (string) $link->label ) . '</strong><br><span class="description">' . esc_html( sprintf( /* translators: 1: date, 2: number of visits */ __( 'Created %1$s · %2$d visits', 'mna-feedback' ), self::date( (string) $link->created_at ), (int) $link->use_count ) ) . '</span></td>';
			echo '<td class="mnafb-copy-cell">';
			if ( $url ) {
				echo '<input type="text" readonly class="mnafb-copy-input" value="' . esc_attr( $url ) . '" aria-label="' . esc_attr__( 'Share link address', 'mna-feedback' ) . '"> <button type="button" class="button mnafb-copy" data-copy="' . esc_attr( $url ) . '">' . esc_html__( 'Copy', 'mna-feedback' ) . '</button>';
			} else {
				echo '<span class="description">' . esc_html__( 'Address unavailable (security keys changed). Replace the link to get a new one.', 'mna-feedback' ) . '</span>';
			}
			echo '</td>';
			echo '<td><span class="mnafb-status mnafb-status--link-' . esc_attr( $status ) . '">' . esc_html( self::link_status_label( $status ) ) . '</span></td>';
			echo '<td>' . (int) ( $counts[ $id ] ?? 0 ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mnafb-inline-form">';
			self::form_fields( 'link_update' );
			echo '<input type="hidden" name="id" value="' . (int) $id . '">';
			$local = $link->expires_at ? get_date_from_gmt( (string) $link->expires_at, 'Y-m-d' ) : '';
			echo '<label class="screen-reader-text" for="mnafb-exp-' . (int) $id . '">' . esc_html__( 'Expiry date', 'mna-feedback' ) . '</label>';
			echo '<input type="date" id="mnafb-exp-' . (int) $id . '" name="expires_at" value="' . esc_attr( $local ) . '"> ';
			echo '<button type="submit" class="button button-small">' . esc_html__( 'Save', 'mna-feedback' ) . '</button>';
			echo '</form>';
			echo '</td>';
			echo '<td class="mnafb-actions">';
			self::action_button( 'link_rotate', array( 'id' => $id ), __( 'Replace link', 'mna-feedback' ), 'confirmRotate' );
			if ( 'revoked' === $status ) {
				self::action_button( 'link_reactivate', array( 'id' => $id ), __( 'Switch back on', 'mna-feedback' ) );
			} else {
				self::action_button( 'link_revoke', array( 'id' => $id ), __( 'Revoke', 'mna-feedback' ), 'confirmRevoke', 'button-link-delete' );
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function view_people(): void {
		$members = Team::members();
		echo '<div class="mnafb-card">';
		echo '<h2>' . esc_html__( 'Team', 'mna-feedback' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'WordPress users who work on feedback. Implementers triage, assign and move cards; managers also control links, people, settings, exports and Trash. Administrators are always managers.', 'mna-feedback' ) . '</p>';
		echo '<table class="widefat striped mnafb-table"><thead><tr><th scope="col">' . esc_html__( 'Name', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Email', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Feedback role', 'mna-feedback' ) . '</th></tr></thead><tbody>';
		foreach ( $members as $member ) {
			echo '<tr><td><strong>' . esc_html( $member['name'] ) . '</strong><br><span class="description">' . esc_html( $member['login'] ) . '</span></td><td>' . esc_html( $member['email'] ) . '</td><td>';
			if ( 'manager' === $member['locked_role'] ) {
				echo esc_html__( 'Manager', 'mna-feedback' ) . ' <span class="description">(' . esc_html__( 'from WordPress role', 'mna-feedback' ) . ')</span>';
			} else {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mnafb-inline-form">';
				self::form_fields( 'team_set' );
				echo '<input type="hidden" name="user_id" value="' . (int) $member['user_id'] . '">';
				self::role_select( 'mnafb-role-' . (int) $member['user_id'], (string) $member['role'], $member['locked_role'] );
				echo ' <button type="submit" class="button button-small">' . esc_html__( 'Save', 'mna-feedback' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Add a team member', 'mna-feedback' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mnafb-inline-form">';
		self::form_fields( 'team_add' );
		echo '<label for="mnafb-user">' . esc_html__( 'Username or email', 'mna-feedback' ) . '</label> <input type="text" id="mnafb-user" name="user" class="regular-text" required> ';
		self::role_select( 'mnafb-new-role', 'implementer', null, false );
		echo ' <button type="submit" class="button">' . esc_html__( 'Add', 'mna-feedback' ) . '</button>';
		echo '</form>';
		echo '</div>';

		$counts    = Reviewers::item_counts();
		$reviewers = array_filter( Reviewers::all(), static fn( $r ) => 'guest' === $r->type );
		echo '<h2>' . esc_html__( 'Reviewers', 'mna-feedback' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'People who joined through a share link. Names are self-reported; each person has their own identity even if two people use the same name.', 'mna-feedback' ) . '</p>';
		if ( ! $reviewers ) {
			echo '<p class="description">' . esc_html__( 'Nobody has joined yet.', 'mna-feedback' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped mnafb-table"><thead><tr><th scope="col">' . esc_html__( 'Name', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Email', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Joined through', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Comments', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Last seen', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Access', 'mna-feedback' ) . '</th></tr></thead><tbody>';
		foreach ( $reviewers as $reviewer ) {
			$link    = (int) $reviewer->link_id ? Links::get( (int) $reviewer->link_id ) : null;
			$blocked = 'active' !== $reviewer->status;
			echo '<tr>';
			echo '<td><span class="mnafb-avatar" style="background:' . esc_attr( (string) $reviewer->color ) . '" aria-hidden="true">' . esc_html( Formatter::initials( (string) $reviewer->display_name ) ) . '</span> <strong>' . esc_html( (string) $reviewer->display_name ) . '</strong></td>';
			echo '<td>' . esc_html( (string) $reviewer->email ?: '—' ) . '</td>';
			echo '<td>' . esc_html( $link ? (string) $link->label : '—' ) . '</td>';
			echo '<td>' . (int) ( $counts[ (int) $reviewer->id ] ?? 0 ) . '</td>';
			echo '<td>' . esc_html( self::ago( (string) $reviewer->last_seen_at ) ) . '</td>';
			echo '<td>';
			if ( $blocked ) {
				echo '<span class="mnafb-status mnafb-status--link-revoked">' . esc_html__( 'Removed', 'mna-feedback' ) . '</span> ';
				self::action_button( 'reviewer_status', array( 'id' => (int) $reviewer->id, 'status' => 'active' ), __( 'Restore access', 'mna-feedback' ) );
			} else {
				self::action_button( 'reviewer_status', array( 'id' => (int) $reviewer->id, 'status' => 'blocked' ), __( 'Remove access', 'mna-feedback' ), 'confirmBlock', 'button-link-delete' );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function view_trash(): void {
		$items   = Items::query(
			array(
				'trashed' => true,
				'order'   => 'updated',
				'limit'   => 500,
			)
		);
		$replies = Replies::trashed( 500 );

		echo '<p class="description">' . esc_html__( 'Deleted feedback and replies stay here until a manager restores them or deletes them permanently.', 'mna-feedback' ) . '</p>';
		if ( $items || $replies ) {
			echo '<p>';
			self::action_button( 'trash_empty', array(), __( 'Empty Trash', 'mna-feedback' ), 'confirmEmpty', 'button-link-delete' );
			echo '</p>';
		}

		echo '<h2>' . esc_html__( 'Feedback', 'mna-feedback' ) . '</h2>';
		if ( ! $items ) {
			echo '<p class="description">' . esc_html__( 'No deleted feedback.', 'mna-feedback' ) . '</p>';
		} else {
			$people = array();
			foreach ( $items as $row ) {
				$people[] = (int) $row->author_id;
				$people[] = (int) $row->deleted_by;
			}
			Reviewers::get_many( $people );
			echo '<table class="widefat striped mnafb-table"><thead><tr><th scope="col">' . esc_html__( 'Feedback', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Page', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'From', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Deleted', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Actions', 'mna-feedback' ) . '</th></tr></thead><tbody>';
			foreach ( $items as $row ) {
				$author  = Reviewers::get( (int) $row->author_id );
				$deleter = Reviewers::get( (int) $row->deleted_by );
				echo '<tr><td><strong>' . esc_html( (string) $row->title ) . '</strong><br><span class="description">' . esc_html( wp_trim_words( (string) $row->body, 20 ) ) . '</span></td>';
				echo '<td>' . esc_html( (string) ( $row->page_title ?: self::short_url( (string) $row->page_url ) ) ) . '</td>';
				echo '<td>' . esc_html( $author ? (string) $author->display_name : '—' ) . '</td>';
				echo '<td>' . esc_html( self::ago( (string) $row->deleted_at ) ) . ( $deleter ? '<br><span class="description">' . esc_html( sprintf( /* translators: %s: name */ __( 'by %s', 'mna-feedback' ), (string) $deleter->display_name ) ) . '</span>' : '' ) . '</td>';
				echo '<td class="mnafb-actions">';
				self::action_button( 'item_restore', array( 'id' => (int) $row->id ), __( 'Restore', 'mna-feedback' ) );
				self::action_button( 'item_purge', array( 'id' => (int) $row->id ), __( 'Delete permanently', 'mna-feedback' ), 'confirmPurge', 'button-link-delete' );
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2>' . esc_html__( 'Replies', 'mna-feedback' ) . '</h2>';
		if ( ! $replies ) {
			echo '<p class="description">' . esc_html__( 'No deleted replies.', 'mna-feedback' ) . '</p>';
			return;
		}
		Reviewers::get_many( array_map( static fn( $r ) => (int) $r->author_id, $replies ) );
		echo '<table class="widefat striped mnafb-table"><thead><tr><th scope="col">' . esc_html__( 'Reply', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'On', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'From', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Deleted', 'mna-feedback' ) . '</th><th scope="col">' . esc_html__( 'Actions', 'mna-feedback' ) . '</th></tr></thead><tbody>';
		foreach ( $replies as $reply ) {
			$item   = Items::get( (int) $reply->item_id );
			$author = Reviewers::get( (int) $reply->author_id );
			echo '<tr><td>' . esc_html( wp_trim_words( (string) $reply->body, 30 ) ) . '</td>';
			echo '<td>' . esc_html( $item ? (string) $item->title : '—' ) . ( $item && $item->deleted_at ? ' <span class="description">(' . esc_html__( 'in Trash', 'mna-feedback' ) . ')</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $author ? (string) $author->display_name : '—' ) . '</td>';
			echo '<td>' . esc_html( self::ago( (string) $reply->deleted_at ) ) . '</td>';
			echo '<td class="mnafb-actions">';
			self::action_button( 'reply_restore', array( 'id' => (int) $reply->id ), __( 'Restore', 'mna-feedback' ) );
			self::action_button( 'reply_purge', array( 'id' => (int) $reply->id ), __( 'Delete permanently', 'mna-feedback' ), 'confirmPurge', 'button-link-delete' );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function view_settings(): void {
		$s    = Settings::all();
		$logo = (int) $s['logo_id'] ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::form_fields( 'settings' );

		echo '<div class="mnafb-card"><h2>' . esc_html__( 'Branding', 'mna-feedback' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="mnafb-name">' . esc_html__( 'Display name', 'mna-feedback' ) . '</label></th><td><input type="text" id="mnafb-name" name="display_name" class="regular-text" maxlength="60" value="' . esc_attr( (string) $s['display_name'] ) . '"><p class="description">' . esc_html__( 'Shown at the top of the review panel and in the toolbar.', 'mna-feedback' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Logo', 'mna-feedback' ) . '</th><td><div class="mnafb-logo-field"><img class="mnafb-logo-preview" src="' . esc_url( (string) $logo ) . '" alt=""' . ( $logo ? '' : ' hidden' ) . '><input type="hidden" name="logo_id" value="' . (int) $s['logo_id'] . '"> <button type="button" class="button mnafb-logo-choose">' . esc_html__( 'Choose logo', 'mna-feedback' ) . '</button> <button type="button" class="button-link mnafb-logo-remove"' . ( $logo ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'mna-feedback' ) . '</button></div><p class="description">' . esc_html__( 'A small square or wide logo works best.', 'mna-feedback' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="mnafb-accent">' . esc_html__( 'Accent colour', 'mna-feedback' ) . '</label></th><td><input type="color" id="mnafb-accent" name="accent" value="' . esc_attr( (string) $s['accent'] ) . '"> <code class="mnafb-accent-value">' . esc_html( (string) $s['accent'] ) . '</code><p class="description">' . esc_html__( 'Used for buttons, pins and highlights. Pick a colour dark enough for white text.', 'mna-feedback' ) . '</p></td></tr>';
		echo '</tbody></table></div>';

		echo '<div class="mnafb-card"><h2>' . esc_html__( 'Review tool', 'mna-feedback' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		self::checkbox_row( 'enabled', __( 'Review tool', 'mna-feedback' ), __( 'Switched on (turn off to hide the review tool for everyone without deleting anything)', 'mna-feedback' ), (bool) $s['enabled'] );
		self::checkbox_row( 'page_comments', __( 'Page comments', 'mna-feedback' ), __( 'Allow general comments about a whole page, not tied to one element', 'mna-feedback' ), (bool) $s['page_comments'] );
		self::checkbox_row( 'guest_uploads', __( 'Screenshots', 'mna-feedback' ), __( 'Let reviewers attach screenshots (PNG, JPEG or WebP, up to 2 MB)', 'mna-feedback' ), (bool) $s['guest_uploads'] );
		echo '<tr><th scope="row"><label for="mnafb-poll">' . esc_html__( 'Refresh every', 'mna-feedback' ) . '</label></th><td><input type="number" id="mnafb-poll" name="poll_interval" min="5" max="120" value="' . (int) $s['poll_interval'] . '" class="small-text"> ' . esc_html__( 'seconds while the review tool is open and visible', 'mna-feedback' ) . '</td></tr>';
		echo '<tr><th scope="row"><label for="mnafb-days">' . esc_html__( 'Reviewers stay signed in for', 'mna-feedback' ) . '</label></th><td><input type="number" id="mnafb-days" name="session_days" min="1" max="365" value="' . (int) $s['session_days'] . '" class="small-text"> ' . esc_html__( 'days after their last visit', 'mna-feedback' ) . '</td></tr>';
		echo '</tbody></table></div>';

		echo '<div class="mnafb-card"><h2>' . esc_html__( 'AI agents (Novamira)', 'mna-feedback' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		if ( Abilities::available() ) {
			self::checkbox_row( 'abilities_enabled', __( 'Abilities', 'mna-feedback' ), __( 'Let AI agents signed in as a team member list and read feedback, add implementation notes, assign items and change their status. Agents cannot change site content through these abilities, and everything they do appears in each item\'s history.', 'mna-feedback' ), (bool) $s['abilities_enabled'] );
		} else {
			echo '<tr><th scope="row">' . esc_html__( 'Abilities', 'mna-feedback' ) . '</th><td><p class="description">' . esc_html__( 'Needs WordPress 6.9 or newer (the Abilities API). This site does not have it, so the integration is unavailable.', 'mna-feedback' ) . '</p><input type="hidden" name="abilities_enabled" value="0"></td></tr>';
		}
		echo '</tbody></table></div>';

		echo '<div class="mnafb-card"><h2>' . esc_html__( 'Data', 'mna-feedback' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		self::checkbox_row( 'purge_on_uninstall', __( 'When uninstalling', 'mna-feedback' ), __( 'Permanently delete all feedback, screenshots and links when the plugin is deleted. Leave unticked to keep records (recommended).', 'mna-feedback' ), (bool) $s['purge_on_uninstall'] );
		echo '</tbody></table></div>';

		submit_button( __( 'Save settings', 'mna-feedback' ) );
		echo '</form>';

		echo '<div class="mnafb-card"><h2>' . esc_html__( 'Export', 'mna-feedback' ) . '</h2><p class="description">' . esc_html__( 'Download every feedback item on this site, including Trash. JSON includes replies, notes and full history.', 'mna-feedback' ) . '</p><p>';
		foreach ( array( 'csv' => __( 'Download CSV', 'mna-feedback' ), 'json' => __( 'Download JSON', 'mna-feedback' ) ) as $format => $label ) {
			echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mnafb_export&format=' . $format ), 'mnafb_export' ) ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '</p></div>';

		echo '<div class="mnafb-card mnafb-danger"><h2>' . esc_html__( 'Delete all feedback data', 'mna-feedback' ) . '</h2>';
		echo '<p>' . esc_html__( 'Permanently deletes every feedback item, reply, screenshot, reviewer identity and share link on this site. Settings are kept. This cannot be undone — export first if you might need the records.', 'mna-feedback' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mnafb-inline-form">';
		self::form_fields( 'purge_all' );
		/* translators: %s: confirmation phrase */
		echo '<label for="mnafb-confirm">' . esc_html( sprintf( __( 'Type %s to confirm', 'mna-feedback' ), AdminController::PURGE_PHRASE ) ) . '</label> <input type="text" id="mnafb-confirm" name="confirm" class="regular-text" autocomplete="off" required> ';
		echo '<button type="submit" class="button button-link-delete">' . esc_html__( 'Delete everything', 'mna-feedback' ) . '</button>';
		echo '</form></div>';
	}

	/* ---------------------------------------------------------------------
	 * Form helpers
	 * ------------------------------------------------------------------ */

	private static function form_fields( string $op ): void {
		echo '<input type="hidden" name="action" value="mnafb_admin"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
		wp_nonce_field( 'mnafb_admin_' . $op );
	}

	private static function action_button( string $op, array $fields, string $label, string $confirm = '', string $class = '' ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mnafb-action-form"' . ( $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
		self::form_fields( $op );
		foreach ( $fields as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		echo '<button type="submit" class="button button-small ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function role_select( string $id, string $current, ?string $locked, bool $allow_none = true ): void {
		$roles = array(
			'manager'     => __( 'Manager', 'mna-feedback' ),
			'implementer' => __( 'Implementer', 'mna-feedback' ),
			'reviewer'    => __( 'Reviewer', 'mna-feedback' ),
		);
		$rank  = array(
			'reviewer'    => 1,
			'implementer' => 2,
			'manager'     => 3,
		);
		echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Feedback role', 'mna-feedback' ) . '</label>';
		echo '<select id="' . esc_attr( $id ) . '" name="role">';
		foreach ( $roles as $value => $label ) {
			$disabled = $locked && $rank[ $value ] < $rank[ $locked ];
			printf( '<option value="%s"%s%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), disabled( $disabled, true, false ), esc_html( $label ) );
		}
		if ( $allow_none && ! $locked ) {
			printf( '<option value="none">%s</option>', esc_html__( 'Remove from team', 'mna-feedback' ) );
		}
		echo '</select>';
	}

	private static function checkbox_row( string $name, string $label, string $description, bool $checked ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . '> ' . esc_html( $description ) . '</label></td></tr>';
	}

	/* ---------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	public static function handle(): void {
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		check_admin_referer( 'mnafb_admin_' . $op );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Only feedback managers can do that.', 'mna-feedback' ), 403 );
		}
		$actor = Auth::for_user( wp_get_current_user() );
		if ( ! $actor ) {
			wp_die( esc_html__( 'Only feedback managers can do that.', 'mna-feedback' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$page = 'mna-feedback';

		switch ( $op ) {
			case 'link_create':
			case 'link_update':
				$page    = 'mna-feedback-links';
				$request = new \WP_REST_Request( 'POST' );
				foreach ( array( 'label', 'landing_url', 'expires_at' ) as $field ) {
					if ( isset( $_POST[ $field ] ) ) {
						$request->set_param( $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
					}
				}
				$fields = AdminController::link_fields( $request, 'link_create' === $op );
				if ( is_wp_error( $fields ) ) {
					self::back( $page, '', $fields->get_error_message() );
				}
				if ( 'link_create' === $op ) {
					Links::create( $fields['label'], $fields['expires_at'], $fields['landing_url'], get_current_user_id() );
					self::back( $page, 'link_created' );
				}
				if ( Links::get( $id ) ) {
					Links::update( $id, $fields );
				}
				self::back( $page, 'link_updated' );
				break;

			case 'link_revoke':
				Links::revoke( $id );
				self::back( 'mna-feedback-links', 'link_revoked' );
				break;

			case 'link_rotate':
				Links::rotate( $id );
				self::back( 'mna-feedback-links', 'link_rotated' );
				break;

			case 'link_reactivate':
				Links::reactivate( $id );
				self::back( 'mna-feedback-links', 'link_restored' );
				break;

			case 'team_set':
			case 'team_add':
				$page = 'mna-feedback-people';
				$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
				if ( 'team_add' === $op ) {
					$login = isset( $_POST['user'] ) ? sanitize_text_field( wp_unslash( $_POST['user'] ) ) : '';
					$user  = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
					if ( ! $user ) {
						self::back( $page, '', __( 'No WordPress user has that username or email address.', 'mna-feedback' ) );
					}
					$user_id = (int) $user->ID;
				} else {
					$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
				}
				$result = Team::set_role( $user_id, 'none' === $role ? null : $role, get_current_user_id() );
				if ( is_wp_error( $result ) ) {
					self::back( $page, '', $result->get_error_message() );
				}
				self::back( $page, 'team_updated' );
				break;

			case 'reviewer_status':
				$status   = isset( $_POST['status'] ) && 'blocked' === $_POST['status'] ? 'blocked' : 'active';
				$reviewer = Reviewers::get( $id );
				if ( $reviewer && 'guest' === $reviewer->type ) {
					Reviewers::update( $id, array( 'status' => $status ) );
					if ( 'blocked' === $status ) {
						Sessions::revoke_for_reviewer( $id );
					}
				}
				self::back( 'mna-feedback-people', 'reviewer_saved' );
				break;

			case 'item_restore':
				$result = Workflow::restore_item( $actor, $id );
				self::back( 'mna-feedback-trash', is_wp_error( $result ) ? '' : 'restored', is_wp_error( $result ) ? $result->get_error_message() : '' );
				break;

			case 'item_purge':
				$result = Workflow::purge_item( $actor, $id );
				self::back( 'mna-feedback-trash', is_wp_error( $result ) ? '' : 'purged', is_wp_error( $result ) ? $result->get_error_message() : '' );
				break;

			case 'reply_restore':
				$result = Workflow::restore_reply( $actor, $id );
				self::back( 'mna-feedback-trash', is_wp_error( $result ) ? '' : 'restored', is_wp_error( $result ) ? $result->get_error_message() : '' );
				break;

			case 'reply_purge':
				$result = Workflow::purge_reply( $actor, $id );
				self::back( 'mna-feedback-trash', is_wp_error( $result ) ? '' : 'purged', is_wp_error( $result ) ? $result->get_error_message() : '' );
				break;

			case 'trash_empty':
				foreach ( Replies::trashed( 5000 ) as $reply ) {
					Workflow::purge_reply( $actor, (int) $reply->id );
				}
				do {
					$batch = Items::query(
						array(
							'trashed' => true,
							'limit'   => 200,
						)
					);
					foreach ( $batch as $item ) {
						Workflow::purge_item( $actor, (int) $item->id );
					}
				} while ( count( $batch ) === 200 );
				self::back( 'mna-feedback-trash', 'emptied' );
				break;

			case 'settings':
				$input = array();
				foreach ( array_keys( Settings::DEFAULTS ) as $key ) {
					if ( isset( $_POST[ $key ] ) ) {
						$input[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
					}
				}
				Settings::update( $input );
				self::back( 'mna-feedback-settings', 'settings_saved' );
				break;

			case 'purge_all':
				$confirm = isset( $_POST['confirm'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) ) : '';
				if ( AdminController::PURGE_PHRASE !== $confirm ) {
					/* translators: %s: confirmation phrase */
					self::back( 'mna-feedback-settings', '', sprintf( __( 'Nothing was deleted. Type %s exactly to confirm.', 'mna-feedback' ), AdminController::PURGE_PHRASE ) );
				}
				Installer::purge_site_data( true );
				self::back( 'mna-feedback-settings', 'all_purged' );
				break;
		}
		// phpcs:enable
		self::back( $page );
	}

	public static function download(): void {
		check_admin_referer( 'mnafb_export' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Only feedback managers can export feedback.', 'mna-feedback' ), 403 );
		}
		$format = isset( $_GET['format'] ) && 'csv' === $_GET['format'] ? 'csv' : 'json';
		nocache_headers();
		header( 'Content-Type: ' . ( 'csv' === $format ? 'text/csv' : 'application/json' ) . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . Export::filename( $format ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo 'csv' === $format ? Export::csv() : Export::json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- File download.
		exit;
	}

	private static function back( string $page, string $notice = '', string $error = '' ): never {
		$url = admin_url( 'admin.php?page=' . $page );
		if ( $notice ) {
			$url = add_query_arg( 'mnafb_notice', $notice, $url );
		}
		if ( $error ) {
			$url = add_query_arg( 'mnafb_error', rawurlencode( $error ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Formatting
	 * ------------------------------------------------------------------ */

	private static function ago( string $mysql ): string {
		if ( '' === $mysql || str_starts_with( $mysql, '0000' ) ) {
			return '—';
		}
		$ts = strtotime( $mysql . ' UTC' );
		if ( ! $ts ) {
			return '—';
		}
		if ( time() - $ts < 7 * DAY_IN_SECONDS ) {
			/* translators: %s: human time difference */
			return sprintf( __( '%s ago', 'mna-feedback' ), human_time_diff( $ts ) );
		}
		return wp_date( get_option( 'date_format' ), $ts );
	}

	private static function date( string $mysql ): string {
		$ts = strtotime( $mysql . ' UTC' );
		return $ts ? wp_date( get_option( 'date_format' ), $ts ) : '—';
	}

	private static function short_url( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === $path ? '/' : $path;
	}

	private static function link_status_label( string $status ): string {
		return match ( $status ) {
			'revoked' => __( 'Revoked', 'mna-feedback' ),
			'expired' => __( 'Expired', 'mna-feedback' ),
			default   => __( 'Active', 'mna-feedback' ),
		};
	}
}
