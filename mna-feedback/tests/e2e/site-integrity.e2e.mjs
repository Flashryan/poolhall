#!/usr/bin/env node
/**
 * Checks that review mode leaves a real site working: layout unchanged,
 * Elementor pins robust to layout and text changes, WooCommerce basket and
 * checkout, forms and navigation.
 *
 *   MNAFB_BASE=https://example.com MNAFB_LINK='https://example.com/?mna-review=…' \
 *   MNAFB_PAGE=/about-us/ MNAFB_PRODUCT=/product/some-product/ MNAFB_FORM_PAGE=/contact-us/ \
 *   MNAFB_SHOTS=./shots node tests/e2e/site-integrity.e2e.mjs
 *
 * Joins through the share link as a guest. Prints the ids of feedback it
 * created so they can be removed afterwards (guests cannot purge).
 */

import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire( import.meta.url );
function loadPlaywright() {
	for ( const candidate of [ process.env.PLAYWRIGHT_MODULE, 'playwright', '/opt/node22/lib/node_modules/playwright' ].filter( Boolean ) ) {
		try {
			return require( candidate );
		} catch {
			/* next */
		}
	}
	throw new Error( 'Playwright not found. Set PLAYWRIGHT_MODULE.' );
}
const { chromium } = loadPlaywright();

const BASE = ( process.env.MNAFB_BASE || '' ).replace( /\/$/, '' );
const LINK = process.env.MNAFB_LINK || '';
const PAGE = process.env.MNAFB_PAGE || '/';
const PRODUCT = process.env.MNAFB_PRODUCT || '';
const FORM_PAGE = process.env.MNAFB_FORM_PAGE || '';
const SHOTS = process.env.MNAFB_SHOTS || '';
// Light mode for rate-limited hosts: skip images, media, fonts and third-party
// requests (the same in every measurement) and pause between page loads.
const LIGHT = process.env.MNAFB_LIGHT === '1';
const PAUSE = LIGHT ? 4000 : 0;
if ( SHOTS ) {
	mkdirSync( SHOTS, { recursive: true } );
}

const results = [];
const created = [];
function check( name, ok, detail = '' ) {
	results.push( !! ok );
	console.log( `[${ ok ? 'PASS' : 'FAIL' }] ${ name }${ ! ok && detail ? ' — ' + detail : '' }` );
}
async function attempt( name, fn ) {
	try {
		await fn();
	} catch ( error ) {
		check( name, false, String( error?.message || error ).split( '\n' )[ 0 ] );
	}
}
async function shot( page, name ) {
	if ( SHOTS ) {
		await page.waitForTimeout( 400 );
		await page.screenshot( { path: `${ SHOTS }/${ name }.png` } );
	}
}

async function lighten( context ) {
	if ( ! LIGHT ) {
		return;
	}
	const host = new URL( BASE ).host;
	await context.route( '**/*', ( route ) => {
		const request = route.request();
		const type = request.resourceType();
		let external = false;
		try {
			external = new URL( request.url() ).host !== host;
		} catch {}
		if ( external || [ 'image', 'media', 'font' ].includes( type ) ) {
			return route.abort();
		}
		return route.continue();
	} );
}

async function go( page, url, options = {} ) {
	if ( PAUSE ) {
		await page.waitForTimeout( PAUSE );
	}
	return page.goto( url, { timeout: 60000, ...options } );
}

/** Positions of visible content elements and document size, for layout comparison. */
async function layout( page ) {
	return page.evaluate( () => {
		const moving = '.swiper, .swiper-container, .elementor-carousel, .slick-slider, .elementor-slides, .e-n-carousel, [class*="carousel"], [class*="slider"], [class*="marquee"], [class*="ticker"], iframe';
		const pick = Array.from( document.querySelectorAll( 'h1, h2, h3, p, .elementor-button, .elementor-widget-heading, .elementor-widget-text-editor' ) )
			.filter( ( el ) => {
				const r = el.getBoundingClientRect();
				return r.width > 20 && r.height > 8 && ! el.closest( '#mnafb-root' ) && ! el.closest( moving );
			} )
			.slice( 0, 60 );
		return {
			width: document.documentElement.scrollWidth,
			height: document.documentElement.scrollHeight,
			bodyClass: document.body.className,
			stylesheets: document.querySelectorAll( 'link[rel="stylesheet"], style' ).length,
			rects: pick.map( ( el ) => {
				const r = el.getBoundingClientRect();
				return [ Math.round( r.left + scrollX ), Math.round( r.top + scrollY ), Math.round( r.width ), Math.round( r.height ) ];
			} ),
		};
	} );
}

