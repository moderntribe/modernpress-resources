<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Support;

/**
 * Tracks sync state and keeps a bounded, user readable activity log.
 */
class Logger {

	public const STATE_IDLE      = 'idle';
	public const STATE_RUNNING   = 'running';
	public const STATE_COMPLETED = 'completed';
	public const STATE_FAILED    = 'failed';

	public const LEVEL_INFO  = 'info';
	public const LEVEL_ERROR = 'error';

	/**
	 * Entries are shown on the settings page, so the log stays short enough to read
	 * and small enough to keep in a single option.
	 */
	private const LOG_LIMIT = 50;

	private Options $options;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	public function set_status( array $overrides ): void {
		$status = array_merge( $this->get_defaults(), $this->options->get_status(), $overrides );

		$status['updated_at'] = time();

		$this->options->update_status( $status );

		$message = (string) $status['message'];

		if ( $message === '' ) {
			return;
		}

		$level = $status['state'] === self::STATE_FAILED ? self::LEVEL_ERROR : self::LEVEL_INFO;

		$this->log( $message, $level );
	}

	public function log( string $message, string $level = self::LEVEL_INFO ): void {
		$entries = $this->options->get_log();
		$last    = end( $entries );

		// Batch steps repeat the same status message; only record when it actually changes.
		if ( is_array( $last ) && ( $last['message'] ?? '' ) === $message ) {
			return;
		}

		$entries[] = [
			'time'    => time(),
			'level'   => $level,
			'message' => $message,
		];

		$this->options->update_log( array_slice( $entries, -self::LOG_LIMIT ) );
	}

	public function log_error( \Throwable $exception ): void {
		$this->set_status( [
			'state'   => self::STATE_FAILED,
			'message' => $exception->getMessage(),
		] );

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		error_log( sprintf( '[Algolia Sync] %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine() ) );
		error_log( $exception->getTraceAsString() );
	}

	public function reset(): void {
		$this->options->update_status( $this->get_defaults() );
		$this->options->update_log( [] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_defaults(): array {
		return [
			'state'       => self::STATE_IDLE,
			'stage'       => '',
			'message'     => '',
			'processed'   => 0,
			'total'       => 0,
			'started_at'  => 0,
			'updated_at'  => 0,
			'finished_at' => 0,
		];
	}

}
