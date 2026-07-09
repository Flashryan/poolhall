/**
 * Candidate document forms: signature pad, DOB->age, health-question
 * exclusivity and small input niceties. Loaded only on pages rendering a
 * poolhall-document-form Gravity Form; signature_pad.js is vendored
 * locally (no CDN).
 */
( function () {
	'use strict';

	function initSignature( wrap ) {
		if ( wrap.dataset.phSigReady || typeof window.SignaturePad === 'undefined' ) { return; }
		wrap.dataset.phSigReady = '1';

		var canvas = wrap.querySelector( '.ph-sig-pad' );
		var hidden = wrap.querySelector( '[data-ph-sig-value]' );
		var typed = wrap.querySelector( '[data-ph-sig-typed]' );
		var clearBtn = wrap.querySelector( '[data-ph-sig-clear]' );
		if ( ! canvas || ! hidden ) { return; }

		var pad = new window.SignaturePad( canvas, { penColor: '#0B2846' } );

		function resize() {
			var ratio = Math.max( window.devicePixelRatio || 1, 1 );
			var data = pad.toData();
			canvas.width = canvas.offsetWidth * ratio;
			canvas.height = 160 * ratio;
			canvas.getContext( '2d' ).scale( ratio, ratio );
			pad.fromData( data );
		}
		resize();
		window.addEventListener( 'resize', resize );

		function sync() {
			if ( ! pad.isEmpty() ) {
				hidden.value = JSON.stringify( { method: 'drawn', data: pad.toDataURL( 'image/png' ) } );
				if ( typed ) { typed.value = ''; }
			} else if ( typed && typed.value.trim().length > 2 ) {
				hidden.value = JSON.stringify( { method: 'typed', data: typed.value.trim() } );
			} else {
				hidden.value = '';
			}
		}

		if ( typeof pad.addEventListener === 'function' ) {
			pad.addEventListener( 'endStroke', sync );
		}
		// Belt-and-braces: some builds/browsers miss the pad's own event.
		[ 'pointerup', 'mouseup', 'touchend' ].forEach( function ( ev ) {
			canvas.addEventListener( ev, function () { setTimeout( sync, 30 ); } );
		} );
		if ( typed ) {
			typed.addEventListener( 'input', function () {
				if ( typed.value.trim().length > 0 && ! pad.isEmpty() ) { pad.clear(); }
				sync();
			} );
		}
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				pad.clear();
				sync();
			} );
		}
		var form = wrap.closest( 'form' );
		if ( form ) { form.addEventListener( 'submit', sync ); }
	}

	function initAge( scope ) {
		var dob = scope.querySelector( '.ph-dob input' );
		var age = scope.querySelector( '.ph-age input' );
		if ( ! dob || ! age ) { return; }
		function calc() {
			var m = dob.value.match( /^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/ );
			if ( ! m ) { return; }
			var birth = new Date( +m[ 3 ], +m[ 2 ] - 1, +m[ 1 ] );
			if ( birth > new Date() ) { return; }
			var years = Math.floor( ( Date.now() - birth.getTime() ) / 31557600000 );
			if ( years >= 0 && years < 120 ) {
				age.value = years;
				var note = dob.closest( '.gfield' ).querySelector( '.ph-under18' );
				if ( years < 18 && ! note ) {
					note = document.createElement( 'p' );
					note.className = 'ph-under18';
					note.textContent = 'You appear to be under 18 — a member of the team will discuss suitable roles with you.';
					dob.closest( '.gfield' ).appendChild( note );
				} else if ( years >= 18 && note ) {
					note.remove();
				}
			}
		}
		dob.addEventListener( 'change', calc );
		dob.addEventListener( 'blur', calc );
	}

	function initHealthExclusive( scope ) {
		var group = scope.querySelector( '.ph-health' );
		if ( ! group ) { return; }
		var boxes = group.querySelectorAll( 'input[type=checkbox]' );
		var none = null;
		boxes.forEach( function ( b ) {
			var label = ( b.closest( '.gchoice' ) || b.parentElement ).textContent || '';
			if ( label.indexOf( 'None of the above' ) !== -1 ) { none = b; }
		} );
		if ( ! none ) { return; }
		boxes.forEach( function ( b ) {
			b.addEventListener( 'change', function () {
				if ( b === none && none.checked ) {
					boxes.forEach( function ( o ) { if ( o !== none ) { o.checked = false; o.dispatchEvent( new Event( 'change', { bubbles: true } ) ); } } );
				} else if ( b !== none && b.checked && none.checked ) {
					none.checked = false;
					none.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			} );
		} );
	}

	function initAll() {
		document.querySelectorAll( '[data-ph-signature]' ).forEach( initSignature );
		document.querySelectorAll( '.poolhall-document-form' ).forEach( function ( f ) {
			initAge( f );
			initHealthExclusive( f );
		} );
		// Double-submit prevention.
		document.querySelectorAll( '.poolhall-document-form form, form .poolhall-document-form' ).forEach( function ( el ) {
			var form = el.tagName === 'FORM' ? el : el.closest( 'form' );
			if ( ! form || form.dataset.phGuard ) { return; }
			form.dataset.phGuard = '1';
			form.addEventListener( 'submit', function () {
				var btn = form.querySelector( 'input[type=submit], button[type=submit]' );
				if ( btn ) { setTimeout( function () { btn.disabled = true; }, 10 ); }
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
	// Gravity Forms re-render (validation errors / page steps).
	if ( window.jQuery ) {
		window.jQuery( document ).on( 'gform_post_render', initAll );
	}
}() );
