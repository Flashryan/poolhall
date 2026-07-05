<?php
/**
 * Branded 404 (directive §12): search plus popular routes, never a dead end.
 *
 * @package HelloElementorChild
 */

defined( 'ABSPATH' ) || exit;

get_header();

$poolhall_jobs_page = get_page_by_path( 'jobs' );
$poolhall_jobs_url  = $poolhall_jobs_page instanceof WP_Post ? (string) get_permalink( $poolhall_jobs_page ) : home_url( '/jobs/' );
?>
<main class="ph-v2-hero ph-404" style="min-height:auto">
	<div class="container" style="padding-top:96px;padding-bottom:110px">
		<div class="hero-inner">
			<p class="label on-dark"><span class="idx">404</span> Page not found</p>
			<h1 class="display on-dark" style="font-size:clamp(2rem,4vw,3.4rem)"><?php esc_html_e( 'That page has moved on.', 'hello-elementor-child' ); ?></h1>
			<p class="lead on-dark"><?php esc_html_e( 'The link may be old, or the role may have been filled. The live jobs list is the best place to pick the trail back up.', 'hello-elementor-child' ); ?></p>
			<form class="ph-404__search" method="get" action="<?php echo esc_url( $poolhall_jobs_url ); ?>" style="display:flex;gap:10px;margin-top:26px;max-width:460px;flex-wrap:wrap">
				<label class="ph-visually-hidden" for="ph-404-q"><?php esc_html_e( 'Search live jobs', 'hello-elementor-child' ); ?></label>
				<input id="ph-404-q" type="search" name="q" placeholder="<?php esc_attr_e( 'Search live jobs&hellip;', 'hello-elementor-child' ); ?>"
					style="flex:1;min-width:200px;padding:13px 15px;border:1.5px solid rgba(255,255,255,.4);border-radius:3px;background:rgba(255,255,255,.08);color:#fff" />
				<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Search', 'hello-elementor-child' ); ?></button>
			</form>
			<div class="hero-cta">
				<a class="btn btn-primary" href="<?php echo esc_url( $poolhall_jobs_url ); ?>"><?php esc_html_e( 'Browse live jobs', 'hello-elementor-child' ); ?></a>
				<a class="btn btn-ghost-light" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'hello-elementor-child' ); ?></a>
				<a class="btn btn-ghost-light" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact us', 'hello-elementor-child' ); ?></a>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();
