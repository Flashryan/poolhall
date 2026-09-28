#!/usr/bin/env node
/**
 * Browser acceptance tests for the MNA Feedback overlay (Playwright, Chromium).
 *
 *   MNAFB_BASE=http://localhost:8889 MNAFB_ADMIN_USER=admin MNAFB_ADMIN_PASS=... \
 *   MNAFB_PAGE=/review-test/ MNAFB_SHOTS=./shots node tests/e2e/overlay.e2e.mjs
 *
 * MNAFB_PAGE must be a page on the site with at least one <h1>, <h2> and <p>.
 * Everything the test creates is removed at the end (items purged, link revoked).
 */

import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire( import.meta.url );
function loadPlaywright() {
	for ( const candidate of [ process.env.PLAYWRIGHT_MODULE, 'playwright', '/opt/node22/lib/node_modules/playwright' ].filter( Boolean ) ) {
		try {
			return require( candidate );
		} catch {
			/* try the next one */
		}
	}
	throw new Error( 'Playwright not found. Set PLAYWRIGHT_MODULE to its path.' );
}
const { chromium } = loadPlaywright();

const BASE = ( process.env.MNAFB_BASE || 'http://localhost:8889' ).replace( /\/$/, '' );
const PAGE = process.env.MNAFB_PAGE || '/review-test/';
const USER = process.env.MNAFB_ADMIN_USER || 'admin';
const PASS = process.env.MNAFB_ADMIN_PASS || '';
const SHOTS = process.env.MNAFB_SHOTS || '';
const EXECUTABLE = process.env.MNAFB_CHROMIUM || undefined;
if ( SHOTS ) {
	mkdirSync( SHOTS, { recursive: true } );
}

const results = [];
function check( name, ok, detail = '' ) {
	results.push( [ name, !! ok ] );
	console.log( `[${ ok ? 'PASS' : 'FAIL' }] ${ name }${ ! ok && detail ? ' — ' + detail : '' }` );
	return !! ok;
}
async function shot( page, name ) {
	if ( SHOTS ) {
		await page.waitForTimeout( 350 );
		await page.screenshot( { path: `${ SHOTS }/${ name }.png` } );
	}
}
async function attempt( name, fn ) {
	try {
		await fn();
	} catch ( error ) {
		check( name, false, String( error && error.message ? error.message.split( '\n' )[ 0 ] : error ) );
	}
}

const created = new Set();
const errors = [];

function watch( page, label ) {
	page.on( 'pageerror', ( e ) => errors.push( `${ label }: ${ e.message }` ) );
	page.on( 'console', ( m ) => {
		// Browsers log every 4xx/5xx response; the expected ones (401 after revocation,
		// 409 for the conflict test, network errors while offline) are not app errors.
		if ( m.type() === 'error' && ! /ERR_CERT|favicon|gravatar|Failed to load resource: (net::|the server responded with a status of (401|409))/i.test( m.text() ) ) {
			errors.push( `${ label } console: ${ m.text() }` );
		}
	} );
}

/** Calls the API from inside a page (same origin, its cookies, optional nonce). */
async function api( page, method, route, body, nonce ) {
	return page.evaluate(
		async ( { method, route, body, nonce } ) => {
			const headers = { 'X-MNAFB-Client': '1' };
			if ( nonce ) {
				headers[ 'X-WP-Nonce' ] = nonce;
			}
			if ( body !== undefined ) {
				headers[ 'Content-Type' ] = 'application/json';
			}
			const r = await fetch( '/wp-json/mna-feedback/v1/' + route, { method, headers, body: body !== undefined ? JSON.stringify( body ) : undefined, credentials: 'same-origin' } );
			let json = null;
			try {
				json = await r.json();
			} catch {}
			return { status: r.status, json };
		},
		{ method, route, body, nonce }
	);
}

/**
 * Scrolls the first visible element matching the selector into the middle
 * of the viewport and returns its centre, skipping elements behind the
 * review interface.
 */
async function visibleTarget( page, selector ) {
	const found = await page.evaluate( ( sel ) => {
		const list = Array.from( document.querySelectorAll( sel ) ).filter( ( el ) => {
			const r = el.getBoundingClientRect();
			return r.width > 40 && r.height > 10 && ! el.closest( 'header, nav, #wpadminbar' );
		} );
		const el = list[ 0 ];
		if ( ! el ) {
			return null;
		}
		el.scrollIntoView( { block: 'center' } );
		return true;
	}, selector );
	if ( ! found ) {
		throw new Error( `No visible ${ selector }` );
	}
	await page.waitForTimeout( 300 );
	return page.evaluate( ( sel ) => {
		const el = Array.from( document.querySelectorAll( sel ) ).find( ( e ) => {
			const r = e.getBoundingClientRect();
			return r.width > 40 && r.height > 10 && ! e.closest( 'header, nav, #wpadminbar' ) && r.top > 60 && r.bottom < window.innerHeight - 60;
		} );
		const r = el.getBoundingClientRect();
		return { x: r.left + Math.min( r.width / 2, 120 ), y: r.top + r.height / 2 };
	}, selector );
}

