<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Sync;

use Tribe\Algolia_Sync\Algolia\Client_Factory;
use Tribe\Algolia_Sync\Algolia\Index_Resolver;
use Tribe\Algolia_Sync\Algolia\Record_Transformer;
use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Upserts or deletes a single post across all configured indexes.
 */
class Post_Sync {

	private Options $options;
	private Client_Factory $client_factory;
	private Index_Resolver $index_resolver;
	private Record_Transformer $transformer;
	private Logger $logger;

	public function __construct(
		Options $options,
		Client_Factory $client_factory,
		Index_Resolver $index_resolver,
		Record_Transformer $transformer,
		Logger $logger
	) {
		$this->options         = $options;
		$this->client_factory  = $client_factory;
		$this->index_resolver  = $index_resolver;
		$this->transformer     = $transformer;
		$this->logger          = $logger;
	}

	public function upsert_post( \WP_Post $post ): void {
		if ( ! $this->options->is_ready_to_sync() ) {
			return;
		}

		if ( ! $this->is_selected_post_type( $post->post_type ) ) {
			return;
		}

		if ( ! $this->should_index( $post ) ) {
			$this->delete_post( $post->ID );
			return;
		}

		$indexes = $this->resolved_indexes();

		if ( $indexes === [] ) {
			return;
		}

		$record = $this->transformer->to_record( $post );
		$client = $this->client_factory->get_client();

		foreach ( $indexes as $index_name ) {
			$client->saveObject( $index_name, $record );
		}
	}

	public function delete_post( int $post_id ): void {
		if ( ! $this->options->is_ready_to_sync() ) {
			return;
		}

		$post = get_post( $post_id );

		if ( $post instanceof \WP_Post && ! $this->is_selected_post_type( $post->post_type ) ) {
			return;
		}

		$indexes = $this->resolved_indexes();

		if ( $indexes === [] ) {
			return;
		}

		$object_id = $this->transformer->build_object_id( $post_id );
		$client    = $this->client_factory->get_client();

		foreach ( $indexes as $index_name ) {
			$client->deleteObject( $index_name, $object_id );
		}
	}

	/**
	 * @param list<int> $post_ids
	 */
	public function upsert_posts( array $post_ids ): int {
		if ( ! $this->options->is_ready_to_sync() || $post_ids === [] ) {
			return 0;
		}

		$records = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( ! $this->should_index( $post ) ) {
				try {
					$this->delete_post( $post->ID );
				} catch ( \Throwable $exception ) {
					$this->logger->log(
						sprintf(
							/* translators: 1: post ID, 2: error message */
							__( 'Failed to remove post #%1$d: %2$s', 'tribe-algolia-sync' ),
							$post->ID,
							$exception->getMessage()
						),
						Logger::LEVEL_ERROR
					);
				}
				continue;
			}

			$records[] = $this->transformer->to_record( $post );
		}

		if ( $records === [] ) {
			return 0;
		}

		$indexes = $this->resolved_indexes();

		if ( $indexes === [] ) {
			return 0;
		}

		$client = $this->client_factory->get_client();

		try {
			foreach ( $indexes as $index_name ) {
				$client->saveObjects( $index_name, $records );
			}
		} catch ( \Throwable $exception ) {
			$this->logger->log(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to save Algolia batch: %s', 'tribe-algolia-sync' ),
					$exception->getMessage()
				),
				Logger::LEVEL_ERROR
			);

			throw $exception;
		}

		return count( $records );
	}

	public function should_index( \WP_Post $post ): bool {
		if ( $post->post_status !== 'publish' ) {
			return false;
		}

		if ( $post->ID === (int) get_option( 'page_on_front', 0 ) ) {
			return false;
		}

		/**
		 * Filters whether a post should be indexed in Algolia.
		 *
		 * @param bool     $should_index Whether the post should be indexed.
		 * @param \WP_Post $post         Source post.
		 */
		return (bool) apply_filters( 'tribe/algolia_sync/should_index', true, $post );
	}

	private function is_selected_post_type( string $post_type ): bool {
		return in_array( $post_type, $this->options->get_post_types(), true );
	}

	/**
	 * @return list<string>
	 */
	private function resolved_indexes(): array {
		$resolved = [];

		foreach ( $this->options->get_index_names() as $base_name ) {
			$base_name = trim( $base_name );

			if ( $base_name === '' ) {
				continue;
			}

			$resolved[] = $this->index_resolver->resolve( $base_name );
		}

		return array_values( array_unique( $resolved ) );
	}

}
