<?php declare(strict_types=1);

/**
 * Merge the entries below into `wp-content/plugins/core/src/Core.php`.
 */

// Add to the `$definers` array:
Locations\Locations_Definer::class,

// Add to the `$subscribers` array:
Post_Types\Location\Location_Subscriber::class,

Locations\Location_Geocode_Subscriber::class,
Locations\Location_Map_Subscriber::class,
Routes\Routes_Subscriber::class,
