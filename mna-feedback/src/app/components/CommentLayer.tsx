/**
 * Comment mode: a transparent layer over the page. Hovering outlines the
 * element underneath; clicking (or tapping) it opens the composer. The page
 * never receives these clicks, so links and forms are not triggered.
 *
 * Keyboard: arrow keys move the selection (up = larger area, down = inside,
 * left/right = neighbours), Enter comments on it, Escape leaves comment mode.
 */

import { useEffect, useRef, useState } from 'react';
import { capture, pickElement } from '../anchor/capture';
import { describe, isOurs } from '../anchor/dom';
import { loadDraft } from '../util/drafts';
import { getState, setState, useStore, type ComposerState } from '../store';
import type { Anchor, Priority } from '../types';
import { useLayout } from '../hooks/useMedia';
import { Icon } from './Icons';

interface StoredDraft {
	title: string;
	body: string;
	priority: Priority;
}

export function openComposer( el: Element | null, point: { x: number; y: number } | null ): void {
	const { pageKey, branding } = getState();
	let composer: ComposerState;
	if ( el ) {
		const cap = capture( el, point ? point.x : null, point ? point.y : null );
		const draftKey = `new:${ pageKey }:${ cap.anchor.css }`;
		const draft = loadDraft< StoredDraft >( draftKey );
		const rect = el.getBoundingClientRect();
		composer = {
			draftKey,
			pin: { type: 'element', x: cap.x, y: cap.y },
			anchor: cap.anchor,
			target: el,
			point: point || { x: rect.left + rect.width / 2, y: rect.top + Math.min( rect.height / 2, 60 ) },
			label: cap.label,
			title: draft?.title || '',
			body: draft?.body || '',
			priority: draft?.priority || 'normal',
			files: [],
			submitting: false,
			error: '',
		};
	} else {
		if ( branding && ! branding.page_comments ) {
			return;
		}
		const draftKey = `new:${ pageKey }:page`;
		const draft = loadDraft< StoredDraft >( draftKey );
		composer = {
			draftKey,
			pin: { type: 'page', x: 0.5, y: 0.5 },
			anchor: null,
			target: null,
			point: null,
			label: 'Whole page',
			title: draft?.title || '',
			body: draft?.body || '',
			priority: draft?.priority || 'normal',
			files: [],
			submitting: false,
			error: '',
		};
	}
	setState( { composer } );
}

/** Re-opens an unsent comment saved before a reload or network failure. */
export function restoreComposer( key: string, saved: { pin: ComposerState[ 'pin' ]; anchor: Anchor | null; label: string; title: string; body: string; priority: Priority }, target: Element | null ): void {
	const rect = target?.getBoundingClientRect();
	setState( {
		composer: {
			draftKey: key,
			pin: saved.pin,
			anchor: saved.anchor,
			target,
			point: rect ? { x: rect.left + rect.width / 2, y: rect.top + Math.min( rect.height / 2, 60 ) } : null,
			label: saved.label,
			title: saved.title,
			body: saved.body,
			priority: saved.priority,
			files: [],
			submitting: false,
			error: '',
		},
		mode: saved.pin.type === 'element' ? 'comment' : getState().mode,
	} );
}

function usable( el: Element | null ): boolean {
	if ( ! el || isOurs( el ) || el === document.body || el === document.documentElement ) {
		return false;
	}
	const rect = el.getBoundingClientRect();
	return rect.width > 0 && rect.height > 0;
}

function neighbour( el: Element, direction: 'up' | 'down' | 'prev' | 'next' ): Element | null {
	if ( direction === 'up' ) {
		const parent = el.parentElement;
		return usable( parent ) ? parent : null;
	}
	if ( direction === 'down' ) {
		return Array.from( el.children ).find( usable ) || null;
	}
	let node = direction === 'next' ? el.nextElementSibling : el.previousElementSibling;
	while ( node && ! usable( node ) ) {
		node = direction === 'next' ? node.nextElementSibling : node.previousElementSibling;
	}
	return node;
}

