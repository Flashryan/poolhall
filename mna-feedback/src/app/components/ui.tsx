/** Small shared building blocks. */

import { Fragment, useEffect, useId, useLayoutEffect, useRef, useState, type ReactNode, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { api } from '../actions';
import type { Person, Priority, Status } from '../types';
import { PRIORITY_LABEL, STATUS_LABEL } from '../util/format';
import { Icon, type IconName } from './Icons';

export function Avatar( { person, size = 24 }: { person: Person | null; size?: number } ) {
	if ( ! person ) {
		return <span className="mnafb-avatar mnafb-avatar--empty" style={ { width: size, height: size } } aria-hidden="true" />;
	}
	return (
		<span
			className="mnafb-avatar"
			style={ { width: size, height: size, background: person.color, fontSize: Math.max( 9, Math.round( size * 0.42 ) ) } }
			title={ person.name }
			aria-hidden="true"
		>
			{ person.initials }
		</span>
	);
}

export function StatusChip( { status }: { status: Status } ) {
	return <span className={ `mnafb-chip mnafb-chip--${ status }` }>{ STATUS_LABEL[ status ] }</span>;
}

export function PriorityChip( { priority, always = false }: { priority: Priority; always?: boolean } ) {
	if ( ! always && ( priority === 'normal' || priority === 'low' ) ) {
		return null;
	}
	return <span className={ `mnafb-chip mnafb-chip--p-${ priority }` }>{ PRIORITY_LABEL[ priority ] }</span>;
}

export function IconButton( {
	icon,
	label,
	onClick,
	className = '',
	disabled,
	pressed,
	size = 18,
	showLabel = false,
	type = 'button',
}: {
	icon: IconName;
	label: string;
	onClick?: () => void;
	className?: string;
	disabled?: boolean;
	pressed?: boolean;
	size?: number;
	showLabel?: boolean;
	type?: 'button' | 'submit';
} ) {
	return (
		<button
			type={ type }
			className={ `mnafb-btn mnafb-btn--icon ${ showLabel ? 'mnafb-btn--with-label' : '' } ${ className }` }
			onClick={ onClick }
			disabled={ disabled }
			aria-label={ showLabel ? undefined : label }
			aria-pressed={ pressed }
			title={ label }
		>
			<Icon name={ icon } size={ size } />
			{ showLabel && <span>{ label }</span> }
		</button>
	);
}

const URL_PATTERN = /(https?:\/\/[^\s<>"')\]]+[^\s<>"')\].,;:!?])/g;

/** Plain text with line breaks kept and links made clickable. */
export function Text( { text, className = 'mnafb-text' }: { text: string; className?: string } ) {
	const parts = text.split( URL_PATTERN );
	return (
		<div className={ className }>
			{ parts.map( ( part, index ) =>
				index % 2 === 1 ? (
					<a key={ index } href={ part } target="_blank" rel="noopener noreferrer nofollow ugc">
						{ part }
					</a>
				) : (
					<Fragment key={ index }>{ part }</Fragment>
				)
			) }
		</div>
	);
}

/** A private screenshot, loaded through the API with the right credentials. */
export function AuthImg( { src, alt, className, onOpen }: { src: string; alt: string; className?: string; onOpen?: ( url: string ) => void } ) {
	const [ url, setUrl ] = useState< string | null >( null );
	const [ failed, setFailed ] = useState( false );
	useEffect( () => {
		const controller = new AbortController();
		let objectUrl: string | null = null;
		setFailed( false );
		api.blobUrl( src, controller.signal )
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
	}, [ src ] );
	if ( failed ) {
		return (
			<span className={ `mnafb-img mnafb-img--failed ${ className || '' }` } role="img" aria-label={ alt }>
				<Icon name="image" />
			</span>
		);
	}
	if ( ! url ) {
		return <span className={ `mnafb-img mnafb-img--loading ${ className || '' }` } aria-hidden="true" />;
	}
	const img = <img className={ `mnafb-img ${ className || '' }` } src={ url } alt={ alt } loading="lazy" decoding="async" />;
	return onOpen ? (
		<button type="button" className="mnafb-img-btn" onClick={ () => onOpen( src ) } aria-label={ `Open ${ alt }` }>
			{ img }
		</button>
	) : (
		img
	);
}

function focusables( node: HTMLElement ): HTMLElement[] {
	return Array.from(
		node.querySelectorAll< HTMLElement >( 'button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])' )
	).filter( ( el ) => el.offsetParent !== null || el === document.activeElement );
}

function activeIn( node: Node ): Element | null {
	const root = node.getRootNode() as Document | ShadowRoot;
	return root.activeElement;
}

/** Keeps keyboard focus inside a container while it is open. */
export function useFocusTrap( ref: React.RefObject< HTMLElement >, active = true, initial?: React.RefObject< HTMLElement > ) {
	useLayoutEffect( () => {
		const node = ref.current;
		if ( ! node || ! active ) {
			return;
		}
		const previous = activeIn( node ) as HTMLElement | null;
		const first = initial?.current || focusables( node )[ 0 ] || node;
		window.requestAnimationFrame( () => first.focus( { preventScroll: true } ) );
		const onKey = ( event: KeyboardEvent ) => {
			if ( event.key !== 'Tab' ) {
				return;
			}
			const list = focusables( node );
			if ( ! list.length ) {
				event.preventDefault();
				return;
			}
			const current = activeIn( node );
			const index = list.indexOf( current as HTMLElement );
			if ( event.shiftKey && ( index <= 0 ) ) {
				event.preventDefault();
				list[ list.length - 1 ].focus();
			} else if ( ! event.shiftKey && index === list.length - 1 ) {
				event.preventDefault();
				list[ 0 ].focus();
			}
		};
		node.addEventListener( 'keydown', onKey );
		return () => {
			node.removeEventListener( 'keydown', onKey );
			if ( previous && typeof previous.focus === 'function' && previous.isConnected ) {
				previous.focus( { preventScroll: true } );
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ active ] );
}

export function Dialog( {
	title,
	onClose,
	children,
	footer,
	size = 'md',
	initialFocus,
	dismissible = true,
}: {
	title: string;
	onClose: () => void;
	children: ReactNode;
	footer?: ReactNode;
	size?: 'sm' | 'md' | 'lg';
	initialFocus?: React.RefObject< HTMLElement >;
	dismissible?: boolean;
} ) {
	const ref = useRef< HTMLDivElement >( null );
	const titleId = useId();
	useFocusTrap( ref, true, initialFocus );
	return (
		<div className="mnafb-modal" onMouseDown={ ( e ) => dismissible && e.target === e.currentTarget && onClose() }>
			<div
				ref={ ref }
				className={ `mnafb-dialog mnafb-dialog--${ size }` }
				role="dialog"
				aria-modal="true"
				aria-labelledby={ titleId }
				tabIndex={ -1 }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Escape' && dismissible ) {
						e.stopPropagation();
						onClose();
					}
				} }
			>
				<header className="mnafb-dialog__head">
					<h2 id={ titleId }>{ title }</h2>
					{ dismissible && <IconButton icon="close" label="Close" onClick={ onClose } /> }
				</header>
				<div className="mnafb-dialog__body">{ children }</div>
				{ footer && <footer className="mnafb-dialog__foot">{ footer }</footer> }
			</div>
		</div>
	);
}

/** Open menus, so Escape closes the most recent one before anything else. */
const openMenus: Array< () => void > = [];

export function closeTopMenu(): boolean {
	const close = openMenus.pop();
	if ( close ) {
		close();
		return true;
	}
	return false;
}

export interface MenuItem {
	label: string;
	icon?: IconName;
	onSelect: () => void;
	danger?: boolean;
	disabled?: boolean;
	checked?: boolean;
}

/** A button that opens a list of actions, operable by keyboard. */
export function Menu( { label, icon = 'more', items, align = 'right', text }: { label: string; icon?: IconName; items: Array< MenuItem | null | false >; align?: 'left' | 'right'; text?: string } ) {
	const [ open, setOpen ] = useState( false );
	const [ position, setPosition ] = useState< React.CSSProperties >( {} );
	const wrap = useRef< HTMLDivElement >( null );
	const button = useRef< HTMLButtonElement >( null );
	const list = useRef< HTMLDivElement >( null );
	const menuId = useId();
	const entries = items.filter( Boolean ) as MenuItem[];

	const toggle = () => {
		if ( open ) {
			setOpen( false );
			return;
		}
		// The list is position: fixed so scrolling containers cannot clip it.
		const rect = button.current?.getBoundingClientRect();
		if ( rect ) {
			const below = window.innerHeight - rect.bottom;
			const upward = below < entries.length * 40 + 24 && rect.top > below;
			const style: React.CSSProperties = upward ? { bottom: window.innerHeight - rect.top + 4 } : { top: rect.bottom + 4 };
			if ( align === 'right' ) {
				style.right = Math.max( 8, window.innerWidth - rect.right );
			} else {
				style.left = Math.max( 8, rect.left );
			}
			setPosition( style );
		}
		setOpen( true );
	};

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		const node = wrap.current;
		if ( ! node ) {
			return;
		}
		const close = () => setOpen( false );
		openMenus.push( close );
		const root = node.getRootNode() as ShadowRoot | Document;
		const onDown = ( event: Event ) => {
			if ( ! event.composedPath().includes( node ) ) {
				setOpen( false );
			}
		};
		const onMove = ( event: Event ) => {
			if ( ! list.current || ! event.composedPath().includes( list.current ) ) {
				setOpen( false );
			}
		};
		root.addEventListener( 'pointerdown', onDown, true );
		document.addEventListener( 'pointerdown', onDown, true );
		window.addEventListener( 'scroll', onMove, true );
		window.addEventListener( 'resize', onMove );
		window.requestAnimationFrame( () => list.current?.querySelector< HTMLElement >( '[role^="menuitem"]:not([disabled])' )?.focus() );
		return () => {
			const index = openMenus.indexOf( close );
			if ( index >= 0 ) {
				openMenus.splice( index, 1 );
			}
			root.removeEventListener( 'pointerdown', onDown, true );
			document.removeEventListener( 'pointerdown', onDown, true );
			window.removeEventListener( 'scroll', onMove, true );
			window.removeEventListener( 'resize', onMove );
		};
	}, [ open ] );

	const onKeyDown = ( event: ReactKeyboardEvent ) => {
		const options = Array.from( list.current?.querySelectorAll< HTMLElement >( '[role^="menuitem"]:not([disabled])' ) || [] );
		const current = options.indexOf( activeIn( list.current as Node ) as HTMLElement );
		if ( event.key === 'ArrowDown' ) {
			event.preventDefault();
			options[ ( current + 1 ) % options.length ]?.focus();
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			options[ ( current - 1 + options.length ) % options.length ]?.focus();
		} else if ( event.key === 'Home' ) {
			event.preventDefault();
			options[ 0 ]?.focus();
		} else if ( event.key === 'End' ) {
			event.preventDefault();
			options[ options.length - 1 ]?.focus();
		} else if ( event.key === 'Escape' ) {
			event.preventDefault();
			event.stopPropagation();
			setOpen( false );
			button.current?.focus();
		} else if ( event.key === 'Tab' ) {
			setOpen( false );
		}
	};

	if ( ! entries.length ) {
		return null;
	}

	return (
		<div className="mnafb-menu" ref={ wrap }>
			<button
				ref={ button }
				type="button"
				className={ `mnafb-btn ${ text ? 'mnafb-btn--ghost mnafb-btn--sm' : 'mnafb-btn--icon' }` }
				aria-haspopup="menu"
				aria-expanded={ open }
				aria-controls={ open ? menuId : undefined }
				aria-label={ text ? undefined : label }
				title={ label }
				onClick={ ( e ) => {
					e.stopPropagation();
					toggle();
				} }
			>
				{ text ? (
					<>
						<span>{ text }</span>
						<Icon name="chevronDown" size={ 14 } />
					</>
				) : (
					<Icon name={ icon } />
				) }
			</button>
			{ open && (
				<div ref={ list } id={ menuId } className="mnafb-menu__list" style={ position } role="menu" aria-label={ label } onKeyDown={ onKeyDown }>
					{ entries.map( ( entry ) => (
						<button
							key={ entry.label }
							type="button"
							role={ entry.checked === undefined ? 'menuitem' : 'menuitemcheckbox' }
							aria-checked={ entry.checked }
							className={ `mnafb-menu__item ${ entry.danger ? 'is-danger' : '' }` }
							disabled={ entry.disabled }
							onClick={ ( e ) => {
								e.stopPropagation();
								setOpen( false );
								entry.onSelect();
							} }
						>
							{ entry.icon && <Icon name={ entry.icon } size={ 16 } /> }
							<span>{ entry.label }</span>
							{ entry.checked && <Icon name="check" size={ 14 } className="mnafb-menu__check" /> }
						</button>
					) ) }
				</div>
			) }
		</div>
	);
}

export function Spinner( { label = 'Loading' }: { label?: string } ) {
	return <span className="mnafb-spinner" role="status" aria-label={ label } />;
}

/** Textarea that grows with its content. */
export function AutoTextarea( props: React.TextareaHTMLAttributes< HTMLTextAreaElement > & { minRows?: number; innerRef?: React.Ref< HTMLTextAreaElement > } ) {
	const { minRows = 2, innerRef, ...rest } = props;
	const local = useRef< HTMLTextAreaElement | null >( null );
	useLayoutEffect( () => {
		const el = local.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight + 2, 320 ) + 'px';
	}, [ props.value ] );
	return (
		<textarea
			{ ...rest }
			rows={ minRows }
			ref={ ( node ) => {
				local.current = node;
				if ( typeof innerRef === 'function' ) {
					innerRef( node );
				} else if ( innerRef && typeof innerRef === 'object' ) {
					( innerRef as React.MutableRefObject< HTMLTextAreaElement | null > ).current = node;
				}
			} }
		/>
	);
}

export function copyText( text: string ): Promise< boolean > {
	if ( navigator.clipboard && window.isSecureContext ) {
		return navigator.clipboard.writeText( text ).then(
			() => true,
			() => false
		);
	}
	return Promise.resolve( false );
}
