/**
 * Everything that talks to the server, and the state changes around it.
 */

import { Api, ApiError } from './api';
import { getResolution, refresh as refreshPins } from './anchor/registry';
import { resolve } from './anchor/resolve';
import { deviceHints, prepareDeviceHints } from './util/device';
import { clearDraft, saveDraft } from './util/drafts';
import { prepareImage } from './util/images';
import { clearFlagCookie } from './util/storage';
import { getState, setState, toast, type ComposerState, type PendingFile } from './store';
import type { Attachment, Item, ItemDetail, PageSummary, Person, Priority, Reply, SessionData, SessionGate, Status, SyncResponse } from './types';

export let api: Api;

export function initApi( root: string ): void {
	api = new Api( root );
}

const currentUrl = (): string => window.location.href.split( '#' )[ 0 ];

/* -------------------------------------------------------------------------
 * Session
 * ---------------------------------------------------------------------- */

async function getNonce(): Promise< boolean > {
	try {
		const response = await fetch( api.url( 'session/nonce', { _mnafb: Date.now() } ), {
			headers: { Accept: 'application/json', 'X-MNAFB-Client': '1' },
			credentials: 'same-origin',
			cache: 'no-store',
		} );
		if ( ! response.ok ) {
			return false;
		}
		const json = ( await response.json() ) as { nonce?: string };
		if ( json.nonce ) {
			api.setNonce( json.nonce );
			return true;
		}
	} catch {
		/* fall through */
	}
	return false;
}

function applySession( data: SessionData ): void {
	api.csrf = data.csrf;
	prepareDeviceHints();
	setState( {
		session: data,
		branding: data.branding,
		pageKey: data.page ? data.page.key : null,
		pageTitle: data.page?.title || '',
		pageUrl: currentUrl(),
		phase: 'ready',
	} );
}

/**
 * Works out who this is: a team member (nonce), a guest with a session, or
 * someone who still has to join / whose access has ended.
 */
export async function boot(): Promise< void > {
	let attempts = 0;
	// Brief waits before giving up when the host throttles or drops a request.
	const waits = [ 2000, 6000 ];
	while ( attempts++ < 3 ) {
		let data: SessionData | SessionGate;
		try {
			data = await api.get< SessionData | SessionGate >( 'session', { url: currentUrl() } );
		} catch ( error ) {
			const err = error as ApiError;
			if ( err.code === 'rest_cookie_invalid_nonce' ) {
				api.setNonce( null );
				continue;
			}
			const wait = err.isTransient ? waits.shift() : undefined;
			if ( wait ) {
				await new Promise( ( resolve ) => window.setTimeout( resolve, wait ) );
				attempts--;
				continue;
			}
			if ( err.status === 401 ) {
				data = { authenticated: false, ...( err.data as Omit< SessionGate, 'authenticated' > ) };
			} else {
				setState( { phase: 'error', notice: err.message || 'The review tool could not start.' } );
				return;
			}
		}

		if ( data.authenticated ) {
			if ( data.wp_login && ! api.nonce && ( await getNonce() ) ) {
				continue; // Also signed in to WordPress as a team member: use that identity.
			}
			applySession( data );
			await loadAll();
			handleHash();
			startPolling();
			startPageWatch();
			return;
		}

		if ( data.wp_login && ( await getNonce() ) ) {
			continue;
		}
		if ( data.join?.state === 'required' ) {
			setState( { phase: 'join', joinLabel: data.join.label || '', branding: data.branding || null } );
			return;
		}
		if ( data.join?.state === 'invalid' ) {
			setState( { phase: 'invalid', notice: data.join.message || 'This review link is not valid.' } );
			return;
		}
		if ( data.no_role ) {
			setState( { phase: 'no-access', notice: 'Your account does not have access to feedback on this site.' } );
			return;
		}
		setState( { phase: 'ended', notice: 'Your review access has ended. Open the review link again to continue, or ask the site owner for a new link.' } );
		return;
	}
	setState( { phase: 'error', notice: 'The review tool could not start. Reload the page to try again.' } );
}

