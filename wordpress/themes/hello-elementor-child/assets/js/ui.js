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
 * v2 carousels: arrows scroll one snap card. Individual carousels may opt
 * into autoplay with data-ph-carousel-autoplay="milliseconds". Autoplay
 * wraps, pauses for interaction/background tabs and honours reduced motion.
 */
( function () {
	document.querySelectorAll( '[data-ph-carousel]' ).forEach( function ( c ) {
		var track = c.querySelector( '.carousel-track' );
		if ( ! track ) { return; }
		var delay   = parseInt( c.getAttribute( 'data-ph-carousel-autoplay' ) || '0', 10 );
		var media   = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;
		var timer   = null;
		var hovered = false;
		var focused = false;
		var pressed = false;
		var step = function () {
			var card  = track.firstElementChild;
			var style = window.getComputedStyle ? window.getComputedStyle( track ) : null;
			var gap   = style ? parseFloat( style.columnGap || style.gap || '0' ) : 0;
			return card ? card.getBoundingClientRect().width + ( isNaN( gap ) ? 0 : gap ) : 380;
		};
		var stop = function () {
			if ( timer ) { window.clearTimeout( timer ); }
			timer = null;
		};
		var canPlay = function () {
			return delay >= 1000
				&& track.children.length > 1
				&& ! hovered
				&& ! focused
				&& ! pressed
				&& ! document.hidden
				&& ! ( media && media.matches );
		};
		var move = function ( direction ) {
			var amount = step();
			var current = Math.round( track.scrollLeft / amount );
			var last = track.children.length - 1;
			var target = current + direction;
			if ( target > last ) { target = 0; }
			if ( target < 0 ) { target = last; }
			track.scrollTo( { left: target * amount, behavior: 'smooth' } );
		};
		var schedule = function () {
			stop();
			if ( ! canPlay() ) { return; }
			timer = window.setTimeout( function () {
				move( 1 );
				schedule();
			}, delay );
		};
		var interact = function ( direction ) {
			move( direction );
			schedule();
		};
		var prev = c.querySelector( '[data-ph-carousel-prev]' );
		var next = c.querySelector( '[data-ph-carousel-next]' );
		if ( prev ) { prev.addEventListener( 'click', function () { interact( -1 ); } ); }
		if ( next ) { next.addEventListener( 'click', function () { interact( 1 ); } ); }

		if ( delay >= 1000 ) {
			c.addEventListener( 'pointerenter', function () { hovered = true; stop(); } );
			c.addEventListener( 'pointerleave', function () { hovered = false; schedule(); } );
			c.addEventListener( 'focusin', function () { focused = true; stop(); } );
			c.addEventListener( 'focusout', function () {
				window.setTimeout( function () {
					focused = c.contains( document.activeElement );
					schedule();
				}, 0 );
			} );
			track.addEventListener( 'pointerdown', function () { pressed = true; stop(); } );
			window.addEventListener( 'pointerup', function () { pressed = false; schedule(); } );
			document.addEventListener( 'visibilitychange', schedule );
			if ( media && media.addEventListener ) { media.addEventListener( 'change', schedule ); }
			schedule();
		}
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

/**
 * Essential-storage notice. There are no non-essential scripts to gate; the
 * versioned dismissal persists locally and page spacing prevents overlays.
 */
( function () {
	var box = document.querySelector( '[data-ph-cookies]' );
	if ( ! box ) { return; }
	var KEY = 'ph-cookie-notice-v2';
	var root = document.documentElement;
	var body = document.body;
	var dismissed = false;
	try { dismissed = '1' === window.localStorage.getItem( KEY ); } catch ( e ) { dismissed = false; }
	if ( dismissed ) { return; }

	var syncOffset = function () {
		root.style.setProperty( '--ph-cookie-notice-height', Math.ceil( box.getBoundingClientRect().height ) + 'px' );
	};
	var close = function () {
		box.hidden = true;
		body.classList.remove( 'ph-cookie-notice-open' );
		root.style.removeProperty( '--ph-cookie-notice-height' );
		try { window.localStorage.setItem( KEY, '1' ); } catch ( e ) { /* private mode */ }
	};

	box.hidden = false;
	body.classList.add( 'ph-cookie-notice-open' );
	syncOffset();
	window.addEventListener( 'resize', syncOffset, { passive: true } );
	var ok = box.querySelector( '[data-ph-cookies-ok]' );
	if ( ok ) {
		ok.addEventListener( 'click', close );
	}
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && ! box.hidden ) { close(); }
	} );
}() );

/**
 * Save job (single-job aside): client-side bookmark persisted in
 * localStorage, per prototype §9.4 (v1 scope — no account required).
 */
( function () {
	var KEY = 'ph-saved-jobs';
	var btns = document.querySelectorAll( '[data-ph-save]' );
	var toggle = document.querySelector( '[data-ph-saved-filter]' );
	if ( ! btns.length && ! toggle ) { return; }
	var read = function () {
		try { return JSON.parse( window.localStorage.getItem( KEY ) || '[]' ); } catch ( e ) { return []; }
	};
	var write = function ( list ) {
		try { window.localStorage.setItem( KEY, JSON.stringify( list ) ); } catch ( e ) { /* private mode */ }
	};
	var paintCount = function () {
		var n = String( read().length );
		document.querySelectorAll( '[data-ph-saved-count]' ).forEach( function ( el ) { el.textContent = n; } );
	};
	var paint = function ( btn, saved ) {
		btn.classList.toggle( 'is-saved', saved );
		if ( btn.hasAttribute( 'data-ph-save-icon' ) ) {
			btn.setAttribute( 'aria-pressed', saved ? 'true' : 'false' );
			btn.setAttribute( 'aria-label', saved ? 'Remove from saved jobs' : 'Save job' );
		} else {
			btn.textContent = saved ? 'Saved' : 'Save job';
		}
	};
	// Saved-only view on the jobs archive (rows carry data-ph-job-row).
	var applyFilter = function () {
		if ( ! toggle ) { return; }
		var on = toggle.classList.contains( 'is-on' );
		var list = read();
		document.querySelectorAll( '[data-ph-job-row]' ).forEach( function ( row ) {
			row.style.display = ( ! on || list.indexOf( row.getAttribute( 'data-ph-job-row' ) ) !== -1 ) ? '' : 'none';
		} );
		var empty = document.querySelector( '[data-ph-saved-empty]' );
		if ( empty ) { empty.style.display = ( on && ! list.length ) ? '' : 'none'; }
	};
	btns.forEach( function ( btn ) {
		var id = btn.getAttribute( 'data-job-id' );
		paint( btn, read().indexOf( id ) !== -1 );
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var list = read();
			var at = list.indexOf( id );
			if ( at === -1 ) { list.push( id ); } else { list.splice( at, 1 ); }
			write( list );
			paint( btn, at === -1 );
			paintCount();
			applyFilter();
		} );
	} );
	if ( toggle ) {
		toggle.addEventListener( 'click', function () {
			toggle.classList.toggle( 'is-on' );
			toggle.setAttribute( 'aria-pressed', toggle.classList.contains( 'is-on' ) ? 'true' : 'false' );
			applyFilter();
		} );
	}
	paintCount();
}() );

