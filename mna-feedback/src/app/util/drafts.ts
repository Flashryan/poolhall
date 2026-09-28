/**
 * Unsent text survives reloads and network failures: drafts are kept in
 * localStorage until they are sent successfully or discarded.
 */

const PREFIX = 'mnafb:draft:';
const MAX_AGE = 14 * 24 * 60 * 60 * 1000;

interface Stored< T > {
	t: number;
	v: T;
}

function storage(): Storage | null {
	try {
		return window.localStorage;
	} catch {
		return null;
	}
}

export function saveDraft< T >( key: string, value: T ): void {
	const store = storage();
	if ( ! store ) {
		return;
	}
	try {
		store.setItem( PREFIX + key, JSON.stringify( { t: Date.now(), v: value } as Stored< T > ) );
	} catch {
		/* Quota or privacy mode: drafts stay in memory only. */
	}
}

export function loadDraft< T >( key: string ): T | null {
	const store = storage();
	if ( ! store ) {
		return null;
	}
	try {
		const raw = store.getItem( PREFIX + key );
		if ( ! raw ) {
			return null;
		}
		const parsed = JSON.parse( raw ) as Stored< T >;
		if ( ! parsed || Date.now() - parsed.t > MAX_AGE ) {
			store.removeItem( PREFIX + key );
			return null;
		}
		return parsed.v;
	} catch {
		return null;
	}
}

export function clearDraft( key: string ): void {
	const store = storage();
	try {
		store?.removeItem( PREFIX + key );
	} catch {
		/* ignore */
	}
}

/** Drafts whose key starts with the given prefix (for "restore unsent comment"). */
export function listDrafts< T >( prefix: string ): Array< { key: string; value: T; time: number } > {
	const store = storage();
	if ( ! store ) {
		return [];
	}
	const out: Array< { key: string; value: T; time: number } > = [];
	try {
		for ( let i = 0; i < store.length; i++ ) {
			const full = store.key( i );
			if ( ! full || ! full.startsWith( PREFIX + prefix ) ) {
				continue;
			}
			const parsed = JSON.parse( store.getItem( full ) || 'null' ) as Stored< T > | null;
			if ( parsed && Date.now() - parsed.t <= MAX_AGE ) {
				out.push( { key: full.slice( PREFIX.length ), value: parsed.v, time: parsed.t } );
			}
		}
	} catch {
		return out;
	}
	return out.sort( ( a, b ) => b.time - a.time );
}
