<?php
/**
 * Front-end rendering: the bookmark list and its Drive items.
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive_Templates
 */
class DP_GDrive_Templates {

	/**
	 * Render the list of approved bookmarks.
	 *
	 * Only the first batch is rendered; the rest is loaded lazily over AJAX.
	 *
	 * @param bool     $show_title Whether to print each bookmark's title.
	 * @param int|null $per_page   Bookmarks per batch; null falls back to the setting.
	 * @return string
	 */
	public static function render_list( $show_title = true, $per_page = null ) {
		if ( null === $per_page ) {
			$per_page = (int) DP_GDrive_Settings::get( 'per_page', 5 );
		}
		$per_page = max( 1, (int) $per_page );

		$bookmarks = self::published_bookmarks();
		$total     = count( $bookmarks );

		if ( 0 === $total ) {
			return '<div class="dp-gdrive dp-gdrive--empty">' . esc_html__( 'Belum ada bookmark.', 'dp-googl-drive' ) . '</div>';
		}

		$slice = array_slice( $bookmarks, 0, $per_page );
		$offset = count( $slice );

		$html  = '<div class="dp-gdrive" data-dp-gdrive-list data-show-title="' . ( $show_title ? '1' : '0' ) . '" data-per-page="' . esc_attr( $per_page ) . '" data-offset="' . esc_attr( $offset ) . '" data-total="' . esc_attr( $total ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'dp_gdrive_list' ) ) . '">';
		$html .= self::render_bookmarks( $slice, $show_title );
		$html .= '</div>';

		if ( $total > $offset ) {
			$html .= self::load_more();
		}

