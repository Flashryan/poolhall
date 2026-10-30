<?php
/**
 * The SEO menu: an overview of every page, site settings, redirects and the 404 log.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', static function (): void {
	add_menu_page( 'SEO', 'SEO', 'manage_options', 'phseo', 'phseo_page_overview', 'dashicons-search', 81 );
	add_submenu_page( 'phseo', 'SEO overview', 'Overview', 'manage_options', 'phseo', 'phseo_page_overview' );
	add_submenu_page( 'phseo', 'SEO settings', 'Settings', 'manage_options', 'phseo-settings', 'phseo_page_settings' );
	add_submenu_page( 'phseo', 'Redirects', 'Redirects', 'manage_options', 'phseo-redirects', 'phseo_page_redirects' );
	add_submenu_page( 'phseo', 'Pages not found', '404 log', 'manage_options', 'phseo-404', 'phseo_page_404' );
} );

add_action( 'admin_notices', static function (): void {
	$other = phseo_other_seo_plugin();
	if ( $other && current_user_can( 'manage_options' ) ) {
		printf( '<div class="notice notice-warning"><p><strong>Poolhall SEO</strong> is standing back because %s is active. Use one SEO plugin at a time.</p></div>', esc_html( $other ) );
	}
	if ( current_user_can( 'manage_options' ) && '0' === (string) get_option( 'blog_public' ) && isset( $_GET['page'] ) && 0 === strpos( sanitize_key( $_GET['page'] ), 'phseo' ) ) {
		echo '<div class="notice notice-error"><p><strong>Search engines are blocked site-wide</strong> (Settings &rarr; Reading &rarr; &ldquo;Discourage search engines&rdquo;). Right for a staging site; switch it off at launch.</p></div>';
	}
} );

function phseo_admin_notice_from_query(): void {
	if ( empty( $_GET['phseo_msg'] ) ) {
		return;
	}
	$msg  = sanitize_text_field( wp_unslash( $_GET['phseo_msg'] ) );
	$type = empty( $_GET['phseo_err'] ) ? 'success' : 'error';
	printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $msg ) );
}

function phseo_back( string $page, string $msg, bool $error = false, array $extra = array() ): void {
	wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page, 'phseo_msg' => rawurlencode( $msg ), 'phseo_err' => $error ? 1 : 0 ), $extra ), admin_url( 'admin.php' ) ) );
	exit;
}

/* ---------------------------------------------------------------- Overview */

function phseo_page_overview(): void {
	$posts = get_posts( array( 'post_type' => phseo_post_types(), 'post_status' => 'publish', 'numberposts' => 500, 'orderby' => array( 'post_type' => 'ASC', 'title' => 'ASC' ) ) );
	$issues = 0;
	echo '<div class="wrap phseo-wrap"><h1>SEO overview</h1>';
	echo '<p>Every published page, post and job, with the title and description Google is given. <em>Auto</em> means it is written from the page itself; edit any page and use its SEO box to write your own.</p>';
	echo '<table class="widefat striped phseo-table"><thead><tr><th>Page</th><th>Type</th><th>Title</th><th>Description</th><th>Search</th></tr></thead><tbody>';
	foreach ( $posts as $post ) {
		$title = phseo_title_for( $post );
		$desc  = phseo_description_for( $post );
		$own_t = '' !== (string) get_post_meta( $post->ID, PHSEO_META_TITLE, true );
		$own_d = '' !== (string) get_post_meta( $post->ID, PHSEO_META_DESCRIPTION, true );
		$noidx = (bool) get_post_meta( $post->ID, PHSEO_META_NOINDEX, true );
		$t_len = mb_strlen( $title );
		$d_len = mb_strlen( $desc );
		$t_flag = $t_len > 60 ? 'warn' : 'ok';
		$d_flag = 0 === $d_len ? 'bad' : ( $d_len < 70 || $d_len > 160 ? 'warn' : 'ok' );
		if ( ! $noidx && ( 'ok' !== $t_flag || 'ok' !== $d_flag ) ) {
			$issues++;
		}
		printf(
			'<tr><td><a href="%s"><strong>%s</strong></a><br><a href="%s" class="phseo-muted">%s</a></td><td>%s</td><td><span class="phseo-flag phseo-flag--%s">%d</span> %s%s</td><td><span class="phseo-flag phseo-flag--%s">%d</span> %s%s</td><td>%s</td></tr>',
			esc_url( get_edit_post_link( $post->ID ) ?: '#' ),
			esc_html( get_the_title( $post ) ?: '(no title)' ),
			esc_url( get_permalink( $post ) ),
			esc_html( wp_make_link_relative( get_permalink( $post ) ) ),
			esc_html( get_post_type_object( $post->post_type )->labels->singular_name ?? $post->post_type ),
			esc_attr( $t_flag ), $t_len, esc_html( $title ), $own_t ? '' : ' <span class="phseo-auto">Auto</span>',
			esc_attr( $d_flag ), $d_len, esc_html( $desc ?: '— none —' ), $own_d ? '' : ' <span class="phseo-auto">Auto</span>',
			$noidx ? '<span class="phseo-flag phseo-flag--warn">Hidden</span>' : 'Shown'
		);
	}
	echo '</tbody></table>';
	printf( '<p>%d pages could do with a shorter title or a better description (numbers are character counts: titles up to 60, descriptions 70&ndash;160).</p></div>', (int) $issues );
}

