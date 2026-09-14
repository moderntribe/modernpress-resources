<?php declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = [
	'tribe_algolia_sync_app_id',
	'tribe_algolia_sync_admin_key',
	'tribe_algolia_sync_post_types',
	'tribe_algolia_sync_index_names',
	'tribe_algolia_sync_status',
	'tribe_algolia_sync_log',
];

foreach ( $options as $option ) {
	delete_option( $option );
}
