<?php

namespace WSOAuthDiscord\Tests;

use PHPUnit\Framework\TestCase;
use WSOAuthDiscord\AccountName;

/**
 * @covers \WSOAuthDiscord\AccountName
 */
class AccountNameTest extends TestCase {

	public static function provideNames(): array {
		return [
			'plain name' => [ 'someuser', 'Someuser' ],
			'already capitalised' => [ 'Someone', 'Someone' ],
			'dot is kept' => [ 'some.user', 'Some.user' ],
			'digits' => [ 'user42', 'User42' ],
			'underscore inside' => [ 'some_user', 'Some-user' ],
			'leading underscore' => [ '_lead', '-lead' ],
			'trailing underscore' => [ 'trail_', 'Trail-' ],
			'double underscore stays double' => [ 'a__b', 'A--b' ],
			'legacy name keeps its spaces' => [ 'Old Name', 'Old Name' ],
			'legacy name with a bar' => [ 'bad|name', 'Badname' ],
			'brackets and braces' => [ 'x[y]{z}', 'Xyz' ],
			'angle brackets and hash' => [ 'a<b>#c', 'Abc' ],
		];
	}

	/**
	 * @dataProvider provideNames
	 */
	public function testFromDiscord( string $username, string $expected ): void {
		$this->assertSame( $expected, AccountName::fromDiscord( $username ) );
	}

	public function testUnderscoresDontCollide(): void {
		$this->assertNotSame( AccountName::fromDiscord( 'ab_c' ), AccountName::fromDiscord( 'a_bc' ) );
	}
}
