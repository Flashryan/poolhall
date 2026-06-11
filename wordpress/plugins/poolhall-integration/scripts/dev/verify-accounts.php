<?php
/**
 * Phase 6 (backend foundations) verification. Run inside WordPress:
 *
 *   wp eval-file scripts/dev/verify-accounts.php
 *
 * Exercises the real candidate-account services (users, meta, role,
 * saved-jobs table) with a capturing mailer — no real email, no real
 * candidate data. Asserts the portal-spec rules:
 *   1. The candidate role exists with minimal capabilities.
 *   2. Registration validates input and creates an unverified candidate
 *      with an internal username and recorded consent.
 *   3. Duplicate registration is generic and creates no second account.
 *   4. Verification tokens are single-use, expiring and tamper-proof.
 *   5. Resends respect the cooldown and invalidate earlier links.
 *   6. Candidates are excluded from REST user listings.
 *   7. Saved jobs are idempotent, survive job recreation under the same
 *      source ID and report live/expired status correctly.
 *   8. Login is generic about failures, never logs in unverified accounts,
 *      and rate limits by account and network without permanent lockout.
 *   9. Password recovery is enumeration-safe; reset links are single-use,
 *      expire after 60 minutes, revoke sessions and trigger a security
 *      email; a password is never emailed.
 *  10. The saved-jobs REST routes enforce authentication, verification and
 *      ownership, and toggling is idempotent.
 *
 * No strict_types declaration: wp eval-file runs this through eval(), where
 * declare() cannot be the first statement.
 *
 * @package Poolhall\Integration
 */

use Poolhall\Integration\Accounts\CandidateRepository;
use Poolhall\Integration\Accounts\CandidateRole;
use Poolhall\Integration\Accounts\FailedLoginStore;
use Poolhall\Integration\Accounts\LoginRateLimiter;
use Poolhall\Integration\Accounts\LoginService;
use Poolhall\Integration\Accounts\Mailer;
use Poolhall\Integration\Accounts\PasswordPolicy;
use Poolhall\Integration\Accounts\PasswordRecoveryService;
use Poolhall\Integration\Accounts\RegistrationService;
use Poolhall\Integration\Accounts\ResendPolicy;
use Poolhall\Integration\Accounts\SavedJobsRepository;
use Poolhall\Integration\Accounts\TokenPolicy;
use Poolhall\Integration\Accounts\VerificationService;
use Poolhall\Integration\Jobs\ExpiryPolicy;
use Poolhall\Integration\Jobs\JobRepository;
use Poolhall\Integration\Source\Giig\GiigNormalizer;
use Poolhall\Integration\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/verify-accounts.php\n";
	exit( 1 );
}

/** Captures account email instead of sending it. */
final class Poolhall_Capture_Mailer implements Mailer {
	/** @var array<int,array{to:string,subject:string,message:string}> */
	public array $sent = array();

	public function send( string $to, string $subject, string $message ): bool {
		$this->sent[] = array(
			'to'      => $to,
			'subject' => $subject,
			'message' => $message,
		);
		return true;
	}

	public function last(): ?array {
		return array() === $this->sent ? null : $this->sent[ count( $this->sent ) - 1 ];
	}
}

$checks = array();
$check  = function ( string $name, bool $pass, string $detail = '' ) use ( &$checks ): void {
	$checks[] = array( $name, $pass, $detail );
	printf( "[%s] %s%s\n", $pass ? 'PASS' : 'FAIL', $name, '' === $detail ? '' : ' — ' . $detail );
};

// Make sure role + table exist even if activation predates this phase.
CandidateRole::install();
SavedJobsRepository::install();

