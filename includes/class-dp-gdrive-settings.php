<?php
/**
 * Plugin settings storage.
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive_Settings
 */
class DP_GDrive_Settings {

	const OPTION = 'dp_gdrive_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'per_page'           => 5,
			'cache_lifetime'     => 6,
			'allow_guest_submit' => 1,
			'delete_data'        => 0,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist default settings on activation.
	 */
	public static function set_defaults() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}
}
