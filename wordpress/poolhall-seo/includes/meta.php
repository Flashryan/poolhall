<?php
/**
 * Per-page SEO fields: title, description, share image and "hide from search".
 *
 * Every field is optional. Left blank, the page gets a sensible default
 * (see phseo_title(), phseo_description() and phseo_image() in head.php),
 * so nobody has to fill anything in for the site to be in good shape.
 */

defined( 'ABSPATH' ) || exit;

const PHSEO_META_TITLE       = '_phseo_title';
const PHSEO_META_DESCRIPTION = '_phseo_description';
const PHSEO_META_IMAGE       = '_phseo_image';
const PHSEO_META_NOINDEX     = '_phseo_noindex';

/** Public post types that get the SEO box: everything a visitor can land on. */
function phseo_post_types(): array {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'], $types['elementor_library'], $types['e-floating-buttons'] );
	return array_values( (array) apply_filters( 'phseo_post_types', $types ) );
}

add_action( 'init', static function (): void {
	foreach ( phseo_post_types() as $type ) {
		foreach ( array( PHSEO_META_TITLE, PHSEO_META_DESCRIPTION ) as $key ) {
			register_post_meta( $type, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ) ) );
		}
		register_post_meta( $type, PHSEO_META_IMAGE, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint', 'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ) ) );
		register_post_meta( $type, PHSEO_META_NOINDEX, array( 'type' => 'boolean', 'single' => true, 'show_in_rest' => true, 'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ) ) );
	}
}, 20 );

add_action( 'add_meta_boxes', static function (): void {
	foreach ( phseo_post_types() as $type ) {
		add_meta_box( 'phseo-box', 'SEO', 'phseo_render_box', $type, 'normal', 'high' );
	}
} );

function phseo_render_box( WP_Post $post ): void {
	wp_nonce_field( 'phseo_save_' . $post->ID, 'phseo_nonce' );
	$title   = (string) get_post_meta( $post->ID, PHSEO_META_TITLE, true );
	$desc    = (string) get_post_meta( $post->ID, PHSEO_META_DESCRIPTION, true );
	$image   = (int) get_post_meta( $post->ID, PHSEO_META_IMAGE, true );
	$noindex = (bool) get_post_meta( $post->ID, PHSEO_META_NOINDEX, true );
	$auto_t  = phseo_default_title( $post );
	$auto_d  = phseo_auto_description( $post );
	$url     = get_permalink( $post );
	?>
	<div class="phseo-box">
		<p class="phseo-preview" aria-label="Search result preview">
			<span class="phseo-preview__url"><?php echo esc_html( $url ); ?></span>
			<span class="phseo-preview__title" data-auto="<?php echo esc_attr( $auto_t ); ?>"><?php echo esc_html( $title ?: $auto_t ); ?></span>
			<span class="phseo-preview__desc" data-auto="<?php echo esc_attr( $auto_d ); ?>"><?php echo esc_html( $desc ?: $auto_d ); ?></span>
		</p>
		<p>
			<label for="phseo-title"><strong>SEO title</strong> <span class="phseo-count" data-for="phseo-title" data-max="60"></span></label>
			<input type="text" id="phseo-title" name="phseo_title" class="widefat" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $auto_t ); ?>">
			<span class="description">Shown as the blue link in Google and on the browser tab. Aim for under 60 characters. Leave blank to use the placeholder.</span>
		</p>
		<p>
			<label for="phseo-description"><strong>Meta description</strong> <span class="phseo-count" data-for="phseo-description" data-max="155"></span></label>
			<textarea id="phseo-description" name="phseo_description" class="widefat" rows="3" placeholder="<?php echo esc_attr( $auto_d ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
			<span class="description">The summary under the link in Google. Aim for 120&ndash;155 characters. Leave blank to use the placeholder.</span>
		</p>
		<p>
			<label for="phseo-image"><strong>Share image</strong></label><br>
			<input type="number" min="0" id="phseo-image" name="phseo_image" value="<?php echo esc_attr( (string) $image ); ?>" class="small-text">
			<button type="button" class="button phseo-pick-image" data-target="phseo-image">Choose image</button>
			<span class="phseo-image-preview" data-for="phseo-image"><?php echo $image ? wp_get_attachment_image( $image, array( 120, 63 ) ) : ''; ?></span><br>
			<span class="description">Used when the page is shared on LinkedIn, Facebook, WhatsApp and so on. Leave empty to use the featured image or the site default.</span>
		</p>
		<p>
			<label><input type="checkbox" name="phseo_noindex" value="1" <?php checked( $noindex ); ?>> <strong>Hide this page from search engines</strong></label><br>
			<span class="description">Adds &ldquo;noindex&rdquo; and leaves the page out of the sitemap. For thank-you pages, private forms and the like.</span>
		</p>
	</div>
	<?php
}

add_action( 'save_post', static function ( int $post_id, WP_Post $post ): void {
	if ( ! isset( $_POST['phseo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['phseo_nonce'] ) ), 'phseo_save_' . $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$fields = array(
		PHSEO_META_TITLE       => sanitize_text_field( wp_unslash( $_POST['phseo_title'] ?? '' ) ),
		PHSEO_META_DESCRIPTION => sanitize_textarea_field( wp_unslash( $_POST['phseo_description'] ?? '' ) ),
		PHSEO_META_IMAGE       => absint( $_POST['phseo_image'] ?? 0 ),
		PHSEO_META_NOINDEX     => empty( $_POST['phseo_noindex'] ) ? 0 : 1,
	);
	foreach ( $fields as $key => $value ) {
		if ( '' === $value || 0 === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}, 10, 2 );

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
	$screen = get_current_screen();
	$is_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $screen && in_array( $screen->post_type, phseo_post_types(), true );
	$is_settings = false !== strpos( $hook, 'phseo' );
	if ( ! $is_editor && ! $is_settings ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'phseo-admin', plugins_url( 'assets/admin.css', PHSEO_FILE ), array(), PHSEO_VERSION );
	wp_enqueue_script( 'phseo-admin', plugins_url( 'assets/admin.js', PHSEO_FILE ), array( 'jquery' ), PHSEO_VERSION, true );
} );