// Clean slate: remove candidates, saved rows and login-failure transients
// from prior verification runs (the rate-limit window outlives a run).
foreach ( get_users( array( 'role' => CandidateRole::ROLE, 'fields' => 'ID' ) ) as $old_id ) {
	wp_delete_user( (int) $old_id );
}
global $wpdb;
$wpdb->query( 'DELETE FROM ' . SavedJobsRepository::table_name() ); // phpcs:ignore WordPress.DB
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%poolhall_login_fail_%'" ); // phpcs:ignore WordPress.DB

$now          = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
$mailer       = new Poolhall_Capture_Mailer();
$candidates   = new CandidateRepository();
$tokens       = new TokenPolicy();
$resends      = new ResendPolicy();
$registration = new RegistrationService( $candidates, new PasswordPolicy(), $tokens, $resends, $mailer, new Logger() );
$verification = new VerificationService( $candidates, $tokens, $resends, $registration, new Logger() );

$extract_token = static function ( ?array $mail ): string {
	if ( null === $mail || 1 !== preg_match( '/token=([0-9a-f]{64})/', $mail['message'], $m ) ) {
		return '';
	}
	return $m[1];
};

// 1. Role.
$role = get_role( CandidateRole::ROLE );
$check( 'candidate role exists', null !== $role );
$check(
	'candidate capabilities are minimal',
	null !== $role && ( $role->capabilities['read'] ?? false )
		&& ! ( $role->capabilities['edit_posts'] ?? false )
		&& ! ( $role->capabilities['upload_files'] ?? false )
		&& ! ( $role->capabilities['list_users'] ?? false )
);

// 2. Validation.
$result = $registration->register(
	array(
		'first_name'   => 'Avery',
		'last_name'    => '',
		'email'        => 'not-an-email',
		'password'     => 'short',
		'accept_terms' => false,
	),
	$now
);
$check(
	'invalid registration rejected with field errors',
	'invalid' === $result['status']
		&& in_array( 'last_name_required', $result['errors'], true )
		&& in_array( 'email_invalid', $result['errors'], true )
		&& in_array( 'terms_required', $result['errors'], true )
		&& in_array( 'too_short', $result['errors'], true ),
	implode( ',', $result['errors'] )
);
$check( 'invalid registration creates no user and sends nothing', null === $candidates->find_by_email( 'not-an-email' ) && array() === $mailer->sent );

// 3. Valid registration.
$result = $registration->register(
	array(
		'first_name'      => 'Avery',
		'last_name'       => 'Quinn',
		'email'           => 'Avery.Quinn@Example.test',
		'password'        => 'quiet velvet morning',
		'accept_terms'    => true,
		'alert_consent'   => true,
		'terms_version'   => '2026-06',
		'privacy_version' => '2026-06',
	),
	$now
);
$user = $candidates->find_by_email( 'avery.quinn@example.test' );
$check( 'registration returns generic check_email', 'check_email' === $result['status'] );
$check( 'unverified candidate created with normalized email', null !== $user && ! $candidates->is_verified( (int) $user->ID ) );
$check( 'candidate has internal non-email username', null !== $user && str_starts_with( $user->user_login, 'ph_c_' ) && $user->user_login !== $user->user_email );
$check( 'candidate holds only the candidate role', null !== $user && array( CandidateRole::ROLE ) === $user->roles );
$consent = json_decode( (string) get_user_meta( (int) $user->ID, CandidateRepository::META_CONSENT, true ), true );
$check( 'consent versions and timestamp recorded', is_array( $consent ) && '2026-06' === $consent['terms_version'] && true === $consent['alert_consent'] && '' !== $consent['accepted_at'] );
$verify_token = $extract_token( $mailer->last() );
$check( 'verification email sent with single-use token link', 1 === count( $mailer->sent ) && '' !== $verify_token );
$check( 'token stored as hash only, never raw', $tokens->hash_token( $verify_token ) === (string) get_user_meta( (int) $user->ID, CandidateRepository::META_TOKEN_HASH, true ) );

