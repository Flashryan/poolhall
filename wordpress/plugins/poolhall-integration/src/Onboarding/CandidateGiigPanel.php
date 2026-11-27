<?php
declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/** Standard WordPress admin UI; no candidate data or secrets in error messages. */
final class CandidateGiigPanel {

	public static function render( int $record ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = (string) get_post_meta( $record, 'giig_link_status', true );
		$id = (string) get_post_meta( $record, 'giig_candidate_id', true );
		$labels = array( 'linked' => 'Linked — profile sync awaits approval or API access', 'queued' => 'Queued for sync', 'syncing' => 'Sync in progress', 'synced' => 'Linked and synced', 'partial' => 'Linked — sync incomplete', 'failed' => 'Sync blocked or failed', 'needs_review' => 'Staff review required' );
		$label = $labels[ $status ] ?? ( '' !== $id ? 'Linked — approval sync not yet checked' : 'Not linked — no approval sync yet' );
		$error = (string) get_post_meta( $record, 'giig_link_error', true );
		$messages = array(
			'permission_denied' => '' === $id ? 'Giig denied API access (HTTP 401/403). No candidate was created. Check Giig manually, then link the existing candidate ID below. Automatic search needs candidate/get permission.' : 'The candidate is linked, but Giig denied a sync request (HTTP 401/403). The saved ID is retained. Profile updates need candidate/update permission; linking does not grant it.',
			'invalid_giig_id' => 'Enter the positive numeric Candidate ID from Giig, not a name or website URL.',
			'giig_details_mismatch' => 'Giig did not return that exact candidate ID with the same name and email. Nothing was linked. Check the details in both systems; manual confirmation cannot override a mismatch.',
			'giig_id_conflict' => 'This website identity is already linked to a different Giig ID. It cannot be overwritten here.',
			'giig_id_in_use' => 'That Giig ID already belongs to a different website identity. Review and reconcile the website records before linking.',
			'manual_confirmation_required' => 'Giig blocked ID verification with HTTP 403. Check this candidate’s ID, name and email directly in Giig, then tick the confirmation below and submit again.',
			'id_verification_failed' => 'The ID verification failed. Nothing was linked. Only a read-permission denial (HTTP 403) allows the recorded manual-verification fallback; connection failures and mismatches do not.',
			'sync_busy' => 'Another operation for this candidate is running. Wait briefly and retry.',
			'unconfigured' => 'Giig candidate lookup is not configured. Check the integration connection before retrying.',
			'lookup_failed' => 'The existing-candidate lookup failed. No candidate was created. Retry after the API connection is working.',
			'lookup_incomplete' => 'Giig returned incomplete or unfamiliar lookup results. No candidate was created; staff review is required.',
			'ambiguous_match' => 'More than one candidate has this email, or the existing name differs. Nothing was merged or created. Reconcile the candidate in Giig before retrying.',
			'identity_review' => 'The website identity requires review. Resolve the identity conflict before syncing.',
			'invalid_profile' => 'The approved profile needs a first name, last name and valid email before it can sync.',
			'create_uncertain' => 'A previous create attempt had an uncertain result. Retry checks for the existing record but will not blindly create another. Staff must reconcile an unresolved result.',
			'link_storage_failed' => 'The candidate ID could not be safely saved. Staff review is required; do not create another candidate manually without checking Giig.',
			'storage_failed' => 'The website could not persist the sync intent. No create request was sent.',
			'update_failed' => 'The Giig candidate is linked, but the profile update failed. Retry updates that same candidate; it does not create another.',
			'note_failed' => 'The Giig candidate is linked, but an accepted-document timeline note failed. Retry uses the same candidate.',
			'notes_pending' => 'The candidate is linked. Remaining accepted-document notes will continue in the background.',
			'queue_failed' => 'The background task could not be scheduled. Use Retry Giig sync to continue safely.',
		);
		echo '<section class="notice notice-' . esc_attr( 'synced' === $status ? 'success' : ( in_array( $status, array( 'queued', 'syncing' ), true ) ? 'info' : 'warning' ) ) . ' inline" aria-labelledby="poolhall-giig-status">';
		echo '<h2 id="poolhall-giig-status">Giig integration</h2><p><strong>' . esc_html( $label ) . '</strong>' . ( '' !== $id ? ' — candidate #' . esc_html( $id ) : '' ) . '</p>';
		if ( isset( $messages[ $error ] ) ) {
			echo '<p>' . esc_html( $messages[ $error ] ) . '</p>';
		}
		$checked = (string) get_post_meta( $record, 'giig_link_checked_at', true );
		if ( '' !== $checked ) {
			echo '<p>Last check: ' . esc_html( $checked ) . '</p>';
		}
		$method = (string) get_post_meta( $record, 'giig_link_verified_method', true );
		if ( in_array( $method, array( 'api', 'staff_attestation' ), true ) ) {
			echo '<p>Link verified: ' . esc_html( 'api' === $method ? 'matching details checked through the API' : 'staff checked details directly in Giig; API read was denied' ) . ' — ' . esc_html( (string) get_post_meta( $record, 'giig_link_verified_at', true ) ) . '.</p>';
		}
		echo '<p>Future forms use the saved Giig ID. Automatic linking searches first and only creates after a confirmed no-match; a blocked search waits for staff. Signed PDFs stay in the protected website record. Supported profile updates and accepted-document notes depend on Giig permissions; bank/tax/signature data and PDFs are not sent.</p>';
		if ( '' === $id && 'closed' !== (string) get_post_meta( $record, 'workflow_stage', true ) && ! \Poolhall\Integration\Applications\CandidateIdentity::review_required( $record ) ) {
			echo '<h3>Link an existing Giig candidate</h3><p>Open the candidate in Giig and copy their numeric Candidate ID. This can be done before or after the candidate profile is accepted. If they are new, check Giig for duplicates first, create them there, then link their ID here.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="poolhall_review_action"><input type="hidden" name="record" value="' . esc_attr( (string) $record ) . '"><input type="hidden" name="do" value="link_giig">';
			echo wp_nonce_field( 'poolhall_review_' . $record, '_wpnonce', true, false );
			echo '<p><label for="poolhall-existing-giig-id"><strong>Existing Giig candidate ID</strong></label><br><input id="poolhall-existing-giig-id" name="giig_candidate_id" type="text" inputmode="numeric" pattern="[1-9][0-9]*" maxlength="20" class="regular-text" required aria-describedby="poolhall-giig-id-help"></p>';
			echo '<p id="poolhall-giig-id-help">We try to verify the ID, name and email through Giig. If Giig denies the read (HTTP 403), your confirmation below is required and recorded with your staff user and time.</p>';
			echo '<p><label><input type="checkbox" name="giig_details_verified" value="1"> I have checked directly in Giig that this ID belongs to the same candidate, with the same first name, last name and email as this website record.</label></p>';
			echo '<p><button type="submit" class="button button-primary">Verify &amp; link existing candidate</button> This never creates a Giig candidate or resets forms.</p></form>';
		}
		if ( 'accepted' === (string) get_post_meta( $record, 'task_full_status', true ) && 'closed' !== (string) get_post_meta( $record, 'workflow_stage', true ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="poolhall_review_action"><input type="hidden" name="record" value="' . esc_attr( (string) $record ) . '"><input type="hidden" name="do" value="sync_giig">';
			echo wp_nonce_field( 'poolhall_review_' . $record, '_wpnonce', true, false );
			echo '<p><button type="submit" class="button">' . esc_html( '' === $status ? 'Sync/link to Giig' : 'Retry Giig sync' ) . '</button> This does not reset forms or resend emails.</p></form>';
		} else {
			echo '<p>Review and accept the candidate profile first.</p>';
		}
		echo '</section>';
	}
}
