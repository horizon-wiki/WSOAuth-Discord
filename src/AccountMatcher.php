<?php

namespace WSOAuthDiscord;

use RuntimeException;

/**
 * Picks the account name to hand WSOAuth on a Discord ID's first login. WSOAuth takes over the existing account with
 * that name even when it already belongs to someone else, so the matcher only names an existing account when it is
 * this user's: the account their stored handle points to, or an account nobody has a claim on yet. Anyone else gets a
 * new account, "<name> 1" (or 2, ...).
 */
class AccountMatcher {

	/** Matches WSOAuth's own limit when it numbers names. */
	private const MAX_TRIES = 256;

	private NameLookup $names;
	private MappingLookup $mappings;
	private ?HandleLookup $handles;

	public function __construct( NameLookup $names, MappingLookup $mappings, ?HandleLookup $handles = null ) {
		$this->names = $names;
		$this->mappings = $mappings;
		$this->handles = $handles;
	}

	/**
	 * @param string $discordId the Discord user ID logging in
	 * @param string $username their Discord username, as Discord sent it
	 * @param string $name the canonical account name AccountName gives for it
	 */
	public function choose( string $discordId, string $username, string $name ): string {
		if ( $this->handles ) {
			$adopted = $this->handles->accountForHandle( $username );
			if ( $adopted !== null ) {
				return $adopted;
			}
		}
		$userId = $this->names->idFor( $name );
		if ( $userId === 0 ) {
			return $name;
		}
		$bound = $this->mappings->remoteIdsFor( $userId );
		if ( in_array( $discordId, $bound, true ) ) {
			return $name;
		}
		$someoneElses = $bound !== [] || ( $this->handles && $this->handles->storedHandleOf( $userId ) !== null );
		if ( !$someoneElses ) {
			return $name;
		}
		// Discord usernames never contain spaces, so these names can't collide with a future user's own name.
		for ( $i = 1; $i < self::MAX_TRIES; $i++ ) {
			$candidate = "$name $i";
			if ( $this->names->idFor( $candidate ) === 0 ) {
				return $candidate;
			}
		}
		throw new RuntimeException( "No free account name near $name" );
	}
}
