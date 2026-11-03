# PRD — Steizen Store Multi Game

Versi 3.0 · 2 November 2026  
Status: katalog, invoice, pembayaran demo sampai berhasil, serta integrasi Tripay sandbox tersedia; uang/item produksi belum aktif.

## Ringkasan

Steizen Store membantu pelanggan Indonesia memilih game, memasukkan akun sesuai game, memilih nominal, dan melihat pesanan. Target produksi adalah pembayaran serta top up otomatis melalui agregator/supplier, bukan koneksi langsung ke setiap penerbit game. Game, region, nominal, dan cara pengiriman mengikuti kontrak supplier.

## Tujuan

1. Menambah game melalui data dan skema input tanpa membuat checkout terpisah.
2. Menjaga harga, status katalog, dan keputusan transaksi di server.
3. Menyimpan pesanan secara permanen dengan akses yang dibatasi dan data sensitif terenkripsi.
4. Memisahkan status pembayaran dan pengiriman saat integrasi produksi ditambahkan.
5. Mencegah pengiriman ganda, pembayaran tidak sah, dan paparan data pelanggan.

## Arsitektur saat ini

- Laravel 13, PHP 8.4+, Eloquent, Blade, dan aset CSS/JavaScript lokal tanpa build npm.
- MVC dengan Form Requests untuk validasi; service terpisah untuk pemilik sesi, pembuatan pesanan, dan sinkronisasi supplier.
- Migration untuk users, sessions, cache/locks, jobs, games, products, orders, dan admin audit logs.
- SQLite untuk lingkungan lokal dan tests; MySQL dapat dikonfigurasi melalui `.env`. Database lama tidak diubah/diimpor.
- Controller/routes lama telah dipindahkan ke arsip privat. DocumentRoot aplikasi adalah `public`.

## Tampilan storefront

Identitas visual Steizen memakai biru–ungu dan komposisi banner utama dengan dua panel pendamping. Navigasi memuat katalog, pesanan, cara top up, dan panduan. Halaman depan menyediakan pilihan game yang dikurasi (bukan klaim penjualan terlaris), pencarian, filter kategori yang dapat digabungkan dengan pencarian, kartu game bergambar, dan FAQ. Checkout, login, serta admin mengikuti tema yang sama.

Foto dan logo berasal dari aset proyek; tujuh ilustrasi SVG dibuat khusus sebagai visual kategori game, bukan materi promosi resmi penerbit. Tidak ada aset atau kode situs referensi yang disalin. Tampilan tetap memuat status simulasi dan tidak menambahkan klaim kecepatan pengiriman, diskon, atau metode pembayaran yang belum tersedia.

## Peran

| Peran | Fitur |
| --- | --- |
| Pelanggan tamu | Katalog, pencarian, form akun, nominal, pesanan simulasi, riwayat pada sesi yang sama |
| Admin | Login, status game, harga/status produk demo, ringkasan pesanan, audit aktivitas |
| Operator server | Membuat admin lewat CLI, mengatur environment, sinkronisasi supplier, migration dan deployment |

Tidak ada registrasi admin publik atau password admin bawaan. Autentikasi pelanggan permanen dan pemulihan akses pesanan belum tersedia.

## Game awal

| Game | Data akun contoh | Mata uang contoh |
| --- | --- | --- |
| Mobile Legends | User ID, Zone ID | Diamonds |
| Free Fire | Player ID | Diamonds |
| PUBG Mobile | Character ID | UC |
| Genshin Impact | UID, Server | Genesis Crystals |
| Honor of Kings | Player ID | Tokens |
| Valorant | Riot ID dengan tag | Points |
| Honkai: Star Rail | UID, Server | Oneiric Shards |
| Call of Duty Mobile | Player ID | CP |

Delapan game dan 24 nominal seed adalah contoh. Setiap SKU supplier harus diverifikasi jenisnya (voucher/top up langsung), region, format akun, dan cara pengiriman. Tidak semua SKU atau game yang muncul pada supplier dapat otomatis diproses dengan format input contoh.

## Cakupan implementasi

