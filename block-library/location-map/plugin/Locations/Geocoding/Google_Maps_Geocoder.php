<?php declare(strict_types=1);

namespace Tribe\Plugin\Locations\Geocoding;

use Tribe\Plugin\Locations\Google_Maps_Config;
use Tribe\Plugin\Locations\Location_Search_Resolver;
use Tribe\Plugin\Settings\Tribe_Settings;

/**
 * Google Geocoding API provider with transient caching.
 */
class Google_Maps_Geocoder implements Geocoder_Interface {

	private const string ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';
	private const int CACHE_TTL   = DAY_IN_SECONDS;

	public function __construct(
		private Tribe_Settings $settings,
		private Google_Maps_Config $maps_config,
		private Location_Search_Resolver $search_resolver,
	) {
	}

	public function geocode( string $address ): ?array {
		$address = trim( $address );
		$api_key = $this->settings->get_google_maps_api_key();

		if ( $address === '' || $api_key === '' ) {
			return null;
		}

		$cache_key = 'tribe_google_geocode_' . md5( strtolower( $address ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['lat'], $cached['lng'] ) ) {
			return $cached;
		}

		if ( is_array( $cached ) && empty( $cached ) ) {
			return null;
		}

		$response = $this->request_geocode( $address, $api_key );

		if ( null === $response ) {
			return null;
		}

		if ( 'ZERO_RESULTS' === ( $response['status'] ?? '' ) ) {
			set_transient( $cache_key, [], self::CACHE_TTL );

			return null;
		}

		$result = $this->get_valid_result( $response );

		if ( null === $result ) {
			return null;
		}

		$coordinates = [
			'lat'    => $result['lat'],
			'lng'    => $result['lng'],
			'search' => $this->search_resolver->from_geocode_result( $result['data'] ),
		];

		set_transient( $cache_key, $coordinates, self::CACHE_TTL );

		return $coordinates;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function request_geocode( string $address, string $api_key ): ?array {
		$url = add_query_arg(
			[
				'address'    => $address,
				'key'        => $api_key,
				'region'     => $this->maps_config->get_region(),
				'components' => $this->maps_config->get_geocode_components(),
			],
			self::ENDPOINT
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : null;
	}

	/**
	 * @param array<string, mixed> $response
	 *
	 * @return array{data: array<string, mixed>, lat: float, lng: float}|null
	 */
	private function get_valid_result( array $response ): ?array {
		if ( 'OK' !== ( $response['status'] ?? '' ) ) {
			return null;
		}

		$result = $response['results'][0] ?? null;

		if ( ! is_array( $result ) ) {
			return null;
		}

		$location = $result['geometry']['location'] ?? null;

		if ( ! is_array( $location ) ) {
			return null;
		}

		$lat = $location['lat'] ?? null;
		$lng = $location['lng'] ?? null;

		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}

		return [
			'data' => $result,
			'lat'  => (float) $lat,
			'lng'  => (float) $lng,
		];
	}

}
