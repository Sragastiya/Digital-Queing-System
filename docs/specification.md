# Spesifikasi dan catatan keputusan

## Pemberitahuan pembatalan dan panggilan otomatis — 18 November 2026

Pengguna menyetujui D05. Dasbor menampilkan lima pembatalan terbaru yang dilakukan pelanggan setelah dipanggil, dengan nomor tiket, jenis layanan dan waktu. Kedua layanan tampil pada panel yang sama, terpisah dari filter dan paginasi daftar. Pemberitahuan berasal dari riwayat dan tetap terlihat setelah muat ulang. Jika ada panggilan pengganti otomatis, nomor penggantinya ikut ditampilkan.

Staf dapat mengaktifkan panggilan otomatis secara terpisah per layanan. Pengaturan awal nonaktif. Jika aktif, pembatalan pelanggan dari status Called memanggil satu Waiting berikutnya dalam urutan layanan yang sama, dengan batas hadir baru dari pengaturan yang sudah berlaku. Pembatalan dan panggilan dicatat dalam satu transaksi. Antrean kosong saat pembatalan tidak menjadwalkan panggilan kemudian; retry pembatalan yang sama tidak memanggil tiket tambahan. Pembatalan Waiting, pembatalan oleh staf, dan perpindahan layanan tidak memicu panggilan otomatis. Panggilan manual tetap tersedia sebagai tindakan staf tersendiri.

## Pilihan antrean pelanggan — 18 November 2026

Permintaan pengguna dalam percakapan ini secara eksplisit mengizinkan backend, UI pelanggan dan pembaruan Figma V2 untuk pilihan pada S-PROP-REV §6.2.5/F12. Pengguna mengonfirmasi dua rincian yang belum dijelaskan proposal: pindah layanan/beri giliran **hanya saat Waiting**, dan beri giliran dapat diulang dengan konfirmasi baru. Ini menggantikan penundaan kedua fitur pada rekaman 17 November di bawah, tanpa menerima seluruh aturan proposal atau menyatakan validasi restoran selesai.

- **Tetap di antrean:** tutup pilihan tanpa penulisan database.
- **Pindah layanan:** tiket asal menjadi Cancelled dengan alasan `service_switch`; satu tiket Waiting baru dibuat pada akhir antrean tujuan, dengan nomor A global baru dan data pelanggan yang sama. Nomor, jenis, hash akses dan riwayat asli tetap tersimpan. Pelanggan diarahkan ke tautan privat baru; halaman tiket lama juga menyediakan akses ke tiket pengganti.
- **Beri giliran:** pindah ke belakang hingga tiga tiket Waiting berikutnya pada layanan yang sama. Jika kurang dari tiga, pindah ke akhir; jika tidak ada, tampilkan penjelasan tanpa mengubah posisi. Nomor dan tautan tiket tetap. Setiap konfirmasi berbeda boleh mengulangi tindakan; retry operation_id yang sama tidak memindahkan lagi.
- **Batalkan antrean:** tetap tersedia pada Waiting/Called, wajib memilih Terlalu lama (`too_long`), Sudah dapat tempat lain (`found_elsewhere`), Rencana berubah (`plans_changed`), atau Lainnya (`other`). Tidak ditambahkan isian bebas. Alasan `customer` lama dan alasan staf tetap terbaca tanpa ditulis ulang.

Nomor tampilan bukan lagi kunci pengurutan setelah beri giliran: `queue_order` menyimpan posisi relatif. Panggilan berikutnya, daftar staf, posisi dan ETA menggunakan urutan yang sama, tetap dibatasi layanan. Tiket baru masuk di akhir; Called/Arrived tidak ikut dipindah. Lifecycle, nomor global, grace/deadline, ETA interval, beberapa Called sekaligus dan tindakan staf tetap berlaku. Transfer memang mengurangi satu Waiting pada asal dan menambah satu Waiting pada tujuan; panggilan biasa tetap independen.

Perpindahan, counter dan event dilakukan atomik dengan mutex settings → tiket. Event transfer menghubungkan tiket asal/pengganti; token pengganti diturunkan dengan HMAC dari rahasia asal, operation_id dan application key agar retry mendapat tautan yang sama, tanpa menyimpan token plaintext. Pertahankan application key; rotasi key memerlukan strategi terpisah untuk pemulihan tautan dari tiket lama. Migration tambahan mengisi `queue_order = number` pada data lama; rollback menolak jika pengurutan/transfer sudah digunakan.

