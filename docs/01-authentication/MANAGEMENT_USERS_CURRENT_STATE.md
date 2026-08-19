# Management Users Current State

Last updated: 2026-08-18.

Dokumen ini merangkum kondisi fitur Management Users saat ini, apa saja yang
sudah dibuat, rekomendasi pekerjaan berikutnya, dan isu yang perlu dibahas
sebelum fitur diperluas. Entry point domain ini adalah tabel `users`, tetapi
fiturnya juga menyentuh `user_positions`, dokumen SK, izin tahun historis,
MFA, session guard, audit autentikasi, dan audit administrasi Management Users.

## Status

Status implementasi: active implementation.

Fitur sudah dipindahkan dari referensi project lama ke struktur Laravel saat
ini dan sudah dipisah menjadi beberapa lapis:

- route dan middleware authenticated;
- controller Management Users;
- Form Request untuk validasi;
- action layer untuk create/update/delete user, posisi, dan izin tahun historis;
- action/service untuk aksi keamanan akun;
- model dan relasi posisi/dokumen/izin tahun;
- dedicated audit table dan service logger Management Users;
- Blade satu halaman dengan modal user, posisi, keamanan akun, dokumen SK, dan
  izin tahun historis.

## Entry Point Kode

Route utama:

- `GET /users` -> `users.index`;
- `GET /users/datatable` -> `users.datatable`;
- `GET /users/audit-trail` -> `users.audit-trail`;
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
- `GET /users/{user}/positions/{position}` -> `users.positions.show`;
- `PUT /users/{user}/positions/{position}` -> `users.positions.update`;
- `DELETE /users/{user}/positions/{position}` -> `users.positions.destroy`;
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
- `app/Http/Controllers/Users/UserManagementAuditController.php`;
- `app/Http/Requests/User/*`;
- `app/Models/User.php`;
- `app/Models/UserPosition.php`;
- `app/Actions/UserManagement/*`;
- `app/Actions/UserSecurity/*`;
- `app/Actions/Auth/ResetUserMfa.php`;
- `app/Services/User/*`;
- `app/Support/UserManagement/UserDatatablePresenter.php`;
- `app/Models/UserManagementAuditEvent.php`;
- `database/migrations/2026_08_12_025830_create_user_management_audit_events_table.php`;
- `database/migrations/2026_08_18_044639_add_management_user_filter_indexes.php`;
- `resources/views/users/index.blade.php`;
- `resources/views/users/audit-trail.blade.php`.

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
- Route binding model `User` dan `UserPosition` sudah memakai encrypted route
  key melalui `getRouteKey()` dan `resolveRouteBinding()`. URL numeric polos
  untuk parameter `{user}` dan `{position}` ditolak pada binding ini.
- Package `yajra/laravel-datatables-oracle` sudah terpasang di Composer.
- Endpoint DataTables users sudah memakai Yajra pada
  `UserController@datatable`, dengan scope PA/KPA tetap melalui
  `UserManagementAccessService`.
- Endpoint DataTables users sudah mendukung filter server-side untuk
  `status`, `account_type`, `jabatan_id`, `instansi_id`, `unit_kerja_id`, dan
  `position_state`.
- HTML kolom dan payload tombol DataTables users sudah dipindahkan ke
  `App\Support\UserManagement\UserDatatablePresenter`, sehingga controller
  fokus pada query, filter, dan response.
- Halaman Management Users sudah memiliki panel filter di atas tabel. Filter
  instansi dan unit kerja mengikuti pilihan jabatan melalui endpoint AJAX scope
  `users-management`.
- Index database pendukung filter Management Users sudah ditambahkan melalui
  migration `2026_08_18_044639_add_management_user_filter_indexes.php`.

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
- opsi status `locked` tidak tersedia di form akun umum. Lock/unlock akun harus
  dilakukan melalui modal Keamanan Akun agar session, reason, dan audit tetap
  konsisten;
- status audit ringan mengisi `status_changed_at` dan
  `status_changed_by_user_id` saat status berubah;
- audit actor create/update/delete memakai kolom `created_by_user_id`,
  `updated_by_user_id`, dan `deleted_by_user_id` melalui mekanisme model yang
  sudah ada.

### Flow User Dan Posisi

