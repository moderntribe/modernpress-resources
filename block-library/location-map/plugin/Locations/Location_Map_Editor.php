<?php declare(strict_types=1);

namespace Tribe\Plugin\Locations;

use Tribe\Plugin\Settings\Tribe_Settings;

class Location_Map_Editor {

	public function __construct(
		private Tribe_Settings $settings,
	) {
	}

	public function enqueue_config(): void {
		$config = wp_json_encode( [
			'hasGoogleMapsApiKey'         => $this->settings->has_google_maps_api_key(),
			'settingsUrl'                 => admin_url(
				'options-general.php?page=' . Tribe_Settings::PAGE_SLUG
			),
			'defaultLocationsEndpointUrl' => $this->get_locations_endpoint_url(),
		] );

		if ( ! is_string( $config ) ) {
			return;
		}

		wp_add_inline_script(
			'wp-blocks',
			'window.tribeLocationMap = ' . $config . ';',
			'before'
		);
	}

	private function get_locations_endpoint_url(): string {
		$url      = rest_url( 'tribe/v1/locations' );
		$relative = wp_make_link_relative( $url );

		return is_string( $relative ) && $relative !== '' ? $relative : $url;
	}

}