Kode dan pengujian terisolasi tersedia; migration operasional diaktifkan setelah persetujuan rollout eksplisit pengguna pada 18 November 2026, dengan backup SQL terverifikasi dan seluruh field domain lama dipertahankan. Metadata live V2 membuktikan dialog lama `39:301`/`39:432`, tetapi belum memuat seluruh pilihan F12. Connector mencapai batas kuota dan kedua alat browser gagal memulai; pembaruan canvas, pemeriksaan visual desktop/mobile dan interaksi keyboard belum dapat diselesaikan. UI aplikasi memperluas komponen/style yang sudah ada dan menyediakan halaman pilihan tanpa JavaScript. Batas verifikasi rinci ada pada `docs/verification.md`.

## Dua antrean operasional — 17 November 2026

Permintaan pengguna pada tugas ini mengizinkan perubahan backend, schema, dan antarmuka untuk memisahkan Makan di Tempat (`dine_in`) dan Bawa Pulang (`takeaway`). Registrasi harus memilih layanan; jenis tiket tetap melekat selama siklusnya. FIFO, posisi, jumlah menunggu, panggilan terakhir, daftar/pelanggan aktif, pencarian, paginasi, dan tindakan staf dibatasi pada layanan yang dipilih. Tautan privat menentukan jenis tiket pelanggan; parameter URL tidak dapat memindahkannya ke antrean lain.

Pemisahan mempertahankan konvensi yang sudah berjalan: nomor global `A-…`, singleton settings/counter, interval ETA, batas hadir, serta Waiting → Called → Arrived → Completed. Data antrean meja lama dibaca sebagai Makan di Tempat tanpa mengubah nomor, hash akses, status, timestamp atau riwayat. Pada Bawa Pulang, antarmuka penyelesaian memakai teks netral layanan selesai oleh staf; tidak ditambahkan tahap penyiapan pesanan atau transisi langsung Called → Completed.

Figma V2 menjadi referensi visual selector pelanggan, identitas layanan pada tiket dan tab staf. Nomor M/B, batas hadir take-out baru, perhitungan ETA adaptif, kuota, kapasitas, perpindahan jenis, beri giliran, serta emergency controls tidak menjadi bagian perubahan ini. Perbedaan tersebut tidak dianggap sebagai persetujuan aturan usulan. Pengaturan interval lama masih berlaku untuk kedua antrean; estimasi memakai jumlah Waiting pada layanan tiket, bukan seluruh restoran.

Setiap event, termasuk panggilan kosong, menyimpan jenis antrean untuk menjaga retry tidak mengoperasikan layanan lain. Transaksi tetap memakai urutan kunci settings → tiket dan retry yang sudah ada. Schema baru bersifat tambahan; rollback ditolak jika data tiket atau event non-dine-in akan kehilangan klasifikasinya.

## Sumber dan prioritas

Panduan kanonis berada di `D:\ChatGPT\Project Based Learning\AGENTS.md`; lokasi sumber mengikuti `INDEX.md` di direktori tersebut. Untuk lingkup yang berlaku, urutan kewenangan ialah instruksi pengguna/keputusan yang disetujui dengan provenance, perubahan penguji yang penerimaannya terbukti, revisi proposal/laporan yang secara eksplisit diterima atau ditetapkan, lalu referensi historis yang kompatibel. Rincian keputusan dan pertanyaan terbuka mengikuti `DECISIONS.md`. Tidak ada presentasi yang saat ini ditetapkan sebagai sumber otoritatif.

INDEX mencatat S-PROP-REV (proposal revisi 29 halaman) sebagai sumber yang tersedia; penerimaan aturan spesifik dan cakupan penggantiannya belum ditetapkan. Keberadaannya tidak menyetujui seluruh usulan. Rubrik mengatur bukti penilaian; Figma menjadi referensi visual, bukan kewenangan aturan bisnis. Implementasi diverifikasi dari kode/schema dan pemeriksaan yang relevan.

Pada tabel implementasi awal di bawah, D = presentasi historis `A\Draf_Presentasi_PBL_3B4.pptx`, P = proposal historis 19 halaman (`S-PROP`), R = rubrik Semester 3 (`S-RUBRIC`), F = referensi Figma dalam INDEX. A menunjuk direktori akademik kanonis di atas. Sitasi D/P dipertahankan sebagai provenance prototipe awal, bukan urutan kewenangan saat ini; jangan memulihkan direktori `reference/` yang sudah dipensiunkan.