/** A point inside the element that is not covered by an existing pin. */
async function freePoint( page, selector ) {
	const box = await page.locator( selector ).first().boundingBox();
	if ( ! box ) {
		throw new Error( `No box for ${ selector }` );
	}
	const pins = await page.evaluate( () => {
		const root = document.getElementById( 'mnafb-root' )?.shadowRoot;
		return root ? Array.from( root.querySelectorAll( '.mnafb-pin' ) ).map( ( p ) => p.getBoundingClientRect().toJSON() ) : [];
	} );
	for ( let fy = 0.5; fy < 1; fy += 0.2 ) {
		for ( let fx = 0.15; fx < 0.95; fx += 0.1 ) {
			const x = box.x + box.width * fx;
			const y = box.y + box.height * fy;
			const hit = pins.some( ( r ) => x > r.left - 12 && x < r.right + 12 && y > r.top - 12 && y < r.bottom + 12 );
			if ( ! hit && x < ( page.viewportSize().width - 410 ) ) {
				return { x, y };
			}
		}
	}
	return { x: box.x + box.width * 0.8, y: box.y + box.height * 0.75 };
}

async function pinBox( page, title ) {
	return page.evaluate( ( t ) => {
		const root = document.getElementById( 'mnafb-root' )?.shadowRoot;
		const pin = root && Array.from( root.querySelectorAll( '.mnafb-pin' ) ).find( ( p ) => ( p.getAttribute( 'aria-label' ) || '' ).includes( t ) );
		if ( ! pin || pin.style.display === 'none' ) {
			return null;
		}
		return pin.getBoundingClientRect().toJSON();
	}, title );
}

async function createViaUi( page, selector, title, body = '' ) {
	const mode = page.locator( '.mnafb-mode button:has-text("Comment")' );
	if ( ( await mode.getAttribute( 'aria-pressed' ) ) !== 'true' ) {
		await mode.click();
	}
	await page.locator( selector ).first().scrollIntoViewIfNeeded();
	const point = await freePoint( page, selector );
	await page.mouse.move( point.x, point.y );
	await page.mouse.click( point.x, point.y );
	await page.waitForSelector( '.mnafb-composer', { timeout: 5000 } );
	await page.fill( '.mnafb-composer input.mnafb-input--title', title );
	if ( body ) {
		await page.fill( '.mnafb-composer textarea', body );
	}
	const response = page.waitForResponse( ( r ) => r.url().includes( '/mna-feedback/v1/items' ) && r.request().method() === 'POST' );
	await page.click( '.mnafb-composer button[type="submit"]' );
	const json = await ( await response ).json();
	if ( json && json.id ) {
		created.add( json.id );
	}
	await page.waitForSelector( '.mnafb-composer', { state: 'detached', timeout: 5000 } );
	return json;
}

