<?php
/**
 * Shortcodes and the visitor submission handler.
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive_Shortcodes
 */
class DP_GDrive_Shortcodes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'dp_googl_drive', array( $this, 'render_list' ) );
		add_shortcode( 'dp_googl_drive_form', array( $this, 'render_form' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_action( 'admin_post_dp_gdrive_submit', array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_dp_gdrive_submit', array( $this, 'handle_submit' ) );

		add_action( 'wp_ajax_dp_gdrive_expand', array( $this, 'ajax_expand' ) );
		add_action( 'wp_ajax_nopriv_dp_gdrive_expand', array( $this, 'ajax_expand' ) );

		add_action( 'wp_ajax_dp_gdrive_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_nopriv_dp_gdrive_list', array( $this, 'ajax_list' ) );
	}

	/**
	 * Register front-end assets.
	 */
	public function register_assets() {
		wp_register_style(
			'dp-googl-drive',
			DP_GDRIVE_URL . 'assets/css/dp-googl-drive.css',
			array(),
			DP_GDRIVE_VERSION
		);

		wp_register_script(
			'dp-googl-drive',
			DP_GDRIVE_URL . 'assets/js/dp-googl-drive.js',
			array(),
			DP_GDRIVE_VERSION,
			true
		);

		wp_localize_script(
			'dp-googl-drive',
			'dpGdriveData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'loading'   => __( 'Memuat…', 'dp-googl-drive' ),
					'error'     => __( 'Gagal memuat isi folder.', 'dp-googl-drive' ),
					'listError' => __( 'Gagal memuat daftar. Coba lagi.', 'dp-googl-drive' ),
				),
			)
		);
	}

	/**
	 * Render the bookmark list shortcode.
	 *
	 * @return string
	 */
	public function render_list( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'show_title' => 'yes',
				'per_page'   => 0,
			),
			$atts,
			'dp_googl_drive'
		);

		$show_title = ! in_array( strtolower( (string) $atts['show_title'] ), array( 'no', '0', 'false', 'off' ), true );
		$per_page   = max( 0, absint( $atts['per_page'] ) );

		wp_enqueue_style( 'dp-googl-drive' );
		wp_enqueue_script( 'dp-googl-drive' );

		return DP_GDrive_Templates::render_list( $show_title, $per_page > 0 ? $per_page : null );
	}

	/**
	 * AJAX: return the next batch of bookmarks (lazy loading).
	 */
	public function ajax_list() {
		check_ajax_referer( 'dp_gdrive_list', 'nonce' );

		$offset     = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
		$per_page   = isset( $_POST['per_page'] ) ? max( 1, absint( wp_unslash( $_POST['per_page'] ) ) ) : 5;
		$show_title = ! isset( $_POST['show_title'] ) || '0' !== (string) wp_unslash( $_POST['show_title'] );

		$visible = DP_GDrive_Templates::published_bookmarks();
		$total   = count( $visible );
		$slice   = array_slice( $visible, $offset, $per_page );

		$offset += count( $slice );

		wp_send_json_success(
			array(
				'html'    => DP_GDrive_Templates::render_bookmarks( $slice, $show_title ),
				'offset'  => $offset,
				'total'   => $total,
				'hasMore' => $offset < $total,
			)
		);
	}

	/**
	 * AJAX: return the rendered children of a folder.
	 */
	public function ajax_expand() {
		check_ajax_referer( 'dp_gdrive_expand', 'nonce' );

		$id          = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$resourcekey = isset( $_POST['resourcekey'] ) ? sanitize_text_field( wp_unslash( $_POST['resourcekey'] ) ) : '';
		$bookmark    = isset( $_POST['bookmark'] ) ? absint( wp_unslash( $_POST['bookmark'] ) ) : 0;

		if ( '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'Folder tidak dikenali.', 'dp-googl-drive' ) ) );
		}

		$data = DP_GDrive_Drive::fetch_folder( $id, $resourcekey );
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
		}

		if ( empty( $data['items'] ) ) {
			wp_send_json_success(
				array(
					'html' => '<p class="dp-gdrive-empty">' . esc_html__( 'Folder kosong.', 'dp-googl-drive' ) . '</p>',
				)
			);
		}

		$file_audience = array();
		$can_download  = true;
		if ( $bookmark && DP_GDrive_CPT::LINK === get_post_type( $bookmark ) ) {
			$file_audience = (array) get_post_meta( $bookmark, '_dp_gdrive_file_audience', true );
			$can_download  = DP_GDrive_Templates::can_download_bookmark( get_post( $bookmark ) );
		}

		wp_send_json_success( array( 'html' => DP_GDrive_Templates::render_items( $data['items'], true, $file_audience, $can_download ) ) );
	}

	/**
	 * Render the paste-a-link form.
	 *
	 * @return string
	 */
	public function render_form() {
		wp_enqueue_style( 'dp-googl-drive' );

		$is_logged_in = is_user_logged_in();
		$allowed      = $is_logged_in || (int) DP_GDrive_Settings::get( 'allow_guest_submit', 1 );

		if ( ! $allowed ) {
			return '<div class="dp-gdrive-form dp-gdrive-form--closed">' . esc_html__( 'Pengiriman link ditutup.', 'dp-googl-drive' ) . '</div>';
		}

		$notice = isset( $_GET['dp_gdrive_notice'] ) ? sanitize_key( wp_unslash( $_GET['dp_gdrive_notice'] ) ) : '';
		$banner = '';

		if ( 'submitted' === $notice ) {
			$banner = '<p class="dp-gdrive-notice dp-gdrive-notice--ok">' . esc_html__( 'Terima kasih. Link menunggu persetujuan admin.', 'dp-googl-drive' ) . '</p>';
		} elseif ( 'duplicate' === $notice ) {
			$banner = '<p class="dp-gdrive-notice dp-gdrive-notice--error">' . esc_html__( 'Link ini sudah ada dalam daftar.', 'dp-googl-drive' ) . '</p>';
		} elseif ( 'invalid' === $notice ) {
			$banner = '<p class="dp-gdrive-notice dp-gdrive-notice--error">' . esc_html__( 'Link tidak valid. Tempel tautan Google Drive (file atau folder).', 'dp-googl-drive' ) . '</p>';
		}

		ob_start();
		?>
		<form class="dp-gdrive-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php echo $banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
			<input type="hidden" name="action" value="dp_gdrive_submit" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ); ?>" />
			<?php wp_nonce_field( 'dp_gdrive_submit' ); ?>

			<p class="dp-gdrive-field">
				<label for="dp-gdrive-url"><?php esc_html_e( 'Tautan Google Drive', 'dp-googl-drive' ); ?></label>
				<input type="url" id="dp-gdrive-url" name="dp_gdrive_url" required placeholder="https://drive.google.com/drive/folders/..." />
			</p>

			<p class="dp-gdrive-field">
				<label for="dp-gdrive-title"><?php esc_html_e( 'Judul (opsional)', 'dp-googl-drive' ); ?></label>
				<input type="text" id="dp-gdrive-title" name="dp_gdrive_title" maxlength="160" />
			</p>

			<?php if ( ! $is_logged_in ) : ?>
				<p class="dp-gdrive-field">
					<label for="dp-gdrive-name"><?php esc_html_e( 'Nama Anda', 'dp-googl-drive' ); ?></label>
					<input type="text" id="dp-gdrive-name" name="dp_gdrive_name" maxlength="80" />
				</p>
				<p class="dp-gdrive-field">
					<label for="dp-gdrive-email"><?php esc_html_e( 'Email (opsional)', 'dp-googl-drive' ); ?></label>
					<input type="email" id="dp-gdrive-email" name="dp_gdrive_email" maxlength="120" />
				</p>
			<?php endif; ?>

			<p class="dp-gdrive-honeypot" aria-hidden="true">
				<label for="dp-gdrive-website"><?php esc_html_e( 'Website', 'dp-googl-drive' ); ?></label>
				<input type="text" id="dp-gdrive-website" name="dp_gdrive_website" tabindex="-1" autocomplete="off" />
			</p>

			<p class="dp-gdrive-field dp-gdrive-submit">
				<button type="submit" class="dp-gdrive-button"><?php esc_html_e( 'Kirim link', 'dp-googl-drive' ); ?></button>
			</p>
		</form>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Handle a submitted link: validate, extract the Drive ID, store as pending.
	 */
	public function handle_submit() {
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'dp_gdrive_submit' ) ) {
			$this->redirect_back( $redirect, 'invalid' );
		}

		// Honeypot: real users leave this empty.
		if ( ! empty( $_POST['dp_gdrive_website'] ) ) {
			$this->redirect_back( $redirect, 'submitted' );
		}

		$is_logged_in = is_user_logged_in();
		$allowed      = $is_logged_in || (int) DP_GDrive_Settings::get( 'allow_guest_submit', 1 );
		if ( ! $allowed ) {
			$this->redirect_back( $redirect, 'invalid' );
		}

		$url   = isset( $_POST['dp_gdrive_url'] ) ? esc_url_raw( wp_unslash( $_POST['dp_gdrive_url'] ) ) : '';
		$title = isset( $_POST['dp_gdrive_title'] ) ? sanitize_text_field( wp_unslash( $_POST['dp_gdrive_title'] ) ) : '';

		$parsed = DP_GDrive_Drive::parse( $url );
		if ( empty( $parsed['id'] ) ) {
			$this->redirect_back( $redirect, 'invalid' );
		}

		if ( $this->find_duplicate( $parsed['id'] ) ) {
			$this->redirect_back( $redirect, 'duplicate' );
		}

		// For a folder with no title, try to borrow the folder name.
		if ( '' === $title && 'folder' === $parsed['type'] ) {
			$data = DP_GDrive_Drive::fetch_folder( $parsed['id'], $parsed['resourcekey'] );
			if ( ! is_wp_error( $data ) && ! empty( $data['title'] ) ) {
				$title = $data['title'];
			}
		}
		if ( '' === $title ) {
			$title = 'folder' === $parsed['type']
				? __( 'Folder Google Drive', 'dp-googl-drive' )
				: __( 'Berkas Google Drive', 'dp-googl-drive' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => DP_GDrive_CPT::LINK,
				'post_status' => 'pending',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->redirect_back( $redirect, 'invalid' );
		}

		update_post_meta( $post_id, '_dp_gdrive_url', $url );
		update_post_meta( $post_id, '_dp_gdrive_id', $parsed['id'] );
		update_post_meta( $post_id, '_dp_gdrive_type', $parsed['type'] );
		update_post_meta( $post_id, '_dp_gdrive_resourcekey', $parsed['resourcekey'] );
		update_post_meta( $post_id, '_dp_gdrive_audience', 'public' );
		update_post_meta( $post_id, '_dp_gdrive_roles', array() );

		if ( ! $is_logged_in ) {
			$name  = isset( $_POST['dp_gdrive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dp_gdrive_name'] ) ) : '';
			$email = isset( $_POST['dp_gdrive_email'] ) ? sanitize_email( wp_unslash( $_POST['dp_gdrive_email'] ) ) : '';
			if ( '' !== $name ) {
				update_post_meta( $post_id, '_dp_gdrive_submitter', $name );
			}
			if ( '' !== $email ) {
				update_post_meta( $post_id, '_dp_gdrive_submitter_email', $email );
			}
		}

		$this->redirect_back( $redirect, 'submitted' );
	}

	/**
	 * Find an existing bookmark for the same Drive ID (any status except trash).
	 *
	 * @param string $drive_id Drive file or folder ID.
	 * @return int Post ID, or 0 when none.
	 */
	protected function find_duplicate( $drive_id ) {
		if ( '' === $drive_id ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'      => DP_GDrive_CPT::LINK,
				'post_status'    => array( 'pending', 'publish', 'draft', 'future', 'private' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_dp_gdrive_id',
						'value'   => $drive_id,
						'compare' => '=',
					),
				),
			)
		);

		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * Redirect back to the form page with a notice flag.
	 *
	 * @param string $redirect Target URL.
	 * @param string $notice   Notice flag.
	 */
	protected function redirect_back( $redirect, $notice ) {
		$redirect = $redirect ? $redirect : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'dp_gdrive_notice', $notice, $redirect ) );
		exit;
	}
}
