/** Screenshot validation and client-side downsizing to fit the upload limit. */

export const ACCEPTED_TYPES = [ 'image/png', 'image/jpeg', 'image/webp' ];

function loadImage( file: File ): Promise< { source: CanvasImageSource; width: number; height: number; release: () => void } > {
	if ( typeof createImageBitmap === 'function' ) {
		return createImageBitmap( file ).then( ( bitmap ) => ( {
			source: bitmap,
			width: bitmap.width,
			height: bitmap.height,
			release: () => bitmap.close(),
		} ) );
	}
	return new Promise( ( resolve, reject ) => {
		const url = URL.createObjectURL( file );
		const img = new Image();
		img.onload = () => resolve( { source: img, width: img.naturalWidth, height: img.naturalHeight, release: () => URL.revokeObjectURL( url ) } );
		img.onerror = () => {
			URL.revokeObjectURL( url );
			reject( new Error( 'That file could not be read as an image.' ) );
		};
		img.src = url;
	} );
}

function toBlob( canvas: HTMLCanvasElement, type: string, quality: number ): Promise< Blob | null > {
	return new Promise( ( resolve ) => canvas.toBlob( resolve, type, quality ) );
}

/**
 * Returns an image ready to upload: the original when it is an accepted type
 * within the limit, otherwise a re-encoded, scaled-down copy.
 */
export async function prepareImage( file: File, maxBytes: number ): Promise< File > {
	if ( ! ACCEPTED_TYPES.includes( file.type ) ) {
		throw new Error( 'Screenshots must be PNG, JPEG or WebP images.' );
	}
	if ( file.size <= maxBytes ) {
		return file;
	}
	const image = await loadImage( file );
	try {
		let scale = Math.min( 1, 2560 / Math.max( image.width, image.height ) );
		for ( let attempt = 0; attempt < 6; attempt++ ) {
			const canvas = document.createElement( 'canvas' );
			canvas.width = Math.max( 1, Math.round( image.width * scale ) );
			canvas.height = Math.max( 1, Math.round( image.height * scale ) );
			const ctx = canvas.getContext( '2d' );
			if ( ! ctx ) {
				break;
			}
			ctx.drawImage( image.source, 0, 0, canvas.width, canvas.height );
			let blob = await toBlob( canvas, 'image/webp', 0.86 );
			if ( ! blob || blob.type !== 'image/webp' ) {
				blob = await toBlob( canvas, 'image/jpeg', 0.86 );
			}
			if ( blob && blob.size <= maxBytes ) {
				const ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
				return new File( [ blob ], ( file.name || 'screenshot' ).replace( /\.\w+$/, '' ) + '.' + ext, { type: blob.type } );
			}
			scale *= 0.75;
		}
	} finally {
		image.release();
	}
	throw new Error( 'That image is too large. Try a smaller screenshot (up to 2 MB).' );
}

/** Image files from a paste or drop event. */
export function imagesFrom( data: DataTransfer | null ): File[] {
	if ( ! data ) {
		return [];
	}
	const files: File[] = [];
	if ( data.items && data.items.length ) {
		for ( const item of Array.from( data.items ) ) {
			if ( item.kind === 'file' && item.type.startsWith( 'image/' ) ) {
				const file = item.getAsFile();
				if ( file ) {
					files.push( file );
				}
			}
		}
	} else if ( data.files ) {
		for ( const file of Array.from( data.files ) ) {
			if ( file.type.startsWith( 'image/' ) ) {
				files.push( file );
			}
		}
	}
	return files;
}
