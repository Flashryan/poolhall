/**
 * What only the browser knows about the device a comment is left on: screen
 * size, pixel ratio and touch support, plus Client Hints where the browser
 * offers them (the real Android version and phone model, Windows 11 rather
 * than 10). Sent with new comments and replies; the server combines it with
 * the browser's user-agent string to work out phone, tablet or desktop.
 */

interface UserAgentData {
	mobile?: boolean;
	platform?: string;
	getHighEntropyValues?: ( hints: string[] ) => Promise< { platformVersion?: string; model?: string } >;
}

function userAgentData(): UserAgentData | undefined {
	return ( navigator as Navigator & { userAgentData?: UserAgentData } ).userAgentData;
}

let precise: { platformVersion?: string; model?: string } = {};

/** Asks Chromium-based browsers once for the exact system version and model. */
export function prepareDeviceHints(): void {
	const data = userAgentData();
	if ( ! data?.getHighEntropyValues ) {
		return;
	}
	data.getHighEntropyValues( [ 'platformVersion', 'model' ] )
		.then( ( values ) => {
			precise = { platformVersion: values.platformVersion || undefined, model: values.model || undefined };
		} )
		.catch( () => {
			/* Not available: the user-agent string is used instead. */
		} );
}

export function deviceHints(): Record< string, unknown > {
	const data = userAgentData();
	return {
		screen: { w: window.screen?.width || 0, h: window.screen?.height || 0 },
		viewport: { w: window.innerWidth, h: window.innerHeight },
		dpr: window.devicePixelRatio || 1,
		touch: navigator.maxTouchPoints || 0,
		platform: data?.platform || undefined,
		mobile: data?.mobile,
		...precise,
	};
}
