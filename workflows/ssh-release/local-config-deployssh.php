<?php declare(strict_types=1);

/**
 * Local config for the SSH release deploy.
 *
 * Template for the persistent, server-side <deploy-path>/shared/local-config.php,
 * symlinked into every release. Seeded on the first deploy only; the live copy
 * stays out of git. Keep this file to server-only values: everything else,
 * including the production overrides, comes from wp-config-environment.php.
 */

define( 'WP_ENVIRONMENT_TYPE', 'production' );

define( 'DB_NAME', '' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );

// Generate at https://api.wordpress.org/secret-key/1.1/salt/
define( 'AUTH_KEY', '' );
define( 'SECURE_AUTH_KEY', '' );
define( 'LOGGED_IN_KEY', '' );
define( 'NONCE_KEY', '' );
define( 'AUTH_SALT', '' );
define( 'SECURE_AUTH_SALT', '' );
define( 'LOGGED_IN_SALT', '' );
define( 'NONCE_SALT', '' );

/**
 * Glomar
 *
 * GLOMAR is a plugin that blocks the frontend of the site from public access.
 * Disable it to allow public access to the website.
 */
define( 'TRIBE_GLOMAR', tribe_getenv( 'TRIBE_GLOMAR', true ) );

/**
 * Redis object cache
 *
 * Off until configured. If the Redis server is shared, set a prefix unique to
 * this site and keep selective flush on, so `wp cache flush` only clears this
 * site's keys. Then set WP_REDIS_DISABLED to false.
 */
define( 'WP_REDIS_DISABLED', true );

// define( 'WP_REDIS_HOST', '' );
// define( 'WP_REDIS_PORT', 6379 );
// define( 'WP_REDIS_PREFIX', '' );
// define( 'WP_REDIS_SELECTIVE_FLUSH', true );
// define( 'WP_REDIS_DATABASE', 0 ); // alternative isolation on a shared server
// define( 'WP_REDIS_PASSWORD', '' ); // only if the server requires one
