<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Sync;

use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Reindexes selected posts when their terms change.
 *
 * Term edits happen outside the Algolia settings page, so work is deferred to
 * chained wp-cron single events instead of admin AJAX.
 */
class Term_Lifecycle {

	public const HOOK_TERM_BATCH  = 'tribe/algolia_sync/term_reindex_batch';
	public const HOOK_POSTS_BATCH = 'tribe/algolia_sync/term_posts_batch';

	private const BATCH_SIZE = 50;

	private Options $options;
	private Post_Sync $post_sync;
	private Logger $logger;

	public function __construct( Options $options, Post_Sync $post_sync, Logger $logger ) {
		$this->options   = $options;
		$this->post_sync = $post_sync;
		$this->logger    = $logger;
	}

	public function register(): void {
		add_action( 'created_term', [ $this, 'schedule_term_reindex' ], 10, 3 );
		add_action( 'edited_term', [ $this, 'schedule_term_reindex' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'schedule_deleted_term_reindex' ], 10, 5 );
		add_action( self::HOOK_TERM_BATCH, [ $this, 'reindex_term_batch' ] );
		add_action( self::HOOK_POSTS_BATCH, [ $this, 'reindex_posts_batch' ] );
	}

	public function schedule_term_reindex( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $tt_id );

		if ( ! $this->should_handle_taxonomy( $taxonomy ) ) {
			return;
		}

		$this->enqueue_term_batch( $taxonomy, $term_id, 1 );

		$this->logger->log(
			sprintf(
				/* translators: 1: taxonomy slug, 2: term ID */
				__( 'Scheduled Algolia reindex for %1$s term #%2$d.', 'tribe-algolia-sync' ),
				$taxonomy,
				$term_id
			)
		);
	}

	/**
	 * @param list<int>|array<int, mixed> $object_ids
	 */
	public function schedule_deleted_term_reindex(
		int $term_id,
		int $tt_id,
		string $taxonomy,
		\WP_Term $deleted_term,
		array $object_ids
	): void {
		unset( $term_id, $tt_id, $deleted_term );

		if ( ! $this->should_handle_taxonomy( $taxonomy ) ) {
			return;
		}

		$post_ids = array_values( array_filter(
			array_map( 'intval', $object_ids ),
			static fn( int $post_id ): bool => $post_id > 0
		) );

		if ( $post_ids === [] ) {
			return;
		}

		foreach ( array_chunk( $post_ids, self::BATCH_SIZE ) as $chunk ) {
			$this->enqueue_posts_batch( $chunk );
		}

		$this->logger->log(
			sprintf(
				/* translators: 1: taxonomy slug, 2: number of posts */
				__( 'Scheduled Algolia reindex for %2$d posts after deleting a %1$s term.', 'tribe-algolia-sync' ),
				$taxonomy,
				count( $post_ids )
			)
		);
	}

	/**
	 * @param array{taxonomy?: string, term_id?: int, page?: int} $args
	 */
	public function reindex_term_batch( array $args ): void {
		$taxonomy = (string) ( $args['taxonomy'] ?? '' );
		$term_id  = (int) ( $args['term_id'] ?? 0 );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

		if ( $taxonomy === '' || $term_id <= 0 || ! $this->should_handle_taxonomy( $taxonomy ) ) {
			return;
		}

		$post_types = $this->options->get_post_types();

		$query = new \WP_Query( [
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => self::BATCH_SIZE,
			'paged'                  => $page,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
			'tax_query'              => [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
		] );

		$post_ids = array_map( 'intval', $query->posts );

		try {
			$processed = $this->post_sync->upsert_posts( $post_ids );
		} catch ( \Throwable $exception ) {
			$this->logger->log(
				sprintf(
					/* translators: %s: error message */
					__( 'Term reindex batch failed: %s', 'tribe-algolia-sync' ),
					$exception->getMessage()
				),
				Logger::LEVEL_ERROR
			);
			return;
		}

		$this->logger->log(
			sprintf(
				/* translators: 1: taxonomy, 2: term ID, 3: page, 4: processed count */
				__( 'Reindexed %1$s term #%2$d — page %3$d (%4$d posts).', 'tribe-algolia-sync' ),
				$taxonomy,
				$term_id,
				$page,
				$processed
			)
		);

		if ( $page >= (int) $query->max_num_pages ) {
			return;
		}

		$this->enqueue_term_batch( $taxonomy, $term_id, $page + 1 );
	}

	/**
	 * @param array{post_ids?: list<int>} $args
	 */
	public function reindex_posts_batch( array $args ): void {
		$post_ids = $args['post_ids'] ?? [];

		if ( ! is_array( $post_ids ) || $post_ids === [] ) {
			return;
		}

		$post_ids = array_values( array_filter(
			array_map( 'intval', $post_ids ),
			static fn( int $post_id ): bool => $post_id > 0
		) );

		try {
			$processed = $this->post_sync->upsert_posts( $post_ids );
		} catch ( \Throwable $exception ) {
			$this->logger->log(
				sprintf(
					/* translators: %s: error message */
					__( 'Term post-batch reindex failed: %s', 'tribe-algolia-sync' ),
					$exception->getMessage()
				),
				Logger::LEVEL_ERROR
			);
			return;
		}

		$this->logger->log(
			sprintf(
				/* translators: %d: processed count */
				__( 'Reindexed %d posts after a term change.', 'tribe-algolia-sync' ),
				$processed
			)
		);
	}

	private function should_handle_taxonomy( string $taxonomy ): bool {
		if ( $taxonomy === '' || ! $this->options->is_ready_to_sync() ) {
			return false;
		}

		if ( $this->options->get_post_types() === [] ) {
			return false;
		}

		if ( ! $this->taxonomy_applies_to_selected_types( $taxonomy ) ) {
			return false;
		}

		/**
		 * Filters whether a taxonomy change should trigger an Algolia reindex.
		 *
		 * @param bool   $should_reindex Whether to reindex.
		 * @param string $taxonomy       Taxonomy slug.
		 */
		return (bool) apply_filters( 'tribe/algolia_sync/should_reindex_taxonomy', true, $taxonomy );
	}

	private function taxonomy_applies_to_selected_types( string $taxonomy ): bool {
		foreach ( $this->options->get_post_types() as $post_type ) {
			$taxonomies = get_object_taxonomies( $post_type, 'names' );

			if ( in_array( $taxonomy, $taxonomies, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function enqueue_term_batch( string $taxonomy, int $term_id, int $page ): void {
		$args = [ [
			'taxonomy' => $taxonomy,
			'term_id'  => $term_id,
			'page'     => $page,
		] ];

		if ( wp_next_scheduled( self::HOOK_TERM_BATCH, $args ) ) {
			return;
		}

		wp_schedule_single_event( time(), self::HOOK_TERM_BATCH, $args );
	}

	/**
	 * @param list<int> $post_ids
	 */
	private function enqueue_posts_batch( array $post_ids ): void {
		$args = [ [
			'post_ids' => $post_ids,
		] ];

		if ( wp_next_scheduled( self::HOOK_POSTS_BATCH, $args ) ) {
			return;
		}

		wp_schedule_single_event( time(), self::HOOK_POSTS_BATCH, $args );
	}

}