/* ---------------------------------------------------------------- Settings */

function phseo_media_field( string $name, int $id ): void {
	printf(
		'<input type="number" min="0" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="small-text"> <button type="button" class="button phseo-pick-image" data-target="%1$s">Choose image</button> <span class="phseo-image-preview" data-for="%1$s">%4$s</span>',
		esc_attr( $name ), esc_attr( PHSEO_SETTINGS ), esc_attr( (string) $id ), $id ? wp_get_attachment_image( $id, array( 120, 63 ) ) : ''
	);
}

function phseo_page_settings(): void {
	$s = phseo_settings();
	$f = static function ( string $key, string $label, string $help = '', string $type = 'text' ) use ( $s ): void {
		printf( '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>', esc_attr( $key ), esc_html( $label ) );
		if ( 'textarea' === $type ) {
			printf( '<textarea id="%1$s" name="%2$s[%1$s]" rows="3" class="large-text">%3$s</textarea>', esc_attr( $key ), esc_attr( PHSEO_SETTINGS ), esc_textarea( (string) $s[ $key ] ) );
		} else {
			printf( '<input type="%4$s" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">', esc_attr( $key ), esc_attr( PHSEO_SETTINGS ), esc_attr( (string) $s[ $key ] ), esc_attr( $type ) );
		}
		if ( $help ) {
			printf( '<p class="description">%s</p>', wp_kses_post( $help ) );
		}
		echo '</td></tr>';
	};
	echo '<div class="wrap phseo-wrap"><h1>SEO settings</h1><form method="post" action="options.php">';
	settings_fields( 'phseo_settings' );
	echo '<h2>Titles</h2><table class="form-table">';
	$f( 'site_name', 'Site name', 'Added to the end of page titles, e.g. &ldquo;About Us | Poolhall Recruitment&rdquo;.' );
	$f( 'separator', 'Separator', 'Between the page name and the site name.' );
	$f( 'home_title', 'Homepage title', 'The homepage&rsquo;s full title in Google. Leave blank for just the site name.' );
	$f( 'home_description', 'Homepage description', 'The homepage&rsquo;s summary in Google, 120&ndash;155 characters.', 'textarea' );
	echo '<tr><th scope="row">Default share image</th><td>';
	phseo_media_field( 'default_image', (int) $s['default_image'] );
	echo '<p class="description">Used for social shares when a page has no share or featured image. JPG or PNG, ideally 1200&times;630.</p></td></tr>';
	echo '</table><h2>Business details for Google</h2><p>Shown to Google as structured data on the homepage, so it can link the site to the business.</p><table class="form-table">';
	echo '<tr><th scope="row"><label for="org_type">Business type</label></th><td><select id="org_type" name="' . esc_attr( PHSEO_SETTINGS ) . '[org_type]">';
	foreach ( phseo_org_types() as $value => $label ) {
		printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s['org_type'], $value, false ), esc_html( $label ) );
	}
	echo '</select></td></tr>';
	$f( 'org_name', 'Business name' );
	echo '<tr><th scope="row">Logo</th><td>';
	phseo_media_field( 'org_logo', (int) $s['org_logo'] );
	echo '<p class="description">A square PNG or JPG. Leave empty to use the site icon.</p></td></tr>';
	$f( 'phone', 'Phone' );
	$f( 'email', 'Email', '', 'email' );
	$f( 'street', 'Street address' );
	$f( 'locality', 'Town or city' );
	$f( 'region', 'County' );
	$f( 'postcode', 'Postcode' );
	$f( 'country', 'Country code', 'Two letters, e.g. GB.' );
	$f( 'same_as', 'Social profiles', 'One address per line: LinkedIn, Instagram, Google Business Profile and so on.', 'textarea' );
	echo '</table>';
	submit_button();
	echo '</form></div>';
}

