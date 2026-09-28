/* MNA Feedback — wp-admin helpers: copy buttons, confirmations, logo picker, colour preview. */
( function () {
	'use strict';
	var i18n = window.mnafbAdmin || {};

	document.addEventListener( 'click', function ( event ) {
		var copy = event.target.closest( '.mnafb-copy' );
		if ( copy ) {
			var text = copy.getAttribute( 'data-copy' ) || '';
			var done = function () {
				copy.textContent = i18n.copied || 'Copied';
				setTimeout( function () { copy.textContent = i18n.copy || 'Copy'; }, 1800 );
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( done, function () { fallbackCopy( copy, text, done ); } );
			} else {
				fallbackCopy( copy, text, done );
			}
			return;
		}

		var choose = event.target.closest( '.mnafb-logo-choose' );
		if ( choose && window.wp && wp.media ) {
			event.preventDefault();
			var field = choose.closest( '.mnafb-logo-field' );
			var frame = wp.media( {
				title: i18n.chooseLogo || 'Choose a logo',
				button: { text: i18n.useLogo || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;
				field.querySelector( 'input[name="logo_id"]' ).value = attachment.id;
				var img = field.querySelector( '.mnafb-logo-preview' );
				img.src = url;
				img.hidden = false;
				field.querySelector( '.mnafb-logo-remove' ).hidden = false;
			} );
			frame.open();
			return;
		}

		var remove = event.target.closest( '.mnafb-logo-remove' );
		if ( remove ) {
			event.preventDefault();
			var wrap = remove.closest( '.mnafb-logo-field' );
			wrap.querySelector( 'input[name="logo_id"]' ).value = '0';
			wrap.querySelector( '.mnafb-logo-preview' ).hidden = true;
			remove.hidden = true;
		}
	} );

	function fallbackCopy( button, text, done ) {
		var input = button.parentNode.querySelector( '.mnafb-copy-input' );
		if ( input ) {
			input.focus();
			input.select();
			try { document.execCommand( 'copy' ); done(); } catch ( e ) { /* The address stays selected for manual copying. */ }
		}
	}

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var key = form.getAttribute && form.getAttribute( 'data-confirm' );
		if ( key && ! window.confirm( i18n[ key ] || 'Are you sure?' ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'focusin', function ( event ) {
		if ( event.target.classList && event.target.classList.contains( 'mnafb-copy-input' ) ) {
			event.target.select();
		}
	} );

	var accent = document.getElementById( 'mnafb-accent' );
	if ( accent ) {
		accent.addEventListener( 'input', function () {
			var code = document.querySelector( '.mnafb-accent-value' );
			if ( code ) { code.textContent = accent.value.toUpperCase(); }
		} );
	}
} )();
