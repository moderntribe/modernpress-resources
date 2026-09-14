<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Admin;

use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Renders full-sync controls and handles status reset requests.
 */
class Sync_Controls {

	public const RESET_ACTION = 'tribe_algolia_sync_reset_status';

	private Options $options;
	private Logger $logger;
	private Status_Panel $status_panel;

	public function __construct( Options $options, Logger $logger, Status_Panel $status_panel ) {
		$this->options      = $options;
		$this->logger       = $logger;
		$this->status_panel = $status_panel;
	}

	public function register(): void {
		add_action( 'admin_post_' . self::RESET_ACTION, [ $this, 'handle_reset_status' ] );
	}

	public function handle_reset_status(): void {
		if ( ! current_user_can( Settings_Page::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to reset sync status.', 'tribe-algolia-sync' ) );
		}

		check_admin_referer( self::RESET_ACTION );
		$this->logger->reset();

		wp_safe_redirect(
			add_query_arg(
				[
					'page'  => Settings_Page::MENU_SLUG,
					'reset' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render(): void {
		$can_sync   = $this->options->is_ready_to_sync();
		$status     = $this->options->get_status();
		$state      = (string) ( $status['state'] ?? Logger::STATE_IDLE );
		$is_running = $state === Logger::STATE_RUNNING;
		$is_failed  = $state === Logger::STATE_FAILED;

		?>
		<hr />

		<h2><?php echo esc_html__( 'Full sync', 'tribe-algolia-sync' ); ?></h2>

		<div class="tribe-algolia-sync-warning <?php echo $is_running ? 'is-running' : ''; ?>" role="alert">
			<p>
				<strong><?php echo esc_html__( 'Important:', 'tribe-algolia-sync' ); ?></strong>
				<?php echo esc_html__( 'Stay on this page until the sync finishes. Do not navigate away, close the tab, or refresh — leaving will interrupt the sync.', 'tribe-algolia-sync' ); ?>
			</p>
		</div>

		<?php if ( $is_running ) : ?>
			<div class="notice notice-warning inline">
				<p><?php echo esc_html__( 'A sync is marked as running. If you left this page earlier, use Reset status before starting again.', 'tribe-algolia-sync' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $is_failed ) : ?>
			<div class="notice notice-error inline">
				<p><?php echo esc_html__( 'The last sync failed. Check Recent activity below, fix the issue, then Reset status if needed before retrying.', 'tribe-algolia-sync' ); ?></p>
			</div>
		<?php endif; ?>

		<p>
			<button
				type="button"
				class="button button-primary"
				id="tribe-algolia-sync-run"
				<?php disabled( ! $can_sync || $is_running ); ?>
			>
				<?php echo esc_html__( 'Run full sync', 'tribe-algolia-sync' ); ?>
			</button>
		</p>

		<?php if ( ! $can_sync ) : ?>
			<p class="description">
				<?php echo esc_html__( 'Save App ID, Admin API key, at least one post type, and at least one index before running a sync.', 'tribe-algolia-sync' ); ?>
			</p>
		<?php endif; ?>

		<?php $this->status_panel->render(); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tribe-algolia-sync-reset">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>" />
			<?php wp_nonce_field( self::RESET_ACTION ); ?>
			<?php
			submit_button(
				__( 'Reset status', 'tribe-algolia-sync' ),
				'secondary',
				'submit',
				false,
				[
					'title' => __( 'Clear running/failed status and activity log', 'tribe-algolia-sync' ),
				]
			);
			?>
		</form>
		<?php
	}

}
