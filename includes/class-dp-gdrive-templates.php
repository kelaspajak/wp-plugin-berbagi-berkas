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
	 * @param string   $view       Layout: "catalog" or "table".
	 * @param array    $search     Search args: q (keyword).
	 * @return string
	 */
	public static function render_list( $show_title = true, $per_page = null, $view = 'catalog', $search = array() ) {
		if ( null === $per_page ) {
			$per_page = (int) DP_GDrive_Settings::get( 'per_page', 5 );
		}
		$per_page = max( 1, (int) $per_page );
		$view     = in_array( $view, array( 'catalog', 'table' ), true ) ? $view : 'catalog';

		$bookmarks = self::published_bookmarks( $search );
		$total     = count( $bookmarks );

		$search_form = 'catalog' === $view ? self::search_form( $search ) : '';

		if ( 0 === $total ) {
			return '<div class="dp-gdrive dp-gdrive--empty">' . $search_form . '<p>' . esc_html__( 'Belum ada berkas yang cocok.', 'dp-googl-drive' ) . '</p></div>';
		}

		$slice  = array_slice( $bookmarks, 0, $per_page );
		$offset = count( $slice );

		$html  = '<div class="dp-gdrive' . ( 'catalog' === $view ? ' dp-gdrive--catalog' : '' ) . '" data-dp-gdrive-list'
			. ' data-view="' . esc_attr( $view ) . '"'
			. ' data-show-title="' . ( $show_title ? '1' : '0' ) . '"'
			. ' data-per-page="' . esc_attr( $per_page ) . '"'
			. ' data-offset="' . esc_attr( $offset ) . '"'
			. ' data-total="' . esc_attr( $total ) . '"'
			. ' data-q="' . esc_attr( isset( $search['q'] ) ? $search['q'] : '' ) . '"'
			. ' data-nonce="' . esc_attr( wp_create_nonce( 'dp_gdrive_list' ) ) . '">';
		$html .= $search_form;

		if ( 'catalog' === $view ) {
			$html .= '<div class="dp-gdrive-catalog" data-dp-gdrive-items>' . self::render_cards( $slice, $show_title ) . '</div>';
		} else {
			$html .= self::render_bookmarks( $slice, $show_title );
		}

		$html .= '</div>';

		if ( $total > $offset ) {
			$html .= self::load_more();
		}

		return $html;
	}

	/**
	 * All published bookmarks, optionally filtered by keyword.
	 *
	 * Audience restrictions never hide a bookmark; they only gate its downloads.
	 *
	 * @param array $args Search args: q (keyword).
	 * @return WP_Post[]
	 */
	public static function published_bookmarks( $args = array() ) {
		$query = array(
			'post_type'      => DP_GDrive_CPT::LINK,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$q = isset( $args['q'] ) ? trim( (string) $args['q'] ) : '';
		if ( '' !== $q ) {
			$query['s'] = $q;
		}

		return get_posts( $query );
	}

	/**
	 * Catalog status labels.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			'active'     => __( 'Aktif', 'dp-googl-drive' ),
			'archive'    => __( 'Arsip', 'dp-googl-drive' ),
			'superseded' => __( 'Digantikan', 'dp-googl-drive' ),
			'review'     => __( 'Perlu ditinjau', 'dp-googl-drive' ),
		);
	}

	/**
	 * Search form for the catalog view.
	 *
	 * @param array $search Current search args.
	 * @return string
	 */
	protected static function search_form( $search ) {
		$q = isset( $search['q'] ) ? (string) $search['q'] : '';

		$html  = '<form class="dp-gdrive-search" method="get" action="' . esc_url( self::current_url() ) . '" role="search">';
		foreach ( $_GET as $key => $value ) {
			if ( 'dp_q' === $key || ! is_scalar( $value ) ) {
				continue;
			}
			$html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( wp_unslash( $value ) ) . '" />';
		}
		$html .= '<input type="search" name="dp_q" class="dp-gdrive-search__q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Cari nama berkas atau topik…', 'dp-googl-drive' ) . '" />';
		$html .= '<button type="submit" class="dp-gdrive-button">' . esc_html__( 'Cari', 'dp-googl-drive' ) . '</button>';
		if ( '' !== $q ) {
			$html .= '<a class="dp-gdrive-search__reset" href="' . esc_url( self::current_url() ) . '">' . esc_html__( 'Reset', 'dp-googl-drive' ) . '</a>';
		}
		$html .= '</form>';

		return $html;
	}

	/**
	 * Current page URL, used as the search form action.
	 *
	 * @return string
	 */
	protected static function current_url() {
		$url = get_permalink();

		return $url ? $url : home_url( '/' );
	}

	/**
	 * Render catalog cards.
	 *
	 * @param WP_Post[] $posts      Bookmarks.
	 * @param bool      $show_title Whether to print each bookmark's title.
	 * @return string
	 */
	public static function render_cards( $posts, $show_title = true ) {
		$html = '';

		foreach ( $posts as $post ) {
			$html .= self::render_card( $post, $show_title );
		}

		return $html;
	}

	/**
	 * Render a single catalog card (metadata only; Drive is fetched on demand).
	 *
	 * @param WP_Post $post       Bookmark post.
	 * @param bool    $show_title Whether to print the title.
	 * @return string
	 */
	protected static function render_card( $post, $show_title = true ) {
		$type        = (string) get_post_meta( $post->ID, '_dp_gdrive_type', true );
		$format      = (string) get_post_meta( $post->ID, '_dp_gdrive_format', true );
		$period      = (string) get_post_meta( $post->ID, '_dp_gdrive_period', true );
		$version     = (string) get_post_meta( $post->ID, '_dp_gdrive_version', true );
		$status_key  = (string) get_post_meta( $post->ID, '_dp_gdrive_status', true );
		$description = (string) get_post_meta( $post->ID, '_dp_gdrive_description', true );
		$contributor = (string) get_post_meta( $post->ID, '_dp_gdrive_contributor', true );
		$source      = (string) get_post_meta( $post->ID, '_dp_gdrive_source_url', true );
		$license     = (string) get_post_meta( $post->ID, '_dp_gdrive_license', true );

		$statuses = self::statuses();
		if ( ! isset( $statuses[ $status_key ] ) ) {
			$status_key = 'active';
		}

		if ( '' === $format ) {
			$format = 'folder' === $type ? __( 'Folder', 'dp-googl-drive' ) : __( 'File', 'dp-googl-drive' );
		}

		$badges   = array();
		$badges[] = '<span class="dp-gdrive-badge dp-gdrive-badge--' . esc_attr( $status_key ) . '">' . esc_html( $format ) . '</span>';
		if ( '' !== $period ) {
			$badges[] = '<span class="dp-gdrive-badge">' . esc_html( $period ) . '</span>';
		}
		$badges[] = '<span class="dp-gdrive-badge dp-gdrive-badge--' . esc_attr( $status_key ) . '">' . esc_html( $statuses[ $status_key ] ) . '</span>';

		$facts = array();
		if ( '' !== $contributor ) {
			$facts[] = esc_html__( 'Kontributor:', 'dp-googl-drive' ) . ' ' . esc_html( $contributor );
		}
		if ( '' !== $version ) {
			$facts[] = esc_html__( 'Versi:', 'dp-googl-drive' ) . ' ' . esc_html( $version );
		}
		if ( '' !== $license ) {
			$facts[] = esc_html__( 'Lisensi:', 'dp-googl-drive' ) . ' ' . esc_html( $license );
		}
		if ( '' !== $source ) {
			$facts[] = '<a href="' . esc_url( $source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sumber asli', 'dp-googl-drive' ) . '</a>';
		}

		$html  = '<article class="dp-gdrive-catalog-card" data-dp-gdrive-card data-post="' . esc_attr( $post->ID ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'dp_gdrive_card' ) ) . '">';
		if ( $show_title ) {
			$html .= '<h3 class="dp-gdrive-catalog-card__title">' . esc_html( get_the_title( $post ) ) . '</h3>';
		}
		$html .= '<p class="dp-gdrive-catalog-card__meta">' . implode( '', $badges ) . '</p>';
		if ( '' !== $description ) {
			$html .= '<p class="dp-gdrive-catalog-card__desc">' . esc_html( wp_trim_words( $description, 32 ) ) . '</p>';
		}
		if ( ! empty( $facts ) ) {
			$html .= '<p class="dp-gdrive-catalog-card__facts">' . implode( ' · ', $facts ) . '</p>';
		}
		$html .= '<p class="dp-gdrive-catalog-card__actions"><button type="button" class="dp-gdrive-button dp-gdrive-catalog-card__open" aria-expanded="false">' . esc_html__( 'Lihat berkas', 'dp-googl-drive' ) . '</button></p>';
		$html .= '<div class="dp-gdrive-catalog-card__panel" hidden></div>';
		$html .= '</article>';

		return $html;
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
