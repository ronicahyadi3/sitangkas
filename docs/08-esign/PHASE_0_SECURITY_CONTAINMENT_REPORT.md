# Phase 0 Security Containment Report

Tanggal audit: **17 September 2026**.

Status: **containment lokal selesai; rotasi eksternal dan provisioning secret
belum dapat dinyatakan selesai**.

Dokumen ini adalah laporan tersanitasi. Nilai NIK, passphrase, Basic Auth,
token, PDF, dan material credential tidak dicatat.

## 1. Scope audit

Audit read-only dilakukan terhadap:

- source aktif `app`, `config`, `routes`, `resources`, `database`, dan aset JS
  eSign terkait;
- `.env`, `.env.example`, dan `.env.production.example` tanpa menampilkan nilai;
- Git history project aktif berdasarkan pola BSrE/eSign/passphrase/Basic Auth;
- log aplikasi, config cache, file cache, dan file session;
- tabel database `jobs`, `failed_jobs`, `sessions`, `cache`, `login_events`, dan
  `user_management_audit_events` menggunakan query hitung read-only;
- file integrasi terpilih pada project lama
  `C:\Apache24\htdocs\sitangkas` sebagai referensi read-only;
- metadata authentication collection Postman 2.2.0-beta yang diberikan user.

Tidak dilakukan:

- perubahan pada project lama;
- pemanggilan endpoint BSrE;
- penulisan credential baru ke `.env`;
- rotasi akun pada sistem BSrE karena akses pengelolaan credential tidak
  tersedia dalam workspace;
- rewrite Git history;
- penghapusan log/backup/deployment artifact di luar workspace;
- pembuatan atau eksekusi test suite.

## 2. Temuan project aktif

1. Belum ada variable `BSRE_*`/`ESIGN_*` pada `.env` ketika audit dilakukan.
2. `config/services.php` sebelumnya belum mempunyai konfigurasi eSign.
3. Tidak ditemukan literal identitas 16 digit pada file eSign aktif yang
   diperiksa.
4. Komponen Blade eSign legacy masih di-include oleh banyak halaman payment dan
   masih memiliki input NIK/passphrase, tetapi audit tidak menemukan nilai
   credential yang di-hardcode pada komponen tersebut. Input aktif kemudian
   diperketat dengan `autocomplete="off"`; input passphrase juga memakai
   `autocapitalize="none"` dan `spellcheck="false"`.
5. Tidak ditemukan `BsreClient` atau controller TTE provider aktif di project
   baru pada saat audit. UI legacy belum merupakan bukti koneksi provider v2.
6. `.env` tidak dilacak Git dan rule `.gitignore` mencakup `.env`,
   `.env.backup`, dan `.env.production`.
7. Git history project aktif yang cocok dengan pola audit menunjukkan referensi
   UI/payment, bukan konfigurasi Basic Auth BSrE yang terdeteksi oleh scan ini.
8. `gitleaks` dan `trufflehog` tidak tersedia di environment, sehingga scan
   history dilakukan dengan pattern-based Git inspection dan bukan scanner
   entropy/secret penuh.
9. Tidak ditemukan pattern BSrE/passphrase/Basic Auth/Authorization pada log,
   config cache, file cache, atau file session yang diperiksa.
10. Query read-only menghasilkan 0 row mencurigakan pada `jobs`, `failed_jobs`,
    `sessions`, `cache`, `login_events`, dan `user_management_audit_events`.
11. Config cache tidak ada dan queue worker SITANGKAS tidak sedang berjalan saat
    audit, sehingga tidak ada process lokal yang perlu direstart pada tahap ini.

## 3. Temuan project lama

Project lama mengandung material yang harus dianggap terekspos:

- dua hardcoded sensitive values pada
  `app/Http/Controllers/Esign/TteController.php` sekitar baris 69-70;
- konfigurasi eSign pada `config/services.php` sekitar baris 39-43 menggunakan
  environment variable dengan non-empty default credential;
- `.env` dan `.env.example` project lama tidak mendefinisikan
  `BSRE_BASE_URL`, `BSRE_USERNAME`, `BSRE_PASSWORD`, `BSRE_TIMEOUT`, atau
  `BSRE_LOCATION`, sehingga code path lama berpotensi memakai seluruh default
  yang tertanam di config;
- scan tersanitasi menemukan 1.166 keyword match terkait eSign/passphrase/Basic
  Auth/Authorization pada 31 file log lama. Ini adalah kandidat paparan dan
  tidak berarti setiap match memuat nilai credential;
- `bootstrap/cache/config-recovery-20260723-134158.php` pada project lama
  memiliki satu keyword match terkait dan harus diperlakukan sebagai artifact
  konfigurasi sensitif sampai diperiksa oleh pemilik;
