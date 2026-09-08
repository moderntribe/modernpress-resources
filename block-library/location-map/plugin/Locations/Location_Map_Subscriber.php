<?php declare(strict_types=1);

namespace Tribe\Plugin\Locations;

use Tribe\Plugin\Core\Abstract_Subscriber;

class Location_Map_Subscriber extends Abstract_Subscriber {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', function (): void {
			$this->container->get( Google_Maps::class )->enqueue_frontend_loader();
		}, 5, 0 );

		add_action( 'enqueue_block_editor_assets', function (): void {
			$this->container->get( Google_Maps::class )->enqueue_editor_loader();
		}, 5, 0 );

		add_action( 'enqueue_block_editor_assets', function (): void {
			$this->container->get( Location_Map_Editor::class )->enqueue_config();
		} );
	}

}
