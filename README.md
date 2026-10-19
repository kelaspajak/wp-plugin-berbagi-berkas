# DP Google Drive

Ubah tautan Google Drive menjadi **bookmark**. Tautan folder dibaca otomatis dan
ditampilkan **per item** dengan tombol unduh. Kiriman pengunjung masuk sebagai
**menunggu persetujuan** admin sebelum tampil.

Dibuat tanpa API key Google — isi folder dibaca dari endpoint publik
`embeddedfolderview`. Folder harus dibagikan sebagai **"Siapa saja yang memiliki
link"**.

- **Versi:** 1.0.0
- **Butuh WordPress:** 5.8+
- **Butuh PHP:** 7.4+
- **Lisensi:** GPL-2.0-or-later
- **Text domain:** `dp-googl-drive`

## Fitur

- **Dua jenis bookmark** — Folder atau File, dipilih saat menambah.
- **Folder dirinci per item** — nama, tanggal diubah, ikon, dan tombol unduh untuk tiap file.
- **Telusuri subfolder (gaya Google Drive)** — klik nama folder untuk masuk ke isinya, lengkap dengan breadcrumb untuk naik; tanpa reload halaman. Posisi folder tercermin di URL (`?dp_gdrive_trail=…`) sehingga bisa di-bookmark dan tombol Back bekerja.
- **Anak yang sudah jadi bookmark disembunyikan** — item yang punya entri bookmark sendiri tidak diulang di rincian folder induknya.
- **Akses per file** — di meta box folder, tiap file punya centang “Publik”; hilangkan centang untuk membatasinya ke peran tertentu. File terbatas tetap tampil tetapi tombol unduhnya terkunci.
- **Kiriman pengunjung** — siapa pun bisa menempel tautan lewat form; masuk sebagai *Menunggu*, admin menyetujui dari daftar. Tautan yang sudah ada ditolak otomatis.
- **Moderasi** — bookmark yang dibuat pengguna non-admin otomatis berstatus *Menunggu*; hanya admin (kapabilitas `manage_options`) yang bisa menyetujui. Admin diberi notifikasi lewat angka pada menu dan pemberitahuan saat ada yang menunggu.
- **Kontrol akses (audience)** — tampilkan ke *Siapa saja*, *Pengguna login*, atau *Peran tertentu*.
- **Auto-deteksi** — jenis (folder/file), ID, dan resource key dikoreksi otomatis dari tautan.
- **Cache** — hasil pembacaan folder disimpan sementara agar tidak memanggil Google tiap tampilan.
- **Tanpa API key** — cukup menempel tautan.

## Instalasi

1. Salin folder `dp-googl-drive` ke `wp-content/plugins/`.
2. Aktifkan lewat menu **Plugins** di WordPress.
3. Atur di **DP Google Drive → Pengaturan** (opsional).
4. Tambahkan bookmark atau pasang shortcode (lihat di bawah).

## Penggunaan

### Shortcode

| Shortcode | Fungsi |
| --- | --- |
| `[dp_googl_drive]` | Menampilkan daftar bookmark yang sudah disetujui. |
| `[dp_googl_drive_form]` | Menampilkan form bagi pengunjung untuk mengirim tautan. |

Contoh halaman:

```
[dp_googl_drive_form]

[dp_googl_drive]
```

### Menambah bookmark (admin)

1. Buka **DP Google Drive → Tambah Link**.
2. Pilih **Jenis** (Folder/File) dan tempel **Link Folder** atau **Link File**.
3. Tentukan **Status** (*Menunggu* / *Disetujui*) dan **Dibagi ke** (*Siapa saja* / *Pengguna login* / *Peran tertentu*).
4. Simpan.

Admin yang menambah manual memakai status **Disetujui** secara default. Jika
tautan tidak valid, bookmark otomatis disimpan sebagai *Menunggu* dan muncul
notis kesalahan.

### Menyetujui kiriman pengunjung

Di **DP Google Drive → Semua Link**, gunakan aksi baris **Setujui** / **Batalkan**.

## Kontrol akses (Audience)

| Pilihan | Yang melihat |
| --- | --- |
| Siapa saja (publik) | Semua pengunjung. Kiriman pengunjung selalu memakai ini. |
| Pengguna login | Hanya pengguna yang sudah login. |
| Peran tertentu | Hanya pengguna login yang salah satu perannya dicentang. |

Administrator (kapabilitas `manage_options`) selalu melihat semua bookmark.

## Pengaturan

