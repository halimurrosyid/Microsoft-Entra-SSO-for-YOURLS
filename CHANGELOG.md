# Changelog

## 2.3.2

- **Perbaikan Hak Akses Statistik Shortlink Pengguna Biasa (`/admin/?access=denied`)**:
  - Mengatasi kendala di mana pengguna biasa yang mengklik ikon statistik (atau membuka URL statistik bertanda `+`) pada shortlink buatan mereka sendiri terlempar ke halaman *Access Denied*.
  - Memperbarui mekanisme validasi kepemilikan tautan (`telu_entra_get_keyword_owner`) agar memeriksa langsung kolom `user` di database tabel `yourls_url` dan mencocokkannya dengan email SSO pengguna yang aktif.
  - Memastikan sanitasi keyword statistik secara tepat memotong tanda `+` di akhir kata kunci.
  - Pengguna biasa kini dapat melihat statistik lengkap dan analitik klik untuk tautan milik mereka sendiri, sementara Super Admin tetap memiliki akses statistik penuh ke semua tautan di sistem.
- **Pencegahan Header Ganda pada Halaman Statistik (`Double Header Fix`)**:
  - Memperbaiki script inisialisasi tema yang sebelumnya keliru menganggap halaman statistik (`yourls-infos.php` atau `/keyword+`) sebagai antarmuka publik murni karena URL-nya tidak mengandung path `/admin`.
  - Mencegah injeksi kartu header publik (`.telu-public-brand`) pada halaman yang sudah memiliki header resmi template YOURLS (`.telu-brand-header`).
  - Menambahkan aturan proteksi CSS (`body#infos .telu-public-brand { display: none !important; }`) sehingga header publik tidak akan pernah tampil ganda di halaman statistik maupun admin.
- **Perbaikan Hover Tombol Keluar pada Header Publik**:
  - Memperbaiki teks "KELUAR" yang sebelumnya hilang/tak terbaca (warna putih di atas latar putih) ketika kursor diarahkan ke tombol.
  - Menetapkan warna teks dan ikon menjadi merah tegas Telkom University (`#b72025`) saat di-hover dengan transisi yang halus dan kontras tinggi.

## 2.3.1

- **Perbaikan Menyeluruh Tampilan Menu Navigasi Admin (`#admin_menu`)**:
  - Mengatasi masalah teks menu yang sempat tak terlihat (putih di atas putih) akibat selektor legasi tema gelap.
  - Menata ulang bar menu admin menjadi card modern dengan kontras tinggi: salam pengguna di sisi kiri dan tab navigasi (*Kelola Short Link / Admin interface*, *Plugin*, *Peralatan*) di sisi kanan.
  - Mempertegas tombol **Logout / Keluar** dengan badge merah kontras (*red pill button*) agar jelas terlihat dan mudah diakses.
  - Tab navigasi halaman aktif otomatis ditandai dengan warna solid merah khas Telkom University.
- **Penyempurnaan Lebar Penuh Footer Tabel & Form Filter (`#filter_form` & Pagination)**:
  - Memperbaiki `tfoot th` yang sebelumnya menyusut (*collapse*) ke kolom kiri dengan mengembalikan ke `display: table-cell` selebar 100% tabel.
  - Menata pagination (halaman 1, 2, 3...) di baris atas footer dengan tombol-tombol nomor yang rapi dan nyaman diklik.
  - Menata baris kriteria pencarian dan tombol aksi (*Search* & *Clear*) membentang horizontal secara proporsional.
- **Perataan Tombol Aksi Tabel (*Actions Column*)**:
  - Memastikan kolom aksi ke-7 selalu berukuran pasti (168px) dan tombol-tombol aksi (*Stats*, *Share*, *Edit*, *Delete*) tetap sejajar dalam 1 baris horizontal tanpa terlipat ke baris kedua.

## 2.3.0

