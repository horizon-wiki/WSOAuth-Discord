// Runs modules/ext.wsoauthDiscord.sameWindow.js against a stand-in for MediaWiki, jQuery and the page.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync( new URL( '../../modules/ext.wsoauthDiscord.sameWindow.js', import.meta.url ), 'utf8' );

function load( { userName = null, pageName = 'Some_page', search = '', selector = '#discord-login', tokenFails = false } = {} ) {
	const page = { buttonHandlers: [], linkHandlers: [], posts: [], href: null };
	const location = {
		search,
		get href() { return 'https://wiki.example/' + pageName + search; },
		set href( value ) { page.href = value; }
	};
	const document = {};
	const $ = ( arg ) => {
		if ( typeof arg === 'function' ) {
			arg();
			return;
		}
		return {
			on( event, ...rest ) {
				if ( arg === document ) {
					page.linkHandlers.push( { selector: rest[ 0 ], handler: rest[ 1 ] } );
				} else {
					page.buttonHandlers.push( { selector: arg, handler: rest[ 0 ] } );
				}
			}
		};
	};
	const config = { wgUserName: userName, wgPageName: pageName };
	const mw = {
		config: { get: ( key ) => config[ key ] },
		util: {
			getUrl: ( title, params ) => '/' + title + '?' + new URLSearchParams( params ).toString()
		},
		loader: { using: () => Promise.resolve() },
		Api: class {
			post( params ) {
				page.posts.push( params );
				return tokenFails ? Promise.reject( new Error( 'no session' ) ) : Promise.resolve( {} );
			}
		}
	};
	const require = ( name ) => {
		assert.equal( name, './config.json' );
		return { WSOAuthDiscordLoginButtonSelector: selector };
	};
	vm.runInNewContext( source, { mw, $, document, location, require, URL, URLSearchParams } );
	return page;
}

function event() {
	return { prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } };
}

const settle = () => new Promise( ( resolve ) => setImmediate( resolve ) );

test( 'logged-in visitors get no handlers', () => {
	const page = load( { userName: 'Someone' } );
	assert.equal( page.buttonHandlers.length, 0 );
	assert.equal( page.linkHandlers.length, 0 );
} );

test( 'the button logs in from the current page, keeping its query', async () => {
	const page = load( { pageName: 'Some_page', search: '?title=Some_page&action=edit&section=2' } );
	assert.equal( page.buttonHandlers[ 0 ].selector, '#discord-login' );
	const e = event();
	page.buttonHandlers[ 0 ].handler( e );
	await settle();
	assert.ok( e.prevented && e.stopped );
	assert.equal( JSON.stringify( page.posts ), JSON.stringify( [ { action: 'query', meta: 'tokens', type: 'login' } ] ) );
	assert.equal( page.href, '/Special:UserLogin?returnto=Some_page&returntoquery=action%3Dedit%26section%3D2' );
} );

test( 'a page with no query returns to the page only', async () => {
	const page = load( { pageName: 'Main_Page' } );
	page.buttonHandlers[ 0 ].handler( event() );
	await settle();
	assert.equal( page.href, '/Special:UserLogin?returnto=Main_Page' );
} );

test( 'a "Log in" link keeps its own returnto and returntoquery', async () => {
	const page = load();
	assert.match( page.linkHandlers[ 0 ].selector, /Special:UserLogin/ );
	const e = event();
	page.linkHandlers[ 0 ].handler.call(
		{ href: 'https://wiki.example/w/index.php?title=Special:UserLogin&returnto=Other&returntoquery=diff%3D5' },
		e
	);
	await settle();
	assert.ok( e.prevented );
	assert.equal( page.href, '/Special:UserLogin?returnto=Other&returntoquery=diff%3D5' );
} );

test( 'without a session, it returns to the page only', async () => {
	const page = load( { search: '?action=edit', tokenFails: true } );
	page.buttonHandlers[ 0 ].handler( event() );
	await settle();
	assert.equal( page.href, '/Special:UserLogin?returnto=Some_page' );
} );

test( 'with no selector set, only links are handled', () => {
	const page = load( { selector: '' } );
	assert.equal( page.buttonHandlers.length, 0 );
	assert.equal( page.linkHandlers.length, 1 );
} );
