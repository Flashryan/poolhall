/*
 * Poolhall — progressive enhancement only. Every behaviour here has a
 * working no-JS fallback (GET forms with submit buttons), so this script
 * just makes the jobs filters and the mobile filter drawer feel quicker.
 */
( function () {
	'use strict';

	// Auto-submit a control's form on change (sort selects, filter radios,
	// salary select). Falls back to the visible submit button without JS.
	document.addEventListener( 'change', function ( event ) {
		var el = event.target;
		if ( el && el.matches && el.matches( '[data-ph-autosubmit]' ) && el.form ) {
			el.form.submit();
		}
	} );

	// Mobile filter drawer.
	var jobs = document.querySelector( '.ph-jobs' );
	if ( ! jobs ) {
		return;
	}
	var panel   = jobs.querySelector( '.ph-filter-panel' );
	var overlay = jobs.querySelector( '.ph-drawer-overlay' );
	var opener  = null;

	function open( trigger ) {
		opener = trigger || null;
		jobs.classList.add( 'is-filters-open' );
		if ( overlay ) {
			overlay.hidden = false;
		}
		document.body.style.overflow = 'hidden';
		if ( panel ) {
			var focusable = panel.querySelector( '[data-ph-filters-close]' );
			if ( focusable ) {
				focusable.focus();
			}
		}
	}

	function close() {
		jobs.classList.remove( 'is-filters-open' );
		if ( overlay ) {
			overlay.hidden = true;
		}
		document.body.style.overflow = '';
		if ( opener && opener.focus ) {
			opener.focus();
		}
		opener = null;
	}

	document.addEventListener( 'click', function ( event ) {
		var openBtn = event.target.closest ? event.target.closest( '[data-ph-filters-open]' ) : null;
		if ( openBtn ) {
			event.preventDefault();
			open( openBtn );
			return;
		}
		if ( event.target.closest && event.target.closest( '[data-ph-filters-close]' ) ) {
			event.preventDefault();
			close();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && jobs.classList.contains( 'is-filters-open' ) ) {
			close();
		}
	} );
}() );

/*
 * Application popup (build reference: applypopupjourney.html). Two-step
 * journey inside a modal, submitting by fetch to the poolhall_apply
 * endpoint. Progressive enhancement: without JS the Apply button is a real
 * link to the contact page, so candidates always have a route.
 */