		return $html;
	}

	/**
	 * All published bookmarks, newest first.
	 *
	 * Audience restrictions never hide a bookmark; they only gate its downloads.
	 *
	 * @return WP_Post[]
	 */
	public static function published_bookmarks() {
		return get_posts(
			array(
				'post_type'      => DP_GDrive_CPT::LINK,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Render a set of bookmark sections.
	 *
	 * @param WP_Post[] $posts      Bookmarks.
	 * @param bool      $show_title Whether to print each bookmark's title.
	 * @return string
	 */
	public static function render_bookmarks( $posts, $show_title = true ) {
		$html = '';

		foreach ( $posts as $post ) {
			$html .= self::render_bookmark( $post, $show_title, self::can_download_bookmark( $post ) );
		}

		return $html;
	}

	/**
	 * The "load more" control used for lazy loading.
	 *
	 * @return string
	 */
	protected static function load_more() {
		return '<div class="dp-gdrive-loadmore" data-dp-gdrive-loadmore>'
			. '<button type="button" class="dp-gdrive-loadmore__btn dp-gdrive-button">' . esc_html__( 'Muat lagi', 'dp-googl-drive' ) . '</button>'
			. '<span class="dp-gdrive-loadmore__status" hidden>' . esc_html__( 'Memuat…', 'dp-googl-drive' ) . '</span>'
			. '</div>';
	}

	/**
	 * Whether the current visitor may download files in this bookmark.
	 *
	 * The bookmark stays visible to everyone; this only controls the download link.
	 *
	 * @param WP_Post $post Bookmark post.
	 * @return bool
	 */
	public static function can_download_bookmark( $post ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$audience = (string) get_post_meta( $post->ID, '_dp_gdrive_audience', true );

		if ( 'logged_in' === $audience ) {
			return is_user_logged_in();
		}

		if ( 'roles' === $audience ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$roles = array_filter( array_map( 'sanitize_key', (array) get_post_meta( $post->ID, '_dp_gdrive_roles', true ) ) );
			if ( empty( $roles ) ) {
				return false;
			}

			$user = wp_get_current_user();

			return (bool) array_intersect( (array) $user->roles, $roles );
		}

		// Default / public.
		return true;
	}

	/**
	 * Render a single bookmark (a file or a folder and its children).
	 *
	 * @param WP_Post $post         Bookmark post.
	 * @param bool    $show_title   Whether to print the bookmark title.
	 * @param bool    $can_download Whether the visitor may download its files.
	 * @return string
	 */
	protected static function render_bookmark( $post, $show_title = true, $can_download = true ) {
		$type        = (string) get_post_meta( $post->ID, '_dp_gdrive_type', true );
		$id          = (string) get_post_meta( $post->ID, '_dp_gdrive_id', true );
		$resourcekey = (string) get_post_meta( $post->ID, '_dp_gdrive_resourcekey', true );
		$title       = get_the_title( $post );

		$audience = (string) get_post_meta( $post->ID, '_dp_gdrive_audience', true );
		$roles    = array_filter( array_map( 'sanitize_key', (array) get_post_meta( $post->ID, '_dp_gdrive_roles', true ) ) );

		$classes = 'dp-gdrive-bookmark';
		if ( 'roles' === $audience && in_array( 'author', $roles, true ) ) {
			$classes .= ' dp-gdrive-bookmark--role-author';
		}

		$html = '<section class="' . esc_attr( $classes ) . '" data-dp-gdrive="1" data-title="' . esc_attr( $title ) . '" data-id="' . esc_attr( $id ) . '" data-post="' . esc_attr( $post->ID ) . '">';

		if ( $show_title ) {
			$html .= '<h3 class="dp-gdrive-bookmark__title">' . esc_html( $title ) . '</h3>';
		}

		if ( 'folder' === $type ) {
			$data = DP_GDrive_Drive::fetch_folder( $id, $resourcekey );

			if ( is_wp_error( $data ) ) {
				$html .= '<p class="dp-gdrive-error">' . esc_html( $data->get_error_message() ) . '</p>';
			} elseif ( empty( $data['items'] ) ) {
				$html .= '<p class="dp-gdrive-empty">' . esc_html__( 'Folder kosong.', 'dp-googl-drive' ) . '</p>';
			} else {
				$file_audience = (array) get_post_meta( $post->ID, '_dp_gdrive_file_audience', true );
				$html         .= '<nav class="dp-gdrive-breadcrumb" aria-label="' . esc_attr__( 'Lokasi folder', 'dp-googl-drive' ) . '"></nav>';
				$html         .= '<div class="dp-gdrive-view">' . self::render_items( $data['items'], true, $file_audience, $can_download ) . '</div>';
			}
		} else {
			$html .= '<div class="dp-gdrive-view">' . self::render_items(
				array(
					array(
						'id'        => $id,
						'name'      => $title,
						'mime'      => '',
						'is_folder' => false,
						'modified'  => '',
						'download'  => DP_GDrive_Drive::download_url( $id ),
					),
				),
				false,
				array(),
				$can_download
			) . '</div>';
		}

		$html .= '</section>';

		return $html;
	}

	/**
	 * Render the item table for a bookmark or a folder level.
	 *
	 * @param array $items           Items from DP_GDrive_Drive.
	 * @param bool  $skip_bookmarked Skip items that are their own published bookmark.
	 * @param array $file_audience   Map of file ID => allowed role slugs.
	 * @param bool  $can_download    Whether the bookmark allows the visitor to download.
	 * @return string
	 */
	public static function render_items( $items, $skip_bookmarked = true, $file_audience = array(), $can_download = true ) {
		$nonce = wp_create_nonce( 'dp_gdrive_expand' );
		$skip  = $skip_bookmarked ? self::bookmark_ids() : array();
		$items = self::sort_items( $items );

		$html  = '<div class="dp-gdrive-table-wrap" data-nonce="' . esc_attr( $nonce ) . '">';
		$html .= '<table class="dp-gdrive-table">';
		$html .= '<thead><tr>';
		$html .= '<th scope="col">' . esc_html__( 'Nama', 'dp-googl-drive' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Diubah', 'dp-googl-drive' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Aksi', 'dp-googl-drive' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		$rows = '';

		foreach ( $items as $item ) {
			$item_id = isset( $item['id'] ) ? (string) $item['id'] : '';

			if ( '' !== $item_id && isset( $skip[ $item_id ] ) ) {
				continue;
			}

			$is_folder = ! empty( $item['is_folder'] );
			$name      = isset( $item['name'] ) ? $item['name'] : '';

			if ( $is_folder ) {
				$name_cell = '<button type="button" class="dp-gdrive-dir" data-id="' . esc_attr( $item_id ) . '" data-name="' . esc_attr( $name ) . '">'
					. self::item_icon( $item ) . '<span class="dp-gdrive-dir__name">' . esc_html( $name ) . '</span></button>';
				$action = '<span class="dp-gdrive-chevron" aria-hidden="true">&rsaquo;</span>';
			} else {
				$name_cell = '<span class="dp-gdrive-name">' . self::item_icon( $item ) . esc_html( $name ) . '</span>';
				$action    = self::download_action( $item, $item_id, $file_audience, $can_download );
			}

			$rows .= '<tr class="dp-gdrive-row' . ( $is_folder ? ' dp-gdrive-row--folder' : '' ) . '">';
			$rows .= '<td class="dp-gdrive-cell-name">' . $name_cell . '</td>';
			$rows .= '<td class="dp-gdrive-cell-modified">' . esc_html( isset( $item['modified'] ) ? $item['modified'] : '' ) . '</td>';
			$rows .= '<td class="dp-gdrive-cell-action">' . $action . '</td>';
			$rows .= '</tr>';
		}

		if ( '' === $rows ) {
			$html .= '<tr><td colspan="3" class="dp-gdrive-empty">' . esc_html__( 'Tidak ada item untuk ditampilkan.', 'dp-googl-drive' ) . '</td></tr>';
		} else {
			$html .= $rows;
		}

		$html .= '</tbody></table></div>';

		return $html;
	}

	/**
	 * The download action cell: an active link, or a locked button when not allowed.
	 *
	 * @param array  $item          Item data.
	 * @param string $item_id       Drive file ID.
	 * @param array  $file_audience Map of file ID => allowed role slugs.
	 * @param bool   $can_download  Bookmark-level download permission.
	 * @return string
	 */
	protected static function download_action( $item, $item_id, $file_audience, $can_download ) {
		if ( self::item_downloadable( $item_id, $file_audience, $can_download ) ) {
			$url = isset( $item['download'] ) ? (string) $item['download'] : '';

			return '<a class="dp-gdrive-button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Unduh', 'dp-googl-drive' ) . '</a>';
		}

		return '<span class="dp-gdrive-button dp-gdrive-button--locked" aria-disabled="true" title="' . esc_attr( self::lock_title() ) . '">' . esc_html__( 'Unduh', 'dp-googl-drive' ) . '</span>';
	}

	/**
	 * Whether a specific file may be downloaded by the current visitor.
	 *
	 * @param string $item_id       Drive file ID.
	 * @param array  $file_audience Map of file ID => allowed role slugs.
	 * @param bool   $can_download  Bookmark-level download permission.
	 * @return bool
	 */
	protected static function item_downloadable( $item_id, $file_audience, $can_download ) {
		if ( ! $can_download ) {
			return false;
		}

		if ( '' !== $item_id && isset( $file_audience[ $item_id ] ) && ! self::is_role_allowed( $file_audience[ $item_id ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Tooltip shown on a locked download button.
	 *
	 * @return string
	 */
	protected static function lock_title() {
		if ( ! is_user_logged_in() ) {
			return __( 'Silakan login untuk mengunduh file ini.', 'dp-googl-drive' );
		}

		return __( 'Peran Anda tidak memiliki akses untuk mengunduh file ini.', 'dp-googl-drive' );
	}

	/**
	 * Sort items: folders first, then by name (case-insensitive).
	 *
	 * @param array $items Items to sort.
	 * @return array
	 */
	protected static function sort_items( $items ) {
		usort(
			$items,
			function ( $a, $b ) {
				$af = empty( $a['is_folder'] ) ? 1 : 0;
				$bf = empty( $b['is_folder'] ) ? 1 : 0;

				if ( $af !== $bf ) {
					return $af - $bf;
				}

				return strcasecmp( isset( $a['name'] ) ? $a['name'] : '', isset( $b['name'] ) ? $b['name'] : '' );
			}
		);

		return $items;
	}

	/**
	 * Whether the current user may see a file restricted to the given roles.
	 *
	 * @param array $roles Allowed role slugs.
	 * @return bool
	 */
	protected static function is_role_allowed( $roles ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		foreach ( (array) $roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Drive IDs of published bookmarks, keyed for O(1) lookup.
	 *
	 * @return array
	 */
	protected static function bookmark_ids() {
		static $ids = null;

		if ( null !== $ids ) {
			return $ids;
		}

		global $wpdb;

		$ids  = array();
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'",
				'_dp_gdrive_id',
				DP_GDrive_CPT::LINK
			)
		);

		foreach ( (array) $rows as $value ) {
			$value = (string) $value;

			if ( '' !== $value ) {
				$ids[ $value ] = true;
			}
		}

		return $ids;
	}

	/**
	 * The icon markup for an item: preview thumbnail, else type icon, else badge.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	protected static function item_icon( $item ) {
		$thumb = isset( $item['thumb'] ) ? (string) $item['thumb'] : '';
		$icon  = isset( $item['icon'] ) ? (string) $item['icon'] : '';
		$src   = '' !== $thumb ? $thumb : $icon;

		if ( '' === $src && ! empty( $item['mime'] ) ) {
			$src = 'https://drive-thirdparty.googleusercontent.com/16/type/' . rawurlencode( $item['mime'] );
		}

		if ( '' === $src ) {
			return '';
		}

		return '<img class="dp-gdrive-icon" src="' . esc_url( $src ) . '" alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer" />';
	}
}
