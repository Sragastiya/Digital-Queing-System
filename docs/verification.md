# Verifikasi dan pekerjaan lanjutan

## Rekonsiliasi konteks — 18 November 2026

- Working tree awal bersih pada commit `95422e1` (17 November). Kode, schema, rute, controller, formulir dan pengujian dibandingkan dengan rekaman implementasi di S-SPEC. Cakupan dua antrean sesuai; nomor A global, settings/interval/grace bersama dan lifecycle lama tetap dipertahankan.
- Pemeriksaan baru: **15 tests, 174 assertions**, lulus memakai filter `(IndependentQueuesTest::test_(?!forward_migration|rollback)|TwoQueueHttpTest)` pada SQLite `:memory:`. Koneksi dipilih melalui environment proses dan guard membaca nama koneksi yang sudah di-resolve. Kasus mencakup FIFO/posisi/count/ETA/panggilan per layanan, jalur HTTP pelanggan/staf, penolakan tindakan/retry silang antrean dan panggilan kosong.
- Tidak ada service yang dimulai/dihentikan, database persisten yang diuji, atau migration/rollback operasional yang dijalankan. Kasus migration/rollback sengaja tidak dipilih. Hasil MySQL, concurrency, rollout lokal dan browser tanggal 17 November di bawah dipakai sebagai bukti historis untuk commit yang sama, bukan hasil pemeriksaan baru. Keadaan database lokal saat ini dan live Figma tidak diperiksa ulang.
- Perubahan hanya pada dokumentasi: availability proposal/U01, observasi historis D01, batas implementasi/U04, prioritas sumber S-SPEC/README dan discrepancy notes INDEX. Riwayat persetujuan, ID keputusan dan pertanyaan terbuka dipertahankan. Proposal revisi tidak dipromosikan menjadi persetujuan bisnis.
- Handoff V2 lokal dan direktori verifikasinya tetap tidak tersedia. `AGENTS.md.bad` masih terlacak Git; tidak dibaca sebagai panduan, diubah atau dikeluarkan dari tracking dalam tugas ini.

## Dua antrean — pemeriksaan 17 November 2026

- PHPUnit: **34 tests, 282 assertions**, lulus pada SQLite `:memory:` dan MySQL `pbl_testing`. Guard database tetap memeriksa koneksi yang sudah di-resolve sebelum RefreshDatabase.
- Cakupan baru meliputi pemilihan layanan wajib, FIFO/posisi/count/ETA/panggilan terakhir per layanan, pengelolaan staf dan konteks tab, pencarian/paginasi setelah polling, pembatalan/skip/penyelesaian, dan penolakan operation_id yang digunakan silang antrean, termasuk panggilan kosong.
- Migration diperiksa dengan data lama sintetis: nomor/hash akses/status/timestamp/event/counter dipertahankan; rollback menolak tiket Bawa Pulang maupun event panggilan kosong yang akan kehilangan jenisnya.
- Migration tambahan juga diterapkan pada database lokal `pbl` setelah backup SQL tersimpan dan SHA-256 file backup diverifikasi. Server HTTP proyek tidak aktif dan tidak ada koneksi database proyek lain sebelum rollout; maintenance sementara dipulihkan setelah selesai. Seluruh field domain lama dari 5 tiket, 17 event dan 1 settings dibandingkan sebelum/sesudah dan identik. Backup serta laporan hash disimpan privat pada `storage/local/backups/` yang diabaikan Git.
- MySQL `pbl_concurrency`: registrasi campuran serentak menghasilkan nomor global unik; FIFO satu layanan, panggilan silang layanan, replay operation_id, penolakan operation_id silang, serta replay panggilan kosong lulus. Runner memeriksa nama database pada proses induk dan worker sebelum menulis.
- Browser menggunakan **aplikasi Laravel sebenarnya** dengan database SQLite khusus `*_testing`, session/login/CSRF dan POST asli, bukan halaman hasil render statis. Empat pelanggan mendaftar berselang-seling ke kedua layanan. Panggilan A-002 Bawa Pulang tidak mengubah status/posisi A-001/A-003 Makan di Tempat; kedua tab hanya menampilkan tiket layanannya. Kedatangan, selesai, skip staf dan pembatalan pelanggan dijalankan melalui antarmuka.
- Selector keyboard (Space/ArrowRight) dan layout pelanggan 390 px serta staf 320/1366 px diperiksa tanpa overflow horizontal; aset pada halaman pelanggan termuat. Pemeriksaan JavaScript dan diff whitespace lulus. Referensi staf diperoleh dari design context Figma; selector/halaman pelanggan diperiksa langsung melalui browser saat kuota MCP habis.

