<?php declare(strict_types=1);

namespace Tribe\Plugin\Locations;

use Tribe\Plugin\Locations\Geocoding\Geocoder_Interface;
use Tribe\Plugin\Object_Meta\Post_Types\Location_Meta;
use Tribe\Plugin\Post_Types\Location\Location;

class Location_Geocoder {

	public function __construct(
		private Geocoder_Interface $geocoder,
		private Location_Data $location_data,
	) {
	}

	public function maybe_geocode_post( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( Location::NAME !== get_post_type( $post_id ) ) {
			return;
		}

		$address = $this->location_data->get_formatted_address( $post_id );

		if ( $address === '' ) {
			return;
		}

		// Geocode only when the saved address changes.
		$address_hash        = $this->location_data->get_address_hash( $post_id );
		$stored_address_hash = (string) get_post_meta( $post_id, Location_Meta::ADDRESS_HASH, true );

		if ( $address_hash === $stored_address_hash ) {
			return;
		}

		$coordinates = $this->geocoder->geocode( $address );

		if ( null === $coordinates ) {
			// Keep existing coordinates and retry the next time the post is saved.
			return;
		}

		$this->update_coordinates( $post_id, $coordinates['lat'], $coordinates['lng'] );
		update_post_meta( $post_id, Location_Meta::ADDRESS_HASH, $address_hash );
	}

	private function update_coordinates( int $post_id, float $lat, float $lng ): void {
		if ( function_exists( 'update_field' ) ) {
			update_field( Location_Meta::LATITUDE, (string) $lat, $post_id );
			update_field( Location_Meta::LONGITUDE, (string) $lng, $post_id );

			return;
		}

		update_post_meta( $post_id, Location_Meta::LATITUDE, (string) $lat );
		update_post_meta( $post_id, Location_Meta::LONGITUDE, (string) $lng );
	}

}
