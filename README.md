# Antrean Digital

Aplikasi Project Based Learning (PBL) untuk mengelola antrean kunjungan saat ini pada satu restoran di Kuta. Dibangun menggunakan Laravel 13, PHP 8.3+, MySQL 8.4, Blade, CSS, dan JavaScript biasa. Aplikasi tidak memerlukan proses build atau server Node.js untuk menampilkan halaman.

## Menjalankan di komputer ini

Dari PowerShell:

```powershell
cd D:\Git\PBL
.\scripts\start.ps1
```

Buka [halaman pelanggan](http://127.0.0.1:8085/) atau [dasbor staf](http://127.0.0.1:8085/staf). Informasi login demo lokal tersimpan di `storage/local/demo-credentials.txt`, yang dikecualikan dari Git. Gunakan data buatan saat mencoba aplikasi.

MySQL khusus proyek menggunakan port 3311 dan menyimpan data di `storage/local/mysql`. Pengembangan, pengujian otomatis, dan pemeriksaan akses serentak menggunakan database terpisah: `pbl`, `pbl_testing`, dan `pbl_concurrency`. Skrip tidak mengubah database coursework maupun layanan XAMPP/Laragon lainnya. Lokasi executable khusus komputer ini tersimpan di `storage/local/runtime.json`.

Tekan Ctrl+C untuk menghentikan server web. Jalankan `scripts/stop-database.ps1` untuk menghentikan MySQL milik proyek.

## Menyiapkan di komputer lain

1. install PHP 8.3+ beserta extension yang dibutuhkan Laravel, termasuk PDO MySQL, serta Composer dan MySQL 8.4.
2. Jalankan `composer install`, salin `.env.example` menjadi `.env`, lalu atur koneksi ke database baru khusus proyek. Jangan menggunakan database aplikasi lain.
3. Jalankan `php artisan key:generate`, kemudian `php artisan migrate`.
4. Buat akun staf dengan `php artisan pbl:staff staff@example.test`. Masukkan kata sandi melalui prompt tersembunyi. Registrasi staf secara publik tidak tersedia.
5. Jalankan `php artisan serve --host=127.0.0.1 --port=8085 --no-reload --quiet`.

Untuk pengujian otomatis, buat database terpisah, salin `.env` menjadi `.env.testing`, lalu sesuaikan `APP_ENV=testing`, `DB_DATABASE`, dan kredensialnya. Nama database pengujian harus berakhiran `_testing`. Pengujian menggunakan `RefreshDatabase`; jangan mengarahkannya ke database operasional.

## Verifikasi

```powershell
php artisan test --compact
php tests/Support/concurrency.php
node --check public/app.js
```

Skrip concurrency **menghapus dan membuat ulang tabel hanya di dalam `pbl_concurrency`**, yang harus dikhususkan untuk data pengujian yang boleh dihapus. Suite pengujian utama menggunakan `.env.testing` dan database `pbl_testing` pada komputer ini. Node.js hanya diperlukan untuk pemeriksaan sintaks JavaScript di atas, bukan untuk menjalankan aplikasi.

Hasil pemeriksaan dan batas verifikasinya dicatat dalam [docs/verification.md](docs/verification.md).

## Alur dan aturan antrean

Nama pelanggan dan jumlah orang wajib diisi; nomor telepon bersifat opsional. Pelanggan menyimpan tautan pribadi menuju tiket antreannya. Staf memanggil pelanggan berdasarkan urutan masuk atau first-in, first-out (FIFO) ketika tempat tersedia, mengonfirmasi kedatangan, lalu menyelesaikan tiket setelah pelanggan diarahkan ke meja. Beberapa rombongan dapat dipanggil dan menunggu kedatangan secara bersamaan.

Pembatalan atau tindakan melewati antrean mengakhiri tiket. Pelanggan yang kembali harus mengambil nomor baru.

Batas kedatangan 15 menit merupakan **aturan usulan yang masih perlu divalidasi**. Sistem menandai tiket yang melewati batas waktu, sedangkan staf menentukan pembatalannya. Perkiraan waktu tunggu belum tersedia sampai interval dikonfigurasi melalui dasbor. Nilai waktu tersebut perlu divalidasi bersama pihak restoran.

## Dokumentasi dan keamanan

Lihat [spesifikasi](docs/specification.md), [rancangan sistem](docs/design.md), dan [catatan verifikasi](docs/verification.md). Bahan referensi asli tersimpan di `reference/`. Sesuai klarifikasi pengguna, file presentasi tanpa `(1)` pada namanya merupakan revisi terbaru.

Presentasi pada 3 November 2026 merupakan PBL Tahap 1 yang berfokus pada perancangan. Aplikasi yang sudah berjalan tidak berarti seluruh deliverable penilaian atau validasi bersama pihak restoran telah selesai.

Tautan tiket merupakan rahasia akses. Jangan membagikan tangkapan layar URL atau log yang memuat tautan tersebut. Saat mengonfigurasi Nginx, gunakan `public` sebagai document root, nonaktifkan access log untuk rute `/tiket/`, aktifkan HTTPS, dan cegah akses ke `.env`, `storage`, serta root proyek.

Konfigurasi produksi dan kebijakan retensi data belum termasuk dalam demo lokal ini. Aplikasi tidak mencakup notifikasi SMS/WhatsApp, akun pelanggan, pembayaran, pemesanan menu, atau pemantauan ketersediaan meja secara otomatis.
