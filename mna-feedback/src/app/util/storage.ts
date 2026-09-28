/**
 * Names for browser storage. Sites on a multisite network can share an origin,
 * so each site's drafts, preferences and nonce are kept under its own names.
 */
export function storageKey( name: string ): string {
	const site = window.mnafbBoot?.site;
	return site ? `mnafb:${ site }:${ name }` : `mnafb:${ name }`;
}

/** Removes the review-mode flag so the interface does not load on the next page. */
export function clearFlagCookie(): void {
	const boot = window.mnafbBoot;
	const name = boot?.flag || 'mnafb_on';
	document.cookie = `${ name }=; Max-Age=0; path=${ boot?.cookiePath || '/' }`;
}
