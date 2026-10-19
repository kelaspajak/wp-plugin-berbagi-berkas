<?php
/**
 * Plugin Name:       DP Google Drive
 * Description:       Tempel link Google Drive menjadi bookmark. Link folder ditampilkan per item dengan tombol unduh. Kiriman pengunjung menunggu persetujuan admin.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            DP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dp-googl-drive
 * Domain Path:       /languages
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

define( 'DP_GDRIVE_VERSION', '1.0.0' );
define( 'DP_GDRIVE_FILE', __FILE__ );
define( 'DP_GDRIVE_PATH', plugin_dir_path( __FILE__ ) );
define( 'DP_GDRIVE_URL', plugin_dir_url( __FILE__ ) );
define( 'DP_GDRIVE_BASENAME', plugin_basename( __FILE__ ) );

require_once DP_GDRIVE_PATH . 'includes/class-dp-gdrive-settings.php';
require_once DP_GDRIVE_PATH . 'includes/class-dp-gdrive-cpt.php';
require_once DP_GDRIVE_PATH . 'includes/class-dp-gdrive-drive.php';
require_once DP_GDRIVE_PATH . 'includes/class-dp-gdrive-activator.php';
require_once DP_GDRIVE_PATH . 'includes/class-dp-gdrive.php';

/**
 * Retrieve the main plugin instance.
 *
 * @return DP_GDrive
 */
function dp_gdrive() {
	return DP_GDrive::instance();
}

add_action( 'plugins_loaded', 'dp_gdrive', 5 );

register_activation_hook( __FILE__, array( 'DP_GDrive_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DP_GDrive_Activator', 'deactivate' ) );
