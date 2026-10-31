<?php
/**
 * Plugin Name: Poolhall skip link target
 * Description: Gives the theme's "Skip to content" link somewhere to land: a focusable marker straight after the Elementor header.
 */
add_action( 'elementor/theme/after_do_header', static function () {
	echo '<div id="content" tabindex="-1" style="outline:none"></div>' . "\n";
} );
