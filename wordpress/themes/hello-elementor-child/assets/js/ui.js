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
