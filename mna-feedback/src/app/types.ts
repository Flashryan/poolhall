/** Shapes returned by the mna-feedback/v1 REST API. */

export type Status = 'open' | 'in_progress' | 'done';
export type Priority = 'low' | 'normal' | 'high' | 'urgent';
export type Role = 'reviewer' | 'implementer' | 'manager' | 'former';

export const STATUSES: Status[] = [ 'open', 'in_progress', 'done' ];
export const PRIORITIES: Priority[] = [ 'low', 'normal', 'high', 'urgent' ];

export interface Person {
	id: number;
	name: string;
	initials: string;
	color: string;
	type: 'guest' | 'wp_user';
	role: Role;
	blocked: boolean;
}

export interface Me extends Person {
	email: string;
	caps: { implement: boolean; manage: boolean };
}

export interface Branding {
	name: string;
	logo: string;
	accent: string;
	poll_interval: number;
	page_comments: boolean;
	guest_uploads: boolean;
	max_upload: number;
}

export interface SessionData {
	authenticated: true;
	me: Me;
	csrf: string | null;
	branding: Branding;
	page: { key: string; url: string; title: string } | null;
	link: { label: string; expires_at: string | null } | null;
	joined_at: string | null;
	server_time: string;
	admin_url: string | null;
	wp_login: boolean;
	return_link?: string;
}

export interface Attachment {
	id: number;
	mime: string;
	width: number;
	height: number;
	size: number;
	uploader_id: number;
	url: string;
	thumb_url: string;
	created_at: string;
}

/** Element anchor recorded when a pin is placed (version 1). */
export interface Anchor {
	v: 1;
	elementor?: { id: string; nth: number; loop?: string; path?: string; type?: string };
	htmlId?: { id: string; path?: string };
	post?: { id: string; path?: string };
	css: string;
	tag: string;
	text?: string;
	attrs?: Record< string, string >;
	label?: string;
	rect?: { x: number; y: number; w: number; h: number; dw: number; dh: number };
}

export interface ItemContext {
	selection?: string;
	scroll?: { x: number; y: number };
	ua?: string;
	dpr?: number;
}

export interface ItemCan {
	edit: boolean;
	delete: boolean;
	priority: boolean;
	assign: boolean;
	reorder: boolean;
	statuses: Status[];
	reply: boolean;
	note: boolean;
	attach: boolean;
	restore: boolean;
	purge: boolean;
}

export interface Item {
	id: number;
	uuid: string;
	title: string;
	body: string;
	status: Status;
	priority: Priority;
	author: Person | null;
	assignee: Person | null;
	page: { url: string; key: string; title: string };
	pin: { type: 'element' | 'page'; x: number; y: number; anchor: Anchor | null };
	viewport: { w: number; h: number };
	context: ItemContext | null;
	counts: { replies: number; attachments: number };
	attachments: Attachment[];
	unread: boolean;
	order: number;
	revision: number;
	activity_rev: number;
	created_at: string;
	updated_at: string;
	edited_at: string | null;
	trashed: boolean;
	trashed_at: string | null;
	can: ItemCan;
}

export interface Reply {
	id: number;
	item_id: number;
	kind: 'reply' | 'note';
	body: string;
	author: Person | null;
	revision: number;
	created_at: string;
	edited_at: string | null;
	trashed: boolean;
	attachments: Attachment[];
	can: { edit: boolean; delete: boolean; restore: boolean; purge: boolean };
}

export interface ActivityEntry {
	id: number;
	action: string;
	source: 'ui' | 'agent' | 'system';
	actor: Person | null;
	data: Record< string, unknown > & { from_person?: Person | null; to_person?: Person | null };
	created_at: string;
}

export interface ItemDetail extends Item {
	replies: Reply[];
	activity: ActivityEntry[];
}

export interface PageSummary {
	key: string;
	url: string;
	title: string;
	open: number;
	in_progress: number;
	done: number;
}

/** GET /session when there is no session yet (or any more). */
export interface SessionGate {
	authenticated: false;
	wp_login?: boolean;
	no_role?: boolean;
	ended?: boolean;
	join?: { state: 'required' | 'invalid'; label?: string; reason?: string; message?: string };
	branding?: Branding;
}

export interface ApiErrorShape {
	code: string;
	message: string;
	status: number;
	data: Record< string, unknown >;
}

export interface SyncResponse {
	reset: boolean;
	items: Item[];
	removed: number[];
	server_time: string;
}
