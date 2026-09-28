/** Accent colour helpers: derives hover, tint and readable text colours. */

function parse( hex: string ): [ number, number, number ] | null {
	const match = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec( hex.trim() );
	if ( ! match ) {
		return null;
	}
	let value = match[ 1 ];
	if ( value.length === 3 ) {
		value = value
			.split( '' )
			.map( ( c ) => c + c )
			.join( '' );
	}
	const n = parseInt( value, 16 );
	return [ ( n >> 16 ) & 255, ( n >> 8 ) & 255, n & 255 ];
}

function toHex( [ r, g, b ]: [ number, number, number ] ): string {
	return '#' + [ r, g, b ].map( ( v ) => Math.round( Math.max( 0, Math.min( 255, v ) ) ).toString( 16 ).padStart( 2, '0' ) ).join( '' );
}

function luminance( [ r, g, b ]: [ number, number, number ] ): number {
	const channel = ( v: number ) => {
		const s = v / 255;
		return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
	};
	return 0.2126 * channel( r ) + 0.7152 * channel( g ) + 0.0722 * channel( b );
}

export function accentVars( accent: string | undefined ): Record< string, string > {
	const rgb = parse( accent || '' ) || [ 79, 70, 229 ];
	const lum = luminance( rgb );
	const ink = lum > 0.45 ? '#111827' : '#ffffff';
	const strong = toHex( rgb.map( ( v ) => v * 0.82 ) as [ number, number, number ] );
	const soft = `rgba(${ rgb[ 0 ] }, ${ rgb[ 1 ] }, ${ rgb[ 2 ] }, 0.1)`;
	const ring = `rgba(${ rgb[ 0 ] }, ${ rgb[ 1 ] }, ${ rgb[ 2 ] }, 0.35)`;
	// Text in the accent colour on white must stay readable: darken very light accents.
	const text = lum > 0.35 ? toHex( rgb.map( ( v ) => v * 0.55 ) as [ number, number, number ] ) : toHex( rgb );
	return {
		'--mnafb-accent': toHex( rgb ),
		'--mnafb-accent-strong': strong,
		'--mnafb-accent-soft': soft,
		'--mnafb-accent-ring': ring,
		'--mnafb-accent-ink': ink,
		'--mnafb-accent-text': text,
	};
}