/* --------------------------------------------------------------- Redirects */

function phseo_page_redirects(): void {
	$rules   = phseo_redirects();
	$hits    = get_option( 'phseo_redirect_hits', array() );
	$enabled = '1' === get_option( PHSEO_REDIRECTS_ENABLED, '1' );
	$prefill = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
	$action  = esc_url( admin_url( 'admin-post.php' ) );
	echo '<div class="wrap phseo-wrap"><h1>Redirects</h1>';
	phseo_admin_notice_from_query();
	foreach ( phseo_other_redirect_plugins() as $other ) {
		printf( '<div class="notice notice-warning"><p>%s is also active. Two redirect tools can fight over the same address; use one.</p></div>', esc_html( $other ) );
	}
	echo '<p>Send visitors and Google from an old address to the right page. Permanent (301) is right for almost everything: it passes the old page&rsquo;s search ranking to the new one. Use <code>/old-folder/*</code> to redirect a whole folder.</p>';

	echo '<h2>Add a redirect</h2><form method="post" action="' . $action . '" class="phseo-inline-form">';
	wp_nonce_field( 'phseo_redirect_add' );
	echo '<input type="hidden" name="action" value="phseo_redirect_add">';
	printf( '<label>From <input type="text" name="from" value="%s" placeholder="/old-page" required class="regular-text"></label> ', esc_attr( $prefill ) );
	echo '<label>To <input type="text" name="to" placeholder="/new-page/ or https://…" required class="regular-text"></label> ';
	echo '<label>Type <select name="code"><option value="301">Permanent (301)</option><option value="302">Temporary (302)</option></select></label> ';
	submit_button( 'Add redirect', 'primary', 'submit', false );
	echo '</form>';

	printf( '<h2>Redirects (%d)</h2>', count( $rules ) );
	echo '<form method="post" action="' . $action . '" class="phseo-inline-form">';
	wp_nonce_field( 'phseo_redirect_toggle' );
	echo '<input type="hidden" name="action" value="phseo_redirect_toggle">';
	printf( '<p>Redirects are <strong>%s</strong>. ', $enabled ? 'on' : 'off' );
	submit_button( $enabled ? 'Turn all off' : 'Turn all on', 'secondary', 'submit', false );
	echo '</p></form>';
	if ( $rules ) {
		echo '<table class="widefat striped phseo-table"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Used</th><th></th></tr></thead><tbody>';
		foreach ( $rules as $from => $rule ) {
			$h = $hits[ $from ] ?? null;
			$del = wp_nonce_url( add_query_arg( array( 'action' => 'phseo_redirect_delete', 'from' => rawurlencode( (string) $from ) ), admin_url( 'admin-post.php' ) ), 'phseo_redirect_delete' );
			printf(
				'<tr><td><code>%s</code></td><td><a href="%s" target="_blank" rel="noopener">%s</a></td><td>%d</td><td>%s</td><td><a href="%s" class="phseo-delete">Delete</a></td></tr>',
				esc_html( (string) $from ), esc_url( $rule['to'] ), esc_html( wp_make_link_relative( $rule['to'] ) ?: $rule['to'] ), (int) $rule['code'],
				$h ? esc_html( sprintf( '%d× · last %s ago', $h[0], human_time_diff( $h[1] ) ) ) : '<span class="phseo-muted">not yet</span>',
				esc_url( $del )
			);
		}
		echo '</tbody></table>';
	}

	echo '<h2>Import many at once</h2><form method="post" action="' . $action . '">';
	wp_nonce_field( 'phseo_redirect_import' );
	echo '<input type="hidden" name="action" value="phseo_redirect_import">';
	echo '<p>One per line: <code>/old-address, /new-address</code> (optionally <code>, 302</code>). Paste straight from a spreadsheet.</p>';
	echo '<textarea name="rows" rows="6" class="large-text code" placeholder="/old-page, /new-page/"></textarea>';
	submit_button( 'Import', 'secondary' );
	echo '</form>';
	if ( $rules ) {
		$export = implode( "\n", array_map( static fn( $f, $r ) => $f . ', ' . $r['to'] . ', ' . $r['code'], array_keys( $rules ), $rules ) );
		echo '<details><summary>Export as text</summary><textarea readonly rows="6" class="large-text code">' . esc_textarea( $export ) . '</textarea></details>';
	}
	echo '</div>';
}

