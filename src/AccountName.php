<?php

namespace WSOAuthDiscord;

/**
 * The wiki account name for a new Discord user: the username with "_" turned into "-" and its first letter
 * capitalised. MediaWiki treats "_" as a space (trimming and collapsing it), but keeps "-" as written; and Discord
 * usernames can't contain "-", so a name made this way never equals another user's plain handle.
 */
class AccountName {

	/** Only legacy (pre-2023) Discord usernames can hold these, and MediaWiki forbids them in titles. */
	private const FORBIDDEN = [ '#', '<', '>', '[', ']', '{', '|', '}' ];

	public static function fromDiscord( string $username ): string {
		return ucfirst( str_replace( '_', '-', str_replace( self::FORBIDDEN, '', $username ) ) );
	}
}
