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
	 * @return string
	 */
	public static function render_list() {
		$per_page = max( 1, (int) DP_GDrive_Settings::get( 'per_page', 20 ) );
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		$posts = get_posts(
			array(
				'post_type'      => DP_GDrive_CPT::LINK,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		// Audience filter: admins see everything; everyone else is filtered.
		$visible = array();
		foreach ( $posts as $post ) {
			if ( self::is_visible( $post ) ) {
				$visible[] = $post;
			}
		}

		$total = count( $visible );
		$pages = (int) max( 1, ceil( $total / $per_page ) );
		$paged = max( 1, min( $paged, $pages ) );
		$slice = array_slice( $visible, ( $paged - 1 ) * $per_page, $per_page );

		if ( empty( $slice ) ) {
			return '<div class="dp-gdrive dp-gdrive--empty">' . esc_html__( 'Belum ada bookmark.', 'dp-googl-drive' ) . '</div>';
		}

		$html = '<div class="dp-gdrive">';

		foreach ( $slice as $post ) {
			$html .= self::render_bookmark( $post );
		}

		$html .= '</div>';
		$html .= self::pagination( $paged, $pages );

		return $html;
	}

	/**
	 * Whether the current visitor may see a bookmark, based on its audience.
	 *
	 * @param WP_Post $post Bookmark post.
	 * @return bool
	 */
	protected static function is_visible( $post ) {
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
	 * @param WP_Post $post Bookmark post.
	 * @return string
	 */
	protected static function render_bookmark( $post ) {
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

		$html  = '<section class="' . esc_attr( $classes ) . '" data-dp-gdrive="1" data-title="' . esc_attr( $title ) . '" data-id="' . esc_attr( $id ) . '" data-post="' . esc_attr( $post->ID ) . '">';
		$html .= '<h3 class="dp-gdrive-bookmark__title">' . esc_html( $title ) . '</h3>';

		if ( 'folder' === $type ) {
			$data = DP_GDrive_Drive::fetch_folder( $id, $resourcekey );

			if ( is_wp_error( $data ) ) {
				$html .= '<p class="dp-gdrive-error">' . esc_html( $data->get_error_message() ) . '</p>';
			} elseif ( empty( $data['items'] ) ) {
				$html .= '<p class="dp-gdrive-empty">' . esc_html__( 'Folder kosong.', 'dp-googl-drive' ) . '</p>';
			} else {
				$file_audience = (array) get_post_meta( $post->ID, '_dp_gdrive_file_audience', true );
				$html         .= '<nav class="dp-gdrive-breadcrumb" aria-label="' . esc_attr__( 'Lokasi folder', 'dp-googl-drive' ) . '"></nav>';
				$html         .= '<div class="dp-gdrive-view">' . self::render_items( $data['items'], true, $file_audience ) . '</div>';
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
				false
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
	 * @param array $file_audience   Map of file ID => allowed role slugs (locked when not allowed).
	 * @return string
	 */
	public static function render_items( $items, $skip_bookmarked = true, $file_audience = array() ) {
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
			$locked    = ! $is_folder && '' !== $item_id && isset( $file_audience[ $item_id ] ) && ! self::is_role_allowed( $file_audience[ $item_id ] );

			if ( $is_folder ) {
				$name_cell = '<button type="button" class="dp-gdrive-dir" data-id="' . esc_attr( $item_id ) . '" data-name="' . esc_attr( $name ) . '">'
					. self::item_icon( $item ) . '<span class="dp-gdrive-dir__name">' . esc_html( $name ) . '</span></button>';
				$action = '<span class="dp-gdrive-chevron" aria-hidden="true">&rsaquo;</span>';
			} elseif ( $locked ) {
				$name_cell = '<span class="dp-gdrive-name">' . self::item_icon( $item ) . esc_html( $name ) . '</span>';
				$action    = '<span class="dp-gdrive-lock">' . esc_html__( 'Terkunci', 'dp-googl-drive' ) . '</span>';
			} else {
				$name_cell = '<span class="dp-gdrive-name">' . self::item_icon( $item ) . esc_html( $name ) . '</span>';
				$action = '<a class="dp-gdrive-button" href="' . esc_url( $item['download'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Unduh', 'dp-googl-drive' ) . '</a>';
			}

			$rows .= '<tr class="dp-gdrive-row' . ( $is_folder ? ' dp-gdrive-row--folder' : '' ) . ( $locked ? ' dp-gdrive-row--locked' : '' ) . '">';
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
	 * Whether the current user may download a file restricted to the given roles.
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

	/**
	 * Numbered pagination for the bookmark list.
	 *
	 * @param int $paged Current page.
	 * @param int $pages Total pages.
	 * @return string
	 */
	protected static function pagination( $paged, $pages ) {
		if ( $pages < 2 ) {
			return '';
		}

		$base = get_permalink();
		if ( ! $base ) {
			$base = home_url( '/' );
		}
		$base = remove_query_arg( 'paged', $base );

		$items = paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%', $base ),
				'format'    => '',
				'current'   => max( 1, (int) $paged ),
				'total'     => (int) $pages,
				'type'      => 'array',
				'prev_text' => '&lsaquo;',
				'next_text' => '&rsaquo;',
			)
		);

		if ( empty( $items ) ) {
			return '';
		}

		return '<nav class="dp-gdrive-pagination" aria-label="' . esc_attr__( 'Navigasi halaman', 'dp-googl-drive' ) . '">' . implode( '', $items ) . '</nav>';
	}
}
