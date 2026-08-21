# Microsoft Entra SSO for YOURLS

Plugin Microsoft Entra ID SSO khusus YOURLS. Pembuatan dan pengelolaan shortlink melalui homepage `/` wajib login dengan akun Microsoft dalam tenant yang dikonfigurasi apabila emailnya memakai:

- `@telkomuniversity.ac.id`
- subdomain apa pun, misalnya `@student.telkomuniversity.ac.id`

Domain yang mirip seperti `telkomuniversity.ac.id.example.com` atau `eviltelkomuniversity.ac.id` otomatis ditolak.

Shortlink yang sudah dibuat tetap dapat dibuka oleh siapa saja seperti biasa, tanpa login. Plugin melindungi homepage `/` dan halaman admin, tetapi tidak mengubah request keyword seperti `/abc123` maupun proses redirect shortlink publik.

## Fitur keamanan

- Authorization Code Flow dengan PKCE.
- Validasi `state` dan `nonce`.
- Verifikasi tanda tangan RS256 ID token menggunakan Microsoft JWKS.
- Validasi ketat `issuer`, `tenant ID`, `audience`, waktu berlaku, dan domain email.
- Cookie sesi bertanda tangan, `Secure`, `HttpOnly`, `SameSite=Lax`, dan prefix `__Host-`.
- Header menampilkan nama lengkap dari claim Microsoft `name`; identitas dan kepemilikan tetap memakai email.
- Client Secret hanya dibaca dari `user/config.php` atau environment server.
- SSO nonaktif secara default sampai administrator menguji dan mengaktifkannya.
- Tes login menjalankan alur Microsoft sebenarnya tanpa membuka SSO untuk publik.
- Login lokal dinonaktifkan secara default dan hanya dapat diaktifkan sementara untuk pemulihan darurat.
- Pembuatan shortlink melalui YOURLS API diblokir agar tidak melewati login Microsoft.
- Tidak membutuhkan Composer atau library pihak ketiga.

## Kompatibilitas

