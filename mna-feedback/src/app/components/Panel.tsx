/**
 * The review panel: a 400px column on the right on desktop, an overlay on
 * tablets and a bottom sheet on phones. Collapses to a small launcher.
 */

import { useEffect, useMemo, useState } from 'react';
import { getResolution, useResolutions } from '../anchor/registry';
import { resolve } from '../anchor/resolve';
import { leave, loadTrash, openItem, purgeItem, restoreItem, showReturnLink } from '../actions';
import { pageNumbers, setState, useStore, type PanelFilter } from '../store';
import type { Anchor, Item, Priority } from '../types';
import { clearDraft, listDrafts } from '../util/drafts';
import { pagePath, timeAgo } from '../util/format';
import { useLayout } from '../hooks/useMedia';
import { Card } from './Card';
import { openComposer, restoreComposer } from './CommentLayer';
import { Detail } from './Detail';
import { Icon } from './Icons';
import { Avatar, IconButton, Menu, Spinner } from './ui';

function Brand() {
	const branding = useStore( ( s ) => s.branding );
	const sync = useStore( ( s ) => s.sync );
	return (
		<div className="mnafb-brand">
			{ branding?.logo ? (
				<img className="mnafb-brand__logo" src={ branding.logo } alt="" />
			) : (
				<span className="mnafb-brand__mark" aria-hidden="true">
					<Icon name="message" size={ 16 } />
				</span>
			) }
			<span className="mnafb-brand__name">{ branding?.name || 'Feedback' }</span>
			{ sync === 'offline' && (
				<span className="mnafb-offline" role="status" title="Reconnecting — your changes are kept on this device until the site is reachable">
					<Icon name="offline" size={ 14 } /> Offline
				</span>
			) }
		</div>
	);
}

export function ModeSwitch( { compact = false }: { compact?: boolean } ) {
	const mode = useStore( ( s ) => s.mode );
	return (
		<div className={ `mnafb-mode ${ compact ? 'mnafb-mode--compact' : '' }` } role="group" aria-label="Mode">
			<button type="button" className={ mode === 'browse' ? 'is-on' : '' } aria-pressed={ mode === 'browse' } onClick={ () => setState( { mode: 'browse', composer: null } ) }>
				<Icon name="cursor" size={ 15 } />
				<span>Browse</span>
			</button>
			<button type="button" className={ mode === 'comment' ? 'is-on' : '' } aria-pressed={ mode === 'comment' } onClick={ () => setState( { mode: 'comment' } ) }>
				<Icon name="crosshair" size={ 15 } />
				<span>Comment</span>
			</button>
		</div>
	);
}

function PanelMenu() {
	const session = useStore( ( s ) => s.session );
	const pinsVisible = useStore( ( s ) => s.pinsVisible );
	if ( ! session ) {
		return null;
	}
	const me = session.me;
	const guest = me.type === 'guest';
	return (
		<Menu
			label="More options"
			items={ [
				{ label: pinsVisible ? 'Hide pins' : 'Show pins', icon: pinsVisible ? 'eyeOff' : 'eye', onSelect: () => setState( { pinsVisible: ! pinsVisible } ) },
				guest && { label: 'Your name and email', icon: 'user', onSelect: () => setState( { dialog: { type: 'profile' } } ) },
				guest && { label: 'Continue on another device', icon: 'link', onSelect: () => void showReturnLink() },
				me.caps.manage && { label: 'Trash', icon: 'trash', onSelect: () => setState( { view: { name: 'trash' } } ) },
				me.caps.manage && !! session.admin_url && { label: 'Links, people and settings', icon: 'settings', onSelect: () => window.open( session.admin_url as string, '_blank', 'noopener' ) },
				{
					label: guest ? 'Sign out of review' : 'Turn review mode off',
					icon: 'logout',
					onSelect: () =>
						setState( {
							dialog: {
								type: 'confirm',
								title: guest ? 'Sign out of review?' : 'Turn review mode off?',
								text: guest ? 'You can come back with the review link or your private return link.' : 'The review tool will be hidden until you turn it on again from the toolbar.',
								confirm: guest ? 'Sign out' : 'Turn off',
								danger: false,
								run: () => void leave(),
							},
						} ),
				},
			] }
		/>
	);
}

