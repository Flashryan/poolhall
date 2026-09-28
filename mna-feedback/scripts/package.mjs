/**
 * Builds the installable plugin ZIP: dist/mna-feedback-<version>.zip
 *
 * Contains only what WordPress needs at runtime (PHP, the built interface,
 * admin assets and readme). Source, tests and tooling stay out. No external
 * zip tool is required.
 *
 *   npm run build && npm run package
 */

import { deflateRawSync } from 'node:zlib';
import { mkdirSync, readFileSync, readdirSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath( new URL( '..', import.meta.url ) );
const pkg = JSON.parse( readFileSync( join( root, 'package.json' ), 'utf8' ) );
const slug = 'mna-feedback';

const header = readFileSync( join( root, 'mna-feedback.php' ), 'utf8' );
const phpVersion = /Version:\s*([0-9.]+)/.exec( header )?.[ 1 ];
if ( phpVersion !== pkg.version ) {
	console.error( `Version mismatch: plugin header ${ phpVersion }, package.json ${ pkg.version }` );
	process.exit( 1 );
}
if ( ! existsSync( join( root, 'assets', 'app.js' ) ) ) {
	console.error( 'assets/app.js is missing. Run `npm run build` first.' );
	process.exit( 1 );
}

const include = [ 'mna-feedback.php', 'uninstall.php', 'readme.txt', 'includes', 'assets' ];
const skip = ( path ) => /(^|\/)\.|\.map$|\.DS_Store$/.test( path );

function walk( path ) {
	const full = join( root, path );
	const info = statSync( full );
	if ( info.isDirectory() ) {
		return [ { path, dir: true }, ...readdirSync( full ).sort().flatMap( ( name ) => walk( join( path, name ) ) ) ];
	}
	return [ { path, dir: false } ];
}

const entries = include.flatMap( walk ).filter( ( e ) => ! skip( e.path.split( sep ).join( '/' ) ) );

const CRC_TABLE = new Uint32Array( 256 ).map( ( _, n ) => {
	let c = n;
	for ( let k = 0; k < 8; k++ ) {
		c = c & 1 ? 0xedb88320 ^ ( c >>> 1 ) : c >>> 1;
	}
	return c >>> 0;
} );

function crc32( buffer ) {
	let crc = 0xffffffff;
	for ( let i = 0; i < buffer.length; i++ ) {
		crc = CRC_TABLE[ ( crc ^ buffer[ i ] ) & 0xff ] ^ ( crc >>> 8 );
	}
	return ( crc ^ 0xffffffff ) >>> 0;
}

function dosDateTime( date ) {
	const time = ( date.getHours() << 11 ) | ( date.getMinutes() << 5 ) | Math.floor( date.getSeconds() / 2 );
	const day = ( ( date.getFullYear() - 1980 ) << 9 ) | ( ( date.getMonth() + 1 ) << 5 ) | date.getDate();
	return { time, day };
}

const stamp = dosDateTime( new Date() );
const locals = [];
const centrals = [];
let offset = 0;

for ( const entry of entries ) {
	const name = Buffer.from( `${ slug }/${ entry.path.split( sep ).join( '/' ) }${ entry.dir ? '/' : '' }`, 'utf8' );
	const raw = entry.dir ? Buffer.alloc( 0 ) : readFileSync( join( root, entry.path ) );
	const deflated = entry.dir ? raw : deflateRawSync( raw, { level: 9 } );
	const useDeflate = ! entry.dir && deflated.length < raw.length;
	const data = useDeflate ? deflated : raw;
	const crc = entry.dir ? 0 : crc32( raw );
	const method = useDeflate ? 8 : 0;

	const local = Buffer.alloc( 30 );
	local.writeUInt32LE( 0x04034b50, 0 );
	local.writeUInt16LE( 20, 4 );
	local.writeUInt16LE( 0x0800, 6 ); // UTF-8 names
	local.writeUInt16LE( method, 8 );
	local.writeUInt16LE( stamp.time, 10 );
	local.writeUInt16LE( stamp.day, 12 );
	local.writeUInt32LE( crc, 14 );
	local.writeUInt32LE( data.length, 18 );
	local.writeUInt32LE( raw.length, 22 );
	local.writeUInt16LE( name.length, 26 );
	local.writeUInt16LE( 0, 28 );
	locals.push( local, name, data );

	const central = Buffer.alloc( 46 );
	central.writeUInt32LE( 0x02014b50, 0 );
	central.writeUInt16LE( 0x031e, 4 ); // made by: Unix, spec 3.0
	central.writeUInt16LE( 20, 6 );
	central.writeUInt16LE( 0x0800, 8 );
	central.writeUInt16LE( method, 10 );
	central.writeUInt16LE( stamp.time, 12 );
	central.writeUInt16LE( stamp.day, 14 );
	central.writeUInt32LE( crc, 16 );
	central.writeUInt32LE( data.length, 20 );
	central.writeUInt32LE( raw.length, 24 );
	central.writeUInt16LE( name.length, 28 );
	central.writeUInt16LE( 0, 30 );
	central.writeUInt16LE( 0, 32 );
	central.writeUInt16LE( 0, 34 );
	central.writeUInt16LE( 0, 36 );
	central.writeUInt32LE( ( ( entry.dir ? 0o40755 : 0o100644 ) << 16 ) | ( entry.dir ? 0x10 : 0 ), 38 );
	central.writeUInt32LE( offset, 42 );
	centrals.push( central, name );

	offset += local.length + name.length + data.length;
}

const centralSize = centrals.reduce( ( total, b ) => total + b.length, 0 );
const end = Buffer.alloc( 22 );
end.writeUInt32LE( 0x06054b50, 0 );
end.writeUInt16LE( 0, 4 );
end.writeUInt16LE( 0, 6 );
end.writeUInt16LE( entries.length, 8 );
end.writeUInt16LE( entries.length, 10 );
end.writeUInt32LE( centralSize, 12 );
end.writeUInt32LE( offset, 16 );
end.writeUInt16LE( 0, 20 );

mkdirSync( join( root, 'dist' ), { recursive: true } );
const out = join( root, 'dist', `${ slug }-${ pkg.version }.zip` );
writeFileSync( out, Buffer.concat( [ ...locals, ...centrals, end ] ) );
console.log( `Wrote ${ relative( root, out ) } (${ entries.filter( ( e ) => ! e.dir ).length } files, ${ Math.round( ( offset + centralSize + 22 ) / 1024 ) } KB)` );