| Kebutuhan | Sumber | Implementasi awal |
| --- | --- | --- |
| QR/tautan browser, ambil nomor | D3,7; P F1-F2 | Halaman `/`; URL dapat dijadikan tujuan QR |
| Posisi/status/perkiraan | D3,10; P F3,F8 | Tautan privat, polling 5 detik, interval dapat diatur |
| Giliran hampir tiba | P F4, tidak dihapus oleh D | Indikator ketika <=2 antrean menunggu di depan; threshold usulan |
| Pilihan/pembatalan pelanggan | D4,6,10; F; S-PROP-REV §6.2.5/F12 dan persetujuan 18 November | Tetap, pindah layanan, beri giliran, batal dengan alasan; aturan status mengikuti rekaman 18 November |
| Login dan daftar staf | D6,11; P16 | Session login, pencarian/filter/paginasi |
| Panggil/hadir/selesai | D3,7,11; P F5-F6 | FIFO; beberapa Called; hadir oleh staf; selesai saat diarahkan ke meja |
| Lewati/tidak hadir | D7,11; P F7 | Cancelled dengan alasan; tidak ada pemulihan posisi |
| Tambah oleh staf | P3; F; tidak dihapus oleh D | Form menggunakan validasi dan urutan yang sama |
| Riwayat dan waktu | D9; P16 | Event dan timestamp dalam transaksi yang sama |

## Keputusan pengguna

- Nama/jumlah orang wajib; telepon opsional.
- Beberapa pelanggan boleh Called sekaligus.
- Completed berarti diarahkan ke meja, bukan selesai makan.
- Skip berarti dikeluarkan; kembali memerlukan tiket baru.
- ETA = jumlah Waiting di depan x interval konfigurasi. Tanpa interval, tampilkan belum tersedia.
- November 3 menargetkan paket desain Stage 1; implementasi tidak menggantikan bukti pemodelan.

Catatan cakupan historis: keputusan Completed sebagai diarahkan ke meja berasal dari prototipe antrean meja. Rekaman implementasi 17 November di atas mempertahankannya untuk Makan di Tempat dan memakai label layanan selesai oleh staf untuk Bawa Pulang, tetap melalui Arrived. Ini tidak menetapkan kapan pesanan Bawa Pulang dianggap diserahkan/diambil; detail operasional tersebut tetap terbuka pada U04. Keputusan awal dan riwayat persetujuannya tidak diganti oleh kecocokan dengan kode.

## Default teknis/usulan yang belum divalidasi restoran

- Grace 15 menit sejak called_at. Snapshot deadline per tiket. Overdue tidak membatalkan otomatis.
- Staf boleh menerima pelanggan overdue selama tiket belum dibatalkan.
- Staf dapat membatalkan Arrived dengan alasan staff; skip tidak tersedia setelah hadir.
- Near-turn <=2 Waiting di depan. Polling 5 detik. Batas panjang nama 120, telepon 40; jumlah orang positif, maksimum penyimpanan 65535 bukan kebijakan restoran.
- Nomor naik terus tanpa reset tengah malam. UTC disimpan, WITA ditampilkan.
- Nomor terakhir dipanggil bukan satu-satunya pelanggan yang boleh datang.
- Telephone tidak digunakan untuk OTP, pemblokiran duplikasi, atau SMS. Tujuan pengumpulan dan kebutuhan retensinya tetap harus divalidasi.

## Konflik dan validasi

Proposal historis S-PROP F2 menyebut nama+jumlah orang; prose menyebut nama+telepon. Figma menggabungkan keduanya. Pilihan pengguna menjadi baseline prototipe dan harus dibawa ke validasi stakeholder.

Tanyakan narasumber tentang grace 15 menit, pelanggan yang terlambat, beberapa panggilan serentak, tujuan telepon, batas ukuran rombongan, interval ETA, ambang near-turn, dan kebijakan data sebelum penggunaan operasional. Observasi awal dilakukan saat sepi; jangan mengklaim perilaku jam puncak atau akurasi estimasi sudah terbukti.

F9 bilingual, F10 buka/tutup antrean, F11 ringkasan per jam dari proposal historis tetap backlog opsional pada prototipe. S-PROP-REV mengusulkan kuota/status layanan dan perubahan prioritas F10/F13, tetapi konflik tersebut belum ditetapkan sebagai perubahan keputusan; D02–D03/U05 tetap berlaku untuk emergency controls. Tidak termasuk menu, pembayaran, reservasi jauh hari, integrasi kasir, banyak restoran, pemantauan meja otomatis, atau analitik lanjutan.

## Tahap berikutnya

Lengkapi diagram use-case/aktivitas/sequence dan prototype klik untuk seluruh alur utama, validasi bersama narasumber, kemudian evaluasi fungsional/usability/SUS. Jangan menyebut seluruh deliverable Stage 1 atau validasi selesai hanya karena aplikasi dapat berjalan.
