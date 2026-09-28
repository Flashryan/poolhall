/**
 * New comment form: a popover beside the selected element on desktop, a
 * bottom sheet on phones and tablets. Text is saved as a draft while typing,
 * so nothing is lost if posting fails or the page reloads.
 */

import { useEffect, useRef, useState } from 'react';
import { capture } from '../anchor/capture';
import { createItem, pendingFiles, persistComposer } from '../actions';
import { clearDraft } from '../util/drafts';
import { imagesFrom } from '../util/images';
import { getState, setState, toast, useStore, type ComposerState } from '../store';
import { PRIORITIES } from '../types';
import { PRIORITY_LABEL } from '../util/format';
import { useLayout } from '../hooks/useMedia';
import { AutoTextarea, IconButton, useFocusTrap } from './ui';
import { Icon } from './Icons';

const PANEL_WIDTH = 400;
const WIDTH = 360;

function update( patch: Partial< ComposerState > ): void {
	const current = getState().composer;
	if ( current ) {
		setState( { composer: { ...current, ...patch } } );
	}
}

/** Closes the composer, offering Undo when text would be lost. */
export function discardComposer(): void {
	const snapshot = getState().composer;
	setState( { composer: null } );
	if ( ! snapshot ) {
		return;
	}
	clearDraft( snapshot.draftKey );
	if ( snapshot.title.trim() || snapshot.body.trim() ) {
		toast( 'Comment discarded.', 'info', {
			label: 'Undo',
			run: () => {
				persistComposer( snapshot );
				setState( { composer: { ...snapshot, submitting: false, files: [] } } );
			},
		} );
	}
	snapshot.files.forEach( ( f ) => URL.revokeObjectURL( f.preview ) );
}

