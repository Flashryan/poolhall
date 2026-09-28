<?php
/**
 * Front-end integration.
 *
 * Every public page gets the same tiny inline loader, so page caches can
 * store one copy for everyone. The loader fetches the review interface only
 * when the browser holds the "review mode" flag cookie; everything private
 * is loaded afterwards through the authorised REST API. Ordinary visitors
 * never see review controls and never download the interface.
 *
 * Shared links (?mna-review=TOKEN) and private return links
 * (?mna-return=TOKEN) are handled here: the token moves into an HttpOnly
 * cookie and the visitor is redirected to the same page without it.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback;

use MNA\Feedback\Access\Cookies;
use MNA\Feedback\Access\Join;
use MNA\Feedback\Access\Links;
use MNA\Feedback\Access\Sessions;
use MNA\Feedback\Data\Items;
use MNA\Feedback\Data\Reviewers;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	/** Query parameters that mean a page builder's editor is running. */
	private const BUILDER_PARAMS = array( 'elementor-preview', 'fl_builder', 'et_fb', 'bricks', 'ct_builder', 'vc_editable', 'vc_action', 'tve', 'brizy-edit-iframe', 'preview_iframe' );

	public static function init(): void {
		add_action( 'init', array( self::class, 'handle_links' ), 5 );
		add_action( 'wp_footer', array( self::class, 'print_loader' ), 1000 );
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 90 );
	}

	/* ---------------------------------------------------------------------
	 * Shared and return links
	 * ------------------------------------------------------------------ */

	public static function handle_links(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Capability links, not form submissions.
		$join   = isset( $_GET[ Links::QUERY_JOIN ] ) ? sanitize_text_field( wp_unslash( $_GET[ Links::QUERY_JOIN ] ) ) : null;
		$return = isset( $_GET[ Links::QUERY_RETURN ] ) ? sanitize_text_field( wp_unslash( $_GET[ Links::QUERY_RETURN ] ) ) : null;
		// phpcs:enable
		if ( null === $join && null === $return ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( 'GET' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
			return;
		}

		self::private_response();

		if ( null !== $return ) {
			self::handle_return( $return );
		} elseif ( 'on' === $join ) {
			self::handle_on();
		} elseif ( 'off' === $join ) {
			Cookies::set_flag( false );
		} else {
			self::handle_join( $join );
		}

		self::redirect_clean();
	}

	private static function handle_on(): void {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			// Team members sign in first, then land back here with review mode on.
			$back = add_query_arg( Links::QUERY_JOIN, 'on', self::current_url( false ) );
			wp_safe_redirect( wp_login_url( $back ) );
			exit;
		}
		if ( Capabilities::role_for_user( $user ) ) {
			Cookies::set_flag( true );
		}
	}

	private static function handle_join( string $token ): void {
		$user = wp_get_current_user();
		if ( $user->exists() && Capabilities::role_for_user( $user ) ) {
			// Team members review as themselves.
			Cookies::set_flag( true );
			return;
		}

		$link = Links::find_by_token( $token );
		if ( ! $link ) {
			Join::mark_invalid( 'invalid' );
			return;
		}
		$status = Links::status( $link );
		if ( 'active' !== $status ) {
			Join::mark_invalid( $status );
			return;
		}

		Links::record_use( (int) $link->id );
		if ( Sessions::current() ) {
			// Already reviewing on this device.
			Join::clear();
			Cookies::set_flag( true );
			return;
		}
		Join::set_pending( $token );
	}

	private static function handle_return( string $token ): void {
		$reviewer = Reviewers::find_by_return_token( $token );
		if ( ! $reviewer ) {
			Join::mark_invalid( 'return' );
			return;
		}
		if ( 'active' !== $reviewer->status ) {
			Join::mark_invalid( 'blocked' );
			return;
		}
		$link_id = (int) $reviewer->link_id;
		if ( $link_id > 0 ) {
			$link   = Links::get( $link_id );
			$status = $link ? Links::status( $link ) : 'revoked';
			if ( 'active' !== $status ) {
				Join::mark_invalid( $status );
				return;
			}
		}

		$current = Sessions::current();
		if ( ! $current || (int) $current->reviewer_id !== (int) $reviewer->id ) {
			if ( $current ) {
				Sessions::end_current();
			}
			Sessions::create( (int) $reviewer->id, $link_id );
		}
		Join::clear();
		Cookies::set_flag( true );
	}

	/**
	 * Responses to link visits must never be cached or leak the token.
	 */
	private static function private_response(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'mna-feedback link' );
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
	}

	private static function redirect_clean(): void {
		// current_url() only ever returns one of this site's own origins.
		wp_redirect( self::current_url( true ), 302, 'MNA Feedback' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * The requested address, optionally without the link parameters.
	 */
	private static function current_url( bool $strip ): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! str_starts_with( $uri, '/' ) ) {
			$uri = '/';
		}
		// Stay on the address the visitor used (cookies were just set for it)
		// when it is one of this site's addresses; otherwise use the home URL.
		$origin    = (string) Rest\Router::origin_of( home_url( '/' ) );
		$requested = isset( $_SERVER['HTTP_HOST'] ) ? ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( '' !== $requested && Rest\Router::origin_allowed( $requested ) ) {
			$origin = (string) Rest\Router::origin_of( $requested );
		}
		$url = $origin . $uri;
		if ( $strip ) {
			$url = remove_query_arg( array( Links::QUERY_JOIN, Links::QUERY_RETURN ), $url );
		}
		return esc_url_raw( $url );
	}

	/* ---------------------------------------------------------------------
	 * Loader
	 * ------------------------------------------------------------------ */

	public static function should_print_loader(): bool {
		if ( ! Settings::get( 'enabled' ) ) {
			return false;
		}
		if ( is_admin() || is_feed() || is_embed() || is_customize_preview() || wp_is_json_request() ) {
			return false;
		}
		foreach ( self::BUILDER_PARAMS as $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return false;
			}
		}
		/**
		 * Whether to print the review loader on this request.
		 *
		 * @param bool $print
		 */
		return (bool) apply_filters( 'mnafb_print_loader', true );
	}

	public static function config(): array {
		return array(
			'rest' => self::relative( rest_url( Rest\Router::NS . '/' ) ),
			'app'  => MNAFB_URL . 'assets/app.js?ver=' . rawurlencode( MNAFB_VERSION ),
			'ver'  => MNAFB_VERSION,
		);
	}

	public static function print_loader(): void {
		if ( ! self::should_print_loader() ) {
			return;
		}
		$config = wp_json_encode( self::config(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		$js     = '(function(){try{if(window.self!==window.top||!/(?:^|;\s*)mnafb_on=1(?:;|$)/.test(document.cookie))return;var c=window.mnafbBoot=' . $config . ';if(document.getElementById("mnafb-app"))return;var s=document.createElement("script");s.id="mnafb-app";s.src=c.app;s.async=true;document.head.appendChild(s);}catch(e){}})();';
		wp_print_inline_script_tag(
			$js,
			array(
				'id'                => 'mnafb-loader',
				'data-no-optimize'  => '1',
				'data-no-minify'    => '1',
				'data-no-defer'     => '1',
				'data-cfasync'      => 'false',
				'nowprocket'        => true,
			)
		);
	}

	/**
	 * Path (and query) of a URL on this site, so requests stay same-origin
	 * whichever of the site's addresses the page was opened on.
	 */
	private static function relative( string $url ): string {
		$parts = wp_parse_url( $url );
		$path  = $parts['path'] ?? '/';
		return $path . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
	}

	/* ---------------------------------------------------------------------
	 * Toolbar
	 * ------------------------------------------------------------------ */

	public static function admin_bar( \WP_Admin_Bar $bar ): void {
		$user = wp_get_current_user();
		if ( ! $user->exists() || ! Capabilities::role_for_user( $user ) || ! Settings::get( 'enabled' ) ) {
			return;
		}
		$open  = Items::count( array( 'status' => array( 'open' ) ) );
		$title = '<span class="ab-icon dashicons dashicons-format-chat" aria-hidden="true" style="top:2px"></span><span class="ab-label">' . esc_html( (string) Settings::get( 'display_name' ) ) . ( $open ? ' <span class="count" style="display:inline-block;min-width:18px;padding:0 5px;border-radius:9px;background:#4F46E5;color:#fff;font-size:11px;line-height:18px;text-align:center">' . (int) $open . '</span>' : '' ) . '</span>';

		if ( is_admin() ) {
			$bar->add_node(
				array(
					'id'    => 'mna-feedback',
					'title' => $title,
					'href'  => add_query_arg( Links::QUERY_JOIN, 'on', home_url( '/' ) ),
					'meta'  => array( 'title' => __( 'Review the site', 'mna-feedback' ) ),
				)
			);
			return;
		}

		$on = '1' === Cookies::get( Cookies::FLAG );
		$bar->add_node(
			array(
				'id'    => 'mna-feedback',
				'title' => $title,
				'href'  => add_query_arg( Links::QUERY_JOIN, $on ? 'off' : 'on', self::current_url( true ) ),
				'meta'  => array( 'title' => $on ? __( 'Turn review mode off', 'mna-feedback' ) : __( 'Turn review mode on', 'mna-feedback' ) ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'mna-feedback',
				'id'     => 'mna-feedback-toggle',
				'title'  => $on ? esc_html__( 'Turn review mode off', 'mna-feedback' ) : esc_html__( 'Turn review mode on', 'mna-feedback' ),
				'href'   => add_query_arg( Links::QUERY_JOIN, $on ? 'off' : 'on', self::current_url( true ) ),
			)
		);
		if ( user_can( $user, Capabilities::MANAGE ) ) {
			$bar->add_node(
				array(
					'parent' => 'mna-feedback',
					'id'     => 'mna-feedback-admin',
					'title'  => esc_html__( 'Links, people and settings', 'mna-feedback' ),
					'href'   => admin_url( 'admin.php?page=mna-feedback' ),
				)
			);
		}
	}
}
