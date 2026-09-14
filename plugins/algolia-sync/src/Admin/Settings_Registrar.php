<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Admin;

use Tribe\Algolia_Sync\Support\Options;

/**
 * Registers and sanitizes native WordPress settings.
 */
class Settings_Registrar {

	private Options $options;
	private Settings_Fields $fields;

	public function __construct( Options $options, Settings_Fields $fields ) {
		$this->options = $options;
		$this->fields  = $fields;
	}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter(
			'option_page_capability_' . Settings_Page::SETTINGS_GROUP,
			static fn(): string => Settings_Page::CAPABILITY
		);
	}

	public function register_settings(): void {
		$this->register_options();
		$this->register_credentials_section();
		$this->register_content_section();
		$this->register_indexes_section();
	}

	public function sanitize_app_id( mixed $app_id ): string {
		return sanitize_text_field( is_string( $app_id ) ? $app_id : '' );
	}

	public function sanitize_admin_key( mixed $admin_key ): string {
		$admin_key = sanitize_text_field( is_string( $admin_key ) ? $admin_key : '' );

		if ( $admin_key === '' ) {
			return $this->options->get_admin_key();
		}

		return $admin_key;
	}

	/**
	 * @return list<string>
	 */
	public function sanitize_post_types( mixed $raw_post_types ): array {
		if ( ! is_array( $raw_post_types ) ) {
			return [];
		}

		$allowed_post_types = array_keys( $this->get_public_post_types() );
		$post_types         = [];

		foreach ( $raw_post_types as $post_type ) {
			$post_type = sanitize_key( (string) $post_type );

			if ( $post_type === '' || ! in_array( $post_type, $allowed_post_types, true ) ) {
				continue;
			}

			$post_types[] = $post_type;
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * @return list<string>
	 */
	public function sanitize_index_names( mixed $raw_indexes ): array {
		if ( ! is_array( $raw_indexes ) ) {
			return [];
		}

		$index_names = [];

		foreach ( $raw_indexes as $index_name ) {
			$index_name = sanitize_text_field( (string) $index_name );
			$index_name = trim( $index_name );

			if ( $index_name === '' ) {
				continue;
			}

			$index_names[] = $index_name;
		}

		return array_values( array_unique( $index_names ) );
	}

	private function register_options(): void {
		register_setting(
			Settings_Page::SETTINGS_GROUP,
			Options::OPTION_APP_ID,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_app_id' ],
				'default'           => '',
			]
		);

		register_setting(
			Settings_Page::SETTINGS_GROUP,
			Options::OPTION_ADMIN_KEY,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_admin_key' ],
				'default'           => '',
			]
		);

		register_setting(
			Settings_Page::SETTINGS_GROUP,
			Options::OPTION_POST_TYPES,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_post_types' ],
				'default'           => [],
			]
		);

		register_setting(
			Settings_Page::SETTINGS_GROUP,
			Options::OPTION_INDEX_NAMES,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_index_names' ],
				'default'           => [],
			]
		);
	}

	private function register_credentials_section(): void {
		add_settings_section(
			'tribe_algolia_sync_credentials',
			__( 'Algolia credentials', 'tribe-algolia-sync' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Use an Admin API key with write access to the target indexes.', 'tribe-algolia-sync' ) . '</p>';
			},
			Settings_Page::MENU_SLUG
		);

		add_settings_field(
			Options::OPTION_APP_ID,
			__( 'Application ID', 'tribe-algolia-sync' ),
			[ $this->fields, 'render_app_id' ],
			Settings_Page::MENU_SLUG,
			'tribe_algolia_sync_credentials'
		);

		add_settings_field(
			Options::OPTION_ADMIN_KEY,
			__( 'Admin API key', 'tribe-algolia-sync' ),
			[ $this->fields, 'render_admin_key' ],
			Settings_Page::MENU_SLUG,
			'tribe_algolia_sync_credentials'
		);
	}

	private function register_content_section(): void {
		add_settings_section(
			'tribe_algolia_sync_content',
			__( 'Content to sync', 'tribe-algolia-sync' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Choose which public post types should be pushed to Algolia.', 'tribe-algolia-sync' ) . '</p>';
			},
			Settings_Page::MENU_SLUG
		);

		add_settings_field(
			Options::OPTION_POST_TYPES,
			__( 'Post types', 'tribe-algolia-sync' ),
			[ $this->fields, 'render_post_types' ],
			Settings_Page::MENU_SLUG,
			'tribe_algolia_sync_content'
		);
	}

	private function register_indexes_section(): void {
		add_settings_section(
			'tribe_algolia_sync_indexes',
			__( 'Indexes', 'tribe-algolia-sync' ),
			static function (): void {
				printf(
					'<p>%s</p>',
					esc_html(
						sprintf(
							/* translators: %s: current WP environment type */
							__( 'Enter base index names. The current environment (%s) is appended automatically at sync time.', 'tribe-algolia-sync' ),
							wp_get_environment_type()
						)
					)
				);
			},
			Settings_Page::MENU_SLUG
		);

		add_settings_field(
			Options::OPTION_INDEX_NAMES,
			__( 'Index names', 'tribe-algolia-sync' ),
			[ $this->fields, 'render_index_names' ],
			Settings_Page::MENU_SLUG,
			'tribe_algolia_sync_indexes'
		);
	}

	/**
	 * @return array<string, \WP_Post_Type>
	 */
	private function get_public_post_types(): array {
		$types = get_post_types( [ 'public' => true ], 'objects' );

		return is_array( $types ) ? $types : [];
	}

}
