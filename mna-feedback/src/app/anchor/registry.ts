/**
 * Keeps each pinned item on the current page matched to its element, and
 * re-resolves when the page changes: DOM mutations (dynamic content, sliders,
 * lazy loading), resizes (responsive layouts) and new items.
 */

import { useSyncExternalStore } from 'react';
import type { Item } from '../types';
import { resolve, type Resolution } from './resolve';

const resolutions = new Map< number, Resolution >();
let tracked: Item[] = [];
let version = 0;
const listeners = new Set< () => void >();
let observer: MutationObserver | null = null;
let mutationTimer = 0;
let resizeTimer = 0;

function notify(): void {
	version++;
	listeners.forEach( ( listener ) => listener() );
}

function same( a: Resolution | undefined, b: Resolution ): boolean {
	return !! a && a.el === b.el && a.state === b.state;
}

/** Resolves all tracked items (or only those whose element went missing). */
function run( onlyBroken: boolean ): void {
	let changed = false;
	const ids = new Set< number >();
	tracked.forEach( ( item ) => {
		ids.add( item.id );
		if ( item.pin.type !== 'element' ) {
			return;
		}
		const current = resolutions.get( item.id );
		if ( onlyBroken && current && current.el && current.el.isConnected && current.state !== 'unavailable' && current.state !== 'hidden' ) {
			return;
		}
		const next = resolve( item.pin.anchor );
		if ( ! same( current, next ) ) {
			resolutions.set( item.id, next );
			changed = true;
		}
	} );
	resolutions.forEach( ( _value, id ) => {
		if ( ! ids.has( id ) ) {
			resolutions.delete( id );
			changed = true;
		}
	} );
	if ( changed ) {
		notify();
	}
}

function start(): void {
	if ( observer || ! document.body ) {
		return;
	}
	observer = new MutationObserver( () => {
		window.clearTimeout( mutationTimer );
		mutationTimer = window.setTimeout( () => run( true ), 400 );
	} );
	observer.observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'class', 'style', 'hidden', 'open' ] } );
	window.addEventListener( 'resize', () => {
		window.clearTimeout( resizeTimer );
		resizeTimer = window.setTimeout( () => run( false ), 250 );
	} );
	window.addEventListener( 'load', () => run( false ) );
	// Late layout shifts (fonts, images, animations) settle within a few seconds.
	[ 1000, 3000 ].forEach( ( delay ) => window.setTimeout( () => run( true ), delay ) );
}

export function track( items: Item[] ): void {
	tracked = items;
	start();
	run( false );
}

export function refresh(): void {
	run( false );
}

export function getResolution( id: number ): Resolution | undefined {
	return resolutions.get( id );
}

export function useResolutions(): number {
	return useSyncExternalStore(
		( listener ) => {
			listeners.add( listener );
			return () => listeners.delete( listener );
		},
		() => version,
		() => version
	);
}
