declare module '*.css' {
	const content: string;
	export default content;
}

declare const __MNAFB_VERSION__: string;

interface MnafbBoot {
	rest: string;
	app: string;
	ver: string;
}

interface Window {
	mnafbBoot?: MnafbBoot;
	__mnafbLoaded?: boolean;
}
