<?php
/**
 * Admin UI: menu, dashboard, settings, columns, and moderation actions.
 *
 * @package DP_GDrive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DP_GDrive_Admin
 */
class DP_GDrive_Admin {

	const MENU_SLUG = 'dp-googl-drive';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . DP_GDrive_CPT::LINK, array( $this, 'save_metabox' ), 10, 2 );

		add_filter( 'manage_' . DP_GDrive_CPT::LINK . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . DP_GDrive_CPT::LINK . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );

		add_action( 'admin_post_dp_gdrive_approve', array( $this, 'handle_status' ) );
		add_action( 'admin_post_dp_gdrive_reject', array( $this, 'handle_status' ) );
		add_action( 'admin_post_dp_gdrive_save_settings', array( $this, 'handle_save_settings' ) );
	}

	/**
	 * Register admin menus.
	 */
	public function add_menu() {
		$menu_title = __( 'DP Google Drive', 'dp-googl-drive' );
		$pending    = $this->pending_count();

		if ( $pending > 0 ) {
			$menu_title .= sprintf(
				' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>',
				$pending
			);
		}

		add_menu_page(
			__( 'DP Google Drive', 'dp-googl-drive' ),
			$menu_title,
			'edit_posts',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-google',
			31
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'dp-googl-drive' ),
			__( 'Dashboard', 'dp-googl-drive' ),
			'edit_posts',
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Pengaturan', 'dp-googl-drive' ),
			__( 'Pengaturan', 'dp-googl-drive' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Admin notices for plugin actions.
	 */
	public function admin_notices() {
		if ( $this->can_moderate() && $this->is_gdrive_screen() ) {
			$pending = $this->pending_count();

			if ( $pending > 0 ) {
				$url = admin_url( 'edit.php?post_type=' . DP_GDrive_CPT::LINK . '&post_status=pending' );
				printf(
					'<div class="notice notice-warning"><p>%s</p></div>',
					sprintf(
						esc_html__( 'Ada %1$d bookmark menunggu persetujuan. %2$s', 'dp-googl-drive' ),
						$pending,
						'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Tinjau sekarang', 'dp-googl-drive' ) . '</a>'
					)
				);
			}
		}

		$transient_key = 'dp_gdrive_admin_notice_' . get_current_user_id();
		$inline_notice = get_transient( $transient_key );

		if ( 'invalid_link' === $inline_notice ) {
			delete_transient( $transient_key );
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'Link Google Drive tidak valid. Bookmark disimpan sebagai “Menunggu” sampai link diperbaiki.', 'dp-googl-drive' )
			);
		}

		if ( ! isset( $_GET['dp_gdrive_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['dp_gdrive_notice'] ) );

		$messages = array(
			'approved' => array( 'success', __( 'Bookmark disetujui dan tampil di daftar.', 'dp-googl-drive' ) ),
			'rejected' => array( 'success', __( 'Bookmark dikembalikan ke Menunggu.', 'dp-googl-drive' ) ),
			'saved'    => array( 'success', __( 'Pengaturan disimpan.', 'dp-googl-drive' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $notice ][0] ),
			esc_html( $messages[ $notice ][1] )
		);
	}

	/**
	 * Whether the current user may moderate bookmarks (approve/publish).
	 *
	 * @return bool
	 */
	protected function can_moderate() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Number of bookmarks awaiting approval.
	 *
	 * @return int
	 */
	protected function pending_count() {
		$counts = wp_count_posts( DP_GDrive_CPT::LINK );

		return isset( $counts->pending ) ? (int) $counts->pending : 0;
	}

	/**
	 * Whether the current admin screen belongs to this plugin.
	 *
	 * @return bool
	 */
	protected function is_gdrive_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return in_array(
			$screen->id,
			array( 'dashboard', 'toplevel_page_' . self::MENU_SLUG, 'edit-' . DP_GDrive_CPT::LINK ),
			true
		);
	}

	/**
	 * Dashboard page.
	 */
	public function render_dashboard() {
		$counts    = wp_count_posts( DP_GDrive_CPT::LINK );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$pending   = isset( $counts->pending ) ? (int) $counts->pending : 0;
		$list_url  = admin_url( 'edit.php?post_type=' . DP_GDrive_CPT::LINK );
		?>
		<div class="wrap dp-gdrive-dashboard">
			<h1><?php esc_html_e( 'DP Google Drive', 'dp-googl-drive' ); ?></h1>

			<div class="dp-gdrive-cards">
				<div class="dp-gdrive-card">
					<span class="dp-gdrive-card__number"><?php echo esc_html( number_format_i18n( $published ) ); ?></span>
					<span class="dp-gdrive-card__label"><?php esc_html_e( 'Bookmark tampil', 'dp-googl-drive' ); ?></span>
				</div>
				<div class="dp-gdrive-card">
					<span class="dp-gdrive-card__number"><?php echo esc_html( number_format_i18n( $pending ) ); ?></span>
					<span class="dp-gdrive-card__label"><?php esc_html_e( 'Menunggu persetujuan', 'dp-googl-drive' ); ?></span>
				</div>
			</div>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . DP_GDrive_CPT::LINK ) ); ?>">
					<?php esc_html_e( 'Tambah Link', 'dp-googl-drive' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $list_url ); ?>">
					<?php esc_html_e( 'Kelola Bookmark', 'dp-googl-drive' ); ?>
				</a>
			</p>

			<h2><?php esc_html_e( 'Menampilkan di halaman', 'dp-googl-drive' ); ?></h2>
			<p><?php esc_html_e( 'Tempel shortcode ini di sebuah Page atau Post:', 'dp-googl-drive' ); ?></p>
			<p><code>[dp_googl_drive]</code> &mdash; <?php esc_html_e( 'daftar bookmark yang sudah disetujui.', 'dp-googl-drive' ); ?></p>
			<p><code>[dp_googl_drive show_title="no"]</code> &mdash; <?php esc_html_e( 'sembunyikan judul tiap bookmark (tampilkan hanya isinya).', 'dp-googl-drive' ); ?></p>
			<p><code>[dp_googl_drive per_page="5"]</code> &mdash; <?php esc_html_e( 'jumlah bookmark per muat; sisanya dimuat otomatis saat di-scroll.', 'dp-googl-drive' ); ?></p>
			<p><code>[dp_googl_drive_form]</code> &mdash; <?php esc_html_e( 'form bagi pengunjung untuk mengirim link (menunggu persetujuan).', 'dp-googl-drive' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Settings page.
	 */
	public function render_settings() {
		$settings = DP_GDrive_Settings::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pengaturan DP Google Drive', 'dp-googl-drive' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="dp_gdrive_save_settings" />
				<?php wp_nonce_field( 'dp_gdrive_save_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dp-gdrive-per-page"><?php esc_html_e( 'Bookmark per muat', 'dp-googl-drive' ); ?></label></th>
						<td>
							<input type="number" min="1" id="dp-gdrive-per-page" name="per_page" value="<?php echo esc_attr( $settings['per_page'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Jumlah bookmark yang dimuat sekaligus. Sisanya dimuat otomatis saat pengunjung men-scroll (lazy loading).', 'dp-googl-drive' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dp-gdrive-cache"><?php esc_html_e( 'Masa cache folder (jam)', 'dp-googl-drive' ); ?></label></th>
						<td>
							<input type="number" min="1" id="dp-gdrive-cache" name="cache_lifetime" value="<?php echo esc_attr( $settings['cache_lifetime'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Berapa lama daftar isi folder disimpan sebelum diambil ulang dari Google.', 'dp-googl-drive' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kiriman pengunjung', 'dp-googl-drive' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="allow_guest_submit" value="1" <?php checked( $settings['allow_guest_submit'], 1 ); ?> />
								<?php esc_html_e( 'Izinkan pengunjung (tanpa login) mengirim link. Semua kiriman menunggu persetujuan.', 'dp-googl-drive' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hapus data', 'dp-googl-drive' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="delete_data" value="1" <?php checked( $settings['delete_data'], 1 ); ?> />
								<?php esc_html_e( 'Hapus semua bookmark dan pengaturan saat plugin di-uninstall.', 'dp-googl-drive' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue the editor helper script on bookmark screens.
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();
		if ( ! $screen || DP_GDrive_CPT::LINK !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'dp-googl-drive-admin',
			DP_GDRIVE_URL . 'assets/js/dp-googl-drive-admin.js',
			array(),
			DP_GDRIVE_VERSION,
			true
		);
	}

	/**
	 * Register the bookmark detail meta box.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'dp_gdrive_details',
			__( 'Detail Bookmark', 'dp-googl-drive' ),
			array( $this, 'render_metabox' ),
			DP_GDrive_CPT::LINK,
			'normal',
			'high'
		);
	}

	/**
	 * Render the bookmark detail meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'dp_gdrive_save_link', 'dp_gdrive_link_nonce' );

		$type = (string) get_post_meta( $post->ID, '_dp_gdrive_type', true );
		$type = in_array( $type, array( 'folder', 'file' ), true ) ? $type : 'folder';

		$url        = (string) get_post_meta( $post->ID, '_dp_gdrive_url', true );
		$folder_url = ( 'folder' === $type ) ? $url : '';
		$file_url   = ( 'file' === $type ) ? $url : '';

		$audience = (string) get_post_meta( $post->ID, '_dp_gdrive_audience', true );
		$audience = in_array( $audience, array( 'public', 'logged_in', 'roles' ), true ) ? $audience : 'public';

		$roles = (array) get_post_meta( $post->ID, '_dp_gdrive_roles', true );
		$roles = array_filter( array_map( 'sanitize_key', $roles ) );

		$submitter       = (string) get_post_meta( $post->ID, '_dp_gdrive_submitter', true );
		$submitter_email = (string) get_post_meta( $post->ID, '_dp_gdrive_submitter_email', true );

		$file_id       = (string) get_post_meta( $post->ID, '_dp_gdrive_id', true );
		$file_audience = (array) get_post_meta( $post->ID, '_dp_gdrive_file_audience', true );
		$file_items    = array();

		if ( 'folder' === $type && '' !== $file_id ) {
			$folder_data = DP_GDrive_Drive::fetch_folder( $file_id, (string) get_post_meta( $post->ID, '_dp_gdrive_resourcekey', true ) );

			if ( ! is_wp_error( $folder_data ) && ! empty( $folder_data['items'] ) ) {
				foreach ( $folder_data['items'] as $folder_item ) {
					if ( empty( $folder_item['is_folder'] ) && ! empty( $folder_item['id'] ) ) {
						$file_items[] = $folder_item;
					}
				}
			}
		}

		$status = (string) $post->post_status;
		if ( in_array( $status, array( 'auto-draft', 'draft', 'new' ), true ) ) {
			$status = $this->can_moderate() ? 'publish' : 'pending';
		}
		$status = ( 'pending' === $status ) ? 'pending' : 'publish';
		?>
		<div class="dp-gdrive-metabox">
			<p class="dp-gdrive-metabox__type">
				<strong><?php esc_html_e( 'Jenis', 'dp-googl-drive' ); ?></strong><br />
				<label><input type="radio" name="dp_gdrive_type" value="folder" <?php checked( $type, 'folder' ); ?> /> <?php esc_html_e( 'Folder', 'dp-googl-drive' ); ?></label>
				<label style="margin-left:1em"><input type="radio" name="dp_gdrive_type" value="file" <?php checked( $type, 'file' ); ?> /> <?php esc_html_e( 'File', 'dp-googl-drive' ); ?></label>
			</p>

			<p class="dp-gdrive-metabox__field dp-gdrive-metabox__field--folder">
				<label for="dp-gdrive-folder-url"><strong><?php esc_html_e( 'Link Folder', 'dp-googl-drive' ); ?></strong></label><br />
				<input type="url" id="dp-gdrive-folder-url" name="dp_gdrive_folder_url" value="<?php echo esc_attr( $folder_url ); ?>" class="large-text code" placeholder="https://drive.google.com/drive/folders/..." />
				<span class="description"><?php esc_html_e( 'Isi folder akan ditampilkan per item. Folder harus publik (“siapa saja yang memiliki link”).', 'dp-googl-drive' ); ?></span>
			</p>

			<p class="dp-gdrive-metabox__field dp-gdrive-metabox__field--file">
				<label for="dp-gdrive-file-url"><strong><?php esc_html_e( 'Link File', 'dp-googl-drive' ); ?></strong></label><br />
				<input type="url" id="dp-gdrive-file-url" name="dp_gdrive_file_url" value="<?php echo esc_attr( $file_url ); ?>" class="large-text code" placeholder="https://drive.google.com/file/d/..." />
				<span class="description"><?php esc_html_e( 'Satu berkas saja. Akan ditampilkan sebagai satu baris dengan tombol unduh.', 'dp-googl-drive' ); ?></span>
			</p>

			<p class="dp-gdrive-metabox__status">
				<label for="dp-gdrive-status"><strong><?php esc_html_e( 'Status', 'dp-googl-drive' ); ?></strong></label><br />
				<?php if ( $this->can_moderate() ) : ?>
					<select id="dp-gdrive-status" name="dp_gdrive_status">
						<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Menunggu', 'dp-googl-drive' ); ?></option>
						<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Disetujui (tampil)', 'dp-googl-drive' ); ?></option>
					</select>
				<?php else : ?>
					<?php esc_html_e( 'Menunggu', 'dp-googl-drive' ); ?>
					<span class="description"><?php esc_html_e( 'Admin akan meninjau sebelum bookmark tampil.', 'dp-googl-drive' ); ?></span>
				<?php endif; ?>
			</p>

			<p class="dp-gdrive-metabox__audience">
				<label for="dp-gdrive-audience"><strong><?php esc_html_e( 'Dibagi ke', 'dp-googl-drive' ); ?></strong></label><br />
				<select id="dp-gdrive-audience" name="dp_gdrive_audience">
					<option value="public" <?php selected( $audience, 'public' ); ?>><?php esc_html_e( 'Siapa saja (publik)', 'dp-googl-drive' ); ?></option>
					<option value="logged_in" <?php selected( $audience, 'logged_in' ); ?>><?php esc_html_e( 'Pengguna login', 'dp-googl-drive' ); ?></option>
					<option value="roles" <?php selected( $audience, 'roles' ); ?>><?php esc_html_e( 'Peran tertentu', 'dp-googl-drive' ); ?></option>
				</select>
				<span class="description"><?php esc_html_e( 'Menentukan siapa yang boleh mengunduh. Semua orang tetap melihat daftar file; tombol unduh dikunci bagi yang tidak berhak.', 'dp-googl-drive' ); ?></span>
			</p>

			<fieldset class="dp-gdrive-metabox__roles">
				<legend class="screen-reader-text"><?php esc_html_e( 'Peran yang boleh mengunduh', 'dp-googl-drive' ); ?></legend>
				<?php
				$wp_roles = wp_roles();
				if ( $wp_roles ) :
					foreach ( $wp_roles->roles as $slug => $role ) :
						?>
						<label style="display:block;margin-bottom:4px">
							<input type="checkbox" name="dp_gdrive_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $roles, true ), true ); ?> />
							<?php echo esc_html( ! empty( $role['name'] ) ? $role['name'] : $slug ); ?>
						</label>
						<?php
					endforeach;
				endif;
				?>
			</fieldset>

			<?php if ( 'folder' === $type ) : ?>
				<div class="dp-gdrive-metabox__files">
					<p><strong><?php esc_html_e( 'Akses per file', 'dp-googl-drive' ); ?></strong></p>
					<?php if ( empty( $file_items ) ) : ?>
						<p class="description"><?php esc_html_e( 'Belum ada file yang bisa diatur (folder kosong, privat, atau tautan belum disimpan).', 'dp-googl-drive' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Centang “Publik” agar semua orang bisa mengunduh. Hilangkan centang lalu pilih peran untuk membatasi; nama file tetap tampil, tetapi tombol unduhnya dikunci bagi yang tidak berhak. Tanpa peran dipilih, tombol unduh terkunci untuk semua orang (kecuali admin).', 'dp-googl-drive' ); ?></p>
						<?php
						$wp_roles_list = wp_roles();
						foreach ( $file_items as $file_item ) :
							$fid       = (string) $file_item['id'];
							$has_roles = isset( $file_audience[ $fid ] );
							$roles_set = $has_roles ? (array) $file_audience[ $fid ] : array();
							?>
							<div class="dp-gdrive-file">
								<input type="hidden" name="dp_gdrive_file_ids[]" value="<?php echo esc_attr( $fid ); ?>" />
								<label class="dp-gdrive-file__public">
									<input type="checkbox" name="dp_gdrive_file_public[<?php echo esc_attr( $fid ); ?>]" value="1" <?php checked( ! $has_roles ); ?> />
									<?php echo esc_html( isset( $file_item['name'] ) ? $file_item['name'] : $fid ); ?>
								</label>
								<div class="dp-gdrive-file__roles"<?php echo $has_roles ? '' : ' hidden'; ?>>
									<?php if ( $wp_roles_list && ! empty( $wp_roles_list->roles ) ) : ?>
										<?php foreach ( $wp_roles_list->roles as $role_slug => $role_obj ) : ?>
											<label><input type="checkbox" name="dp_gdrive_file_roles[<?php echo esc_attr( $fid ); ?>][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $roles_set, true ) ); ?> /> <?php echo esc_html( ! empty( $role_obj['name'] ) ? $role_obj['name'] : $role_slug ); ?></label>
										<?php endforeach; ?>
									<?php endif; ?>
									<?php if ( $has_roles && empty( $roles_set ) ) : ?>
										<p class="description"><?php esc_html_e( 'Belum ada peran dipilih — tombol unduh terkunci untuk semua orang (kecuali admin).', 'dp-googl-drive' ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $submitter || '' !== $submitter_email ) : ?>
				<p class="dp-gdrive-metabox__submitter">
					<strong><?php esc_html_e( 'Dikirim oleh', 'dp-googl-drive' ); ?></strong><br />
					<?php echo esc_html( '' !== $submitter ? $submitter : __( 'Tamu', 'dp-googl-drive' ) ); ?>
					<?php if ( '' !== $submitter_email ) : ?>
						<br /><a href="<?php echo esc_url( 'mailto:' . $submitter_email ); ?>"><?php echo esc_html( $submitter_email ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save the bookmark detail meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['dp_gdrive_link_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dp_gdrive_link_nonce'] ) ), 'dp_gdrive_save_link' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || DP_GDrive_CPT::LINK !== $post->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$type = ( isset( $_POST['dp_gdrive_type'] ) && 'file' === $_POST['dp_gdrive_type'] ) ? 'file' : 'folder';

		$folder_url = isset( $_POST['dp_gdrive_folder_url'] ) ? esc_url_raw( wp_unslash( $_POST['dp_gdrive_folder_url'] ) ) : '';
		$file_url   = isset( $_POST['dp_gdrive_file_url'] ) ? esc_url_raw( wp_unslash( $_POST['dp_gdrive_file_url'] ) ) : '';

		$url = ( 'file' === $type ) ? $file_url : $folder_url;
		if ( '' === $url ) {
			$url = ( 'file' === $type ) ? $folder_url : $file_url;
		}

		$parsed = DP_GDrive_Drive::parse( $url );
		if ( ! empty( $parsed['type'] ) ) {
			$type = $parsed['type'];
		}

		update_post_meta( $post_id, '_dp_gdrive_type', $type );
		update_post_meta( $post_id, '_dp_gdrive_url', $url );
		update_post_meta( $post_id, '_dp_gdrive_id', isset( $parsed['id'] ) ? $parsed['id'] : '' );
		update_post_meta( $post_id, '_dp_gdrive_resourcekey', isset( $parsed['resourcekey'] ) ? $parsed['resourcekey'] : '' );

		$audience = isset( $_POST['dp_gdrive_audience'] ) ? sanitize_key( wp_unslash( $_POST['dp_gdrive_audience'] ) ) : 'public';
		if ( ! in_array( $audience, array( 'public', 'logged_in', 'roles' ), true ) ) {
			$audience = 'public';
		}
		$roles = array();
		if ( isset( $_POST['dp_gdrive_roles'] ) && is_array( $_POST['dp_gdrive_roles'] ) ) {
			$roles = array_values( array_filter( array_map( 'sanitize_key', wp_unslash( $_POST['dp_gdrive_roles'] ) ) ) );
		}
		update_post_meta( $post_id, '_dp_gdrive_audience', $audience );
		update_post_meta( $post_id, '_dp_gdrive_roles', $roles );

		$file_ids  = isset( $_POST['dp_gdrive_file_ids'] ) && is_array( $_POST['dp_gdrive_file_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['dp_gdrive_file_ids'] ) ) : array();
		$file_pub  = isset( $_POST['dp_gdrive_file_public'] ) && is_array( $_POST['dp_gdrive_file_public'] ) ? wp_unslash( $_POST['dp_gdrive_file_public'] ) : array();
		$file_role = isset( $_POST['dp_gdrive_file_roles'] ) && is_array( $_POST['dp_gdrive_file_roles'] ) ? wp_unslash( $_POST['dp_gdrive_file_roles'] ) : array();

		if ( ! empty( $file_ids ) ) {
			$file_audience = array();

			foreach ( $file_ids as $file_id ) {
				$file_id = (string) $file_id;

				if ( '' === $file_id || ! empty( $file_pub[ $file_id ] ) ) {
					continue;
				}

				$file_roles = array();
				if ( isset( $file_role[ $file_id ] ) && is_array( $file_role[ $file_id ] ) ) {
					$file_roles = array_values( array_filter( array_map( 'sanitize_key', $file_role[ $file_id ] ) ) );
				}

				$file_audience[ $file_id ] = $file_roles;
			}

			update_post_meta( $post_id, '_dp_gdrive_file_audience', $file_audience );
		}

		$valid  = ! empty( $parsed['id'] );
		$status = ( $valid && $this->can_moderate() && isset( $_POST['dp_gdrive_status'] ) && 'publish' === $_POST['dp_gdrive_status'] ) ? 'publish' : 'pending';

		if ( ! $valid ) {
			set_transient( 'dp_gdrive_admin_notice_' . get_current_user_id(), 'invalid_link', MINUTE_IN_SECONDS );
		}

		if ( $post->post_status !== $status ) {
			remove_action( 'save_post_' . DP_GDrive_CPT::LINK, array( $this, 'save_metabox' ), 10 );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $status,
				)
			);
			add_action( 'save_post_' . DP_GDrive_CPT::LINK, array( $this, 'save_metabox' ), 10, 2 );
		}
	}

	/**
	 * Human label for an audience value.
	 *
	 * @param string $audience Audience key.
	 * @return string
	 */
	protected function audience_label( $audience ) {
		switch ( $audience ) {
			case 'logged_in':
				return __( 'Pengguna login', 'dp-googl-drive' );
			case 'roles':
				return __( 'Peran tertentu', 'dp-googl-drive' );
			default:
				return __( 'Siapa saja (publik)', 'dp-googl-drive' );
		}
	}

	/**
	 * Bookmark list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['dp_gdrive_type']     = __( 'Jenis', 'dp-googl-drive' );
				$new['dp_gdrive_audience'] = __( 'Akses', 'dp-googl-drive' );
				$new['dp_gdrive_status']   = __( 'Status', 'dp-googl-drive' );
				$new['dp_gdrive_source']   = __( 'Pengirim', 'dp-googl-drive' );
			}
			$new[ $key ] = $label;
		}

		return $new;
	}

	/**
	 * Bookmark column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ) {
		if ( 'dp_gdrive_type' === $column ) {
			$type = (string) get_post_meta( $post_id, '_dp_gdrive_type', true );
			echo 'folder' === $type
				? '<span class="dp-gdrive-badge">' . esc_html__( 'Folder', 'dp-googl-drive' ) . '</span>'
				: '<span class="dp-gdrive-badge">' . esc_html__( 'File', 'dp-googl-drive' ) . '</span>';
		} elseif ( 'dp_gdrive_audience' === $column ) {
			$audience = (string) get_post_meta( $post_id, '_dp_gdrive_audience', true );
			echo esc_html( $this->audience_label( $audience ) );
		} elseif ( 'dp_gdrive_status' === $column ) {
			$status = get_post_status( $post_id );
			if ( 'publish' === $status ) {
				echo '<span class="dp-gdrive-badge dp-gdrive-badge--on">' . esc_html__( 'Disetujui', 'dp-googl-drive' ) . '</span>';
			} else {
				echo '<span class="dp-gdrive-badge dp-gdrive-badge--pending">' . esc_html__( 'Menunggu', 'dp-googl-drive' ) . '</span>';
			}
		} elseif ( 'dp_gdrive_source' === $column ) {
			$name  = (string) get_post_meta( $post_id, '_dp_gdrive_submitter', true );
			$email = (string) get_post_meta( $post_id, '_dp_gdrive_submitter_email', true );

			if ( '' === $name && '' === $email ) {
				echo esc_html__( 'Admin', 'dp-googl-drive' );
			} else {
				echo esc_html( '' !== $name ? $name : __( 'Tamu', 'dp-googl-drive' ) );

				if ( '' !== $email ) {
					echo '<br /><a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
				}
			}
		}
	}

	/**
	 * Row actions: approve / unapprove.
	 *
	 * @param array   $actions Existing actions.
	 * @param WP_Post $post    Post object.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( DP_GDrive_CPT::LINK !== $post->post_type || ! $this->can_moderate() || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		if ( 'pending' === $post->post_status ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=dp_gdrive_approve&post=' . $post->ID ),
				'dp_gdrive_status_' . $post->ID
			);
			$actions['dp_gdrive_approve'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Setujui', 'dp-googl-drive' ) . '</a>';
		} elseif ( 'publish' === $post->post_status ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=dp_gdrive_reject&post=' . $post->ID ),
				'dp_gdrive_status_' . $post->ID
			);
			$actions['dp_gdrive_reject'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Batalkan', 'dp-googl-drive' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Handle approve / reject status changes.
	 */
	public function handle_status() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		check_admin_referer( 'dp_gdrive_status_' . $post_id );

		if ( ! $post_id || ! $this->can_moderate() || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Anda tidak punya izin untuk tindakan ini.', 'dp-googl-drive' ) );
		}

		$status = ( 'dp_gdrive_approve' === $action ) ? 'publish' : 'pending';

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'        => DP_GDrive_CPT::LINK,
					'dp_gdrive_notice' => ( 'publish' === $status ) ? 'approved' : 'rejected',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Save plugin settings.
	 */
	public function handle_save_settings() {
		check_admin_referer( 'dp_gdrive_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Anda tidak punya izin untuk tindakan ini.', 'dp-googl-drive' ) );
		}

		$settings = array(
			'per_page'           => isset( $_POST['per_page'] ) ? max( 1, absint( wp_unslash( $_POST['per_page'] ) ) ) : 5,
			'cache_lifetime'     => isset( $_POST['cache_lifetime'] ) ? max( 1, absint( wp_unslash( $_POST['cache_lifetime'] ) ) ) : 6,
			'allow_guest_submit' => isset( $_POST['allow_guest_submit'] ) ? 1 : 0,
			'delete_data'        => isset( $_POST['delete_data'] ) ? 1 : 0,
		);

		update_option( DP_GDrive_Settings::OPTION, $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::MENU_SLUG . '-settings',
					'dp_gdrive_notice' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
