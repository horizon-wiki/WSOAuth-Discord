<?php

namespace WSOAuthDiscord;

use MediaWiki\Output\OutputPage;
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

	/**
	 * Adds the same-window login script for logged-out visitors when $wgWSOAuthDiscordSameWindowLogin is on.
	 *
	 * @param OutputPage $out
	 * @param \Skin $skin
	 */
	public static function onBeforePageDisplay( $out, $skin ): void {
		if ( self::wantsSameWindowLogin(
			(bool)$out->getConfig()->get( 'WSOAuthDiscordSameWindowLogin' ),
			$out->getUser()->isRegistered()
		) ) {
			$out->addModules( 'ext.wsoauthDiscord.sameWindow' );
		}
	}

	/**
	 * @param bool $enabled $wgWSOAuthDiscordSameWindowLogin
	 * @param bool $isRegistered whether the visitor is logged in
	 * @return bool
	 */
	public static function wantsSameWindowLogin( bool $enabled, bool $isRegistered ): bool {
		return $enabled && !$isRegistered;
	}
}
