# Steizen Store — Laravel

Website top up multi game berbasis **Laravel 13 / PHP 8.4+**, Eloquent, Blade, dan database. Katalog delapan game, pencarian, formulir per game, pesanan permanen, dashboard admin, serta konektor katalog Digiflazz tersedia.

Logo asli `public/foto/liby.png` dipakai pada header/footer. Gambar `public/foto/diamond1.jpg` dipakai pada banner dan pilihan nominal game bermata uang Diamonds. Penempatan gambar diatur melalui Blade dan `public/store.css`; file asli tetap dipertahankan.

Tampilan menggunakan tema biru–ungu Steizen dengan banner utama, panel pesanan, pilihan game, filter kategori, dan FAQ. Kategori dapat digabungkan dengan pencarian melalui query `category` dan `q`. Foto Mobile Legends berasal dari aset proyek; ilustrasi SVG game lain dibuat khusus di `public/artwork` dan dipetakan pada `config/store.php`. Referensi susunan: [Ourastore](https://www.ourastore.com/id-id); kode, gambar, dan teks situs tersebut tidak disalin.

**Invoice dan alur pembayaran uji sudah tersedia**, sampai status berhasil dan bukti yang dapat dicetak/disimpan sebagai PDF melalui browser. Mode demo memakai simulator lokal; mode sandbox terhubung ke API Tripay sandbox. Pembayaran uang riil dan pengiriman DM belum aktif. Detail cakupan: [prd.md](prd.md). Catatan keamanan: [SECURITY.md](SECURITY.md).

## Menjalankan proyek yang sudah disiapkan

Dari PowerShell di direktori proyek:

```powershell
./start-local.bat
```

Buka **http://localhost:8000**. Script memakai PHP 8.4 portable dalam `.tools/php` bila tersedia; instalasi PHP Laragon global tidak diubah. `.env`, application key lokal, database SQLite, migration, dan seed demo sudah disiapkan pada lingkungan pengerjaan ini.

Runtime portable adalah PHP CLI NTS untuk pengembangan, bukan modul PHP Apache. Laragon lama yang masih memakai PHP 8.1 belum dapat menjalankan aplikasi ini melalui Apache. Untuk memakai domain Laragon, pasang/pilih PHP 8.4+ yang sesuai Apache Anda, arahkan virtual host ke `C:/laragon/www/steizen2/public`, lalu sesuaikan `APP_URL` dan restart layanan. Jangan memakai direktori proyek sebagai DocumentRoot.

## Instalasi dari checkout baru

Prasyarat: PHP 8.4+ dan Composer. Ekstensi: ctype, curl, dom, fileinfo, filter, intl, mbstring, openssl, pdo, pdo_sqlite (SQLite) atau pdo_mysql (MySQL), session, tokenizer, xml, xmlwriter, dan zip. Tidak perlu npm/build frontend; CSS dan JavaScript lokal dilayani dari `public`.

```powershell
composer install
Copy-Item .env.example .env
```

Untuk lokal HTTP, edit `.env`: `APP_ENV=local`, `APP_URL=http://localhost:8000`, `SESSION_SECURE_COOKIE=false`. Pertahankan `APP_DEBUG=false` dan `SESSION_ENCRYPT=true`. `.env.example` memakai pengaturan aman untuk produksi HTTPS.

```powershell
php artisan key:generate
New-Item -ItemType File database/database.sqlite
php artisan migrate --seed
php artisan serve --host=localhost --port=8000
```

Jalankan pembuatan file SQLite **hanya jika belum ada**. Jangan menimpa database yang berisi data. Seeder dapat dijalankan ulang dan tidak menimpa harga/status record lama.

Untuk runtime portable pada proyek ini, ganti `php` dengan `./.tools/php/php.exe`, dan `composer` dengan `./.tools/php/php.exe .tools/composer.phar`.

## Membuat akun admin

```powershell
./.tools/php/php.exe artisan admin:create
```

Isi nama, email, dan kata sandi pada prompt tersembunyi. Tidak ada akun/kata sandi admin bawaan dan tidak ada registrasi publik. Kata sandi minimal 14 karakter dengan huruf besar/kecil, angka, serta simbol; maksimum 72 byte untuk bcrypt. Buka `/login` setelah akun dibuat.

Admin dapat mengaktifkan/nonaktifkan game, mengubah harga/status produk demo, melihat ringkasan pesanan, dan meninjau aktivitas admin. Perubahan produk/game dicatat dalam transaksi database yang sama dengan audit log. Harga supplier dihitung saat sinkronisasi; perubahan harga dari form admin diabaikan untuk produk supplier.

Untuk menambah nominal, login → `/admin` → **Tambah nominal** → pilih game → isi jumlah (misalnya `86` untuk `86 Diamonds`), harga jual rupiah tanpa pemisah, dan status → **Tambah nominal**. Penambahan ini khusus admin, menghasilkan produk demo, menolak nominal ganda pada game yang sama, dan mencatat audit. Nominal aktif tampil jika game aktif serta `CATALOG_SOURCE=demo`. Produk riil Digiflazz tetap ditambahkan melalui sinkronisasi SKU supplier.

## Pesanan dan data

### Checkout sampai pembayaran berhasil (lokal)

1. Pilih game dan nominal, isi akun/WhatsApp, lalu klik **Buat invoice uji**.
2. Halaman invoice menampilkan nomor unik, data penerima, rincian produk, subtotal, dan batas pembayaran 60 menit.
3. Pilih contoh metode QRIS, Virtual Account, atau E-Wallet, lalu klik **Pilih metode & lihat total**.
4. Klik **Simulasikan pembayaran berhasil** untuk mencoba status lunas, atau **Simulasikan gagal** untuk menguji kegagalan. Tombol ini hanya tersedia pada mode demo di environment lokal/testing, dan tidak menjalankan transaksi uang.
5. Setelah berhasil, halaman menampilkan bukti pembayaran uji dan waktu lunas. Klik **Cetak / Simpan PDF** untuk membuka dialog cetak browser.

Nomor invoice dan data pembayaran disimpan di database. Satu pesanan memiliki satu invoice. Status pembayaran terpisah dari pengiriman: pembayaran uji berhasil **tidak** berarti DM telah masuk. Riwayat/admin menampilkan status invoice. Pembayaran kedaluwarsa tidak dapat diselesaikan melalui simulator.

### Pengujian Tripay sandbox

Migration tambahan sudah diterapkan pada lingkungan pengerjaan. Pada instalasi lain, jalankan `php artisan migrate` setelah meninjau target database. Jangan menjalankan `migrate:fresh` pada database berisi pesanan.

Siapkan akun/merchant **sandbox**, kredensial sandbox, dan URL callback publik HTTPS. Atur environment privat:

```dotenv
STORE_MODE=sandbox
TRIPAY_API_KEY=
TRIPAY_PRIVATE_KEY=
TRIPAY_MERCHANT_CODE=
TRIPAY_CALLBACK_URL=https://domain-uji.example/api/payments/tripay/callback
```

Isi secret hanya pada `.env` privat/server secret manager, bukan di file contoh atau chat. Jalankan `php artisan config:clear` untuk pengembangan, atau bangun ulang cache konfigurasi pada deployment. Cocokkan callback merchant dengan `/api/payments/tripay/callback`. Jangan memakai API key produksi; adapter saat ini hanya mengakses `https://tripay.co.id/api-sandbox/`.

Buat pesanan baru setelah beralih mode. Invoice demo lama tetap demo. Pilih metode merchant yang aktif, masukkan email, lihat biaya aktual dari fee calculator, lalu konfirmasikan invoice. Email dan WhatsApp digunakan gateway untuk invoice uji. Halaman invoice menyediakan tautan checkout resmi Tripay sandbox; QR/kode bayar riil tidak dibuat oleh simulator lokal. Uji status melalui simulator/dashboard sandbox Tripay sesuai dokumentasi merchant Anda.

Kembali dari gateway tidak menandai invoice lunas. Server memverifikasi callback HMAC dari raw body, event, invoice, referensi gateway, metode, nominal, biaya, dan closed-payment. Pelanggan dapat menggunakan **Cek status gateway** untuk mengambil detail dari server Tripay. Polling halaman membaca database setiap 15 detik, bukan menghubungi gateway terus-menerus.

Jika create timeout, invoice masuk status teknis UNKNOWN dan tidak dibuat ulang otomatis. Pemeriksaan berikutnya mencari transaksi menggunakan nomor invoice yang sama. Aktifkan scheduler untuk rekonsiliasi berkala:

```powershell
php artisan schedule:work
# Atau periksa manual:
php artisan payments:reconcile --limit=20
```

Pada server, jalankan `schedule:run` setiap menit. Rekonsiliasi dijadwalkan setiap lima menit, maksimal 20 invoice per proses, untuk invoice tertunda/gagal/kedaluwarsa dari dua hari terakhir. Tinjau hasil dan sesuaikan operasional jika antrean bertambah. Perintah hanya mengecek status, tidak membuat pembayaran baru. Pengujian kode gateway menggunakan HTTP fake; belum ada pengujian menggunakan akun Tripay asli pada lingkungan pengerjaan.

Dokumentasi primer integrasi: [Tripay API Developer Guide](https://tripay.co.id/developer).

Pesanan tersimpan dalam database, dengan snapshot nama dan harga. Identitas akun serta WhatsApp dienkripsi dengan application key. Akses pelanggan ke detail/riwayat pesanan terikat pada sesi browser pembuatnya. Setelah sesi kedaluwarsa, pelanggan belum memiliki fitur pemulihan akses; jangan menjanjikan pelacakan permanen melalui referensi saja.

Database lama `steizen_db` **tidak diubah atau diimpor**. Sumber PHP lama disimpan di `storage/app/private/legacy-20261102` dan dikecualikan dari Git. Arsip itu bukan aplikasi aktif. Database SQLite lokal juga dikecualikan dari Git.

Untuk MySQL gunakan database baru, misalnya `steizen_laravel`, dengan user khusus dan hak minimum. Atur `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`, kemudian jalankan migration pada database baru. Impor data lama memerlukan pemetaan skema dan audit kata sandi terlebih dahulu.

## Katalog Digiflazz

Atur `DIGIFLAZZ_USERNAME`, `DIGIFLAZZ_API_KEY`, dan `SELLING_MARGIN_PERCENT` di environment server atau `.env` privat. Jangan menaruh secret di repository, browser, atau chat.

```powershell
php artisan catalog:sync
```

Perintah ini hanya membaca daftar harga, memetakan `supplier_brand` game, menghitung harga jual, dan menyimpan hasil dalam database. Kegagalan respons tidak mengganti katalog lama. Produk hilang/nonaktif/stok habis dinonaktifkan setelah sinkronisasi berhasil.

Ubah `CATALOG_SOURCE=digiflazz` untuk memakai katalog supplier, lalu jalankan `php artisan config:clear` untuk lokal atau bangun ulang config cache pada deployment. Produk supplier yang lebih tua dari 24 jam tidak ditawarkan; tidak ada fallback otomatis ke demo. Checkout tetap simulasi.

Scheduler Laravel mengatur sinkronisasi setiap enam jam ketika sumber katalog adalah Digiflazz. Jalankan `php artisan schedule:work` saat pengembangan, atau `schedule:run` setiap menit melalui scheduler server. Pastikan frekuensi sesuai batas akun API supplier Anda.

Untuk game baru, tambahkan metadata/input ke `config/games.php`, jalankan seed untuk menambahkan record baru, dan cocokkan `supplier_brand` dengan respons supplier. Mengubah konfigurasi game yang sudah ada memerlukan migration/update terkontrol karena seed tidak menimpa record admin. Region, format akun, dan jenis SKU (voucher/top up langsung) wajib diverifikasi sebelum integrasi pengiriman.

## Pengujian

```powershell
./.tools/php/php.exe artisan test --compact
./.tools/php/php.exe artisan view:cache
./.tools/php/php.exe .tools/composer.phar audit
./.tools/php/php.exe .tools/composer.phar validate --strict --no-check-publish
```

Tests menggunakan SQLite **in-memory**, bukan database lokal/produksi. Pengujian mencakup manipulasi harga, SKU lintas game, validasi akun, enkripsi, kepemilikan pesanan, idempotensi, CSRF, pembatasan request, login/hak admin, audit log, HTTPS/host produksi, serta respons supplier palsu. Request supplier pada tests menggunakan fake; belum ada pengujian API berbayar/asli.

`tests/http_smoke.ps1` menguji server lokal yang sudah berjalan. Pengujian itu membuat satu pesanan demo di database lokal dan tidak menghubungi supplier. Jika eksekusi script PowerShell dinonaktifkan, gunakan:

```powershell
& ([scriptblock]::Create((Get-Content tests/http_smoke.ps1 -Raw)))
```

## Deployment

Gunakan PHP 8.4+, HTTPS, DocumentRoot `public`, dan environment produksi sesuai `.env.example`. Simpan application key dengan aman: kehilangan key berarti data terenkripsi tidak dapat dibaca. Jangan menjalankan `key:generate` ulang pada database berisi pesanan.

```text
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jalankan migration setelah backup dan peninjauan target database. PHP worker hanya memerlukan akses tulis ke `storage` dan `bootstrap/cache` (serta file database SQLite bila dipakai); gunakan MySQL/PostgreSQL untuk beban transaksi produksi sesuai kebutuhan. Gunakan konfigurasi server contoh di [deploy/apache-vhost.conf.example](deploy/apache-vhost.conf.example).

Pembayaran **produksi**, fulfillment, pemulihan akses pelanggan, dan MFA admin masih merupakan pekerjaan lanjutan; lihat batas peluncuran pada PRD. `STORE_MODE` yang didukung adalah `demo` dan `sandbox`; mode `live` atau nilai lain tetap ditolak HTTP 503. Mengisi secret produksi tidak mengaktifkan endpoint pembayaran produksi.
