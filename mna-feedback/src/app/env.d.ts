declare module '*.css' {
	const content: string;
	export default content;
}

declare const __MNAFB_VERSION__: string;

interface MnafbBoot {
	rest: string;
	app: string;
	ver: string;
	/** Site ID on a multisite network, 0 on a single site. */
	site?: number;
	/** Name of the review-mode flag cookie. */
	flag?: string;
	cookiePath?: string;
}

interface Window {
	mnafbBoot?: MnafbBoot;
	__mnafbLoaded?: boolean;
}