export function CommentLayer() {
	const mode = useStore( ( s ) => s.mode );
	const composer = useStore( ( s ) => s.composer );
	const branding = useStore( ( s ) => s.branding );
	const media = useLayout();
	const glass = useRef< HTMLDivElement >( null );
	const box = useRef< HTMLDivElement >( null );
	const [ hover, setHover ] = useState< Element | null >( null );
	const [ keyboard, setKeyboard ] = useState( false );
	const active = mode === 'comment' && ! composer;
	const target = composer?.target || ( active ? hover : null );

	useEffect( () => {
		if ( active ) {
			glass.current?.focus( { preventScroll: true } );
		} else {
			setHover( null );
			setKeyboard( false );
		}
	}, [ active ] );

	// Keep the outline on its element while the page scrolls or moves.
	useEffect( () => {
		const node = box.current;
		if ( ! node ) {
			return;
		}
		if ( ! target || ( mode !== 'comment' && ! composer ) ) {
			node.style.display = 'none';
			return;
		}
		let frame = 0;
		const update = () => {
			frame = 0;
			if ( ! target.isConnected ) {
				node.style.display = 'none';
				return;
			}
			const rect = target.getBoundingClientRect();
			node.style.display = '';
			node.style.transform = `translate(${ Math.round( rect.left - 2 ) }px, ${ Math.round( rect.top - 2 ) }px)`;
			node.style.width = `${ Math.round( rect.width + 4 ) }px`;
			node.style.height = `${ Math.round( rect.height + 4 ) }px`;
			node.setAttribute( 'data-label', describe( target ) );
			node.classList.toggle( 'is-below', rect.top < 28 );
		};
		const schedule = () => {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		};
		schedule();
		window.addEventListener( 'scroll', schedule, { capture: true, passive: true } );
		window.addEventListener( 'resize', schedule, { passive: true } );
		const timer = window.setInterval( schedule, 500 );
		return () => {
			window.cancelAnimationFrame( frame );
			window.removeEventListener( 'scroll', schedule, { capture: true } );
			window.removeEventListener( 'resize', schedule );
			window.clearInterval( timer );
		};
	}, [ target, mode, composer ] );

	const pick = ( x: number, y: number ): Element | null => {
		const node = glass.current;
		if ( node ) {
			node.style.pointerEvents = 'none';
		}
		const el = pickElement( x, y );
		if ( node ) {
			node.style.pointerEvents = '';
		}
		return el;
	};

	const onKeyDown = ( event: React.KeyboardEvent ) => {
		const map: Record< string, 'up' | 'down' | 'prev' | 'next' > = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'prev', ArrowRight: 'next' };
		if ( map[ event.key ] ) {
			event.preventDefault();
			setKeyboard( true );
			let current = hover;
			if ( ! current ) {
				current = pick( window.innerWidth / 2, window.innerHeight / 2 );
			} else {
				current = neighbour( current, map[ event.key ] ) || current;
			}
			if ( current ) {
				setHover( current );
				const rect = current.getBoundingClientRect();
				if ( rect.top < 0 || rect.bottom > window.innerHeight ) {
					current.scrollIntoView( { block: 'nearest' } );
				}
			}
		} else if ( ( event.key === 'Enter' || event.key === ' ' ) && hover ) {
			event.preventDefault();
			openComposer( hover, null );
		}
	};

	if ( mode !== 'comment' && ! composer?.target ) {
		return <div ref={ box } className="mnafb-target" style={ { display: 'none' } } />;
	}

	const touch = media.coarse || media.layout === 'phone';

	return (
		<>
			<div ref={ box } className="mnafb-target" style={ { display: 'none' } } />
			{ active && (
				<div
					ref={ glass }
					className="mnafb-glass"
					tabIndex={ 0 }
					role="application"
					aria-label="Comment mode. Click an element to comment on it, or use the arrow keys to choose one and press Enter."
					onPointerMove={ ( e ) => {
						if ( e.pointerType === 'mouse' ) {
							setKeyboard( false );
							const el = pick( e.clientX, e.clientY );
							if ( el !== hover ) {
								setHover( el );
							}
						}
					} }
					onPointerLeave={ () => ! keyboard && setHover( null ) }
					onClick={ ( e ) => {
						e.preventDefault();
						const el = pick( e.clientX, e.clientY );
						if ( el ) {
							openComposer( el, { x: e.clientX, y: e.clientY } );
						}
					} }
					onKeyDown={ onKeyDown }
				/>
			) }
			{ active && (
				<div className="mnafb-hint" role="status" style={ { top: media.adminBar + 12 } }>
					<Icon name="crosshair" size={ 16 } />
					<span>{ touch ? 'Tap anything to comment on it' : keyboard ? 'Arrow keys to choose · Enter to comment' : 'Click anything to comment on it' }</span>
					{ branding?.page_comments && (
						<button type="button" className="mnafb-hint__btn" onClick={ () => openComposer( null, null ) }>
							Whole page
						</button>
					) }
					<button type="button" className="mnafb-hint__btn mnafb-hint__btn--done" onClick={ () => setState( { mode: 'browse' } ) }>
						Done
					</button>
				</div>
			) }
		</>
	);
}