- YOURLS 1.10.x
- PHP 8.1 atau lebih baru
- PHP cURL dan OpenSSL
- HTTPS wajib
- [AuthMgrPlus](https://github.com/joshp23/YOURLS-AuthMgrPlus) 2.3.1 wajib untuk pemisahan link per pengguna

## 1. Microsoft Entra App Registration

1. Masuk ke Microsoft Entra Admin Center.
2. Buka **App registrations** lalu pilih **New registration**.
3. Gunakan tipe akun **Accounts in this organizational directory only**.
4. Tambahkan platform **Web**.
5. Isi Redirect URI berikut secara persis:

   `https://s.telkomuniversity.ac.id/admin/`

6. Buat Client Secret pada **Certificates & secrets**.
7. Salin **Value** Client Secret, bukan Secret ID.
8. Catat **Directory (tenant) ID** dan **Application (client) ID**.

Plugin hanya meminta scope OIDC `openid`, `profile`, dan `email`. Tidak memerlukan izin membaca email, file, kontak, maupun kalender.

## 2. Instalasi plugin

1. Ekstrak folder `yourls-microsoft-entra-sso` ke:

   `YOURLS/user/plugins/yourls-microsoft-entra-sso/`

2. Isi Client Secret di `user/config.php` seperti langkah berikut, lalu aktifkan plugin.

## 3. Konfigurasi user/config.php

Pastikan konfigurasi YOURLS menggunakan mode private:

```php
define( 'YOURLS_PRIVATE', true );
```

Ganti `YOURLS_COOKIEKEY` bawaan dengan nilai acak minimal 32 karakter. Client Secret adalah satu-satunya kredensial Entra yang wajib ditambahkan ke file ini:

```php
define( 'TELU_ENTRA_CLIENT_SECRET', 'CLIENT-SECRET-VALUE' );
define( 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' );

// Pertahankan false. Aktifkan hanya sementara saat pemulihan darurat.
define( 'TELU_ENTRA_ALLOW_LOCAL_RECOVERY', false );
```

Client Secret juga dapat disimpan sebagai environment variable `TELU_ENTRA_CLIENT_SECRET` agar tidak ditulis di file.

Tenant ID, Client ID, email Administrator/Editor, durasi sesi, serta pembatasan Group/App Role dimasukkan melalui menu plugin.

## 4. AuthMgrPlus

Unduh dan pasang plugin wajib dari repository resmi: [joshp23/YOURLS-AuthMgrPlus](https://github.com/joshp23/YOURLS-AuthMgrPlus).

AuthMgrPlus wajib aktif sebelum tes dan aktivasi SSO. Plugin otomatis menambahkan pengguna Microsoft ke role AuthMgrPlus pada setiap request:

- Default: `Contributor`
- Email dalam `TELU_ENTRA_EDITOR_EMAILS`: `Editor`
- Email dalam `TELU_ENTRA_ADMIN_EMAILS`: `Administrator`

Akun lokal yang sudah ada tetap ditentukan melalui `$amp_role_assignment` seperti biasa:

```php
$amp_role_assignment = array(
    'administrator' => array( 'admin' ),
    'editor'        => array( 'celoe' ),
    'contributor'   => array( 'celoezoom' ),
);
```

Pengguna Contributor hanya mengelola shortlink miliknya. Plugin menolak aktivasi ketika AuthMgrPlus tidak terdeteksi untuk mencegah akses bersama yang tidak disengaja.

## 5. Aktivasi dan pengujian

1. Aktifkan **Microsoft Entra SSO for YOURLS** pada Manage Plugins.
2. Login menggunakan administrator lokal YOURLS; SSO masih nonaktif secara default.
3. Buka menu **Microsoft SSO**.
4. Masukkan **Directory (tenant) ID**, **Application (client) ID**, dan minimal satu email Administrator, lalu klik **Simpan Pengaturan**.
5. Pastikan status Client Secret menunjukkan **Terpasang (disembunyikan)** dan konfigurasi dinyatakan siap.
6. Klik **Tes Login Microsoft**. Selesaikan login menggunakan akun Telkom University.
7. Pastikan status **Tes login terakhir** menunjukkan berhasil beserta email yang diuji.
8. Klik **Aktifkan SSO** hanya setelah tes berhasil.
9. Keluar dari sesi lokal dan uji menggunakan jendela Incognito/Private.
10. Buka `https://s.telkomuniversity.ac.id/`; login Microsoft akan dimulai sebelum homepage ditampilkan.
11. Buat sebuah shortlink, lalu buka URL pendeknya dari browser Incognito. Redirect harus berjalan tanpa meminta login.

## Tes dan enable/disable

- **Tes Login Microsoft** menjalankan Authorization Code Flow lengkap, termasuk pertukaran code menggunakan Client Secret dan validasi token/domain.
- Tes dapat dijalankan ketika SSO masih nonaktif, sehingga pengunjung belum diarahkan ke Microsoft.
- Hasil tes hanya menyimpan status sukses, email yang diuji, dan waktu tes. Token tidak disimpan.
- Hasil tes terikat pada fingerprint satu arah Client Secret. Jika secret berubah, tes wajib diulang.
- **Aktifkan SSO** mulai melindungi homepage dan admin serta memblokir pembuatan link melalui API.
- **Nonaktifkan SSO** mengembalikan autentikasi ke perilaku bawaan YOURLS tanpa menghapus konfigurasi atau shortlink.
- **Reset Konfigurasi** tersedia setelah SSO dinonaktifkan; Client Secret di `user/config.php` tidak ikut dihapus.
- Audit dibatasi ke 100 event dan tidak mencatat token, secret, IP, atau user-agent.

## Pembatasan Entra opsional

- **Allowed Group IDs** membatasi login ke Object ID grup Entra tertentu dan membutuhkan `groups` claim di ID token.
- **Allowed App Roles** membatasi login ke App Role tertentu melalui `roles` claim.
- Jika Group IDs dan App Roles sama-sama diisi, akun wajib memenuhi kedua kategori.
- Kosongkan keduanya jika validasi tenant dan domain email sudah mencukupi.

## Kebijakan akses

- Homepage `/` dan `/admin/`: wajib login Microsoft Entra Telkom University.
- Akun harus berasal dari Tenant ID yang dikonfigurasi.
- Email harus tepat pada `telkomuniversity.ac.id` atau subdomainnya.
- Endpoint API `action=shorturl`: ditolak, karena tidak mempunyai sesi login Microsoft interaktif.
- Request keyword shortlink seperti `/abc123` dan redirect ke URL tujuan: tetap bebas diakses tanpa login.
- Instalasi harus menggunakan `define( 'YOURLS_PRIVATE', true );` agar antarmuka pembuatan link tidak terbuka untuk publik.

## Akses admin lokal darurat

Login lokal tidak tersedia secara default. Jika Microsoft SSO bermasalah, ubah sementara konfigurasi berikut menjadi `true`:

```php
define( 'TELU_ENTRA_ALLOW_LOCAL_RECOVERY', true );
```

Kemudian gunakan:

`https://s.telkomuniversity.ac.id/admin/?telu_local_login=1`

Login menggunakan akun lokal YOURLS, misalnya `admin`.

Segera kembalikan `TELU_ENTRA_ALLOW_LOCAL_RECOVERY` menjadi `false` setelah perbaikan selesai.

Jika plugin menyebabkan kendala sebelum halaman login tampil, ubah nama `plugin.php` menjadi `plugin-disabled.php` melalui aaPanel. Setelah akses pulih, periksa konfigurasi dan kembalikan namanya.

## Logout Microsoft opsional

Secara default, logout YOURLS hanya menghapus sesi plugin. Untuk ikut mengakhiri sesi Microsoft:

```php
define( 'TELU_ENTRA_LOGOUT_MICROSOFT', true );
define( 'TELU_ENTRA_POST_LOGOUT_REDIRECT_URI', 'https://s.telkomuniversity.ac.id/' );
```

Pastikan URI setelah logout juga diizinkan dalam App Registration.

## Catatan privasi

Plugin menyimpan Tenant ID, Client ID, role allowlist, batas Group/App Role, status enable, hasil tes, dan audit terbatas sebagai pengaturan non-rahasia di database. Cookie sesi menyimpan email institusi, `sub`, Tenant ID, dan waktu sesi. Token Microsoft dan Client Secret tidak pernah disimpan ke database.
