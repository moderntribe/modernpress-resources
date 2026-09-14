<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Sync;

use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Keeps Algolia in sync when posts are created, updated, unpublished, or deleted.
 */
class Post_Lifecycle {

	private Options $options;
	private Post_Sync $post_sync;
	private Logger $logger;

	public function __construct( Options $options, Post_Sync $post_sync, Logger $logger ) {
		$this->options   = $options;
		$this->post_sync = $post_sync;
		$this->logger    = $logger;
	}

	public function register(): void {
		add_action( 'wp_after_insert_post', [ $this, 'sync_after_save' ], 20, 2 );
		add_action( 'before_delete_post', [ $this, 'delete_before_remove' ], 10, 2 );
		add_action( 'trashed_post', [ $this, 'delete_trashed' ] );
	}

	public function sync_after_save( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $this->is_selected_post_type( $post->post_type ) ) {
			return;
		}

		try {
			if ( $this->post_sync->should_index( $post ) ) {
				$this->post_sync->upsert_post( $post );
				return;
			}

			$this->post_sync->delete_post( $post_id );
		} catch ( \Throwable $exception ) {
			$this->logger->log(
				sprintf(
					/* translators: 1: post ID, 2: error message */
					__( 'Lifecycle sync failed for post #%1$d: %2$s', 'tribe-algolia-sync' ),
					$post_id,
					$exception->getMessage()
				),
				Logger::LEVEL_ERROR
			);
		}
	}

	public function delete_before_remove( int $post_id, \WP_Post $post ): void {
		if ( ! $this->is_selected_post_type( $post->post_type ) ) {
			return;
		}

		try {
			$this->post_sync->delete_post( $post_id );
		} catch ( \Throwable $exception ) {
			$this->logger->log(
				sprintf(
					/* translators: 1: post ID, 2: error message */
					__( 'Failed to remove post #%1$d from Algolia: %2$s', 'tribe-algolia-sync' ),
					$post_id,
					$exception->getMessage()
				),
				Logger::LEVEL_ERROR
			);
		}
	}

	public function delete_trashed( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->delete_before_remove( $post_id, $post );
	}

	private function is_selected_post_type( string $post_type ): bool {
		return in_array( $post_type, $this->options->get_post_types(), true );
	}

}
