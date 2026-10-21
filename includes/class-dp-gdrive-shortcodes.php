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
	 * Form instance counter for unique element IDs.
	 *
	 * @var int
	 */
	protected static $instances = 0;

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

		add_action( 'wp_ajax_dp_gdrive_bookmark', array( $this, 'ajax_bookmark' ) );
		add_action( 'wp_ajax_nopriv_dp_gdrive_bookmark', array( $this, 'ajax_bookmark' ) );

		add_action( 'wp_ajax_dp_gdrive_peek', array( $this, 'ajax_peek' ) );
		add_action( 'wp_ajax_nopriv_dp_gdrive_peek', array( $this, 'ajax_peek' ) );
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
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'peekNonce' => wp_create_nonce( 'dp_gdrive_peek' ),
				'i18n'      => array(
					'loading'     => __( 'Memuat…', 'dp-googl-drive' ),
					'error'       => __( 'Gagal memuat isi folder.', 'dp-googl-drive' ),
					'listError'   => __( 'Gagal memuat daftar. Coba lagi.', 'dp-googl-drive' ),
					'peekLoading' => __( 'Menarik data…', 'dp-googl-drive' ),
					'peekEmpty'   => __( 'Tempel tautan Google Drive dulu.', 'dp-googl-drive' ),
					'peekError'   => __( 'Gagal menarik data. Isi judul secara manual.', 'dp-googl-drive' ),
					'peekUse'     => __( 'Gunakan nama ini', 'dp-googl-drive' ),
					'peekFilled'  => __( 'Judul terisi otomatis.', 'dp-googl-drive' ),
				),
			)
		);
	}

	/**
	 * Render the bookmark list shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_list( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'show_title' => 'yes',
				'per_page'   => 0,
				'view'       => 'catalog',
			),
			$atts,
			'dp_googl_drive'
		);

		$show_title = ! in_array( strtolower( (string) $atts['show_title'] ), array( 'no', '0', 'false', 'off' ), true );
		$per_page   = max( 0, absint( $atts['per_page'] ) );
		$view       = 'table' === strtolower( (string) $atts['view'] ) ? 'table' : 'catalog';

		$search = array(
			'q' => isset( $_GET['dp_q'] ) ? sanitize_text_field( wp_unslash( $_GET['dp_q'] ) ) : '',
		);

		wp_enqueue_style( 'dp-googl-drive' );
		wp_enqueue_script( 'dp-googl-drive' );

		return DP_GDrive_Templates::render_list( $show_title, $per_page > 0 ? $per_page : null, $view, $search );
	}

	/**
	 * AJAX: return the next batch of bookmarks (lazy loading).
	 */
	public function ajax_list() {
		check_ajax_referer( 'dp_gdrive_list', 'nonce' );

		$offset     = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
		$per_page   = isset( $_POST['per_page'] ) ? max( 1, absint( wp_unslash( $_POST['per_page'] ) ) ) : 5;
		$show_title = ! isset( $_POST['show_title'] ) || '0' !== (string) wp_unslash( $_POST['show_title'] );
		$view       = isset( $_POST['view'] ) && 'table' === (string) wp_unslash( $_POST['view'] ) ? 'table' : 'catalog';
		$search     = array(
			'q' => isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '',
		);

		$visible = DP_GDrive_Templates::published_bookmarks( $search );
		$total   = count( $visible );
		$slice   = array_slice( $visible, $offset, $per_page );

		$offset += count( $slice );

		$html = 'table' === $view
			? DP_GDrive_Templates::render_bookmarks( $slice, $show_title )
			: DP_GDrive_Templates::render_cards( $slice, $show_title );

		wp_send_json_success(
			array(
				'html'    => $html,
				'offset'  => $offset,
				'total'   => $total,
				'hasMore' => $offset < $total,
			)
		);
	}

	/**
	 * AJAX: return a single bookmark section for a catalog card.
	 */
	public function ajax_bookmark() {
		check_ajax_referer( 'dp_gdrive_card', 'nonce' );

		$id   = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$post = $id ? get_post( $id ) : null;

		if ( ! $post || DP_GDrive_CPT::LINK !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Berkas tidak ditemukan.', 'dp-googl-drive' ) ) );
		}

		wp_send_json_success( array( 'html' => DP_GDrive_Templates::render_bookmarks( array( $post ), false ) ) );
	}

	/**
	 * AJAX: fetch a Drive link's name (and folder item count) for form prefill.
	 */
	public function ajax_peek() {
		check_ajax_referer( 'dp_gdrive_peek', 'nonce' );

		$url    = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$parsed = DP_GDrive_Drive::parse( $url );

		if ( empty( $parsed['id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Tautan Google Drive tidak dikenali. Tempel tautan file atau folder.', 'dp-googl-drive' ) ) );
		}

		if ( 'folder' === $parsed['type'] ) {
			$data = DP_GDrive_Drive::fetch_folder( $parsed['id'], $parsed['resourcekey'] );
			if ( is_wp_error( $data ) ) {
				wp_send_json_error( array( 'message' => $data->get_error_message() ) );
			}

			$count = isset( $data['items'] ) ? count( $data['items'] ) : 0;

			wp_send_json_success(
				array(
					'type'    => 'folder',
					'title'   => isset( $data['title'] ) ? $data['title'] : '',
					'message' => sprintf(
						/* translators: %d: number of items in the folder. */
						_n( 'Folder publik · %d item', 'Folder publik · %d item', $count, 'dp-googl-drive' ),
						$count
					),
				)
			);
		}

		$file_title = DP_GDrive_Drive::fetch_file_title( $parsed['id'] );

		wp_send_json_success(
			array(
				'type'    => 'file',
				'title'   => $file_title,
				'message' => '' !== $file_title
					? __( 'Berkas publik terdeteksi.', 'dp-googl-drive' )
					: __( 'Tautan file dikenali. Isi judul secara manual bila perlu.', 'dp-googl-drive' ),
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
	 * Render the "share a link" launcher and its dialog form.
	 *
	 * @param array $atts Shortcode attributes: label, title.
	 * @return string
	 */
	public function render_form( $atts = array() ) {
		wp_enqueue_style( 'dp-googl-drive' );
		wp_enqueue_script( 'dp-googl-drive' );

		$is_logged_in = is_user_logged_in();
		$allowed      = $is_logged_in || (int) DP_GDrive_Settings::get( 'allow_guest_submit', 1 );

		if ( ! $allowed ) {
			return '<div class="dp-gdrive-form dp-gdrive-form--closed">' . esc_html__( 'Pengiriman link ditutup.', 'dp-googl-drive' ) . '</div>';
		}

		$atts = shortcode_atts(
			array(
				'label' => __( 'Bagikan tautan', 'dp-googl-drive' ),
				'title' => __( 'Bagikan tautan Google Drive', 'dp-googl-drive' ),
			),
			$atts,
			'dp_googl_drive_form'
		);

		$label = sanitize_text_field( (string) $atts['label'] );
		$title = sanitize_text_field( (string) $atts['title'] );
		if ( '' === $label ) {
			$label = __( 'Bagikan tautan', 'dp-googl-drive' );
		}
		if ( '' === $title ) {
			$title = __( 'Bagikan tautan Google Drive', 'dp-googl-drive' );
		}

		$notice   = isset( $_GET['dp_gdrive_notice'] ) ? sanitize_key( wp_unslash( $_GET['dp_gdrive_notice'] ) ) : '';
		$banner   = '';
		$is_error = false;

		if ( 'submitted' === $notice ) {
			$banner = '<p class="dp-gdrive-notice dp-gdrive-notice--ok">' . esc_html__( 'Terima kasih. Link menunggu persetujuan admin.', 'dp-googl-drive' ) . '</p>';
		} elseif ( 'duplicate' === $notice ) {
			$banner   = '<p class="dp-gdrive-notice dp-gdrive-notice--error">' . esc_html__( 'Link ini sudah ada dalam daftar.', 'dp-googl-drive' ) . '</p>';
			$is_error = true;
		} elseif ( 'invalid' === $notice ) {
			$banner   = '<p class="dp-gdrive-notice dp-gdrive-notice--error">' . esc_html__( 'Link tidak valid. Tempel tautan Google Drive (file atau folder).', 'dp-googl-drive' ) . '</p>';
			$is_error = true;
		}

		self::$instances++;
		$n         = self::$instances;
		$dialog_id = 'dp-gdrive-dialog-' . $n;

		ob_start();
		?>
		<div class="dp-gdrive-share" data-dp-gdrive-submit>
			<?php if ( '' !== $banner && ! $is_error ) : ?>
				<?php echo $banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
			<?php endif; ?>

			<a class="dp-gdrive-button dp-gdrive-share__open" href="#<?php echo esc_attr( $dialog_id ); ?>" data-dp-gdrive-open aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $dialog_id ); ?>" aria-expanded="false"><?php echo esc_html( $label ); ?></a>

			<dialog class="dp-gdrive-dialog" id="<?php echo esc_attr( $dialog_id ); ?>" data-dp-gdrive-dialog data-open-on-load="<?php echo $is_error ? '1' : '0'; ?>" aria-labelledby="<?php echo esc_attr( $dialog_id ); ?>-title" open>
				<div class="dp-gdrive-dialog__inner">
					<header class="dp-gdrive-dialog__head">
						<h2 class="dp-gdrive-dialog__title" id="<?php echo esc_attr( $dialog_id ); ?>-title"><?php echo esc_html( $title ); ?></h2>
						<button type="button" class="dp-gdrive-dialog__close" data-dp-gdrive-close aria-label="<?php esc_attr_e( 'Tutup', 'dp-googl-drive' ); ?>">&times;</button>
					</header>

					<?php if ( '' !== $banner && $is_error ) : ?>
						<?php echo $banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
					<?php endif; ?>

					<form class="dp-gdrive-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="dp_gdrive_submit" />
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink() ); ?>" />
						<?php wp_nonce_field( 'dp_gdrive_submit' ); ?>

						<p class="dp-gdrive-field">
							<label for="dp-gdrive-url-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Tautan Google Drive', 'dp-googl-drive' ); ?></label>
							<span class="dp-gdrive-url-row">
								<input type="url" id="dp-gdrive-url-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_url" required autocomplete="off" placeholder="https://drive.google.com/drive/folders/..." />
								<button type="button" class="dp-gdrive-button dp-gdrive-url-row__peek" data-dp-gdrive-peek><?php esc_html_e( 'Tarik data', 'dp-googl-drive' ); ?></button>
							</span>
							<span class="dp-gdrive-peek-status" data-dp-gdrive-peek-status aria-live="polite"></span>
						</p>

						<p class="dp-gdrive-field">
							<label for="dp-gdrive-title-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Judul (opsional)', 'dp-googl-drive' ); ?></label>
							<input type="text" id="dp-gdrive-title-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_title" maxlength="160" />
						</p>

						<p class="dp-gdrive-field">
							<label for="dp-gdrive-description-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Deskripsi singkat', 'dp-googl-drive' ); ?></label>
							<textarea id="dp-gdrive-description-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_description" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Isi berkas, untuk apa, dan siapa yang membutuhkan.', 'dp-googl-drive' ); ?>"></textarea>
						</p>

						<p class="dp-gdrive-field">
							<label for="dp-gdrive-period-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Periode / tahun pajak', 'dp-googl-drive' ); ?></label>
							<input type="text" id="dp-gdrive-period-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_period" maxlength="40" placeholder="<?php esc_attr_e( 'Mis. 2025', 'dp-googl-drive' ); ?>" />
						</p>

						<p class="dp-gdrive-field">
							<label for="dp-gdrive-source-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Sumber asli (opsional)', 'dp-googl-drive' ); ?></label>
							<input type="url" id="dp-gdrive-source-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_source_url" maxlength="300" placeholder="https://..." />
						</p>

						<p class="dp-gdrive-field">
							<label for="dp-gdrive-license-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Lisensi / izin berbagi (opsional)', 'dp-googl-drive' ); ?></label>
							<input type="text" id="dp-gdrive-license-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_license" maxlength="120" placeholder="<?php esc_attr_e( 'Mis. Publik, CC BY, izin penulis', 'dp-googl-drive' ); ?>" />
						</p>

						<p class="dp-gdrive-field dp-gdrive-field--checkbox">
							<label>
								<input type="checkbox" name="dp_gdrive_rights" value="1" required />
								<?php esc_html_e( 'Saya memastikan berhak membagikan berkas ini dan tidak melanggar hak cipta.', 'dp-googl-drive' ); ?>
							</label>
						</p>

						<?php if ( ! $is_logged_in ) : ?>
							<p class="dp-gdrive-field">
								<label for="dp-gdrive-name-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Nama Anda', 'dp-googl-drive' ); ?></label>
								<input type="text" id="dp-gdrive-name-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_name" maxlength="80" />
							</p>
							<p class="dp-gdrive-field">
								<label for="dp-gdrive-email-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Email (opsional)', 'dp-googl-drive' ); ?></label>
								<input type="email" id="dp-gdrive-email-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_email" maxlength="120" />
							</p>
						<?php endif; ?>

						<p class="dp-gdrive-honeypot" aria-hidden="true">
							<label for="dp-gdrive-website-<?php echo esc_attr( $n ); ?>"><?php esc_html_e( 'Website', 'dp-googl-drive' ); ?></label>
							<input type="text" id="dp-gdrive-website-<?php echo esc_attr( $n ); ?>" name="dp_gdrive_website" tabindex="-1" autocomplete="off" />
						</p>

						<p class="dp-gdrive-field dp-gdrive-submit">
							<button type="submit" class="dp-gdrive-button"><?php esc_html_e( 'Kirim tautan', 'dp-googl-drive' ); ?></button>
						</p>
					</form>
				</div>
			</dialog>

			<noscript><style>.dp-gdrive-dialog[open]:not(.dp-gdrive-dialog--enhanced){display:block;position:static;width:auto;max-height:none;margin:1em 0;border-radius:8px;overflow:visible}.dp-gdrive-dialog[open]:not(.dp-gdrive-dialog--enhanced) .dp-gdrive-dialog__inner{max-height:none}</style></noscript>
		</div>
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

		$description = isset( $_POST['dp_gdrive_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dp_gdrive_description'] ) ) : '';
		$period      = isset( $_POST['dp_gdrive_period'] ) ? sanitize_text_field( wp_unslash( $_POST['dp_gdrive_period'] ) ) : '';
		$source_url  = isset( $_POST['dp_gdrive_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['dp_gdrive_source_url'] ) ) : '';
		$license     = isset( $_POST['dp_gdrive_license'] ) ? sanitize_text_field( wp_unslash( $_POST['dp_gdrive_license'] ) ) : '';

		if ( empty( $_POST['dp_gdrive_rights'] ) ) {
			$this->redirect_back( $redirect, 'invalid' );
		}

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

		update_post_meta( $post_id, '_dp_gdrive_description', $description );
		update_post_meta( $post_id, '_dp_gdrive_period', $period );
		update_post_meta( $post_id, '_dp_gdrive_source_url', $source_url );
		update_post_meta( $post_id, '_dp_gdrive_license', $license );
		update_post_meta( $post_id, '_dp_gdrive_status', 'review' );
		update_post_meta( $post_id, '_dp_gdrive_rights_confirmed', 1 );

		if ( $is_logged_in ) {
			$user = wp_get_current_user();
			if ( $user && $user->display_name ) {
				update_post_meta( $post_id, '_dp_gdrive_contributor', $user->display_name );
			}
		} else {
			$name  = isset( $_POST['dp_gdrive_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dp_gdrive_name'] ) ) : '';
			$email = isset( $_POST['dp_gdrive_email'] ) ? sanitize_email( wp_unslash( $_POST['dp_gdrive_email'] ) ) : '';
			if ( '' !== $name ) {
				update_post_meta( $post_id, '_dp_gdrive_submitter', $name );
				update_post_meta( $post_id, '_dp_gdrive_contributor', $name );
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