interface SavedDraft {
	pin: { type: 'element' | 'page'; x: number; y: number };
	anchor: Anchor | null;
	label: string;
	title: string;
	body: string;
	priority: Priority;
}

function DraftBanner() {
	const pageKey = useStore( ( s ) => s.pageKey );
	const composer = useStore( ( s ) => s.composer );
	const [ tick, setTick ] = useState( 0 );
	const drafts = useMemo( () => ( pageKey ? listDrafts< SavedDraft >( `new:${ pageKey }:` ) : [] ), [ pageKey, composer, tick ] ); // eslint-disable-line react-hooks/exhaustive-deps
	const draft = drafts.find( ( d ) => d.value && ( d.value.title || d.value.body ) );
	if ( ! draft || composer ) {
		return null;
	}
	return (
		<div className="mnafb-banner" role="status">
			<Icon name="edit" size={ 16 } />
			<span>
				You have an unsent comment{ draft.value.title ? ` — “${ draft.value.title.slice( 0, 40 ) }”` : '' }.
			</span>
			<button
				type="button"
				className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm"
				onClick={ () => {
					const found = draft.value.anchor ? resolve( draft.value.anchor ) : null;
					restoreComposer( draft.key, draft.value, found && found.el && found.state !== 'unavailable' ? found.el : null );
				} }
			>
				Restore
			</button>
			<button
				type="button"
				className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm"
				onClick={ () => {
					clearDraft( draft.key );
					setTick( ( t ) => t + 1 );
				} }
			>
				Discard
			</button>
		</div>
	);
}

function PanelList() {
	const items = useStore( ( s ) => s.items );
	const pageKey = useStore( ( s ) => s.pageKey );
	const filter = useStore( ( s ) => s.panelFilter );
	const loaded = useStore( ( s ) => s.itemsLoaded );
	const branding = useStore( ( s ) => s.branding );
	const pageTitle = useStore( ( s ) => s.pageTitle );
	useResolutions();

	const onPage = useMemo( () => Object.values( items ).filter( ( item ) => item.page.key === pageKey && ! item.trashed ), [ items, pageKey ] );
	const numbers = useMemo( () => pageNumbers( items, pageKey ), [ items, pageKey ] );
	const counts = {
		active: onPage.filter( ( i ) => i.status !== 'done' ).length,
		done: onPage.filter( ( i ) => i.status === 'done' ).length,
		all: onPage.length,
	};
	const shown = onPage
		.filter( ( i ) => filter === 'all' || ( filter === 'done' ? i.status === 'done' : i.status !== 'done' ) )
		.sort( ( a, b ) => ( numbers.get( a.id ) || 0 ) - ( numbers.get( b.id ) || 0 ) );
	const otherPages = useMemo( () => Object.values( items ).filter( ( i ) => i.page.key !== pageKey && ! i.trashed && i.status !== 'done' ).length, [ items, pageKey ] );

	const tabs: Array< [ PanelFilter, string, number ] > = [
		[ 'active', 'Open', counts.active ],
		[ 'done', 'Done', counts.done ],
		[ 'all', 'All', counts.all ],
	];

	return (
		<>
			<div className="mnafb-page">
				<span className="mnafb-page__label">This page</span>
				<span className="mnafb-page__name" title={ window.location.href }>
					{ pageTitle || document.title || pagePath( window.location.href ) }
				</span>
			</div>
			<div className="mnafb-tabs" role="tablist" aria-label="Filter comments">
				{ tabs.map( ( [ value, label, count ] ) => (
					<button key={ value } type="button" role="tab" aria-selected={ filter === value } className={ filter === value ? 'is-on' : '' } onClick={ () => setState( { panelFilter: value } ) }>
						{ label } <span className="mnafb-tabs__count">{ count }</span>
					</button>
				) ) }
			</div>
			<DraftBanner />
			<div className="mnafb-panel__scroll">
				{ ! loaded && <Spinner label="Loading comments" /> }
				{ loaded && shown.length === 0 && (
					<div className="mnafb-empty">
						{ filter === 'done' ? (
							<p>Nothing on this page has been marked done yet.</p>
						) : (
							<>
								<p>
									<strong>No open comments on this page.</strong>
								</p>
								<p>Switch to Comment mode, then click any part of the page to leave feedback about it.</p>
								<button type="button" className="mnafb-btn mnafb-btn--primary" onClick={ () => setState( { mode: 'comment' } ) }>
									<Icon name="crosshair" size={ 16 } />
									<span>Start commenting</span>
								</button>
							</>
						) }
					</div>
				) }
				{ shown.length > 0 && (
					<ul className="mnafb-list">
						{ shown.map( ( item ) => (
							<li key={ item.id }>
								<Card
									item={ item }
									number={ numbers.get( item.id ) || 0 }
									variant="panel"
									onOpen={ () => openItem( item.id ) }
									anchorState={ item.pin.type === 'element' ? getResolution( item.id )?.state ?? null : null }
								/>
							</li>
						) ) }
					</ul>
				) }
				{ otherPages > 0 && (
					<button type="button" className="mnafb-more-link" onClick={ () => setState( { boardOpen: true } ) }>
						{ otherPages } open on other pages — view the board
						<Icon name="chevronRight" size={ 14 } />
					</button>
				) }
			</div>
			{ branding?.page_comments && (
				<footer className="mnafb-panel__foot">
					<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--block" onClick={ () => openComposer( null, null ) }>
						<Icon name="page" size={ 16 } />
						<span>Comment on the whole page</span>
					</button>
				</footer>
			) }
		</>
	);
}

