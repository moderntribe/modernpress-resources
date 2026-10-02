<?php declare(strict_types=1);

/**
 * Merge the entries below into `wp-content/plugins/core/src/Object_Meta/Meta_Definer.php`.
 */

// Add to the `use` statements:
use Tribe\Plugin\Object_Meta\Post_Types\Location_Meta;

// Add to the `self::OBJECT_META` array in `define()`:
DI\get( Location_Meta::class ),
