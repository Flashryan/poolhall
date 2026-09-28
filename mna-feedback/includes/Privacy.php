<?php
/**
 * WordPress privacy tools: personal data export and erasure by email
 * address, plus suggested privacy policy text.
 *
 * Erasure anonymises the person's identity (name, email, return link and
 * sessions). The feedback text itself is kept as project records unless the
 * mnafb_privacy_erase_content filter returns true, in which case their
 * comments, replies and screenshots are removed as well.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

use MNA\Feedback\Access\Sessions;
use MNA\Feedback\Data\Attachments;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Replies;
use MNA\Feedback\Data\Reviewers;

defined( 'ABSPATH' ) || exit;

final class Privacy {

	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
		add_action( 'admin_init', array( self::class, 'policy_text' ) );
	}

	public static function register_exporter( array $exporters ): array {
		$exporters['mna-feedback'] = array(
			'exporter_friendly_name' => __( 'Site feedback', 'mna-feedback' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( array $erasers ): array {
		$erasers['mna-feedback'] = array(
			'eraser_friendly_name' => __( 'Site feedback', 'mna-feedback' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * @return object[]
	 */
	private static function identities( string $email ): array {
		$email = trim( $email );
		if ( '' === $email ) {
			return array();
		}
		$found = array();
		foreach ( Reviewers::find_by_email( $email ) as $row ) {
			$found[ (int) $row->id ] = $row;
		}
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			global $wpdb;
			$table = Schema::table( 'reviewers' );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE type = 'wp_user' AND wp_user_id = %d", $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $row ) {
				$found[ (int) $row->id ] = $row;
			}
		}
		return array_values( $found );
	}

	public static function export( string $email, int $page = 1 ): array {
		$data = array();
		foreach ( self::identities( $email ) as $reviewer ) {
			$data[] = array(
				'group_id'    => 'mna-feedback-identity',
				'group_label' => __( 'Feedback identity', 'mna-feedback' ),
				'item_id'     => 'mnafb-reviewer-' . $reviewer->id,
				'data'        => array(
					array(
						'name'  => __( 'Name', 'mna-feedback' ),
						'value' => (string) $reviewer->display_name,
					),
					array(
						'name'  => __( 'Email', 'mna-feedback' ),
						'value' => (string) $reviewer->email,
					),
					array(
						'name'  => __( 'Joined', 'mna-feedback' ),
						'value' => (string) $reviewer->created_at . ' UTC',
					),
					array(
						'name'  => __( 'Last seen', 'mna-feedback' ),
						'value' => (string) $reviewer->last_seen_at . ' UTC',
					),
				),
			);

			foreach ( Items::query(
				array(
					'author_id' => (int) $reviewer->id,
					'trashed'   => 'any',
					'order'     => 'oldest',
					'limit'     => 1000,
				)
			) as $item ) {
				$data[] = array(
					'group_id'    => 'mna-feedback-items',
					'group_label' => __( 'Feedback comments', 'mna-feedback' ),
					'item_id'     => 'mnafb-item-' . $item->id,
					'data'        => array(
						array(
							'name'  => __( 'Title', 'mna-feedback' ),
							'value' => (string) $item->title,
						),
						array(
							'name'  => __( 'Description', 'mna-feedback' ),
							'value' => (string) $item->body,
						),
						array(
							'name'  => __( 'Page', 'mna-feedback' ),
							'value' => (string) $item->page_url,
						),
						array(
							'name'  => __( 'Created', 'mna-feedback' ),
							'value' => (string) $item->created_at . ' UTC',
						),
					),
				);
			}

			global $wpdb;
			$table   = Replies::table();
			$replies = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE author_id = %d ORDER BY id ASC LIMIT 5000", $reviewer->id ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $replies as $reply ) {
				$data[] = array(
					'group_id'    => 'mna-feedback-replies',
					'group_label' => __( 'Feedback replies', 'mna-feedback' ),
					'item_id'     => 'mnafb-reply-' . $reply->id,
					'data'        => array(
						array(
							'name'  => __( 'Reply', 'mna-feedback' ),
							'value' => (string) $reply->body,
						),
						array(
							'name'  => __( 'Created', 'mna-feedback' ),
							'value' => (string) $reply->created_at . ' UTC',
						),
					),
				);
			}
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	public static function erase( string $email, int $page = 1 ): array {
		$identities    = self::identities( $email );
		$erase_content = (bool) apply_filters( 'mnafb_privacy_erase_content', false, $email );
		$removed       = false;
		$retained      = false;

		foreach ( $identities as $reviewer ) {
			global $wpdb;
			$id = (int) $reviewer->id;
			$wpdb->update(
				Schema::table( 'reviewers' ),
				array(
					'display_name' => __( 'Removed reviewer', 'mna-feedback' ),
					'email'        => '',
					'status'       => 'blocked',
					'return_hash'  => null,
					'return_enc'   => null,
				),
				array( 'id' => $id )
			);
			Sessions::revoke_for_reviewer( $id );
			$removed = true;

			if ( $erase_content ) {
				$items_table   = Items::table();
				$replies_table = Replies::table();
				$wpdb->query( $wpdb->prepare( "UPDATE {$items_table} SET title = %s, body = '', context = NULL WHERE author_id = %d", __( '[Removed]', 'mna-feedback' ), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "UPDATE {$replies_table} SET body = %s WHERE author_id = %d", __( '[Removed]', 'mna-feedback' ), $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attachments_table = Attachments::table();
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$attachments_table} WHERE uploader_id = %d", $id ) ) ?: array() as $attachment ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					Attachments::delete( $attachment );
				}
			} elseif ( Items::count( array( 'author_id' => $id, 'trashed' => 'any' ) ) > 0 ) {
				$retained = true;
			}
		}
		Reviewers::flush_cache();

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'Feedback comments were kept as project records with the author anonymised.', 'mna-feedback' ) ) : array(),
			'done'           => true,
		);
	}

	public static function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'When you review this site through a shared review link, we store the name (and optional email address) you enter, the feedback, replies and screenshots you add, and when you last used the review tool. A cookie keeps you signed in to the review tool on your device. This information is stored on this website only and is used to manage changes to the site.', 'mna-feedback' ) . '</p>';
		wp_add_privacy_policy_content( 'MNA Feedback', wp_kses_post( wpautop( $text, false ) ) );
	}
}
