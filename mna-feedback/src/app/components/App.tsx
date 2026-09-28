import { useEffect } from 'react';
import { getState, setState, useStore } from '../store';
import { MediaContext, useMedia } from '../hooks/useMedia';
import { accentVars } from '../util/color';
import { Board } from './Board';
import { CommentLayer } from './CommentLayer';
import { Composer, discardComposer } from './Composer';
import { Dialogs } from './Dialogs';
import { Gate } from './Gate';
import { Panel } from './Panel';
import { Pins } from './Pins';
import { Toasts } from './Toasts';
import { closeTopMenu } from './ui';

/** Closes the topmost open layer. Returns false when nothing was open. */
export function handleEscape(): boolean {
	if ( closeTopMenu() ) {
		return true;
	}
	const s = getState();
	if ( s.dialog ) {
		setState( { dialog: null } );
	} else if ( s.composer ) {
		discardComposer();
	} else if ( s.mode === 'comment' ) {
		setState( { mode: 'browse' } );
	} else if ( s.boardOpen && s.boardDetailId ) {
		setState( { boardDetailId: null } );
	} else if ( s.boardOpen ) {
		setState( { boardOpen: false } );
	} else if ( s.view.name !== 'list' ) {
		setState( { view: { name: 'list' } } );
	} else {
		return false;
	}
	return true;
}

export function App() {
	const phase = useStore( ( s ) => s.phase );
	const branding = useStore( ( s ) => s.branding );
	const media = useMedia();

	// Escape pressed while focus is on the page itself.
	useEffect( () => {
		const onKey = ( event: KeyboardEvent ) => {
			if ( event.key === 'Escape' && ! event.defaultPrevented && handleEscape() ) {
				event.preventDefault();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [] );

	if ( phase === 'closed' ) {
		return null;
	}

	const style = { ...accentVars( branding?.accent ), '--mnafb-top': `${ media.adminBar }px` } as React.CSSProperties;

	return (
		<MediaContext.Provider value={ media }>
			<div
				className={ `mnafb mnafb--${ media.layout } ${ media.coarse ? 'mnafb--touch' : '' } ${ media.reducedMotion ? 'mnafb--calm' : '' }` }
				style={ style }
				onKeyDown={ ( event ) => {
					if ( event.key === 'Escape' && handleEscape() ) {
						event.preventDefault();
					}
				} }
			>
				{ phase === 'ready' && (
					<>
						<Pins />
						<CommentLayer />
						<Panel />
						<Board />
						<Composer />
					</>
				) }
				<Gate />
				<Dialogs />
				<Toasts />
			</div>
		</MediaContext.Provider>
	);
}
