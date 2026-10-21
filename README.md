# DP Google Drive

Ubah tautan Google Drive menjadi **bookmark** dan tampilkan sebagai **katalog
berkas** yang bisa dicari dan difilter. Tautan folder dibaca otomatis dan
ditampilkan **per item** dengan tombol unduh. Kiriman pengunjung masuk sebagai
**menunggu persetujuan** admin sebelum tampil.

Dibuat tanpa API key Google — isi folder dibaca dari endpoint publik
`embeddedfolderview`. Folder harus dibagikan sebagai **"Siapa saja yang memiliki
link"**.

- **Versi:** 1.2.2
- **Butuh WordPress:** 5.8+
- **Butuh PHP:** 7.4+
- **Lisensi:** GPL-2.0-or-later
- **Text domain:** `dp-googl-drive`

## Fitur

- **Dua jenis bookmark** — Folder atau File, dipilih saat menambah.
- **Tampilan katalog** — kartu ringkas berisi judul, format, periode, status, dan deskripsi; isi Drive baru dimuat saat tombol **Lihat berkas** diklik (hemat resource).
- **Pencarian** — cari berdasarkan judul; hasil diproses di server sehingga tetap akurat meski daftar dimuat bertahap.
- **Metadata berkas** — deskripsi, format, periode/tahun pajak, versi, status katalog, kontributor, sumber asli, dan lisensi.
- **Folder dirinci per item** — nama, tanggal diubah, ikon, dan tombol unduh untuk tiap file.
- **Telusuri subfolder (gaya Google Drive)** — klik nama folder untuk masuk ke isinya, lengkap dengan breadcrumb untuk naik; tanpa reload halaman. Posisi folder tercermin di URL (`?dp_gdrive_trail=…`) sehingga bisa di-bookmark dan tombol Back bekerja.
- **Anak yang sudah jadi bookmark disembunyikan** — item yang punya entri bookmark sendiri tidak diulang di rincian folder induknya.
- **Akses per file** — di meta box folder, tiap file punya centang “Publik”; hilangkan centang lalu pilih peran untuk membatasi. Nama file tetap tampil; tombol unduhnya dikunci bagi yang tidak berhak. Tanpa peran dipilih, tombol unduh terkunci untuk semua orang (kecuali admin).
- **Kiriman pengunjung** — siapa pun bisa menempel tautan lewat dialog **Bagikan tautan**; masuk sebagai *Menunggu*, admin menyetujui dari daftar. Tautan yang sudah ada ditolak otomatis. Tombol **Tarik data** mengisi judul dari nama folder/berkas Google Drive.
- **Dialog progresif** — tanpa JavaScript, form tetap tampil langsung di halaman; dengan JavaScript, form dibuka sebagai dialog modal dan bisa ditutup lewat tombol, klik latar, atau Esc.
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
| `[dp_googl_drive]` | Menampilkan katalog berkas (kartu + pencarian). |
| `[dp_googl_drive view="table"]` | Menampilkan tabel dengan isi folder langsung terlihat. |
| `[dp_googl_drive show_title="no"]` | Menyembunyikan judul tiap bookmark (hanya isinya). |
| `[dp_googl_drive per_page="5"]` | Mengatur jumlah bookmark per muat (batch lazy loading). |
| `[dp_googl_drive_form]` | Menampilkan tombol **Bagikan tautan** yang membuka form dalam dialog. |
| `[dp_googl_drive_form label="Kirim berkas" title="Kirim berkas pajak"]` | Mengubah teks tombol dan judul dialog. |

Atribut `view` menerima `catalog` (default) atau `table`. Atribut `show_title`
menerima `yes` (default) atau `no`. Atribut `per_page` (lazy loading) menerima
bilangan bulat; jika kosong memakai nilai Pengaturan.

Katalog memakai parameter URL `dp_q` (kata kunci), jadi hasil pencarian bisa
dibagikan lewat tautan.

Contoh halaman:

```
[dp_googl_drive_form]

[dp_googl_drive]
```

### Menambah bookmark (admin)

1. Buka **DP Google Drive → Tambah Link**.
2. Pilih **Jenis** (Folder/File) dan tempel **Link Folder** atau **Link File**.
3. Isi **Katalog**: deskripsi, format, periode, versi, status katalog,
   kontributor, sumber asli, dan lisensi.
4. Tentukan **Status** (*Menunggu* / *Disetujui*) dan **Dibagi ke** (*Siapa saja* / *Pengguna login* / *Peran tertentu*).
5. Simpan.

Status katalog berbeda dari status post: status post menentukan tampil/tidak,
sedangkan status katalog (`Aktif`, `Arsip`, `Digantikan`, `Perlu ditinjau`)
memberi tahu pengunjung kondisi berkas.

Admin yang menambah manual memakai status **Disetujui** secara default. Jika
tautan tidak valid, bookmark otomatis disimpan sebagai *Menunggu* dan muncul
notis kesalahan.

