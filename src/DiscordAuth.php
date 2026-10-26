<?php

namespace WSOAuthDiscord;

use League\OAuth2\Client\Provider\GenericProvider;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use RuntimeException;
use Throwable;
use WSOAuth\AuthenticationProvider\AuthProvider;

/**
 * Logs users in with Discord's documented OAuth2 API. The account is keyed on the Discord user ID ("name"); the wiki
 * account name for a first login travels in "realname", for WSOAuth's useRealNameAsUsername.
 */
class DiscordAuth extends AuthProvider {

	private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
	private const TOKEN_URL = 'https://discord.com/api/v10/oauth2/token';
	private const USER_URL = 'https://discord.com/api/v10/users/@me';

	private GenericProvider $provider;
	private bool $adoptByStoredHandle;

	public function __construct(
		string $clientId,
		string $clientSecret,
		?string $authUri,
		?string $redirectUri,
		array $extensionData = []
	) {
		// GenericProvider has no default scopes, so nothing beyond "identify" can ever be asked for.
		$this->provider = new GenericProvider( [
			'clientId' => $clientId,
			'clientSecret' => $clientSecret,
			'redirectUri' => $redirectUri,
			'urlAuthorize' => self::AUTHORIZE_URL,
			'urlAccessToken' => $extensionData['tokenUrl'] ?? self::TOKEN_URL,
			'urlResourceOwnerDetails' => self::USER_URL,
			'scopes' => [ 'identify' ],
			'scopeSeparator' => ' ',
			'timeout' => 10,
		] );
		$this->adoptByStoredHandle = !empty( $extensionData['adoptByStoredHandle'] );
	}

	public function login( ?string &$key, ?string &$secret, ?string &$authUrl ): bool {
		// prompt=none: someone who has already authorised the app goes straight back, with no consent screen.
		$authUrl = $this->provider->getAuthorizationUrl( [ 'prompt' => 'none' ] );
		$secret = $this->provider->getState();
		return true;
	}

	public function logout( UserIdentity &$user ): void {
	}

	/**
	 * @return array|false [ 'name' => Discord ID, 'realname' => wiki account name ], or false with $errorMessage set
	 */
	public function getUser( string $key, string $secret, &$errorMessage ) {
		$request = RequestContext::getMain()->getRequest();
		if ( $request->getVal( 'error' ) !== null ) {
			$errorMessage = wfMessage( 'wsoauth-discord-cancelled' )->text();
			return false;
		}
		$state = (string)$request->getVal( 'state', '' );
		if ( $secret === '' || !hash_equals( $secret, $state ) ) {
			$errorMessage = wfMessage( 'wsoauth-discord-bad-state' )->text();
			return false;
		}
		$code = $request->getVal( 'code' );
		if ( $code === null || $code === '' ) {
			$errorMessage = wfMessage( 'wsoauth-discord-failed' )->text();
			return false;
		}

		try {
			$token = $this->provider->getAccessToken( 'authorization_code', [ 'code' => $code ] );
			$discordUser = $this->provider->getResourceOwner( $token )->toArray();
		} catch ( Throwable $e ) {
			$this->logger->warning( 'Discord request failed: {message}', [ 'message' => $e->getMessage() ] );
			$errorMessage = wfMessage( 'wsoauth-discord-unreachable' )->text();
			return false;
		}

		// GenericProvider reacts only to an "error" field, not to the HTTP status, so check the reply itself.
		$id = (string)( $discordUser['id'] ?? '' );
		$username = (string)( $discordUser['username'] ?? '' );
		if ( !ctype_digit( $id ) || $username === '' ) {
			$errorMessage = wfMessage( 'wsoauth-discord-failed' )->text();
			return false;
		}

		// Already bound: WSOAuth logs straight into that account and ignores the name, so don't compute one (a
		// username that makes no valid name must not lock out an account that is already bound).
		$lookups = new MediaWikiLookups();
		$boundTo = $lookups->userForRemoteId( $id );
		if ( $boundTo !== 0 ) {
			$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $boundTo );
			return [ 'name' => $id, 'realname' => $user->getName() ];
		}

		$name = MediaWikiServices::getInstance()->getUserNameUtils()->getCanonical(
			AccountName::fromDiscord( $username ),
			UserNameUtils::RIGOR_CREATABLE
		);
		if ( $name === false ) {
			$errorMessage = wfMessage( 'wsoauth-discord-bad-name' )->text();
			return false;
		}

		try {
			$matcher = new AccountMatcher( $lookups, $lookups, $this->adoptByStoredHandle ? $lookups : null );
			$name = $matcher->choose( $id, $username, $name );
		} catch ( RuntimeException $e ) {
			$this->logger->warning( 'No free account name: {message}', [ 'message' => $e->getMessage() ] );
			$errorMessage = wfMessage( 'wsoauth-discord-bad-name' )->text();
			return false;
		}

		return [ 'name' => $id, 'realname' => $name ];
	}

	/**
	 * Runs when WSOAuth binds an account to a Discord ID: on adoption, and after a new account is created.
	 */
	public function saveExtraAttributes( int $id ): void {
		if ( $this->adoptByStoredHandle ) {
			( new MediaWikiLookups() )->clearStoredHandle( $id );
		}
	}
}