- client lama menggunakan kontrak API v1 dan tidak boleh disalin ke code path
  baru.

Nilainya sengaja tidak dicatat. Menghapus source lama saja tidak membatalkan
credential; pemilik credential tetap harus melakukan revoke/rotate.

## 4. Perubahan containment pada project aktif

### `config/services.php`

Ditambahkan `services.bsre_esign` dengan ketentuan:

- default `enabled=false`;
- base URL, username, dan password tidak mempunyai fallback;
- TLS verification default aktif;
- connect/sign/verify timeout eksplisit;
- endpoint default hanya menunjuk `/api/v2/*`;
- seluruh runtime code kelak wajib membaca `config()`, bukan `env()` langsung.

### Environment example

`.env.example` dan `.env.production.example` sekarang mendokumentasikan seluruh
variable `BSRE_ESIGN_*` dengan:

- credential kosong;
- integration kill switch nonaktif;
- TLS verification aktif;
- endpoint API v2;
- tidak ada variable credential berawalan `VITE_`.

Tidak ada credential yang ditambahkan ke `.env` lokal.

### Komponen Blade eSign legacy aktif

Input NIK dan passphrase pada
`resources/views/components/esign/esign.blade.php` diberi browser hardening:

- autocomplete dinonaktifkan;
- input NIK memakai numeric input mode;
- autocapitalization dan spellcheck passphrase dinonaktifkan.

Perubahan ini hanya containment sementara. Komponen dan bundle legacy tetap
harus digantikan modal Svelte baru sesuai rencana frontend.

## 5. Tindakan eksternal yang masih wajib

Pemilik akun/credential BSrE harus:

1. mengidentifikasi apakah Basic Auth/default credential dari project lama
   masih valid;
2. revoke/rotate Basic Auth tersebut;
3. meminta pemilik sertifikat mengganti/revoke passphrase yang pernah tertanam
   di controller lama bila credential masih berlaku;
4. menerbitkan credential sandbox baru yang terpisah dari production;
5. memasukkan secret melalui secret store/deployment environment, bukan source
   atau pesan/chat;
6. meninjau log, backup, config cache, deployment artifact, dan Git repository
   project lama pada seluruh server/operator, termasuk file log dan config
   recovery yang teridentifikasi pada audit ini;
7. memutuskan bersama security/repository owner apakah Git history project lama
   perlu dibersihkan setelah rotasi selesai;
8. memberikan bukti administratif rotasi tanpa mencantumkan nilai credential.

## 6. Exit criteria Phase 0

Phase 0 baru berstatus **complete** setelah seluruh butir berikut terbukti:

- credential lama telah revoke/rotate oleh pemilik;
- credential sandbox baru tersedia melalui secret store;
- safe read-only status check berhasil memakai credential baru;
- deployment artifact dan process terkait sudah memakai config baru;
- scan ulang source, log, cache, queue, session, backup yang berada dalam scope
  menghasilkan tidak ada material credential;
- keputusan Git history cleanup tercatat.

Sampai bukti eksternal tersedia, `BSRE_ESIGN_ENABLED` harus tetap `false` dan
Phase 1 tidak boleh memakai credential lama.

## 7. Verifikasi perubahan lokal

Verifikasi yang dijalankan setelah perubahan:

- `php -l config/services.php`: lulus;
- `vendor/bin/pint --dirty --format agent`: lulus;
- `php artisan config:show services.bsre_esign`: konfigurasi dapat dimuat,
  `enabled=false`, TLS verification aktif, timeout terbaca, dan endpoint hanya
  `/api/v2/*`; nilai field credential disembunyikan dari laporan;
- pemeriksaan fail-closed: base URL, username, dan password tidak mempunyai
  fallback;
- pemeriksaan variable frontend: tidak ada kandidat credential eSign dengan
  prefix `VITE_`;
- pemeriksaan log aktif: 0 keyword match;
- pemeriksaan input legacy: autocomplete NIK/passphrase nonaktif dan spellcheck
  passphrase nonaktif;
- `git diff --check` pada file yang disentuh: lulus.

Scan literal 16 digit lintas source menemukan lima entry yang sudah ada di
`database/seeders/AdminSuperSeeder.php`; semuanya berada di luar jalur eSign dan
tidak diubah dalam Phase 0 ini. Scan terarah file eSign aktif tidak menemukan
literal identitas 16 digit.

Test suite tidak dibuat atau dijalankan karena aturan project memerlukan izin
eksplisit pengguna. Tidak ada request ke endpoint BSrE selama verifikasi.
