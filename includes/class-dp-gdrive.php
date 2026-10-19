<?php
/**
 * Main plugin bootstrap class.
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive
 */
final class DP_GDrive {

	/**
	 * Singleton instance.
	 *
	 * @var DP_GDrive|null
	 */
	private static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return DP_GDrive
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor. Loads dependencies and hooks.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		$files = array(
			'includes/class-dp-gdrive-templates.php',
			'includes/class-dp-gdrive-shortcodes.php',
			'admin/class-dp-gdrive-admin.php',
		);

		foreach ( $files as $file ) {
			$path = DP_GDRIVE_PATH . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Instantiate components. Each registers its own hooks on construction, so
	 * references are held by the WordPress hook registry.
	 */
	private function init_components() {
		new DP_GDrive_CPT();
		new DP_GDrive_Shortcodes();
		new DP_GDrive_Admin();
	}

	/**
	 * Register global hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'dp-googl-drive', false, dirname( DP_GDRIVE_BASENAME ) . '/languages' );
	}
}