async function main() {
	if ( ! BASE || ! LINK ) {
		console.log( 'Set MNAFB_BASE and MNAFB_LINK' );
		return 2;
	}
	const proxyServer = process.env.HTTPS_PROXY || process.env.https_proxy;
	const remote = ! /^https?:\/\/(localhost|127\.0\.0\.1)/.test( BASE );
	const browser = await chromium.launch( {
		...( remote ? { channel: 'chromium' } : {} ),
		...( remote && proxyServer ? { proxy: { server: proxyServer } } : {} ),
	} );
	const overlayErrors = [];
	const siteErrors = [];
	let throttled = 0;
	const watch = ( page ) => {
		page.on( 'pageerror', ( e ) => ( /mna-feedback|mnafb/.test( String( e.stack ) ) ? overlayErrors : siteErrors ).push( e.message ) );
		page.on( 'response', ( r ) => {
			if ( r.status() === 429 ) {
				throttled++;
			}
		} );
	};

	// ------------------------------------------------ Join, then compare layout within one page load
	const ctx = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	await lighten( ctx );
	const page = await ctx.newPage();
	watch( page );
	await go( page, LINK );
	await page.waitForSelector( 'input[autocomplete="name"]', { timeout: 30000 } );
	await page.fill( 'input[autocomplete="name"]', 'Integrity Check' );
	await page.click( 'button:has-text("Start reviewing")' );
	await page.click( '.mnafb-dialog__foot button:has-text("Start reviewing")', { timeout: 20000 } );
	await page.waitForSelector( '.mnafb-panel', { timeout: 20000 } );
	await page.waitForTimeout( 2500 );

	await attempt( 'Review mode does not change the page layout', async () => {
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		await page.waitForTimeout( 400 );
		const open = await layout( page ); // overlay present, panel open
		await page.evaluate( () => ( document.getElementById( 'mnafb-root' ).style.display = 'none' ) );
		await page.waitForTimeout( 400 );
		const hidden = await layout( page ); // as if the plugin were absent
		await page.evaluate( () => ( document.getElementById( 'mnafb-root' ).style.display = '' ) );
		await page.locator( 'button[title="Open the board"]' ).click();
		await page.waitForSelector( '.mnafb-board' );
		await page.waitForTimeout( 400 );
		const board = await layout( page ); // board covering the page
		await shot( page, 'site-board' );
		await page.locator( 'button[aria-label="Close the board"]' ).first().click();
		const diff = ( x, y ) => x.rects.filter( ( r, i ) => ! y.rects[ i ] || r.some( ( v, j ) => Math.abs( v - y.rects[ i ][ j ] ) > 1 ) ).length;
		const same = ( x, y ) => x.width === y.width && x.height === y.height && x.bodyClass === y.bodyClass && x.stylesheets === y.stylesheets && x.rects.length === y.rects.length && diff( x, y ) === 0;
		check(
			'Review mode does not change the page layout',
			same( open, hidden ) && same( board, hidden ),
			JSON.stringify( { elements: hidden.rects.length, movedWithPanel: diff( open, hidden ), movedWithBoard: diff( board, hidden ), size: [ hidden.width, hidden.height, open.width, open.height ] } )
		);
		console.log( `      (${ hidden.rects.length } content elements compared)` );
	} );
	await shot( page, 'site-with-review' );

	// ------------------------------------------------ Elementor anchoring
	const hasElementor = await page.evaluate( () => !! document.querySelector( '.elementor-widget-heading .elementor-heading-title' ) );
	if ( ! hasElementor ) {
		console.log( '(No Elementor heading widgets on this page: Elementor checks skipped.)' );
	} else {
		let widgetId = null;
		let itemTitle = 'Integrity: Elementor heading pin';
		await attempt( 'Pins on Elementor widgets record the Elementor element', async () => {
			const target = await page.evaluate( () => {
				const el = Array.from( document.querySelectorAll( '.elementor-widget-heading .elementor-heading-title' ) ).find( ( e ) => {
					const r = e.getBoundingClientRect();
					return r.width > 60 && r.height > 12 && ! e.closest( '[data-elementor-type="header"], [data-elementor-type="footer"], header, nav' );
				} );
				if ( ! el ) {
					return null;
				}
				el.scrollIntoView( { block: 'center' } );
				return true;
			} );
			if ( ! target ) {
				throw new Error( 'No Elementor heading widget on this page' );
			}
			await page.waitForTimeout( 500 );
			const point = await page.evaluate( () => {
				const el = Array.from( document.querySelectorAll( '.elementor-widget-heading .elementor-heading-title' ) ).find( ( e ) => {
					const r = e.getBoundingClientRect();
					return r.width > 60 && r.top > 80 && r.bottom < innerHeight - 60 && r.left + 30 < innerWidth - 420 && ! e.closest( '[data-elementor-type="header"], [data-elementor-type="footer"], header, nav' );
				} );
				const r = el.getBoundingClientRect();
				return { x: r.left + Math.min( 30, r.width / 3 ), y: r.top + r.height / 2 };
			} );
			await page.locator( '.mnafb-mode button:has-text("Comment")' ).click();
			await page.mouse.click( point.x, point.y );
			await page.waitForSelector( '.mnafb-composer', { timeout: 8000 } );
			await page.fill( '.mnafb-composer input.mnafb-input--title', itemTitle );
			const response = page.waitForResponse( ( r ) => r.url().includes( '/mna-feedback/v1/items' ) && r.request().method() === 'POST' );
			await page.click( '.mnafb-composer button[type="submit"]' );
			const item = await ( await response ).json();
			created.push( item.id );
			widgetId = item.pin?.anchor?.elementor?.id || null;
			await page.keyboard.press( 'Escape' );
			check( 'Pins on Elementor widgets record the Elementor element', !! widgetId, JSON.stringify( item.pin?.anchor || item ).slice( 0, 200 ) );
		} );

		const pinFor = () =>
			page.evaluate( ( title ) => {
				const root = document.getElementById( 'mnafb-root' ).shadowRoot;
				const pin = Array.from( root.querySelectorAll( '.mnafb-pin' ) ).find( ( p ) => ( p.getAttribute( 'aria-label' ) || '' ).includes( title ) );
				return pin && pin.style.display !== 'none' ? pin.getBoundingClientRect().toJSON() : null;
			}, itemTitle );

		await attempt( 'Pin follows its Elementor widget when the layout changes', async () => {
			if ( ! widgetId ) {
				throw new Error( 'No widget id' );
			}
			// Simulate an Elementor layout edit: the widget moves to a different container.
			await page.evaluate( ( id ) => {
				const widget = document.querySelector( `.elementor-element[data-id="${ id }"]` );
				const doc = widget.closest( '[data-elementor-type="wp-page"], [data-elementor-type="single-page"], .elementor' ) || document.body;
				const holder = document.createElement( 'div' );
				holder.id = 'mnafb-integrity-moved';
				holder.style.padding = '40px';
				doc.appendChild( holder );
				holder.appendChild( widget );
				widget.scrollIntoView( { block: 'center' } );
			}, widgetId );
			await page.waitForTimeout( 1500 );
			const pin = await pinFor();
			const el = await page.evaluate( ( id ) => document.querySelector( `.elementor-element[data-id="${ id }"]` ).getBoundingClientRect().toJSON(), widgetId );
			await shot( page, 'site-elementor-moved' );
			check( 'Pin follows its Elementor widget when the layout changes', pin && pin.bottom >= el.top - 4 && pin.bottom <= el.bottom + 40 && pin.left >= el.left - 4 && pin.left <= el.right, JSON.stringify( { pin, el } ) );
		} );

		await attempt( 'Pin stays attached after the widget text is edited', async () => {
			await page.evaluate( ( id ) => {
				const title = document.querySelector( `.elementor-element[data-id="${ id }"] .elementor-heading-title` );
				title.textContent = 'Completely rewritten heading after feedback';
			}, widgetId );
			await page.waitForTimeout( 1500 );
			check( 'Pin stays attached after the widget text is edited', !! ( await pinFor() ) );
		} );

		await attempt( 'Removing the widget reports "Original element unavailable"', async () => {
			await page.evaluate( ( id ) => document.querySelector( `.elementor-element[data-id="${ id }"]` ).remove(), widgetId );
			await page.waitForTimeout( 1500 );
			await page.locator( `.mnafb-card__open[aria-label*="${ itemTitle }"]` ).click();
			await page.waitForSelector( 'text=Original element unavailable', { timeout: 8000 } );
			await shot( page, 'site-elementor-removed' );
			check( 'Removing the widget reports "Original element unavailable"', ! ( await pinFor() ) );
		} );

	}

	// ------------------------------------------------ Navigation
	await attempt( 'Site navigation works in review mode', async () => {
		await go( page, BASE + PAGE, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( '#mnafb-root', { state: 'attached', timeout: 30000 } );
		const target = await page.evaluate( () => {
			const a = Array.from( document.querySelectorAll( 'header a[href], nav a[href], [data-elementor-type="header"] a[href]' ) ).find( ( el ) => {
				const r = el.getBoundingClientRect();
				return r.width > 0 && r.height > 0 && r.top >= 0 && r.bottom < innerHeight && r.right < innerWidth - 420 && el.origin === location.origin && el.pathname !== location.pathname && ! el.getAttribute( 'href' ).startsWith( '#' );
			} );
			if ( ! a ) {
				return null;
			}
			const r = a.getBoundingClientRect();
			return { href: a.href, x: r.left + r.width / 2, y: r.top + r.height / 2 };
		} );
		if ( ! target ) {
			throw new Error( 'No header link found' );
		}
		await Promise.all( [ page.waitForURL( ( u ) => u.pathname === new URL( target.href ).pathname, { timeout: 30000 } ), page.mouse.click( target.x, target.y ) ] );
		await page.waitForSelector( '#mnafb-root', { state: 'attached', timeout: 20000 } );
		check( 'Site navigation works in review mode', true );
	} );

	// ------------------------------------------------ WooCommerce basket and checkout
	if ( PRODUCT ) {
		await attempt( 'WooCommerce: add to basket works in review mode', async () => {
			await go( page, BASE + PRODUCT, { waitUntil: 'domcontentloaded' } );
			await page.waitForSelector( '#mnafb-root', { state: 'attached', timeout: 20000 } );
			const button = page.locator( 'form.cart button.single_add_to_cart_button, form.cart [name="add-to-cart"]' ).first();
			await button.scrollIntoViewIfNeeded();
			await Promise.all( [ page.waitForLoadState( 'domcontentloaded' ), button.click() ] );
			await page.waitForTimeout( 2500 );
			await go( page, BASE + '/cart/', { waitUntil: 'domcontentloaded' } );
			await page.waitForTimeout( 1500 );
			const rows = await page.locator( '.woocommerce-cart-form__cart-item, .wc-block-cart-items__row, .cart_item' ).count();
			await shot( page, 'site-cart' );
			check( 'WooCommerce: add to basket works in review mode', rows > 0, `${ rows } rows` );
		} );
		await attempt( 'WooCommerce: checkout form is usable in review mode', async () => {
			await go( page, BASE + '/checkout/', { waitUntil: 'domcontentloaded' } );
			await page.waitForTimeout( 2500 );
			const email = page.locator( '#billing_email, #email, input[type="email"][autocomplete="email"]' ).first();
			await email.scrollIntoViewIfNeeded();
			await email.fill( 'integrity-check@example.com' );
			const value = await email.inputValue();
			await shot( page, 'site-checkout' );
			check( 'WooCommerce: checkout form is usable in review mode', value === 'integrity-check@example.com' );
		} );
		await attempt( 'WooCommerce: basket emptied again', async () => {
			await go( page, BASE + '/cart/', { waitUntil: 'domcontentloaded' } );
			await page.waitForTimeout( 1500 );
			for ( let i = 0; i < 5; i++ ) {
				const remove = page.locator( 'a.remove, .wc-block-cart-item__remove-link' ).first();
				if ( ! ( await remove.count() ) ) {
					break;
				}
				await Promise.all( [ page.waitForLoadState( 'domcontentloaded' ), remove.click() ] );
				await page.waitForTimeout( 2000 );
			}
			const rows = await page.locator( '.woocommerce-cart-form__cart-item, .wc-block-cart-items__row, .cart_item' ).count();
			check( 'WooCommerce: basket emptied again', rows === 0, `${ rows } rows left` );
		} );
	}

	// ------------------------------------------------ Forms
	if ( FORM_PAGE ) {
		await attempt( 'Forms accept input in review mode', async () => {
			await go( page, BASE + FORM_PAGE, { waitUntil: 'domcontentloaded' } );
			await page.waitForSelector( '#mnafb-root', { state: 'attached', timeout: 20000 } );
			const field = page.locator( 'form input[type="text"]:visible, form textarea:visible' ).first();
			await field.scrollIntoViewIfNeeded();
			await field.click();
			await page.keyboard.type( 'Typed while reviewing' );
			const value = await field.inputValue();
			check( 'Forms accept input in review mode', value.includes( 'Typed while reviewing' ), value );
		} );
	}

	check( 'No JavaScript errors from the review interface', overlayErrors.length === 0, overlayErrors.slice( 0, 3 ).join( ' | ' ) );
	if ( siteErrors.length ) {
		console.log( `(Site's own JavaScript reported ${ siteErrors.length } error(s): ${ siteErrors.slice( 0, 3 ).join( ' | ' ) })` );
	}
	console.log( `Created feedback ids: ${ created.join( ',' ) || 'none' }` );
	if ( throttled ) {
		console.log( `(The host answered ${ throttled } request(s) with 429 Too Many Requests during this run.)` );
	}
	await browser.close();
	const passed = results.filter( Boolean ).length;
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