- Flow resmi adalah buat akun terlebih dahulu, lalu tambah posisi.
- Create user tidak membuat posisi awal secara otomatis.
- Setelah create user sukses melalui AJAX, UI mengirim
  `prompt_position_setup = true` untuk membuka modal tambah posisi.
- Posisi pertama untuk user baru dapat dibuat oleh Admin Super, PA, atau KPA
  selama kombinasi `jabatan_id`, `instansi_id`, dan `unit_kerja_id` yang akan
  ditempel berada dalam scope kewenangan aktor.
- Authorization create posisi memakai
  `UserManagementAccessService::canAttachPositionToUser()`. Method ini
  memberi jalur khusus untuk user yang belum punya posisi, tetapi tetap
  memvalidasi scope posisi yang dipilih.
- Audit create posisi menyimpan metadata `is_initial_position` agar laporan
  dapat membedakan posisi pertama dan posisi tambahan.
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

- hanya Admin Super yang boleh menjalankan lock/unlock akun dari Management
  Users;
- PA/KPA tidak boleh lock/unlock akun user lain walaupun target berada dalam
  scope posisi mereka;
- lock hanya boleh untuk akun aktif yang belum terkunci;
- unlock hanya membuka akun yang memang terkunci;
- lock/unlock tidak boleh dijalankan untuk akun sendiri;
- session target dibatalkan;
- audit `account_locked` dan `account_unlocked` dicatat pada `login_events`;
- middleware `account.accessible` memutus session user yang status akunnya
  locked, inactive, pending, suspended, atau service account web login denied.

Reset MFA:

- hanya Admin Super yang boleh menjalankan reset MFA dari Management Users;
- PA/KPA tidak boleh reset MFA user lain;
- non-Admin Super tetap boleh enroll atau mengaktifkan MFA untuk dirinya
  sendiri sesuai policy optional;
- target harus punya MFA aktif atau pending setup;
- action menghapus secret, pending secret, recovery codes, dan timestamp MFA;
- remember token dirotasi dan session target dicabut;
- audit `mfa_reset` dicatat pada `login_events`;
- command operator `php artisan auth:mfa-reset` tetap tersedia untuk jalur
  teknis/server.

### Audit Dan Log

Dedicated audit table Management Users sudah dibuat:

- tabel `user_management_audit_events`;
- model `App\Models\UserManagementAuditEvent`;
- service logger `App\Services\User\UserManagementAuditLogger`;
- halaman audit trail `users.audit-trail`.

Audit Management Users saat ini mencatat event utama:

- create/update/delete user;
- create/update/activate/deactivate/delete posisi;
- grant/revoke izin tahun historis;
- force change password;
- lock/unlock akun;
- reset MFA;
- authorization denial penting sebagai result `blocked`.

Audit `blocked` saat ini diterapkan pada:

- akses module Management Users oleh jabatan yang tidak berwenang;
- akses opsi AJAX Management Users oleh jabatan yang tidak berwenang;
- update/delete user di luar scope;
- lihat/create/update/activate/deactivate/delete posisi di luar scope;
- nested URL posisi yang tidak dimiliki user target;
- grant/revoke izin tahun historis oleh non-Admin Super;
- force change password, lock/unlock, dan reset MFA yang ditolak oleh
  authorization FormRequest.

Data audit yang tersedia:

- aktor user dan posisi aktor;
- target user dan target posisi;
- event type, result, resource, reason, dan message;
- snapshot `before_state` dan `after_state`;
- metadata operasional, termasuk `is_initial_position` untuk create posisi;
- request context seperti route, path, method, HTTP status, IP, user agent,
  request id, dan correlation id.

Catatan:

- audit keamanan akun tetap juga dicatat pada `login_events` untuk kebutuhan
  histori autentikasi;
- error teknis tetap dicatat melalui channel `module_users`;
- kolom actor seperti `created_by_user_id`, `updated_by_user_id`, dan
  `deleted_by_user_id` tetap dipakai sebagai ringkasan aktor terakhir pada
  tabel domain;
- reason penting tetap disimpan pada field domain seperti `status_reason`,
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
- PA/KPA dapat membuat posisi pertama untuk user baru hanya jika posisi yang
  dibuat berada dalam scope mereka;
