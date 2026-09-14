<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Admin;

use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;
use Tribe\Algolia_Sync\Sync\Full_Sync;

/**
 * Admin AJAX endpoints for recursive full-sync batches.
 */
class Sync_Ajax {

	public const ACTION_START = 'tribe_algolia_sync_start';
	public const ACTION_BATCH = 'tribe_algolia_sync_batch';
	public const NONCE_ACTION = 'tribe_algolia_sync';

	private Full_Sync $full_sync;
	private Logger $logger;
	private Options $options;

	public function __construct( Full_Sync $full_sync, Logger $logger, Options $options ) {
		$this->full_sync = $full_sync;
		$this->logger    = $logger;
		$this->options   = $options;
	}

	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION_START, [ $this, 'handle_start' ] );
		add_action( 'wp_ajax_' . self::ACTION_BATCH, [ $this, 'handle_batch' ] );
	}

	public function handle_start(): void {
		$this->guard();

		try {
			wp_send_json_success( $this->full_sync->start() );
		} catch ( \Throwable $exception ) {
			$this->fail( $exception );
		}
	}

	public function handle_batch(): void {
		$this->guard();

		try {
			wp_send_json_success( $this->full_sync->process_batch() );
		} catch ( \Throwable $exception ) {
			$this->fail( $exception );
		}
	}

	private function guard(): void {
		if ( ! current_user_can( Settings_Page::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to sync.', 'tribe-algolia-sync' ) ], 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
	}

	private function fail( \Throwable $exception ): void {
		$this->logger->log_error( $exception );

		wp_send_json_error( [
			'message' => $exception->getMessage(),
			'state'   => Logger::STATE_FAILED,
			'log'     => array_slice( array_reverse( $this->options->get_log() ), 0, 20 ),
		], 400 );
	}

}
