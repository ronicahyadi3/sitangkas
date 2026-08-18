# Management Users Current State

Last updated: 2026-08-11.

Dokumen ini merangkum kondisi fitur Management Users saat ini, apa saja yang
sudah dibuat, rekomendasi pekerjaan berikutnya, dan isu yang perlu dibahas
sebelum fitur diperluas. Entry point domain ini adalah tabel `users`, tetapi
fiturnya juga menyentuh `user_positions`, dokumen SK, izin tahun historis,
MFA, session guard, dan audit autentikasi.

## Status

Status implementasi: active implementation.

Fitur sudah dipindahkan dari referensi project lama ke struktur Laravel saat
ini dan sudah dipisah menjadi beberapa lapis:

- route dan middleware authenticated;
- controller Management Users;
- Form Request untuk validasi;
- action/service untuk aksi keamanan akun;
- model dan relasi posisi/dokumen/izin tahun;
- Blade satu halaman dengan modal user, posisi, keamanan akun, dokumen SK, dan
  izin tahun historis.

## Entry Point Kode

Route utama:

- `GET /users` -> `users.index`;
- `GET /users/datatable` -> `users.datatable`;
- `POST /users` -> `users.store`;
- `PUT /users/{user}` -> `users.update`;
- `DELETE /users/{user}` -> `users.destroy`;
- `GET /users/{user}/security` -> `users.security.show`;
- `POST /users/{user}/security/force-password-change` ->
  `users.security.force-password-change`;
- `POST /users/{user}/security/lock` -> `users.security.lock`;
- `POST /users/{user}/security/unlock` -> `users.security.unlock`;
- `POST /users/{user}/security/reset-mfa` -> `users.security.reset-mfa`;
- `GET /users/{user}/positions` -> `users.positions.index`;
- `POST /users/{user}/positions` -> `users.positions.store`;
- `PUT /users/{user}/positions/{position}` -> `users.positions.update`;
- `POST /users/{user}/positions/{position}/activate` ->
  `users.positions.activate`;
- `POST /users/{user}/positions/{position}/deactivate` ->
  `users.positions.deactivate`;
- `POST /users/{user}/positions/{position}/historical-year-access/grant` ->
  `users.positions.year-access.grant`;
- `POST /users/{user}/positions/{position}/historical-year-access/revoke` ->
  `users.positions.year-access.revoke`.

File utama:

- `routes/web.php`;
- `bootstrap/app.php`;
- `app/Http/Controllers/Users/UserController.php`;
- `app/Http/Controllers/Users/UserPositionController.php`;
- `app/Http/Controllers/Users/UserSecurityController.php`;
- `app/Http/Requests/User/*`;
- `app/Actions/UserSecurity/*`;
- `app/Actions/Auth/ResetUserMfa.php`;
- `app/Services/User/*`;
- `resources/views/users/index.blade.php`.

Middleware terkait:

- `auth`;
- `account.accessible`;
- `single.device.session`;
- `has.position`;
- `mfa.verified`;
- `active.position`;
- `password.fresh`;
- `user.management`.

## Yang Sudah Dibuat

### Route, Namespace, Dan Dependency

- Route Management Users sudah berada pada group authenticated.
- Prefix route `users.*` sudah memakai middleware `user.management`.
- Route internal penting sudah memakai guard posisi, MFA, active position, dan
  password freshness.
- Package `yajra/laravel-datatables-oracle` sudah terpasang di Composer.
- Endpoint DataTables users saat ini masih memakai response manual
  server-side pada `UserController@datatable`; belum memakai builder Yajra.

### Form Akun

Form akun sudah mengisi field penting pada `users`:

- `nik`;
- `nip`;
- `nama`;
- `email`;
- `account_type`;
- `status`;
- `status_reason`;
- `tahun_aktif`;
- `password`.

Catatan:

- akun baru dibuat tanpa posisi awal;
- setelah create sukses, UI membuka flow tambah posisi;
- akun baru ditandai `must_change_password = true`;
- status audit ringan mengisi `status_changed_at` dan
  `status_changed_by_user_id` saat status berubah;
- audit actor create/update/delete memakai kolom `created_by_user_id`,
  `updated_by_user_id`, dan `deleted_by_user_id` melalui mekanisme model yang
  sudah ada.

### Flow User Dan Posisi

- Flow resmi adalah buat akun terlebih dahulu, lalu tambah posisi.
- Satu user boleh memiliki lebih dari satu posisi.
- Posisi aktif berarti posisi tersedia untuk dipilih, bukan berarti sedang
  dipakai pada session.
- Pemilihan posisi saat login atau switch context disimpan di session.
- User tanpa posisi aktif diarahkan ke halaman full-screen
  `login.no_active_position`.
