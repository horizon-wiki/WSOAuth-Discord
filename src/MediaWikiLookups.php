<?php

namespace WSOAuthDiscord;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserNameUtils;

/**
 * The matcher's lookups against the wiki's database. Reads go to the primary, as WSOAuth's own mapping lookup does,
 * so a binding written a moment ago is seen.
 */
class MediaWikiLookups implements NameLookup, MappingLookup, HandleLookup {

	private const MAPPINGS = 'wsoauth_multiauth_mappings';

	public function idFor( string $name ): int {
		$user = MediaWikiServices::getInstance()->getUserIdentityLookup()->getUserIdentityByName( $name );
		return $user ? $user->getId() : 0;
	}

	public function remoteIdsFor( int $userId ): array {
		return $this->db()->newSelectQueryBuilder()
			->select( 'wsoauth_remote_name' )
			->from( self::MAPPINGS )
			->where( [ 'wsoauth_user' => $userId ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/** The account bound to this remote ID, or 0. */
	public function userForRemoteId( string $remoteId ): int {
		return (int)$this->db()->newSelectQueryBuilder()
			->select( 'wsoauth_user' )
			->from( self::MAPPINGS )
			->where( [ 'wsoauth_remote_name' => $remoteId ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	public function accountForHandle( string $username ): ?string {
		$dbw = $this->db();
		$names = $dbw->newSelectQueryBuilder()
			->select( 'user_name' )
			->from( 'user' )
			->leftJoin( self::MAPPINGS, null, 'wsoauth_user = user_id' )
			->where( [ 'user_real_name' => $username . '#0', 'wsoauth_user' => null ] )
			// Not user_touched: any bulk change to the accounts resets it, so it says nothing about who was active.
			->orderBy( [ 'user_editcount', 'user_id' ], 'DESC' )
			->caller( __METHOD__ )
			->fetchFieldValues();
		// The 2023 ghost accounts were stored under names MediaWiki never looks up; skip them.
		$utils = MediaWikiServices::getInstance()->getUserNameUtils();
		foreach ( $names as $name ) {
			if ( $utils->getCanonical( $name, UserNameUtils::RIGOR_VALID ) === $name ) {
				return $name;
			}
		}
		return null;
	}

	public function storedHandleOf( int $userId ): ?string {
		$realName = (string)$this->db()->newSelectQueryBuilder()
			->select( 'user_real_name' )
			->from( 'user' )
			->where( [ 'user_id' => $userId ] )
			->caller( __METHOD__ )
			->fetchField();
		return str_ends_with( $realName, '#0' ) ? substr( $realName, 0, -2 ) : null;
	}

	/**
	 * Clears the stored handle once the account is bound: the binding replaces it. A real name that isn't a stored
	 * handle ("username#discriminator") is the user's own, and stays.
	 */
	public function clearStoredHandle( int $userId ): void {
		$dbw = $this->db();
		$realName = (string)$dbw->newSelectQueryBuilder()
			->select( 'user_real_name' )
			->from( 'user' )
			->where( [ 'user_id' => $userId ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( !preg_match( '/#\d+$/', $realName ) ) {
			return;
		}
		$dbw->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_real_name' => '' ] )
			->where( [ 'user_id' => $userId ] )
			->caller( __METHOD__ )
			->execute();
		MediaWikiServices::getInstance()->getUserFactory()->newFromId( $userId )->invalidateCache();
	}

	private function db() {
		return MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
	}
}