export function Composer() {
	const composer = useStore( ( s ) => s.composer );
	const panelOpen = useStore( ( s ) => s.panelOpen );
	const branding = useStore( ( s ) => s.branding );
	const media = useLayout();
	const ref = useRef< HTMLFormElement >( null );
	const titleRef = useRef< HTMLInputElement >( null );
	const fileRef = useRef< HTMLInputElement >( null );
	const [ history, setHistory ] = useState< Element[] >( [] );
	const [ dragging, setDragging ] = useState( false );
	const [ height, setHeight ] = useState( 420 );

	useFocusTrap( ref, !! composer, titleRef );

	useEffect( () => {
		setHistory( [] );
	}, [ composer?.draftKey ] );

	// Autosave the draft.
	useEffect( () => {
		if ( ! composer ) {
			return;
		}
		const timer = window.setTimeout( () => {
			if ( composer.title || composer.body ) {
				persistComposer( composer );
			}
		}, 400 );
		return () => window.clearTimeout( timer );
	}, [ composer?.title, composer?.body, composer?.priority ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		if ( ref.current ) {
			setHeight( ref.current.offsetHeight );
		}
	} );

	if ( ! composer ) {
		return null;
	}

	const sheet = media.layout !== 'desktop';
	const style: React.CSSProperties = {};
	if ( ! sheet ) {
		const reserved = panelOpen ? PANEL_WIDTH : 0;
		const vw = window.innerWidth - reserved;
		const vh = window.innerHeight;
		const top = media.adminBar + 12;
		if ( composer.point ) {
			let left = composer.point.x + 18;
			if ( left + WIDTH > vw - 12 ) {
				left = composer.point.x - WIDTH - 18;
			}
			left = Math.max( 12, Math.min( left, vw - WIDTH - 12 ) );
			const y = Math.max( top, Math.min( composer.point.y - 40, vh - height - 12 ) );
			style.left = left;
			style.top = y;
		} else {
			style.left = Math.max( 12, ( vw - WIDTH ) / 2 );
			style.top = Math.max( top, ( vh - height ) / 2 );
		}
	}

	const addFiles = async ( list: File[] ) => {
		if ( ! list.length ) {
			return;
		}
		if ( ! branding?.guest_uploads && getState().session?.me.type === 'guest' ) {
			toast( 'Screenshots are turned off for reviewers on this site.', 'info' );
			return;
		}
		const prepared = await pendingFiles( list.slice( 0, 4 ) );
		const current = getState().composer;
		if ( current ) {
			update( { files: [ ...current.files, ...prepared ].slice( 0, 4 ) } );
		}
	};

	const reselect = ( el: Element, push: boolean ) => {
		const current = getState().composer;
		if ( ! current || ! current.target ) {
			return;
		}
		if ( push ) {
			setHistory( ( h ) => [ ...h, current.target as Element ] );
		}
		const point = current.point;
		const cap = capture( el, point ? point.x : null, point ? point.y : null );
		update( { target: el, anchor: cap.anchor, pin: { type: 'element', x: cap.x, y: cap.y }, label: cap.label } );
	};

	const widen = () => {
		const parent = composer.target?.parentElement;
		if ( parent && parent !== document.body && parent !== document.documentElement ) {
			reselect( parent, true );
		}
	};

	const narrow = () => {
		const previous = history[ history.length - 1 ];
		if ( previous ) {
			setHistory( ( h ) => h.slice( 0, -1 ) );
			reselect( previous, false );
		}
	};

	const cancel = discardComposer;

	const submit = async ( event?: React.FormEvent ) => {
		event?.preventDefault();
		const current = getState().composer;
		if ( ! current || current.submitting ) {
			return;
		}
		if ( ! current.title.trim() && ! current.body.trim() ) {
			update( { error: 'Add a short title for this comment.' } );
			titleRef.current?.focus();
			return;
		}
		persistComposer( current );
		await createItem( current );
	};

	const canAttach = getState().session?.me.type !== 'guest' || !! branding?.guest_uploads;

	return (
		<>
			{ sheet && <div className="mnafb-scrim" onClick={ cancel } /> }
			<form
				ref={ ref }
				className={ `mnafb-composer ${ sheet ? 'mnafb-composer--sheet' : 'mnafb-composer--pop' } ${ dragging ? 'is-dragging' : '' }` }
				style={ style }
				role="dialog"
				aria-modal={ sheet ? 'true' : undefined }
				aria-label="New comment"
				onSubmit={ submit }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Escape' ) {
						e.stopPropagation();
						cancel();
					} else if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
						void submit();
					}
				} }
				onPaste={ ( e ) => {
					const files = imagesFrom( e.clipboardData );
					if ( files.length && canAttach ) {
						e.preventDefault();
						void addFiles( files );
					}
				} }
				onDragOver={ ( e ) => {
					if ( canAttach && Array.from( e.dataTransfer.types ).includes( 'Files' ) ) {
						e.preventDefault();
						setDragging( true );
					}
				} }
				onDragLeave={ () => setDragging( false ) }
				onDrop={ ( e ) => {
					if ( canAttach ) {
						e.preventDefault();
						setDragging( false );
						void addFiles( imagesFrom( e.dataTransfer ) );
					}
				} }
			>
				<header className="mnafb-composer__head">
					<span className="mnafb-target-chip">
						<Icon name={ composer.pin.type === 'page' ? 'page' : 'crosshair' } size={ 14 } />
						{ composer.label }
					</span>
					{ composer.target && (
						<span className="mnafb-composer__scope">
							<IconButton icon="expand" label="Select a larger area" onClick={ widen } size={ 16 } />
							<IconButton icon="locate" label="Back to the smaller area" onClick={ narrow } disabled={ ! history.length } size={ 16 } />
						</span>
					) }
					<IconButton icon="close" label="Cancel" onClick={ cancel } />
				</header>

				<label className="mnafb-field">
					<span className="mnafb-sr">Title</span>
					<input
						ref={ titleRef }
						className="mnafb-input mnafb-input--title"
						value={ composer.title }
						maxLength={ 200 }
						placeholder="What needs changing?"
						onChange={ ( e ) => update( { title: e.target.value, error: '' } ) }
						enterKeyHint="next"
					/>
				</label>
				<label className="mnafb-field">
					<span className="mnafb-sr">Details</span>
					<AutoTextarea
						className="mnafb-input"
						value={ composer.body }
						maxLength={ 10000 }
						placeholder="Add details (optional)"
						onChange={ ( e ) => update( { body: e.target.value } ) }
						minRows={ 3 }
					/>
				</label>

				<fieldset className="mnafb-segment" aria-label="Priority">
					<legend className="mnafb-sr">Priority</legend>
					{ PRIORITIES.map( ( p ) => (
						<label key={ p } className={ `mnafb-segment__opt ${ composer.priority === p ? 'is-on' : '' } mnafb-p-${ p }` }>
							<input type="radio" name="mnafb-priority" value={ p } checked={ composer.priority === p } onChange={ () => update( { priority: p } ) } />
							<span>{ PRIORITY_LABEL[ p ] }</span>
						</label>
					) ) }
				</fieldset>

				{ ( composer.files.length > 0 || canAttach ) && (
					<div className="mnafb-attach-row">
						{ composer.files.map( ( f ) => (
							<span key={ f.id } className="mnafb-thumb">
								<img src={ f.preview } alt="Screenshot to attach" />
								<button
									type="button"
									className="mnafb-thumb__remove"
									aria-label="Remove screenshot"
									onClick={ () => {
										URL.revokeObjectURL( f.preview );
										update( { files: composer.files.filter( ( x ) => x.id !== f.id ) } );
									} }
								>
									<Icon name="close" size={ 12 } />
								</button>
							</span>
						) ) }
						{ canAttach && composer.files.length < 4 && (
							<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => fileRef.current?.click() }>
								<Icon name="image" size={ 16 } />
								<span>{ composer.files.length ? 'Add another' : 'Add screenshot' }</span>
							</button>
						) }
						<input
							ref={ fileRef }
							type="file"
							accept="image/png,image/jpeg,image/webp"
							multiple
							hidden
							onChange={ ( e ) => {
								void addFiles( Array.from( e.target.files || [] ) );
								e.target.value = '';
							} }
						/>
					</div>
				) }

				{ composer.error && (
					<p className="mnafb-error" role="alert">
						<Icon name="warning" size={ 16 } />
						<span>{ composer.error }</span>
					</p>
				) }

				<footer className="mnafb-composer__foot">
					<span className="mnafb-hint-text">{ media.layout === 'desktop' ? 'Ctrl + Enter to post' : '' }</span>
					<button type="button" className="mnafb-btn mnafb-btn--ghost" onClick={ cancel }>
						Cancel
					</button>
					<button type="submit" className="mnafb-btn mnafb-btn--primary" disabled={ composer.submitting }>
						{ composer.submitting ? 'Posting…' : composer.error ? 'Try again' : 'Post comment' }
					</button>
				</footer>
			</form>
		</>
	);
}
