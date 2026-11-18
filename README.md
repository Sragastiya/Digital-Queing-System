# Antrean Digital

Aplikasi Project Based Learning (PBL) untuk mengelola dua antrean kunjungan pada satu restoran di Kuta: **Makan di Tempat** dan **Bawa Pulang**. Kedua antrean memiliki urutan, posisi, jumlah menunggu, panggilan, dan pengelolaan staf masing-masing. Dibangun menggunakan Laravel 13, PHP 8.3+, MySQL 8.4, Blade, CSS, dan JavaScript biasa. Aplikasi tidak memerlukan proses build atau server Node.js untuk menampilkan halaman.

## Urutan penggunaan

Jika proyek belum pernah disiapkan pada instalasi ini, mulai dari **Persiapan awal**. Setelah persiapan selesai, langsung ikuti **Menjalankan aplikasi** setiap kali ingin menggunakannya. Persiapan database, pembuatan application key, dan pembuatan akun staf tidak perlu diulang setiap kali menjalankan aplikasi.

Semua perintah dijalankan dari folder utama proyek, yaitu folder yang berisi `artisan` dan `composer.json`. Contoh lokasi proyek adalah `D:\Git\PBL`; sesuaikan dengan lokasi hasil clone atau unduhan Anda.

## Persiapan awal — sekali untuk setiap instalasi

1. Siapkan PHP 8.3+ beserta extension yang dibutuhkan Laravel, termasuk PDO MySQL, serta Composer dan MySQL 8.4. Pastikan perintah `php` dan `composer` tersedia di terminal.
2. Clone atau unduh repository, lalu buka PowerShell di folder utama proyek. Instal dependency:

   ```powershell
   composer install
   ```

3. Jika `.env` belum ada, salin file contohnya. Pertahankan `.env` yang sudah dikonfigurasi pada instalasi yang sudah berjalan.

   ```powershell
   Copy-Item .env.example .env
   ```