// 4. Duplicate registration is generic and non-destructive.
$before_users = count( get_users( array( 'role' => CandidateRole::ROLE, 'fields' => 'ID' ) ) );
$result       = $registration->register(
	array(
		'first_name'   => 'Avery',
		'last_name'    => 'Quinn',
		'email'        => 'avery.quinn@example.test',
		'password'     => 'another fine passphrase',
		'accept_terms' => true,
	),
	$now->modify( '+5 seconds' )
);
$after_users = count( get_users( array( 'role' => CandidateRole::ROLE, 'fields' => 'ID' ) ) );
$check( 'duplicate registration returns the same generic response', 'check_email' === $result['status'] );
$check( 'duplicate registration creates no account', $before_users === $after_users );
$check( 'duplicate within cooldown sends no second email', 1 === count( $mailer->sent ) );

// 5. Verification.
$bad = $verification->verify( str_repeat( '0', 64 ), $now );
$check( 'unknown token rejected', 'invalid' === $bad['status'] );
$ok = $verification->verify( $verify_token, $now->modify( '+10 minutes' ) );
$check( 'valid token verifies the account', 'verified' === $ok['status'] && $candidates->is_verified( (int) $user->ID ) );
$replay = $verification->verify( $verify_token, $now->modify( '+11 minutes' ) );
$check( 'token is single use', 'invalid' === $replay['status'] );

// 6. Expired token.
$mailer->sent = array();
$registration->register(
	array(
		'first_name'   => 'Briar',
		'last_name'    => 'Holt',
		'email'        => 'briar.holt@example.test',
		'password'     => 'plum kettle ninety nine',
		'accept_terms' => true,
	),
	$now
);
$briar       = $candidates->find_by_email( 'briar.holt@example.test' );
$stale_token = $extract_token( $mailer->last() );
$expired     = $verification->verify( $stale_token, $now->modify( '+25 hours' ) );
$check( 'token past 24h is expired, account stays unverified', 'expired' === $expired['status'] && ! $candidates->is_verified( (int) $briar->ID ) );

// 7. Resend: cooldown, invalidation, new token works.
$count_before = count( $mailer->sent );
$verification->resend( 'briar.holt@example.test', $now->modify( '+30 seconds' ) );
$check( 'resend inside 60s cooldown sends nothing', count( $mailer->sent ) === $count_before );
$verification->resend( 'briar.holt@example.test', $now->modify( '+2 minutes' ) );
$fresh_token = $extract_token( $mailer->last() );
$check( 'resend after cooldown sends a new link', count( $mailer->sent ) === $count_before + 1 && '' !== $fresh_token && $fresh_token !== $stale_token );
$old = $verification->verify( $stale_token, $now->modify( '+3 minutes' ) );
$check( 'resend invalidates the previous token', 'invalid' === $old['status'] );
$fresh = $verification->verify( $fresh_token, $now->modify( '+4 minutes' ) );
$check( 'fresh token verifies', 'verified' === $fresh['status'] );
$generic = $verification->resend( 'nobody@example.test', $now );
$check( 'resend for unknown email is generic and silent', 'check_email' === $generic['status'] && count( $mailer->sent ) === $count_before + 1 );

// 8. Enumeration surfaces.
$rest_args = apply_filters( 'rest_user_query', array(), new WP_REST_Request() );
$check( 'candidates excluded from REST user queries', in_array( CandidateRole::ROLE, (array) ( $rest_args['role__not_in'] ?? array() ), true ) );

// 9. Saved jobs.
$fixture = json_decode( (string) file_get_contents( __DIR__ . '/../../tests/fixtures/giig-getjobs.sample.json' ), true );
$source  = ( new GiigNormalizer() )->normalize( $fixture['jobs'][0] );
$jobs    = new JobRepository();
$post_id = $jobs->upsert( $source, new ExpiryPolicy(), $now );
$saved   = new SavedJobsRepository();
$uid     = (int) $user->ID;

