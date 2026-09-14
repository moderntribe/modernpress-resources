<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Admin;

use Tribe\Algolia_Sync\Algolia\Index_Resolver;
use Tribe\Algolia_Sync\Support\Options;

/**
 * Renders fields registered on the Algolia Sync settings page.
 */
class Settings_Fields {

	private Options $options;
	private Index_Resolver $index_resolver;

	public function __construct( Options $options, Index_Resolver $index_resolver ) {
		$this->options        = $options;
		$this->index_resolver = $index_resolver;
	}

	public function render_app_id(): void {
		printf(
			'<input type="text" class="regular-text" name="%1$s" value="%2$s" autocomplete="off" />',
			esc_attr( Options::OPTION_APP_ID ),
			esc_attr( $this->options->get_app_id() )
		);
	}

	public function render_admin_key(): void {
		$has_key = $this->options->get_admin_key() !== '';

		printf(
			'<input type="password" class="regular-text" name="%1$s" value="" autocomplete="new-password" placeholder="%2$s" />',
			esc_attr( Options::OPTION_ADMIN_KEY ),
			esc_attr( $has_key ? __( '•••••••• (saved — leave blank to keep)', 'tribe-algolia-sync' ) : '' )
		);

		if ( $has_key ) {
			echo '<p class="description">' . esc_html__( 'Leave blank to keep the existing Admin API key.', 'tribe-algolia-sync' ) . '</p>';
		}
	}

	public function render_post_types(): void {
		$selected = $this->options->get_post_types();

		echo '<fieldset>';
		printf(
			'<input type="hidden" name="%s[]" value="" />',
			esc_attr( Options::OPTION_POST_TYPES )
		);

		foreach ( $this->get_public_post_types() as $name => $object ) {
			$id = 'tribe-algolia-sync-pt-' . $name;
			printf(
				'<label for="%1$s" style="display:block;margin-bottom:0.35em;"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$s" %4$s /> %5$s <code>%3$s</code></label>',
				esc_attr( $id ),
				esc_attr( Options::OPTION_POST_TYPES ),
				esc_attr( $name ),
				checked( in_array( $name, $selected, true ), true, false ),
				esc_html( $object->labels->singular_name )
			);
		}

		echo '</fieldset>';
	}

	public function render_index_names(): void {
		$names = $this->options->get_index_names();

		if ( $names === [] ) {
			$names = [ '' ];
		}

		echo '<div id="tribe-algolia-sync-indexes" class="tribe-algolia-sync-indexes" data-environment="' . esc_attr( wp_get_environment_type() ) . '">';

		foreach ( $names as $name ) {
			$this->render_index_row( $name );
		}

		echo '</div>';
		echo '<p><button type="button" class="button" id="tribe-algolia-sync-add-index">' . esc_html__( 'Add index', 'tribe-algolia-sync' ) . '</button></p>';
		echo '<template id="tribe-algolia-sync-index-row-template">';
		$this->render_index_row( '' );
		echo '</template>';
	}

	private function render_index_row( string $name ): void {
		$resolved = $name !== '' ? $this->index_resolver->resolve( $name ) : '';

		?>
		<div class="tribe-algolia-sync-index-row">
			<input
				type="text"
				class="regular-text tribe-algolia-sync-index-input"
				name="<?php echo esc_attr( Options::OPTION_INDEX_NAMES . '[]' ); ?>"
				value="<?php echo esc_attr( $name ); ?>"
				placeholder="<?php echo esc_attr__( 'e.g. general', 'tribe-algolia-sync' ); ?>"
			/>
			<button type="button" class="button tribe-algolia-sync-remove-index"><?php echo esc_html__( 'Remove', 'tribe-algolia-sync' ); ?></button>
			<p class="description tribe-algolia-sync-index-preview">
				<?php
				if ( $resolved !== '' ) {
					echo esc_html(
						sprintf(
							/* translators: %s: resolved index name with environment suffix */
							__( 'Resolves to: %s', 'tribe-algolia-sync' ),
							$resolved
						)
					);
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * @return array<string, \WP_Post_Type>
	 */
	private function get_public_post_types(): array {
		$types = get_post_types( [ 'public' => true ], 'objects' );

		return is_array( $types ) ? $types : [];
	}

}
