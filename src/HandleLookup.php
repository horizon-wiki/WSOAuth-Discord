<?php

namespace WSOAuthDiscord;

/**
 * Discord handles stored by an earlier login, as "username#0" in the account's real name. Used to adopt existing
 * accounts by the handle that last used them (adoptByStoredHandle).
 */
interface HandleLookup {

	/**
	 * The name of the unbound account whose stored handle is exactly this username, or null. If several have it, the
	 * one MediaWiki can log into, most recently active first.
	 */
	public function accountForHandle( string $username ): ?string;

	/** The current-style handle stored for this account, or null if it has none. */
	public function storedHandleOf( int $userId ): ?string;
}