/**
 * Register-form CV upload feedback: when a file is chosen the dashed box
 * flips to a "attached" state with an animated tick and the filename.
 */
( function () {
	var input = document.querySelector( '.ph-v2reg .upload input[type=file]' );
	if ( ! input ) { return; }
	var box = input.closest( '.upload' );
	input.addEventListener( 'change', function () {
		var old = box.querySelector( '.upload-done' );
		if ( old ) { old.remove(); }
		if ( ! input.files || ! input.files.length ) {
			box.classList.remove( 'has-file' );
			return;
		}
		var f = input.files[ 0 ];
		var mb = ( f.size / 1048576 ).toFixed( 1 );
		var done = document.createElement( 'span' );
		done.className = 'upload-done';
		done.innerHTML = '<svg viewBox="0 0 52 52" aria-hidden="true"><circle class="tick-ring" cx="26" cy="26" r="24" fill="none"/><path class="tick-mark" fill="none" d="M14 27l8 8 16-17"/></svg>'
			+ '<span class="upload-name"></span><span class="upload-size"></span>';
		done.querySelector( '.upload-name' ).textContent = f.name;
		done.querySelector( '.upload-size' ).textContent = mb + ' MB attached - choose a different file?';
		box.appendChild( done );
		box.classList.add( 'has-file' );
	} );
}() );

