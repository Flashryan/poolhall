/**
 * Records where a pin was placed so it can be found again after scrolling,
 * resizing, content changes or an Elementor layout edit.
 *
 * Several independent strategies are stored, strongest first:
 *   1. The nearest Elementor element (data-id), its occurrence on the page and
 *      loop item, plus the path from it to the clicked element.
 *   2. The nearest stable HTML id, plus path.
 *   3. The nearest post / product container (post-123), plus path.
 *   4. A full structural CSS path from <body>.
 * Text, tag and key attributes are kept to validate a match, so a pin is never
 * attached to an unrelated element that happens to sit in the same place.
 */

import type { Anchor } from '../types';
import { describe, displayText, isOurs, pathFrom } from './dom';

export interface Capture {
	anchor: Anchor;
	x: number;
	y: number;
	label: string;
}

const GENERATED_ID = /^(elementor-|e-|wp-block-|ui-id-|react-|radix-|headlessui-|mui-|ember\d|yui_|jquery|swiper-wrapper-|mnafb)/i;

export function isStableId( id: string ): boolean {
	if ( ! id || id.length > 64 || GENERATED_ID.test( id ) ) {
		return false;
	}
	if ( /\d{5,}/.test( id ) || ( /[0-9a-f]{8,}/i.test( id ) && /\d/.test( id ) ) ) {
		return false;
	}
	return /^[A-Za-z][\w-]*$/.test( id );
}

function loopPostId( el: Element ): string | undefined {
	const loop = el.closest( '.e-loop-item' );
	if ( ! loop ) {
		return undefined;
	}
	const match = /\be-loop-item-(\d+)\b/.exec( loop.className );
	return match ? match[ 1 ] : undefined;
}

function postAncestor( el: Element ): { node: Element; id: string } | null {
	let node: Element | null = el;
	while ( node && node !== document.body ) {
		const cls = typeof node.className === 'string' ? node.className : '';
		const match = /\bpost-(\d+)\b/.exec( cls ) || /^post-(\d+)$/.exec( node.id || '' );
		if ( match ) {
			return { node, id: match[ 1 ] };
		}
		node = node.parentElement;
	}
	return null;
}

function stableIdAncestor( el: Element ): Element | null {
	let node: Element | null = el;
	while ( node && node !== document.body ) {
		if ( node.id && isStableId( node.id ) && document.querySelectorAll( '#' + CSS.escape( node.id ) ).length === 1 ) {
			return node;
		}
		node = node.parentElement;
	}
	return null;
}

function basename( url: string ): string {
	try {
		const path = new URL( url, window.location.href ).pathname;
		const file = path.split( '/' ).pop() || '';
		// Drop WordPress size suffixes (-300x200) and scaled/rotated markers.
		return file.replace( /-\d+x\d+(?=\.\w+$)/, '' ).replace( /-(scaled|rotated)(?=\.\w+$)/, '' ).toLowerCase();
	} catch {
		return '';
	}
}

function attributes( el: Element ): Record< string, string > | undefined {
	const out: Record< string, string > = {};
	const tag = el.tagName.toLowerCase();
	if ( tag === 'img' ) {
		const src = el.getAttribute( 'src' ) || '';
		if ( src && ! src.startsWith( 'data:' ) ) {
			out.src = basename( src );
		}
		const alt = el.getAttribute( 'alt' );
		if ( alt ) {
			out.alt = alt.slice( 0, 120 );
		}
	}
	if ( tag === 'a' ) {
		const href = el.getAttribute( 'href' ) || '';
		if ( href && ! href.startsWith( 'javascript:' ) ) {
			try {
				const url = new URL( href, window.location.href );
				out.href = url.origin === window.location.origin ? url.pathname + url.search : url.origin + url.pathname;
			} catch {
				/* ignore malformed links */
			}
		}
	}
	for ( const name of [ 'name', 'type', 'placeholder', 'aria-label', 'role', 'title' ] ) {
		const value = el.getAttribute( name );
		if ( value ) {
			out[ name ] = value.slice( 0, 120 );
		}
	}
	return Object.keys( out ).length ? out : undefined;
}