export async function join( name: string, email: string ): Promise< string | null > {
	try {
		const data = await api.post< SessionData >( 'session', { name, email, url: currentUrl() } );
		applySession( data );
		await loadAll();
		startPolling();
		startPageWatch();
		if ( data.return_link ) {
			setState( { dialog: { type: 'return-link', url: data.return_link, afterJoin: true } } );
		}
		return null;
	} catch ( error ) {
		const err = error as ApiError;
		if ( err.code === 'mnafb_link_invalid' ) {
			setState( { phase: 'invalid', notice: err.message } );
			return null;
		}
		return err.message;
	}
}

/** Guests: sign out on this device. Team: switch review mode off. Either way the overlay closes. */
export async function leave(): Promise< void > {
	stopPolling();
	try {
		await api.del( 'session' );
	} catch {
		// Clear the flag locally so the interface does not come back on the next page.
		clearFlagCookie();
	}
	api.setNonce( null );
	setState( { phase: 'closed' } );
	window.dispatchEvent( new CustomEvent( 'mnafb:close' ) );
}

export async function updateProfile( name: string, email: string ): Promise< string | null > {
	try {
		const data = await api.patch< SessionData >( 'session', { name, email } );
		setState( ( s ) => ( { session: s.session ? { ...s.session, me: data.me } : s.session } ) );
		toast( 'Your details were updated.', 'success' );
		return null;
	} catch ( error ) {
		return ( error as ApiError ).message;
	}
}

export async function showReturnLink( rotate = false ): Promise< void > {
	try {
		const result = rotate ? await api.post< { url: string } >( 'session/return-link' ) : await api.get< { url: string } >( 'session/return-link' );
		setState( { dialog: { type: 'return-link', url: result.url } } );
		if ( rotate ) {
			toast( 'New private link created. The old one no longer works.', 'success' );
		}
	} catch ( error ) {
		toast( ( error as ApiError ).message, 'error' );
	}
}

function handleUnauthorized( error: unknown ): boolean {
	const err = error as ApiError;
	if ( err && err.status === 401 ) {
		stopPolling();
		setState( { phase: 'ended', notice: 'Your review access has ended. Open the review link again to continue, or ask the site owner for a new link.', composer: null } );
		return true;
	}
	if ( err && err.code === 'rest_cookie_invalid_nonce' ) {
		api.setNonce( null );
		void boot();
		return true;
	}
	return false;
}

/* -------------------------------------------------------------------------
 * Loading and sync
 * ---------------------------------------------------------------------- */

let lastServerTime = '';