- **Fitur Tombol Salin Cepat 1-Klik (`Copy Link`)**: Menambahkan tombol salin instan dengan indikator animasi visual (*"✓ Tersalin!"*) pada antarmuka hasil shortlink (baik di homepage publik maupun dashboard admin).
- **Fitur Generator QR Code Otomatis**: Menampilkan QR Code resmi beresolusi tinggi langsung saat tautan selesai dibuat, lengkap dengan tombol **"Unduh PNG (Resolusi Tinggi)"** untuk kebutuhan poster, presentasi, dan media cetak sivitas akademika.
- **Tombol Keluar / Logout di Navigasi Publik**: Menambahkan tombol "Keluar" di bar navigasi depan sehingga pengguna komputer bersama (lab/perpustakaan) dapat mengakhiri sesi SSO dengan aman tanpa harus masuk ke panel `/admin/`.
- **Pencegahan Redirection Loop (*Anti Self-Redirect*)**: Memblokir penyingkatan tautan yang mengarah kembali ke domain `s.telkomuniversity.ac.id` untuk mencegah *infinite redirect loop* yang dapat membebani server.
- **Perlindungan Kata Kunci Khusus (*Reserved Keyword Blacklist*)**: Melindungi kata kunci sistem (`admin`, `api`, `login`, `logout`, `dashboard`, `assets`, `plugins`, `pages`, `tools`, `stats`, dll.) agar tidak dapat digunakan sebagai *custom keyword*.
- **Validasi Skema URL Ketat**: Membatasi protokol yang diizinkan hanya `http://` dan `https://`, menolak skema berbahaya seperti `javascript:`, `data:`, atau `file:`.
- **Penyempurnaan Penampil Audit Log**: Memperluas tampilan riwayat audit di panel pengaturan Microsoft SSO admin hingga 25 aktivitas terakhir lengkap dengan label status berwarna (*badge*).

## 2.2.0

- Menggabungkan plugin tema Telkom University secara langsung ke dalam plugin ini sehingga menjadi satu plugin terpadu tanpa perlu mengaktifkan plugin tema terpisah.
- Menerapkan proteksi gerbang homepage (`telu_entra_enforce_root_homepage_gate`) pada tahap awal `plugins_loaded`: saat pertama kali membuka `s.telkomuniversity.ac.id` (belum login), pengunjung HANYA disajikan pop-up modal bersih Microsoft 365. Latar belakang formulir pembuatan shortlink dan navbar dashboard diblokir total hingga login berhasil.
- Setelah login Microsoft 365 berhasil, pengguna baru dapat mengakses antarmuka formulir pembuatan short link dan menu dashboard dengan nama akun mereka.
- **Menghilangkan bagian "The bookmarklet" / Bookmarklets sepenuhnya** dari antarmuka publik via CSS dan JavaScript DOM cleaner agar halaman depan lebih bersih, fokus, dan rapi.
- **Meningkatkan responsivitas tampilan (Mobile, Tablet, Desktop)**:
  - Form pembuatan tautan publik ditata fleksibel dengan input yang memenuhi lebar layar secara vertikal serta target sentuh yang nyaman pada smartphone (<640px).
  - Tabel dashboard admin (`#main_table`) diberikan horizontal scrolling touch-friendly sehingga seluruh kolom dan tombol aksi tetap mudah diakses tanpa terpotong pada perangkat mobile (<820px).
  - Pop-up modal login menggunakan tinggi viewport dinamis (`100dvh`) dan padding adaptif sehingga pas di berbagai resolusi layar ponsel.
- Memperbarui nama direktorat resmi pada copyright menjadi **Direktorat Pusat Teknologi Informasi** dengan tahun yang berganti secara otomatis (`date('Y')` di server dan `new Date().getFullYear()` di frontend).
- Menyembunyikan notifikasi pembaruan versi YOURLS (*"YOURLS version ... is available. Please update!"*) dari role user biasa. Notifikasi pembaruan ini sekarang secara eksklusif hanya dapat dilihat oleh role Super Admin (Administrator).
- Menambahkan pop-up modal login ringkas dan elegan dengan logo resmi Telkom University dan tombol langsung "Masuk dengan Microsoft 365" (O365 SSO) tanpa form cek email manual.
- Menyimpan dan menggunakan aset logo resmi Telkom University yang baru secara lokal di folder `assets/`.
- Memastikan 100% kompatibilitas penuh dengan pengaturan lama (`telu_entra_*` dan `telu_yourls_theme_settings_v1`) agar konfigurasi yang sudah ada di database tetap terbaca utuh tanpa perubahan.

## 2.1.6

