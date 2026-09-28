/**
 * Entry point. Loaded only for people in review mode (see Frontend.php).
 *
 * The interface lives in a Shadow DOM attached to a zero-size fixed host, so
 * the site's styles cannot leak in, ours cannot leak out, and the page layout
 * and Elementor documents are left untouched.
 */

import { createRoot } from 'react-dom/client';
import css from './styles.css';
import { boot, initApi } from './actions';
import { HOST_ID } from './anchor/dom';
import { App } from './components/App';

// Release events (mouseup, pointerup, touchend) are deliberately left alone so
// drags that started on the page still finish when released over the panel.
const ISOLATED_EVENTS = [ 'keydown', 'keyup', 'keypress', 'click', 'dblclick', 'mousedown', 'pointerdown', 'touchstart', 'input', 'change', 'paste', 'copy', 'cut', 'focusin', 'focusout', 'contextmenu' ];

function start(): void {
	const config = window.mnafbBoot;
	if ( ! config || window.__mnafbLoaded || document.getElementById( HOST_ID ) ) {
		return;
	}
	window.__mnafbLoaded = true;

	const host = document.createElement( 'div' );
	host.id = HOST_ID;
	host.setAttribute( 'data-version', __MNAFB_VERSION__ );
	host.style.cssText = 'all:initial;position:fixed;top:0;left:0;width:0;height:0;z-index:2147483000;';
	const shadow = host.attachShadow( { mode: 'open' } );

	const style = document.createElement( 'style' );
	style.textContent = css;
	shadow.appendChild( style );

	const container = document.createElement( 'div' );
	shadow.appendChild( container );

	// Keep the site's own keyboard shortcuts and click handlers from reacting to
	// what people do inside the review interface.
	ISOLATED_EVENTS.forEach( ( type ) => shadow.addEventListener( type, ( event ) => event.stopPropagation() ) );

	document.documentElement.appendChild( host );

	initApi( config.rest );
	const root = createRoot( container );
	root.render( <App /> );
	void boot();

	window.addEventListener( 'mnafb:close', () => {
		window.setTimeout( () => {
			root.unmount();
			host.remove();
			window.__mnafbLoaded = false;
		}, 0 );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start, { once: true } );
} else {
	start();
}
