<?php
/**
 * Expiring, single-use onboarding invitations.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/**
 * Issues and validates the invitation tokens that gate the document
 * forms (full registration, starter statement, terms). A token is bound
 * to one pre-registration record, carries which forms it unlocks, hides
 * only a hash server-side, expires after 14 days, and each form can be
 * completed once per invite. Nothing sensitive travels in the URL —
 * the token resolves to the stored record server-side.
 */
final class InviteManager {

	public const QUERY_ARG = 'inv';
	private const TTL      = 14 * 86400;

	/**
	 * @param string[] $kinds Forms this invite unlocks: full|starter|terms.
	 * @return string The raw token for the link (stored only as a hash).
	 */
	public function create( int $record_id, array $kinds = array( 'full' ) ): string {
		$token = wp_generate_password( 32, false );
		update_post_meta( $record_id, 'invite_hash', wp_hash( $token ) );
		update_post_meta( $record_id, 'invite_expires', (string) ( time() + self::TTL ) );
		update_post_meta( $record_id, 'invite_kinds', implode( ',', array_map( 'sanitize_key', $kinds ) ) );
		delete_post_meta( $record_id, 'invite_used' );
		update_post_meta( $record_id, 'review_status', 'invite_sent' );
		return $token;
	}

	/** Record id when the token is valid, unexpired and unused for $kind; 0 otherwise. */
	public function validate( string $token, string $kind ): int {
		$token = sanitize_text_field( $token );
		if ( strlen( $token ) < 20 ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'      => 'poolhall_application',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'invite_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- exact-match token lookup.
				'meta_value'     => wp_hash( $token ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( array() === $found ) {
			return 0;
		}
		$record_id = (int) $found[0];
		if ( time() > (int) get_post_meta( $record_id, 'invite_expires', true ) ) {
			return 0;
		}
		$kinds = explode( ',', (string) get_post_meta( $record_id, 'invite_kinds', true ) );
		if ( ! in_array( $kind, $kinds, true ) ) {
			return 0;
		}
		$used = array_filter( explode( ',', (string) get_post_meta( $record_id, 'invite_used', true ) ) );
		if ( in_array( $kind, $used, true ) ) {
			return 0;
		}
		return $record_id;
	}

	/** Burns the invite for one form kind (single-use per form). */
	public function mark_used( int $record_id, string $kind ): void {
		$used   = array_filter( explode( ',', (string) get_post_meta( $record_id, 'invite_used', true ) ) );
		$used[] = sanitize_key( $kind );
		update_post_meta( $record_id, 'invite_used', implode( ',', array_unique( $used ) ) );
		$kinds = array_filter( explode( ',', (string) get_post_meta( $record_id, 'invite_kinds', true ) ) );
		if ( array() === array_diff( $kinds, $used ) ) {
			update_post_meta( $record_id, 'review_status', 'completed' );
		}
	}

	/** Safe prefill data for the invited candidate (never bank/NI/health). */
	public function prefill( int $record_id ): array {
		return array(
			'first_name' => (string) get_post_meta( $record_id, 'applicant_first', true ),
			'last_name'  => (string) get_post_meta( $record_id, 'applicant_last', true ),
			'email'      => (string) get_post_meta( $record_id, 'applicant_email', true ),
			'phone'      => (string) get_post_meta( $record_id, 'applicant_phone', true ),
			'role_title' => (string) get_post_meta( $record_id, 'role_title', true ),
			'giig_id'    => (string) get_post_meta( $record_id, 'giig_candidate_id', true ),
		);
	}

	public function link( string $token, string $page_slug ): string {
		return add_query_arg( self::QUERY_ARG, rawurlencode( $token ), home_url( '/' . $page_slug . '/' ) );
	}
}
