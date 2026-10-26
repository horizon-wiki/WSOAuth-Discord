<?php

namespace WSOAuthDiscord\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WSOAuthDiscord\AccountMatcher;
use WSOAuthDiscord\HandleLookup;
use WSOAuthDiscord\MappingLookup;
use WSOAuthDiscord\NameLookup;

/**
 * @covers \WSOAuthDiscord\AccountMatcher
 */
class AccountMatcherTest extends TestCase {

	/**
	 * @param array<string,int> $accounts name => user ID
	 * @param array<int,string[]> $bindings user ID => remote IDs
	 * @param array<int,string>|null $handles user ID => stored handle, or null for no handle lookups
	 */
	private function matcher( array $accounts, array $bindings, ?array $handles = [] ): AccountMatcher {
		$wiki = new class( $accounts, $bindings, $handles ?? [] ) implements NameLookup, MappingLookup, HandleLookup {
			private array $accounts;
			private array $bindings;
			private array $handles;

			public function __construct( array $accounts, array $bindings, array $handles ) {
				$this->accounts = $accounts;
				$this->bindings = $bindings;
				$this->handles = $handles;
			}

			public function idFor( string $name ): int {
				return $this->accounts[$name] ?? 0;
			}

			public function remoteIdsFor( int $userId ): array {
				return $this->bindings[$userId] ?? [];
			}

			public function accountForHandle( string $username ): ?string {
				foreach ( $this->handles as $userId => $handle ) {
					if ( $handle === $username && !isset( $this->bindings[$userId] ) ) {
						return array_search( $userId, $this->accounts, true );
					}
				}
				return null;
			}

			public function storedHandleOf( int $userId ): ?string {
				return $this->handles[$userId] ?? null;
			}
		};
		return new AccountMatcher( $wiki, $wiki, $handles === null ? null : $wiki );
	}

	public function testNoAccountKeepsTheName(): void {
		$this->assertSame( 'Someuser', $this->matcher( [], [] )->choose( '1001', 'someuser', 'Someuser' ) );
	}

	public function testUnclaimedAccountIsAdoptedByName(): void {
		$matcher = $this->matcher( [ 'Someuser' => 5 ], [] );
		$this->assertSame( 'Someuser', $matcher->choose( '1001', 'someuser', 'Someuser' ) );
	}

	public function testStoredHandleWinsOverTheName(): void {
		$matcher = $this->matcher( [ 'Xy0' => 5 ], [], [ 5 => 'x_y' ] );
		$this->assertSame( 'Xy0', $matcher->choose( '1001', 'x_y', 'X-y' ) );
	}

	public function testAnotherHandlesAccountIsNotAdopted(): void {
		$matcher = $this->matcher( [ 'Xy0' => 5 ], [], [ 5 => 'x_y' ] );
		$this->assertSame( 'Xy0 1', $matcher->choose( '1001', 'xy0', 'Xy0' ) );
	}

	public function testAccountBoundToThisIdKeepsTheName(): void {
		$matcher = $this->matcher( [ 'Someuser' => 5 ], [ 5 => [ '1001' ] ] );
		$this->assertSame( 'Someuser', $matcher->choose( '1001', 'someuser', 'Someuser' ) );
	}

	public function testAccountBoundToAnotherIdGetsAFreshName(): void {
		$matcher = $this->matcher( [ 'Someuser' => 5 ], [ 5 => [ '2002' ] ] );
		$this->assertSame( 'Someuser 1', $matcher->choose( '1001', 'someuser', 'Someuser' ) );
	}

	public function testFreshNameSkipsTakenNumbers(): void {
		$matcher = $this->matcher( [ 'Someuser' => 5, 'Someuser 1' => 6 ], [ 5 => [ '2002' ] ] );
		$this->assertSame( 'Someuser 2', $matcher->choose( '1001', 'someuser', 'Someuser' ) );
	}

	public function testOneOfSeveralBindingsIsThisId(): void {
		$matcher = $this->matcher( [ 'Someuser' => 5 ], [ 5 => [ '2002', '1001' ] ] );
		$this->assertSame( 'Someuser', $matcher->choose( '1001', 'someuser', 'Someuser' ) );
	}

	public function testWithoutHandleLookupsAStoredHandleIsIgnored(): void {
		$matcher = $this->matcher( [ 'Xy0' => 5 ], [], null );
		$this->assertSame( 'Xy0', $matcher->choose( '1001', 'xy0', 'Xy0' ) );
	}

	public function testGivesUpWhenEveryNumberIsTaken(): void {
		$accounts = [ 'Someuser' => 5 ];
		for ( $i = 1; $i < 256; $i++ ) {
			$accounts["Someuser $i"] = 100 + $i;
		}
		$this->expectException( RuntimeException::class );
		$this->matcher( $accounts, [ 5 => [ '2002' ] ] )->choose( '1001', 'someuser', 'Someuser' );
	}
}