- PA/KPA ditolak saat membuat posisi pertama di luar scope;
- PA/KPA tidak dapat lock/unlock akun;
- PA/KPA tidak dapat reset MFA;
- Admin Super dapat reset MFA user yang punya MFA;
- reset MFA ditolak bila target belum punya MFA aktif/pending;
- user locked tidak bisa login atau lanjut ke dashboard;
- user tanpa posisi diarahkan ke halaman no-active-position;
- user dengan `must_change_password = true` diarahkan ke halaman ganti password.

### 2. Tambahkan Test Minimal Untuk Posisi Dan Izin Tahun

Skenario prioritas:

- user baru tanpa posisi dapat diberi posisi pertama oleh aktor yang berwenang;
- audit create posisi pertama menyimpan `is_initial_position = true`;
- audit create posisi tambahan menyimpan `is_initial_position = false`;
- user dapat punya dua posisi aktif;
- switch posisi tidak menonaktifkan posisi lain;
- PA/KPA hanya bisa membuat posisi dalam scope;
- deactivation wajib alasan;
- grant izin historis wajib metadata dasar;
- revoke izin historis membuat akses tulis tidak berlaku.

### 3. Review Upload SK

Karena keputusan saat ini hanya upload, review minimal yang tetap penting:

- validasi MIME dan ukuran file;
- storage disk production;
- akses file hanya untuk user yang punya scope;
- `php artisan storage:link` atau strategi private download;
- kebijakan replace dokumen lama dan versi dokumen.

### 4. Konsolidasi SOP Keamanan Akun

Keputusan teknis sudah jelas:

- lock/unlock akun dari Management Users adalah Admin Super only;
- reset MFA dari Management Users adalah Admin Super only;
- PA/KPA tidak boleh menjalankan lock/unlock atau reset MFA user lain;
- command `auth:mfa-reset` tetap jalur operator/server sesuai SOP environment.

Tahap berikutnya adalah menyamakan SOP operasional, approval internal, dan
kalimat bantuan UI agar tidak ada admin umum yang memakai jalur reset di luar
desain.

### 5. Retention Audit Management Users

Audit `blocked` penting sudah mulai dicatat. Retention belum dibuat sebagai job
purge otomatis.

Rancangan retention yang direkomendasikan:

- simpan audit Management Users minimal 5 tahun;
- simpan event keamanan kritis 7 tahun bila dibutuhkan kebijakan organisasi;
- event kritis mencakup lock/unlock, force change password, reset MFA,
  delete user, delete/nonaktif posisi, dan grant/revoke izin historis;
- jangan membuat auto purge sebelum ada SOP arsip, backup, dan approval
  administrasi;
- gunakan kolom `retention_until` untuk menandai tanggal retensi, bukan langsung
  menghapus data.

## Issue Yang Perlu Dibahas Kedepannya

1. Apakah non-Admin Super nanti wajib MFA global?
2. Apakah dokumen SK cukup upload saja atau perlu verifikasi dokumen?
3. Apakah file SK harus public disk atau private download terotorisasi?
4. Apakah user dengan posisi campuran lintas scope boleh diedit profil globalnya
   oleh PA/KPA? Implementasi saat ini cenderung menolak bila tidak semua posisi
   user berada dalam scope actor.
5. Apakah retention audit Management Users memakai 5 tahun atau 7 tahun untuk
   semua event kritis?
6. Apakah perlu halaman riwayat keamanan/user activity di modal Management
   Users, atau cukup audit di database/log untuk admin teknis?

## Hal Yang Jangan Diubah Tanpa Diskusi

- Jangan membuat posisi awal otomatis saat create user.
- Jangan bypass `canAttachPositionToUser()` ketika membuat posisi pertama user
  baru.
- Jangan menganggap hanya satu posisi yang boleh aktif.
- Jangan memakai `users.tahun_aktif` sebagai bukti authorization tahun.
- Jangan memberi lock/unlock akun kepada PA/KPA tanpa decision baru.
- Jangan memberi reset MFA kepada PA/KPA tanpa decision baru.
- Jangan menjadikan remember-me sebagai pengganti MFA.
- Jangan menyimpan secret, password, token, atau recovery code mentah di log.
- Jangan menyimpan isi file SK di audit/log.

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