$first  = $saved->save( $uid, 'giig', $source->source_job_id, $now );
$second = $saved->save( $uid, 'giig', $source->source_job_id, $now->modify( '+1 minute' ) );
$check( 'save is idempotent', true === $first && false === $second && 1 === $saved->count_for_user( $uid ) );
$check( 'is_saved reflects state', $saved->is_saved( $uid, 'giig', $source->source_job_id ) );

// Recreation under the same source ID keeps the relationship (spec §7).
wp_delete_post( $post_id, true );
$new_post_id = $jobs->upsert( $source, new ExpiryPolicy(), $now->modify( '+2 minutes' ) );
$list        = $saved->list_for_user( $uid, $jobs );
$check(
	'saved job survives recreation under the same source ID',
	1 === count( $list ) && $new_post_id === $list[0]['post_id'] && 'live' === $list[0]['status'],
	wp_json_encode( $list )
);

// Live-first ordering and expired status.
$source_b  = ( new GiigNormalizer() )->normalize( $fixture['jobs'][1] );
$post_b    = $jobs->upsert( $source_b, new ExpiryPolicy(), $now );
$saved->save( $uid, 'giig', $source_b->source_job_id, $now->modify( '+3 minutes' ) );
wp_update_post(
	array(
		'ID'          => $post_b,
		'post_status' => 'draft',
	)
);
$list = $saved->list_for_user( $uid, $jobs );
$check(
	'expired job reported and live jobs sort first',
	2 === count( $list ) && 'live' === $list[0]['status'] && 'expired' === $list[1]['status'] && $source_b->source_job_id === $list[1]['source_job_id']
);

$removed = $saved->clear_not_live_for_user( $uid, $jobs );
$check( 'clear removes only non-live saved jobs', 1 === $removed && 1 === $saved->count_for_user( $uid ) && $saved->is_saved( $uid, 'giig', $source->source_job_id ) );

$gone = $saved->unsave( $uid, 'giig', $source->source_job_id );
$again = $saved->unsave( $uid, 'giig', $source->source_job_id );
$check( 'unsave is idempotent', true === $gone && false === $again && 0 === $saved->count_for_user( $uid ) );

// 10. Login: generic failures, unverified gate, rate limits that self-clear.
$login = new LoginService( $candidates, new LoginRateLimiter(), new FailedLoginStore( new LoginRateLimiter() ), new Logger() );

$registration->register(
	array(
		'first_name'   => 'Dana',
		'last_name'    => 'Reeve',
		'email'        => 'dana.reeve@example.test',
		'password'     => 'maple sunrise harbour',
		'accept_terms' => true,
	),
	$now
);
$dana = $candidates->find_by_email( 'dana.reeve@example.test' );

$unknown = $login->attempt( array( 'email' => 'ghost@example.test', 'password' => 'whatever this is' ), 'net-a', $now );
$wrong   = $login->attempt( array( 'email' => 'avery.quinn@example.test', 'password' => 'not the password' ), 'net-a', $now );
$check( 'unknown email and wrong password are the same generic invalid', 'invalid' === $unknown['status'] && 'invalid' === $wrong['status'] && 0 === $unknown['user_id'] );

$ok = $login->attempt( array( 'email' => 'Avery.Quinn@Example.test', 'password' => 'quiet velvet morning' ), 'net-a', $now );
$check( 'verified candidate with correct password logs in', 'ok' === $ok['status'] && (int) $user->ID === $ok['user_id'] );

$gate = $login->attempt( array( 'email' => 'dana.reeve@example.test', 'password' => 'maple sunrise harbour' ), 'net-a', $now );
$check( 'correct password on an unverified account routes to verification, not a session', 'verify_required' === $gate['status'] );

$admin_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
if ( null !== $admin_user ) {
	$staff = $login->attempt( array( 'email' => (string) $admin_user->user_email, 'password' => 'irrelevant here!' ), 'net-a', $now );
	$check( 'non-candidate accounts get the same generic invalid', 'invalid' === $staff['status'] );
}

