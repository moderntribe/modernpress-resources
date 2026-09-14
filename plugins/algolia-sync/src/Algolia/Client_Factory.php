<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Algolia;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Configuration\SearchConfig;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Creates a configured Algolia search client from the stored credentials.
 */
class Client_Factory {

	private const CONNECT_TIMEOUT = 5;

	private Options $options;
	private ?SearchClient $client = null;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * @throws \RuntimeException When credentials are not configured.
	 */
	public function get_client(): SearchClient {
		if ( $this->client instanceof SearchClient ) {
			return $this->client;
		}

		$app_id    = $this->options->get_app_id();
		$admin_key = $this->options->get_admin_key();

		if ( $app_id === '' || $admin_key === '' ) {
			throw new \RuntimeException( 'Algolia credentials are not configured.' );
		}

		$config = SearchConfig::create( $app_id, $admin_key );
		$config->setConnectTimeout( self::CONNECT_TIMEOUT );

		$this->client = SearchClient::createWithConfig( $config );

		return $this->client;
	}

}