Menu **DP Google Drive → Pengaturan**. Opsi disimpan pada option `dp_gdrive_settings`.

| Kunci | Bawaan | Keterangan |
| --- | --- | --- |
| `per_page` | `20` | Jumlah bookmark per halaman pada daftar. |
| `cache_lifetime` | `6` | Lama cache pembacaan folder (jam). |
| `allow_guest_submit` | `1` | Izinkan tamu (tanpa login) mengirim tautan. |
| `delete_data` | `0` | Hapus semua data saat plugin dihapus. |

## Cara kerja

Tidak ada API key. Isi folder dibaca dari:

```
https://drive.google.com/embeddedfolderview?hl=en&id={ID}
```

(tambah `&resourcekey={KEY}` bila tautan menyertakan resource key). Endpoint
mengembalikan satu `div.flip-entry` per anak folder, yang diparse untuk
mengambil nama, jenis (dari ikon), tanggal diubah, ikon, pratinjau, dan tautan
unduh. Karena endpoint ini tidak resmi dan dapat berubah, hasilnya di-cache dan
parser dibuat toleran.

- **Unduhan:** file biasa lewat `drive.google.com/uc?export=download&id=...`; file Google native (Docs/Sheets/Slides/Drawing) lewat endpoint export.
- **Subfolder:** dimuat sesuai permintaan melalui AJAX (`admin-ajax.php`, aksi `dp_gdrive_expand`) dan memakai cache yang sama.

## Model data

Bookmark disimpan sebagai custom post type `dp_gdrive_link`.

| Status post | Arti |
| --- | --- |
| `pending` | Menunggu persetujuan |
| `publish` | Disetujui / tampil |

Post meta:

| Meta key | Isi |
| --- | --- |
| `_dp_gdrive_type` | `folder` atau `file` |
| `_dp_gdrive_url` | Tautan asli |
| `_dp_gdrive_id` | ID Google Drive |
| `_dp_gdrive_resourcekey` | Resource key (bila ada) |
| `_dp_gdrive_audience` | `public` / `logged_in` / `roles` |
| `_dp_gdrive_roles` | Array peran (bila audience `roles`) |
| `_dp_gdrive_file_audience` | Map `file_id => [peran]` untuk akses per file (absen = publik) |
| `_dp_gdrive_submitter` | Nama pengirim (kiriman tamu) |
| `_dp_gdrive_submitter_email` | Email pengirim (kiriman tamu) |

## Struktur berkas

```
dp-googl-drive/
├── dp-googl-drive.php              # Bootstrap, konstanta, hook aktivasi
├── uninstall.php                   # Pembersihan opsional saat dihapus
├── admin/
│   └── class-dp-gdrive-admin.php   # Menu, meta box, kolom, notices
├── includes/
│   ├── class-dp-gdrive.php         # Singleton, pemuat komponen
│   ├── class-dp-gdrive-activator.php
│   ├── class-dp-gdrive-cpt.php     # CPT dp_gdrive_link
│   ├── class-dp-gdrive-drive.php   # Parsing URL & folder, cache, URL unduh
│   ├── class-dp-gdrive-settings.php
│   ├── class-dp-gdrive-shortcodes.php  # Shortcode, submit, AJAX expand
│   └── class-dp-gdrive-templates.php   # Render daftar, item, paginasi
└── assets/
    ├── css/dp-googl-drive.css
    └── js/
        ├── dp-googl-drive.js       # Front-end: telusuri subfolder
        └── dp-googl-drive-admin.js # Editor: toggle field
```

## Batasan

- **Satu sumber publik saja.** Folder harus "Anyone with the link"; folder privat tidak terbaca.
- **Ukuran file tidak tersedia** dari `embeddedfolderview` (hanya nama, jenis, tanggal).
- **Markup tidak resmi** — perubahan di sisi Google dapat memengaruhi parsing.
- **Thumbnail pratinjau** dapat kedaluwarsa dari cache; jika kosong, ikon tipe dipakai sebagai gantinya.
- **Satu level per tampilan** — isi folder dimuat per level lewat AJAX, bukan seluruh pohon sekaligus.

## Changelog

### 1.0.0
- Rilis awal: CPT, bookmark folder/file, form kiriman pengunjung, alur persetujuan, kontrol audience, meta box admin, kolom admin, pengaturan, buka subfolder via AJAX, ikon per item.

## Lisensi

GPL-2.0-or-later — <https://www.gnu.org/licenses/gpl-2.0.html>
