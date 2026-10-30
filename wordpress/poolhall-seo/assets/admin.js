( function ( $ ) {
	'use strict';

	// Character counts and the live search preview in the SEO box.
	function count( field ) {
		var $f = $( '#' + field );
		var $c = $( '.phseo-count[data-for="' + field + '"]' );
		if ( ! $f.length || ! $c.length ) { return; }
		var len = ( $f.val() || $f.attr( 'placeholder' ) || '' ).length;
		$c.text( len + ' / ' + $c.data( 'max' ) ).toggleClass( 'is-over', len > +$c.data( 'max' ) );
	}
	function preview() {
		var $t = $( '.phseo-preview__title' ), $d = $( '.phseo-preview__desc' );
		$t.text( $( '#phseo-title' ).val() || $t.data( 'auto' ) );
		$d.text( $( '#phseo-description' ).val() || $d.data( 'auto' ) );
	}
	$( document ).on( 'input', '#phseo-title, #phseo-description', function () { count( this.id ); preview(); } );
	$( function () { count( 'phseo-title' ); count( 'phseo-description' ); } );

	// Media picker for share images and the logo.
	$( document ).on( 'click', '.phseo-pick-image', function ( e ) {
		e.preventDefault();
		var target = $( this ).data( 'target' );
		var frame = wp.media( { title: 'Choose image', library: { type: 'image' }, multiple: false } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			$( '#' + target ).val( a.id );
			var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
			$( '.phseo-image-preview[data-for="' + target + '"]' ).html( '<img src="' + url + '" width="120" alt="">' );
		} );
		frame.open();
	} );
}( jQuery ) );
