# Verifikasi dan pekerjaan lanjutan

## Pemberitahuan pembatalan dan panggilan otomatis — 18 November 2026

- **69 tests, 744 assertions** lulus pada SQLite `:memory:` dan MySQL `pbl_testing`. Kasus baru mencakup pengaturan awal nonaktif, validasi dan autentikasi perubahan pengaturan, notifikasi lintas layanan/filter, pengecualian pembatalan Waiting/staf/transfer, urutan setelah beri giliran, batas hadir baru, serta antrean kosong dan retry. Run SQLite pertama terkena konflik penulisan cache Blade pada Windows saat suite berjalan bersamaan. Run dengan cache Blade tersendiri lulus.
- MySQL concurrency pada `pbl_concurrency` lulus. Dua retry pembatalan Called dan satu panggilan manual serentak menghasilkan tepat satu panggilan otomatis dan satu panggilan manual untuk tiket berbeda dalam layanan yang sama. Layanan lain tidak berubah.
- Browser dengan database SQLite sementara dan data sintetis memverifikasi login staf, pemberitahuan lintas tab, pilihan awal Nonaktif, pengaktifan Bawa Pulang, pemberitahuan polling saat filter sedang diedit, serta pemberitahuan dan tiket pengganti setelah muat ulang. Panel tidak menambah overflow horizontal pada tampilan desktop maupun 390 piksel. Pembatalan browser dipicu melalui QueueService di fixture dan endpoint pelanggan diuji terpisah oleh PHPUnit.
- Pemberitahuan memakai event yang sudah ada. Migration pengaturan baru sudah diuji pada database terisolasi, tetapi masih menunggu aktivasi pada database lokal operasional. Sebelum migration pengaturan diterapkan, pilihan panggilan otomatis tidak tampil dan panggilan otomatis tetap nonaktif.

## Pilihan antrean pelanggan — 18 November 2026

- Cakupan pengguna: S-PROP-REV §6.2.5/F12, dengan jawaban eksplisit Waiting-only untuk pindah/beri giliran dan konfirmasi baru boleh mengulangi beri giliran. Keputusan dicatat pada D04 dan spesifikasi; aturan lain dalam proposal tetap di luar tugas ini.
- Pemeriksaan baru: **64 tests, 681 assertions**, seluruhnya lulus pada SQLite `:memory:` dan MySQL `pbl_testing`. Environment proses memilih koneksi dan mengosongkan DB_URL; guard memeriksa database yang sudah di-resolve sebelum RefreshDatabase. Tidak ada uji yang diarahkan ke `pbl`.
- Kasus baru menguji kedua arah perpindahan, nomor global baru, akhir antrean tujuan, hash/tautan privat, riwayat asal/pengganti, input berubah pada replay, pemulihan tautan dari tiket lama, alasan pembatalan, Waiting-only termasuk formulir stale, tepat/kurang dari tiga pengikut, sudah terakhir, pengulangan, FIFO/ETA/daftar staf setelah pengurutan, serta rollback transaksi saat constraint tujuan gagal. Alur login, registrasi, panggil, hadir, selesai dan pembatalan staf yang sudah ada tetap lulus.
- Enam kasus migration terisolasi memakai koneksi SQLite `:memory:` tersendiri dengan data campuran dua layanan/status: seluruh field domain/event/settings lama dipertahankan, urutan diisi dari nomor, rollback belum terpakai aman, dan rollback ditolak sebelum DDL setelah beri giliran/transfer/perubahan slot. Fixture migration historis diperbarui agar tidak memanggil kode aplikasi baru pada schema lama.
- Runner MySQL `pbl_concurrency` lulus: registrasi/call/replay dua layanan yang sudah ada; transfer identik serentak; retry beri giliran bersamaan dengan call/registrasi; transfer versus cancel/call; dua operation transfer berbeda untuk sumber sama; transfer dua arah bersamaan; keunikan dan rentang slot urutan. Runner tetap memeriksa database disposable pada induk dan worker sebelum `migrate:fresh`; rahasia tiket transfer sintetis diteruskan lewat stdin, bukan argumen proses.
- `node --check public/app.js` dan `git diff --check` lulus. Review independen menemukan risiko respons polling masuk ketika dialog pilihan sudah terbuka; guard sebelum/sesudah fetch sekarang menunda pembaruan saat dialog pilihan/konfirmasi terbuka. Teks alasan baru juga ditampilkan pada rincian staf. Pemeriksaan ini bukan bukti interaksi browser.
- Metadata live Figma V2 mengidentifikasi dialog `39:301`/`39:432` dan layar Waiting/Called/Cancelled kedua layanan; tidak ditemukan layout lengkap transfer/beri giliran/alasan. Pembacaan berikutnya mencapai batas paket. Integrated browser gagal memulai, termasuk setelah reset; alat Windows browser juga gagal dengan `failed to write kernel assets`. Tidak ada canvas Figma yang diedit. Pemeriksaan visual desktop/mobile, keyboard/focus/Escape dan prototype links **belum dilakukan**. Handoff F12 tambahan disimpan di direktori desain akademik melalui INDEX; ini bukan pengganti update live.
- Automatic approval review awalnya menolak migration/maintenance operasional sebelum eksekusi karena belum ada izin rollout eksplisit. Pengguna kemudian memilih **Approve local database rollout** pada 18 November 2026. Pemeriksaan ulang memastikan MySQL lokal `pbl` pada port 3311, kedua kolom/migration belum ada, 9 tiket/24 event/1 settings, maintenance tidak aktif dan tanpa koneksi database lain. Setelah jeda request sementara, backup SQL baru disimpan dan checksum SHA-256 diverifikasi; hanya migration `2026_11_18_000001_add_customer_queue_options` diterapkan. Perbandingan sebelum/sesudah membuktikan seluruh field domain lama dari 9 tiket, 24 event dan 1 settings identik; `queue_order` sama dengan nomor awal dan relasi transfer lama null. Backup/laporan tersimpan privat pada `storage/local/backups/` yang diabaikan Git. Review helper juga memperbaiki jalur gagal: maintenance tidak dibuka kembali jika DDL sudah dimulai tetapi schema/preservasi belum terverifikasi, serta kegagalan dump tidak mencetak SQL pelanggan.
- Pemeriksaan baru setelah rollout: migration tercatat, kedua kolom tersedia, backfill sesuai dan maintenance kembali nonaktif. HTTP aplikasi yang sudah berjalan: home/login/CSS/JS **200**, dasbor tanpa login **302**, tautan tiket sintetis tidak valid **404**. Tidak ada tiket/event operasional yang ditambah untuk pengujian; hasil suite 64/681 dan concurrency sebelumnya dipakai kembali karena kode aplikasi tidak berubah. Service yang sudah berjalan tidak dihentikan atau dimulai ulang. Browser Figma dicoba kembali setelah pengguna membukanya, tetapi masih gagal memulai dengan error kernel assets yang sama; batas visual/prototype di atas tetap berlaku.

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