- Menambahkan Landing Page (Gateway Portal) resmi Telkom University saat mengakses root layanan sebelum diarahkan ke SSO.
- Menyediakan form validasi email interaktif (real-time feedback) di YOURLS untuk menyaring email di luar domain kampus sebelum dilempar ke Microsoft (mencegah error teknis AADSTS90072).
- Meneruskan parameter OIDC `login_hint` ke Microsoft ketika email telah tervalidasi agar field akun otomatis terisi di portal Microsoft.
- Menambahkan modal pop-up pemberitahuan ketentuan akses layanan khusus civitas Telkom University.
- Menampilkan halaman pemberitahuan khusus yang informatif ketika terdeteksi login dengan akun di luar domain yang sah.
- Menambahkan parameter OIDC `prompt=select_account` pada otorisasi Microsoft agar pengguna dapat memilih/mengganti akun tanpa terjebak auto-login akun pribadi.

## 2.1.5

- Mencegah custom keyword terpotong ketika memakai huruf kapital, tanda hubung (`-`), atau garis bawah (`_`).
- Mempertahankan urutan charset bawaan YOURLS agar keyword otomatis dan shortlink lama tidak berubah.
- Menolak input berisi karakter yang tidak didukung dengan pesan yang jelas, bukan diam-diam menyimpan keyword yang terpotong.

## 2.1.4

- Memperbaiki pengguna yang tidak dapat mengedit atau menghapus shortlink miliknya karena owner lama berbeda kapital atau memiliki spasi tersembunyi.
- Menormalkan hanya owner yang identik dengan email Entra aktif; URL anonim dan milik pengguna lain tetap tidak disentuh.
- Menjalankan rekonsiliasi sebelum pemeriksaan izin AuthMgrPlus agar tombol aksi dan AJAX konsisten.

## 2.1.3

- Memastikan tombol nonaktif benar-benar menghentikan filter menu, role hardening, statistik, dan pemisahan owner milik plugin tanpa menghapus data shortlink.
- Mengikat sesi pada Tenant ID, Client ID, domain, Group ID, dan App Role aktif; perubahan kebijakan otomatis meminta login ulang.
- Menormalkan role AuthMgrPlus sebelum menambahkan user Entra agar assignment lama tidak tertimpa karena perbedaan kapitalisasi nama role.
- Menambahkan pengujian regresi untuk perilaku enable/disable dan fingerprint kebijakan sesi.

## 2.1.2

- Memastikan pembuatan shortlink dari form homepage `POST /result.php` selalu memakai email sesi Microsoft sebagai owner.
- Menambahkan verifikasi owner setelah insert dan perbaikan terparameterisasi jika frontend publik melewati jalur autentikasi standar YOURLS.
- Menambahkan audit `homepage_link_created`, `homepage_owner_repaired`, dan `homepage_owner_failed` untuk diagnosis tanpa merekam token atau secret.

## 2.1.1

- Mengautentikasi request pembuatan shortlink dari frontend `result.php` menggunakan cookie Entra yang telah diverifikasi.
- Menetapkan `YOURLS_USER` sebelum link dibuat agar AuthMgrPlus menyimpan email pembuat sebagai owner.
- Menambahkan guard urutan `insert_link` sebelum callback AuthMgrPlus tanpa menulis database secara langsung.
- Menolak request pembuatan frontend tanpa sesi Microsoft yang valid; pembuatan API anonim tetap diblokir.

## 2.1.0

- Menampilkan Tools, Manage Plugins, dan pengaturan Microsoft SSO hanya kepada Administrator AuthMgrPlus.
- Menambahkan pembatasan server-side untuk akses langsung ke halaman administratif sensitif.
- Mempertahankan Admin interface dan Help untuk user biasa.

## 2.0.1

- Memperbaiki login recovery lokal agar tetap dapat dipakai saat domain atau konfigurasi Entra belum lengkap.
- Memigrasikan domain dari konstanta lama `TELU_ENTRA_ALLOWED_ROOT_DOMAIN` ke pengaturan database satu kali saat upgrade.
- Memperjelas bahwa parameter recovery hanya aktif jika `YOURLS_ENTRA_ALLOW_LOCAL_RECOVERY` bernilai `true`.

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