/**
 * Employers flexibility toggle: purely illustrative — emphasise whichever
 * end of the rebate/fee balance the slider is nearer.
 */
( function () {
	var wrap = document.querySelector( '[data-ph-flexi]' );
	if ( ! wrap ) { return; }
	var range = wrap.querySelector( 'input[type=range]' );
	var left = wrap.querySelector( '.ft-left' );
	var right = wrap.querySelector( '.ft-right' );
	if ( ! range || ! left || ! right ) { return; }
	var paint = function () {
		var v = parseInt( range.value, 10 );
		left.classList.toggle( 'is-on', v <= 50 );
		right.classList.toggle( 'is-on', v > 50 );
	};
	range.addEventListener( 'input', paint );
	paint();
}() );

/**
 * Site motion system.
 *
 * Adds restrained, one-time entrance reveals to shared page components and a
 * low-amplitude desktop parallax treatment to photographic bands. Everything
 * is progressive enhancement: content stays visible without JavaScript, and
 * motion is disabled when the visitor asks for reduced motion.
 */
( function () {
	var reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	if ( reduceMotion.matches || ! ( 'IntersectionObserver' in window ) ) { return; }

	var revealItems = [];
	var mark = function ( element, direction, delay ) {
		if ( ! element || element.hasAttribute( 'data-ph-reveal' ) ) { return; }
		if ( element.closest( '[data-ph-reveal]' ) || element.querySelector( '[data-ph-reveal]' ) ) { return; }
		element.setAttribute( 'data-ph-reveal', direction || 'up' );
		element.style.setProperty( '--ph-reveal-delay', Math.min( delay || 0, 280 ) + 'ms' );
		revealItems.push( element );
	};

	// The main hero reads as one deliberate sequence rather than several
	// unrelated animations.
	document.querySelectorAll( '.ph-v2-hero .hero-inner' ).forEach( function ( hero ) {
		Array.prototype.forEach.call( hero.children, function ( child, index ) {
			mark( child, 'up', index * 85 );
		} );
	} );

	// Page titles retain a subtle hierarchy: breadcrumb, label, title, copy,
	// then actions. This covers all shared inner-page templates.
	document.querySelectorAll( '.ph-v2page .pagehead .container' ).forEach( function ( heading ) {
		Array.prototype.forEach.call( heading.children, function ( child, index ) {
			mark( child, 'up', index * 65 );
		} );
	} );

	// Shared section headings and the homepage reviews heading.
	document.querySelectorAll( '.ph-v2page .section-head, .ph-reviews__head' ).forEach( function ( heading ) {
		Array.prototype.forEach.call( heading.children, function ( child, index ) {
			mark( child, 0 === index ? 'up' : 'right', index * 70 );
		} );
	} );

	// Some marketing pages remain intentionally editable in Elementor. Apply
	// the same title treatment to their content headings while explicitly
	// excluding global header and footer templates.
	document.querySelectorAll( '.elementor-widget-heading h1, .elementor-widget-heading h2' ).forEach( function ( title, index ) {
		var widget = title.closest( '.elementor-widget-heading' );
		if ( widget && ! widget.closest( 'header, footer, .ph-v2-header, .ph-v2-footer' ) ) {
			mark( widget, 'up', Math.min( index, 2 ) * 45 );
		}
	} );

	// Coordinate copy and imagery so paired layouts enter together from a
	// shallow opposing direction. Existing hover transforms remain untouched.
	[
		'.ph-v2page .split',
		'.ph-v2page .sector-showcase__grid',
		'.ph-v2page .hr-split',
		'.ph-v2page .bja-intro',
		'.ph-v2page .contact-grid',
		'.ph-v2page .hr-onboarding__panel'
	].forEach( function ( selector ) {
		document.querySelectorAll( selector ).forEach( function ( pair ) {
			Array.prototype.forEach.call( pair.children, function ( child, index ) {
				mark( child, 0 === index ? 'left' : 'right', index * 60 );
			} );
		} );
	} );
	document.querySelectorAll( '.ph-split' ).forEach( function ( pair ) {
		Array.prototype.forEach.call( pair.children, function ( child, index ) {
			mark( child, 0 === index ? 'left' : 'right', index * 60 );
		} );
	} );

	document.querySelectorAll( '.ph-v2page .photoband .pb-inner, .ph-v2page .guide-action__panel' ).forEach( function ( panel ) {
		mark( panel, 'left', 0 );
	} );
	document.querySelectorAll( '.ph-v2page .cookie-policy__intro, .ph-v2page .cookie-policy__main > .h2' ).forEach( function ( item, index ) {
		mark( item, 'up', Math.min( index, 2 ) * 60 );
	} );
	document.querySelectorAll( '.ph-v2page .cookie-policy__aside, .ph-v2page .job-layout > aside' ).forEach( function ( aside ) {
		mark( aside, 'right', 70 );
	} );

	// Repeated content receives a small, capped stagger. The cap keeps large
	// job archives and carousels feeling responsive rather than choreographed.
	[
		'.ph-v2-strip .grid',
		'.ph-v2page .points',
		'.ph-v2page .svc-grid',
		'.ph-v2page .secjobs',
		'.ph-v2page .guide-card-grid',
		'.ph-v2page .team-grid',
		'.ph-v2page .blog-grid',
		'.ph-v2page .bja-support-grid',
		'.ph-v2page .hr-service-grid__cards',
		'.ph-v2page .cookie-policy__list',
		'.ph-v2page .stats',
		'.sector-grid',
		'.ph-grid-2',
		'.ph-grid-3',
		'.ph-stats-box',
		'.ph-reviews .ph-grid',
		'.ph-v2-ctas'
	].forEach( function ( selector ) {
		document.querySelectorAll( selector ).forEach( function ( group ) {
			Array.prototype.forEach.call( group.children, function ( child, index ) {
				mark( child, 'up', Math.min( index, 4 ) * 65 );
			} );
		} );
	} );
	document.querySelectorAll( 'body.home [data-ph-carousel]' ).forEach( function ( carousel ) {
		mark( carousel, 'up', 80 );
	} );

	// A handful of standalone cards live outside a predictable grid wrapper.
	document.querySelectorAll( '.ph-reviews .ph-card--review, .ph-v2page .bja-case-card, .ph-v2page .sector-topic' ).forEach( function ( card, index ) {
		mark( card, 'up', Math.min( index, 4 ) * 65 );
	} );

	if ( revealItems.length ) {
		document.documentElement.classList.add( 'ph-motion-ready' );
		var revealObserver = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) { return; }
				entry.target.classList.add( 'is-revealed' );
				revealObserver.unobserve( entry.target );
			} );
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 } );
		revealItems.forEach( function ( item ) { revealObserver.observe( item ); } );
	}

	// Parallax is deliberately limited to wide-screen decorative photography.
	// The homepage hero already owns a slow zoom effect and is excluded here.
	var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
	if ( ( connection && connection.saveData ) || ! window.matchMedia( '(min-width: 821px)' ).matches ) { return; }

	var parallaxItems = Array.prototype.slice.call( document.querySelectorAll(
		'.ph-v2page .pagehead.photo > img, .ph-v2page .photoband > img'
	) );
	if ( ! parallaxItems.length ) { return; }

	parallaxItems.forEach( function ( image ) { image.setAttribute( 'data-ph-parallax', '' ); } );
	var ticking = false;
	var updateParallax = function () {
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		parallaxItems.forEach( function ( image ) {
			var host = image.parentElement;
			var rect = host.getBoundingClientRect();
			if ( rect.bottom < -80 || rect.top > viewportHeight + 80 ) { return; }
			var distance = ( viewportHeight / 2 ) - ( rect.top + rect.height / 2 );
			var limit = host.classList.contains( 'pagehead' ) ? 16 : 12;
			var offset = Math.max( -limit, Math.min( limit, distance / viewportHeight * limit * 2 ) );
			image.style.setProperty( '--ph-parallax-y', offset.toFixed( 2 ) + 'px' );
		} );
		ticking = false;
	};
	var requestParallax = function () {
		if ( ticking ) { return; }
		ticking = true;
		window.requestAnimationFrame( updateParallax );
	};
	window.addEventListener( 'scroll', requestParallax, { passive: true } );
	window.addEventListener( 'resize', requestParallax, { passive: true } );
	requestParallax();
}() );


