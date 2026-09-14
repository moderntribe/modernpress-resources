<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Admin;

use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Renders current sync state and recent activity on the settings page.
 */
class Status_Panel {

	private Options $options;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	public function render(): void {
		$status      = $this->options->get_status();
		$state       = (string) ( $status['state'] ?? Logger::STATE_IDLE );
		$stage       = (string) ( $status['stage'] ?? '' );
		$message     = (string) ( $status['message'] ?? '' );
		$processed   = (int) ( $status['processed'] ?? 0 );
		$total       = (int) ( $status['total'] ?? 0 );
		$started_at  = (int) ( $status['started_at'] ?? 0 );
		$updated_at  = (int) ( $status['updated_at'] ?? 0 );
		$finished_at = (int) ( $status['finished_at'] ?? 0 );
		$is_failed   = $state === Logger::STATE_FAILED;

		?>
		<div
			id="tribe-algolia-sync-status"
			class="tribe-algolia-sync-status<?php echo $is_failed ? ' is-failed' : ''; ?>"
			data-state="<?php echo esc_attr( $state ); ?>"
		>
			<h2><?php echo esc_html__( 'Sync status', 'tribe-algolia-sync' ); ?></h2>

			<p>
				<strong><?php echo esc_html__( 'State:', 'tribe-algolia-sync' ); ?></strong>
				<span class="tribe-algolia-sync-status-state"><?php echo esc_html( $this->get_state_label( $state ) ); ?></span>
			</p>

			<p class="tribe-algolia-sync-status-stage-wrap" <?php echo $stage !== '' ? '' : 'hidden'; ?>>
				<strong><?php echo esc_html__( 'Stage:', 'tribe-algolia-sync' ); ?></strong>
				<span class="tribe-algolia-sync-status-stage"><?php echo esc_html( $this->get_stage_label( $stage ) ); ?></span>
			</p>

			<p class="tribe-algolia-sync-status-message<?php echo $is_failed ? ' tribe-algolia-sync-status-error' : ''; ?>">
				<?php echo esc_html( $message !== '' ? $message : __( 'No sync has run yet.', 'tribe-algolia-sync' ) ); ?>
			</p>

			<p class="tribe-algolia-sync-status-progress" <?php echo ( $processed > 0 || $total > 0 ) ? '' : 'hidden'; ?>>
				<strong><?php echo esc_html__( 'Processed:', 'tribe-algolia-sync' ); ?></strong>
				<span class="tribe-algolia-sync-status-processed"><?php echo esc_html( (string) $processed ); ?></span>
				<span class="tribe-algolia-sync-status-total-wrap" <?php echo $total > 0 ? '' : 'hidden'; ?>>
					/ <span class="tribe-algolia-sync-status-total"><?php echo esc_html( (string) $total ); ?></span>
				</span>
			</p>

			<p class="tribe-algolia-sync-status-started-wrap" <?php echo $started_at > 0 ? '' : 'hidden'; ?>>
				<strong><?php echo esc_html__( 'Started:', 'tribe-algolia-sync' ); ?></strong>
				<span class="tribe-algolia-sync-status-started"><?php echo esc_html( $this->format_time( $started_at ) ); ?></span>
			</p>

			<p class="tribe-algolia-sync-status-updated-wrap" <?php echo $updated_at > 0 ? '' : 'hidden'; ?>>
				<strong><?php echo esc_html__( 'Last update:', 'tribe-algolia-sync' ); ?></strong>
				<span class="tribe-algolia-sync-status-updated"><?php echo esc_html( $this->format_time( $updated_at ) ); ?></span>
			</p>

			<p class="tribe-algolia-sync-status-finished-wrap" <?php echo $finished_at > 0 ? '' : 'hidden'; ?>>
				<strong><?php echo esc_html__( 'Finished:', 'tribe-algolia-sync' ); ?></strong>
				<span class="tribe-algolia-sync-status-finished"><?php echo esc_html( $this->format_time( $finished_at ) ); ?></span>
			</p>

			<?php $this->render_log(); ?>
		</div>
		<?php
	}

	private function render_log(): void {
		$entries = $this->options->get_log();

		?>
		<div id="tribe-algolia-sync-log-wrap" <?php echo $entries === [] ? 'hidden' : ''; ?>>
			<h3><?php echo esc_html__( 'Recent activity', 'tribe-algolia-sync' ); ?></h3>
			<ul id="tribe-algolia-sync-log" class="tribe-algolia-sync-log">
				<?php foreach ( array_reverse( $entries ) as $entry ) : ?>
					<li class="tribe-algolia-sync-log-<?php echo esc_attr( (string) ( $entry['level'] ?? Logger::LEVEL_INFO ) ); ?>">
						<span class="tribe-algolia-sync-log-time"><?php echo esc_html( $this->format_time( (int) ( $entry['time'] ?? 0 ) ) ); ?></span>
						<?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private function get_state_label( string $state ): string {
		$labels = [
			Logger::STATE_IDLE      => __( 'Idle', 'tribe-algolia-sync' ),
			Logger::STATE_RUNNING   => __( 'Running', 'tribe-algolia-sync' ),
			Logger::STATE_COMPLETED => __( 'Completed', 'tribe-algolia-sync' ),
			Logger::STATE_FAILED    => __( 'Failed', 'tribe-algolia-sync' ),
		];

		return $labels[ $state ] ?? $state;
	}

	private function get_stage_label( string $stage ): string {
		$labels = [
			'full_sync' => __( 'Full sync', 'tribe-algolia-sync' ),
			'idle'      => __( 'Idle', 'tribe-algolia-sync' ),
		];

		return $labels[ $stage ] ?? $stage;
	}

	private function format_time( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return '';
		}

		return wp_date( 'Y-m-d H:i:s', $timestamp );
	}

}