foreach ( range( 1, 5 ) as $i ) {
	$login->attempt( array( 'email' => 'dana.reeve@example.test', 'password' => 'wrong attempt ' . $i ), 'net-dana', $now->modify( "+{$i} seconds" ) );
}
$locked = $login->attempt( array( 'email' => 'dana.reeve@example.test', 'password' => 'maple sunrise harbour' ), 'net-dana', $now->modify( '+10 seconds' ) );
$check( 'five failures rate limit the account even with the correct password', 'rate_limited' === $locked['status'] );
$unlocked = $login->attempt( array( 'email' => 'dana.reeve@example.test', 'password' => 'maple sunrise harbour' ), 'net-dana', $now->modify( '+16 minutes' ) );
$check( 'the lock clears by itself after the window — never permanent', 'verify_required' === $unlocked['status'] );

foreach ( range( 1, 5 ) as $i ) {
	$login->attempt( array( 'email' => sprintf( 'ghost%d@example.test', $i ), 'password' => 'wrong attempt ' . $i ), 'net-shared', $now );
}
$network_limited = $login->attempt( array( 'email' => 'avery.quinn@example.test', 'password' => 'quiet velvet morning' ), 'net-shared', $now->modify( '+5 seconds' ) );
$check( 'failures across many accounts rate limit the network signal', 'rate_limited' === $network_limited['status'] );

// 11. Password recovery: enumeration-safe, single-use, expiring, session-revoking.
$recovery     = new PasswordRecoveryService( $candidates, new PasswordPolicy(), $tokens, $resends, $mailer, new Logger() );
$mail_count   = count( $mailer->sent );
$ghost_result = $recovery->request( 'nobody@example.test', $now );
$check( 'reset request for an unknown email is generic and sends nothing', 'check_email' === $ghost_result['status'] && count( $mailer->sent ) === $mail_count );

$recovery->request( 'avery.quinn@example.test', $now );
$reset_token = $extract_token( $mailer->last() );
$reset_mail  = $mailer->last();
$check( 'reset email sent with a single-use link and no password', count( $mailer->sent ) === $mail_count + 1 && '' !== $reset_token && str_contains( $reset_mail['message'], '/candidate/reset-password/' ) );
$check( 'reset token stored as hash only', $tokens->hash_token( $reset_token ) === (string) get_user_meta( (int) $user->ID, CandidateRepository::META_RESET_HASH, true ) );

$recovery->request( 'avery.quinn@example.test', $now->modify( '+30 seconds' ) );
$check( 'reset requests respect the send cooldown', count( $mailer->sent ) === $mail_count + 1 );

$bad_reset = $recovery->reset( 'not-a-token', 'rosewood lantern forty two', $now );
$fake      = $recovery->reset( str_repeat( 'a', 64 ), 'rosewood lantern forty two', $now );
$check( 'malformed and unknown reset tokens are invalid', 'invalid' === $bad_reset['status'] && 'invalid' === $fake['status'] );

$weak = $recovery->reset( $reset_token, 'short', $now->modify( '+5 minutes' ) );
$check( 'weak replacement password is rejected and the token survives', 'weak_password' === $weak['status'] && in_array( 'too_short', $weak['errors'], true ) );

// A live session that must die with the reset.
$session_manager = WP_Session_Tokens::get_instance( (int) $user->ID );
$session_token   = $session_manager->create( time() + HOUR_IN_SECONDS );
$done            = $recovery->reset( $reset_token, 'rosewood lantern forty two', $now->modify( '+10 minutes' ) );
$avery_fresh     = get_user_by( 'id', (int) $user->ID );
$check( 'valid reset changes the password', 'reset' === $done['status'] && wp_check_password( 'rosewood lantern forty two', (string) $avery_fresh->user_pass, (int) $user->ID ) );
$check( 'old password no longer works', ! wp_check_password( 'quiet velvet morning', (string) $avery_fresh->user_pass, (int) $user->ID ) );
$check( 'reset revokes existing sessions', false === $session_manager->verify( $session_token ) );
$security_mail = $mailer->last();
$check( 'security email confirms the change without containing the password', str_contains( $security_mail['subject'], 'password was changed' ) && ! str_contains( $security_mail['message'], 'rosewood lantern forty two' ) );