add_action( 'admin_post_phseo_redirect_add', static function (): void {
	check_admin_referer( 'phseo_redirect_add' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$from = sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) );
	$r    = phseo_redirect_save( $from, sanitize_text_field( wp_unslash( $_POST['to'] ?? '' ) ), (int) ( $_POST['code'] ?? 301 ) );
	if ( is_wp_error( $r ) ) {
		phseo_back( 'phseo-redirects', $r->get_error_message(), true );
	}
	phseo_404_forget( phseo_normalise_path( $from ) );
	phseo_back( 'phseo-redirects', 'Redirect saved.' );
} );

add_action( 'admin_post_phseo_redirect_delete', static function (): void {
	check_admin_referer( 'phseo_redirect_delete' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	phseo_redirect_delete( rawurldecode( sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ) ) );
	phseo_back( 'phseo-redirects', 'Redirect deleted.' );
} );

add_action( 'admin_post_phseo_redirect_toggle', static function (): void {
	check_admin_referer( 'phseo_redirect_toggle' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$on = '1' === get_option( PHSEO_REDIRECTS_ENABLED, '1' );
	update_option( PHSEO_REDIRECTS_ENABLED, $on ? '0' : '1', true );
	phseo_back( 'phseo-redirects', $on ? 'Redirects switched off.' : 'Redirects switched on.' );
} );

add_action( 'admin_post_phseo_redirect_import', static function (): void {
	check_admin_referer( 'phseo_redirect_import' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$ok = 0;
	$errors = array();
	foreach ( preg_split( '/\R/', (string) wp_unslash( $_POST['rows'] ?? '' ) ) as $n => $line ) {
		$cols = array_map( 'trim', str_getcsv( str_replace( "\t", ',', $line ) ) );
		if ( count( $cols ) < 2 || '' === $cols[0] ) {
			continue;
		}
		$r = phseo_redirect_save( sanitize_text_field( $cols[0] ), sanitize_text_field( $cols[1] ), (int) ( $cols[2] ?? 301 ) ?: 301 );
		if ( is_wp_error( $r ) ) {
			$errors[] = 'line ' . ( $n + 1 ) . ': ' . $r->get_error_message();
		} else {
			$ok++;
			phseo_404_forget( phseo_normalise_path( $cols[0] ) );
		}
	}
	$msg = sprintf( '%d redirects imported.', $ok ) . ( $errors ? ' Skipped ' . implode( '; ', array_slice( $errors, 0, 5 ) ) . ( count( $errors ) > 5 ? '…' : '' ) : '' );
	phseo_back( 'phseo-redirects', $msg, (bool) $errors && ! $ok );
} );

/* ----------------------------------------------------------------- 404 log */

function phseo_page_404(): void {
	$log = phseo_404_log();
	uasort( $log, static fn( $a, $b ) => ( $b['hits'] <=> $a['hits'] ) ?: ( ( $b['last'] ?? 0 ) <=> ( $a['last'] ?? 0 ) ) );
	echo '<div class="wrap phseo-wrap"><h1>Pages not found</h1>';
	phseo_admin_notice_from_query();
	echo '<p>Addresses people (and Google) tried that do not exist. Redirect the ones that matter to the right page, especially soon after launching a new site.</p>';
	if ( ! $log ) {
		echo '<p><em>Nothing logged yet.</em></p></div>';
		return;
	}
	echo '<table class="widefat striped phseo-table"><thead><tr><th>Address</th><th>Times</th><th>Last</th><th>Came from</th><th></th></tr></thead><tbody>';
	foreach ( $log as $path => $e ) {
		printf(
			'<tr><td><code>%s</code></td><td>%d</td><td>%s ago</td><td>%s</td><td><a class="button button-small" href="%s">Redirect…</a></td></tr>',
			esc_html( (string) $path ), (int) $e['hits'], esc_html( human_time_diff( (int) ( $e['last'] ?? time() ) ) ),
			$e['referrer'] ? '<a href="' . esc_url( $e['referrer'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_parse_url( $e['referrer'], PHP_URL_HOST ) ) . '</a>' : '<span class="phseo-muted">—</span>',
			esc_url( add_query_arg( array( 'page' => 'phseo-redirects', 'from' => rawurlencode( (string) $path ) ), admin_url( 'admin.php' ) ) )
		);
	}
	echo '</tbody></table>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'phseo_404_clear' );
	echo '<input type="hidden" name="action" value="phseo_404_clear">';
	submit_button( 'Clear the log', 'secondary' );
	echo '</form></div>';
}

add_action( 'admin_post_phseo_404_clear', static function (): void {
	check_admin_referer( 'phseo_404_clear' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	update_option( PHSEO_404_LOG, array(), false );
	phseo_back( 'phseo-404', 'Log cleared.' );
} );
