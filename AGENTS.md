# Steizen Store

Laravel 13 / PHP 8.4+. Blade dan aset lokal, tanpa pipeline npm.

- Gunakan PHP `.tools/php/php.exe` bila runtime global belum mendukung PHP 8.4. Composer lokal tersedia di `.tools/composer.phar`.
- Jalankan `php artisan test --compact` setelah perubahan perilaku. Tests memakai SQLite in-memory.
- DocumentRoot harus `public`; jangan mengaktifkan ulang endpoint legacy di `storage/app/private`.
- Jangan membaca atau menampilkan secret `.env`, application key, isi data pelanggan, atau password pada output.
- Harga, status transaksi, peran admin, dan owner pesanan harus ditentukan server. Gunakan validated input dan whitelist atribut.
- Semua mutasi HTTP memerlukan perlindungan CSRF; pengecualian baru harus dibatasi pada webhook yang autentikasinya telah diimplementasikan dan diuji.
- Pertahankan transaksi/idempotensi/locking serta pemeriksaan pemilik pesanan. Jangan mengganti encrypted casts menjadi plaintext.
- Mode checkout mendukung demo lokal dan Tripay sandbox, termasuk invoice, callback HMAC, serta rekonsiliasi. Jangan melakukan pembelian supplier/pembayaran riil tanpa implementasi dan otorisasi yang sesuai. Jangan menambahkan simulator ke provider gateway atau mengulangi POST create ketika hasil sebelumnya belum diketahui.
- Gunakan dependency lock dan `composer audit` untuk perubahan dependensi; jangan menonaktifkan verifikasi TLS atau audit blocking.
- Jangan menjalankan migration destruktif atau mengubah database lama untuk menguji aplikasi.
- Perbarui README, PRD, dan SECURITY bila cakupan atau cara menjalankan berubah.