( function () {
	'use strict';
	var overlay = document.querySelector( '[data-ph-apply-overlay]' );
	if ( ! overlay ) {
		return;
	}
	var form    = overlay.querySelector( '[data-ph-apply-form]' );
	var success = overlay.querySelector( '[data-ph-apply-success]' );
	var progress = overlay.querySelector( '.ph-modal__progress' );
	var bar     = overlay.querySelector( '[data-ph-progress]' );
	var steps   = overlay.querySelectorAll( '[data-ph-step]' );
	var panel1  = overlay.querySelector( '[data-ph-panel="1"]' );
	var panel2  = overlay.querySelector( '[data-ph-panel="2"]' );
	var cv      = overlay.querySelector( '[data-ph-cv]' );
	var dz      = overlay.querySelector( '[data-ph-dropzone]' );
	var dzLabel = overlay.querySelector( '[data-ph-dropzone-label]' );
	var submitBtn = overlay.querySelector( '[data-ph-submit]' );
	var opener  = null;
	var messages = {};
	try { messages = JSON.parse( form.getAttribute( 'data-messages' ) || '{}' ); } catch ( e ) { messages = {}; }

	function val( name ) {
		var el = form.querySelector( '[name="' + name + '"]' );
		return el ? el.value.trim() : '';
	}
	function setError( key, code ) {
		var box = overlay.querySelector( '[data-ph-error="' + key + '"]' );
		if ( box ) {
			box.textContent = code ? ( messages[ code ] || '' ) : '';
			box.hidden = ! code;
		}
		var field = form.querySelector( '[name="' + key + '"]' );
		if ( field ) { field.classList.toggle( 'ph-field--invalid-input', !! code ); }
	}
	function step( n ) {
		panel1.hidden = n !== 1;
		panel2.hidden = n !== 2;
		if ( bar ) { bar.style.width = n === 1 ? '50%' : '100%'; }
		steps.forEach( function ( s ) {
			var sn = parseInt( s.getAttribute( 'data-ph-step' ), 10 );
			s.classList.toggle( 'is-on', sn === n );
			s.classList.toggle( 'is-done', sn < n );
		} );
	}
	function fillContext( t ) {
		var map = {
			'sector': 'data-ph-job-sector', 'title': 'data-ph-job-title',
			'salary': 'data-ph-job-salary', 'ref': 'data-ph-job-ref', 'location': 'data-ph-job-location'
		};
		Object.keys( map ).forEach( function ( k ) {
			var v = t.getAttribute( 'data-job-' + k ) || '';
			var el = overlay.querySelector( '[' + map[ k ] + ']' );
			if ( el ) { el.textContent = v; }
			var row = overlay.querySelector( '[data-ph-job-' + k + '-row]' );
			if ( row ) { row.hidden = ! v; }
		} );
		var idField = form.querySelector( '[data-ph-job-id-field]' );
		if ( idField ) { idField.value = t.getAttribute( 'data-job-id' ) || ''; }
	}
	function reset() {
		form.reset();
		form.hidden = false;
		if ( progress ) { progress.hidden = false; }
		success.hidden = true;
		if ( dz ) { dz.classList.remove( 'has-file' ); }
		if ( dzLabel ) { dzLabel.textContent = 'Drag and drop or browse'; }
		[ 'first_name', 'last_name', 'email', 'phone', 'cv', 'consent' ].forEach( function ( k ) { setError( k, null ); } );
		step( 1 );
	}
	function open( trigger ) {
		opener = trigger;
		reset();
		fillContext( trigger );
		overlay.hidden = false;
		document.body.style.overflow = 'hidden';
		var first = form.querySelector( '[name="first_name"]' );
		if ( first ) { setTimeout( function () { first.focus(); }, 120 ); }
	}
	function close() {
		overlay.hidden = true;
		document.body.style.overflow = '';
		if ( opener && opener.focus ) { opener.focus(); }
		opener = null;
	}
	function validStep1() {
		var ok = true;
		if ( ! val( 'first_name' ) ) { setError( 'first_name', 'first_name_required' ); ok = false; } else { setError( 'first_name', null ); }
		if ( ! val( 'last_name' ) ) { setError( 'last_name', 'last_name_required' ); ok = false; } else { setError( 'last_name', null ); }
		if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( val( 'email' ) ) ) { setError( 'email', 'email_invalid' ); ok = false; } else { setError( 'email', null ); }
		if ( ! val( 'phone' ) ) { setError( 'phone', 'phone_required' ); ok = false; } else { setError( 'phone', null ); }
		return ok;
	}
	function validStep2() {
		var ok = true;
		if ( ! cv || ! cv.files || ! cv.files.length ) { setError( 'cv', 'cv_required' ); ok = false; }
		else if ( cv.files[ 0 ].size > 25 * 1024 * 1024 ) { setError( 'cv', 'cv_too_large' ); ok = false; }
		else { setError( 'cv', null ); }
		var consent = form.querySelector( '[data-ph-consent]' );
		if ( ! consent || ! consent.checked ) { setError( 'consent', 'consent_required' ); ok = false; } else { setError( 'consent', null ); }
		return ok;
	}

	if ( cv ) {
		cv.addEventListener( 'change', function () {
			if ( cv.files && cv.files.length ) {
				dz.classList.add( 'has-file' );
				dzLabel.textContent = cv.files[ 0 ].name;
				setError( 'cv', null );
			}
		} );
		[ 'dragover', 'dragenter' ].forEach( function ( ev ) { dz.addEventListener( ev, function ( e ) { e.preventDefault(); dz.classList.add( 'is-drag' ); } ); } );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) { dz.addEventListener( ev, function ( e ) { e.preventDefault(); dz.classList.remove( 'is-drag' ); } ); } );
		dz.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer && e.dataTransfer.files.length ) { cv.files = e.dataTransfer.files; cv.dispatchEvent( new Event( 'change' ) ); }
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest ? e.target.closest( '[data-ph-apply]' ) : null;
		if ( trigger ) { e.preventDefault(); open( trigger ); return; }
		if ( e.target.closest && e.target.closest( '[data-ph-apply-close]' ) ) { e.preventDefault(); close(); return; }
		if ( e.target === overlay ) { close(); return; }
		if ( e.target.closest && e.target.closest( '[data-ph-next]' ) ) { if ( validStep1() ) { step( 2 ); } return; }
		if ( e.target.closest && e.target.closest( '[data-ph-back]' ) ) { step( 1 ); }
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! overlay.hidden ) { close(); }
	} );

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		if ( form.querySelector( '[name="company_url"]' ).value ) { return; } // honeypot
		if ( ! validStep2() ) { return; }
		submitBtn.disabled = true;
		var original = submitBtn.textContent;
		submitBtn.textContent = 'Submitting…';
		var payload = new FormData( form );
		// The host WAF rejects multipart uploads whose filename contains
		// quote characters (403 before PHP runs), and real CVs are often
		// named like "Ryan's CV.docx". Transmit under a sanitised name;
		// the original is only used to label the email attachment.
		var cvInput = form.querySelector( '[name="cv"]' );
		if ( cvInput && cvInput.files && cvInput.files[0] ) {
			var cvFile = cvInput.files[0];
			var safeName = cvFile.name.replace( /[^\w .()-]+/g, '-' );
			if ( safeName !== cvFile.name ) { payload.set( 'cv', cvFile, safeName ); }
		}
		var httpStatus = 0;
		// form.action is shadowed by the hidden <input name="action"> the
		// admin-post contract requires, so it resolves to the INPUT ELEMENT
		// (and a garbage URL). Read the attribute, never the property.
		var endpoint = form.getAttribute( 'action' );
		fetch( endpoint, { method: 'POST', body: payload, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' } )
			.then( function ( r ) { httpStatus = r.status; return r.json(); } )
			.then( function ( data ) {
				if ( data.status === 'sent' || data.status === 'received' ) {
					form.hidden = true;
					if ( progress ) { progress.hidden = true; }
					var name = val( 'first_name' ) || 'there';
					var txt = overlay.querySelector( '[data-ph-success-text]' );
					if ( txt ) { txt.textContent = 'Thanks ' + name + '! Your application is on its way to our team. We will be in touch within two working days.'; }
					success.hidden = false;
				} else if ( data.status === 'rate_limited' ) {
					setError( 'cv', null );
					submitBtn.disabled = false; submitBtn.textContent = original;
					alert( 'Too many applications from this connection just now. Please try again shortly, or call 0121 516 3000.' );
				} else {
					var fieldCodes = ( data.errors || [] ).filter( function ( code ) { return code !== 'system'; } );
					fieldCodes.forEach( function ( code ) {
						if ( code.indexOf( 'cv' ) === 0 ) { setError( 'cv', code ); }
						else if ( code === 'consent_required' ) { setError( 'consent', code ); }
						else if ( code === 'email_invalid' ) { setError( 'email', code ); step( 1 ); }
						else if ( code.indexOf( 'first_name' ) === 0 || code.indexOf( 'last_name' ) === 0 || code === 'phone_required' ) { setError( code.replace( '_required', '' ).replace( '_invalid', '' ), code ); step( 1 ); }
					} );
					submitBtn.disabled = false; submitBtn.textContent = original;
					if ( ( data.errors || [] ).indexOf( 'system' ) !== -1 && fieldCodes.length === 0 ) {
						alert( 'Sorry, something went wrong at our end and your application was not sent. Please try again in a minute, or call 0121 516 3000. (ref: server)' );
					}
				}
			} )
			.catch( function () {
				submitBtn.disabled = false; submitBtn.textContent = original;
				var ref = httpStatus > 0 ? 'HTTP ' + httpStatus : 'network';
				alert( 'Sorry, something went wrong sending your application. Please try again, or call 0121 516 3000. (ref: ' + ref + ')' );
			} );
	} );
}() );

