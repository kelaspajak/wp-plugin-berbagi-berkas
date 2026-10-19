# AGENTS.md

Panduan kerja untuk agen/AI di proyek plugin WordPress **DP Google Drive**.

## 7 Aturan

### 01 — No faked tests
Jangan pernah mengarang hasil tes, lulus `php -l`, atau output yang belum benar-benar
dijalankan. Jalankan perintahnya dan laporkan output sebenarnya. Kalau belum diuji,
katakan "belum diuji" — jangan klaim lulus.

### 02 — Verify every package
Sebelum memakai library, fungsi, kelas, atau API, pastikan benar-benar ada di
lingkungan ini (baca file/`composer.json`/kode sekitar). Jangan berasumsi sesuatu
tersedia hanya karena "umum". Untuk fungsi WordPress, pakai fungsi inti yang sudah
ada — jangan bikin pengganti sendiri.

### 03 — Check the installed version
Cek versi PHP, WordPress, dan dependensi yang terpasang sebelum menulis kode.
Target plugin: **PHP 7.4+**, **WordPress 5.8+**. Hindari sintaks/ fungsi yang lebih
baru dari itu tanpa guard.

### 04 — Prove it's done
Buktikan pekerjaan selesai dengan bukti nyata: output lint, hasil tes, atau
langkah reproduksi yang berhasil. Untuk setiap perubahan PHP, jalankan:

```powershell
& "E:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" -l <path-file.php>
```

### 05 — Kill the fix loop
Kalau satu perbaikan gagal dua kali, berhenti menebak. Kumpulkan bukti (log, output,
perilaku), cari akar masalahnya, baru perbaiki. Jangan mengulang percobaan yang sama
berulang-ulang.

### 06 — Stay in scope
Kerjakan hanya yang diminta. Jangan refactor, ganti nama, atau "rapikan" kode lain
tanpa diminta. Perubahan sekecil mungkin, terfokus, dan konsisten dengan gaya yang
sudah ada.

### 07 — No silent deletes
Jangan menghapus file, kode, data, atau baris apa pun secara diam-diam. Sebutkan apa
yang dihapus dan alasannya. Untuk perubahan besar/destruktif, minta konfirmasi dulu.

## Konteks Proyek

- **Teknologi:** PHP murni (plugin WordPress). Tanpa build step, tanpa Composer runtime.
- **Arsitektur:** singleton `DP_GDrive` + komponen terpisah; admin class dimuat dari `includes/class-dp-gdrive.php`.
- **Tanpa API key Google:** isi folder dibaca dari `https://drive.google.com/embeddedfolderview?hl=en&id={ID}` lalu di-parse `div.flip-entry`.

### Konvensi kode
- Indentasi: **tab**.
- Prefix fungsi: `dp_gdrive_*`.
- Nama kelas: `DP_GDrive_*`.
- Konstanta: `DP_GDRIVE_*`.
- Text domain: `dp-googl-drive`.
- Jangan menambah komentar kecuali diminta.
- Escape output (`esc_html`, `esc_attr`, `esc_url`) dan sanitasi input (`sanitize_*`, `wp_unslash`). Jaga nonce & kapabilitas.

### Struktur utama
- `dp-googl-drive.php` — bootstrap & konstanta.
- `includes/class-dp-gdrive-drive.php` — parse URL/folder, cache, URL unduh.
- `includes/class-dp-gdrive-cpt.php` — CPT `dp_gdrive_link`.
- `includes/class-dp-gdrive-templates.php` — render daftar/item/paginasi.
- `includes/class-dp-gdrive-shortcodes.php` — shortcode, submit, AJAX expand.
- `includes/class-dp-gdrive-settings.php` — option `dp_gdrive_settings`.
- `admin/class-dp-gdrive-admin.php` — menu, meta box, kolom.
- `assets/` — CSS & JS.

### Verifikasi sebelum menandai selesai
1. `php -l` pada semua file yang diubah.
2. Pastikan tidak ada regresi pada alur: tambah bookmark, persetujuan, audience, buka subfolder.
3. Laporkan bukti (output perintah / langkah reproduksi).

## Referensi
- `README.md` — dokumentasi lengkap plugin.
