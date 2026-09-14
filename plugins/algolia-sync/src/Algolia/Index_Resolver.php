<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Algolia;

/**
 * Resolves configured base index names to env-suffixed runtime names.
 */
class Index_Resolver {

	public function resolve( string $base_name ): string {
		return sprintf( '%s_%s', $base_name, wp_get_environment_type() );
	}

}
