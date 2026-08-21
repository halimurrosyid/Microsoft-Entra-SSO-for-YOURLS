# Microsoft Entra SSO for YOURLS

Plugin SSO Microsoft Entra ID yang generik untuk YOURLS. Administrator menentukan sendiri tenant dan domain email organisasinya. Domain utama dan seluruh subdomain yang sah dapat digunakan, sedangkan domain tiruan otomatis ditolak.

Contoh jika domain organisasi diisi `example.edu`:

- `user@example.edu` dan `user@student.example.edu` diizinkan.
- `user@evilexample.edu` dan `user@example.edu.example.com` ditolak.

Homepage `/` dan halaman administrasi memerlukan login Microsoft. Shortlink yang telah dibuat, misalnya `/abc123`, tetap dapat dibuka publik tanpa login.

## Fitur utama

- Authorization Code Flow dengan PKCE serta validasi `state` dan `nonce`.
- Verifikasi RS256 ID token melalui Microsoft JWKS.
- Validasi tenant, audience, issuer, waktu token, domain email, serta Group/App Role opsional.
- Tes koneksi sebelum SSO diaktifkan dan tombol enable/disable.
- Nama lengkap claim Microsoft `name` ditampilkan sebagai `Hello Nama Lengkap`.
- Pemisahan shortlink per pengguna melalui AuthMgrPlus.
- Contributor dan Editor hanya melihat serta mengelola shortlink miliknya.
- Administrator melihat seluruh shortlink, termasuk link lama tanpa pemilik.
- Client Secret tidak disimpan di database atau ditampilkan kembali.
- Tidak membutuhkan Composer.

## Persyaratan