4. Jalankan server MySQL, lalu buat database baru khusus proyek dan siapkan akun MySQL yang dapat mengaksesnya melalui alat administrasi database yang Anda gunakan. Atur `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dalam `.env` sesuai konfigurasi tersebut. Nilai pada `.env.example` hanyalah contoh; port 3311 tidak wajib untuk instalasi baru. Jangan menggunakan database aplikasi lain.
5. Buat application key dan tabel aplikasi. MySQL harus tetap berjalan saat perintah ini dijalankan.

   ```powershell
   php artisan key:generate
   php artisan migrate
   ```

6. Buat akun staf untuk masuk ke dasbor dengan perintah berikut:

   ```powershell
   php artisan pbl:staff staff@example.test
   ```

   `staff@example.test` adalah **contoh alamat email untuk login**, bukan kata sandi. Ganti dengan alamat email yang ingin digunakan.

   **Kata sandi tidak ditulis dalam perintah.** Setelah perintah dijalankan, terminal meminta `Kata sandi baru (minimal 12 karakter)`. Ketik kata sandi pilihan Anda, lalu tekan Enter. Karakter yang diketik disembunyikan oleh terminal. Gunakan email dan kata sandi tersebut untuk masuk ke dasbor staf.

   Registrasi staf secara publik tidak tersedia. File login demo `storage/local/demo-credentials.txt` hanya ada pada instalasi demo yang sudah dikonfigurasi dan tidak disertakan dalam Git.

Setelah keenam langkah selesai, lanjutkan ke bagian berikutnya. Gunakan data buatan saat mencoba aplikasi.

## Menjalankan aplikasi — setiap kali digunakan

1. Pastikan server MySQL yang sesuai dengan `.env` sudah berjalan. Jika MySQL dijalankan sebagai layanan otomatis, cukup pastikan layanannya aktif.
2. Buka PowerShell di folder utama proyek, lalu jalankan server web:

   ```powershell
   php artisan serve --host=127.0.0.1 --port=8085 --no-reload --quiet
   ```

3. Biarkan terminal tersebut terbuka. Buka [halaman pelanggan](http://127.0.0.1:8085/) atau [dasbor staf](http://127.0.0.1:8085/staf). Untuk mengakses dasbor staf, masuk menggunakan akun yang dibuat saat persiapan awal.

### Pintasan untuk instalasi dengan konfigurasi lokal

Jika instalasi **sudah memiliki** `storage/local/runtime.json` dengan lokasi executable yang benar dan database MySQL proyek yang sudah diinisialisasi di `storage/local/mysql`, perintah berikut dapat menggantikan langkah menjalankan MySQL dan server web di atas:

```powershell
.\scripts\start.ps1
```

Konfigurasi lokal ini tidak disertakan dalam Git dan tidak dibuat oleh `composer install` atau `php artisan migrate`. Pada konfigurasi tersebut, skrip memakai MySQL khusus proyek pada port 3311, sehingga koneksi `.env` juga harus sesuai. Skrip tidak menginisialisasi database atau membuat akun MySQL baru. Tanpa `runtime.json`, skrip hanya menjalankan server web menggunakan `php` dari terminal; MySQL tetap perlu dijalankan sendiri.

## Menghentikan aplikasi

Tekan **Ctrl+C** pada terminal server web. Jika MySQL khusus proyek dijalankan melalui `scripts/start.ps1`, hentikan juga melalui:

```powershell
.\scripts\stop-database.ps1
```

Jika MySQL dikelola melalui layanan atau alat lain, kelola penghentiannya melalui cara tersebut dengan memperhatikan aplikasi lain yang mungkin memakai server yang sama.

## Memperbarui aplikasi

Bagian ini berlaku saat memasang pembaruan pada instalasi yang sudah disiapkan. Pertahankan `.env`, application key, akun staf, dan data yang sudah ada; jangan mengulang seluruh persiapan awal.

Cadangkan database sebelum menerapkan perubahan schema. Setelah memperbarui kode, jalankan `composer install` bila dependency berubah dan `php artisan migrate` bila pembaruan menambahkan migration. Pastikan MySQL aktif saat menjalankan migration.

Untuk pembaruan dari versi satu antrean, migration `2026_11_17_000001_add_queue_types` menambahkan jenis layanan ke tiket dan riwayat. Data antrean meja sebelumnya masuk Makan di Tempat; nomor, tautan privat, status, waktu, dan riwayatnya dipertahankan. Rollback memerlukan penghentian sementara seluruh penulisan; migration menolak rollback apabila tiket atau riwayat Bawa Pulang masih ada agar klasifikasi layanan tidak hilang.

## Verifikasi

Bagian ini untuk pengembangan dan pengujian, bukan langkah wajib setiap kali menjalankan aplikasi. Siapkan database pengujian terpisah, salin konfigurasi `.env` menjadi `.env.testing`, lalu sesuaikan `APP_ENV=testing`, `DB_DATABASE`, dan kredensialnya. Nama database pengujian harus berakhiran `_testing`. Pengujian menggunakan `RefreshDatabase`; jangan mengarahkannya ke database operasional.

```powershell
php artisan test --compact
php tests/Support/concurrency.php
node --check public/app.js
```

Pengembangan, pengujian otomatis, dan pemeriksaan akses serentak menggunakan database terpisah. Nama yang digunakan pada konfigurasi demo adalah `pbl`, `pbl_testing`, dan `pbl_concurrency`. Skrip concurrency **menghapus dan membuat ulang tabel hanya di dalam `pbl_concurrency`**, yang harus disiapkan khusus untuk data pengujian yang boleh dihapus. Suite pengujian utama menggunakan koneksi dalam `.env.testing`. Node.js hanya diperlukan untuk pemeriksaan sintaks JavaScript di atas, bukan untuk menjalankan aplikasi.

Hasil pemeriksaan dan batas verifikasinya dicatat dalam [docs/verification.md](docs/verification.md).

## Alur dan aturan antrean

Jenis layanan, nama pelanggan, dan jumlah orang wajib diisi; nomor telepon bersifat opsional. Pelanggan menyimpan tautan pribadi menuju tiket antreannya. Halaman tiket hanya menghitung posisi, perkiraan, dan panggilan pada layanan tiket tersebut. Staf memilih tab layanan, lalu memanggil berdasarkan urutan masuk atau first-in, first-out (FIFO) pada antrean itu. Panggilan atau perubahan tiket pada satu antrean tidak memajukan antrean lainnya.

Kedua layanan mempertahankan tahap Menunggu → Dipanggil → Hadir → Selesai. Staf mengonfirmasi kedatangan sebelum menyelesaikan tiket. Pada Makan di Tempat, selesai berarti sudah diarahkan ke meja; Bawa Pulang menampilkan konfirmasi penyelesaian layanan oleh staf tanpa menambah tahap pemesanan/penyiapan baru. Beberapa pelanggan tetap dapat dipanggil dan menunggu kedatangan secara bersamaan.

Nomor tetap memakai urutan global `A-…` tanpa reset, sehingga tiket lama tidak berubah; nomor yang diselingi layanan lain bukan posisi antrean. Konvensi nomor `M-…`/`B-…` pada Figma belum diterapkan sebagai aturan baru. Interval perkiraan dan batas hadir yang sudah ada tetap dipakai bersama; perhitungan jumlah di depan dan estimasi dipisahkan menurut layanan.

Pembatalan atau tindakan melewati antrean mengakhiri tiket. Pelanggan yang kembali harus mengambil nomor baru.

Batas kedatangan 15 menit merupakan **aturan usulan yang masih perlu divalidasi**. Sistem menandai tiket yang melewati batas waktu, sedangkan staf menentukan pembatalannya. Perkiraan waktu tunggu belum tersedia sampai interval dikonfigurasi melalui dasbor. Nilai waktu tersebut perlu divalidasi bersama pihak restoran.

## Dokumentasi dan keamanan

Lihat [spesifikasi](docs/specification.md), [rancangan sistem](docs/design.md), dan [catatan verifikasi](docs/verification.md). Panduan kanonis dan sumber akademik berada di `D:\ChatGPT\Project Based Learning`; baca `AGENTS.md`, `INDEX.md`, dan `DECISIONS.md` di sana. Seluruh presentasi bersifat historis dan tidak menjadi sumber otoritatif saat ini. Proposal revisi 29 halaman telah diindeks sebagai sumber tersedia, tetapi tidak menyetujui seluruh aturan usulannya. Direktori `reference/` yang dipensiunkan bukan baseline sumber saat ini.

Presentasi pada 3 November 2026 merupakan PBL Tahap 1 yang berfokus pada perancangan. Aplikasi yang sudah berjalan tidak berarti seluruh deliverable penilaian atau validasi bersama pihak restoran telah selesai.

Tautan tiket merupakan rahasia akses. Jangan membagikan tangkapan layar URL atau log yang memuat tautan tersebut. Saat mengonfigurasi Nginx, gunakan `public` sebagai document root, nonaktifkan access log untuk rute `/tiket/`, aktifkan HTTPS, dan cegah akses ke `.env`, `storage`, serta root proyek.

Konfigurasi produksi dan kebijakan retensi data belum termasuk dalam demo lokal ini. Aplikasi tidak mencakup notifikasi SMS/WhatsApp, akun pelanggan, pembayaran, pemesanan menu, atau pemantauan ketersediaan meja secara otomatis.
