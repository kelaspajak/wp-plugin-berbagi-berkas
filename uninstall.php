<?php
/**
 * Uninstall routine.
 *
 * Bookmarks and settings are only removed when the "delete data" setting is
 * enabled, matching the behaviour of the other DP plugins.
 *
 * @package DP_GDrive
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$dp_gdrive_settings = get_option( 'dp_gdrive_settings', array() );

if ( is_array( $dp_gdrive_settings ) && ! empty( $dp_gdrive_settings['delete_data'] ) ) {
	$dp_gdrive_links = get_posts(
		array(
			'post_type'      => 'dp_gdrive_link',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $dp_gdrive_links as $dp_gdrive_link_id ) {
		wp_delete_post( $dp_gdrive_link_id, true );
	}

	delete_option( 'dp_gdrive_settings' );

	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dp_gdrive_%' OR option_name LIKE '_transient_timeout_dp_gdrive_%'" );
}
