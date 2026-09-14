<?php declare(strict_types=1);

/**
 * Plugin Name: Algolia Sync
 * Description: Sync selected post types to specified Algolia index
 * Author:      Modern Tribe
 * Author URI:  http://tri.be
 * Version:     1.0
 * Requires at least: 6.1
 * Requires PHP: 8.1
 * Text Domain: tribe-algolia-sync
 */

namespace Tribe\Algolia_Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION      = '1.0.0';
const PLUGIN_SLUG  = 'algolia-sync';
const PLUGIN_FILE  = __FILE__;
const PLUGIN_PATH  = __DIR__;

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action( 'admin_notices', static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Algolia Sync requires PHP 8.1 or higher.', 'tribe-algolia-sync' )
		);
	} );

	return;
}

$tribe_algolia_sync_autoload = PLUGIN_PATH . '/vendor/autoload.php';

if ( file_exists( $tribe_algolia_sync_autoload ) ) {
	require_once $tribe_algolia_sync_autoload;
} else {
	/**
	 * Fallback PSR-4 autoload until Composer vendor is installed (Phase 2).
	 */
	spl_autoload_register( static function ( string $class ): void {
		$prefix = 'Tribe\\Algolia_Sync\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = PLUGIN_PATH . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );

	add_action( 'admin_notices', static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Algolia Sync: Composer dependencies are not installed yet. Run composer install inside the plugin directory after package approval.', 'tribe-algolia-sync' )
		);
	} );
}

add_action( 'plugins_loaded', static function (): void {
	$plugin = new Plugin();
	$plugin->init(
		plugins_url( '', PLUGIN_FILE ),
		VERSION
	);
}, 10, 0 );
