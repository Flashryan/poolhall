import type { ReactElement } from 'react';

const PATHS: Record< string, ReactElement > = {
	close: <path d="M18 6 6 18M6 6l12 12" />,
	chevronLeft: <path d="m15 18-6-6 6-6" />,
	chevronRight: <path d="m9 18 6-6-6-6" />,
	chevronDown: <path d="m6 9 6 6 6-6" />,
	more: (
		<g fill="currentColor" stroke="none">
			<circle cx="5" cy="12" r="1.8" />
			<circle cx="12" cy="12" r="1.8" />
			<circle cx="19" cy="12" r="1.8" />
		</g>
	),
	board: (
		<g>
			<rect x="3" y="4" width="18" height="16" rx="2" />
			<path d="M9 4v16M15 4v16" />
		</g>
	),
	message: <path d="M21 14.5a2 2 0 0 1-2 2H8l-5 4V5.5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />,
	crosshair: (
		<g>
			<circle cx="12" cy="12" r="7.5" />
			<path d="M12 2v4M12 18v4M2 12h4M18 12h4" />
		</g>
	),
	cursor: <path d="m4 4 6.5 16 2.3-6.7L19.5 11z" />,
	plus: <path d="M12 5v14M5 12h14" />,
	eye: (
		<g>
			<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" />
			<circle cx="12" cy="12" r="3" />
		</g>
	),
	eyeOff: (
		<g>
			<path d="M3 3l18 18" />
			<path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.1 4.1M6.6 6.6C3.9 8.4 2 12 2 12s3.6 7 10 7a9.8 9.8 0 0 0 5.4-1.6" />
			<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
		</g>
	),
	image: (
		<g>
			<rect x="3" y="3" width="18" height="18" rx="2" />
			<circle cx="9" cy="9" r="2" />
			<path d="m21 15-5-5L5 21" />
		</g>
	),
	trash: <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6" />,
	restore: <path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5" />,
	check: <path d="M20 6 9 17l-5-5" />,
	external: <path d="M14 3h7v7M10 14 21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" />,
	locate: (
		<g>
			<circle cx="12" cy="12" r="8" />
			<circle cx="12" cy="12" r="3" />
		</g>
	),
	user: (
		<g>
			<circle cx="12" cy="8" r="4" />
			<path d="M4 21a8 8 0 0 1 16 0" />
		</g>
	),
	link: <path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7" />,
	logout: <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />,
	search: (
		<g>
			<circle cx="11" cy="11" r="7" />
			<path d="m21 21-4.3-4.3" />
		</g>
	),
	edit: <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" />,
	reply: <path d="M9 17 4 12l5-5M20 18v-2a4 4 0 0 0-4-4H4" />,
	clip: <path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5" />,
	warning: <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01" />,
	clock: (
		<g>
			<circle cx="12" cy="12" r="9" />
			<path d="M12 7v5l3 2" />
		</g>
	),
	filter: <path d="M3 5h18l-7 8v6l-4 2v-8z" />,
	send: <path d="M22 2 11 13M22 2l-7 20-4-9-9-4z" />,
	wrench: <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.1-.5-.5-2.1z" />,
	page: <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h5" />,
	expand: <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />,
	move: <path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20" />,
	arrowUp: <path d="M12 19V5M5 12l7-7 7 7" />,
	arrowDown: <path d="M12 5v14M19 12l-7 7-7-7" />,
	settings: (
		<g>
			<circle cx="12" cy="12" r="3" />
			<path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />
		</g>
	),
	offline: <path d="M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 4.2-2.7M10.7 5.1A15 15 0 0 1 22 8.8M5 12.9a10 10 0 0 1 5.2-2.8M16.9 12.2a10 10 0 0 1 2.1.7M12 20h.01" />,
	// Device types, named to match DeviceInfo.type.
	phone: (
		<g>
			<rect x="6.5" y="2" width="11" height="20" rx="2.2" />
			<path d="M11 18h2" />
		</g>
	),
	tablet: (
		<g>
			<rect x="4" y="2.5" width="16" height="19" rx="2.2" />
			<path d="M11 18h2" />
		</g>
	),
	desktop: (
		<g>
			<rect x="2.5" y="3.5" width="19" height="13" rx="2" />
			<path d="M8 20.5h8M12 16.5v4" />
		</g>
	),
};

export type IconName = keyof typeof PATHS;

export function Icon( { name, size = 18, className }: { name: IconName; size?: number; className?: string } ) {
	return (
		<svg
			className={ 'mnafb-icon' + ( className ? ' ' + className : '' ) }
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth={ 1.9 }
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			{ PATHS[ name ] }
		</svg>
	);
}