$replay = $recovery->reset( $reset_token, 'whichever words here', $now->modify( '+11 minutes' ) );
$check( 'reset token is single use', 'invalid' === $replay['status'] );

$recovery->request( 'avery.quinn@example.test', $now->modify( '+2 minutes' ) );
$stale_reset = $extract_token( $mailer->last() );
$expired     = $recovery->reset( $stale_reset, 'rosewood lantern forty two', $now->modify( '+63 minutes' ) );
$check( 'reset token past 60 minutes is expired', 'expired' === $expired['status'] );

// 12. Saved-jobs REST routes: authentication, verification, ownership.
rest_get_server(); // Boots the REST server so rest_api_init registers the routes.

$poolhall_rest_save = static function ( bool $save_state, string $job_id ) use ( $source ): WP_REST_Response {
	$request = new WP_REST_Request( 'POST', '/poolhall/v1/saved-jobs' );
	$request->set_body_params(
		array(
			'source'        => 'giig',
			'source_job_id' => $job_id,
			'saved'         => $save_state,
		)
	);
	return rest_do_request( $request );
};

wp_set_current_user( 0 );
$response = $poolhall_rest_save( true, $source->source_job_id );
$check( 'signed-out save returns 401', 401 === $response->get_status() );

wp_set_current_user( (int) $dana->ID );
$response = $poolhall_rest_save( true, $source->source_job_id );
$check( 'unverified candidate save returns 403 verification_required', 403 === $response->get_status() && 'verification_required' === ( $response->get_data()['code'] ?? '' ) );

if ( null !== $admin_user ) {
	wp_set_current_user( (int) $admin_user->ID );
	$response = $poolhall_rest_save( true, $source->source_job_id );
	$check( 'non-candidate accounts cannot use the saved-jobs API', 403 === $response->get_status() );
}

wp_set_current_user( (int) $user->ID );
$first_save  = $poolhall_rest_save( true, $source->source_job_id );
$second_save = $poolhall_rest_save( true, $source->source_job_id );
$check(
	'verified candidate saves over REST and repeats are idempotent',
	200 === $first_save->get_status() && true === $first_save->get_data()['saved']
		&& 200 === $second_save->get_status() && true === $second_save->get_data()['saved']
		&& 1 === $second_save->get_data()['count']
);

$response = $poolhall_rest_save( true, 'no-such-job-id' );
$check( 'saving an unknown job returns 404', 404 === $response->get_status() );

$briar_id = (int) $candidates->find_by_email( 'briar.holt@example.test' )->ID;
wp_set_current_user( $briar_id );
$list_request = new WP_REST_Request( 'GET', '/poolhall/v1/saved-jobs' );
$briar_list   = rest_do_request( $list_request );
$check( 'another candidate sees none of those saved jobs', 200 === $briar_list->get_status() && 0 === $briar_list->get_data()['count'] );

wp_set_current_user( (int) $user->ID );
$unsaved = $poolhall_rest_save( false, $source->source_job_id );
$check( 'REST unsave clears the state idempotently', 200 === $unsaved->get_status() && false === $unsaved->get_data()['saved'] && 0 === $unsaved->get_data()['count'] );
wp_set_current_user( 0 );

$failed = array_filter( $checks, static fn( array $c ): bool => ! $c[1] );
printf( "\n%d/%d checks passed.\n", count( $checks ) - count( $failed ), count( $checks ) );
exit( array() === $failed ? 0 : 1 );
