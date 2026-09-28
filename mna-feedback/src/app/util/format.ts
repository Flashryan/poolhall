import type { ActivityEntry, Priority, Status } from '../types';

export const STATUS_LABEL: Record< Status, string > = {
	open: 'Open',
	in_progress: 'In progress',
	done: 'Done',
};

export const PRIORITY_LABEL: Record< Priority, string > = {
	low: 'Low',
	normal: 'Normal',
	high: 'High',
	urgent: 'Urgent',
};

export function timeAgo( iso: string | null | undefined, now: number = Date.now() ): string {
	if ( ! iso ) {
		return '';
	}
	const then = Date.parse( iso );
	if ( Number.isNaN( then ) ) {
		return '';
	}
	const seconds = Math.max( 0, Math.round( ( now - then ) / 1000 ) );
	if ( seconds < 45 ) {
		return 'just now';
	}
	const minutes = Math.round( seconds / 60 );
	if ( minutes < 60 ) {
		return `${ minutes }m ago`;
	}
	const hours = Math.round( minutes / 60 );
	if ( hours < 24 ) {
		return `${ hours }h ago`;
	}
	const days = Math.round( hours / 24 );
	if ( days < 7 ) {
		return `${ days }d ago`;
	}
	return new Date( then ).toLocaleDateString( undefined, { day: 'numeric', month: 'short', year: new Date( then ).getFullYear() === new Date( now ).getFullYear() ? undefined : 'numeric' } );
}

export function fullDate( iso: string | null | undefined ): string {
	if ( ! iso ) {
		return '';
	}
	const date = new Date( iso );
	return Number.isNaN( date.getTime() ) ? '' : date.toLocaleString( undefined, { dateStyle: 'medium', timeStyle: 'short' } );
}

export function bytes( size: number ): string {
	if ( size < 1024 ) {
		return `${ size } B`;
	}
	if ( size < 1024 * 1024 ) {
		return `${ Math.round( size / 1024 ) } KB`;
	}
	return `${ ( size / 1024 / 1024 ).toFixed( 1 ) } MB`;
}

export function pagePath( url: string ): string {
	try {
		const parsed = new URL( url );
		return decodeURIComponent( parsed.pathname + parsed.search ) || '/';
	} catch {
		return url;
	}
}

/** Human description of a history entry. */
export function describeActivity( entry: ActivityEntry ): string {
	const data = entry.data || {};
	const from = typeof data.from === 'string' ? data.from : '';
	const to = typeof data.to === 'string' ? data.to : '';
	switch ( entry.action ) {
		case 'created':
			return data.pin === 'page' ? 'left this page comment' : 'pinned this comment';
		case 'edited': {
			const fields = Array.isArray( data.fields ) ? ( data.fields as string[] ) : [];
			const names = fields.map( ( f ) => ( f === 'body' ? 'description' : f ) );
			return names.length ? `edited the ${ names.join( ' and ' ) }` : 'edited this';
		}
		case 'status':
			return `moved this from ${ STATUS_LABEL[ from as Status ] || from } to ${ STATUS_LABEL[ to as Status ] || to }`;
		case 'priority':
			return `changed priority from ${ PRIORITY_LABEL[ from as Priority ] || from } to ${ PRIORITY_LABEL[ to as Priority ] || to }`;
		case 'assigned': {
			const toName = data.to_person?.name;
			const fromName = data.from_person?.name;
			if ( toName && fromName ) {
				return `reassigned this from ${ fromName } to ${ toName }`;
			}
			return toName ? `assigned this to ${ toName }` : 'removed the assignee';
		}
		case 'replied':
			return 'replied';
		case 'note_added':
			return 'added an implementation note';
		case 'reply_edited':
			return 'edited a reply';
		case 'reply_trashed':
			return 'deleted a reply';
		case 'reply_restored':
			return 'restored a reply';
		case 'attachment_added':
			return 'added a screenshot';
		case 'attachment_removed':
			return 'removed a screenshot';
		case 'trashed':
			return 'moved this to Trash';
		case 'restored':
			return 'restored this from Trash';
		default:
			return entry.action.replace( /_/g, ' ' );
	}
}

export function plural( n: number, one: string, many: string ): string {
	return `${ n } ${ n === 1 ? one : many }`;
}