| Fitur | Status |
| --- | --- |
| Katalog multi game, pencarian, tampilan responsif | Tersedia |
| Input per game, whitelist server, validasi backend | Tersedia |
| Harga dari server, produk aktif, kedaluwarsa katalog | Tersedia |
| Pesanan database dengan snapshot harga/nama | Tersedia untuk simulasi |
| Idempotency key unik, transaksi dan locking | Tersedia untuk pembuatan pesanan |
| Enkripsi data akun/WhatsApp dan sesi | Tersedia |
| Pembatasan akses pesanan per pemilik sesi | Tersedia |
| Login/hash password/role admin/rate limit/CSRF | Tersedia |
| Pengaturan produk/game dan audit log admin | Tersedia |
| Penambahan nominal demo melalui dashboard admin | Tersedia, validasi jumlah/harga, penolakan nominal ganda, dan audit atomik |
| Sinkronisasi katalog Digiflazz | Tersedia, tests menggunakan HTTP fake |
| Database schema dan seed lokal | Sudah dijalankan pada SQLite baru |
| Import transaksi/user dari database lama | Belum dikerjakan; perlu audit skema/password |
| Invoice unik, subtotal/biaya/total, expiry, cetak/simpan PDF browser | Tersedia |
| Pembayaran demo lokal sampai berhasil/gagal | Tersedia, tidak dapat dipakai untuk gateway atau pada environment produksi |
| Tripay sandbox, metode merchant, quote biaya, checkout dan pengecekan status | Implementasi tersedia; pengujian memakai HTTP fake |
| Callback pembayaran bertanda tangan, deduplikasi, validasi nominal/referensi | Tersedia untuk Tripay sandbox |
| Rekonsiliasi invoice setelah timeout/callback terlewat | Tersedia melalui command dan scheduler; tidak membuat pembayaran ulang |
| Pembayaran uang riil / endpoint gateway produksi | Belum aktif |
| Fulfillment supplier, polling, refund | Belum tersedia |
| Notifikasi WhatsApp | Belum tersedia |
| MFA admin dan akun pelanggan permanen | Belum tersedia |

## Alur versi ini

Katalog → pilih game → isi akun/nominal/WhatsApp → invoice unik → pilih metode → periksa biaya dan total → pembayaran demo/sandbox → halaman berhasil dan bukti yang dapat dicetak. Pesanan dan invoice disimpan sebelum pemanggilan gateway. Harga/status browser diabaikan. Request yang sama pada pemilik sesi yang sama mengembalikan pesanan serta invoice yang sama.

Mode demo menyediakan tombol simulator hanya untuk pemilik invoice dalam environment lokal/testing. Mode sandbox mengambil channel aktif dan fee dari Tripay, mengunci quote sebelum create, serta memverifikasi hasil create/detail dan callback. Tidak ada simulator klien untuk invoice gateway. Status `UNPAID`, `PAID`, `EXPIRED`, `FAILED`, dan `REFUND` disimpan pada pembayaran; status PAID tidak boleh mundur menjadi UNPAID/EXPIRED/FAILED. REFUND tidak boleh kembali menjadi PAID. Pengiriman DM tetap belum aktif dan tidak dianggap selesai saat pembayaran berhasil.

Timeout setelah create tidak memicu POST create kedua. Status UNKNOWN/CREATING diperiksa menggunakan invoice/reference yang sama. Scheduler memeriksa hingga 20 invoice dari dua hari terakhir setiap lima menit dalam mode sandbox; operator perlu memantau antrean dan hasil rekonsiliasi. Callback yang datang sebelum referensi create tersimpan mendapat respons retryable; rekonsiliasi mengambil kembali referensi dari gateway bila respons create hilang.

Detail/riwayat hanya dapat dibaca melalui identitas pemilik pada sesi browser. Pesanan tetap tersimpan setelah sesi berakhir, tetapi pelanggan belum memiliki mekanisme pemulihan akses. Admin saat ini hanya melihat ringkasan pesanan tanpa membuka data pribadi pelanggan.

## Katalog supplier

`php artisan catalog:sync` membaca daftar harga Digiflazz melalui HTTPS, memetakan `supplier_brand`, menghitung harga jual menggunakan margin environment, serta melakukan perubahan dalam satu transaksi database. Produk hilang, nonaktif, atau stok habis tidak ditawarkan setelah sinkronisasi berhasil. Respons tidak sah/bermasalah mempertahankan katalog lama.

`CATALOG_SOURCE=digiflazz` memilih katalog supplier; tidak ada fallback diam-diam ke demo. Data berumur lebih dari 24 jam tidak ditawarkan. Scheduler menjalankan sinkronisasi setiap enam jam bila proses scheduler server tersedia. Kredensial supplier berasal dari environment privat. Belum ada tes dengan akun supplier asli.

## Target transaksi produksi