Nomor A global, settings bersama dan tahapan lama sengaja dipertahankan. Hasil ini tidak memvalidasi aturan operasional restoran, konvensi nomor M/B, ETA adaptif, kuota atau emergency controls, dan bukan uji beban produksi. Rollback MySQL hanya boleh dilakukan setelah seluruh penulisan dihentikan; pemeriksaan awal migration bukan penghalang terhadap penulis bersamaan selama DDL.

## Hasil awal

- PHPUnit: 15 tests, 77 assertions, lulus pada SQLite awal dan MySQL khusus pbl_testing.
- MySQL concurrency: 10 proses registrasi serentak menghasilkan nomor unik; dua replay satu operation memanggil satu tiket; dua operation berbeda memanggil FIFO 2 dan 3.
- Blade view compilation dan node --check public/app.js lulus.
- Browser: registration tanpa telepon, status tiket/near-turn, dialog cancellation dan mobile viewport diperiksa.

HTTP live: CSRF tanpa token ditolak (419); existing staff login, dashboard, call, arrive, complete, dan fragment polling lulus. Browser mobile: stepper, registrasi, hasil selesai, konfirmasi dan hasil cancellation diperiksa. Rate-limit login dipisah dari polling dan memiliki regression test. Tidak ada klaim audit keamanan lengkap, uji Linux, pengujian beban produksi, akurasi ETA, atau penelitian usability yang selesai.

## Traceability penilaian

| Rubrik | Bobot dalam matakuliah | Bukti dan pekerjaan |
| --- | --- | --- |
| SIP1 | 50% | Problem/observasi, batas data, kebutuhan turis/operator; lengkapi traceability sumber primer+sekunder |
| SIP2 | 50% | Ide digital, manfaat dan feasibility; lengkapi perbandingan solusi |
| IMK1 | 50% | Main-flow prototype; hubungkan prototype dan lakukan evaluasi usability |
| IMK2 | 50% | Token visual dan alur konsisten; lengkapi persona/rationale |
| DPPL1 | 30% | Scrum/backlog; use-case diagram dan deskripsi perlu dilengkapi |
| DPPL2 | 30% | Activity/sequence sesuai use-case perlu dilengkapi |
| DPPL3 | 40% | Class/ERD awal dalam design.md; review kelengkapan notasi/atribut/operasi |
| PWF1 | 60% | Migration/model dan foreign key berjalan; cocokkan diagram lengkap |
| PWF2 | 40% | Registration/status/dashboard berjalan dan mengikuti entity |

## Strategi

Uji state transition legal/ilegal, FIFO/skip/cancel, optional phone, XSS escaping, private token dan authorized access, deadline boundary, midnight, unknown ETA, retry kosong, lintas staf, dan race arrival/cancel. MySQL concurrency script memakai database disposable sendiri. Verifikasi keyboard/mobile/desktop, polling offline/reconnect, dan error states. Gunakan data buatan; lakukan black-box dan SUS dengan peserta yang benar-benar dilibatkan, lalu laporkan batas sampel.

Pemeriksaan keselamatan: DB_URL dipaksa kosong pada PHPUnit dan guard membaca nama database yang sudah di-resolve sebelum RefreshDatabase. Concurrency script menonaktifkan URL dan memverifikasi SELECT DATABASE(). Start script memakai --quiet agar token tiket tidak muncul pada output access log Artisan.

Startup dan shutdown lokal: scripts/start.ps1 berhasil memulai database tersendiri dan server; home/up 200, tiket sintetis tidak valid 404. Output --quiet tetap kosong setelah request, sehingga URL tiket tidak tercetak. scripts/stop-database.ps1 berhasil SHUTDOWN secara graceful. Browser mobile cancellation dan result berhasil; desktop geometry pada 1366 tidak overflow dan seluruh ikon termuat, tetapi screenshot viewport besar dibatasi renderer browser. Tampilan staf diverifikasi lewat HTTP/Blade tests, belum review visual penuh di browser.
