<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Admin;

use Tribe\Algolia_Sync\Support\Logger;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Registers and renders the Algolia Sync admin page.
 */
class Settings_Page {

	public const MENU_SLUG      = 'tribe-algolia-sync';
	public const CAPABILITY     = 'edit_pages';
	public const SETTINGS_GROUP = 'tribe_algolia_sync_group';

	private Options $options;
	private Sync_Controls $sync_controls;
	private string $plugin_url;
	private string $version;

	public function __construct(
		Options $options,
		Sync_Controls $sync_controls,
		string $plugin_url,
		string $version
	) {
		$this->options       = $options;
		$this->sync_controls = $sync_controls;
		$this->plugin_url    = $plugin_url;
		$this->version       = $version;
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu_page(): void {
		add_menu_page(
			__( 'Algolia Sync', 'tribe-algolia-sync' ),
			__( 'Algolia Sync', 'tribe-algolia-sync' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_page' ],
			'dashicons-search'
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== 'toplevel_page_' . self::MENU_SLUG ) {
			return;
		}

		wp_enqueue_style(
			'tribe-algolia-sync-admin',
			$this->plugin_url . '/assets/admin-sync.css',
			[],
			$this->version
		);

		wp_enqueue_script(
			'tribe-algolia-sync-admin',
			$this->plugin_url . '/assets/admin-sync.js',
			[],
			$this->version,
			true
		);

		$status = $this->options->get_status();

		wp_localize_script(
			'tribe-algolia-sync-admin',
			'tribeAlgoliaSync',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( Sync_Ajax::NONCE_ACTION ),
				'actions'     => [
					'start' => Sync_Ajax::ACTION_START,
					'batch' => Sync_Ajax::ACTION_BATCH,
				],
				'environment' => wp_get_environment_type(),
				'canSync'     => $this->options->is_ready_to_sync(),
				'isRunning'   => ( (string) ( $status['state'] ?? '' ) ) === Logger::STATE_RUNNING,
				'i18n'        => [
					'stayOnPage'   => __( 'Stay on this page until the sync finishes. Do not navigate away, close the tab, or refresh — leaving will interrupt the sync.', 'tribe-algolia-sync' ),
					'notReady'     => __( 'Save App ID, Admin API key, at least one post type, and at least one index before running a sync.', 'tribe-algolia-sync' ),
					'syncing'      => __( 'Sync in progress…', 'tribe-algolia-sync' ),
					'completed'    => __( 'Full sync completed.', 'tribe-algolia-sync' ),
					'failed'       => __( 'Full sync failed.', 'tribe-algolia-sync' ),
					'addIndex'     => __( 'Add index', 'tribe-algolia-sync' ),
					'removeIndex'  => __( 'Remove', 'tribe-algolia-sync' ),
					'indexPreview' => __( 'Resolves to: %s', 'tribe-algolia-sync' ),
					'stateIdle'    => __( 'Idle', 'tribe-algolia-sync' ),
					'stateRunning' => __( 'Running', 'tribe-algolia-sync' ),
					'stateCompleted' => __( 'Completed', 'tribe-algolia-sync' ),
					'stateFailed'  => __( 'Failed', 'tribe-algolia-sync' ),
					'stageFullSync'=> __( 'Full sync', 'tribe-algolia-sync' ),
				],
			]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		?>
		<div class="wrap tribe-algolia-sync-wrap">
			<h1><?php echo esc_html__( 'Algolia Sync', 'tribe-algolia-sync' ); ?></h1>

			<?php if ( ! class_exists( \Algolia\AlgoliaSearch\Api\SearchClient::class ) ) : ?>
				<div class="notice notice-error">
					<p><?php echo esc_html__( 'Algolia PHP SDK is missing. Run composer install inside the plugin directory before syncing.', 'tribe-algolia-sync' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Sync status reset.', 'tribe-algolia-sync' ); ?></p></div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::MENU_SLUG );
				submit_button( __( 'Save settings', 'tribe-algolia-sync' ) );
				?>
			</form>

			<?php $this->sync_controls->render(); ?>
		</div>
		<?php
	}

}