- Middleware `has.position` menjaga user tanpa posisi agar tidak masuk dashboard
  atau modul internal.
- PA/KPA hanya dapat mengelola user dalam scope posisi yang dapat mereka kelola.
- Admin Super dapat mengelola scope global melalui acting context.

### Form Posisi

Form posisi sudah mendukung:

- `jabatan_id`;
- `instansi_id`;
- `unit_kerja_id`;
- tanggal mulai/akhir;
- status aktif posisi;
- `notes`;
- metadata dokumen SK;
- upload file SK.

Metadata dokumen SK yang sudah tersedia:

- `document_type`;
- `document_number`;
- `document_date`;
- `issued_by`;
- file upload SK.

Keputusan saat ini:

- dokumen SK hanya upload dan metadata;
- belum ada workflow verifikasi dokumen SK.

### Nonaktifkan Posisi

- Flow nonaktif posisi sudah memakai modal alasan.
- Field `deactivation_reason` wajib saat posisi dinonaktifkan.
- `ended_at`, actor nonaktif, dan timestamp nonaktif dikelola pada action
  controller.
- Untuk posisi yang tidak lagi berlaku, nonaktifkan posisi lebih disarankan
  daripada hard delete.

### Izin Tahun Historis

Flow grant/revoke izin tahun historis sudah tersedia pada posisi.

Grant mendukung metadata:

- `reason`;
- `reference_number`;
- `reference_date`;
- `valid_until`;
- `grant_notes`.

Revoke mendukung alasan pencabutan.

Aturan tetap:

- izin melekat pada `user_position_id`;
- izin tahun historis tidak mengganti role dasar;
- event permission tetap berada pada cluster `04-year-permissions`.

### Keamanan Akun

Modal keamanan akun sudah tersedia dengan data status akun, password, MFA, dan
aktivitas login.

Aksi yang sudah tersedia:

- force change password;
- lock account;
- unlock account;
- reset MFA.

Force change password:

- mengisi `must_change_password = true`;
- mengisi `password_reset_at` dan `password_reset_by_user_id`;
- merotasi remember token;
- mengisi `sessions_invalidated_at`;
- mencabut database session target bila session driver mendukung;
- mencatat audit `password_change_forced` pada `login_events`;
- middleware `password.fresh` memaksa user mengganti password sebelum lanjut.

Lock/unlock:

- lock hanya boleh untuk akun aktif yang belum terkunci;
- unlock hanya membuka akun yang memang terkunci;
- lock/unlock tidak boleh dijalankan untuk akun sendiri;
- session target dibatalkan;
- audit `account_locked` dan `account_unlocked` dicatat pada `login_events`;
- middleware `account.accessible` memutus session user yang status akunnya
  locked, inactive, pending, suspended, atau service account web login denied.

Reset MFA:

- hanya Admin Super yang boleh menjalankan reset MFA dari Management Users;
- target harus punya MFA aktif atau pending setup;
- action menghapus secret, pending secret, recovery codes, dan timestamp MFA;
- remember token dirotasi dan session target dicabut;
- audit `mfa_reset` dicatat pada `login_events`;
- command operator `php artisan auth:mfa-reset` tetap tersedia untuk jalur
  teknis/server.

### Audit Dan Log

Keputusan saat ini:

- dedicated audit table untuk Management Users ditunda;
- audit keamanan akun dicatat pada `login_events`;
- error teknis dicatat melalui channel `module_users`;
- audit ringan akun/posisi memakai kolom actor di tabel masing-masing;
- reason penting disimpan pada field domain seperti `status_reason`,
  `deactivation_reason`, metadata izin historis, dan metadata dokumen.

Data yang tidak boleh masuk audit/log:

- password mentah;
- secret MFA;
- kode OTP;
- recovery code mentah;
- remember token/session id mentah;
- isi file SK;
- payload request yang tidak diperlukan.

## Rekomendasi Prioritas Berikutnya

### 1. Test Authorization Dan Guard

Ini prioritas paling penting sebelum menambah fitur besar.

Test awal yang direkomendasikan:

- Admin Super dapat membuka Management Users;
- user biasa tidak dapat membuka Management Users;
- PA/KPA tidak dapat mengelola user di luar scope;
- PA/KPA tidak dapat reset MFA;
- Admin Super dapat reset MFA user yang punya MFA;
- reset MFA ditolak bila target belum punya MFA aktif/pending;
- user locked tidak bisa login atau lanjut ke dashboard;
- user tanpa posisi diarahkan ke halaman no-active-position;
- user dengan `must_change_password = true` diarahkan ke halaman ganti password.

