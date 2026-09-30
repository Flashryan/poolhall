/**
 * The expanded board: Open → In progress → Done across every page.
 *
 * Cards can be dragged between and within columns (mouse or pen straight
 * away, touch after a short press). Every move is also available from each
 * card's "Move to" menu, which works with the keyboard and screen readers.
 * Implementers can move and reorder anything; reviewers can drag finished
 * work back to Open.
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import { locate, openItem, patchItem } from '../actions';
import { allNumbers, initialFilters, setState, toast, useStore, type BoardFilters } from '../store';
import { DEVICE_TYPES, STATUSES, type Item, type Status } from '../types';
import { DEVICE_LABEL, PRIORITY_LABEL, STATUS_LABEL, pagePath } from '../util/format';
import { useLayout } from '../hooks/useMedia';
import { Card } from './Card';
import { Detail } from './Detail';
import { Icon } from './Icons';
import { Menu, useFocusTrap, type MenuItem } from './ui';

interface DragState {
	id: number;
	pointerId: number;
	startX: number;
	startY: number;
	x: number;
	y: number;
	offsetX: number;
	offsetY: number;
	width: number;
	active: boolean;
	touch: boolean;
	over: { status: Status; index: number } | null;
}

interface DragHandlers {
	move: ( event: PointerEvent ) => void;
	up: ( event: PointerEvent ) => void;
	cancel: () => void;
	touch: ( event: TouchEvent ) => void;
	end: () => void;
	activate: () => void;
}

function byOrder( a: Item, b: Item ): number {
	return a.order - b.order || b.id - a.id;
}

function matches( item: Item, f: BoardFilters, meId: number ): boolean {
	if ( f.search ) {
		const haystack = `${ item.title } ${ item.body } ${ item.page.title } ${ item.author?.name || '' }`.toLowerCase();
		if ( ! haystack.includes( f.search.toLowerCase().trim() ) ) {
			return false;
		}
	}
	if ( f.page && item.page.key !== f.page ) {
		return false;
	}
	if ( f.priority && item.priority !== f.priority ) {
		return false;
	}
	if ( f.author && String( item.author?.id || '' ) !== f.author ) {
		return false;
	}
	if ( f.device && ( f.device === 'unknown' ? item.device : item.device?.type !== f.device ) ) {
		return false;
	}
	if ( f.assignee ) {
		if ( f.assignee === 'none' ) {
			if ( item.assignee ) {
				return false;
			}
		} else if ( f.assignee === 'me' ) {
			if ( item.assignee?.id !== meId ) {
				return false;
			}
		} else if ( String( item.assignee?.id || '' ) !== f.assignee ) {
			return false;
		}
	}
	return ! ( f.unread && ! item.unread );
}

export function orderBetween( prev: Item | undefined, next: Item | undefined ): number {
	if ( prev && next ) {
		return ( prev.order + next.order ) / 2;
	}
	if ( prev ) {
		return prev.order + 1024;
	}
	if ( next ) {
		return next.order - 1024;
	}
	return 0;
}

function canDrop( item: Item, status: Status, implementer: boolean ): boolean {
	if ( implementer ) {
		return status === item.status ? item.can.reorder : item.can.statuses.includes( status );
	}
	return status !== item.status && item.can.statuses.includes( status );
}

function Filters( { onClose }: { onClose?: () => void } ) {
	const filters = useStore( ( s ) => s.boardFilters );
	const pages = useStore( ( s ) => s.pages );
	const people = useStore( ( s ) => s.people );
	const assignable = useStore( ( s ) => s.assignable );
	const items = useStore( ( s ) => s.items );
	const set = ( patch: Partial< BoardFilters > ) => setState( ( s ) => ( { boardFilters: { ...s.boardFilters, ...patch } } ) );
	const pageOptions = useMemo( () => {
		const map = new Map< string, { key: string; label: string } >();
		pages.forEach( ( p ) => map.set( p.key, { key: p.key, label: p.title || pagePath( p.url ) } ) );
		Object.values( items ).forEach( ( i ) => {
			if ( ! map.has( i.page.key ) ) {
				map.set( i.page.key, { key: i.page.key, label: i.page.title || pagePath( i.page.url ) } );
			}
		} );
		return Array.from( map.values() ).sort( ( a, b ) => a.label.localeCompare( b.label ) );
	}, [ pages, items ] );
	const authors = useMemo( () => {
		const ids = new Set( Object.values( items ).map( ( i ) => i.author?.id ) );
		return people.filter( ( p ) => ids.has( p.id ) );
	}, [ people, items ] );
	const active = Boolean( filters.search || filters.page || filters.priority || filters.device || filters.author || filters.assignee || filters.unread );
	const hasUnknownDevice = useMemo( () => Object.values( items ).some( ( i ) => ! i.device ), [ items ] );

	return (
		<div className="mnafb-filters">
			<label className="mnafb-search">
				<Icon name="search" size={ 16 } />
				<span className="mnafb-sr">Search feedback</span>
				<input className="mnafb-input" type="search" placeholder="Search" value={ filters.search } onChange={ ( e ) => set( { search: e.target.value } ) } />
			</label>
			<label className="mnafb-filter">
				<span className="mnafb-sr">Page</span>
				<select className="mnafb-select" value={ filters.page } onChange={ ( e ) => set( { page: e.target.value } ) }>
					<option value="">All pages</option>
					{ pageOptions.map( ( p ) => (
						<option key={ p.key } value={ p.key }>
							{ p.label }
						</option>
					) ) }
				</select>
			</label>
			<label className="mnafb-filter">
				<span className="mnafb-sr">Priority</span>
				<select className="mnafb-select" value={ filters.priority } onChange={ ( e ) => set( { priority: e.target.value } ) }>
					<option value="">Any priority</option>
					{ ( [ 'urgent', 'high', 'normal', 'low' ] as const ).map( ( p ) => (
						<option key={ p } value={ p }>
							{ PRIORITY_LABEL[ p ] }
						</option>
					) ) }
				</select>
			</label>
			<label className="mnafb-filter">
				<span className="mnafb-sr">Device</span>
				<select className="mnafb-select" value={ filters.device } onChange={ ( e ) => set( { device: e.target.value } ) }>
					<option value="">Any device</option>
					{ DEVICE_TYPES.map( ( type ) => (
						<option key={ type } value={ type }>
							{ DEVICE_LABEL[ type ] }
						</option>
					) ) }
					{ hasUnknownDevice && <option value="unknown">Not recorded</option> }
				</select>
			</label>
			<label className="mnafb-filter">
				<span className="mnafb-sr">Author</span>
				<select className="mnafb-select" value={ filters.author } onChange={ ( e ) => set( { author: e.target.value } ) }>
					<option value="">Anyone</option>
					{ authors.map( ( p ) => (
						<option key={ p.id } value={ String( p.id ) }>
							{ p.name }
						</option>
					) ) }
				</select>
			</label>
			<label className="mnafb-filter">
				<span className="mnafb-sr">Assigned to</span>
				<select className="mnafb-select" value={ filters.assignee } onChange={ ( e ) => set( { assignee: e.target.value } ) }>
					<option value="">Any assignee</option>
					<option value="me">Assigned to me</option>
					<option value="none">Unassigned</option>
					{ people
						.filter( ( p ) => assignable.includes( p.id ) )
						.map( ( p ) => (
							<option key={ p.id } value={ String( p.id ) }>
								{ p.name }
							</option>
						) ) }
				</select>
			</label>
			<label className="mnafb-check">
				<input type="checkbox" checked={ filters.unread } onChange={ ( e ) => set( { unread: e.target.checked } ) } />
				<span>Unread</span>
			</label>
			{ active && (
				<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => setState( { boardFilters: initialFilters } ) }>
					Clear
				</button>
			) }
			{ onClose && (
				<button type="button" className="mnafb-btn mnafb-btn--primary mnafb-btn--sm" onClick={ onClose }>
					Show results
				</button>
			) }
		</div>
	);
}

export function Board() {
	const open = useStore( ( s ) => s.boardOpen );
	return open ? <BoardView /> : null;
}

function BoardView() {
	const items = useStore( ( s ) => s.items );
	const filters = useStore( ( s ) => s.boardFilters );
	const detailId = useStore( ( s ) => s.boardDetailId );
	const branding = useStore( ( s ) => s.branding );
	const me = useStore( ( s ) => s.session?.me );
	const pageKey = useStore( ( s ) => s.pageKey );
	const media = useLayout();
	const phone = media.layout === 'phone';
	const implementer = !! me?.caps.implement;
	const [ tab, setTab ] = useState< Status >( 'open' );
	const [ showFilters, setShowFilters ] = useState( false );
	const [ drag, setDrag ] = useState< DragState | null >( null );
	const dragRef = useRef< DragState | null >( null );
	const suppressClick = useRef( false );
	const holdTimer = useRef( 0 );
	const columnRefs = useRef( new Map< Status, HTMLElement >() );
	const boardRef = useRef< HTMLDivElement >( null );
	const closeRef = useRef< HTMLButtonElement >( null );

	useFocusTrap( boardRef, true, closeRef );

	const numbers = useMemo( () => allNumbers( items ), [ items ] );
	const all = useMemo( () => Object.values( items ).filter( ( i ) => ! i.trashed ), [ items ] );
	const filtered = useMemo( () => all.filter( ( i ) => matches( i, filters, me?.id || 0 ) ), [ all, filters, me ] );
	const columns = useMemo( () => {
		const out: Record< Status, Item[] > = { open: [], in_progress: [], done: [] };
		filtered.forEach( ( item ) => out[ item.status ].push( item ) );
		STATUSES.forEach( ( status ) => out[ status ].sort( byOrder ) );
		return out;
	}, [ filtered ] );

	const close = () => setState( { boardOpen: false, boardDetailId: null } );

	const moveMenu = ( item: Item ): Array< MenuItem | false > => {
		const list = columns[ item.status ];
		const index = list.findIndex( ( i ) => i.id === item.id );
		return [
			...item.can.statuses.map( ( status ) => ( {
				label: status === 'open' && item.status === 'done' && ! implementer ? 'Reopen' : `Move to ${ STATUS_LABEL[ status ] }`,
				icon: 'move' as const,
				onSelect: () => void patchItem( item.id, { status }, { status: item.status } ),
			} ) ),
			item.can.reorder && index > 0 && { label: 'Move to top', icon: 'arrowUp', onSelect: () => void patchItem( item.id, { order: orderBetween( undefined, list[ 0 ] ) } ) },
			item.can.reorder && index > 0 && { label: 'Move up', icon: 'arrowUp', onSelect: () => void patchItem( item.id, { order: orderBetween( list[ index - 2 ], list[ index - 1 ] ) } ) },
			item.can.reorder && index >= 0 && index < list.length - 1 && { label: 'Move down', icon: 'arrowDown', onSelect: () => void patchItem( item.id, { order: orderBetween( list[ index + 1 ], list[ index + 2 ] ) } ) },
			item.can.reorder && index >= 0 && index < list.length - 1 && { label: 'Move to bottom', icon: 'arrowDown', onSelect: () => void patchItem( item.id, { order: orderBetween( list[ list.length - 1 ], undefined ) } ) },
			{ label: item.page.key === pageKey ? 'Show on page' : 'Open its page', icon: item.page.key === pageKey ? 'locate' : 'external', onSelect: () => locate( item.id ) },
		];
	};

	/* ---------------- Drag and drop ---------------- */

	// Handlers are created once and read current data through refs, so the
	// listeners added at pointerdown are exactly the ones removed afterwards.
	const latest = useRef( { items, columns, implementer } );
	latest.current = { items, columns, implementer };
	const dnd = useRef< DragHandlers | null >( null );

	if ( ! dnd.current ) {
		const hitTest = ( x: number, y: number, id: number ): DragState[ 'over' ] => {
			for ( const status of STATUSES ) {
				const column = columnRefs.current.get( status );
				if ( ! column ) {
					continue;
				}
				const rect = column.getBoundingClientRect();
				if ( x < rect.left || x > rect.right || y < rect.top - 40 || y > rect.bottom + 40 ) {
					continue;
				}
				const cards = Array.from( column.querySelectorAll< HTMLElement >( '[data-card]' ) ).filter( ( el ) => Number( el.dataset.card ) !== id );
				let index = cards.length;
				for ( let i = 0; i < cards.length; i++ ) {
					const r = cards[ i ].getBoundingClientRect();
					if ( y < r.top + r.height / 2 ) {
						index = i;
						break;
					}
				}
				return { status, index };
			}
			return null;
		};

		const drop = ( id: number, over: NonNullable< DragState[ 'over' ] > ) => {
			const { items: current, columns: cols, implementer: team } = latest.current;
			const item = current[ id ];
			if ( ! item ) {
				return;
			}
			if ( ! canDrop( item, over.status, team ) ) {
				if ( over.status !== item.status ) {
					toast( team ? 'That move is not allowed.' : 'Only the team moves cards between columns. You can drag finished work back to Open.', 'info' );
				}
				return;
			}
			if ( ! team ) {
				void patchItem( id, { status: over.status }, { status: item.status } );
				return;
			}
			const list = cols[ over.status ].filter( ( i ) => i.id !== id );
			const order = orderBetween( list[ over.index - 1 ], list[ over.index ] );
			if ( over.status !== item.status ) {
				void patchItem( id, { status: over.status, order }, { status: item.status } );
			} else if ( cols[ over.status ].findIndex( ( i ) => i.id === id ) !== over.index ) {
				void patchItem( id, { order } );
			}
		};

		const handlers: DragHandlers = {
			touch: ( event ) => {
				if ( dragRef.current?.active ) {
					event.preventDefault();
				}
			},
			end: () => {
				window.clearTimeout( holdTimer.current );
				window.removeEventListener( 'pointermove', handlers.move, true );
				window.removeEventListener( 'pointerup', handlers.up, true );
				window.removeEventListener( 'pointercancel', handlers.cancel, true );
				document.removeEventListener( 'touchmove', handlers.touch );
				dragRef.current = null;
				setDrag( null );
			},
			activate: () => {
				const d = dragRef.current;
				if ( ! d ) {
					return;
				}
				d.active = true;
				suppressClick.current = true;
				document.addEventListener( 'touchmove', handlers.touch, { passive: false } );
				setDrag( { ...d } );
			},
			move: ( event ) => {
				const d = dragRef.current;
				if ( ! d || event.pointerId !== d.pointerId ) {
					return;
				}
				const distance = Math.hypot( event.clientX - d.startX, event.clientY - d.startY );
				if ( ! d.active ) {
					if ( d.touch ) {
						if ( distance > 8 ) {
							handlers.end(); // The person is scrolling, not dragging.
						}
						return;
					}
					if ( distance < 6 ) {
						return;
					}
					handlers.activate();
				}
				d.x = event.clientX;
				d.y = event.clientY;
				d.over = hitTest( event.clientX, event.clientY, d.id );
				if ( d.over ) {
					const column = columnRefs.current.get( d.over.status );
					if ( column ) {
						const rect = column.getBoundingClientRect();
						if ( event.clientY < rect.top + 48 ) {
							column.scrollTop -= 14;
						} else if ( event.clientY > rect.bottom - 48 ) {
							column.scrollTop += 14;
						}
					}
				}
				setDrag( { ...d } );
			},
			up: ( event ) => {
				const d = dragRef.current;
				if ( ! d || event.pointerId !== d.pointerId ) {
					return;
				}
				if ( d.active && d.over ) {
					drop( d.id, d.over );
				}
				handlers.end();
				window.setTimeout( () => ( suppressClick.current = false ), 0 );
			},
			cancel: () => {
				handlers.end();
				suppressClick.current = false;
			},
		};
		dnd.current = handlers;
	}

	useEffect( () => () => dnd.current?.end(), [] );

	const onPointerDown = ( item: Item ) => ( event: React.PointerEvent< HTMLElement > ) => {
		const handlers = dnd.current;
		if ( ! handlers || event.button !== 0 || phone ) {
			return;
		}
		if ( ( event.target as Element ).closest( '.mnafb-card__actions' ) ) {
			return;
		}
		if ( ! implementer && ! item.can.statuses.includes( 'open' ) ) {
			return;
		}
		const rect = event.currentTarget.getBoundingClientRect();
		dragRef.current = {
			id: item.id,
			pointerId: event.pointerId,
			startX: event.clientX,
			startY: event.clientY,
			x: event.clientX,
			y: event.clientY,
			offsetX: event.clientX - rect.left,
			offsetY: event.clientY - rect.top,
			width: rect.width,
			active: false,
			touch: event.pointerType === 'touch',
			over: null,
		};
		window.addEventListener( 'pointermove', handlers.move, true );
		window.addEventListener( 'pointerup', handlers.up, true );
		window.addEventListener( 'pointercancel', handlers.cancel, true );
		if ( event.pointerType === 'touch' ) {
			holdTimer.current = window.setTimeout( handlers.activate, 350 );
		}
	};

	const dragged = drag?.active ? items[ drag.id ] : undefined;
	const visibleColumns = phone ? [ tab ] : STATUSES;

	return (
		<div
			ref={ boardRef }
			className={ `mnafb-board ${ drag?.active ? 'is-dragging' : '' }` }
			role="dialog"
			aria-modal="true"
			aria-label={ `${ branding?.name || 'Feedback' } board` }
			style={ { top: media.adminBar } }
		>
			<header className="mnafb-board__head">
				<div className="mnafb-board__title">
					<h2>{ branding?.name || 'Feedback' }</h2>
					<span className="mnafb-muted">
						{ filtered.length === all.length ? `${ all.length } item${ all.length === 1 ? '' : 's' }` : `${ filtered.length } of ${ all.length }` }
					</span>
				</div>
				{ ! phone && <Filters /> }
				{ phone && (
					<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" aria-expanded={ showFilters } onClick={ () => setShowFilters( ( v ) => ! v ) }>
						<Icon name="filter" size={ 16 } />
						<span>Filter</span>
					</button>
				) }
				<button ref={ closeRef } type="button" className="mnafb-btn mnafb-btn--icon" aria-label="Close the board" title="Close the board" onClick={ close }>
					<Icon name="close" />
				</button>
			</header>
			{ phone && showFilters && <Filters onClose={ () => setShowFilters( false ) } /> }
			{ phone && (
				<div className="mnafb-tabs mnafb-board__tabs" role="tablist" aria-label="Columns">
					{ STATUSES.map( ( status ) => (
						<button key={ status } type="button" role="tab" aria-selected={ tab === status } className={ tab === status ? 'is-on' : '' } onClick={ () => setTab( status ) }>
							{ STATUS_LABEL[ status ] } <span className="mnafb-tabs__count">{ columns[ status ].length }</span>
						</button>
					) ) }
				</div>
			) }
			<div className="mnafb-board__cols">
				{ visibleColumns.map( ( status ) => {
					const list = columns[ status ];
					const target = !! drag?.active && drag.over?.status === status;
					const allowed = dragged ? canDrop( dragged, status, implementer ) : true;
					const showLine = target && allowed && implementer && drag?.over;
					const rest = drag ? list.filter( ( i ) => i.id !== drag.id ) : list;
					return (
						<section key={ status } className={ `mnafb-col mnafb-col--${ status } ${ target && allowed ? 'is-target' : '' } ${ dragged && ! allowed ? 'is-blocked' : '' }` } aria-label={ STATUS_LABEL[ status ] }>
							{ ! phone && (
								<header className="mnafb-col__head">
									<span className={ `mnafb-col__dot mnafb-col__dot--${ status }` } aria-hidden="true" />
									<h3>{ STATUS_LABEL[ status ] }</h3>
									<span className="mnafb-col__count">{ list.length }</span>
								</header>
							) }
							<ul
								className="mnafb-col__list"
								ref={ ( node ) => {
									if ( node ) {
										columnRefs.current.set( status, node );
									} else {
										columnRefs.current.delete( status );
									}
								} }
							>
								{ list.map( ( item ) => (
									<li key={ item.id }>
										{ showLine && drag?.over && drag.id !== item.id && rest[ drag.over.index ]?.id === item.id && <div className="mnafb-drop-line" aria-hidden="true" /> }
										<Card
											item={ item }
											number={ numbers.get( item.id ) || 0 }
											variant="board"
											showPage
											selected={ detailId === item.id }
											dragging={ !! drag?.active && drag.id === item.id }
											onPointerDown={ onPointerDown( item ) }
											onOpen={ () => {
												if ( ! suppressClick.current ) {
													openItem( item.id );
												}
											} }
											actions={ <Menu label={ `Move “${ item.title }”` } icon="more" items={ moveMenu( item ) } /> }
										/>
									</li>
								) ) }
								{ showLine && drag?.over && drag.over.index >= rest.length && <li className="mnafb-drop-line" aria-hidden="true" /> }
								{ ! list.length && (
									<li className="mnafb-col__empty">{ status === 'open' ? 'Nothing waiting.' : status === 'in_progress' ? 'Nothing in progress.' : 'Nothing finished yet.' }</li>
								) }
							</ul>
						</section>
					);
				} ) }
			</div>
			{ dragged && drag && (
				<div className="mnafb-ghost" style={ { transform: `translate(${ drag.x - drag.offsetX }px, ${ drag.y - drag.offsetY }px)`, width: drag.width } } aria-hidden="true">
					<strong>{ dragged.title }</strong>
				</div>
			) }
			{ detailId !== null && (
				<>
					{ ! phone && <div className="mnafb-drawer-scrim" onClick={ () => setState( { boardDetailId: null } ) } /> }
					<aside className={ `mnafb-drawer ${ phone ? 'is-full' : '' }` } aria-label="Comment details">
						<Detail id={ detailId } variant="drawer" onBack={ () => setState( { boardDetailId: null } ) } />
					</aside>
				</>
			) }
		</div>
	);
}