export async function loadAll(): Promise< void > {
	try {
		const [ items, people, pages ] = await Promise.all( [
			api.get< { items: Item[]; server_time: string } >( 'items', { limit: 1000 } ),
			api.get< { people: Person[]; assignable: number[] } >( 'people' ),
			api.get< PageSummary[] >( 'pages' ),
		] );
		const map: Record< number, Item > = {};
		items.items.forEach( ( item ) => ( map[ item.id ] = item ) );
		lastServerTime = items.server_time;
		setState( { items: map, itemsLoaded: true, people: people.people, assignable: people.assignable, pages } );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

function mergeItems( changed: Item[], removed: number[] ): void {
	setState( ( s ) => {
		const items = { ...s.items };
		const details = { ...s.details };
		changed.forEach( ( item ) => {
			const existing = items[ item.id ];
			if ( existing ) {
				// Timestamps have one-second precision, so two changes in the same
				// second look identical; the revision counters and counts do not.
				const older = item.revision < existing.revision || item.activity_rev < existing.activity_rev;
				const same =
					item.revision === existing.revision &&
					item.activity_rev === existing.activity_rev &&
					item.updated_at === existing.updated_at &&
					item.unread === existing.unread &&
					item.trashed === existing.trashed &&
					item.counts.replies === existing.counts.replies &&
					item.counts.attachments === existing.counts.attachments;
				if ( older || same ) {
					return; // Nothing new (and an optimistic change on screen stays).
				}
			}
			items[ item.id ] = item;
			const detail = details[ item.id ];
			if ( detail && ( detail.updated_at !== item.updated_at || detail.revision !== item.revision || detail.activity_rev !== item.activity_rev ) ) {
				delete details[ item.id ]; // Refetched when next viewed.
			}
		} );
		removed.forEach( ( id ) => {
			delete items[ id ];
			delete details[ id ];
		} );
		const patch: Partial< ReturnType< typeof getState > > = { items, details };
		if ( s.view.name === 'detail' && removed.includes( s.view.id ) ) {
			patch.view = { name: 'list' };
		}
		if ( s.boardDetailId && removed.includes( s.boardDetailId ) ) {
			patch.boardDetailId = null;
		}
		return patch;
	} );
}

export function putItem( item: Item ): void {
	mergeItems( [ item ], [] );
}

let pollTimer = 0;
let pollDelay = 0;
let polling = false;

export async function syncNow(): Promise< void > {
	if ( polling || getState().phase !== 'ready' || ! lastServerTime ) {
		return;
	}
	polling = true;
	// A request that never answers must not stop live updates for good.
	const controller = new AbortController();
	const timeout = window.setTimeout( () => controller.abort(), 30000 );
	try {
		const result = await api.get< SyncResponse >( 'sync', { since: lastServerTime }, controller.signal );
		lastServerTime = result.server_time;
		if ( result.reset ) {
			await loadAll();
		} else if ( result.items.length || result.removed.length ) {
			mergeItems( result.items, result.removed );
			const open = openDetailId();
			if ( open && result.items.some( ( item ) => item.id === open ) ) {
				void loadDetail( open, true );
			}
		}
		if ( getState().sync !== 'ok' ) {
			setState( { sync: 'ok' } );
		}
		if ( pollDelay ) {
			// Back online: return to the normal pace instead of waiting out the back-off.
			pollDelay = 0;
			schedule();
		}
	} catch ( error ) {
		if ( handleUnauthorized( error ) ) {
			return;
		}
		setState( { sync: 'offline' } );
		pollDelay = Math.min( 120000, ( pollDelay || 5000 ) * 2 );
	} finally {
		window.clearTimeout( timeout );
		polling = false;
	}
}

function schedule(): void {
	window.clearTimeout( pollTimer );
	if ( getState().phase !== 'ready' ) {
		return;
	}
	const base = Math.max( 5, getState().branding?.poll_interval || 15 ) * 1000;
	pollTimer = window.setTimeout( async () => {
		if ( document.visibilityState === 'visible' ) {
			await syncNow();
		}
		schedule();
	}, pollDelay || base );
}

let visibilityBound = false;

export function startPolling(): void {
	schedule();
	if ( ! visibilityBound ) {
		visibilityBound = true;
		document.addEventListener( 'visibilitychange', () => {
			if ( document.visibilityState === 'visible' ) {
				void syncNow().then( schedule );
			}
		} );
		window.addEventListener( 'online', () => void syncNow().then( schedule ) );
	}
}

export function stopPolling(): void {
	window.clearTimeout( pollTimer );
}

let pageWatch = 0;

/** Notices client-side navigation (history.pushState) and switches page. */
function startPageWatch(): void {
	window.clearInterval( pageWatch );
	pageWatch = window.setInterval( async () => {
		const url = currentUrl();
		if ( url === getState().pageUrl ) {
			return;
		}
		setState( { pageUrl: url } );
		try {
			const data = await api.get< SessionData | SessionGate >( 'session', { url } );
			if ( ! data.authenticated ) {
				stopPolling();
				setState( { phase: 'ended', notice: 'Your review access has ended. Open the review link again to continue, or ask the site owner for a new link.' } );
				return;
			}
			setState( { pageKey: data.page ? data.page.key : null, pageTitle: data.page?.title || '', view: { name: 'list' }, composer: null } );
		} catch ( error ) {
			handleUnauthorized( error );
		}
	}, 1500 );
}

function openDetailId(): number | null {
	const s = getState();
	if ( s.boardOpen && s.boardDetailId ) {
		return s.boardDetailId;
	}
	return s.view.name === 'detail' ? s.view.id : null;
}

/* -------------------------------------------------------------------------
 * Items
 * ---------------------------------------------------------------------- */

export async function createItem( composer: ComposerState ): Promise< boolean > {
	const s = getState();
	setState( { composer: { ...composer, submitting: true, error: '' } } );
	try {
		const doc = document.documentElement;
		const item = await api.post< Item >( 'items', {
			title: composer.title.trim(),
			body: composer.body.trim(),
			priority: composer.priority,
			page_url: currentUrl(),
			page_title: document.title,
			pin: composer.pin,
			anchor: composer.anchor,
			viewport: { w: window.innerWidth, h: window.innerHeight },
			context: {
				scroll: { x: Math.round( window.scrollX ), y: Math.round( window.scrollY ) },
				dpr: window.devicePixelRatio || 1,
				ua: navigator.userAgent.slice( 0, 200 ),
				doc: { w: doc.scrollWidth, h: doc.scrollHeight },
			},
			device: deviceHints(),
		} );
		clearDraft( composer.draftKey );
		if ( ! s.pageKey || item.page.key !== s.pageKey ) {
			setState( { pageKey: item.page.key } );
		}
		putItem( item );
		setState( { composer: null, panelOpen: true } );
		refreshPins();

		if ( composer.files.length ) {
			const failed = await uploadFiles( item.id, composer.files.map( ( f ) => f.file ) );
			composer.files.forEach( ( f ) => URL.revokeObjectURL( f.preview ) );
			if ( failed ) {
				toast( 'Your comment was posted, but a screenshot did not upload. Open the comment to try again.', 'error' );
			}
		}
		toast( 'Comment added.', 'success' );
		return true;
	} catch ( error ) {
		if ( handleUnauthorized( error ) ) {
			return false;
		}
		const current = getState().composer;
		if ( current ) {
			setState( { composer: { ...current, submitting: false, error: ( error as ApiError ).message } } );
		}
		return false;
	}
}

/**
 * Saves changes to an item. `expected` holds the values the person started
 * from, so a simultaneous change by someone else produces a conflict prompt
 * instead of being silently overwritten.
 */
export async function patchItem( id: number, fields: Partial< { title: string; body: string; priority: Priority; status: Status; assignee_id: number; order: number } >, expected?: Record< string, string | number > ): Promise< Item | null > {
	const before = getState().items[ id ];
	if ( before ) {
		// Optimistic update for quick moves on the board.
		const optimistic: Item = { ...before };
		if ( fields.status ) {
			optimistic.status = fields.status;
		}
		if ( typeof fields.order === 'number' ) {
			optimistic.order = fields.order;
		}
		if ( fields.priority ) {
			optimistic.priority = fields.priority;
		}
		setState( ( s ) => ( { items: { ...s.items, [ id ]: optimistic } } ) );
	}
	try {
		const item = await api.patch< Item >( `items/${ id }`, { ...fields, expected } );
		putItem( item );
		if ( getState().details[ id ] ) {
			void loadDetail( id, true );
		}
		return item;
	} catch ( error ) {
		if ( before ) {
			setState( ( s ) => ( { items: { ...s.items, [ id ]: before } } ) );
		}
		if ( handleUnauthorized( error ) ) {
			return null;
		}
		const err = error as ApiError;
		if ( err.code === 'mnafb_conflict' && err.data.current ) {
			const theirs = err.data.current as Item;
			putItem( theirs );
			const conflictFields = ( err.data.fields as string[] ) || [];
			if ( fields.title !== undefined || fields.body !== undefined ) {
				setState( { dialog: { type: 'conflict', itemId: id, mine: { title: fields.title, body: fields.body }, theirs, fields: conflictFields } } );
			} else {
				toast( 'Someone else changed this at the same time. Showing the latest version.', 'info' );
			}
			return null;
		}
		toast( err.message, 'error' );
		return null;
	}
}

export async function trashItem( id: number ): Promise< void > {
	try {
		await api.del( `items/${ id }` );
		mergeItems( [], [ id ] );
		setState( ( s ) => ( { trash: null, view: s.view.name === 'detail' && s.view.id === id ? { name: 'list' } : s.view } ) );
		refreshPins();
		const manager = getState().session?.me.caps.manage;
		toast( manager ? 'Moved to Trash.' : 'Deleted. A manager can restore it if needed.', 'info', manager ? { label: 'Undo', run: () => void restoreItem( id ) } : undefined );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

export async function restoreItem( id: number ): Promise< void > {
	try {
		const item = await api.post< Item >( `items/${ id }/restore` );
		putItem( item );
		setState( ( s ) => ( { trash: s.trash ? s.trash.filter( ( t ) => t.id !== id ) : null } ) );
		refreshPins();
		toast( 'Restored.', 'success' );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

export async function purgeItem( id: number ): Promise< void > {
	try {
		await api.del( `items/${ id }`, { force: true } );
		mergeItems( [], [ id ] );
		setState( ( s ) => ( { trash: s.trash ? s.trash.filter( ( t ) => t.id !== id ) : null } ) );
		toast( 'Deleted permanently.', 'info' );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

export async function loadTrash(): Promise< void > {
	try {
		const result = await api.get< { items: Item[] } >( 'items', { trashed: true, limit: 500 } );
		setState( { trash: result.items } );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

export async function loadDetail( id: number, quiet = false ): Promise< ItemDetail | null > {
	try {
		const detail = await api.get< ItemDetail >( `items/${ id }` );
		setState( ( s ) => ( { details: { ...s.details, [ id ]: detail }, items: { ...s.items, [ id ]: stripDetail( detail ) } } ) );
		return detail;
	} catch ( error ) {
		if ( handleUnauthorized( error ) ) {
			return null;
		}
		const err = error as ApiError;
		if ( err.status === 404 ) {
			mergeItems( [], [ id ] );
			if ( ! quiet ) {
				toast( 'That feedback no longer exists.', 'info' );
			}
		} else if ( ! quiet ) {
			toast( err.message, 'error' );
		}
		return null;
	}
}

function stripDetail( detail: ItemDetail ): Item {
	const { replies: _r, activity: _a, ...item } = detail;
	void _r;
	void _a;
	return item;
}

export async function markRead( item: Item ): Promise< void > {
	if ( ! item.unread ) {
		return;
	}
	setState( ( s ) => ( { items: { ...s.items, [ item.id ]: { ...item, unread: false } } } ) );
	try {
		await api.post( `items/${ item.id }/read`, { activity_rev: item.activity_rev } );
	} catch {
		/* Not critical. */
	}
}

/** Opens an item in the panel (or the board drawer when the board is open). */
export function openItem( id: number ): void {
	const s = getState();
	if ( s.boardOpen ) {
		setState( { boardDetailId: id } );
	} else {
		setState( { view: { name: 'detail', id }, panelOpen: true } );
	}
	const item = s.items[ id ];
	if ( item ) {
		void markRead( item );
	}
	void loadDetail( id, true );
}

/** Scrolls to an item's pin on this page, or opens the page it belongs to. */
export function locate( id: number ): void {
	const s = getState();
	const item = s.items[ id ];
	if ( ! item ) {
		return;
	}
	if ( item.page.key !== s.pageKey ) {
		const target = new URL( item.page.url );
		target.hash = `mnafb-item-${ id }`;
		window.location.href = target.toString();
		return;
	}
	setState( { boardOpen: false, pinsVisible: true, view: { name: 'detail', id }, panelOpen: true } );
	if ( item.pin.type !== 'element' ) {
		return;
	}
	const resolution = getResolution( id ) || resolve( item.pin.anchor );
	if ( resolution.el && resolution.state !== 'hidden' && resolution.state !== 'unavailable' ) {
		const reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		resolution.el.scrollIntoView( { block: 'center', inline: 'nearest', behavior: reduced ? 'auto' : 'smooth' } );
		setState( { pulseId: id } );
		window.setTimeout( () => {
			if ( getState().pulseId === id ) {
				setState( { pulseId: null } );
			}
		}, 2400 );
	} else if ( resolution.state === 'hidden' ) {
		toast( 'That element is not visible right now — it may be in a closed menu or tab, or hidden at this screen size.', 'info' );
	} else {
		toast( 'The original element is no longer on this page.', 'info' );
	}
}

function handleHash(): void {
	const match = /^#mnafb-item-(\d+)$/.exec( window.location.hash );
	if ( ! match ) {
		return;
	}
	const id = parseInt( match[ 1 ], 10 );
	try {
		window.history.replaceState( window.history.state, '', currentUrl() );
	} catch {
		/* ignore */
	}
	if ( getState().items[ id ] ) {
		window.setTimeout( () => locate( id ), 300 );
		openItem( id );
	}
}

/* -------------------------------------------------------------------------
 * Replies
 * ---------------------------------------------------------------------- */

export async function addReply( itemId: number, body: string, kind: 'reply' | 'note', files: File[] ): Promise< string | null > {
	try {
		const result = await api.post< { reply: Reply; item: Item | null } >( `items/${ itemId }/replies`, { body, kind, device: deviceHints() } );
		if ( result.item ) {
			putItem( result.item );
		}
		if ( files.length ) {
			const failed = await uploadFiles( itemId, files, result.reply.id );
			if ( failed ) {
				toast( 'Your reply was sent, but a screenshot did not upload.', 'error' );
			}
		}
		await loadDetail( itemId, true );
		clearDraft( `reply:${ itemId }` );
		return null;
	} catch ( error ) {
		if ( handleUnauthorized( error ) ) {
			return 'Your session has ended.';
		}
		return ( error as ApiError ).message;
	}
}

export async function editReply( reply: Reply, body: string ): Promise< string | null > {
	try {
		await api.patch< Reply >( `replies/${ reply.id }`, { body, expected_body: reply.body } );
		await loadDetail( reply.item_id, true );
		clearDraft( `reply-edit:${ reply.id }` );
		return null;
	} catch ( error ) {
		if ( handleUnauthorized( error ) ) {
			return 'Your session has ended.';
		}
		const err = error as ApiError;
		if ( err.code === 'mnafb_conflict' ) {
			await loadDetail( reply.item_id, true );
			return 'This reply was changed by someone else while you were editing. Your text is still here — review the latest version and save again.';
		}
		return err.message;
	}
}

export async function deleteReply( reply: Reply ): Promise< void > {
	try {
		await api.del( `replies/${ reply.id }` );
		await loadDetail( reply.item_id, true );
		toast( 'Reply deleted.', 'info' );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

export async function restoreReply( reply: Reply ): Promise< void > {
	try {
		await api.post( `replies/${ reply.id }/restore` );
		await loadDetail( reply.item_id, true );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

export async function purgeReply( reply: Reply ): Promise< void > {
	try {
		await api.del( `replies/${ reply.id }`, { force: true } );
		await loadDetail( reply.item_id, true );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

/* -------------------------------------------------------------------------
 * Screenshots
 * ---------------------------------------------------------------------- */

/** Uploads files one by one. Returns the number that failed. */
export async function uploadFiles( itemId: number, files: File[], replyId = 0 ): Promise< number > {
	let failed = 0;
	const max = getState().branding?.max_upload || 2 * 1024 * 1024;
	for ( const original of files ) {
		try {
			const file = await prepareImage( original, max );
			const form = new FormData();
			form.append( 'file', file, file.name || 'screenshot.png' );
			if ( replyId ) {
				form.append( 'reply_id', String( replyId ) );
			}
			await api.upload< Attachment >( `items/${ itemId }/attachments`, form );
		} catch ( error ) {
			failed++;
			if ( ! ( error instanceof ApiError ) ) {
				toast( ( error as Error ).message, 'error' );
			}
		}
	}
	await loadDetail( itemId, true );
	return failed;
}

export async function deleteAttachment( attachment: Attachment, itemId: number ): Promise< void > {
	try {
		await api.del( `attachments/${ attachment.id }` );
		await loadDetail( itemId, true );
	} catch ( error ) {
		if ( ! handleUnauthorized( error ) ) {
			toast( ( error as ApiError ).message, 'error' );
		}
	}
}

export async function pendingFiles( list: File[] ): Promise< PendingFile[] > {
	const max = getState().branding?.max_upload || 2 * 1024 * 1024;
	const out: PendingFile[] = [];
	for ( const original of list ) {
		try {
			const file = await prepareImage( original, max );
			out.push( { id: Math.random().toString( 36 ).slice( 2 ), file, preview: URL.createObjectURL( file ) } );
		} catch ( error ) {
			toast( ( error as Error ).message, 'error' );
		}
	}
	return out;
}

/* -------------------------------------------------------------------------
 * Composer drafts
 * ---------------------------------------------------------------------- */

export function persistComposer( composer: ComposerState ): void {
	saveDraft( composer.draftKey, {
		pin: composer.pin,
		anchor: composer.anchor,
		label: composer.label,
		title: composer.title,
		body: composer.body,
		priority: composer.priority,
	} );
}

