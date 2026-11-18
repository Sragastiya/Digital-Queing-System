# Spesifikasi dan catatan keputusan

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
| Pembatalan pelanggan dengan konfirmasi | D4,6,10; F | Dialog sebelum POST; Waiting/Called saja |
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
