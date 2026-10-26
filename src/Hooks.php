<?php

namespace WSOAuthDiscord;

use MediaWiki\SpecialPage\SpecialPage;
use WSOAuth\WSOAuth;

class Hooks {

	/**
	 * Registers the provider as type "discord" for $wgPluggableAuth_Config.
	 *
	 * @param array &$providers type => class
	 */
	public static function onWSOAuthGetAuthProviders( array &$providers ): void {
		$providers['discord'] = DiscordAuth::class;
	}

	/**
	 * A fresh login starts clean. WSOAuth tells its two legs apart only by its keys in the session, and only a
	 * completed return from Discord removes them; after an abandoned login (Back on Discord, a closed tab) the next
	 * attempt would be taken for a return, and fail once.
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage
	 */
	public static function onSpecialPageBeforeExecute( $special, $subPage ): void {
		if ( $special->getName() !== 'Userlogin' || $subPage === 'return' ) {
			return;
		}
		$session = $special->getRequest()->getSession();
		$session->remove( WSOAuth::WSOAUTH_OAUTH_REQUEST_KEY_SESSION_KEY );
		$session->remove( WSOAuth::WSOAUTH_OAUTH_REQUEST_SECRET_SESSION_KEY );
	}
}
