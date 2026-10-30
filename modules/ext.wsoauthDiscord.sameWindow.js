/**
 * Same-window Discord login. A click on the login button or on any "Log in" link goes to Special:UserLogin with
 * returnto and returntoquery, so the visitor comes back exactly where they were, an edit form included. With
 * PluggableAuth's single button, Special:UserLogin sends the visitor straight on to Discord.
 *
 * A session is started first (an API login-token request), so a server that refuses login URLs from visitors with no
 * session cookie lets the request through.
 */
( function () {
	var config = require( './config.json' );

	if ( mw.config.get( 'wgUserName' ) !== null ) {
		return;
	}

	function here() {
		var query = new URLSearchParams( location.search );
		query.delete( 'title' );
		return { returnto: mw.config.get( 'wgPageName' ), returntoquery: query.toString() };
	}

	function fromLink( href ) {
		var url = new URL( href, location.href );
		return {
			returnto: url.searchParams.get( 'returnto' ) || '',
			returntoquery: url.searchParams.get( 'returntoquery' ) || ''
		};
	}

	function startLogin( target ) {
		var plain = { returnto: target.returnto };
		// POST, so nothing caches the answer.
		mw.loader.using( 'mediawiki.api' ).then( function () {
			return new mw.Api().post( { action: 'query', meta: 'tokens', type: 'login' } );
		} ).then( function () {
			location.href = mw.util.getUrl( 'Special:UserLogin', target.returntoquery ? target : plain );
		}, function () {
			// Without a session the full URL may be refused, so return to the page only.
			location.href = mw.util.getUrl( 'Special:UserLogin', plain );
		} );
	}

	// Bound on the button itself, since a skin's menu may stop the click before it reaches the document.
	if ( config.WSOAuthDiscordLoginButtonSelector ) {
		$( function () {
			$( config.WSOAuthDiscordLoginButtonSelector ).on( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				startLogin( here() );
			} );
		} );
	}

	$( document ).on( 'click', 'a[href*="Special:UserLogin"], a[href*="Special%3AUserLogin"]', function ( e ) {
		e.preventDefault();
		startLogin( fromLink( this.href ) );
	} );
}() );
