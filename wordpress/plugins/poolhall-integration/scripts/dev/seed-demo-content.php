<?php
/**
 * STAGING-ONLY: seed demo jobs and demo review quotes so the featured
 * carousel, jobs archive and reviews section are never empty while the
 * Giig and Places integrations wait on credentials (directive §3.1).
 *
 *   wp eval-file scripts/dev/seed-demo-content.php
 *
 * Refuses to run when the site URL is the production domain. Every demo
 * job carries `source = demo` and the quotes live in their own option, so
 * `poolhall_demo_content` can be switched off and the posts deleted
 * without touching real data. Idempotent: jobs are keyed by their demo
 * source id. Content comes from the approved prototype sample data
 * (project/ui_kits/website/data.js) — staging preview only, never
 * production (directive guardrail 5).
 *
 * No strict_types declaration: wp eval-file runs this through eval().
 *
 * @package Poolhall\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file scripts/dev/seed-demo-content.php\n";
	exit( 1 );
}
if ( str_contains( (string) home_url(), 'poolhallrecruitment.co.uk' ) ) {
	echo "REFUSED: demo content never runs on the production domain.\n";
	exit( 1 );
}

$poolhall_demo_jobs = array(
	array( 'demo-27499', 'Project Manager, Multi-Storey Construction', 'Construction & Skilled Trade', 'Cheltenham, Gloucestershire', 'Must be onsite', 60000, 75000, true, 'Lead a technically complex, multi-storey build from design stage through to completion on a predominantly site-based role.', array( 'Own programme, budget and site delivery', 'Manage subcontractors and design teams', 'Drive health, safety and quality on a live site' ) ),
	array( 'demo-27488', 'Structural Steel MIG Welder / Fabricator', 'Manufacturing', 'Wolverhampton, West Midlands', 'Must be onsite', 34000, 34000, true, 'Join a well-established, family-run structural steel business producing high-quality structural and secondary steelwork.', array( '07:30 to 16:00, no overtime', 'Heavy-duty fencing and structural systems', 'Stable, full order book' ) ),
	array( 'demo-27475', 'Head of Paid Media', 'Marketing & PR', 'Brighton, East Sussex', 'Part Remote', 50000, 55000, true, 'Own paid strategy across search and social for a fast-growing agency with enviable retention.', array( 'Lead a team of four specialists', 'Own six-figure monthly budgets', 'Hybrid working, two days in office' ) ),
	array( 'demo-27466', 'Mobile HVAC / Refrigeration Engineer', 'Construction & Skilled Trade', 'Dudley, West Midlands', 'Must be onsite', 50000, 50000, false, 'Mobile engineering role across the Midlands with van, door-to-door pay and genuine progression.', array( 'Company van and fuel card', 'Door-to-door travel pay', 'Manufacturer training provided' ) ),
	array( 'demo-27452', 'SEO Manager', 'Marketing & PR', 'Brighton, East Sussex', 'Part Remote', 30000, 38000, false, 'Take ownership of organic growth for a growing marketing agency and its retained client base.', array( 'Own the SEO roadmap end to end', 'Mentor a junior executive', 'Clear route to Head of SEO' ) ),
	array( 'demo-27440', 'Highways General Operative', 'Construction & Skilled Trade', 'South East, UK', 'Must be onsite', 26000, 32000, false, 'Build a long-term career in highways maintenance, with training and certification fully funded.', array( 'Full training and tickets funded', 'Regular overtime available', 'Long-term framework contracts' ) ),
);

$poolhall_now     = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
$poolhall_expires = $poolhall_now->add( new DateInterval( 'P30D' ) )->format( DateTimeInterface::ATOM );
$poolhall_count   = 0;

foreach ( $poolhall_demo_jobs as [ $poolhall_sid, $poolhall_title, $poolhall_sector, $poolhall_loc, $poolhall_mode, $poolhall_min, $poolhall_max, $poolhall_feat, $poolhall_summary, $poolhall_bullets ] ) {
	$existing = get_posts(
		array(
			'post_type'      => 'poolhall_job',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'source_job_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- keyed lookup at seed time.
			'meta_value'     => $poolhall_sid, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	$poolhall_content = '<p>' . esc_html( $poolhall_summary ) . '</p><h2>What you&rsquo;ll do</h2><ul>';
	foreach ( $poolhall_bullets as $poolhall_bullet ) {
		$poolhall_content .= '<li>' . esc_html( $poolhall_bullet ) . '</li>';
	}
	$poolhall_content .= '</ul><p><em>Sample listing for staging preview.</em></p>';

	$poolhall_post_id = array() !== $existing ? (int) $existing[0] : wp_insert_post(
		array(
			'post_type'   => 'poolhall_job',
			'post_status' => 'publish',
			'post_title'  => $poolhall_title,
			'post_content' => $poolhall_content,
			'post_excerpt' => $poolhall_summary,
		)
	);
	if ( 0 === $poolhall_post_id || is_wp_error( $poolhall_post_id ) ) {
		continue;
	}

	$poolhall_salary = 0 === $poolhall_max || $poolhall_min === $poolhall_max
		? sprintf( '£%s', number_format( $poolhall_min ) )
		: sprintf( '£%s–£%s', number_format( $poolhall_min ), number_format( $poolhall_max ) );

	update_post_meta( $poolhall_post_id, 'source', 'demo' );
	update_post_meta( $poolhall_post_id, 'source_job_id', $poolhall_sid );
	update_post_meta( $poolhall_post_id, 'location_display', $poolhall_loc );
	update_post_meta( $poolhall_post_id, 'work_mode_raw', $poolhall_mode );
	update_post_meta( $poolhall_post_id, 'salary_display', $poolhall_salary . ' /year' );
	update_post_meta( $poolhall_post_id, 'salary_min', (string) $poolhall_min );
	update_post_meta( $poolhall_post_id, 'salary_max', (string) $poolhall_max );
	update_post_meta( $poolhall_post_id, 'date_posted', $poolhall_now->format( 'Y-m-d' ) );
	update_post_meta( $poolhall_post_id, 'expires_at', $poolhall_expires );
	if ( $poolhall_feat ) {
		update_post_meta( $poolhall_post_id, 'is_featured', '1' );
	} else {
		delete_post_meta( $poolhall_post_id, 'is_featured' );
	}
	wp_set_object_terms( $poolhall_post_id, $poolhall_sector, 'poolhall_sector' );
	++$poolhall_count;
}

// Demo review quotes (prototype data.js) — separate option, never the real
// Places cache; the renderer only reads it while demo content is enabled.
$poolhall_demo_reviews = array(
	array( 'Daniel R.', 'Placed as Site Manager', 'From first call to offer, Poolhall kept me informed at every step. Genuinely felt like they were on my side.' ),
	array( 'Sarah T.', 'HR Director, Manufacturing', 'They took time to understand our culture, not just the spec. Two strong hires in six weeks.' ),
	array( 'Marcus L.', 'Placed in Paid Media', 'Honest advice even when it was not the easy answer. That is rare in recruitment.' ),
	array( 'Priya N.', 'Operations Lead', 'Responsive, ethical and no pushy tactics. Exactly how recruitment should be.' ),
	array( 'James W.', 'Placed as Welder/Fabricator', 'Sorted a permanent role close to home with a great firm. Could not ask for more.' ),
);
update_option(
	\Poolhall\Integration\Reviews\ReviewsCarousel::DEMO_OPTION,
	array(
		'place_name'      => 'Poolhall Recruitment',
		'rating'          => 5.0,
		'rating_count'    => count( $poolhall_demo_reviews ),
		'reviews'         => array_map(
			static fn( array $r ): array => array(
				'author_name'        => $r[0],
				'author_profile_url' => null,
				'author_photo_url'   => null,
				'rating'             => 5,
				'text'               => $r[2],
				'relative_time'      => $r[1],
			),
			$poolhall_demo_reviews
		),
		'google_maps_uri' => null,
		'fetched_at'      => $poolhall_now->format( DateTimeInterface::ATOM ),
	),
	false
);
update_option( 'poolhall_demo_content', '1', false );

printf( "Demo jobs in place: %d (source=demo). Demo reviews stored; poolhall_demo_content=1.\n", $poolhall_count );
echo "OK: staging demo content seeded. Never run this on production.\n";