/**
 * v2 four-stage hero (build reference: blocks.jsx Hero). Cross-fades the
 * four world slides every 4.2s; markers jump on click; honours
 * prefers-reduced-motion by staying on the first frame.
 */
( function () {
	var hero = document.querySelector( '[data-ph-hero]' );
	if ( ! hero ) { return; }
	var slides  = hero.querySelectorAll( '.hero-slide' );
	var worlds  = hero.querySelectorAll( '[data-ph-world]' );
	var active  = 0;
	var timer   = null;
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	function show( i ) {
		active = i % slides.length;
		slides.forEach( function ( s, k ) { s.classList.toggle( 'on', k === active ); } );
		worlds.forEach( function ( w, k ) { w.classList.toggle( 'on', k === active ); } );
	}
	function play() {
		if ( reduced ) { return; }
		clearInterval( timer );
		timer = setInterval( function () { show( active + 1 ); }, 4200 );
	}
	worlds.forEach( function ( w ) {
		w.addEventListener( 'click', function () {
			show( parseInt( w.getAttribute( 'data-ph-world' ), 10 ) );
			play();
		} );
	} );
	play();
}() );

/**
 * v2 carousel arrows: scroll the snap track by one card.
 */
( function () {
	document.querySelectorAll( '[data-ph-carousel]' ).forEach( function ( c ) {
		var track = c.querySelector( '.carousel-track' );
		if ( ! track ) { return; }
		var step = function () {
			var card = track.firstElementChild;
			return card ? card.getBoundingClientRect().width + 22 : 380;
		};
		var prev = c.querySelector( '[data-ph-carousel-prev]' );
		var next = c.querySelector( '[data-ph-carousel-next]' );
		if ( prev ) { prev.addEventListener( 'click', function () { track.scrollBy( { left: -step(), behavior: 'smooth' } ); } ); }
		if ( next ) { next.addEventListener( 'click', function () { track.scrollBy( { left: step(), behavior: 'smooth' } ); } ); }
	} );
}() );

