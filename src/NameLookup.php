<?php

namespace WSOAuthDiscord;

interface NameLookup {

	/** The ID of the account with this exact (canonical) name, or 0 if there is none. */
	public function idFor( string $name ): int;
}
