<?php

namespace WSOAuthDiscord;

interface MappingLookup {

	/**
	 * The remote IDs bound to this account in WSOAuth's mapping table, from any provider.
	 *
	 * @return string[]
	 */
	public function remoteIdsFor( int $userId ): array;
}
