/**
 * What people see before they are in: the join form after opening a shared
 * link, or a short explanation when a link has expired or access has ended.
 */

import { useRef, useState } from 'react';
import { join, leave } from '../actions';
import { useStore } from '../store';
import { useLayout } from '../hooks/useMedia';
import { Icon } from './Icons';
import { useFocusTrap } from './ui';

function JoinForm() {
	const branding = useStore( ( s ) => s.branding );
	const label = useStore( ( s ) => s.joinLabel );
	const media = useLayout();
	const [ name, setName ] = useState( '' );
	const [ email, setEmail ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const ref = useRef< HTMLFormElement >( null );
	const nameRef = useRef< HTMLInputElement >( null );
	useFocusTrap( ref, true, nameRef );

	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		if ( ! name.trim() ) {
			setError( 'Enter your name so the team knows who left each comment.' );
			nameRef.current?.focus();
			return;
		}
		setBusy( true );
		setError( '' );
		const problem = await join( name.trim(), email.trim() );
		setBusy( false );
		if ( problem ) {
			setError( problem );
		}
	};

	return (
		<div className={ `mnafb-modal ${ media.layout === 'phone' ? 'mnafb-modal--sheet' : '' }` }>
			<form ref={ ref } className="mnafb-dialog mnafb-dialog--sm mnafb-join" onSubmit={ submit } aria-labelledby="mnafb-join-title" role="dialog" aria-modal="true">
				<div className="mnafb-join__brand">
					{ branding?.logo ? (
						<img src={ branding.logo } alt="" className="mnafb-brand__logo" />
					) : (
						<span className="mnafb-brand__mark" aria-hidden="true">
							<Icon name="message" size={ 18 } />
						</span>
					) }
					<span>{ branding?.name || 'Feedback' }</span>
				</div>
				<h2 id="mnafb-join-title">You’ve been invited to review this site</h2>
				<p className="mnafb-muted">
					{ label ? <>“{ label }”. </> : null }
					Point at anything on the page and tell the team what should change. Enter your name once — no account needed.
				</p>
				<label className="mnafb-field">
					<span className="mnafb-label">Your name</span>
					<input ref={ nameRef } className="mnafb-input" value={ name } maxLength={ 80 } autoComplete="name" onChange={ ( e ) => setName( e.target.value ) } required />
				</label>
				<label className="mnafb-field">
					<span className="mnafb-label">
						Email <span className="mnafb-muted">(optional)</span>
					</span>
					<input className="mnafb-input" type="email" value={ email } maxLength={ 190 } autoComplete="email" onChange={ ( e ) => setEmail( e.target.value ) } />
					<span className="mnafb-help">Only the site’s managers can see it.</span>
				</label>
				<p className="mnafb-help">Your name is shown next to your comments to everyone reviewing this site.</p>
				{ error && (
					<p className="mnafb-error" role="alert">
						<Icon name="warning" size={ 16 } />
						<span>{ error }</span>
					</p>
				) }
				<div className="mnafb-dialog__foot">
					<button type="button" className="mnafb-btn mnafb-btn--ghost" onClick={ () => void leave() }>
						Not now
					</button>
					<button type="submit" className="mnafb-btn mnafb-btn--primary" disabled={ busy }>
						{ busy ? 'Joining…' : 'Start reviewing' }
					</button>
				</div>
			</form>
		</div>
	);
}

function Notice() {
	const phase = useStore( ( s ) => s.phase );
	const notice = useStore( ( s ) => s.notice );
	const title =
		phase === 'invalid' ? 'This review link can’t be used' : phase === 'no-access' ? 'No feedback access' : phase === 'error' ? 'The review tool couldn’t start' : 'Review access has ended';
	return (
		<div className="mnafb-notice" role="alertdialog" aria-labelledby="mnafb-notice-title" aria-describedby="mnafb-notice-text">
			<Icon name="warning" size={ 18 } />
			<div>
				<strong id="mnafb-notice-title">{ title }</strong>
				<p id="mnafb-notice-text">{ notice }</p>
				<div className="mnafb-notice__actions">
					{ phase === 'error' && (
						<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => window.location.reload() }>
							Reload
						</button>
					) }
					<button type="button" className="mnafb-btn mnafb-btn--primary mnafb-btn--sm" onClick={ () => void leave() }>
						Close
					</button>
				</div>
			</div>
		</div>
	);
}

export function Gate() {
	const phase = useStore( ( s ) => s.phase );
	if ( phase === 'join' ) {
		return <JoinForm />;
	}
	if ( phase === 'invalid' || phase === 'ended' || phase === 'no-access' || phase === 'error' ) {
		return <Notice />;
	}
	return null;
}
