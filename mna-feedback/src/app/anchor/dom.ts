/** DOM helpers shared by anchor capture and resolution. */

export const HOST_ID = 'mnafb-root';

export function isOurs( node: Node | null | undefined ): boolean {
	if ( ! node ) {
		return false;
	}
	const el = node.nodeType === 1 ? ( node as Element ) : node.parentElement;
	return !! el && ( el.id === HOST_ID || !! el.closest( '#' + HOST_ID ) );
}

export function normText( value: string ): string {
	return value.replace( /\s+/g, ' ' ).trim().toLowerCase();
}

/** Visible-ish text of an element, normalised and truncated. */
export function textOf( el: Element, max = 160 ): string {
	const raw = el instanceof HTMLElement ? el.innerText || el.textContent || '' : el.textContent || '';
	return normText( raw ).slice( 0, max );
}

/** Visible text with whitespace collapsed, original case (for display). */
export function displayText( el: Element, max = 160 ): string {
	const raw = el instanceof HTMLElement ? el.innerText || el.textContent || '' : el.textContent || '';
	return raw.replace( /\s+/g, ' ' ).trim().slice( 0, max );
}

/** Cheaper text used when scanning many candidates (no layout). */
export function rawTextOf( el: Element, max = 160 ): string {
	return normText( el.textContent || '' ).slice( 0, max );
}

function esc( value: string ): string {
	return typeof CSS !== 'undefined' && CSS.escape ? CSS.escape( value ) : value.replace( /[^\w-]/g, '\\$&' );
}

/**
 * Structural path from `root` (exclusive) down to `el`, using
 * tag:nth-of-type steps. Empty when el === root.
 */
export function pathFrom( root: Element, el: Element ): string | null {
	const parts: string[] = [];
	let node: Element | null = el;
	while ( node && node !== root ) {
		const parent: Element | null = node.parentElement;
		if ( ! parent ) {
			return null;
		}
		let index = 1;
		let sibling = node.previousElementSibling;
		while ( sibling ) {
			if ( sibling.tagName === node.tagName ) {
				index++;
			}
			sibling = sibling.previousElementSibling;
		}
		parts.unshift( `${ esc( node.tagName.toLowerCase() ) }:nth-of-type(${ index })` );
		node = parent;
	}
	return node === root ? parts.join( ' > ' ) : null;
}

export function follow( root: Element, path: string | undefined ): Element | null {
	if ( ! path ) {
		return root;
	}
	try {
		return root.querySelector( ':scope > ' + path );
	} catch {
		return null;
	}
}

/** Word-overlap similarity between two normalised strings (0–1). */
export function similarity( a: string, b: string ): number {
	if ( a === b ) {
		return 1;
	}
	if ( ! a || ! b ) {
		return 0;
	}
	if ( a.length >= 12 && b.length >= 12 && ( a.includes( b ) || b.includes( a ) ) ) {
		return 0.9;
	}
	const words = ( s: string ) => new Set( s.split( ' ' ).filter( ( w ) => w.length > 1 ) );
	const wa = words( a );
	const wb = words( b );
	if ( ! wa.size || ! wb.size ) {
		return 0;
	}
	let shared = 0;
	wa.forEach( ( w ) => {
		if ( wb.has( w ) ) {
			shared++;
		}
	} );
	return shared / ( wa.size + wb.size - shared );
}

/** Whether an element is rendered with a non-zero box at this size. */
export function isRendered( el: Element ): boolean {
	if ( ! el.isConnected ) {
		return false;
	}
	const anyEl = el as Element & { checkVisibility?: ( options?: Record< string, boolean > ) => boolean };
	if ( typeof anyEl.checkVisibility === 'function' && ! anyEl.checkVisibility( { checkOpacity: false, checkVisibilityCSS: true } ) ) {
		return false;
	}
	const rect = el.getBoundingClientRect();
	return rect.width > 0 || rect.height > 0;
}

const WIDGET_LABELS: Record< string, string > = {
	heading: 'Heading',
	'text-editor': 'Text',
	image: 'Image',
	button: 'Button',
	'icon-list': 'Icon list',
	icon: 'Icon',
	'icon-box': 'Icon box',
	'image-box': 'Image box',
	video: 'Video',
	form: 'Form',
	'nav-menu': 'Menu',
	'loop-grid': 'Loop grid',
	'loop-carousel': 'Carousel',
	'image-carousel': 'Carousel',
	testimonial: 'Testimonial',
	tabs: 'Tabs',
	accordion: 'Accordion',
	toggle: 'Toggle',
	divider: 'Divider',
	spacer: 'Spacer',
	'google_maps': 'Map',
	'theme-site-logo': 'Logo',
	'theme-post-title': 'Title',
	'woocommerce-product-title': 'Product title',
	'woocommerce-product-price': 'Price',
	'woocommerce-product-add-to-cart': 'Add to basket',
	'woocommerce-product-images': 'Product images',
	shortcode: 'Shortcode',
	html: 'HTML',
};

const TAG_LABELS: Record< string, string > = {
	h1: 'Heading',
	h2: 'Heading',
	h3: 'Heading',
	h4: 'Heading',
	h5: 'Heading',
	h6: 'Heading',
	p: 'Paragraph',
	img: 'Image',
	picture: 'Image',
	a: 'Link',
	button: 'Button',
	input: 'Field',
	textarea: 'Field',
	select: 'Dropdown',
	label: 'Label',
	ul: 'List',
	ol: 'List',
	li: 'List item',
	nav: 'Navigation',
	header: 'Header',
	footer: 'Footer',
	section: 'Section',
	article: 'Article',
	aside: 'Sidebar',
	form: 'Form',
	video: 'Video',
	iframe: 'Embedded content',
	svg: 'Icon',
	table: 'Table',
	figure: 'Figure',
	blockquote: 'Quote',
	span: 'Text',
	strong: 'Text',
	em: 'Text',
	div: 'Block',
	main: 'Main content',
};

/** A short human name for an element ("Heading", "Button", "Image"). */
export function describe( el: Element ): string {
	const widget = el.getAttribute( 'data-widget_type' );
	if ( widget ) {
		const key = widget.split( '.' )[ 0 ];
		if ( WIDGET_LABELS[ key ] ) {
			return WIDGET_LABELS[ key ];
		}
	}
	const type = el.getAttribute( 'data-element_type' );
	if ( type === 'container' || type === 'section' ) {
		return 'Section';
	}
	if ( type === 'column' ) {
		return 'Column';
	}
	const tag = el.tagName.toLowerCase();
	return TAG_LABELS[ tag ] || tag.toUpperCase();
}
