/**
 * One feedback item: description, where it is pinned, screenshots, the
 * discussion (replies and implementation notes) and its full history.
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import { getResolution, useResolutions } from '../anchor/registry';
import {
	addReply,
	deleteAttachment,
	deleteReply,
	editReply,
	locate,
	patchItem,
	pendingFiles,
	purgeItem,
	purgeReply,
	restoreItem,
	restoreReply,
	trashItem,
	uploadFiles,
} from '../actions';
import { allNumbers, getState, setState, toast, useStore, type PendingFile } from '../store';
import { PRIORITIES, STATUSES, type Attachment, type Item, type Reply } from '../types';
import { loadDraft, saveDraft, clearDraft } from '../util/drafts';
import { imagesFrom } from '../util/images';
import { PRIORITY_LABEL, STATUS_LABEL, describeActivity, fullDate, pagePath, timeAgo } from '../util/format';
import { AuthImg, AutoTextarea, Avatar, IconButton, Menu, PriorityChip, Spinner, StatusChip, Text, copyText } from './ui';
import { Icon } from './Icons';

function openLightbox( src: string, alt: string ): void {
	setState( { dialog: { type: 'lightbox', src, alt } } );
}

function confirm( title: string, text: string, label: string, run: () => void, danger = true ): void {
	setState( { dialog: { type: 'confirm', title, text, confirm: label, run, danger } } );
}

export function itemLink( item: Item ): string {
	try {
		const url = new URL( item.page.url );
		url.hash = `mnafb-item-${ item.id }`;
		return url.toString();
	} catch {
		return item.page.url;
	}
}

function Where( { item }: { item: Item } ) {
	const pageKey = useStore( ( s ) => s.pageKey );
	useResolutions();
	const here = item.page.key === pageKey;
	const anchor = item.pin.anchor;

	if ( ! here ) {
		return (
			<div className="mnafb-where">
				<Icon name="page" size={ 16 } />
				<div className="mnafb-where__text">
					<span>On another page</span>
					<strong title={ item.page.url }>{ item.page.title || pagePath( item.page.url ) }</strong>
				</div>
				<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => locate( item.id ) }>
					<Icon name="external" size={ 14 } />
					<span>Go to page</span>
				</button>
			</div>
		);
	}
	if ( item.pin.type === 'page' ) {
		return (
			<div className="mnafb-where">
				<Icon name="page" size={ 16 } />
				<div className="mnafb-where__text">
					<span>About the whole page</span>
				</div>
			</div>
		);
	}

	const resolution = getResolution( item.id );
	const state = resolution?.state;
	const left = item.viewport.w ? `${ item.viewport.w }×${ item.viewport.h }` : '';
	if ( state === 'unavailable' ) {
		return (
			<div className="mnafb-where mnafb-where--warn" role="note">
				<Icon name="warning" size={ 16 } />
				<div className="mnafb-where__text">
					<strong>Original element unavailable</strong>
					<span>
						This part of the page has changed or been removed, so the pin is not shown.
						{ anchor?.label ? ` It was left on a ${ anchor.label.toLowerCase() }` : '' }
						{ anchor?.text ? ` reading “${ anchor.text.slice( 0, 80 ) }${ anchor.text.length > 80 ? '…' : '' }”` : '' }
						{ left ? `, viewed at ${ left }.` : '.' }
					</span>
				</div>
			</div>
		);
	}
	if ( state === 'hidden' ) {
		return (
			<div className="mnafb-where mnafb-where--muted" role="note">
				<Icon name="eyeOff" size={ 16 } />
				<div className="mnafb-where__text">
					<strong>Not visible right now</strong>
					<span>The { anchor?.label?.toLowerCase() || 'element' } is hidden at this screen size or inside a closed menu, tab or slider{ left ? ` (left at ${ left })` : '' }.</span>
				</div>
			</div>
		);
	}
	return (
		<div className="mnafb-where">
			<Icon name="crosshair" size={ 16 } />
			<div className="mnafb-where__text">
				<span>Pinned to</span>
				<strong>{ anchor?.label || 'an element' }</strong>
			</div>
			<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => locate( item.id ) }>
				<Icon name="locate" size={ 14 } />
				<span>Show on page</span>
			</button>
		</div>
	);
}

function Attachments( { item, list }: { item: Item; list: Attachment[] } ) {
	const me = useStore( ( s ) => s.session?.me );
	if ( ! list.length ) {
		return null;
	}
	return (
		<div className="mnafb-shots">
			{ list.map( ( attachment ) => (
				<div key={ attachment.id } className="mnafb-shot">
					<AuthImg src={ attachment.thumb_url } alt={ `Screenshot ${ attachment.width }×${ attachment.height }` } onOpen={ () => openLightbox( attachment.url, 'Screenshot' ) } />
					{ me && ( attachment.uploader_id === me.id || me.caps.manage ) && ! item.trashed && (
						<button
							type="button"
							className="mnafb-shot__remove"
							aria-label="Remove screenshot"
							title="Remove screenshot"
							onClick={ () => confirm( 'Remove screenshot?', 'The screenshot will be deleted.', 'Remove', () => void deleteAttachment( attachment, item.id ) ) }
						>
							<Icon name="close" size={ 12 } />
						</button>
					) }
				</div>
			) ) }
		</div>
	);
}

function ReplyRow( { reply, item }: { reply: Reply; item: Item } ) {
	const [ editing, setEditing ] = useState( false );
	const [ text, setText ] = useState( reply.body );
	const [ error, setError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const draftKey = `reply-edit:${ reply.id }`;

	const start = () => {
		setText( loadDraft< string >( draftKey ) ?? reply.body );
		setError( '' );
		setEditing( true );
	};
	const save = async () => {
		if ( ! text.trim() ) {
			setError( 'A reply cannot be empty. Delete it instead.' );
			return;
		}
		setSaving( true );
		const problem = await editReply( reply, text );
		setSaving( false );
		if ( problem ) {
			setError( problem );
		} else {
			setEditing( false );
		}
	};

	const team = reply.author?.type === 'wp_user';
	return (
		<li className={ `mnafb-reply ${ reply.kind === 'note' ? 'is-note' : '' } ${ reply.trashed ? 'is-trashed' : '' }` }>
			<Avatar person={ reply.author } size={ 28 } />
			<div className="mnafb-reply__main">
				<div className="mnafb-reply__head">
					<strong>{ reply.author?.name || 'Someone' }</strong>
					{ team && <span className="mnafb-badge">Team</span> }
					{ reply.kind === 'note' && (
						<span className="mnafb-badge mnafb-badge--note">
							<Icon name="wrench" size={ 11 } /> Implementation note
						</span>
					) }
					<time dateTime={ reply.created_at } title={ fullDate( reply.created_at ) }>
						{ timeAgo( reply.created_at ) }
					</time>
					{ reply.edited_at && <span className="mnafb-muted" title={ fullDate( reply.edited_at ) }>· edited</span> }
					{ reply.trashed && <span className="mnafb-badge mnafb-badge--warn">In Trash</span> }
				</div>
				{ editing ? (
					<div className="mnafb-edit">
						<AutoTextarea
							className="mnafb-input"
							value={ text }
							autoFocus
							aria-label="Edit reply"
							onChange={ ( e ) => {
								setText( e.target.value );
								saveDraft( draftKey, e.target.value );
							} }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
									void save();
								}
								if ( e.key === 'Escape' ) {
									e.stopPropagation();
									setEditing( false );
								}
							} }
						/>
						{ error && (
							<p className="mnafb-error" role="alert">
								{ error }
							</p>
						) }
						<div className="mnafb-edit__actions">
							<button
								type="button"
								className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm"
								onClick={ () => {
									clearDraft( draftKey );
									setEditing( false );
								} }
							>
								Cancel
							</button>
							<button type="button" className="mnafb-btn mnafb-btn--primary mnafb-btn--sm" disabled={ saving } onClick={ () => void save() }>
								{ saving ? 'Saving…' : 'Save' }
							</button>
						</div>
					</div>
				) : (
					<Text text={ reply.body } />
				) }
				<Attachments item={ item } list={ reply.attachments } />
			</div>
			<Menu
				label="Reply actions"
				items={ [
					! reply.trashed && reply.can.edit && { label: 'Edit', icon: 'edit', onSelect: start },
					! reply.trashed &&
						reply.can.delete && {
							label: 'Delete',
							icon: 'trash',
							danger: true,
							onSelect: () => confirm( 'Delete reply?', 'The reply is moved to Trash. A manager can restore it.', 'Delete', () => void deleteReply( reply ) ),
						},
					reply.trashed && reply.can.restore && { label: 'Restore', icon: 'restore', onSelect: () => void restoreReply( reply ) },
					reply.trashed &&
						reply.can.purge && {
							label: 'Delete permanently',
							icon: 'trash',
							danger: true,
							onSelect: () => confirm( 'Delete permanently?', 'This reply will be removed for good. This cannot be undone.', 'Delete permanently', () => void purgeReply( reply ) ),
						},
				] }
			/>
		</li>
	);
}

function ReplyBox( { item }: { item: Item } ) {
	const draftKey = `reply:${ item.id }`;
	const [ text, setText ] = useState( () => loadDraft< string >( draftKey ) || '' );
	const [ note, setNote ] = useState( false );
	const [ files, setFiles ] = useState< PendingFile[] >( [] );
	const [ sending, setSending ] = useState( false );
	const [ error, setError ] = useState( '' );
	const fileRef = useRef< HTMLInputElement >( null );

	useEffect( () => {
		setText( loadDraft< string >( draftKey ) || '' );
		setError( '' );
		setNote( false );
	}, [ draftKey ] );

	const add = async ( list: File[] ) => {
		const prepared = await pendingFiles( list.slice( 0, 4 ) );
		setFiles( ( current ) => [ ...current, ...prepared ].slice( 0, 4 ) );
	};

	const send = async () => {
		if ( ! text.trim() || sending ) {
			return;
		}
		setSending( true );
		setError( '' );
		const problem = await addReply( item.id, text.trim(), note ? 'note' : 'reply', files.map( ( f ) => f.file ) );
		setSending( false );
		if ( problem ) {
			setError( problem );
			return;
		}
		files.forEach( ( f ) => URL.revokeObjectURL( f.preview ) );
		setFiles( [] );
		setText( '' );
		setNote( false );
	};

	return (
		<form
			className="mnafb-replybox"
			onSubmit={ ( e ) => {
				e.preventDefault();
				void send();
			} }
			onPaste={ ( e ) => {
				const pasted = imagesFrom( e.clipboardData );
				if ( pasted.length && item.can.attach ) {
					e.preventDefault();
					void add( pasted );
				}
			} }
		>
			<AutoTextarea
				className="mnafb-input"
				value={ text }
				placeholder={ note ? 'What was changed, and where?' : 'Reply…' }
				aria-label={ note ? 'Implementation note' : 'Reply' }
				minRows={ 2 }
				onChange={ ( e ) => {
					setText( e.target.value );
					if ( e.target.value ) {
						saveDraft( draftKey, e.target.value );
					} else {
						clearDraft( draftKey );
					}
				} }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
						e.preventDefault();
						void send();
					}
				} }
			/>
			{ files.length > 0 && (
				<div className="mnafb-attach-row">
					{ files.map( ( f ) => (
						<span key={ f.id } className="mnafb-thumb">
							<img src={ f.preview } alt="Screenshot to attach" />
							<button
								type="button"
								className="mnafb-thumb__remove"
								aria-label="Remove screenshot"
								onClick={ () => {
									URL.revokeObjectURL( f.preview );
									setFiles( ( current ) => current.filter( ( x ) => x.id !== f.id ) );
								} }
							>
								<Icon name="close" size={ 12 } />
							</button>
						</span>
					) ) }
				</div>
			) }
			{ error && (
				<p className="mnafb-error" role="alert">
					<Icon name="warning" size={ 16 } />
					<span>{ error }</span>
				</p>
			) }
			<div className="mnafb-replybox__row">
				{ item.can.attach && (
					<>
						<IconButton icon="image" label="Attach a screenshot" onClick={ () => fileRef.current?.click() } />
						<input
							ref={ fileRef }
							type="file"
							hidden
							multiple
							accept="image/png,image/jpeg,image/webp"
							onChange={ ( e ) => {
								void add( Array.from( e.target.files || [] ) );
								e.target.value = '';
							} }
						/>
					</>
				) }
				{ item.can.note && (
					<label className="mnafb-check">
						<input type="checkbox" checked={ note } onChange={ ( e ) => setNote( e.target.checked ) } />
						<span>Implementation note</span>
					</label>
				) }
				<span className="mnafb-spacer" />
				<button type="submit" className="mnafb-btn mnafb-btn--primary mnafb-btn--sm" disabled={ ! text.trim() || sending }>
					<Icon name="send" size={ 14 } />
					<span>{ sending ? 'Sending…' : error ? 'Try again' : 'Send' }</span>
				</button>
			</div>
		</form>
	);
}

function EditForm( { item, onDone }: { item: Item; onDone: () => void } ) {
	const draftKey = `edit:${ item.id }`;
	const base = useRef( { title: item.title, body: item.body } );
	const saved = loadDraft< { title: string; body: string } >( draftKey );
	const [ title, setTitle ] = useState( saved?.title ?? item.title );
	const [ body, setBody ] = useState( saved?.body ?? item.body );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	const save = async () => {
		if ( ! title.trim() ) {
			setError( 'The title cannot be empty.' );
			return;
		}
		setSaving( true );
		const changes: { title?: string; body?: string } = {};
		const expected: Record< string, string > = {};
		if ( title.trim() !== base.current.title ) {
			changes.title = title.trim();
			expected.title = base.current.title;
		}
		if ( body.trim() !== base.current.body ) {
			changes.body = body.trim();
			expected.body = base.current.body;
		}
		if ( ! Object.keys( changes ).length ) {
			clearDraft( draftKey );
			onDone();
			return;
		}
		const result = await patchItem( item.id, changes, expected );
		setSaving( false );
		if ( result ) {
			clearDraft( draftKey );
			onDone();
		} else if ( getState().dialog?.type === 'conflict' ) {
			// The conflict dialog shows both versions. The draft stays saved, so
			// choosing Edit again brings the text back if the dialog is dismissed.
			onDone();
		} else {
			setError( 'Your changes were not saved. They are still here — try again.' );
		}
	};

	return (
		<div
			className="mnafb-edit"
			onKeyDown={ ( e ) => {
				if ( e.key === 'Escape' ) {
					e.stopPropagation();
					onDone();
				}
				if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
					void save();
				}
			} }
		>
			<input
				className="mnafb-input mnafb-input--title"
				value={ title }
				maxLength={ 200 }
				aria-label="Title"
				autoFocus
				onChange={ ( e ) => {
					setTitle( e.target.value );
					saveDraft( draftKey, { title: e.target.value, body } );
				} }
			/>
			<AutoTextarea
				className="mnafb-input"
				value={ body }
				minRows={ 3 }
				aria-label="Description"
				placeholder="Add details (optional)"
				onChange={ ( e ) => {
					setBody( e.target.value );
					saveDraft( draftKey, { title, body: e.target.value } );
				} }
			/>
			{ error && (
				<p className="mnafb-error" role="alert">
					{ error }
				</p>
			) }
			<div className="mnafb-edit__actions">
				<button
					type="button"
					className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm"
					onClick={ () => {
						clearDraft( draftKey );
						onDone();
					} }
				>
					Cancel
				</button>
				<button type="button" className="mnafb-btn mnafb-btn--primary mnafb-btn--sm" disabled={ saving } onClick={ () => void save() }>
					{ saving ? 'Saving…' : 'Save changes' }
				</button>
			</div>
		</div>
	);
}

export function Detail( { id, variant, onBack }: { id: number; variant: 'panel' | 'drawer'; onBack: () => void } ) {
	const item = useStore( ( s ) => s.items[ id ] ) as Item | undefined;
	const detail = useStore( ( s ) => s.details[ id ] );
	const trash = useStore( ( s ) => s.trash );
	const items = useStore( ( s ) => s.items );
	const people = useStore( ( s ) => s.people );
	const assignable = useStore( ( s ) => s.assignable );
	const me = useStore( ( s ) => s.session?.me );
	const [ tab, setTab ] = useState< 'discussion' | 'history' >( 'discussion' );
	const [ editing, setEditing ] = useState( () => !! loadDraft( `edit:${ id }` ) );
	const shotRef = useRef< HTMLInputElement >( null );
	const numbers = useMemo( () => allNumbers( items ), [ items ] );
	const heading = useRef< HTMLHeadingElement >( null );

	useEffect( () => {
		setTab( 'discussion' );
		setEditing( !! loadDraft( `edit:${ id }` ) );
		heading.current?.focus( { preventScroll: true } );
	}, [ id ] );

	const current = item || trash?.find( ( t ) => t.id === id );
	if ( ! current ) {
		return (
			<div className="mnafb-detail">
				<div className="mnafb-detail__bar">
					<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ onBack }>
						<Icon name="chevronLeft" size={ 16 } />
						<span>Back</span>
					</button>
				</div>
				<p className="mnafb-empty">This feedback no longer exists.</p>
			</div>
		);
	}

	const number = numbers.get( current.id ) || 0;
	const statusButtons = current.can.statuses.length > 0 && me?.caps.implement;
	const canReopen = ! me?.caps.implement && current.can.statuses.includes( 'open' );
	const assignOptions = people.filter( ( p ) => assignable.includes( p.id ) );
	const replies = detail?.replies || [];
	const activity = detail?.activity || [];

	const setStatus = ( status: typeof STATUSES[ number ] ) => {
		if ( status !== current.status ) {
			void patchItem( current.id, { status }, { status: current.status } );
		}
	};

	return (
		<div className={ `mnafb-detail mnafb-detail--${ variant }` }>
			<div className="mnafb-detail__bar">
				<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ onBack }>
					<Icon name={ variant === 'drawer' ? 'close' : 'chevronLeft' } size={ 16 } />
					<span>{ variant === 'drawer' ? 'Close' : 'All comments' }</span>
				</button>
				<span className="mnafb-spacer" />
				<Menu
					label="Comment actions"
					items={ [
						! current.trashed && current.can.edit && { label: 'Edit', icon: 'edit', onSelect: () => setEditing( true ) },
						{
							label: 'Copy link',
							icon: 'link',
							onSelect: () =>
								void copyText( itemLink( current ) ).then( ( ok ) => toast( ok ? 'Link copied. Anyone with review access can open it.' : itemLink( current ), ok ? 'success' : 'info' ) ),
						},
						current.page.key !== getState().pageKey && { label: 'Go to page', icon: 'external', onSelect: () => locate( current.id ) },
						! current.trashed &&
							current.can.delete && {
								label: 'Delete',
								icon: 'trash',
								danger: true,
								onSelect: () =>
									confirm(
										'Delete this comment?',
										me?.caps.manage ? 'It moves to Trash, where you can restore it.' : 'It is removed from the board. A manager can restore it if needed.',
										'Delete',
										() => {
											void trashItem( current.id );
											onBack();
										}
									),
							},
						current.can.restore && { label: 'Restore from Trash', icon: 'restore', onSelect: () => void restoreItem( current.id ) },
						current.can.purge && {
							label: 'Delete permanently',
							icon: 'trash',
							danger: true,
							onSelect: () =>
								confirm( 'Delete permanently?', 'The comment, its replies and screenshots will be removed for good. This cannot be undone.', 'Delete permanently', () => {
									void purgeItem( current.id );
									onBack();
								} ),
						},
					] }
				/>
			</div>

			<div className="mnafb-detail__scroll">
				{ current.trashed && (
					<div className="mnafb-where mnafb-where--warn" role="note">
						<Icon name="trash" size={ 16 } />
						<div className="mnafb-where__text">
							<strong>In Trash</strong>
							<span>Deleted { timeAgo( current.trashed_at ) }. Restore it to continue working on it.</span>
						</div>
						{ current.can.restore && (
							<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => void restoreItem( current.id ) }>
								Restore
							</button>
						) }
					</div>
				) }

				<div className="mnafb-detail__head">
					<span className={ `mnafb-num mnafb-num--lg mnafb-num--${ current.status }` } aria-hidden="true">
						{ current.pin.type === 'page' ? <Icon name="page" size={ 15 } /> : number || '•' }
					</span>
					{ editing ? (
						<EditForm item={ current } onDone={ () => setEditing( false ) } />
					) : (
						<div className="mnafb-detail__titles">
							<h2 className="mnafb-detail__title" tabIndex={ -1 } ref={ heading }>
								{ current.title }
							</h2>
							<div className="mnafb-detail__by">
								<Avatar person={ current.author } size={ 20 } />
								<span>{ current.author?.name || 'Someone' }</span>
								<time dateTime={ current.created_at } title={ fullDate( current.created_at ) }>
									{ timeAgo( current.created_at ) }
								</time>
								{ current.edited_at && <span className="mnafb-muted" title={ fullDate( current.edited_at ) }>· edited</span> }
							</div>
						</div>
					) }
				</div>

				{ ! editing && current.body && <Text text={ current.body } className="mnafb-text mnafb-detail__body" /> }

				<div className="mnafb-props">
					<div className="mnafb-prop">
						<span className="mnafb-prop__label">Status</span>
						{ statusButtons ? (
							<div className="mnafb-status-set" role="group" aria-label="Status">
								{ STATUSES.map( ( status ) => (
									<button
										key={ status }
										type="button"
										className={ `mnafb-status-btn mnafb-status-btn--${ status } ${ current.status === status ? 'is-on' : '' }` }
										aria-pressed={ current.status === status }
										onClick={ () => setStatus( status ) }
									>
										{ status === 'done' && <Icon name="check" size={ 13 } /> }
										{ STATUS_LABEL[ status ] }
									</button>
								) ) }
							</div>
						) : (
							<StatusChip status={ current.status } />
						) }
					</div>
					<div className="mnafb-prop">
						<span className="mnafb-prop__label">Priority</span>
						{ current.can.priority ? (
							<select
								className="mnafb-select"
								value={ current.priority }
								aria-label="Priority"
								onChange={ ( e ) => void patchItem( current.id, { priority: e.target.value as Item[ 'priority' ] }, { priority: current.priority } ) }
							>
								{ PRIORITIES.map( ( p ) => (
									<option key={ p } value={ p }>
										{ PRIORITY_LABEL[ p ] }
									</option>
								) ) }
							</select>
						) : (
							<PriorityChip priority={ current.priority } always />
						) }
					</div>
					<div className="mnafb-prop">
						<span className="mnafb-prop__label">Assigned</span>
						{ current.can.assign ? (
							<select
								className="mnafb-select"
								value={ current.assignee?.id || 0 }
								aria-label="Assigned to"
								onChange={ ( e ) => void patchItem( current.id, { assignee_id: Number( e.target.value ) }, { assignee_id: current.assignee?.id || 0 } ) }
							>
								<option value={ 0 }>Unassigned</option>
								{ assignOptions.map( ( p ) => (
									<option key={ p.id } value={ p.id }>
										{ p.id === me?.id ? `${ p.name } (me)` : p.name }
									</option>
								) ) }
							</select>
						) : (
							<span className="mnafb-prop__value">
								{ current.assignee ? (
									<>
										<Avatar person={ current.assignee } size={ 20 } /> { current.assignee.name }
									</>
								) : (
									<span className="mnafb-muted">Unassigned</span>
								) }
							</span>
						) }
					</div>
				</div>

				{ canReopen && (
					<div className="mnafb-reopen">
						<span>Not quite right yet?</span>
						<button type="button" className="mnafb-btn mnafb-btn--primary mnafb-btn--sm" onClick={ () => setStatus( 'open' ) }>
							<Icon name="restore" size={ 14 } />
							<span>Reopen</span>
						</button>
					</div>
				) }

				<Where item={ current } />

				{ ( current.attachments.length > 0 || ( current.can.attach && ! current.trashed ) ) && (
					<section className="mnafb-section">
						<h3 className="mnafb-section__title">Screenshots</h3>
						<Attachments item={ current } list={ current.attachments } />
						{ current.can.attach && ! current.trashed && (
							<>
								<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => shotRef.current?.click() }>
									<Icon name="image" size={ 14 } />
									<span>Add screenshot</span>
								</button>
								<input
									ref={ shotRef }
									type="file"
									hidden
									multiple
									accept="image/png,image/jpeg,image/webp"
									onChange={ ( e ) => {
										const list = Array.from( e.target.files || [] );
										e.target.value = '';
										if ( list.length ) {
											void uploadFiles( current.id, list.slice( 0, 4 ) ).then( ( failed ) => {
												if ( failed ) {
													toast( 'A screenshot did not upload. Please try again.', 'error' );
												}
											} );
										}
									} }
								/>
							</>
						) }
					</section>
				) }

				<div className="mnafb-tabs mnafb-tabs--inline" role="tablist" aria-label="Discussion and history">
					<button type="button" role="tab" aria-selected={ tab === 'discussion' } className={ tab === 'discussion' ? 'is-on' : '' } onClick={ () => setTab( 'discussion' ) }>
						Discussion{ replies.length ? ` (${ replies.filter( ( r ) => ! r.trashed ).length })` : '' }
					</button>
					<button type="button" role="tab" aria-selected={ tab === 'history' } className={ tab === 'history' ? 'is-on' : '' } onClick={ () => setTab( 'history' ) }>
						History
					</button>
				</div>

				{ ! detail && <Spinner label="Loading discussion" /> }

				{ detail && tab === 'discussion' && (
					<section role="tabpanel" aria-label="Discussion">
						{ replies.length ? (
							<ul className="mnafb-replies">
								{ replies.map( ( reply ) => (
									<ReplyRow key={ reply.id } reply={ reply } item={ current } />
								) ) }
							</ul>
						) : (
							<p className="mnafb-muted mnafb-pad">No replies yet.</p>
						) }
					</section>
				) }

				{ detail && tab === 'history' && (
					<section role="tabpanel" aria-label="History">
						<ol className="mnafb-history">
							{ activity
								.slice()
								.reverse()
								.map( ( entry ) => (
									<li key={ entry.id }>
										<Avatar person={ entry.actor } size={ 20 } />
										<div>
											<span>
												<strong>{ entry.actor?.name || 'System' }</strong> { describeActivity( entry ) }
											</span>
											{ entry.source === 'agent' && (
												<span className="mnafb-badge mnafb-badge--agent" title="Done through the AI agent integration">
													via AI agent
												</span>
											) }
											<time dateTime={ entry.created_at } title={ fullDate( entry.created_at ) }>
												{ timeAgo( entry.created_at ) }
											</time>
										</div>
									</li>
								) ) }
						</ol>
					</section>
				) }
			</div>

			{ detail && tab === 'discussion' && current.can.reply && <ReplyBox item={ current } /> }
		</div>
	);
}
