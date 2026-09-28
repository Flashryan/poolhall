/** Modal dialogs: confirmations, edit conflicts, profile, return link, image viewer. */

import { useEffect, useRef, useState } from 'react';
import { api, patchItem, showReturnLink, updateProfile } from '../actions';
import { setState, toast, useStore, type Dialog as DialogState } from '../store';
import { clearDraft } from '../util/drafts';
import { Icon } from './Icons';
import { Dialog, Spinner, copyText } from './ui';

function close(): void {
	setState( { dialog: null } );
}

function Confirm( { dialog }: { dialog: Extract< DialogState, { type: 'confirm' } > } ) {
	const confirmRef = useRef< HTMLButtonElement >( null );
	return (
		<Dialog
			title={ dialog.title }
			onClose={ close }
			size="sm"
			initialFocus={ confirmRef }
			footer={
				<>
					<button type="button" className="mnafb-btn mnafb-btn--ghost" onClick={ close }>
						Cancel
					</button>
					<button
						ref={ confirmRef }
						type="button"
						className={ `mnafb-btn ${ dialog.danger ? 'mnafb-btn--danger' : 'mnafb-btn--primary' }` }
						onClick={ () => {
							close();
							dialog.run();
						} }
					>
						{ dialog.confirm }
					</button>
				</>
			}
		>
			<p>{ dialog.text }</p>
		</Dialog>
	);
}

function Conflict( { dialog }: { dialog: Extract< DialogState, { type: 'conflict' } > } ) {
	const { theirs, mine } = dialog;
	const [ busy, setBusy ] = useState( false );
	const keepMine = async () => {
		setBusy( true );
		const expected: Record< string, string > = {};
		const fields: { title?: string; body?: string } = {};
		if ( mine.title !== undefined ) {
			fields.title = mine.title;
			expected.title = theirs.title;
		}
		if ( mine.body !== undefined ) {
			fields.body = mine.body;
			expected.body = theirs.body;
		}
		const result = await patchItem( dialog.itemId, fields, expected );
		setBusy( false );
		if ( result ) {
			clearDraft( `edit:${ dialog.itemId }` );
			close();
			toast( 'Your version was saved.', 'success' );
		}
	};
	const copyMine = () => {
		const text = [ mine.title, mine.body ].filter( Boolean ).join( '\n\n' );
		void copyText( text ).then( ( ok ) => toast( ok ? 'Your text was copied.' : 'Copying is not available here — select the text to copy it.', ok ? 'success' : 'info' ) );
	};
	return (
		<Dialog
			title="Someone else changed this"
			onClose={ close }
			size="lg"
			footer={
				<>
					<button type="button" className="mnafb-btn mnafb-btn--ghost" onClick={ copyMine }>
						Copy my text
					</button>
					<span className="mnafb-spacer" />
					<button
						type="button"
						className="mnafb-btn mnafb-btn--ghost"
						onClick={ () => {
							clearDraft( `edit:${ dialog.itemId }` );
							close();
						} }
					>
						Keep their version
					</button>
					<button type="button" className="mnafb-btn mnafb-btn--primary" disabled={ busy } onClick={ () => void keepMine() }>
						{ busy ? 'Saving…' : 'Use my version' }
					</button>
				</>
			}
		>
			<p>While you were editing, this comment was changed by someone else. Nothing has been overwritten — choose which version to keep. If you close this, your text stays saved and comes back when you choose Edit.</p>
			<div className="mnafb-compare">
				<div>
					<h3>Their version</h3>
					<strong>{ theirs.title }</strong>
					<p className="mnafb-text">{ theirs.body || <span className="mnafb-muted">No description</span> }</p>
				</div>
				<div>
					<h3>Your version</h3>
					<strong>{ mine.title ?? theirs.title }</strong>
					<p className="mnafb-text">{ ( mine.body ?? theirs.body ) || <span className="mnafb-muted">No description</span> }</p>
				</div>
			</div>
		</Dialog>
	);
}

