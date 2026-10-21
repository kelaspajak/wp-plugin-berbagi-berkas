<?php
/**
 * Google Drive helper: URL parsing, folder listing, and link building.
 *
 * No API key is used. Folder contents are read from the public
 * `embeddedfolderview` endpoint, which returns a small server-rendered HTML
 * document with one `div.flip-entry` per child. That markup is undocumented and
 * may change without notice, so results are cached and parsers are tolerant.
 * The folder must be shared as "Anyone with the link".
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive_Drive
 */
class DP_GDrive_Drive {

	const MIME_FOLDER = 'application/vnd.google-apps.folder';

	/**
	 * Parse a pasted Drive URL into its parts.
	 *
	 * @param string $url Pasted URL.
	 * @return array{id:string,type:string,resourcekey:string}|array Empty on failure.
	 */
	public static function parse( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return array();
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return array();
		}

		$host = strtolower( $parts['host'] );
		if ( ! in_array( $host, array( 'drive.google.com', 'docs.google.com', 'drive.usercontent.google.com' ), true ) ) {
			return array();
		}

		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		$query = isset( $parts['query'] ) ? $parts['query'] : '';

		$id   = '';
		$type = 'file';

		if ( preg_match( '#/(?:file|document|spreadsheets|presentation|drawings)/(?:u/\d+/)?d/([A-Za-z0-9_-]{10,})#', $path, $m ) ) {
			$id   = $m[1];
			$type = 'file';
		} elseif ( preg_match( '#/drive/(?:u/\d+/)?folders/([A-Za-z0-9_-]{10,})#', $path, $m ) ) {
			$id   = $m[1];
			$type = 'folder';
		} elseif ( '' !== $query && preg_match( '/(?:^|&)id=([A-Za-z0-9_-]{10,})/', $query, $m ) ) {
			$id   = $m[1];
		}

		if ( '' === $id ) {
			return array();
		}

		$resourcekey = '';
		if ( '' !== $query && preg_match( '/(?:^|&)resourcekey=([A-Za-z0-9_-]+)/', $query, $m ) ) {
			$resourcekey = $m[1];
		}

