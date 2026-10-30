# WSOAuth-Discord

A Discord provider for MediaWiki's [WSOAuth](https://www.mediawiki.org/wiki/Extension:WSOAuth) extension, which logs
users in through [PluggableAuth](https://www.mediawiki.org/wiki/Extension:PluggableAuth). Built for the HorizonXI wiki
(horizonffxi.wiki).

- Each wiki account is bound to the user's **Discord ID**, which never changes, not to their Discord username, which
  can.
- It asks Discord for the `identify` scope only, and keeps nothing but the ID (in WSOAuth's mapping table).
- `prompt=none`: someone who has already authorised the app goes straight back to the wiki.
- Cancelling on Discord, an expired login, or Discord being unreachable show a short message, never an error page.

## Requirements

MediaWiki 1.43 or later, with PluggableAuth 7 and WSOAuth 9 (both from their `REL1_43` branches or later).

## Install

Clone into `extensions/WSOAuth-Discord`, then in `LocalSettings.php`:

```php
wfLoadExtension( 'PluggableAuth' );
wfLoadExtension( 'WSOAuth' );
wfLoadExtension( 'WSOAuth-Discord' );
$wgPluggableAuth_Config['discord'] = [
	'plugin' => 'WSOAuth',
	'buttonLabelMessage' => 'wsoauth-discord-login-button',
	'data' => [
		'type' => 'discord',
		'clientId' => '<your Discord application ID>',
		'clientSecret' => '<its client secret>',
		'redirectUri' => $wgServer . '/Special:PluggableAuthLogin',
		'useRealNameAsUsername' => true,
	],
];
```

In the Discord developer portal, add `https://<your wiki>/Special:PluggableAuthLogin` as a redirect for the
application.

## Logging in from the current page

By default a login goes through `Special:UserLogin`. With

```php
$wgWSOAuthDiscordSameWindowLogin = true;
$wgWSOAuthDiscordLoginButtonSelector = '#discord-login'; // optional
```

a logged-out visitor who clicks a "Log in" link, or the skin button the selector names, goes straight to Discord in the
same window and comes back to the page they were on, an edit form included (`returnto` and `returntoquery`). It needs
PluggableAuth's single-button login, where `Special:UserLogin` sends the visitor on by itself. Before leaving, the
script starts a session with an API login-token request, so a server that refuses login URLs from visitors with no
session cookie (a common bot defense) lets the request through; if that fails, the visitor still logs in and returns
to the page, without its query.

## Account names

A new account is named from the Discord username, with `_` turned into `-` and its first letter capitalised:
`some_user` becomes `Some-user`. MediaWiki would treat `_` as a space, trimming and collapsing it; it keeps `-` as
written, and Discord usernames can't contain `-`, so a name made this way never equals another user's plain handle.

## Taking over existing accounts

With `migrateUsersByUsername`, WSOAuth lets a Discord user's first login take over an existing account of the same
name. This provider only allows that when the account isn't someone else's:

- an account already bound to a different Discord ID is never taken over; the newcomer gets `<name> 1` (or `2`, …);
- with `'extensionData' => [ 'adoptByStoredHandle' => true ]`, an account whose real name holds a Discord handle
  stored by an earlier login (`username#0`) is taken over only by that handle, and the stored handle is cleared once the
  account is bound. This is for wikis moving from a login that matched accounts by name.

## Tests

```
composer install
composer test
node --test 'tests/js/*.test.mjs'
```

## Credits

Modelled on WSOAuth's own `FacebookAuth` provider, and on Kneemund's
[mediawiki-discord-oauth](https://github.com/Kneemund/mediawiki-discord-oauth) (MIT), which showed the approach.

## Licence

MIT; see `LICENSE`.