### 2. Rapikan Status Locked Di Form Akun

Saat ini status `locked` masih tersedia sebagai status akun umum. Ini perlu
diputuskan ulang karena lock yang proper harus lewat flow keamanan akun agar:

- session target dibatalkan;
- remember token dirotasi;
- audit `login_events` tercatat;
- reason dan lock metadata konsisten.

Rekomendasi:

- hapus opsi `locked` dari form create/update user biasa; atau
- jika tetap ditampilkan, arahkan perubahan status locked melalui action
  `LockUserAccount`.

### 3. Migrasi Endpoint Users DataTable Ke Yajra

Package Yajra sudah terpasang, tetapi endpoint users masih manual.

Rekomendasi:

- pindahkan `UserController@datatable` ke Yajra DataTables bila query dan
  payload sudah stabil;
- pertahankan scope PA/KPA melalui `UserManagementAccessService`;
- pastikan search dan count tetap efisien;
- hindari HTML berat di controller bila nanti ingin response lebih bersih.

### 4. Tambahkan Test Minimal Untuk Posisi Dan Izin Tahun

Skenario prioritas:

- user dapat punya dua posisi aktif;
- switch posisi tidak menonaktifkan posisi lain;
- PA/KPA hanya bisa membuat posisi dalam scope;
- deactivation wajib alasan;
- grant izin historis wajib metadata dasar;
- revoke izin historis membuat akses tulis tidak berlaku.

### 5. Review Upload SK

Karena keputusan saat ini hanya upload, review minimal yang tetap penting:

- validasi MIME dan ukuran file;
- storage disk production;
- akses file hanya untuk user yang punya scope;
- `php artisan storage:link` atau strategi private download;
- kebijakan replace dokumen lama dan versi dokumen.

### 6. Konsolidasi Dokumentasi MFA

Dokumen lama masih banyak berangkat dari fase command-only reset MFA. Setelah
UI Management Users stabil, pastikan semua dokumen MFA menyebut dua jalur resmi:

- Management Users untuk Admin Super;
- command `auth:mfa-reset` untuk operator/server.

### 7. Pertimbangkan Dedicated Audit Table Nanti

Dedicated table seperti `user_management_audit_events` tidak perlu dibuat pada
tahap sekarang.

Buat hanya jika muncul kebutuhan:

- laporan audit formal;
- investigasi perubahan data lintas admin;
- compliance/inspektorat;
- histori detail perubahan posisi dan dokumen.

## Issue Yang Perlu Dibahas Kedepannya

1. Apakah PA/KPA boleh lock/unlock akun dalam scope mereka, atau hanya Admin
   Super?
2. Apakah reset MFA harus tetap Admin Super only, atau ada workflow approval
   untuk operator tertentu?
3. Apakah status `locked` perlu dihapus dari form akun umum?
4. Apakah non-Admin Super nanti wajib MFA global?
5. Apakah dokumen SK cukup upload saja atau perlu verifikasi dokumen?
6. Apakah file SK harus public disk atau private download terotorisasi?
7. Apakah DataTables users perlu segera dimigrasikan ke Yajra atau tunggu
   setelah test authorization selesai?
8. Apakah user dengan posisi campuran lintas scope boleh diedit profil globalnya
   oleh PA/KPA? Implementasi saat ini cenderung menolak bila tidak semua posisi
   user berada dalam scope actor.
9. Apakah dedicated audit table perlu dibuat sebelum produksi atau cukup
   `login_events`, actor columns, reason fields, dan `module_users` log?
10. Apakah perlu halaman riwayat keamanan/user activity di modal Management
    Users, atau cukup audit di database/log untuk admin teknis?

## Hal Yang Jangan Diubah Tanpa Diskusi

- Jangan membuat posisi awal otomatis saat create user.
- Jangan menganggap hanya satu posisi yang boleh aktif.
- Jangan memakai `users.tahun_aktif` sebagai bukti authorization tahun.
- Jangan memberi reset MFA kepada PA/KPA tanpa decision baru.
- Jangan menjadikan remember-me sebagai pengganti MFA.
- Jangan menyimpan secret, password, token, atau recovery code mentah di log.
- Jangan membuat dedicated audit table hanya karena terlihat rapi; tunggu
  kebutuhan nyata.

## Related Docs

- `README.md`
- `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`
- `USERS_TABLE.md`
- `LOGIN_EVENTS_TABLE.md`
- `MFA_DECISIONS.md`
- `MFA_RESET_COMMAND_RUNBOOK.md`
- `../03-user-positions/README.md`
- `../04-year-permissions/README.md`
- `../05-relationships/README.md`
