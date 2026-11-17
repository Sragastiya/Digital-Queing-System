# Spesifikasi dan catatan keputusan

## Dua antrean operasional — 17 November 2026

Permintaan pengguna pada tugas ini mengizinkan perubahan backend, schema, dan antarmuka untuk memisahkan Makan di Tempat (`dine_in`) dan Bawa Pulang (`takeaway`). Registrasi harus memilih layanan; jenis tiket tetap melekat selama siklusnya. FIFO, posisi, jumlah menunggu, panggilan terakhir, daftar/pelanggan aktif, pencarian, paginasi, dan tindakan staf dibatasi pada layanan yang dipilih. Tautan privat menentukan jenis tiket pelanggan; parameter URL tidak dapat memindahkannya ke antrean lain.

Pemisahan mempertahankan konvensi yang sudah berjalan: nomor global `A-…`, singleton settings/counter, interval ETA, batas hadir, serta Waiting → Called → Arrived → Completed. Data antrean meja lama dibaca sebagai Makan di Tempat tanpa mengubah nomor, hash akses, status, timestamp atau riwayat. Pada Bawa Pulang, antarmuka penyelesaian memakai teks netral layanan selesai oleh staf; tidak ditambahkan tahap penyiapan pesanan atau transisi langsung Called → Completed.

Figma V2 menjadi referensi visual selector pelanggan, identitas layanan pada tiket dan tab staf. Nomor M/B, batas hadir take-out baru, perhitungan ETA adaptif, kuota, kapasitas, perpindahan jenis, beri giliran, serta emergency controls tidak menjadi bagian perubahan ini. Perbedaan tersebut tidak dianggap sebagai persetujuan aturan usulan. Pengaturan interval lama masih berlaku untuk kedua antrean; estimasi memakai jumlah Waiting pada layanan tiket, bukan seluruh restoran.

Setiap event, termasuk panggilan kosong, menyimpan jenis antrean untuk menjaga retry tidak mengoperasikan layanan lain. Transaksi tetap memakai urutan kunci settings → tiket dan retry yang sudah ada. Schema baru bersifat tambahan; rollback ditolak jika data tiket atau event non-dine-in akan kehilangan klasifikasinya.

## Sumber dan prioritas

D = `reference/Draf_Presentasi_PBL_3B4.pptx` (revisi terbaru yang diganti nama), P = proposal 19 halaman, R = rubrik Semester 3, F = https://www.figma.com/design/GOJY0SghUXI4vSI9ONw0e5/ . Presentasi mengatur lingkup fungsional, proposal melengkapi detail tanpa konflik, Figma mengatur visual, rubrik mengatur bukti penilaian. AGENTS mengatur cara kerja.

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

- Workspace dan root Laravel: `D:\Git\PBL`.
- Nama/jumlah orang wajib; telepon opsional.
- Beberapa pelanggan boleh Called sekaligus.
- Completed berarti diarahkan ke meja, bukan selesai makan.
- Skip berarti dikeluarkan; kembali memerlukan tiket baru.
- ETA = jumlah Waiting di depan x interval konfigurasi. Tanpa interval, tampilkan belum tersedia.
- November 3 menargetkan paket desain Stage 1; implementasi tidak menggantikan bukti pemodelan.

## Default teknis/usulan yang belum divalidasi restoran

- Grace 15 menit sejak called_at. Snapshot deadline per tiket. Overdue tidak membatalkan otomatis.
- Staf boleh menerima pelanggan overdue selama tiket belum dibatalkan.
- Staf dapat membatalkan Arrived dengan alasan staff; skip tidak tersedia setelah hadir.
- Near-turn <=2 Waiting di depan. Polling 5 detik. Batas panjang nama 120, telepon 40; jumlah orang positif, maksimum penyimpanan 65535 bukan kebijakan restoran.
- Nomor naik terus tanpa reset tengah malam. UTC disimpan, WITA ditampilkan.
- Nomor terakhir dipanggil bukan satu-satunya pelanggan yang boleh datang.
- Telephone tidak digunakan untuk OTP, pemblokiran duplikasi, atau SMS. Tujuan pengumpulan dan kebutuhan retensinya tetap harus divalidasi.

## Konflik dan validasi

Proposal F2 menyebut nama+jumlah orang; prose menyebut nama+telepon. Figma menggabungkan keduanya. Pilihan pengguna menjadi baseline prototipe dan harus dibawa ke validasi stakeholder.

Tanyakan narasumber tentang grace 15 menit, pelanggan yang terlambat, beberapa panggilan serentak, tujuan telepon, batas ukuran rombongan, interval ETA, ambang near-turn, dan kebijakan data sebelum penggunaan operasional. Observasi awal dilakukan saat sepi; jangan mengklaim perilaku jam puncak atau akurasi estimasi sudah terbukti.

F9 bilingual, F10 buka/tutup antrean, F11 ringkasan per jam tetap backlog opsional. Tidak termasuk menu, pembayaran, reservasi jauh hari, integrasi kasir, banyak restoran, pemantauan meja otomatis, atau analitik lanjutan.

## Tahap berikutnya

Lengkapi diagram use-case/aktivitas/sequence dan prototype klik untuk seluruh alur utama, validasi bersama narasumber, kemudian evaluasi fungsional/usability/SUS. Jangan menyebut seluruh deliverable Stage 1 atau validasi selesai hanya karena aplikasi dapat berjalan.
