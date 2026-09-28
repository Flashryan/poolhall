/**
 * Numbered pins drawn over the page. Positions are recomputed from the live
 * element on scroll, resize and layout changes and written straight to the
 * DOM, so the page itself is never modified.
 */

import { useEffect, useMemo, useRef } from 'react';
import { getResolution, track, useResolutions } from '../anchor/registry';
import { openItem } from '../actions';
import { pageNumbers, setState, useStore } from '../store';
import type { Item } from '../types';
import { Icon } from './Icons';

function visibleFor( filter: string, item: Item ): boolean {
	if ( filter === 'all' ) {
		return true;
	}
	return filter === 'done' ? item.status === 'done' : item.status !== 'done';
}

export function Pins() {
	const items = useStore( ( s ) => s.items );
	const pageKey = useStore( ( s ) => s.pageKey );
	const pinsVisible = useStore( ( s ) => s.pinsVisible );
	const filter = useStore( ( s ) => s.panelFilter );
	const view = useStore( ( s ) => s.view );
	const boardOpen = useStore( ( s ) => s.boardOpen );
	const highlightId = useStore( ( s ) => s.highlightId );
	const pulseId = useStore( ( s ) => s.pulseId );
	const composer = useStore( ( s ) => s.composer );
	const version = useResolutions();

	const pageItems = useMemo(
		() => Object.values( items ).filter( ( item ) => item.page.key === pageKey && ! item.trashed && item.pin.type === 'element' ),
		[ items, pageKey ]
	);
	const numbers = useMemo( () => pageNumbers( items, pageKey ), [ items, pageKey ] );

	useEffect( () => {
		track( pageItems );
	}, [ pageItems ] );

	const selectedId = view.name === 'detail' ? view.id : null;
	const shown = useMemo(
		() => ( pinsVisible && ! boardOpen ? pageItems.filter( ( item ) => visibleFor( filter, item ) || item.id === selectedId ) : [] ),
		[ pinsVisible, boardOpen, pageItems, filter, selectedId ]
	);

	const pinRefs = useRef( new Map< number, HTMLButtonElement >() );
	const outline = useRef< HTMLDivElement >( null );
	const outlineTarget = composer?.target ? null : highlightId ?? pulseId ?? selectedId;

	useEffect( () => {
		let frame = 0;
		const update = () => {
			frame = 0;
			const vw = window.innerWidth;
			const vh = window.innerHeight;
			const taken = new Map< string, number >();
			shown.forEach( ( item ) => {
				const node = pinRefs.current.get( item.id );
				if ( ! node ) {
					return;
				}
				const resolution = getResolution( item.id );
				if ( ! resolution || ! resolution.el || resolution.state === 'hidden' || resolution.state === 'unavailable' ) {
					node.style.display = 'none';
					return;
				}
				const rect = resolution.el.getBoundingClientRect();
				let x = rect.left + rect.width * item.pin.x;
				let y = rect.top + rect.height * item.pin.y;
				if ( x < -40 || y < -40 || x > vw + 40 || y > vh + 40 || ( rect.width === 0 && rect.height === 0 ) ) {
					node.style.display = 'none';
					return;
				}
				// Nudge pins that would sit exactly on top of each other.
				const key = `${ Math.round( x / 10 ) }:${ Math.round( y / 10 ) }`;
				const stacked = taken.get( key ) || 0;
				taken.set( key, stacked + 1 );
				x += stacked * 14;
				y -= stacked * 6;
				node.style.display = '';
				node.style.transform = `translate(${ Math.round( x ) }px, ${ Math.round( y ) }px)`;
			} );

			const box = outline.current;
			if ( box ) {
				const resolution = outlineTarget ? getResolution( outlineTarget ) : undefined;
				if ( resolution?.el && resolution.state !== 'hidden' && resolution.state !== 'unavailable' ) {
					const rect = resolution.el.getBoundingClientRect();
					box.style.display = '';
					box.style.transform = `translate(${ Math.round( rect.left - 3 ) }px, ${ Math.round( rect.top - 3 ) }px)`;
					box.style.width = `${ Math.round( rect.width + 6 ) }px`;
					box.style.height = `${ Math.round( rect.height + 6 ) }px`;
				} else {
					box.style.display = 'none';
				}
			}
		};
		const schedule = () => {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		};
		schedule();
		window.addEventListener( 'scroll', schedule, { capture: true, passive: true } );
		window.addEventListener( 'resize', schedule, { passive: true } );
		const timer = window.setInterval( schedule, 800 );
		return () => {
			window.cancelAnimationFrame( frame );
			window.removeEventListener( 'scroll', schedule, { capture: true } );
			window.removeEventListener( 'resize', schedule );
			window.clearInterval( timer );
		};
	}, [ shown, version, outlineTarget ] );

	return (
		<div className="mnafb-pins" aria-hidden={ shown.length ? undefined : true }>
			<div ref={ outline } className="mnafb-outline" style={ { display: 'none' } } />
			{ shown.map( ( item ) => {
				const n = numbers.get( item.id ) || 0;
				const done = item.status === 'done';
				return (
					<button
						key={ item.id }
						ref={ ( node ) => {
							if ( node ) {
								pinRefs.current.set( item.id, node );
							} else {
								pinRefs.current.delete( item.id );
							}
						} }
						type="button"
						className={ [
							'mnafb-pin',
							`mnafb-pin--${ item.status }`,
							item.id === selectedId ? 'is-selected' : '',
							item.id === pulseId ? 'is-pulse' : '',
							item.id === highlightId ? 'is-hover' : '',
						].join( ' ' ) }
						style={ { display: 'none' } }
						aria-label={ `Comment ${ n }: ${ item.title }${ done ? ' (done)' : '' }${ item.unread ? ' — new activity' : '' }` }
						onClick={ () => openItem( item.id ) }
						onMouseEnter={ () => setState( { highlightId: item.id } ) }
						onMouseLeave={ () => setState( { highlightId: null } ) }
						onFocus={ () => setState( { highlightId: item.id } ) }
						onBlur={ () => setState( { highlightId: null } ) }
					>
						<span className="mnafb-pin__body">{ done ? <Icon name="check" size={ 14 } /> : n }</span>
						{ item.unread && <span className="mnafb-pin__dot" /> }
					</button>
				);
			} ) }
		</div>
	);
}
