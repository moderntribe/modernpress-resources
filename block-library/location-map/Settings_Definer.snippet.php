<?php declare(strict_types=1);

/**
 * Merge the entry below into `wp-content/plugins/core/src/Settings/Settings_Definer.php`.
 *
 * `Tribe_Settings` is in the same namespace and does not require an additional `use` statement.
 */

// Add to the `self::PAGES` array in `define()`:
DI\get( Tribe_Settings::class ),