### Menyetujui kiriman pengunjung

Di **DP Google Drive → Semua Link**, gunakan aksi baris **Setujui** / **Batalkan**.

## Katalog & metadata

Katalog menampilkan satu kartu per bookmark. Kartu tidak memanggil Google Drive;
isi folder baru diambil saat tombol **Lihat berkas** diklik, lalu ditampilkan di
bawah kartu.

Pencarian memakai judul berkas.

Status katalog:

| Status | Arti |
| --- | --- |
| `Aktif` | Berlaku saat ini |
| `Arsip` | Disimpan untuk arsip |
| `Digantikan` | Ada versi yang lebih baru |
| `Perlu ditinjau` | Menunggu pemeriksaan ulang |

## Kontrol akses (Audience)

Nama file & daftar folder **selalu tampil** untuk semua pengunjung. Yang dibatasi
adalah tombol **Unduh**: aktif bila pengunjung berhak, terkunci (disabled) bila tidak.

| Pilihan “Dibagi ke” | Tombol unduh aktif untuk |
| --- | --- |
| Siapa saja (publik) | Semua pengunjung. Kiriman pengunjung selalu memakai ini. |
| Pengguna login | Pengguna yang sudah login. |
| Peran tertentu | Pengguna login yang salah satu perannya dicentang. |

“Akses per file” menambahkan batas per berkas di dalam folder: file yang diberi
peran hanya bisa diunduh oleh peran itu; tanpa peran dipilih, tombol unduh
terkunci untuk semua. Administrator (kapabilitas `manage_options`) selalu bisa
mengunduh.

Catatan: tombol terkunci hanya membatasi lewat UI — tautan unduh mengarah
langsung ke Google Drive, jadi bukan penegakan keras.

## Pengaturan

Menu **DP Google Drive → Pengaturan**. Opsi disimpan pada option `dp_gdrive_settings`.

| Kunci | Bawaan | Keterangan |
| --- | --- | --- |
| `per_page` | `5` | Jumlah bookmark per muat (batch). Sisanya dimuat otomatis saat scroll. |
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
| `_dp_gdrive_file_audience` | Map `file_id => [peran]` untuk akses unduh per file (absen = ikut aturan bookmark; array kosong = tombol unduh terkunci untuk semua non-admin) |
| `_dp_gdrive_description` | Deskripsi singkat |
| `_dp_gdrive_format` | Format berkas (mis. PDF, XLSX, Folder) |
| `_dp_gdrive_period` | Periode / tahun pajak |
| `_dp_gdrive_version` | Versi berkas |
| `_dp_gdrive_status` | Status katalog: `active` / `archive` / `superseded` / `review` |
| `_dp_gdrive_contributor` | Nama kontributor |
| `_dp_gdrive_source_url` | Tautan sumber asli |
| `_dp_gdrive_license` | Lisensi / izin berbagi |
| `_dp_gdrive_rights_confirmed` | `1` bila pengirim menyatakan berhak membagikan |
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
│   └── class-dp-gdrive-templates.php   # Render katalog, daftar, item
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
- **Pencarian berbasis judul** — deskripsi dan metadata tidak ikut dicari.

## Changelog

### 1.2.2
- Badge Folder/File mengikuti warna statusnya (aktif, arsip, digantikan, perlu ditinjau).
- Ukuran font badge diperkecil.

### 1.2.1
- Badge jenis/status kini berbentuk pil dengan titik warna (gaya shadcn/Flowbite).
- Warna status dibedakan: aktif hijau, perlu ditinjau kuning, arsip abu-abu, digantikan merah.

### 1.2.0
- Form kiriman menjadi dialog **Bagikan tautan** dengan atribut `label` dan `title`.
- Tombol **Tarik data** mengisi judul dari nama folder/file Google Drive.
- Dialog memakai `<dialog>` dengan fallback progresif: tetap tampil tanpa JavaScript.
- Notis kiriman sukses tampil di halaman; notis kesalahan/duplikat tampil di dalam dialog yang terbuka otomatis.
- Pengkategorian dihapus dari katalog, form kiriman, dan meta box; pencarian memakai judul saja.

### 1.1.0
- Tampilan katalog (kartu) sebagai default, dengan pencarian dan filter kategori.
- Metadata katalog: kategori, deskripsi, format, periode, versi, status, kontributor, sumber, lisensi.
- Kategori berupa input bebas dengan saran dari kategori yang sudah dipakai.
- Form kiriman meminta kategori dan pernyataan hak berbagi.
- Isi Drive pada kartu dimuat sesuai permintaan (hemat resource).

### 1.0.0
- Rilis awal: CPT, bookmark folder/file, form kiriman pengunjung, alur persetujuan, kontrol audience, meta box admin, kolom admin, pengaturan, buka subfolder via AJAX, ikon per item.

## Lisensi

GPL-2.0-or-later — <https://www.gnu.org/licenses/gpl-2.0.html>
