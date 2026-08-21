# Changelog

## 2.0.0

- Menghapus domain organisasi bawaan; administrator menentukan domain sendiri.
- Menambahkan pengaturan domain organisasi melalui halaman plugin.
- Mengganti konstanta publik menjadi awalan universal `YOURLS_ENTRA_`.
- Menjaga kompatibilitas seluruh konstanta lama `TELU_ENTRA_` versi 1.x.
- Menetralkan contoh, pesan, metadata, dan dokumentasi untuk organisasi mana pun.
- Memperluas panduan Azure, instalasi, role, pengujian, upgrade, recovery, penghapusan, dan privasi.

## 1.5.0

- Memperketat isolasi kepemilikan: Contributor dan Editor hanya melihat shortlink miliknya sendiri.
- Menyembunyikan shortlink lama tanpa pemilik dari semua role selain Administrator.
- Menyamakan pembatasan pada daftar admin, total statistik, API statistik, halaman info, edit, dan hapus.
- Mempertahankan akses redirect shortlink publik tanpa login.

## 1.4.4

- Mengarahkan Author URI ke repository GitHub resmi plugin.

## 1.4.3

- Menghapus pengaturan dan pengingat tanggal kedaluwarsa Client Secret agar halaman konfigurasi lebih sederhana.
- Validasi perubahan Client Secret melalui fingerprint dan tes ulang tetap dipertahankan.

## 1.4.2

- Mengganti nama publik plugin menjadi `Microsoft Entra SSO for YOURLS`.
- Mengganti author menjadi `Konten Telu`.
- Mengarahkan Plugin URI dan Author URI ke `https://it.telkomuniversity.ac.id/`.
- Menggunakan nama folder dan paket yang universal.

## 1.4.1

- Menampilkan nama lengkap dari claim OIDC `name` pada sapaan header YOURLS.
- Mempertahankan email sebagai identitas internal, pemilik shortlink, dan kunci role AuthMgrPlus.
- Menambahkan fallback aman ke email jika Microsoft tidak mengirim nama lengkap.

## 1.4.0

- Mengikat hasil tes ke fingerprint satu arah Client Secret; rotasi secret mewajibkan tes ulang.
- Mewajibkan AuthMgrPlus dan minimal satu administrator Telkom University sebelum tes/aktivasi.
- Menambahkan pencatatan tes gagal dan audit login terbatas maksimal 100 event.
- Menambahkan reset konfigurasi non-rahasia dan cache JWKS ketika SSO nonaktif.
- Menambahkan pengaturan durasi sesi.
- Menambahkan pembatasan opsional berdasarkan Entra Group ID dan App Role claim.
- Menambahkan deteksi apakah homepage melewati hook loader YOURLS.
- Mendukung hingga lima flow login paralel agar beberapa tab tidak saling membatalkan.
- Memperluas smoke test untuk fingerprint, CSV, serta otorisasi Group claim.

## 1.3.0

- Menambahkan tombol Tes Login Microsoft dengan alur OIDC interaktif lengkap.
- Menyimpan hasil tes sukses terakhir tanpa token atau Client Secret.
- Menambahkan tombol Aktifkan/Nonaktifkan SSO di dashboard.
- SSO sekarang nonaktif secara default untuk memungkinkan konfigurasi dan pengujian aman.
- Saat dinonaktifkan, autentikasi kembali ke perilaku bawaan YOURLS tanpa menghapus data atau pengaturan.

## 1.2.0

- Menambahkan form dashboard untuk menyimpan Tenant ID dan Client ID.
- Melindungi form dengan nonce YOURLS dan validasi GUID.
- Client Secret tetap hanya dibaca dari `user/config.php` atau environment dan tidak disimpan ke database.
- Memungkinkan konfigurasi awal menggunakan administrator lokal sebelum SSO diaktifkan.
- Mempertahankan kompatibilitas konfigurasi lama; constant dan environment tetap memiliki prioritas tertinggi.

## 1.1.1

- Melindungi homepage `/` dengan login Entra sebelum antarmuka pembuatan link ditampilkan.
- Membiarkan request keyword shortlink tetap publik dan langsung redirect seperti biasa.
- Mengizinkan homepage sebagai tujuan kembali setelah callback Microsoft.

## 1.1.0

- Admin dan pembuatan shortlink sekarang fail-closed ketika konfigurasi SSO tidak valid.
- Login lokal dan cookie lokal tidak lagi melewati SSO secara default.
- Menambahkan recovery lokal opt-in melalui `TELU_ENTRA_ALLOW_LOCAL_RECOVERY`.
- Memblokir pembuatan shortlink melalui API agar login Entra tidak dapat dilewati.
- Memastikan `YOURLS_PRIVATE` aktif; redirect shortlink publik tetap bebas diakses.
- Memvalidasi OAuth `state` sebelum menangani respons error dan menghapus flow cookie.
- Menyesuaikan persyaratan minimum ke PHP 8.1 untuk YOURLS 1.10.x.

## 1.0.0

- Microsoft Entra Authorization Code Flow dengan PKCE.
- Verifikasi ID token RS256 dan Microsoft JWKS rotation.
- Validasi Tenant ID serta email `telkomuniversity.ac.id` dan seluruh subdomainnya.
- Cookie sesi bertanda tangan.
- Role Contributor otomatis dan allowlist Editor/Administrator untuk AuthMgrPlus.
- Login admin lokal darurat.
- Halaman diagnosis konfigurasi tanpa menampilkan Client Secret.
