<?php
/**
 * Custom post type: a Drive bookmark.
 *
 * A bookmark is a post whose status doubles as the moderation state:
 * `pending` = submitted by a visitor and awaiting approval, `publish` =
 * approved and listed on the front end.
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive_CPT
 */
class DP_GDrive_CPT {

	const LINK = 'dp_gdrive_link';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Register the bookmark post type.
	 */
	public function register_post_types() {
		register_post_type(
			self::LINK,
			array(
				'labels'          => array(
					'name'               => __( 'Bookmark Drive', 'dp-googl-drive' ),
					'singular_name'      => __( 'Bookmark Drive', 'dp-googl-drive' ),
					'add_new'            => __( 'Tambah Link', 'dp-googl-drive' ),
					'add_new_item'       => __( 'Tambah Bookmark Drive', 'dp-googl-drive' ),
					'edit_item'          => __( 'Edit Bookmark Drive', 'dp-googl-drive' ),
					'new_item'           => __( 'Bookmark Baru', 'dp-googl-drive' ),
					'view_item'          => __( 'Lihat Bookmark', 'dp-googl-drive' ),
					'search_items'       => __( 'Cari Bookmark', 'dp-googl-drive' ),
					'not_found'          => __( 'Belum ada bookmark.', 'dp-googl-drive' ),
					'not_found_in_trash' => __( 'Tidak ada bookmark di Trash.', 'dp-googl-drive' ),
					'all_items'          => __( 'Bookmark', 'dp-googl-drive' ),
					'menu_name'          => __( 'DP Google Drive', 'dp-googl-drive' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'dp-googl-drive',
				'menu_icon'       => 'dashicons-google',
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
			)
		);
	}
}