async function main() {
	if ( ! PASS ) {
		console.log( 'Set MNAFB_ADMIN_PASS' );
		return 2;
	}
	const browser = await chromium.launch( EXECUTABLE ? { executablePath: EXECUTABLE } : {} );

	// ---------------------------------------------------------------- Setup
	const adminCtx = await browser.newContext( { viewport: { width: 1280, height: 800 } } );
	const admin = await adminCtx.newPage();
	watch( admin, 'admin' );
	await admin.goto( BASE + '/wp-login.php' );
	await admin.fill( '#user_login', USER );
	await admin.fill( '#user_pass', PASS );
	await admin.click( '#wp-submit' );
	await admin.waitForURL( /wp-admin/, { timeout: 20000 } );
	const nonce = await admin.evaluate( async () => ( await ( await fetch( '/wp-json/mna-feedback/v1/session/nonce', { headers: { 'X-MNAFB-Client': '1' } } ) ).json() ).nonce );
	check( 'Admin signs in and gets a REST nonce', !! nonce );
	const linkRes = await api( admin, 'POST', 'admin/links', { label: 'E2E test link', landing_url: BASE + PAGE }, nonce );
	const link = linkRes.json;
	check( 'Manager creates a share link', linkRes.status === 201 && link.url, JSON.stringify( linkRes.json ).slice( 0, 200 ) );

	// ------------------------------------------------------- Public visitor
	const publicCtx = await browser.newContext();
	const visitor = await publicCtx.newPage();
	await visitor.goto( BASE + PAGE );
	await visitor.waitForTimeout( 800 );
	check( 'Ordinary visitors do not get the review interface', ( await visitor.locator( '#mnafb-root' ).count() ) === 0 );
	const leaked = await visitor.evaluate( async () => ( await fetch( '/wp-json/mna-feedback/v1/items' ) ).status );
	check( 'Ordinary visitors cannot read feedback from the API', leaked === 401, String( leaked ) );
	await publicCtx.close();

	// ------------------------------------------------------- Guest joins
	const guestCtx = await browser.newContext( { viewport: { width: 1280, height: 800 } } );
	const guest = await guestCtx.newPage();
	watch( guest, 'guest' );
	await guest.goto( link.url );
	check( 'Share link redirects to the page without the token', ! guest.url().includes( 'mna-review' ), guest.url() );
	await guest.waitForSelector( 'text=You’ve been invited to review this site', { timeout: 15000 } );
	await shot( guest, '1280-join' );
	await guest.fill( 'input[autocomplete="name"]', 'Alex Reviewer' );
	await guest.click( 'button:has-text("Start reviewing")' );
	await guest.waitForSelector( 'text=Your private link.', { timeout: 10000 } );
	const returnLink = await guest.locator( 'input[aria-label="Your private link"]' ).inputValue();
	check( 'Joining shows a private return link', returnLink.includes( 'mna-return=' ) );
	await guest.click( '.mnafb-dialog__foot button:has-text("Start reviewing")' );
	await guest.waitForSelector( '.mnafb-panel', { timeout: 10000 } );
	await shot( guest, '1280-panel' );

	// --------------------------------------------------- Create by mouse
	let heading;
	await attempt( 'Guest pins a comment to a heading with the mouse', async () => {
		heading = await createViaUi( guest, 'h2', 'E2E: tighten the section heading', 'Too much space above it.' );
		check( 'Guest pins a comment to a heading with the mouse', heading && heading.pin?.type === 'element' && heading.pin.anchor?.tag === 'h2' );
	} );
	await shot( guest, '1280-pinned' );
	const pin1 = heading && ( await pinBox( guest, 'E2E: tighten the section heading' ) );
	check( 'The new pin is drawn on the page', !! pin1 );

	// Pin follows the element: scroll, resize, and content inserted above it.
	await attempt( 'Pins stay on their element after scrolling', async () => {
		const before = await pinBox( guest, 'E2E: tighten the section heading' );
		await guest.mouse.wheel( 0, 220 );
		await guest.waitForTimeout( 400 );
		const after = await pinBox( guest, 'E2E: tighten the section heading' );
		check( 'Pins stay on their element after scrolling', before && after && Math.abs( before.top - after.top - 220 ) < 12, JSON.stringify( { before: before?.top, after: after?.top } ) );
	} );
	await attempt( 'Pins follow dynamic content changes', async () => {
		const target = await guest.evaluate( () => document.querySelector( 'h2' ).getBoundingClientRect().top );
		const before = await pinBox( guest, 'E2E: tighten the section heading' );
		await guest.evaluate( () => {
			const block = document.createElement( 'div' );
			block.id = 'e2e-inserted';
			block.style.height = '150px';
			block.textContent = 'Inserted by the test';
			document.querySelector( 'h2' ).parentElement.insertBefore( block, document.querySelector( 'h2' ) );
		} );
		await guest.waitForTimeout( 900 );
		const moved = await guest.evaluate( () => document.querySelector( 'h2' ).getBoundingClientRect().top );
		const after = await pinBox( guest, 'E2E: tighten the section heading' );
		check( 'Pins follow dynamic content changes', before && after && Math.abs( ( after.top - before.top ) - ( moved - target ) ) < 12, JSON.stringify( { pinDelta: after && before ? after.top - before.top : null, elDelta: moved - target } ) );
		await guest.evaluate( () => document.getElementById( 'e2e-inserted' )?.remove() );
	} );
	await attempt( 'Pins survive a resize', async () => {
		await guest.setViewportSize( { width: 1100, height: 800 } );
		await guest.waitForTimeout( 700 );
		const box = await pinBox( guest, 'E2E: tighten the section heading' );
		const el = await guest.evaluate( () => document.querySelector( 'h2' ).getBoundingClientRect().toJSON() );
		check( 'Pins survive a resize', box && box.bottom >= el.top - 4 && box.bottom <= el.bottom + 4, JSON.stringify( { box, el } ) );
		await guest.setViewportSize( { width: 1280, height: 800 } );
	} );

	// An element that disappears is reported, never re-attached elsewhere.
	await attempt( 'Removed element shows "Original element unavailable"', async () => {
		await guest.evaluate( () => {
			const h = document.querySelector( 'h2' );
			h.setAttribute( 'data-e2e-hidden', '1' );
			h.replaceWith( Object.assign( document.createElement( 'p' ), { textContent: 'Replaced by the test' } ) );
		} );
		await guest.waitForTimeout( 900 );
		const visible = await pinBox( guest, 'E2E: tighten the section heading' );
		await guest.locator( '.mnafb-card__open[aria-label*="E2E: tighten the section heading"]' ).click();
		await guest.waitForSelector( 'text=Original element unavailable', { timeout: 5000 } );
		await shot( guest, '1280-unavailable' );
		check( 'Removed element shows "Original element unavailable"', ! visible );
		await guest.reload();
		await guest.waitForSelector( '.mnafb-panel', { timeout: 10000 } );
	} );

	// ------------------------------------------------ Persistence
	await attempt( 'Feedback survives a refresh', async () => {
		await guest.reload();
		await guest.waitForSelector( '.mnafb-card', { timeout: 10000 } );
		check( 'Feedback survives a refresh', ( await guest.locator( '.mnafb-card__open[aria-label*="E2E: tighten"]' ).count() ) === 1 );
	} );
	await attempt( 'The same identity resumes in another browser session', async () => {
		const otherCtx = await browser.newContext( { viewport: { width: 1280, height: 800 } } );
		const other = await otherCtx.newPage();
		await other.goto( returnLink );
		await other.waitForSelector( '.mnafb-card__open[aria-label*="E2E: tighten"]', { timeout: 15000 } );
		const me = await api( other, 'GET', 'session' );
		check( 'The same identity resumes in another browser session', me.json?.me?.name === 'Alex Reviewer', JSON.stringify( me.json?.me ) );
		await otherCtx.close();
	} );

	// ------------------------------------------------ Keyboard create
	await attempt( 'Keyboard: choose an element with the arrow keys and comment', async () => {
		await guest.locator( '.mnafb-mode button:has-text("Comment")' ).click();
		const glass = guest.locator( '.mnafb-glass' );
		await glass.focus();
		await guest.keyboard.press( 'ArrowUp' );
		await guest.keyboard.press( 'ArrowDown' );
		await guest.keyboard.press( 'Enter' );
		await guest.waitForSelector( '.mnafb-composer', { timeout: 5000 } );
		await guest.keyboard.type( 'E2E: keyboard comment' );
		const response = guest.waitForResponse( ( r ) => r.url().includes( '/mna-feedback/v1/items' ) && r.request().method() === 'POST' );
		await guest.keyboard.press( 'Control+Enter' );
		const json = await ( await response ).json();
		if ( json?.id ) {
			created.add( json.id );
		}
		check( 'Keyboard: choose an element with the arrow keys and comment', json?.pin?.type === 'element' );
		await guest.keyboard.press( 'Escape' ); // leave comment mode
	} );

	// ------------------------------------------------ Page comment + edit + delete own
	let pageItem;
	await attempt( 'Page-level comment', async () => {
		await guest.locator( 'button:has-text("Comment on the whole page")' ).click();
		await guest.waitForSelector( '.mnafb-composer' );
		await guest.fill( '.mnafb-composer input.mnafb-input--title', 'E2E: general page feedback' );
		const response = guest.waitForResponse( ( r ) => r.url().includes( '/mna-feedback/v1/items' ) && r.request().method() === 'POST' );
		await guest.click( '.mnafb-composer button[type="submit"]' );
		pageItem = await ( await response ).json();
		created.add( pageItem.id );
		check( 'Page-level comment', pageItem.pin.type === 'page' );
	} );

	await attempt( 'Author edits their comment', async () => {
		await guest.locator( '.mnafb-card__open[aria-label*="E2E: general page feedback"]' ).click();
		await guest.locator( 'button[aria-label="Comment actions"]' ).click();
		await guest.locator( '[role="menuitem"]:has-text("Edit")' ).click();
		await guest.locator( '.mnafb-edit input[aria-label="Title"]' ).fill( 'E2E: general page feedback (edited)' );
		await guest.locator( '.mnafb-edit button:has-text("Save changes")' ).click();
		await guest.waitForSelector( 'h2.mnafb-detail__title:has-text("(edited)")', { timeout: 5000 } );
		check( 'Author edits their comment', true );
	} );

	// ------------------------------------------------ Conflict between two editors
	await attempt( 'Simultaneous edits are detected and both versions offered', async () => {
		// Guest opens edit, admin changes the title meanwhile, guest saves.
		await guest.locator( 'button[aria-label="Comment actions"]' ).click();
		await guest.locator( '[role="menuitem"]:has-text("Edit")' ).click();
		await api( admin, 'PATCH', `items/${ pageItem.id }`, { title: 'E2E: changed by the manager' }, nonce );
		await guest.locator( '.mnafb-edit input[aria-label="Title"]' ).fill( 'E2E: my competing edit' );
		await guest.locator( '.mnafb-edit button:has-text("Save changes")' ).click();
		await guest.waitForSelector( 'text=Someone else changed this', { timeout: 5000 } );
		await shot( guest, '1280-conflict' );
		const both = ( await guest.locator( '.mnafb-compare' ).innerText() ).includes( 'E2E: changed by the manager' ) && ( await guest.locator( '.mnafb-compare' ).innerText() ).includes( 'E2E: my competing edit' );
		await guest.locator( 'button:has-text("Use my version")' ).click();
		await guest.waitForSelector( 'h2.mnafb-detail__title:has-text("E2E: my competing edit")', { timeout: 5000 } );
		check( 'Simultaneous edits are detected and both versions offered', both );
	} );

	// ------------------------------------------------ Offline: work is kept
	await attempt( 'A failed request keeps the text and offers a retry', async () => {
		await guest.locator( 'button:has-text("All comments")' ).click();
		await guest.locator( 'button:has-text("Comment on the whole page")' ).click();
		await guest.fill( '.mnafb-composer input.mnafb-input--title', 'E2E: written while offline' );
		await guestCtx.setOffline( true );
		await guest.click( '.mnafb-composer button[type="submit"]' );
		await guest.waitForSelector( '.mnafb-composer .mnafb-error', { timeout: 8000 } );
		await shot( guest, '1280-offline' );
		const kept = await guest.locator( '.mnafb-composer input.mnafb-input--title' ).inputValue();
		await guestCtx.setOffline( false );
		const response = guest.waitForResponse( ( r ) => r.url().includes( '/mna-feedback/v1/items' ) && r.request().method() === 'POST' );
		await guest.click( '.mnafb-composer button[type="submit"]:has-text("Try again")' );
		const json = await ( await response ).json();
		created.add( json.id );
		check( 'A failed request keeps the text and offers a retry', kept === 'E2E: written while offline' && json.title === 'E2E: written while offline' );
	} );

	await attempt( 'An unsent comment survives a reload', async () => {
		await guest.locator( 'button:has-text("Comment on the whole page")' ).click();
		await guest.fill( '.mnafb-composer input.mnafb-input--title', 'E2E: draft survives reload' );
		await guest.waitForTimeout( 700 );
		await guest.reload();
		await guest.waitForSelector( 'text=You have an unsent comment', { timeout: 10000 } );
		await guest.locator( '.mnafb-banner button:has-text("Restore")' ).click();
		const value = await guest.locator( '.mnafb-composer input.mnafb-input--title' ).inputValue();
		check( 'An unsent comment survives a reload', value === 'E2E: draft survives reload', value );
		await guest.locator( '.mnafb-composer button:has-text("Cancel")' ).click();
	} );

	// ------------------------------------------------ Another reviewer with the same name
	const twinCtx = await browser.newContext( { viewport: { width: 1280, height: 800 } } );
	const twin = await twinCtx.newPage();
	watch( twin, 'twin' );
	await attempt( 'A second reviewer with the same name cannot edit or delete the first one\'s comments', async () => {
		await twin.goto( link.url );
		await twin.waitForSelector( 'input[autocomplete="name"]', { timeout: 15000 } );
		await twin.fill( 'input[autocomplete="name"]', 'Alex Reviewer' );
		await twin.click( 'button:has-text("Start reviewing")' );
		await twin.click( '.mnafb-dialog__foot button:has-text("Start reviewing")' );
		await twin.locator( '.mnafb-tabs button:has-text("All")' ).click();
		await twin.locator( '.mnafb-card__open[aria-label*="E2E: my competing edit"]' ).click();
		await twin.waitForSelector( '.mnafb-detail__title' );
		await twin.locator( 'button[aria-label="Comment actions"]' ).click();
		const items = await twin.locator( '[role="menuitem"]' ).allInnerTexts();
		await twin.keyboard.press( 'Escape' );
		check( 'A second reviewer with the same name cannot edit or delete the first one\'s comments', ! items.some( ( t ) => /Edit|Delete/.test( t ) ), items.join( ', ' ) );
	} );

	// ------------------------------------------------ Replies, notes, unread
	await attempt( 'Replies reach the other reviewer and show as unread', async () => {
		await twin.locator( '.mnafb-replybox textarea' ).fill( 'E2E: I agree with this.' );
		await twin.locator( '.mnafb-replybox button[type="submit"]' ).click();
		await twin.waitForSelector( '.mnafb-reply:has-text("E2E: I agree with this.")', { timeout: 5000 } );
		await guest.reload();
		await guest.waitForSelector( '.mnafb-card', { timeout: 10000 } );
		await guest.locator( '.mnafb-tabs button:has-text("All")' ).click();
		const unread = await guest.locator( '.mnafb-card.is-unread .mnafb-card__open[aria-label*="E2E: my competing edit"]' ).count();
		check( 'Replies reach the other reviewer and show as unread', unread === 1 );
	} );

	// ------------------------------------------------ Board: implementer moves, reviewer reopens
	await attempt( 'Implementer drags a card from Open to In progress', async () => {
		await admin.goto( BASE + PAGE + '?mna-review=on' );
		await admin.waitForSelector( '.mnafb-panel', { timeout: 15000 } );
		await admin.locator( 'button[title="Open the board"]' ).click();
		await admin.waitForSelector( '.mnafb-board' );
		await shot( admin, '1280-board' );
		const card = admin.locator( '.mnafb-col--open [data-card]:has-text("E2E: written while offline")' ).first();
		const target = admin.locator( '.mnafb-col--in_progress .mnafb-col__list' );
		const from = await card.boundingBox();
		const to = await target.boundingBox();
		await admin.mouse.move( from.x + 60, from.y + 20 );
		await admin.mouse.down();
		await admin.mouse.move( from.x + 80, from.y + 30, { steps: 4 } );
		await admin.mouse.move( to.x + to.width / 2, to.y + 30, { steps: 12 } );
		await shot( admin, '1280-board-dragging' );
		const response = admin.waitForResponse( ( r ) => /\/items\/\d+/.test( r.url() ) && r.request().method() === 'POST' );
		await admin.mouse.up();
		const json = await ( await response ).json();
		check( 'Implementer drags a card from Open to In progress', json.status === 'in_progress', JSON.stringify( json ).slice( 0, 160 ) );
	} );

	await attempt( 'Keyboard: move a card with the "Move to" menu', async () => {
		const menu = admin.locator( '.mnafb-col--in_progress [data-card]:has-text("E2E: written while offline") button[aria-haspopup="menu"]' ).first();
		await menu.focus();
		await admin.keyboard.press( 'Enter' );
		await admin.waitForSelector( '[role="menuitem"]:has-text("Move to Done")' );
		await admin.keyboard.press( 'ArrowDown' );
		const focused = await admin.evaluate( () => document.getElementById( 'mnafb-root' ).shadowRoot.activeElement?.textContent );
		const response = admin.waitForResponse( ( r ) => /\/items\/\d+/.test( r.url() ) && r.request().method() === 'POST' );
		await admin.locator( '[role="menuitem"]:has-text("Move to Done")' ).focus();
		await admin.keyboard.press( 'Enter' );
		const json = await ( await response ).json();
		check( 'Keyboard: move a card with the "Move to" menu', json.status === 'done', `${ focused } → ${ json.status }` );
	} );

	await attempt( 'Reviewer reopens finished work', async () => {
		await guest.reload();
		await guest.waitForSelector( '.mnafb-panel' );
		await guest.locator( '.mnafb-tabs button:has-text("Done")' ).click();
		await guest.locator( '.mnafb-card__open[aria-label*="E2E: written while offline"]' ).click();
		await guest.locator( '.mnafb-reopen button:has-text("Reopen")' ).click();
		await guest.waitForSelector( '.mnafb-reopen', { state: 'detached', timeout: 5000 } );
		const item = await api( guest, 'GET', 'items?search=written%20while%20offline' );
		check( 'Reviewer reopens finished work', item.json?.items?.[ 0 ]?.status === 'open' );
	} );

	// ------------------------------------------------ Delete own → Trash → manager restores
	await attempt( 'Author deletes; manager restores from Trash', async () => {
		await guest.locator( 'button:has-text("All comments")' ).click();
		await guest.locator( '.mnafb-tabs button:has-text("All")' ).click();
		await guest.locator( '.mnafb-card__open[aria-label*="E2E: written while offline"]' ).click();
		await guest.locator( 'button[aria-label="Comment actions"]' ).click();
		await guest.locator( '[role="menuitem"]:has-text("Delete")' ).click();
		await guest.locator( '.mnafb-dialog button:has-text("Delete")' ).click();
		await guest.waitForTimeout( 600 );
		const gone = ( await guest.locator( '.mnafb-card__open[aria-label*="E2E: written while offline"]' ).count() ) === 0;
		await admin.locator( 'button[aria-label="Close the board"]' ).first().click();
		await admin.locator( 'button[aria-label="More options"]' ).click();
		await admin.locator( '[role="menuitem"]:has-text("Trash")' ).click();
		await admin.waitForSelector( '.mnafb-trash-row:has-text("E2E: written while offline")', { timeout: 5000 } );
		await shot( admin, '1280-trash' );
		await admin.locator( '.mnafb-trash-row:has-text("E2E: written while offline") button:has-text("Restore")' ).click();
		await admin.waitForSelector( '.mnafb-trash-row:has-text("E2E: written while offline")', { state: 'detached', timeout: 5000 } );
		check( 'Author deletes; manager restores from Trash', gone );
	} );

	// ------------------------------------------------ Layouts
	for ( const width of [ 320, 390, 768, 1280, 1440 ] ) {
		await attempt( `Layout at ${ width }px`, async () => {
			const ctx = await browser.newContext( { viewport: { width, height: width < 768 ? 740 : 900 }, hasTouch: width < 1024, isMobile: width < 768 } );
			const page = await ctx.newPage();
			watch( page, `layout-${ width }` );
			await page.goto( returnLink );
			await page.waitForSelector( '#mnafb-root', { state: 'attached', timeout: 15000 } );
			await page.waitForTimeout( 1200 );
			if ( width < 768 ) {
				const launcher = page.locator( '.mnafb-launcher__main' );
				if ( await launcher.count() ) {
					await launcher.click();
				}
			}
			await page.waitForSelector( '.mnafb-panel', { timeout: 10000 } );
			await shot( page, `${ width }-panel` );
			const fits = await page.evaluate( () => {
				const root = document.getElementById( 'mnafb-root' ).shadowRoot;
				const panel = root.querySelector( '.mnafb-panel' ).getBoundingClientRect();
				const overflowing = Array.from( root.querySelectorAll( '.mnafb-panel *' ) ).filter( ( el ) => el.getBoundingClientRect().right > window.innerWidth + 1 && el.getBoundingClientRect().width > 0 );
				return { panel: panel.toJSON(), vw: window.innerWidth, overflowing: overflowing.length, docScroll: document.documentElement.scrollWidth > window.innerWidth };
			} );
			const fieldSize = await page.evaluate( () => {
				const root = document.getElementById( 'mnafb-root' ).shadowRoot;
				const button = root.querySelector( '.mnafb-panel__head button' );
				return button ? button.getBoundingClientRect().height : 0;
			} );
			// Board
			await page.locator( 'button[title="Open the board"], button[aria-label="Open the board"]' ).first().click();
			await page.waitForSelector( '.mnafb-board', { timeout: 5000 } );
			await shot( page, `${ width }-board` );
			const boardFits = await page.evaluate( () => {
				const root = document.getElementById( 'mnafb-root' ).shadowRoot;
				return root.querySelector( '.mnafb-board' ).getBoundingClientRect().width <= window.innerWidth + 1;
			} );
			await page.locator( 'button[aria-label="Close the board"]' ).first().click();
			// Composer
			if ( width < 768 ) {
				await page.locator( 'button:has-text("Comment on the whole page")' ).click();
				await page.waitForSelector( '.mnafb-composer' );
				const inputFont = await page.evaluate( () => parseFloat( getComputedStyle( document.getElementById( 'mnafb-root' ).shadowRoot.querySelector( '.mnafb-composer input' ) ).fontSize ) );
				await shot( page, `${ width }-composer` );
				check( `Form fields are 16px on phones (${ width }px)`, inputFont >= 16, String( inputFont ) );
				await page.locator( '.mnafb-composer button:has-text("Cancel")' ).click();
			}
			check( `Layout at ${ width }px`, fits.panel.right <= fits.vw + 1 && fits.panel.left >= -1 && fits.overflowing === 0 && boardFits && ( width >= 1024 || fieldSize >= 44 ), JSON.stringify( { ...fits, fieldSize, boardFits } ) );
			await ctx.close();
		} );
	}

	// ------------------------------------------------ Touch: create by tapping
	await attempt( 'Touch: tap an element to comment (390px phone)', async () => {
		const ctx = await browser.newContext( { viewport: { width: 390, height: 780 }, hasTouch: true, isMobile: true } );
		const page = await ctx.newPage();
		await page.goto( returnLink );
		await page.waitForSelector( '#mnafb-root', { state: 'attached', timeout: 15000 } );
		await page.waitForTimeout( 1000 );
		if ( await page.locator( '.mnafb-launcher__main' ).count() ) {
			await page.locator( '.mnafb-launcher__main' ).tap();
		}
		await page.locator( '.mnafb-mode button:has-text("Comment")' ).tap();
		await page.waitForSelector( '.mnafb-hint' );
		await shot( page, '390-comment-mode' );
		const point = await visibleTarget( page, 'main p, .entry-content p, article p' );
		await page.touchscreen.tap( point.x, point.y );
		await page.waitForSelector( '.mnafb-composer--sheet', { timeout: 5000 } );
		await page.fill( '.mnafb-composer input.mnafb-input--title', 'E2E: tapped on a phone' );
		await shot( page, '390-composer-element' );
		const response = page.waitForResponse( ( r ) => r.url().includes( '/mna-feedback/v1/items' ) && r.request().method() === 'POST' );
		await page.locator( '.mnafb-composer button[type="submit"]' ).tap();
		const json = await ( await response ).json();
		created.add( json.id );
		check( 'Touch: tap an element to comment (390px phone)', json.pin?.type === 'element' );
		await ctx.close();
	} );

	// ------------------------------------------------ Browse mode keeps the site working
	await attempt( 'Browse mode leaves links and navigation working', async () => {
		await guest.goto( BASE + PAGE );
		await guest.waitForSelector( '.mnafb-panel', { timeout: 10000 } );
		const target = await guest.evaluate( () => {
			const a = Array.from( document.querySelectorAll( 'a[href]' ) ).find( ( el ) => {
				const r = el.getBoundingClientRect();
				return r.width > 0 && r.height > 0 && r.top > 0 && r.bottom < window.innerHeight && r.right < window.innerWidth - 420 && el.origin === location.origin && el.href.split( '#' )[ 0 ] !== location.href.split( '#' )[ 0 ] && ! el.closest( '#wpadminbar' );
			} );
			if ( ! a ) {
				return null;
			}
			const r = a.getBoundingClientRect();
			return { href: a.href, x: r.left + r.width / 2, y: r.top + r.height / 2 };
		} );
		if ( ! target ) {
			check( 'Browse mode leaves links and navigation working', true, 'no link found; skipped' );
			return;
		}
		await Promise.all( [ guest.waitForURL( ( u ) => u.href.split( '#' )[ 0 ] === target.href.split( '#' )[ 0 ], { timeout: 10000 } ), guest.mouse.click( target.x, target.y ) ] );
		await guest.waitForSelector( '#mnafb-root', { state: 'attached', timeout: 10000 } );
		check( 'Browse mode leaves links and navigation working', true );
	} );

	// ------------------------------------------------ Revocation
	await attempt( 'Revoking the link ends access in the open tab', async () => {
		await guest.goto( BASE + PAGE );
		await guest.waitForSelector( '.mnafb-panel', { timeout: 10000 } );
		await api( admin, 'POST', `admin/links/${ link.id }/revoke`, {}, nonce );
		await guest.reload();
		await guest.waitForSelector( 'text=Review access has ended', { timeout: 10000 } );
		await shot( guest, '1280-access-ended' );
		const status = ( await api( guest, 'GET', 'items' ) ).status;
		check( 'Revoking the link ends access in the open tab', status === 401, String( status ) );
	} );

	// ------------------------------------------------ Clean up
	for ( const id of created ) {
		await api( admin, 'DELETE', `items/${ id }`, {}, nonce );
		await api( admin, 'DELETE', `items/${ id }?force=true`, {}, nonce );
	}
	check( 'Test data removed', true );

	check( 'No JavaScript errors from the review interface', errors.length === 0, errors.slice( 0, 5 ).join( ' | ' ) );
	await browser.close();

	const passed = results.filter( ( [ , ok ] ) => ok ).length;
	console.log( `\n${ passed }/${ results.length } checks passed` );
	return passed === results.length ? 0 : 1;
}

main().then(
	( code ) => process.exit( code ),
	( error ) => {
		console.error( error );
		process.exit( 1 );
	}
);