function TrashView() {
	const trash = useStore( ( s ) => s.trash );
	useEffect( () => {
		void loadTrash();
	}, [] );
	return (
		<>
			<div className="mnafb-detail__bar">
				<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => setState( { view: { name: 'list' }, trash: null } ) }>
					<Icon name="chevronLeft" size={ 16 } />
					<span>All comments</span>
				</button>
			</div>
			<div className="mnafb-page">
				<span className="mnafb-page__label">Trash</span>
				<span className="mnafb-page__name">Deleted comments from every page</span>
			</div>
			<div className="mnafb-panel__scroll">
				{ ! trash && <Spinner label="Loading Trash" /> }
				{ trash && ! trash.length && <p className="mnafb-empty">Trash is empty.</p> }
				{ trash && trash.length > 0 && (
					<ul className="mnafb-list">
						{ trash.map( ( item: Item ) => (
							<li key={ item.id } className="mnafb-trash-row">
								<div className="mnafb-trash-row__main">
									<strong>{ item.title }</strong>
									<span className="mnafb-muted">
										<Avatar person={ item.author } size={ 16 } /> { item.author?.name } · { item.page.title || pagePath( item.page.url ) } · deleted { timeAgo( item.trashed_at ) }
									</span>
								</div>
								<div className="mnafb-trash-row__actions">
									<button type="button" className="mnafb-btn mnafb-btn--ghost mnafb-btn--sm" onClick={ () => void restoreItem( item.id ) }>
										<Icon name="restore" size={ 14 } />
										<span>Restore</span>
									</button>
									<button
										type="button"
										className="mnafb-btn mnafb-btn--danger-ghost mnafb-btn--sm"
										onClick={ () =>
											setState( {
												dialog: {
													type: 'confirm',
													title: 'Delete permanently?',
													text: 'The comment, its replies and screenshots will be removed for good. This cannot be undone.',
													confirm: 'Delete permanently',
													danger: true,
													run: () => void purgeItem( item.id ),
												},
											} )
										}
									>
										Delete permanently
									</button>
								</div>
							</li>
						) ) }
					</ul>
				) }
			</div>
		</>
	);
}

