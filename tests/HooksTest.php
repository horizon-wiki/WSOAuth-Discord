<?php

namespace WSOAuthDiscord\Tests;

use PHPUnit\Framework\TestCase;
use WSOAuthDiscord\Hooks;

/**
 * @covers \WSOAuthDiscord\Hooks::wantsSameWindowLogin
 */
class HooksTest extends TestCase {

	public static function provideCases(): array {
		return [
			'on, logged out' => [ true, false, true ],
			'on, logged in' => [ true, true, false ],
			'off, logged out' => [ false, false, false ],
			'off, logged in' => [ false, true, false ],
		];
	}

	/**
	 * @dataProvider provideCases
	 */
	public function testWantsSameWindowLogin( bool $enabled, bool $isRegistered, bool $expected ): void {
		$this->assertSame( $expected, Hooks::wantsSameWindowLogin( $enabled, $isRegistered ) );
	}
}
