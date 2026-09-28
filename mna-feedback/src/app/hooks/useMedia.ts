import { createContext, useContext, useEffect, useState } from 'react';

export type Layout = 'phone' | 'tablet' | 'desktop';

function layoutFor( width: number ): Layout {
	if ( width < 768 ) {
		return 'phone';
	}
	return width < 1024 ? 'tablet' : 'desktop';
}

export interface MediaState {
	layout: Layout;
	width: number;
	height: number;
	coarse: boolean;
	reducedMotion: boolean;
	adminBar: number;
}

function adminBarHeight(): number {
	const bar = document.getElementById( 'wpadminbar' );
	if ( ! bar ) {
		return 0;
	}
	const style = window.getComputedStyle( bar );
	if ( style.display === 'none' || style.position !== 'fixed' ) {
		return 0;
	}
	return Math.round( bar.getBoundingClientRect().height );
}

function read(): MediaState {
	return {
		layout: layoutFor( window.innerWidth ),
		width: window.innerWidth,
		height: window.innerHeight,
		coarse: window.matchMedia( '(pointer: coarse)' ).matches,
		reducedMotion: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches,
		adminBar: adminBarHeight(),
	};
}

export function useMedia(): MediaState {
	const [ state, setState ] = useState< MediaState >( read );
	useEffect( () => {
		let frame = 0;
		const update = () => {
			window.cancelAnimationFrame( frame );
			frame = window.requestAnimationFrame( () => {
				const next = read();
				setState( ( prev ) =>
					prev.layout === next.layout && prev.width === next.width && prev.height === next.height && prev.coarse === next.coarse && prev.reducedMotion === next.reducedMotion && prev.adminBar === next.adminBar
						? prev
						: next
				);
			} );
		};
		window.addEventListener( 'resize', update );
		const motion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		motion.addEventListener?.( 'change', update );
		const timer = window.setTimeout( update, 500 );
		return () => {
			window.removeEventListener( 'resize', update );
			motion.removeEventListener?.( 'change', update );
			window.clearTimeout( timer );
			window.cancelAnimationFrame( frame );
		};
	}, [] );
	return state;
}

export const MediaContext = createContext< MediaState >( read() );

/** Current layout, provided once by the app. */
export function useLayout(): MediaState {
	return useContext( MediaContext );
}