		return array(
			'id'          => $id,
			'type'        => $type,
			'resourcekey' => $resourcekey,
		);
	}

	/**
	 * Fetch and parse a folder's children (cached).
	 *
	 * @param string $folder_id   Folder ID.
	 * @param string $resourcekey Optional resource key for the folder.
	 * @return array{title:string,items:array}|WP_Error
	 */
	public static function fetch_folder( $folder_id, $resourcekey = '' ) {
		$folder_id = trim( (string) $folder_id );
		if ( '' === $folder_id ) {
			return new WP_Error( 'dp_gdrive_bad_id', __( 'ID folder tidak valid.', 'dp-googl-drive' ) );
		}

		$cache_key = 'dp_gdrive_folder_' . md5( $folder_id . '|' . $resourcekey );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$endpoint = 'https://drive.google.com/embeddedfolderview?hl=en&id=' . rawurlencode( $folder_id );
		if ( '' !== $resourcekey ) {
			$endpoint .= '&resourcekey=' . rawurlencode( $resourcekey );
		}

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'user-agent'  => 'Mozilla/5.0 (compatible; DP-Google-Drive/' . DP_GDRIVE_VERSION . '; +' . home_url( '/' ) . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'dp_gdrive_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Google membalas status %d. Pastikan folder dibagikan “siapa saja yang memiliki link”.', 'dp-googl-drive' ),
					$code
				)
			);
		}

		$data = self::parse_folder_html( wp_remote_retrieve_body( $response ) );

		if ( empty( $data['items'] ) && '' === $data['title'] ) {
			return new WP_Error(
				'dp_gdrive_parse',
				__( 'Isi folder tidak terbaca. Folder mungkin privat, kosong, atau struktur Google berubah.', 'dp-googl-drive' )
			);
		}

		$ttl = max( 1, (int) DP_GDrive_Settings::get( 'cache_lifetime', 6 ) ) * HOUR_IN_SECONDS;
		set_transient( $cache_key, $data, $ttl );

		return $data;
	}

	/**
	 * Best-effort Drive file name, used to prefill the submission form.
	 *
	 * @param string $file_id File ID.
	 * @return string File name, or an empty string when unknown.
	 */
	public static function fetch_file_title( $file_id ) {
		$file_id = trim( (string) $file_id );
		if ( '' === $file_id ) {
			return '';
		}

		$cache_key = 'dp_gdrive_file_title_' . md5( $file_id );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://drive.google.com/file/d/' . rawurlencode( $file_id ) . '/view',
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'user-agent'  => 'Mozilla/5.0 (compatible; DP-Google-Drive/' . DP_GDRIVE_VERSION . '; +' . home_url( '/' ) . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$html  = (string) wp_remote_retrieve_body( $response );
		$title = '';

		if ( '' !== $html ) {
			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
			libxml_clear_errors();

			if ( $loaded ) {
				$xpath = new DOMXPath( $dom );

				$og = $xpath->query( '//meta[@property="og:title"]' )->item( 0 );
				if ( $og instanceof DOMElement ) {
					$title = trim( (string) $og->getAttribute( 'content' ) );
				}

				if ( '' === $title ) {
					$node = $xpath->query( '//title' )->item( 0 );
					if ( $node ) {
						$title = trim( (string) $node->textContent );
					}
				}
			}
		}

		$title = trim( preg_replace( '/\s*[-–]\s*Google\s*Drive\s*$/iu', '', $title ) );

		set_transient( $cache_key, $title, 12 * HOUR_IN_SECONDS );

		return $title;
	}

	/**
	 * Parse the folder-view HTML into a title and a list of items.
	 *
	 * @param string $html Response body.
	 * @return array{title:string,items:array}
	 */
	public static function parse_folder_html( $html ) {
		$out  = array(
			'title' => '',
			'items' => array(),
		);
		$html = (string) $html;

		if ( '' === $html ) {
			return $out;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return $out;
		}

		$xpath = new DOMXPath( $dom );

		$title_nodes = $xpath->query( '//title' );
		if ( $title_nodes && $title_nodes->length ) {
			$out['title'] = trim( (string) $title_nodes->item( 0 )->textContent );
		}

		$entries = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " flip-entry ")]' );
		if ( ! $entries ) {
			return $out;
		}

		foreach ( $entries as $entry ) {
			$id      = '';
			$entry_id = $entry->getAttribute( 'id' );
			if ( preg_match( '/^entry-(.+)$/', $entry_id, $m ) ) {
				$id = $m[1];
			}

			$anchor = $xpath->query( './/a[@href]', $entry )->item( 0 );
			$href   = $anchor ? $anchor->getAttribute( 'href' ) : '';

			if ( '' === $id && '' !== $href ) {
				if ( preg_match( '~/file/d/([^/?#]+)~', $href, $m ) ) {
					$id = $m[1];
				} elseif ( preg_match( '~/folders/([^/?#]+)~', $href, $m ) ) {
					$id = $m[1];
				}
			}

			if ( '' === $id ) {
				continue;
			}

			$name = '';
			$name_node = $xpath->query( './/div[contains(concat(" ", normalize-space(@class), " "), " flip-entry-title ")]', $entry )->item( 0 );
			if ( $name_node ) {
				$name = trim( (string) $name_node->textContent );
			}

			$mime      = '';
			$icon_src  = '';
			$icon_node = $xpath->query( './/div[contains(concat(" ", normalize-space(@class), " "), " flip-entry-list-icon ")]//img', $entry )->item( 0 );
			if ( $icon_node instanceof DOMElement ) {
				$icon_src = $icon_node->getAttribute( 'src' );
				if ( preg_match( '~/type/([^?#]+)~', $icon_src, $m ) ) {
					$mime = urldecode( $m[1] );
				}
			}

			$thumb    = '';
			$thumb_node = $xpath->query( './/div[contains(concat(" ", normalize-space(@class), " "), " flip-entry-thumb ")]//img', $entry )->item( 0 );
			if ( $thumb_node instanceof DOMElement ) {
				$thumb = $thumb_node->getAttribute( 'src' );
			}

			$modified  = '';
			$mod_node  = $xpath->query( './/div[contains(concat(" ", normalize-space(@class), " "), " flip-entry-last-modified ")]', $entry )->item( 0 );
			if ( $mod_node ) {
				$modified = trim( (string) $mod_node->textContent );
			}

			$is_folder = ( self::MIME_FOLDER === $mime ) || ( '' !== $href && false !== strpos( $href, '/folders/' ) );

			$item = array(
				'id'        => $id,
				'name'      => $name,
				'mime'      => $mime,
				'is_folder' => $is_folder,
				'modified'  => $modified,
				'icon'      => $icon_src,
				'thumb'     => $thumb,
			);

			if ( ! $is_folder ) {
				$item['download'] = self::download_url( $id, $mime );
			}

			$out['items'][] = $item;
		}

		return $out;
	}

	/**
	 * The direct download URL. Google-native files use their export endpoint;
	 * everything else uses the uc download endpoint.
	 *
	 * @param string $id   File ID.
	 * @param string $mime MIME type.
	 * @return string
	 */
	public static function download_url( $id, $mime = '' ) {
		switch ( $mime ) {
			case 'application/vnd.google-apps.document':
				return 'https://docs.google.com/document/d/' . $id . '/export?format=docx';
			case 'application/vnd.google-apps.spreadsheet':
				return 'https://docs.google.com/spreadsheets/d/' . $id . '/export?format=xlsx';
			case 'application/vnd.google-apps.presentation':
				return 'https://docs.google.com/presentation/d/' . $id . '/export/pptx';
			case 'application/vnd.google-apps.drawing':
				return 'https://docs.google.com/drawings/d/' . $id . '/export/png';
		}

		return 'https://drive.google.com/uc?export=download&id=' . $id;
	}
}
