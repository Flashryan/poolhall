/**
 * Application state: a tiny external store read through useSyncExternalStore.
 */

import { useSyncExternalStore } from 'react';
import type { Anchor, Branding, Item, ItemDetail, PageSummary, Person, Priority, SessionData } from './types';

export type Phase = 'loading' | 'join' | 'invalid' | 'ended' | 'no-access' | 'ready' | 'error' | 'closed';
export type Mode = 'browse' | 'comment';
export type PanelFilter = 'active' | 'done' | 'all';

export interface PendingFile {
	id: string;
	file: File;
	preview: string;
}

export interface ComposerState {
	draftKey: string;
	pin: { type: 'element' | 'page'; x: number; y: number };
	anchor: Anchor | null;
	target: Element | null;
	point: { x: number; y: number } | null;
	label: string;
	title: string;
	body: string;
	priority: Priority;
	files: PendingFile[];
	submitting: boolean;
	error: string;
}

export interface BoardFilters {
	search: string;
	page: string;
	priority: string;
	author: string;
	assignee: string;
	unread: boolean;
}

export interface Toast {
	id: number;
	text: string;
	tone: 'info' | 'error' | 'success';
	action?: { label: string; run: () => void };
}

export type Dialog =
	| { type: 'conflict'; itemId: number; mine: { title?: string; body?: string }; theirs: Item; fields: string[] }
	| { type: 'confirm'; title: string; text: string; confirm: string; danger?: boolean; run: () => void }
	| { type: 'profile' }
	| { type: 'return-link'; url: string; afterJoin?: boolean }
	| { type: 'lightbox'; src: string; alt: string };

export type PanelView = { name: 'list' } | { name: 'detail'; id: number } | { name: 'trash' };

export interface State {
	phase: Phase;
	notice: string;
	joinLabel: string;
	branding: Branding | null;
	session: SessionData | null;
	pageKey: string | null;
	pageUrl: string;
	pageTitle: string;
	items: Record< number, Item >;
	itemsLoaded: boolean;
	details: Record< number, ItemDetail >;
	people: Person[];
	assignable: number[];
	pages: PageSummary[];
	trash: Item[] | null;
	mode: Mode;
	panelOpen: boolean;
	view: PanelView;
	boardOpen: boolean;
	boardDetailId: number | null;
	panelFilter: PanelFilter;
	boardFilters: BoardFilters;
	pinsVisible: boolean;
	composer: ComposerState | null;
	highlightId: number | null;
	pulseId: number | null;
	sync: 'ok' | 'offline';
	toasts: Toast[];
	dialog: Dialog | null;
}

const PREFS_KEY = 'mnafb:prefs';

function loadPrefs(): Partial< Pick< State, 'panelOpen' | 'pinsVisible' | 'panelFilter' > > {
	try {
		const raw = window.localStorage.getItem( PREFS_KEY );
		return raw ? JSON.parse( raw ) : {};
	} catch {
		return {};
	}
}

const prefs = loadPrefs();

export const initialFilters: BoardFilters = { search: '', page: '', priority: '', author: '', assignee: '', unread: false };

let state: State = {
	phase: 'loading',
	notice: '',
	joinLabel: '',
	branding: null,
	session: null,
	pageKey: null,
	pageUrl: window.location.href.split( '#' )[ 0 ],
	pageTitle: '',
	items: {},
	itemsLoaded: false,
	details: {},
	people: [],
	assignable: [],
	pages: [],
	trash: null,
	mode: 'browse',
	panelOpen: prefs.panelOpen ?? true,
	view: { name: 'list' },
	boardOpen: false,
	boardDetailId: null,
	panelFilter: prefs.panelFilter ?? 'active',
	boardFilters: initialFilters,
	pinsVisible: prefs.pinsVisible ?? true,
	composer: null,
	highlightId: null,
	pulseId: null,
	sync: 'ok',
	toasts: [],
	dialog: null,
};

const listeners = new Set< () => void >();

export function getState(): State {
	return state;
}

export function setState( patch: Partial< State > | ( ( current: State ) => Partial< State > ) ): void {
	const next = typeof patch === 'function' ? patch( state ) : patch;
	state = { ...state, ...next };
	if ( 'panelOpen' in next || 'pinsVisible' in next || 'panelFilter' in next ) {
		try {
			window.localStorage.setItem(
				PREFS_KEY,
				JSON.stringify( { panelOpen: state.panelOpen, pinsVisible: state.pinsVisible, panelFilter: state.panelFilter } )
			);
		} catch {
			/* Preferences are a convenience only. */
		}
	}
	listeners.forEach( ( listener ) => listener() );
}

export function subscribe( listener: () => void ): () => void {
	listeners.add( listener );
	return () => listeners.delete( listener );
}

export function useStore< T >( selector: ( s: State ) => T ): T {
	return useSyncExternalStore( subscribe, () => selector( state ), () => selector( state ) );
}

let toastId = 0;

export function toast( text: string, tone: Toast[ 'tone' ] = 'info', action?: Toast[ 'action' ], ttl = 5000 ): void {
	const id = ++toastId;
	setState( ( s ) => ( { toasts: [ ...s.toasts.slice( -2 ), { id, text, tone, action } ] } ) );
	window.setTimeout( () => dismissToast( id ), action ? ttl + 3000 : ttl );
}

export function dismissToast( id: number ): void {
	setState( ( s ) => ( { toasts: s.toasts.filter( ( t ) => t.id !== id ) } ) );
}

/** Items on the current page, oldest first, with their pin numbers. */
export function pageNumbers( items: Record< number, Item >, pageKey: string | null ): Map< number, number > {
	const numbers = new Map< number, number >();
	if ( ! pageKey ) {
		return numbers;
	}
	numberPage( Object.values( items ).filter( ( item ) => item.page.key === pageKey && ! item.trashed ) ).forEach( ( n, id ) => numbers.set( id, n ) );
	return numbers;
}

function numberPage( items: Item[] ): Map< number, number > {
	const out = new Map< number, number >();
	items
		.slice()
		.sort( ( a, b ) => a.id - b.id )
		.forEach( ( item, index ) => out.set( item.id, index + 1 ) );
	return out;
}

/** Pin numbers for every item, numbered per page. */
export function allNumbers( items: Record< number, Item > ): Map< number, number > {
	const byPage = new Map< string, Item[] >();
	Object.values( items ).forEach( ( item ) => {
		if ( item.trashed ) {
			return;
		}
		const list = byPage.get( item.page.key ) || [];
		list.push( item );
		byPage.set( item.page.key, list );
	} );
	const out = new Map< number, number >();
	byPage.forEach( ( list ) => numberPage( list ).forEach( ( n, id ) => out.set( id, n ) ) );
	return out;
}
