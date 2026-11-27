/* Poolhall v3 Construction pilot: transparent header state and restrained reveals. */
( function () {
	'use strict';

	if ( ! document.body.classList.contains( 'ph-construction-pilot' ) ) {
		return;
	}

	var syncHeader = function () {
		document.body.classList.toggle( 'ph-header-scrolled', window.scrollY > 36 );
	};
	window.addEventListener( 'scroll', syncHeader, { passive: true } );
	syncHeader();

	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	var groups = document.querySelectorAll( '.ph3-role-grid, .ph3-discipline-grid, .ph3-proof-grid, .ph3-job-list, .ph3-cta-grid' );
	var items  = [];
	groups.forEach( function ( group ) {
		Array.prototype.forEach.call( group.children, function ( item, index ) {
			item.classList.add( 'ph3-will-reveal' );
			item.style.setProperty( '--ph3-delay', Math.min( index, 3 ) * 70 + 'ms' );
			items.push( item );
		} );
	} );

	document.querySelectorAll( '.ph3-intro-grid' ).forEach( function ( group ) {
		Array.prototype.forEach.call( group.children, function ( item, index ) {
			item.classList.add( 'ph3-will-reveal', 0 === index ? 'ph3-from-left' : 'ph3-from-right' );
			item.style.setProperty( '--ph3-delay', index * 70 + 'ms' );
			items.push( item );
		} );
	} );

	var observer = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			if ( ! entry.isIntersecting ) {
				return;
			}
			entry.target.classList.add( 'is-visible' );
			observer.unobserve( entry.target );
		} );
	}, { rootMargin: '0px 0px -7% 0px', threshold: .08 } );

	items.forEach( function ( item ) { observer.observe( item ); } );
}() );