export function Launcher() {
	const items = useStore( ( s ) => s.items );
	const pageKey = useStore( ( s ) => s.pageKey );
	const branding = useStore( ( s ) => s.branding );
	const mode = useStore( ( s ) => s.mode );
	const media = useLayout();
	const active = Object.values( items ).filter( ( i ) => i.page.key === pageKey && ! i.trashed && i.status !== 'done' ).length;
	const unread = Object.values( items ).some( ( i ) => i.unread && ! i.trashed );
	const phone = media.layout === 'phone';
	return (
		<div className="mnafb-launcher" role="region" aria-label={ branding?.name || 'Feedback' }>
			{ ! phone && mode !== 'comment' && (
				<button type="button" className="mnafb-launcher__comment" onClick={ () => setState( { mode: 'comment' } ) }>
					<Icon name="crosshair" size={ 16 } />
					<span>Comment</span>
				</button>
			) }
			<button type="button" className={ `mnafb-launcher__main ${ phone ? 'is-round' : '' }` } onClick={ () => setState( { panelOpen: true, mode: phone ? 'browse' : mode } ) } aria-label={ `Open ${ branding?.name || 'feedback' } panel${ active ? `, ${ active } open on this page` : '' }` }>
				<Icon name="message" size={ phone ? 22 : 18 } />
				{ ! phone && <span>{ branding?.name || 'Feedback' }</span> }
				{ active > 0 && <span className="mnafb-launcher__count">{ active }</span> }
				{ unread && <span className="mnafb-launcher__dot" aria-hidden="true" /> }
			</button>
		</div>
	);
}

export function Panel() {
	const open = useStore( ( s ) => s.panelOpen );
	const view = useStore( ( s ) => s.view );
	const mode = useStore( ( s ) => s.mode );
	const composer = useStore( ( s ) => s.composer );
	const branding = useStore( ( s ) => s.branding );
	const media = useLayout();
	const phone = media.layout === 'phone';
	const [ expanded, setExpanded ] = useState( false );

	// On phones the sheet steps aside while choosing an element.
	if ( ! open || ( phone && ( mode === 'comment' || composer ) ) ) {
		return composer && phone ? null : <Launcher />;
	}

	const layout = phone ? 'sheet' : media.layout === 'tablet' ? 'overlay' : 'side';
	return (
		<aside
			className={ `mnafb-panel mnafb-panel--${ layout } ${ expanded ? 'is-expanded' : '' }` }
			aria-label={ `${ branding?.name || 'Feedback' } panel` }
			style={ layout === 'sheet' ? undefined : { top: media.adminBar } }
		>
			{ phone && (
				<button type="button" className="mnafb-sheet-handle" aria-label={ expanded ? 'Shrink panel' : 'Expand panel' } onClick={ () => setExpanded( ( v ) => ! v ) }>
					<span />
				</button>
			) }
			<header className="mnafb-panel__head">
				<Brand />
				<div className="mnafb-panel__actions">
					<IconButton icon="board" label="Open the board" onClick={ () => setState( { boardOpen: true } ) } showLabel={ ! phone } className="mnafb-btn--sm" />
					<PanelMenu />
					<IconButton icon={ phone ? 'close' : 'chevronRight' } label="Hide panel" onClick={ () => setState( { panelOpen: false } ) } />
				</div>
			</header>
			<div className="mnafb-modebar">
				<ModeSwitch />
			</div>
			{ view.name === 'list' && <PanelList /> }
			{ view.name === 'detail' && <Detail id={ view.id } variant="panel" onBack={ () => setState( { view: { name: 'list' } } ) } /> }
			{ view.name === 'trash' && <TrashView /> }
		</aside>
	);
}