1. Verifikasi format akun dan kebutuhan region pada setiap SKU yang akan dijual.
2. Tampilkan ringkasan final akun/game/produk, harga, biaya, metode bayar, dan kebijakan sebelum konfirmasi pelanggan.
3. Simpan order dan snapshot harga dengan idempotency key permanen.
4. Buat invoice gateway yang dikonfigurasi pada merchant; rekonsiliasi timeout berdasarkan referensi sebelum mengulangi pembuatan invoice.
5. Verifikasi signature, event, merchant/reference, jumlah, dan transisi status pembayaran. Redirect browser tidak boleh menandai order lunas.
6. Pembayaran sah membuat pekerjaan fulfillment dengan constraint unik per order, referensi supplier tetap, serta batas biaya beli.
7. Terjemahkan akun ke format tujuan berdasarkan SKU; jangan mengasumsikan penggabungan UID/server seragam.
8. Tangani sukses/gagal/pending dan timeout. Retry status memakai referensi yang sama; jangan membuat pembelian baru saat status belum jelas.
9. Catat status pembayaran dan pengiriman terpisah. Kegagalan setelah pembayaran masuk ke rekonsiliasi/manual review/refund.

`STORE_MODE` mendukung `demo` dan `sandbox`. Mode lain, termasuk `live`, ditolak HTTP 503. Invoice lama mempertahankan provider awal dan tidak berubah menjadi gateway/produksi ketika konfigurasi diganti.

## Data produksi lanjutan

Tabel `payments` kini menyimpan satu invoice per order, provider, quote/metode, referensi gateway unik, integer jumlah/biaya/total, expiry, waktu lunas, dan state pembuatan gateway. Email, URL checkout, serta kode bayar dienkripsi. `payment_events` mencatat kunci event unik, hash payload, sumber, serta status sebelum/sesudah tanpa raw data pribadi. Perubahan status dan event dilakukan atomik. Tahap fulfillment masih memerlukan tabel/queue pengiriman dengan referensi supplier unik, status, serial, serta biaya beli.

Autentikasi pelanggan/perolehan kembali akses perlu dibangun sebelum menjanjikan tracking jangka panjang. Tetapkan retensi data, akses operasional, backup, prosedur pemulihan, dan proses refund sebelum peluncuran.

## Keamanan dan operasional

Lihat [SECURITY.md](SECURITY.md). Implementasi meliputi CSRF, Eloquent, validasi atribut, enkripsi, cookie aman, pembatasan request, pemeriksaan admin/pemilik, output escaping, CSP tanpa inline/CDN, HTTPS/HSTS produksi, host allowlist, dan pemisahan source dari public.

Keamanan tetap bergantung pada konfigurasi TLS, izin filesystem, secret, backup, pembaruan paket, dan pengujian deployment. Aktifkan MFA admin atau pembatasan akses setara sebelum membuka operasional transaksi riil. Application key harus dicadangkan secara privat agar data terenkripsi tetap dapat dipulihkan.

## Kriteria penerimaan

- Delapan game dan form yang sesuai dapat dibuka; pencarian dan pengiriman form tidak bergantung pada JavaScript.
- SKU lintas game/nonaktif/kedaluwarsa dan akun/server/WhatsApp salah ditolak.
- Harga/status palsu dari browser tidak mengubah snapshot server.
- Request ulang tidak membuat pesanan kedua untuk request key yang sama.
- Data sensitif tidak berupa plaintext pada database atau serialisasi model.
- Sesi lain tidak dapat membaca detail atau daftar pesanan pemilik lain.
- CSRF tidak sah, login berulang, akses admin non-admin, dan perubahan atribut terlarang ditolak.
- Perubahan katalog admin dicatat bersama perubahan database; seeding ulang tidak menimpa perubahan.
- Sinkronisasi supplier palsu diuji untuk signature, margin, stok, penghapusan SKU, kegagalan, dan SKU ganda.
- Produksi menolak HTTP, host tidak sah, dan konfigurasi debug/cookie yang tidak aman.
- Sebelum produksi riil, wajib uji pembayaran dan fulfillment menyeluruh termasuk webhook berulang, race antar-worker, timeout, perubahan harga, refund, backup, serta pemulihan layanan.

## Tahap berikutnya

1. Tinjau deployment dan kebutuhan autentikasi/MFA pelanggan/admin.
2. Pilih merchant gateway serta akun supplier, uji SKU/region dan input akun.
3. Uji integrasi gateway dengan akun sandbox asli, lalu implementasikan fulfillment queue dan penanganan refund operasional sebelum mengaktifkan gateway produksi.
4. Audit keamanan dan uji database produksi dengan worker paralel; luncurkan SKU terbatas setelah penerimaan terpenuhi.

Panduan menjalankan aplikasi ada pada [README.md](README.md). Konektor katalog mengacu pada [dokumentasi daftar harga Digiflazz](https://developer.digiflazz.com/api/buyer/daftar-harga/). Rencana fulfillment mengacu pada [Buyer Topup](https://developer.digiflazz.com/api/buyer/topup/). Laravel memakai struktur publik sesuai [panduan deployment resmi](https://laravel.com/framework/docs/13.x/deployment).
