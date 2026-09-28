/**
 * Bundles the review interface (React + TypeScript) into assets/app.js.
 *
 *   npm run build   production bundle
 *   npm run watch   rebuild on change, with inline source maps
 */
import { build, context, transform } from 'esbuild';
import { readFileSync } from 'node:fs';
import { readFile } from 'node:fs/promises';

const pkg = JSON.parse( readFileSync( new URL( '../package.json', import.meta.url ) ) );
const watch = process.argv.includes( '--watch' );

// The stylesheet is injected into the Shadow DOM as text; minify it first.
const cssAsText = {
	name: 'css-as-text',
	setup( b ) {
		b.onLoad( { filter: /\.css$/ }, async ( args ) => {
			const source = await readFile( args.path, 'utf8' );
			const result = await transform( source, { loader: 'css', minify: ! watch, target: [ 'chrome80', 'firefox78', 'safari13' ] } );
			return { contents: result.code, loader: 'text' };
		} );
	},
};

const options = {
	entryPoints: [ 'src/app/main.tsx' ],
	outfile: 'assets/app.js',
	bundle: true,
	format: 'iife',
	platform: 'browser',
	target: [ 'es2019', 'chrome80', 'firefox78', 'safari13' ],
	jsx: 'automatic',
	plugins: [ cssAsText ],
	minify: ! watch,
	sourcemap: watch ? 'inline' : false,
	legalComments: 'none',
	charset: 'utf8',
	define: {
		'process.env.NODE_ENV': watch ? '"development"' : '"production"',
		__MNAFB_VERSION__: JSON.stringify( pkg.version ),
	},
	banner: { js: `/*! MNA Feedback ${ pkg.version } | GPL-2.0-or-later | Includes React (MIT) */` },
	logLevel: 'info',
};

if ( watch ) {
	const ctx = await context( options );
	await ctx.watch();
} else {
	await build( options );
}