- YOURLS 1.10.x dalam mode private.
- PHP 8.1+ dengan cURL dan OpenSSL.
- HTTPS.
- Plugin wajib [AuthMgrPlus 2.3.1](https://github.com/joshp23/YOURLS-AuthMgrPlus).

## 1. Microsoft Entra App Registration

1. Masuk ke Microsoft Entra Admin Center.
2. Buka **App registrations** → **New registration**.
3. Pilih **Accounts in this organizational directory only**.
4. Tambahkan platform **Web**.
5. Masukkan URL YOURLS dengan `/admin/` sebagai Redirect URI, misalnya `https://go.example.edu/admin/`.
6. Pada **Certificates & secrets**, buat Client Secret.
7. Salin kolom **Value**, bukan Secret ID. Nilai hanya muncul sekali.
8. Catat **Directory (tenant) ID** dan **Application (client) ID**.

Plugin hanya meminta scope OIDC `openid`, `profile`, dan `email`. Plugin tidak meminta akses file, kontak, atau kalender.

## 2. Instalasi

1. Unduh ZIP terbaru dari folder [dist](https://github.com/halimurrosyid/Microsoft-Entra-SSO-for-YOURLS/tree/main/dist).
2. Ekstrak folder `yourls-microsoft-entra-sso` ke `YOURLS/user/plugins/`.
3. Pasang dan aktifkan [AuthMgrPlus](https://github.com/joshp23/YOURLS-AuthMgrPlus).
4. Tambahkan Client Secret ke `user/config.php`.
5. Aktifkan **Microsoft Entra SSO for YOURLS** dari **Manage Plugins**.

## 3. Konfigurasi rahasia

Pastikan YOURLS private dan gunakan cookie key acak yang kuat:

```php
define( 'YOURLS_PRIVATE', true );
define( 'YOURLS_COOKIEKEY', 'NILAI-ACAK-MINIMAL-32-KARAKTER' );

define( 'YOURLS_ENTRA_CLIENT_SECRET', 'CLIENT-SECRET-VALUE' );

// Tetap false. Ubah sementara hanya saat recovery darurat.
define( 'YOURLS_ENTRA_ALLOW_LOCAL_RECOVERY', false );
```

Client Secret juga dapat disimpan sebagai environment variable `YOURLS_ENTRA_CLIENT_SECRET`. Tenant ID, Client ID, domain organisasi, Administrator/Editor, dan pengaturan non-rahasia lainnya diisi dari halaman plugin.

Konstanta lama berawalan `TELU_ENTRA_` dari versi 1.x tetap dibaca agar upgrade tidak merusak instalasi. Instalasi baru sebaiknya memakai `YOURLS_ENTRA_`.

## 4. Pengaturan melalui website

Masuk menggunakan admin lokal YOURLS, lalu buka **Manage Plugins → Microsoft SSO** dan isi:

- **Tenant ID**: Directory (tenant) ID dari Microsoft Entra.
- **Client ID**: Application (client) ID.
- **Domain email organisasi**: domain utama tanpa `@`, protokol, atau path; contoh `example.edu`. Seluruh subdomain otomatis diterima.
- **Email Administrator**: minimal satu alamat dalam domain yang diizinkan.
- **Email Editor**: opsional, pisahkan dengan koma.
- **Durasi sesi**: 900–86400 detik.
- **Allowed Group IDs/App Roles**: opsional.

Domain tidak lagi ditetapkan ke organisasi tertentu. Domain dapat dikunci melalui `YOURLS_ENTRA_ALLOWED_ROOT_DOMAIN`, tetapi untuk penggunaan biasa cukup diisi melalui website.

## 5. AuthMgrPlus dan kepemilikan

AuthMgrPlus wajib aktif sebelum tes atau aktivasi SSO. Role pengguna Microsoft:

- Default: `Contributor`.
- Email pada daftar Editor: `Editor`.
- Email pada daftar Administrator: `Administrator`.

Akun lokal YOURLS tetap dapat ditentukan di `user/config.php`:

```php
$amp_role_assignment = array(
    'administrator' => array( 'admin' ),
    'editor'        => array( 'editor-local' ),
    'contributor'   => array( 'user-local' ),
);
```

Aturan akses:

- Contributor dan Editor hanya melihat total, daftar, statistik, edit, serta hapus shortlink miliknya.
- Administrator dapat melihat dan mengelola seluruh shortlink.
- Shortlink lama dengan kolom `Username` kosong hanya terlihat Administrator.
- Kepemilikan link baru disimpan menggunakan email Microsoft pengguna.
- Menonaktifkan atau menghapus plugin tidak menghapus shortlink atau database YOURLS.
- Redirect shortlink publik tetap berjalan tanpa login selama YOURLS aktif.

## 6. Tes lalu aktifkan

1. Simpan semua pengaturan.
2. Pastikan Client Secret berstatus **Terpasang (disembunyikan)**.
3. Klik **Tes Login Microsoft** ketika SSO masih nonaktif.
4. Login dengan akun dari tenant dan domain yang dikonfigurasi.
5. Pastikan **Tes login terakhir** berhasil.
6. Klik **Aktifkan SSO**.
7. Logout dan uji melalui Incognito/Private.
8. Buka homepage; pengguna harus diarahkan ke Microsoft.
9. Buat shortlink dan pastikan hanya pembuat serta Administrator yang melihatnya.
10. Buka shortlink tanpa sesi; redirect harus tetap berjalan.

Tes memakai alur Microsoft sebenarnya. Token tidak disimpan. Hasil tes terikat pada Tenant ID, Client ID, dan fingerprint satu arah Client Secret; perubahan konfigurasi mengharuskan tes ulang.

## Enable, disable, reset, dan penghapusan

- **Aktifkan SSO** melindungi homepage/admin dan memblokir pembuatan link lewat API.
- **Nonaktifkan SSO** mengembalikan autentikasi bawaan YOURLS tanpa menghapus konfigurasi atau shortlink.
- **Reset Konfigurasi** menghapus pengaturan non-rahasia setelah SSO dinonaktifkan; Client Secret tidak diubah.
- Menghapus folder plugin tidak menghapus shortlink, tetapi login Microsoft dan pemisahan kepemilikan tidak lagi diterapkan.
- Menonaktifkan AuthMgrPlus ketika SSO digunakan tidak didukung; plugin menampilkan kesalahan konfigurasi.

## Pembatasan Entra opsional

- **Allowed Group IDs** membutuhkan claim `groups`.
- **Allowed App Roles** membutuhkan claim `roles`.
- Jika keduanya diisi, akun harus memenuhi kedua kategori.
- Kosongkan keduanya jika validasi tenant dan domain sudah cukup.

## Recovery admin lokal

Jika Microsoft SSO bermasalah, ubah sementara:

```php
define( 'YOURLS_ENTRA_ALLOW_LOCAL_RECOVERY', true );
```

Lalu buka `https://go.example.edu/admin/?telu_local_login=1`, login dengan admin lokal, dan segera kembalikan nilainya menjadi `false`. Parameter URL lama dipertahankan untuk kompatibilitas versi 1.x.

Jika plugin gagal sebelum login muncul, ubah sementara nama `plugin.php` melalui file manager server, perbaiki konfigurasi, lalu kembalikan namanya.

## Logout Microsoft opsional

```php
define( 'YOURLS_ENTRA_LOGOUT_MICROSOFT', true );
define( 'YOURLS_ENTRA_POST_LOGOUT_REDIRECT_URI', 'https://go.example.edu/' );
```

Daftarkan URI tersebut pada App Registration bila digunakan.

## Upgrade dari versi 1.x

1. Backup `user/config.php` dan database YOURLS.
2. Ganti folder plugin; jangan menghapus AuthMgrPlus.
3. Konstanta `TELU_ENTRA_*` lama tetap berfungsi.
4. Buka **Microsoft SSO**, isi domain organisasi bila sebelumnya tidak ditulis eksplisit, lalu simpan.
5. Jalankan tes login kembali sebelum mengaktifkan SSO.
6. Konstanta lama dapat diganti bertahap ke `YOURLS_ENTRA_*`.

## Privasi

Database menyimpan konfigurasi non-rahasia, status enable, hasil tes, dan maksimal 100 event audit. Cookie sesi bertanda tangan menyimpan email, nama tampilan, subject, tenant, dan waktu sesi. Token Microsoft dan Client Secret tidak disimpan di database. Audit tidak mencatat token, secret, IP, atau user-agent.

## Dukungan dan lisensi

- Repository: [Microsoft-Entra-SSO-for-YOURLS](https://github.com/halimurrosyid/Microsoft-Entra-SSO-for-YOURLS)
- Dependensi wajib: [YOURLS-AuthMgrPlus](https://github.com/joshp23/YOURLS-AuthMgrPlus)
- Author: Konten Telu
- Lisensi: GPL-3.0-or-later