/**
 * PRL brand mark: the logo's initials as a fine outline resting on the
 * bottom edge of the "people at the heart of it" section, behind the
 * photography. Each letter travels with the scroll rather than simply
 * fading — downward as you scroll down, upward as you scroll back — so
 * the mark reads as a slow signature that follows your direction.
 *
 * Injected rather than authored into the page because it is purely
 * decorative: no JS just means no ornament, and the Elementor layout
 * never has to be rebuilt to carry it.
 */
( function () {
	var collage = document.querySelector( '.ph-media-collage' );
	if ( ! collage ) { return; }

	// The section, not the split, so the mark can rest on its bottom edge.
	var host = collage.closest( 'section' ) || collage.closest( '.ph-split' );
	if ( ! host || host.querySelector( '.ph-brandmark' ) ) { return; }

	var mark = document.createElement( 'div' );
	mark.className = 'ph-brandmark';
	mark.setAttribute( 'aria-hidden', 'true' );

	var letters = 'PRL'.split( '' ).map( function ( character ) {
		var span = document.createElement( 'span' );
		span.className = 'ph-brandmark__letter';
		span.textContent = character;
		mark.appendChild( span );
		return span;
	} );

	host.classList.add( 'ph-brandmark-host' );
	host.appendChild( mark );

	if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return; // The stylesheet parks the letters visible; nothing to drive.
	}

	var clamp = function ( value ) {
		return value < 0 ? 0 : ( value > 1 ? 1 : value );
	};
	var smooth = function ( t ) {
		return t * t * ( 3 - 2 * t );
	};

	var TRAVEL  = 70;   // px a letter covers across its whole pass.
	var SPAN    = 0.16; // Share of the pass a single letter takes to arrive.
	var STAGGER = 0.07; // Gap between P, R and L.

	var direction = 1;  // 1 while scrolling down, -1 while scrolling back up.
	var lastY = window.pageYOffset || document.documentElement.scrollTop || 0;
	var pending = false;

	var paint = function () {
		pending = false;
		var box = host.getBoundingClientRect();
		var viewport = window.innerHeight || document.documentElement.clientHeight;

		// 0 as the section's top meets the bottom of the viewport, 1 once its
		// bottom has cleared the top — one full pass, however tall the section.
		var progress = clamp( ( viewport - box.top ) / ( viewport + box.height ) );

		for ( var i = 0; i < letters.length; i++ ) {
			var arrive = smooth( clamp( ( progress - ( 0.20 + i * STAGGER ) ) / SPAN ) );
			var depart = smooth( clamp( ( progress - ( 0.66 + i * STAGGER ) ) / SPAN ) );

			// -1 before arriving, 0 while shown, +1 once gone: the letter keeps
			// moving one way through its whole life, and the sign flips with the
			// scroll direction so it leads the eye the way the page is going.
			var offset = ( arrive + depart - 1 ) * direction * TRAVEL;

			letters[ i ].style.opacity = ( arrive * ( 1 - depart ) ).toFixed( 3 );
			letters[ i ].style.transform = 'translateY(' + offset.toFixed( 1 ) + 'px)';
		}
	};

	var schedule = function () {
		var y = window.pageYOffset || document.documentElement.scrollTop || 0;
		if ( y !== lastY ) {
			direction = y > lastY ? 1 : -1;
			lastY = y;
		}
		if ( pending ) { return; }
		pending = true;
		window.requestAnimationFrame( paint );
	};

	window.addEventListener( 'scroll', schedule, { passive: true } );
	window.addEventListener( 'resize', schedule );
	paint();
}() );
