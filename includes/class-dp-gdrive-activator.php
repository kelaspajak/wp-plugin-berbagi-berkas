<?php
/**
 * Activation / deactivation routines.
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive_Activator
 */
class DP_GDrive_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		$cpt = new DP_GDrive_CPT();
		$cpt->register_post_types();

		DP_GDrive_Settings::set_defaults();

		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
