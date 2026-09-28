/** A feedback card, used in the side panel list and on the board. */

import type { ReactNode } from 'react';
import type { ResolveState } from '../anchor/resolve';
import { setState } from '../store';
import type { Item } from '../types';
import { STATUS_LABEL, fullDate, pagePath, timeAgo } from '../util/format';
import { Avatar, PriorityChip, StatusChip } from './ui';
import { Icon } from './Icons';

export interface CardProps {
	item: Item;
	number: number;
	variant: 'panel' | 'board';
	onOpen: () => void;
	showPage?: boolean;
	anchorState?: ResolveState | null;
	selected?: boolean;
	dragging?: boolean;
	actions?: ReactNode;
	onPointerDown?: ( event: React.PointerEvent< HTMLElement > ) => void;
}

export function Card( { item, number, variant, onOpen, showPage, anchorState, selected, dragging, actions, onPointerDown }: CardProps ) {
	const isPage = item.pin.type === 'page';
	const label = `${ isPage ? 'Page comment' : `Comment ${ number }` }: ${ item.title }. ${ STATUS_LABEL[ item.status ] }${ item.unread ? '. New activity' : '' }`;
	return (
		<article
			className={ [
				'mnafb-card',
				`mnafb-card--${ variant }`,
				`is-${ item.status }`,
				item.unread ? 'is-unread' : '',
				selected ? 'is-selected' : '',
				dragging ? 'is-dragging' : '',
			].join( ' ' ) }
			data-card={ item.id }
			onMouseEnter={ () => variant === 'panel' && setState( { highlightId: item.id } ) }
			onMouseLeave={ () => variant === 'panel' && setState( { highlightId: null } ) }
			onPointerDown={ onPointerDown }
		>
			<button type="button" className="mnafb-card__open" aria-label={ label } onClick={ onOpen } onFocus={ () => variant === 'panel' && setState( { highlightId: item.id } ) } onBlur={ () => variant === 'panel' && setState( { highlightId: null } ) } />
			<div className="mnafb-card__row">
				<span className={ `mnafb-num mnafb-num--${ item.status }` } aria-hidden="true">
					{ isPage ? <Icon name="page" size={ 13 } /> : item.status === 'done' ? <Icon name="check" size={ 13 } /> : number }
				</span>
				<div className="mnafb-card__main">
					{ showPage && (
						<div className="mnafb-card__page" title={ item.page.url }>
							{ item.page.title || pagePath( item.page.url ) }
						</div>
					) }
					<h3 className="mnafb-card__title">{ item.title }</h3>
					{ variant === 'panel' && item.body && <p className="mnafb-card__excerpt">{ item.body }</p> }
				</div>
				{ item.unread && <span className="mnafb-dot" title="New activity" /> }
				{ actions && <div className="mnafb-card__actions">{ actions }</div> }
			</div>
			<div className="mnafb-card__meta">
				<Avatar person={ item.author } size={ 20 } />
				<span className="mnafb-card__who">{ item.author?.name || 'Someone' }</span>
				<time dateTime={ item.created_at } title={ fullDate( item.created_at ) }>
					{ timeAgo( item.created_at ) }
				</time>
				{ variant === 'panel' && item.status !== 'open' && <StatusChip status={ item.status } /> }
				<PriorityChip priority={ item.priority } />
				{ anchorState === 'unavailable' && (
					<span className="mnafb-chip mnafb-chip--warn" title="Original element unavailable">
						<Icon name="warning" size={ 12 } /> Element changed
					</span>
				) }
				{ anchorState === 'hidden' && (
					<span className="mnafb-chip mnafb-chip--muted" title="Not visible at this screen size">
						<Icon name="eyeOff" size={ 12 } /> Hidden here
					</span>
				) }
				<span className="mnafb-spacer" />
				{ item.counts.replies > 0 && (
					<span className="mnafb-count" title={ `${ item.counts.replies } ${ item.counts.replies === 1 ? 'reply' : 'replies' }` }>
						<Icon name="reply" size={ 13 } />
						{ item.counts.replies }
					</span>
				) }
				{ item.counts.attachments > 0 && (
					<span className="mnafb-count" title={ `${ item.counts.attachments } screenshot${ item.counts.attachments === 1 ? '' : 's' }` }>
						<Icon name="image" size={ 13 } />
						{ item.counts.attachments }
					</span>
				) }
				{ item.assignee && (
					<span className="mnafb-assignee" title={ `Assigned to ${ item.assignee.name }` }>
						<Avatar person={ item.assignee } size={ 20 } />
					</span>
				) }
			</div>
		</article>
	);
}