/**
 * v2 mobile drawer (header burger).
 */
( function () {
	var open    = document.querySelector( '[data-ph-drawer-open]' );
	var drawer  = document.querySelector( '[data-ph-drawer]' );
	var overlay = document.querySelector( '[data-ph-drawer-overlay]' );
	if ( ! open || ! drawer ) { return; }
	function set( on ) {
		drawer.classList.toggle( 'open', on );
		if ( overlay ) { overlay.classList.toggle( 'open', on ); }
	}
	open.addEventListener( 'click', function () { set( true ); } );
	drawer.querySelectorAll( '[data-ph-drawer-close]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { set( false ); } ); } );
	if ( overlay ) { overlay.addEventListener( 'click', function () { set( false ); } ); }
	document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key ) { set( false ); } } );
}() );

/**
 * Back-to-top control (bottom right, after ~600px of scroll).
 */
( function () {
	var btn = document.querySelector( '[data-ph-backtop]' );
	if ( ! btn ) { return; }
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	function sync() { btn.classList.toggle( 'on', window.scrollY > 600 ); }
	window.addEventListener( 'scroll', sync, { passive: true } );
	sync();
	btn.addEventListener( 'click', function () {
		window.scrollTo( { top: 0, behavior: reduced ? 'auto' : 'smooth' } );
	} );
}() );
