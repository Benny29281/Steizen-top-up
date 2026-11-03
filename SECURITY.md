# Keamanan Steizen Store

## Perlindungan yang diterapkan

- Laravel 13 dengan dependency lock, autentikasi Laravel, bcrypt, dan regenerasi sesi saat login. Logout hanya melalui POST dengan sesi/token yang diperbarui.
- Akun admin hanya dibuat melalui CLI; tidak ada kredensial bawaan atau registrasi publik. Peran admin tidak dapat diisi melalui mass assignment.
- CSRF/origin protection Laravel untuk seluruh route mutasi; tidak ada pengecualian webhook yang belum diimplementasikan.
- Callback stateless khusus `/api/payments/tripay/callback` tidak memakai CSRF browser; autentikasinya wajib HMAC-SHA256 dari raw body, event `payment_status`, dan validasi invoice/reference/metode/jumlah/biaya/closed-payment. Tidak ada pengecualian CSRF pada form pelanggan atau admin.
- Validasi Form Request sesuai game, batas panjang input, whitelist server/kolom akun, relasi produk-game, status aktif, dan kedaluwarsa katalog supplier.
- Harga/status/biaya/kepemilikan pesanan selalu berasal dari server, tanpa mempercayai nilai browser.
- Transaksi database, row lock, request key unik, serta session lock untuk menekan pesanan ganda. Belum ada pembelian supplier dalam alur ini.
- Data akun dan WhatsApp dienkripsi melalui encrypted casts. Data sensitif dan owner hash disembunyikan dari serialisasi model.
- Detail/daftar pesanan pelanggan diperiksa berdasarkan hash identitas pemilik dalam sesi, bukan hanya nomor referensi.
- Rate limit: login 5 percobaan per email+IP per menit dan 20 per IP; pembuatan pesanan 10 per IP per menit; perubahan admin 30 per akun per menit.
- Output Blade di-escape, query melalui Eloquent, whitelist atribut admin, serta audit perubahan katalog dalam transaksi yang sama.
- Penambahan nominal manual hanya untuk admin; source/SKU/nama dibentuk server sebagai produk demo, dengan validasi jumlah/harga, penolakan nominal ganda, serta pencatatan audit atomik. Pengguna tidak dapat membuat SKU supplier melalui formulir ini.
- CSP tanpa `unsafe-inline`/CDN, `nosniff`, anti-framing, no-store, pembatasan permissions browser, dan HSTS pada HTTPS produksi.
- Mode produksi menolak HTTP serta startup dengan debug aktif atau cookie/sesi yang tidak aman. Host dibatasi sesuai `APP_URL`; proxy tidak dipercaya secara global.
- `.env`/database/arsip/runtimes dikecualikan dari Git. Kode lama di luar `public`, dengan pembatasan Apache tambahan untuk source/storage.
- Konektor supplier memakai HTTPS endpoint tetap, timeout, tanpa redirect, tanpa pembelian item, dan tanpa menampilkan raw payload/kredensial pada kegagalan.

## Batas dan tanggung jawab deployment

Tidak ada klaim aplikasi kebal terhadap semua serangan. Tests otomatis dan Composer audit bukan audit penetrasi menyeluruh. Pemeriksaan tampilan melalui browser otomatis belum dapat dilakukan karena host browser tidak tersedia; render Blade dan HTTP telah diperiksa.

Pada pembaruan tampilan, upaya pemeriksaan melalui jendela browser juga dihentikan karena URL jendela tidak dapat diverifikasi oleh alat. Pemeriksaan pembaruan mencakup pengujian fitur, kompilasi Blade, validitas XML ilustrasi SVG, dan respons HTTP; verifikasi visual penuh pada desktop/mobile masih perlu dilakukan.

Application key, file `.env`, backup, database, dan arsip lama harus tetap privat. Arsip kode lama mungkin berisi konfigurasi koneksi lama; jangan menyalinnya ke `public` atau storage publik. Tidak ada symlink storage publik yang dibutuhkan aplikasi ini.

DokumentRoot wajib `public`; jangan menjalankan `php -S` dari direktori root tanpa router/direktori publik. Untuk lokal gunakan `artisan serve` atau `start-local.bat`. Pada Nginx, abaikan `.htaccess` dan konfigurasi root/deny aturan secara eksplisit. Pada Apache, aktifkan rewrite dan AllowOverride yang sesuai.

Jika memakai reverse proxy/load balancer, hanya percayai IP proxy yang benar dan konfigurasi HTTPS forwarding secara terkontrol. Jangan memakai wildcard untuk mempercayai seluruh proxy. TLS, firewall, pembaruan sistem, backup terenkripsi, dan izin filesystem tetap tanggung jawab lingkungan hosting.

Sebelum menerima pembayaran riil, masih diperlukan pengujian gateway dengan akun asli, MFA admin atau pembatasan akses setara, autentikasi/pemulihan akses pelanggan, kebijakan retensi, database produksi teruji, fulfillment idempoten, dan penanganan refund operasional. Webhook bertanda tangan serta rekonsiliasi timeout sudah diimplementasikan untuk sandbox; kode pembayaran produksi belum diaktifkan.

## Invoice dan pembayaran uji

Provider invoice ditetapkan server dan tidak berubah mengikuti input pelanggan atau pergantian mode konfigurasi. Invoice demo mempunyai penanda simulasi pada tampilan/cetakan; tombol simulasi hanya bekerja pada provider demo, mode demo, environment lokal/testing, pemilik sesi yang benar, CSRF sah, metode terpilih, serta invoice belum kedaluwarsa. Tidak ada endpoint pelanggan untuk menandai invoice gateway lunas.

Pembayaran Tripay saat ini terbatas pada endpoint HTTPS sandbox yang tetap, tanpa redirect/retry POST otomatis. Channel aktif dan fee diverifikasi server. Quote ID mencegah tab lama memulai pembayaran setelah metode/total diubah. State CREATING/UNKNOWN dan referensi tetap mencegah create ulang ketika respons hilang. Rekonsiliasi hanya GET transaksi/detail menggunakan invoice/reference yang sama.

Respons gateway harus cocok dengan invoice, reference, metode, jumlah, dan fee. URL checkout hanya diterima dari host HTTPS `tripay.co.id` atau `payment.tripay.co.id`, tanpa userinfo/port lain. Instruksi pembayaran disimpan sebagai teks yang di-escape; HTML/scripts supplier tidak dirender mentah. Tidak ada QR/nomor rekening pembayaran asli pada simulator lokal.

Status dan event ditulis dalam transaksi dengan row lock, event key unik, dan hash payload. Status lunas tidak diturunkan oleh event kedaluwarsa/gagal yang terlambat; refund menjadi terminal. Kembali dari gateway atau parameter `status=PAID` tidak mempunyai efek finansial. Polling pelanggan hanya membaca database; pemeriksaan API dibatasi, dan scheduler tersedia untuk callback yang terlewat. Secret, raw respons gateway, email, dan nomor pelanggan tidak dicetak ke log konektor.

Jangan mengaktifkan kembali endpoint lama (`tambah.php`, `callback.php`, `submit_payment.php`, dan lain-lain). Password legacy tidak diimpor: kode lama melakukan pencocokan password langsung dan tidak memenuhi alur hashing baru.

## Verifikasi rutin

Jalankan `php artisan test` serta `composer audit` setelah pembaruan dependensi atau perubahan akses/transaksi. Dependency lock saat pengerjaan tidak memiliki advisori kerentanan yang dilaporkan Composer; hasil audit dapat berubah seiring advisori baru.
