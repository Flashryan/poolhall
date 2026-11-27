/**
 * Candidate document forms: signature pad, DOB->age, health-question
 * exclusivity, the starter checklist's answer summary and small input
 * niceties. Loaded only on pages rendering a
 * poolhall-document-form Gravity Form; signature_pad.js is vendored
 * locally (no CDN).
 */
( function () {
	'use strict';

	function initSignature( wrap ) {
		if ( wrap.dataset.phSigReady ) { return; }

		var canvas = wrap.querySelector( '.ph-sig-pad' );
		var hidden = wrap.querySelector( '[data-ph-sig-value]' );
		var typed = wrap.querySelector( '[data-ph-sig-typed]' );
		var clearBtn = wrap.querySelector( '[data-ph-sig-clear]' );
		var hint = wrap.querySelector( '.ph-sig-hint' );
		var pad = null;
		if ( ! hidden ) { return; }

		// Gravity Forms can render a future or conditional page at width zero.
		// Wait until the canvas is visible rather than permanently initialising
		// a zero-width pad and marking it ready.
		if ( canvas && typeof window.SignaturePad !== 'undefined' && canvas.offsetWidth < 1 ) {
			if ( ! wrap.dataset.phSigWatching && typeof window.ResizeObserver === 'function' ) {
				wrap.dataset.phSigWatching = '1';
				var observer = new window.ResizeObserver( function () {
					if ( canvas.offsetWidth < 1 ) { return; }
					observer.disconnect();
					delete wrap.dataset.phSigWatching;
					initSignature( wrap );
				} );
				observer.observe( canvas );
			}
			return;
		}
		wrap.dataset.phSigReady = '1';

		function sync() {
			if ( pad && ! pad.isEmpty() ) {
				hidden.value = JSON.stringify( { method: 'drawn', data: pad.toDataURL( 'image/png' ) } );
				if ( typed ) { typed.value = ''; }
			} else if ( typed && typed.value.trim().length > 2 ) {
				hidden.value = JSON.stringify( { method: 'typed', data: typed.value.trim() } );
			} else {
				hidden.value = '';
			}
		}

		if ( typed ) {
			typed.addEventListener( 'input', function () {
				if ( pad && typed.value.trim().length > 0 && ! pad.isEmpty() ) { pad.clear(); }
				sync();
			} );
		}
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				if ( pad ) { pad.clear(); }
				sync();
			} );
		}
		var form = wrap.closest( 'form' );
		if ( form ) { form.addEventListener( 'submit', sync ); }

		// Keep the typed-signature route usable if the drawing library ever
		// fails to load. The build also verifies that the vendored file ships.
		if ( ! canvas || typeof window.SignaturePad === 'undefined' ) {
			if ( canvas ) { canvas.hidden = true; }
			if ( clearBtn ) { clearBtn.hidden = true; }
			if ( hint ) { hint.textContent = 'Drawing is unavailable on this device. Please type your full legal name below.'; }
			return;
		}

		pad = new window.SignaturePad( canvas, { penColor: '#0B2846' } );

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

		if ( typeof pad.addEventListener === 'function' ) {
			pad.addEventListener( 'endStroke', sync );
		}
		// Belt-and-braces: some builds/browsers miss the pad's own event.
		[ 'pointerup', 'mouseup', 'touchend' ].forEach( function ( ev ) {
			canvas.addEventListener( ev, function () { setTimeout( sync, 30 ); } );
		} );
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

	// Tidy an NI number or UK postcode into its usual written form as the
	// candidate leaves the box. The server repeats this on submit.
	function initTidy( scope ) {
		scope.querySelectorAll( '.ph-ni input, .ph-postcode input' ).forEach( function ( input ) {
			if ( input.dataset.phTidy ) { return; }
			input.dataset.phTidy = '1';
			var ni = !! input.closest( '.ph-ni' );
			input.addEventListener( 'blur', function () {
				var c = input.value.replace( ni ? /[^a-z0-9]/gi : /\s+/g, '' ).toUpperCase();
				if ( ni && /^[A-Z]{2}\d{6}[A-D]$/.test( c ) ) {
					input.value = c.replace( /^(..)(..)(..)(..)(.)$/, '$1 $2 $3 $4 $5' );
				} else if ( ! ni && /^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/.test( c ) ) {
					input.value = c.slice( 0, -3 ) + ' ' + c.slice( -3 );
				}
			} );
		} );
	}

	var STATEMENT_LABELS = {
		A: 'Statement A \u2013 standard tax code with your full personal allowance',
		B: 'Statement B \u2013 personal allowance on a week 1/month 1 basis',
		C: 'Statement C \u2013 tax code BR'
	};

	// HMRC starter checklist: fills the "Check your answers" panel on the last
	// step and the "Signing as" line from what the candidate entered earlier.
	// Every step stays in the page (hidden), so nothing has to be fetched.
	function initStarter( scope ) {
		var panel = scope.querySelector( '[data-ph-starter-summary]' );
		var form = panel ? panel.closest( 'form' ) : null;
		if ( ! form ) { return; }

		var map = {};
		( panel.getAttribute( 'data-fields' ) || '' ).split( ';' ).forEach( function ( pair ) {
			var bits = pair.split( '=' );
			if ( bits.length === 2 ) { map[ bits[ 0 ] ] = bits[ 1 ].split( ',' ); }
		} );
		var formId = form.getAttribute( 'data-formid' ) || ( form.id || '' ).replace( 'gform_', '' );

		function id( key ) { return ( map[ key ] || [] )[ 0 ]; }
		function text( fieldId ) {
			var el = form.querySelector( '[name="input_' + fieldId + '"]' );
			return el && el.type !== 'radio' && el.type !== 'file' ? ( el.value || '' ).trim() : '';
		}
		function choice( fieldId ) {
			var el = form.querySelector( 'input[name="input_' + fieldId + '"]:checked' );
			return el ? el.value : '';
		}
		function date( fieldId ) {
			var parts = [];
			form.querySelectorAll( '#field_' + formId + '_' + fieldId + ' input' ).forEach( function ( el ) {
				if ( el.type !== 'hidden' && el.value.trim() ) { parts.push( el.value.trim() ); }
			} );
			return parts.join( '/' );
		}
		function ticked( fieldId ) {
			var out = [];
			form.querySelectorAll( 'input[type=checkbox][name^="input_' + fieldId + '."]:checked' ).forEach( function ( el ) {
				out.push( el.value === 'Not sure' ? 'Not sure of plan type' : el.value );
			} );
			return out;
		}
		// Same rule as the server: HMRC questions 8, 9 then 10.
		function statement() {
			var job = choice( id( 'job' ) ), pension = choice( id( 'pension' ) ), april = choice( id( 'april' ) );
			if ( job === 'Yes' ) { return 'C'; }
			if ( job !== 'No' ) { return ''; }
			if ( pension === 'Yes' ) { return 'C'; }
			if ( pension !== 'No' ) { return ''; }
			return april === 'Yes' ? 'B' : ( april === 'No' ? 'A' : '' );
		}
		function loans() {
			var has = choice( id( 'loan' ) );
			if ( has === 'No' ) { return 'No student or postgraduate loan'; }
			if ( has !== 'Yes' ) { return ''; }
			if ( choice( id( 'exempt' ) ) === 'Yes' ) { return 'No repayments needed for now'; }
			return ticked( id( 'loans' ) ).join( ', ' );
		}
		function name() {
			return ( text( id( 'first' ) ) + ' ' + text( id( 'last' ) ) ).replace( /\s+/g, ' ' ).trim();
		}

		function render() {
			var s = statement();
			var rows = [
				[ 'Name', name(), 1 ],
				[ 'Sex', choice( id( 'sex' ) ), 1 ],
				[ 'Date of birth', date( id( 'dob' ) ), 1 ],
				[ 'National Insurance number', text( id( 'ni' ) ) || 'Not given', 1 ],
				[ 'Home address', ( map.address || [] ).map( text ).filter( Boolean ).join( ', ' ), 1 ],
				[ 'Employment start date', text( id( 'start' ) ), 1 ],
				[ 'Employee statement', s ? STATEMENT_LABELS[ s ] : '', 2 ],
				[ 'Student loans', loans(), 3 ]
			];
			var list = panel.querySelector( '.ph-summary__list' );
			if ( ! list ) {
				list = document.createElement( 'dl' );
				list.className = 'ph-summary__list';
				panel.appendChild( list );
				var fallback = panel.querySelector( '.ph-summary__fallback' );
				if ( fallback ) { fallback.hidden = true; }
			}
			list.textContent = '';
			rows.forEach( function ( row ) {
				var wrap = document.createElement( 'div' );
				var dt = document.createElement( 'dt' );
				var dd = document.createElement( 'dd' );
				var change = document.createElement( 'button' );
				var hidden = document.createElement( 'span' );
				wrap.className = 'ph-summary__row';
				dt.textContent = row[ 0 ];
				dd.textContent = row[ 1 ] || 'Not answered';
				if ( ! row[ 1 ] ) { dd.className = 'is-missing'; }
				change.type = 'button';
				change.className = 'ph-summary__change';
				change.setAttribute( 'data-ph-goto', String( row[ 2 ] ) );
				change.textContent = 'Change';
				hidden.className = 'ph-visually-hidden';
				hidden.textContent = ' ' + row[ 0 ].toLowerCase();
				change.appendChild( hidden );
				wrap.appendChild( dt );
				wrap.appendChild( dd );
				wrap.appendChild( change );
				list.appendChild( wrap );
			} );

			var signer = name().toUpperCase();
			form.querySelectorAll( '[data-ph-starter-signer]' ).forEach( function ( el ) {
				var strong = el.querySelector( 'strong' );
				if ( strong ) { strong.textContent = signer; }
				el.hidden = ! signer;
			} );
		}

		if ( ! panel.dataset.phReady ) {
			panel.dataset.phReady = '1';
			panel.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '[data-ph-goto]' );
				var target = form.querySelector( '#gform_target_page_number_' + formId );
				if ( ! button || ! target ) { return; }
				target.value = button.getAttribute( 'data-ph-goto' );
				// A plain submit skips Gravity Forms' Back handler, which would
				// only go one step back. Going backwards is never validated.
				HTMLFormElement.prototype.submit.call( form );
			} );
			form.addEventListener( 'change', render );
		}
		render();
	}

	function initAll() {
		document.querySelectorAll( '[data-ph-signature]' ).forEach( initSignature );
		document.querySelectorAll( '.poolhall-document-form' ).forEach( function ( f ) {
			initAge( f );
			initHealthExclusive( f );
			initTidy( f );
			initStarter( f );
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
		window.jQuery( document ).on( 'gform_post_render gform_page_loaded', initAll );
	}
}() );
