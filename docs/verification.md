# Verifikasi dan pekerjaan lanjutan

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
