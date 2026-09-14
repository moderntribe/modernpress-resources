<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync;

use Tribe\Algolia_Sync\Admin\Settings_Fields;
use Tribe\Algolia_Sync\Admin\Settings_Page;
use Tribe\Algolia_Sync\Admin\Settings_Registrar;
use Tribe\Algolia_Sync\Admin\Status_Panel;
use Tribe\Algolia_Sync\Admin\Sync_Ajax;
use Tribe\Algolia_Sync\Admin\Sync_Controls;
use Tribe\Algolia_Sync\Algolia\Client_Factory;
use Tribe\Algolia_Sync\Algolia\Index_Resolver;
use Tribe\Algolia_Sync\Algolia\Record_Transformer;
use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;
use Tribe\Algolia_Sync\Sync\Full_Sync;
use Tribe\Algolia_Sync\Sync\Post_Lifecycle;
use Tribe\Algolia_Sync\Sync\Post_Sync;
use Tribe\Algolia_Sync\Sync\Term_Lifecycle;

/**
 * Plugin bootstrap: wires services and registers hooks.
 */
class Plugin {

	public function init( string $plugin_url, string $version ): void {
		$options  = new Options();
		$logger   = new Logger( $options );
		$indexes  = new Index_Resolver();
		$fields   = new Settings_Fields( $options, $indexes );
		$registrar = new Settings_Registrar( $options, $fields );
		$status_panel = new Status_Panel( $options );
		$controls = new Sync_Controls( $options, $logger, $status_panel );
		$settings = new Settings_Page( $options, $controls, $plugin_url, $version );

		$registrar->register();
		$controls->register();
		$settings->register();

		if ( ! class_exists( \Algolia\AlgoliaSearch\Api\SearchClient::class ) ) {
			return;
		}

		$client      = new Client_Factory( $options );
		$transformer = new Record_Transformer();
		$post_sync   = new Post_Sync( $options, $client, $indexes, $transformer, $logger );
		$full_sync   = new Full_Sync( $options, $post_sync, $logger );
		$sync_ajax   = new Sync_Ajax( $full_sync, $logger, $options );
		$lifecycle   = new Post_Lifecycle( $options, $post_sync, $logger );
		$terms       = new Term_Lifecycle( $options, $post_sync, $logger );

		$sync_ajax->register();
		$lifecycle->register();
		$terms->register();
	}

}