export function capture( el: Element, clientX: number | null, clientY: number | null ): Capture {
	const rect = el.getBoundingClientRect();
	const tag = el.tagName.toLowerCase();
	const anchor: Anchor = {
		v: 1,
		css: pathFrom( document.body, el ) || tag,
		tag,
	};

	const text = displayText( el );
	if ( text ) {
		anchor.text = text;
	}
	const attrs = attributes( el );
	if ( attrs ) {
		anchor.attrs = attrs;
	}

	const elementor = el.closest( '[data-id][data-element_type]' );
	if ( elementor && ! isOurs( elementor ) ) {
		const id = elementor.getAttribute( 'data-id' ) || '';
		const loop = loopPostId( elementor );
		let peers = Array.from( document.querySelectorAll( `[data-id="${ CSS.escape( id ) }"]` ) );
		if ( loop ) {
			peers = peers.filter( ( peer ) => peer.closest( `.e-loop-item-${ loop }` ) );
		}
		const path = pathFrom( elementor, el );
		if ( id && path !== null ) {
			anchor.elementor = {
				id,
				nth: Math.max( 0, peers.indexOf( elementor ) ),
				path: path || undefined,
				type: elementor.getAttribute( 'data-widget_type' ) || elementor.getAttribute( 'data-element_type' ) || undefined,
			};
			if ( loop ) {
				anchor.elementor.loop = loop;
			}
		}
	}

	const withId = stableIdAncestor( el );
	if ( withId ) {
		const path = pathFrom( withId, el );
		if ( path !== null ) {
			anchor.htmlId = { id: withId.id, path: path || undefined };
		}
	}

	const post = postAncestor( el );
	if ( post ) {
		const path = pathFrom( post.node, el );
		if ( path !== null ) {
			anchor.post = { id: post.id, path: path || undefined };
		}
	}

	const doc = document.documentElement;
	anchor.rect = {
		x: Math.round( rect.left + window.scrollX ),
		y: Math.round( rect.top + window.scrollY ),
		w: Math.round( rect.width ),
		h: Math.round( rect.height ),
		dw: Math.round( Math.max( doc.scrollWidth, doc.clientWidth ) ),
		dh: Math.round( Math.max( doc.scrollHeight, doc.clientHeight ) ),
	};
	anchor.label = describe( el );

	let x = 0.5;
	let y = 0.5;
	if ( clientX !== null && clientY !== null && rect.width > 0 && rect.height > 0 ) {
		x = Math.min( 1, Math.max( 0, ( clientX - rect.left ) / rect.width ) );
		y = Math.min( 1, Math.max( 0, ( clientY - rect.top ) / rect.height ) );
	}

	return { anchor, x: round( x ), y: round( y ), label: anchor.label };
}

function round( value: number ): number {
	return Math.round( value * 100000 ) / 100000;
}

/**
 * The page element under a point, skipping the review interface itself and
 * preferring the icon's link or button over raw SVG shapes.
 */
export function pickElement( x: number, y: number ): Element | null {
	const stack = document.elementsFromPoint( x, y );
	for ( const hit of stack ) {
		if ( isOurs( hit ) || hit === document.documentElement || hit === document.body ) {
			continue;
		}
		return refine( hit );
	}
	return null;
}

export function refine( el: Element ): Element {
	const svg = el.closest( 'svg' );
	if ( svg ) {
		const interactive = svg.parentElement?.closest( 'a, button, [role="button"]' );
		if ( interactive && interactive.contains( svg ) && depth( interactive, svg ) <= 3 ) {
			return interactive;
		}
		return svg;
	}
	return el;
}

function depth( ancestor: Element, el: Element ): number {
	let n = 0;
	let node: Element | null = el;
	while ( node && node !== ancestor ) {
		n++;
		node = node.parentElement;
	}
	return n;
}
