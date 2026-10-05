<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Support;

/**
 * Option keys and typed getters for plugin settings / sync status.
 */
class Options {

	public const OPTION_APP_ID      = 'tribe_algolia_sync_app_id';
	public const OPTION_ADMIN_KEY   = 'tribe_algolia_sync_admin_key';
	public const OPTION_POST_TYPES  = 'tribe_algolia_sync_post_types';
	public const OPTION_INDEX_NAMES = 'tribe_algolia_sync_index_names';
	public const OPTION_STATUS      = 'tribe_algolia_sync_status';
	public const OPTION_LOG         = 'tribe_algolia_sync_log';

	public function get_app_id(): string {
		$app_id = get_option( self::OPTION_APP_ID, '' );

		return is_string( $app_id ) ? $app_id : '';
	}

	public function get_admin_key(): string {
		$admin_key = get_option( self::OPTION_ADMIN_KEY, '' );

		return is_string( $admin_key ) ? $admin_key : '';
	}

	/**
	 * @return list<string>
	 */
	public function get_post_types(): array {
		$post_types = get_option( self::OPTION_POST_TYPES, [] );

		if ( ! is_array( $post_types ) ) {
			return [];
		}

		return array_values( array_filter( $post_types, 'is_string' ) );
	}

	/**
	 * @return list<string>
	 */
	public function get_index_names(): array {
		$index_names = get_option( self::OPTION_INDEX_NAMES, [] );

		if ( ! is_array( $index_names ) ) {
			return [];
		}

		return array_values( array_filter( $index_names, 'is_string' ) );
	}

	public function has_credentials(): bool {
		return $this->get_app_id() !== '' && $this->get_admin_key() !== '';
	}

	public function is_ready_to_sync(): bool {
		return $this->has_credentials()
			&& $this->get_post_types() !== []
			&& $this->get_index_names() !== [];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_status(): array {
		$status = get_option( self::OPTION_STATUS, [] );

		return is_array( $status ) ? $status : [];
	}

	/**
	 * @param array<string, mixed> $status
	 */
	public function update_status( array $status ): void {
		update_option( self::OPTION_STATUS, $status, false );
	}

	/**
	 * @return list<array{time: int, level: string, message: string}>
	 */
	public function get_log(): array {
		$log = get_option( self::OPTION_LOG, [] );

		if ( ! is_array( $log ) ) {
			return [];
		}

		return array_values( array_filter( $log, 'is_array' ) );
	}

	/**
	 * @param list<array{time: int, level: string, message: string}> $entries
	 */
	public function update_log( array $entries ): void {
		update_option( self::OPTION_LOG, $entries, false );
	}

}