function Profile() {
	const me = useStore( ( s ) => s.session?.me );
	const [ name, setName ] = useState( me?.name || '' );
	const [ email, setEmail ] = useState( me?.email || '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const save = async ( event: React.FormEvent ) => {
		event.preventDefault();
		setBusy( true );
		const problem = await updateProfile( name.trim(), email.trim() );
		setBusy( false );
		if ( problem ) {
			setError( problem );
		} else {
			close();
		}
	};
	return (
		<Dialog title="Your details" onClose={ close } size="sm">
			<form onSubmit={ save } className="mnafb-stack">
				<label className="mnafb-field">
					<span className="mnafb-label">Name</span>
					<input className="mnafb-input" value={ name } maxLength={ 80 } onChange={ ( e ) => setName( e.target.value ) } required />
				</label>
				<label className="mnafb-field">
					<span className="mnafb-label">
						Email <span className="mnafb-muted">(optional)</span>
					</span>
					<input className="mnafb-input" type="email" value={ email } maxLength={ 190 } onChange={ ( e ) => setEmail( e.target.value ) } />
				</label>
				<p className="mnafb-help">Your new name appears on all your comments.</p>
				{ error && (
					<p className="mnafb-error" role="alert">
						{ error }
					</p>
				) }
				<div className="mnafb-dialog__foot mnafb-dialog__foot--flush">
					<button type="button" className="mnafb-btn mnafb-btn--ghost" onClick={ close }>
						Cancel
					</button>
					<button type="submit" className="mnafb-btn mnafb-btn--primary" disabled={ busy || ! name.trim() }>
						{ busy ? 'Saving…' : 'Save' }
					</button>
				</div>
			</form>
		</Dialog>
	);
}

function ReturnLink( { dialog }: { dialog: Extract< DialogState, { type: 'return-link' } > } ) {
	const inputRef = useRef< HTMLInputElement >( null );
	const [ copied, setCopied ] = useState( false );
	return (
		<Dialog
			title={ dialog.afterJoin ? 'You’re in' : 'Continue on another device' }
			onClose={ close }
			size="sm"
			footer={
				<>
					{ ! dialog.afterJoin && (
						<button type="button" className="mnafb-btn mnafb-btn--ghost" onClick={ () => void showReturnLink( true ) }>
							Replace link
						</button>
					) }
					<span className="mnafb-spacer" />
					<button type="button" className="mnafb-btn mnafb-btn--primary" onClick={ close }>
						{ dialog.afterJoin ? 'Start reviewing' : 'Done' }
					</button>
				</>
			}
		>
			{ dialog.afterJoin && <p>Switch to Comment mode and click anything on the page to leave feedback about it.</p> }
			<p>
				<strong>Your private link.</strong> Open it on another device or browser to carry on as the same person. Keep it to yourself — anyone with it can comment as you.
			</p>
			<div className="mnafb-copy">
				<input ref={ inputRef } className="mnafb-input" readOnly value={ dialog.url } aria-label="Your private link" onFocus={ ( e ) => e.target.select() } />
				<button
					type="button"
					className="mnafb-btn mnafb-btn--ghost"
					onClick={ () => {
						void copyText( dialog.url ).then( ( ok ) => {
							if ( ok ) {
								setCopied( true );
								window.setTimeout( () => setCopied( false ), 2000 );
							} else {
								inputRef.current?.select();
							}
						} );
					} }
				>
					<Icon name={ copied ? 'check' : 'link' } size={ 16 } />
					<span>{ copied ? 'Copied' : 'Copy' }</span>
				</button>
			</div>
		</Dialog>
	);
}

function Lightbox( { dialog }: { dialog: Extract< DialogState, { type: 'lightbox' } > } ) {
	const [ url, setUrl ] = useState< string | null >( null );
	const [ failed, setFailed ] = useState( false );
	useEffect( () => {
		let objectUrl: string | null = null;
		const controller = new AbortController();
		api.blobUrl( dialog.src, controller.signal )
			.then( ( value ) => {
				objectUrl = value;
				setUrl( value );
			} )
			.catch( ( error ) => {
				if ( ( error as Error )?.name !== 'AbortError' ) {
					setFailed( true );
				}
			} );
		return () => {
			controller.abort();
			if ( objectUrl ) {
				URL.revokeObjectURL( objectUrl );
			}
		};
	}, [ dialog.src ] );
	return (
		<Dialog title={ dialog.alt } onClose={ close } size="lg">
			<div className="mnafb-lightbox">
				{ url && <img src={ url } alt={ dialog.alt } /> }
				{ ! url && ! failed && <Spinner label="Loading screenshot" /> }
				{ failed && <p className="mnafb-muted">The screenshot could not be loaded.</p> }
			</div>
		</Dialog>
	);
}

export function Dialogs() {
	const dialog = useStore( ( s ) => s.dialog );
	if ( ! dialog ) {
		return null;
	}
	switch ( dialog.type ) {
		case 'confirm':
			return <Confirm dialog={ dialog } />;
		case 'conflict':
			return <Conflict dialog={ dialog } />;
		case 'profile':
			return <Profile />;
		case 'return-link':
			return <ReturnLink dialog={ dialog } />;
		case 'lightbox':
			return <Lightbox dialog={ dialog } />;
	}
	return null;
}
