/**
 * Client for the mna-feedback/v1 REST API.
 *
 * - Requests stay same-origin (the root is a path) and send cookies.
 * - Team members send the wp_rest nonce; guests send their session CSRF token.
 * - PATCH/DELETE travel as POST with X-HTTP-Method-Override, which survives
 *   hosts and firewalls that block those verbs.
 * - GETs carry a throwaway parameter so a misconfigured cache can never serve
 *   one person's private response to someone else.
 */

import type { ApiErrorShape } from './types';
import { storageKey } from './util/storage';

type Method = 'GET' | 'POST' | 'PATCH' | 'DELETE';
type Params = Record< string, string | number | boolean | null | undefined >;

export interface RequestOptions {
	params?: Params;
	body?: unknown;
	form?: FormData;
	signal?: AbortSignal;
}

export class ApiError extends Error implements ApiErrorShape {
	code: string;
	status: number;
	data: Record< string, unknown >;

	constructor( code: string, message: string, status: number, data: Record< string, unknown > = {} ) {
		super( message );
		this.code = code;
		this.status = status;
		this.data = data;
	}

	get isNetwork(): boolean {
		return this.status === 0;
	}
}

const NONCE_KEY = storageKey( 'nonce' );

export class Api {
	private root: string;
	nonce: string | null = null;
	csrf: string | null = null;

	constructor( root: string ) {
		this.root = root.endsWith( '/' ) ? root : root + '/';
		try {
			this.nonce = window.sessionStorage.getItem( NONCE_KEY );
		} catch {
			this.nonce = null;
		}
	}

	setNonce( nonce: string | null ): void {
		this.nonce = nonce;
		try {
			if ( nonce ) {
				window.sessionStorage.setItem( NONCE_KEY, nonce );
			} else {
				window.sessionStorage.removeItem( NONCE_KEY );
			}
		} catch {
			/* Storage unavailable: keep the nonce in memory only. */
		}
	}

	url( route: string, params?: Params ): string {
		let url = this.root + route.replace( /^\//, '' );
		const query = new URLSearchParams();
		Object.entries( params || {} ).forEach( ( [ key, value ] ) => {
			if ( value !== undefined && value !== null && value !== '' ) {
				query.append( key, String( value ) );
			}
		} );
		const qs = query.toString();
		if ( qs ) {
			url += ( url.includes( '?' ) ? '&' : '?' ) + qs;
		}
		return url;
	}

	/** Turns an absolute API URL from the server into a same-origin path. */
	relative( absolute: string ): string {
		try {
			const parsed = new URL( absolute, window.location.href );
			return parsed.pathname + parsed.search;
		} catch {
			return absolute;
		}
	}

	private headers( method: Method ): Record< string, string > {
		const headers: Record< string, string > = { Accept: 'application/json' };
		if ( this.nonce ) {
			headers[ 'X-WP-Nonce' ] = this.nonce;
		}
		if ( method !== 'GET' ) {
			headers[ 'X-MNAFB-Client' ] = '1';
			if ( this.csrf ) {
				headers[ 'X-MNAFB-Token' ] = this.csrf;
			}
		}
		return headers;
	}

	async request< T >( method: Method, route: string, options: RequestOptions = {} ): Promise< T > {
		const params: Params = { ...( options.params || {} ) };
		if ( method === 'GET' ) {
			params._mnafb = Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 7 );
		}
		const headers = this.headers( method );
		let body: BodyInit | undefined;
		let sendMethod: string = method;
		if ( method === 'PATCH' || method === 'DELETE' ) {
			headers[ 'X-HTTP-Method-Override' ] = method;
			sendMethod = 'POST';
		}
		if ( method !== 'GET' ) {
			if ( options.form ) {
				body = options.form;
			} else if ( options.body !== undefined ) {
				headers[ 'Content-Type' ] = 'application/json';
				body = JSON.stringify( options.body );
			}
		}

		let response: Response;
		try {
			response = await fetch( this.url( route, params ), {
				method: sendMethod,
				headers,
				body,
				credentials: 'same-origin',
				cache: 'no-store',
				signal: options.signal,
			} );
		} catch ( error ) {
			if ( ( error as Error )?.name === 'AbortError' ) {
				throw error;
			}
			throw new ApiError( 'network', 'Could not reach the site. Check your connection — nothing you typed has been lost.', 0 );
		}

		const refreshed = response.headers.get( 'X-WP-Nonce' );
		if ( refreshed && this.nonce && refreshed !== this.nonce ) {
			this.setNonce( refreshed );
		}

		const text = await response.text();
		let json: unknown = null;
		if ( text ) {
			try {
				json = JSON.parse( text );
			} catch {
				json = null;
			}
		}
		if ( ! response.ok ) {
			const shape = ( json && typeof json === 'object' ? json : {} ) as { code?: string; message?: string; data?: Record< string, unknown > };
			throw new ApiError(
				shape.code || `http_${ response.status }`,
				shape.message || `The site returned an error (${ response.status }). Please try again.`,
				response.status,
				shape.data && typeof shape.data === 'object' ? shape.data : {}
			);
		}
		return json as T;
	}

	get< T >( route: string, params?: Params, signal?: AbortSignal ): Promise< T > {
		return this.request< T >( 'GET', route, { params, signal } );
	}

	post< T >( route: string, body?: unknown ): Promise< T > {
		return this.request< T >( 'POST', route, { body: body ?? {} } );
	}

	patch< T >( route: string, body: unknown ): Promise< T > {
		return this.request< T >( 'PATCH', route, { body } );
	}

	del< T >( route: string, params?: Params ): Promise< T > {
		return this.request< T >( 'DELETE', route, { params, body: {} } );
	}

	upload< T >( route: string, form: FormData ): Promise< T > {
		return this.request< T >( 'POST', route, { form } );
	}

	/** Fetches a private image through the API and returns an object URL. */
	async blobUrl( absolute: string, signal?: AbortSignal ): Promise< string > {
		const headers: Record< string, string > = {};
		if ( this.nonce ) {
			headers[ 'X-WP-Nonce' ] = this.nonce;
		}
		const response = await fetch( this.relative( absolute ), { headers, credentials: 'same-origin', cache: 'no-store', signal } );
		if ( ! response.ok ) {
			throw new ApiError( 'attachment', 'The screenshot could not be loaded.', response.status );
		}
		return URL.createObjectURL( await response.blob() );
	}
}
