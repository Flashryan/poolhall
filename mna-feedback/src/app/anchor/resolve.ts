/**
 * Finds the element a pin was attached to.
 *
 * Structural strategies (Elementor id, stable HTML id, post container) are
 * trusted as long as the tag still matches, because the content is expected
 * to change once feedback is acted on. Weaker strategies (CSS path, text
 * search) must also match the element's text or key attributes. When nothing
 * qualifies the pin is reported as unavailable instead of being attached to
 * whatever now sits in that position.
 */

import type { Anchor } from '../types';
import { follow, isOurs, isRendered, normText, rawTextOf, similarity, textOf } from './dom';

export type ResolveState = 'exact' | 'likely' | 'hidden' | 'unavailable';

export interface Resolution {
	el: Element | null;
	state: ResolveState;
}

interface Candidate {
	el: Element;
	weight: number;
	structural: boolean;
}

function findElementor( ref: NonNullable< Anchor[ 'elementor' ] > ): Element | null {
	let list = Array.from( document.querySelectorAll( `[data-id="${ CSS.escape( ref.id ) }"]` ) ).filter( ( el ) => ! isOurs( el ) );
	if ( ref.loop ) {
		list = list.filter( ( el ) => el.closest( `.e-loop-item-${ ref.loop }` ) );
		if ( ! list.length ) {
			// That loop item (for example a product card) is not on this page any more.
			return null;
		}
	}
	if ( ! list.length ) {
		return null;
	}
	return list[ ref.nth ] || ( list.length === 1 ? list[ 0 ] : null );
}

function findPost( ref: NonNullable< Anchor[ 'post' ] > ): Element | null {
	const id = CSS.escape( ref.id );
	return document.querySelector( `#post-${ id }, .post-${ id }` );
}

function attrScore( anchor: Anchor, el: Element ): number {
	if ( ! anchor.attrs ) {
		return 0;
	}
	let score = 0;
	const tag = el.tagName.toLowerCase();
	if ( anchor.attrs.src && tag === 'img' ) {
		const src = ( el.getAttribute( 'src' ) || '' ).split( '/' ).pop() || '';
		const clean = src.replace( /\?.*$/, '' ).replace( /-\d+x\d+(?=\.\w+$)/, '' ).replace( /-(scaled|rotated)(?=\.\w+$)/, '' ).toLowerCase();
		score += clean === anchor.attrs.src ? 1.5 : -0.5;
	}
	for ( const name of [ 'alt', 'name', 'aria-label', 'placeholder' ] ) {
		if ( anchor.attrs[ name ] ) {
			score += el.getAttribute( name ) === anchor.attrs[ name ] ? 0.5 : 0;
		}
	}
	if ( anchor.attrs.href && tag === 'a' ) {
		const href = el.getAttribute( 'href' ) || '';
		score += href.includes( anchor.attrs.href ) ? 0.5 : 0;
	}
	return score;
}

export function resolve( anchor: Anchor | null ): Resolution {
	if ( ! anchor ) {
		return { el: null, state: 'unavailable' };
	}
	const candidates: Candidate[] = [];

	if ( anchor.elementor ) {
		const base = findElementor( anchor.elementor );
		if ( base ) {
			const target = follow( base, anchor.elementor.path );
			if ( target ) {
				candidates.push( { el: target, weight: 3, structural: true } );
			}
		}
	}
	if ( anchor.htmlId ) {
		const base = document.getElementById( anchor.htmlId.id );
		if ( base && ! isOurs( base ) ) {
			const target = follow( base, anchor.htmlId.path );
			if ( target ) {
				candidates.push( { el: target, weight: 2.5, structural: true } );
			}
		}
	}
	if ( anchor.post ) {
		const base = findPost( anchor.post );
		if ( base ) {
			const target = follow( base, anchor.post.path );
			if ( target ) {
				candidates.push( { el: target, weight: 2, structural: true } );
			}
		}
	}
	if ( anchor.css && document.body ) {
		const target = follow( document.body, anchor.css );
		if ( target && ! isOurs( target ) ) {
			candidates.push( { el: target, weight: 1, structural: false } );
		}
	}
	// Text search is only worthwhile for specific tags; scanning every div is slow.
	if ( anchor.text && anchor.text.length >= 3 && ! [ 'div', 'span', 'section', 'body' ].includes( anchor.tag ) ) {
		const wanted = normText( anchor.text );
		const matches = Array.from( document.getElementsByTagName( anchor.tag ) ).filter( ( el ) => ! isOurs( el ) && rawTextOf( el ) === wanted );
		if ( matches.length === 1 ) {
			candidates.push( { el: matches[ 0 ], weight: 1.5, structural: false } );
		}
	}

	if ( ! candidates.length ) {
		return { el: null, state: 'unavailable' };
	}

	// Merge candidates that point at the same element: agreement adds confidence.
	const merged = new Map< Element, Candidate & { votes: number } >();
	candidates.forEach( ( candidate ) => {
		const existing = merged.get( candidate.el );
		if ( existing ) {
			existing.weight += candidate.weight * 0.5;
			existing.structural = existing.structural || candidate.structural;
			existing.votes++;
		} else {
			merged.set( candidate.el, { ...candidate, votes: 1 } );
		}
	} );

	let best: { el: Element; score: number; state: ResolveState } | null = null;
	merged.forEach( ( candidate ) => {
		const el = candidate.el;
		const tagOk = el.tagName.toLowerCase() === anchor.tag;
		const text = anchor.text ? textOf( el ) : '';
		const textSim = anchor.text ? similarity( normText( anchor.text ), text ) : 1;
		const attrs = attrScore( anchor, el );

		let accepted = false;
		let state: ResolveState = 'likely';
		if ( candidate.structural && tagOk ) {
			accepted = true;
			state = textSim >= 0.9 && attrs >= 0 ? 'exact' : 'likely';
		} else if ( tagOk ) {
			const hasEvidence = anchor.text ? textSim >= 0.6 : attrs >= 1;
			accepted = hasEvidence && attrs >= 0;
			state = textSim >= 0.95 ? 'exact' : 'likely';
		}
		if ( ! accepted ) {
			return;
		}
		const score = candidate.weight + textSim * 2 + attrs + ( candidate.votes - 1 );
		if ( ! best || score > best.score ) {
			best = { el, score, state };
		}
	} );

	if ( ! best ) {
		return { el: null, state: 'unavailable' };
	}
	const chosen: { el: Element; score: number; state: ResolveState } = best;
	if ( ! isRendered( chosen.el ) ) {
		return { el: chosen.el, state: 'hidden' };
	}
	return { el: chosen.el, state: chosen.state };
}
