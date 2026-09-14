<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Sync;

use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Orchestrates full sync through short AJAX batches.
 */
class Full_Sync {

	private const BATCH_SIZE = 25;

	private Options $options;
	private Post_Sync $post_sync;
	private Logger $logger;

	public function __construct( Options $options, Post_Sync $post_sync, Logger $logger ) {
		$this->options   = $options;
		$this->post_sync = $post_sync;
		$this->logger    = $logger;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function start(): array {
		if ( ! $this->options->is_ready_to_sync() ) {
			throw new \RuntimeException( __( 'Algolia Sync is not configured.', 'tribe-algolia-sync' ) );
		}

		$status = $this->options->get_status();

		if ( ( $status['state'] ?? '' ) === Logger::STATE_RUNNING ) {
			throw new \RuntimeException( __( 'A sync is already running. Reset status before starting again.', 'tribe-algolia-sync' ) );
		}

		$post_types = $this->options->get_post_types();

		$this->logger->set_status( [
			'state'           => Logger::STATE_RUNNING,
			'stage'           => 'full_sync',
			'message'         => __( 'Full sync started.', 'tribe-algolia-sync' ),
			'processed'       => 0,
			'total'           => 0,
			'started_at'      => time(),
			'finished_at'     => 0,
			'post_types'      => $post_types,
			'post_type_index' => 0,
			'page'            => 1,
		] );

		return $this->response( false, __( 'Full sync started.', 'tribe-algolia-sync' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function process_batch(): array {
		$status = $this->options->get_status();

		if ( ( $status['state'] ?? '' ) !== Logger::STATE_RUNNING ) {
			throw new \RuntimeException( __( 'No sync is currently running.', 'tribe-algolia-sync' ) );
		}

		$post_types = $status['post_types'] ?? [];
		if ( ! is_array( $post_types ) || $post_types === [] ) {
			return $this->complete( __( 'No post types selected for sync.', 'tribe-algolia-sync' ) );
		}

		$post_type_index = (int) ( $status['post_type_index'] ?? 0 );
		$page            = (int) ( $status['page'] ?? 1 );
		$processed       = (int) ( $status['processed'] ?? 0 );

		if ( ! isset( $post_types[ $post_type_index ] ) ) {
			return $this->complete(
				sprintf(
					/* translators: %d: number of posts processed */
					__( 'Full sync completed. %d posts processed.', 'tribe-algolia-sync' ),
					$processed
				)
			);
		}

		$post_type = (string) $post_types[ $post_type_index ];
		$homepage  = (int) get_option( 'page_on_front', 0 );

		$query = new \WP_Query( [
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => self::BATCH_SIZE,
			'paged'                  => $page,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		$post_ids = array_map( 'intval', $query->posts );

		if ( $homepage > 0 ) {
			$post_ids = array_values( array_filter(
				$post_ids,
				static fn( int $post_id ): bool => $post_id !== $homepage
			) );
		}

		$batch_count = $this->post_sync->upsert_posts( $post_ids );
		$processed  += $batch_count;

		$message = sprintf(
			/* translators: 1: post type, 2: page number, 3: processed count */
			__( 'Syncing %1$s — page %2$d (%3$d processed).', 'tribe-algolia-sync' ),
			$post_type,
			$page,
			$processed
		);

		$next_page  = $page + 1;
		$next_index = $post_type_index;

		if ( $page >= (int) $query->max_num_pages ) {
			$next_page  = 1;
			$next_index = $post_type_index + 1;
		}

		if ( ! isset( $post_types[ $next_index ] ) ) {
			$this->logger->set_status( [
				'state'           => Logger::STATE_COMPLETED,
				'stage'           => 'full_sync',
				'message'         => sprintf(
					/* translators: %d: number of posts processed */
					__( 'Full sync completed. %d posts processed.', 'tribe-algolia-sync' ),
					$processed
				),
				'processed'       => $processed,
				'finished_at'     => time(),
				'post_types'      => $post_types,
				'post_type_index' => $next_index,
				'page'            => $next_page,
			] );

			return $this->response( true, (string) $this->options->get_status()['message'] );
		}

		$this->logger->set_status( [
			'state'           => Logger::STATE_RUNNING,
			'stage'           => 'full_sync',
			'message'         => $message,
			'processed'       => $processed,
			'post_types'      => $post_types,
			'post_type_index' => $next_index,
			'page'            => $next_page,
		] );

		return $this->response( false, $message );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function complete( string $message ): array {
		$this->logger->set_status( [
			'state'       => Logger::STATE_COMPLETED,
			'stage'       => 'full_sync',
			'message'     => $message,
			'finished_at' => time(),
		] );

		return $this->response( true, $message );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function response( bool $done, string $message ): array {
		$status = $this->options->get_status();

		return [
			'done'        => $done,
			'message'     => $message,
			'state'       => (string) ( $status['state'] ?? Logger::STATE_IDLE ),
			'stage'       => (string) ( $status['stage'] ?? '' ),
			'processed'   => (int) ( $status['processed'] ?? 0 ),
			'total'       => (int) ( $status['total'] ?? 0 ),
			'started'     => (int) ( $status['started_at'] ?? 0 ),
			'updated'     => (int) ( $status['updated_at'] ?? 0 ),
			'finished'    => (int) ( $status['finished_at'] ?? 0 ),
			'log'         => array_slice( array_reverse( $this->options->get_log() ), 0, 20 ),
		];
	}

}
