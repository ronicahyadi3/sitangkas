# Auth Context Decisions

Dokumen ini adalah keputusan arsitektur wajib untuk fitur login context SITANGKAS.
AI agent harus membaca dokumen ini sebelum mengubah login, post-login,
dashboard, navbar, middleware context, atau service context pengguna.

## Status keputusan

Status: accepted.

Alasan dibuat: project baru memakai `user_positions` sebagai posisi nyata user,
sementara project lama mempunyai halaman `auth/postLogin.blade.php` untuk Admin
Super memilih konteks operasional secara manual. Dua kebutuhan ini harus hidup
bersamaan tanpa saling mencampur.

## Ringkasan cepat untuk AI agent

| Konsep | Route utama | Data utama | Untuk siapa |
|---|---|---|---|
| Real User Position | `/positions` | `user_positions.id` | Semua user yang memilih posisi nyata |
| Admin Super Acting Context | `login.post` | session `acting_*` tervalidasi | Hanya Admin Super dengan real position `ADMIN_SUPER` |
| Effective Context | service context | real position atau acting context | Semua modul internal |

Aturan paling penting:

- `/positions` tidak boleh dipakai untuk form manual jabatan/instansi/unit kerja.
- `login.post` bukan file legacy mati; ini halaman resmi Admin Super acting context setelah direfaktor.
- Acting context tidak boleh dibuat menjadi record permanen di `user_positions`.
- Modul tidak boleh membaca `session('acting_*')` langsung.
- `remember me` hanya berlaku untuk real active position non-Admin Super dan
  harus tunduk pada kebijakan single-device dan expiry configurable.
- MFA memakai TOTP kompatibel Google Authenticator. MFA wajib untuk real active
  position Admin Super dan optional/enrollable untuk non-Admin Super. Baca
  `MFA_DECISIONS.md` sebelum mengubah step-up authentication.

## Istilah resmi

### Real User Position

Real User Position adalah baris nyata pada tabel `user_positions`.

Karakteristik:

- dimiliki oleh `users.id`;
- disimpan di session sebagai `active_user_position_id`;
- menunjuk `jabatan_id`, `instansi_id`, dan `unit_kerja_id`;
- dipakai untuk mengetahui posisi asli user yang sedang aktif;
- dipilih melalui `GET/POST /positions`.
- route lama `GET /login/context` hanya legacy/compatibility redirect ke
  `/positions`.

Real User Position adalah sumber kebenaran untuk kepemilikan posisi user.

### Admin Super Acting Context

Admin Super Acting Context adalah konteks operasional sementara yang dipilih
manual oleh Admin Super setelah real active position-nya adalah `ADMIN_SUPER`.

Karakteristik:

- hanya boleh ada jika real active position adalah `ADMIN_SUPER`;
- disimpan sementara di session sebagai `acting_*`;
- bukan record permanen di `user_positions`;
- dipilih melalui `GET/POST /login/post`;
- harus divalidasi server-side terhadap master data dan `position_rules`;
- dapat berisi special user position untuk PPTK/BUD.

Acting context menjawab pertanyaan: "Admin Super sedang bekerja sebagai konteks apa?"

### Effective Context

Effective Context adalah konteks yang harus dipakai modul saat memfilter data,
menampilkan navbar/dashboard, dan menjalankan authorization operasional.

Aturan:

- untuk user biasa, effective context sama dengan Real User Position;
- untuk Admin Super tanpa acting context lengkap, modul internal harus redirect
  ke `login.post`;
- untuk Admin Super dengan acting context lengkap, effective context berasal dari
  acting context;
- audit harus tetap bisa mengetahui real position dan effective context.

## Decision 1 - Pisahkan Real Position dan Acting Context

Keputusan:

`UserPosition` dan Admin Super acting context adalah dua hal yang berbeda.

`UserPosition` adalah posisi nyata milik user. Admin Super acting context adalah
overlay session sementara yang hanya berlaku selama sesi kerja Admin Super.

AI agent tidak boleh:

- membuat baris `user_positions` baru setiap kali Admin Super memilih acting context;
- menganggap `acting_jabatan_id` sebagai posisi nyata milik user;
- menaruh semua pilihan manual Admin Super ke tabel `user_positions`;
- menghapus konsep `auth/postLogin.blade.php` hanya karena sudah ada `/positions`.

Konsekuensi:

- tabel `user_positions` tetap bersih dan hanya berisi posisi nyata;
- Admin Super tetap bisa memilih kombinasi jabatan/instansi/unit kerja luas tanpa
  mengotori data posisi permanen;
- service context harus mampu membedakan real position dan acting context.

Khusus delivery PDF, keputusan pengguna final menetapkan Admin Super dalam
acting context selalu efektif `pdf_watermark_required=false`. Ini hanya memilih
rendition original setelah document Policy lulus; bukan bypass scope, bukan hak
akses global, dan tidak mengubah certificate owner. Jika Admin Super memilih
posisi bisnis nyata miliknya, gunakan nilai flag pada row posisi nyata. Modul
tidak boleh mengambil keputusan ini dengan membaca `session('acting_*')`
langsung; resolver delivery harus memakai `CurrentUserContext`. Kontrak lengkap
berada di `../08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md` dan masih
berstatus belum diimplementasikan.

## Decision 2 - `/positions` hanya untuk memilih posisi nyata

Keputusan:

Route `/positions` digunakan hanya untuk memilih Real User Position.

Route yang dimaksud:

- `GET /positions` dengan name `positions.index`;
- `POST /positions` dengan name `positions.store`;
- `GET /login/context` dengan name `login.context.legacy` hanya untuk legacy
  redirect/compatibility.

Payload yang benar:

- `user_position_id`.

Payload yang tidak boleh dipakai di route ini:

- `jabatan_id`;
- `instansi_id`;
- `unit_kerja_id`;
- `pptk_id`;
- `bud_id`;
- field manual context lain.

Aturan behavior:

- route ini boleh dipakai semua user authenticated;
- pilihan harus berasal dari `user_positions` milik user tersebut;
- posisi harus aktif, efektif, tidak soft-deleted, dan memiliki relasi master aktif;
- jika user memilih posisi nyata `ADMIN_SUPER`, setelah submit harus diarahkan
  ke `login.post`;
- jika user memilih posisi nyata non-Admin Super, acting context lama harus
  dihapus dan user diarahkan ke dashboard.

AI agent tidak boleh menggabungkan form manual Admin Super ke `positions.store`.

## Decision 3 - `login.post` khusus Admin Super manual context

Keputusan:

Route `login.post` adalah route khusus untuk Admin Super memilih acting context
manual.

Route yang direkomendasikan:

- `GET /login/post` dengan name `login.post`;
- `POST /login/post` dengan name `login.post.store`;
- endpoint options khusus di bawah `/login/post/options/*`.

File view resmi:

- `resources/views/auth/postLogin.blade.php`.

File ini dipertahankan, tetapi harus direfaktor agar tidak bergantung pada route
AJAX legacy yang belum ada di project baru.

Syarat akses:

- user harus authenticated;
- session harus punya real active position;
- real active position harus jabatan `ADMIN_SUPER`;
- user yang real active position-nya bukan Admin Super harus diarahkan ke
  dashboard atau ditolak 403 untuk JSON/AJAX.

Payload yang benar untuk `login.post.store`:

- `jabatan_id`;
- `instansi_id`;
- `unit_kerja_id`;
- `pptk_user_position_id` bila acting role adalah PPTK;
- `bud_user_position_id` bila acting role adalah BUD atau Kuasa BUD.

Catatan penting:

- ID boleh dienkripsi atau plain integer, tetapi controller/request harus
  konsisten dan validasi server-side wajib menjadi sumber kebenaran.
- Untuk keamanan dan traceability, special user harus menunjuk
  `user_positions.id`, bukan langsung `users.id`.

## Decision 4 - Redirect setelah login

Keputusan:

Setelah login berhasil, aplikasi memilih Real User Position terlebih dahulu.
Keputusan redirect ditentukan oleh posisi nyata yang terpilih.

Flow resmi:

1. User submit login.
2. Sistem memvalidasi credential, status akun, lock, rate limit, dan CAPTCHA bila aktif.
3. Sistem memilih Real User Position dengan prioritas `last_used_at` terbaru.
4. Session diisi dengan `active_user_position_id` dan `tahun_aktif`.
5. Jika real active position adalah `ADMIN_SUPER`, user diarahkan ke `login.post`.
6. Jika real active position bukan `ADMIN_SUPER`, user diarahkan ke dashboard.

Konsekuensi penting:

- Admin Super yang posisi terakhirnya Admin Super wajib memilih acting context
  setelah fresh login;
- Admin Super yang posisi terakhirnya posisi non-Admin Super tidak perlu masuk
  `login.post`;
- user dengan banyak posisi tetap bisa memilih posisi nyata melalui
  `/positions`;
- ketika posisi nyata diganti ke Admin Super, user juga harus diarahkan ke
  `login.post`.

AI agent tidak boleh menentukan redirect hanya dari `users.account_type`,
`users.nik`, atau nama user. Redirect harus berdasarkan real active
`UserPosition`.

## Decision 6 - Semua modul membaca context dari service

Keputusan:

Dashboard, navbar, middleware, controller modul, policy, dan query operasional
tidak boleh membaca `session('acting_*')` langsung.

Semua pembacaan context harus melalui service context, saat ini:

- `App\Services\Auth\CurrentUserContext`.

Service ini harus berkembang untuk menyediakan minimal:

- real active position;
- apakah real active position adalah Admin Super;
- apakah acting context Admin Super lengkap;
- acting context Admin Super;
- effective context yang harus dipakai modul.

Nama method yang direkomendasikan:

- `realActivePosition(Request $request): ?UserPosition`;
- `activePosition(Request $request): ?UserPosition` untuk effective context;
- `isRealActivePositionAdminSuper(Request $request): bool`;
- `hasCompleteAdminSuperActingContext(Request $request): bool`;
- `adminSuperActingContextData(Request $request): ?array`;
- `effectiveContextIsActing(Request $request): bool`;
- `forgetAdminSuperActingContext(Request $request): void`.

AI agent boleh menyesuaikan nama method jika ada naming yang lebih proper, tetapi
kontrak fungsional di atas tidak boleh hilang.

Aturan konsumsi:

- navbar menampilkan real login user dan effective role;
- dashboard memakai effective context;
- audit mencatat real position dan acting/effective context;
- module query memakai effective context setelah middleware memastikan context
  lengkap.

Catatan implementasi saat ini:

- `activePosition()` sengaja berarti effective context, bukan selalu real
  `user_positions`;
- untuk Admin Super acting context, `activePosition()->id` tetap id real
  Admin Super, sedangkan `jabatan_id`, `instansi_id`, dan `unit_kerja_id`
  berasal dari pilihan `login.post`;
- untuk membaca posisi asli user, gunakan `realActivePosition()`.

Larangan:

- jangan membuat helper global untuk membaca `acting_*`;
- jangan menyebarkan akses `session('acting_jabatan_id')` ke banyak controller;
- jangan menyimpan acting context di request tanpa sumber dari service resmi.

## Decision 10 - Clear acting context saat real position berubah

Keputusan:

Acting context hanya valid selama real active position tetap Admin Super.
Jika real active position berubah, acting context harus dibersihkan.

Rules:

- pindah dari Admin Super ke non-Admin Super: hapus semua `acting_*`;
- pindah dari non-Admin Super ke Admin Super: hapus acting context lama lalu
  redirect ke `login.post`;
- logout: hapus acting context;
- invalid active position: hapus real active position dan acting context;
- fresh login dengan real active position Admin Super: jangan reuse acting context
  lama, minta user memilih ulang di `login.post`.

Session keys yang direkomendasikan untuk acting context:

- `acting_jabatan_id`;
- `acting_jabatan_name`;
- `acting_instansi_id`;
- `acting_unit_kerja_id`;
- `acting_pptk_user_position_id`;
- `acting_bud_user_position_id`;
- `acting_selected_at`.

Jika project masih memiliki key lama seperti `acting_pptk_user_id` atau
`acting_bud_user_id`, AI agent harus memigrasikan pemakaiannya ke nama yang
menjelaskan bahwa nilai tersebut adalah `user_positions.id`.

## Decision 12 - Aturan implementasi untuk AI agent

AI agent wajib membaca dokumen ini sebelum mengubah file atau fitur berikut:

- `app/Actions/Auth/AuthenticateSession.php`;
- `app/Http/Controllers/Auth/*Session*`;
- `app/Http/Controllers/Auth/*Context*`;
- `app/Http/Middleware/*Context*`;
- `app/Http/Middleware/EnsureActiveUserPosition.php`;
- `app/Services/Auth/CurrentUserContext.php`;
- `resources/views/auth/postLogin.blade.php`;
- `resources/views/users/positions-switch.blade.php`;
- `resources/views/dashboard/*`;
- `resources/views/inc/navbar.blade.php`;
- `routes/web.php` untuk route login/context/post-login.

AI agent tidak boleh:

- menghapus `auth/postLogin.blade.php` sebagai file legacy tanpa keputusan baru;
- mengganti `login.post` menjadi alias `/positions`;
- membuat `positions.store` menerima form manual Admin Super;
- membaca session `acting_*` langsung dari modul bisnis;
- membuat special user menunjuk langsung ke `users.id`;
- menyimpan password, token, session id mentah, atau secret di audit metadata;
- membuat, memodifikasi, atau menjalankan test suite tanpa konfirmasi eksplisit user.

AI agent harus:

- memisahkan Real User Position dan Acting Context;
- menjaga validasi server-side;
- memakai service context resmi;
- mencatat audit context switch;
- memakai project lama `C:\Apache24\htdocs\sitangkas` sebagai referensi perilaku,
  bukan sebagai kode yang disalin mentah.

## Decision 13 - Remember Me hanya untuk non-Admin Super dan single-device

Keputusan:

`remember me` tetap tersedia untuk user internal, tetapi hanya untuk user yang
real active position-nya bukan Admin Super. Kebijakan ini dibuat sebagai fitur
kenyamanan terbatas, bukan sebagai pengecualian terhadap kontrol session.

Aturan resmi:

- `remember me` boleh aktif hanya jika real active position yang dipilih saat
  login bukan Admin Super;
- jika posisi yang dipilih otomatis saat login adalah Admin Super karena
  `last_used_at` terakhir menunjuk Admin Super, request `remember` harus dipaksa
  menjadi `false`;
- jika user memilih/ganti real active position ke Admin Super melalui
  `/positions`, remember cookie yang masih ada harus dimatikan;
- Admin Super acting context tidak boleh dipulihkan dari remember cookie;
- acting context tetap session-only dan harus dipilih ulang melalui `login.post`;
- user non-Admin Super hanya boleh mempunyai satu device aktif;
- ketika user login di device baru, session device lama harus direvoke dan
  `remember_token` harus dirotasi agar remember cookie device lama tidak bisa
  mengautentikasi ulang;
- remember cookie dibatasi default 24 jam melalui
  `AUTH_REMEMBER_ME_DURATION_MINUTES=1440`;
- durasi remember harus configurable melalui `config('auth.remember_me.duration_minutes')`
  dan guard `config('auth.guards.web.remember')`;
- server-side expiry harus memakai `users.remember_token_expires_at` agar token
  lama tetap bisa diputus walaupun cookie masih ada;
- session yang dibuat dari login remember-me juga harus menyimpan marker expiry
  `auth_remember_session_expires_at`, sehingga remembered session ikut berakhir
  secara absolute saat melewati `users.remember_token_expires_at`.

Konsekuensi implementasi:

- UI boleh tetap menampilkan checkbox `remember me`, tetapi backend wajib menjadi
  sumber kebenaran apakah request remember diterima atau diabaikan;
- keputusan remember tidak boleh ditentukan dari `users.account_type`, nama user,
  NIK khusus, atau role string yang tidak tervalidasi;
- keputusan remember harus memakai Real User Position yang sudah divalidasi;
- middleware/auth flow harus menangani login otomatis via remember cookie agar
  user non-Admin Super dapat memakai aplikasi tanpa input credential ulang;
- jika remember cookie mengarah ke kondisi Admin Super, request harus dilogout
  dan token/cookie remember harus dibersihkan;
- jika remember cookie melewati `remember_token_expires_at`, request harus
  dilogout, token/cookie remember dibersihkan, dan audit session revoked dicatat.
- jika user masih aktif memakai session yang berasal dari remember-me setelah
  expiry lewat, request berikutnya juga harus dilogout dengan failure code
  `remember_me_expired`.

File yang menjadi sumber aturan:

- `config/auth.php` pada key `remember_me`;
- `config/auth.php` pada guard `web.remember`;
- `users.remember_token_expires_at`;
- `App\Services\Auth\RememberMePolicy`;
- `App\Actions\Auth\EnforceSingleDeviceAuthentication`;
- middleware session single-device.

## Decision 14 - MFA TOTP wajib untuk Admin Super dan optional untuk non-admin

Keputusan:

MFA menggunakan TOTP standard yang kompatibel dengan Google Authenticator.
Enforcement awal wajib untuk real active position Admin Super, sementara user
non-Admin Super disiapkan agar bisa enroll MFA secara optional dan bisa dibuat
wajib melalui policy/config di masa depan.

Aturan inti:

- MFA bukan Google OAuth dan tidak memakai akun Google;
- sumber policy resmi adalah `config('auth.mfa')`;
- real active position Admin Super harus MFA verified sebelum masuk
  `login.post`, dashboard, atau route internal sensitif;
- non-Admin Super boleh disediakan setup MFA tanpa menjadi wajib global;
- jika non-Admin Super sudah enroll MFA, interactive login harus challenge MFA
  sesuai policy;
- remember me tidak boleh menjadi bypass MFA untuk Admin Super;
- secret TOTP, kode OTP, recovery code mentah, dan QR provisioning URI tidak
  boleh dicatat di audit/log/metadata;
- audit MFA memakai `login_events` dengan event `mfa_challenge` dan
  `mfa_verified`.

File keputusan detail:

- `docs/01-authentication/MFA_DECISIONS.md`.

## Status implementasi saat ini

Sudah ada:

- `/positions`;
- `PositionContextController`;
- legacy route `GET /login/context` yang redirect ke `/positions`;
- `CurrentUserContext`;
- `AuthenticateSession`;
- `RecordAuthenticationEvent`;
- `auth/postLogin.blade.php` sebagai view resmi Admin Super acting context;
- route `login.post`;
- controller khusus Admin Super acting context;
- request khusus Admin Super acting context;
- endpoint options khusus `login.post`;
- middleware `EnsureActiveUserPosition` yang mengarahkan Admin Super tanpa
  acting context ke `login.post`;
- method `CurrentUserContext` untuk real/effective/acting context;
- policy `RememberMePolicy` untuk membatasi remember-me hanya ke non-Admin Super;
- action `EnforceSingleDeviceAuthentication` untuk revoke session lama dan
  rotasi remember token;
- middleware `EnsureSingleDeviceSession` untuk enforcement single-device dan
  remembered login non-Admin Super;
- dashboard, navbar, sidebar, dan layout membaca context efektif melalui service;
- referensi project lama di `C:\Apache24\htdocs\sitangkas`;
- policy MFA tersedia di `config('auth.mfa')`;
- migration file storage MFA dan `remember_token_expires_at` tersedia untuk
  tabel `users`;
- runtime MFA sudah diimplementasikan melalui route `login.mfa`,
  `login.mfa.setup`, middleware `mfa.verified`, action TOTP/recovery code, dan
  audit `login_events`;
- Admin Super tanpa MFA verified sudah ditahan sebelum `login.post`, dashboard,
  dan route internal yang memakai middleware `mfa.verified`;
- halaman `/profile/security` sudah menjadi halaman status keamanan akun dan
  recovery-code regeneration;
- reset MFA resmi sudah tersedia melalui Management Users untuk Admin Super
  dan command operator `php artisan auth:mfa-reset`.

Perlu diperhatikan untuk iterasi berikutnya:

- jangan mengubah `activePosition()` menjadi real-only tanpa refactor seluruh
  modul yang membaca effective context;
- jika audit logout perlu snapshot acting yang lebih eksplisit, tambahkan metadata
  `real_user_position_id` dan `acting_context`;
- jika modul bisnis baru membutuhkan `user_position_id` pelaksana, tentukan secara
  eksplisit apakah yang dibutuhkan adalah real Admin Super atau acting context;
- jangan mengaktifkan remember-me untuk real position Admin Super atau acting
  context Admin Super tanpa decision baru;
- runtime MFA sudah aktif. AI agent tidak boleh membuat flow MFA kedua,
  `config/mfa.php` terpisah, atau mengarahkan Admin Super ke `login.post`
  sebelum session MFA valid;
- runtime server-side expiry remember-me sudah diimplementasikan melalui
  `EnforceSingleDeviceAuthentication`, `RememberMePolicy`, dan
  `EnsureSingleDeviceSession`, termasuk marker session
  `auth_remember_session_expires_at`;
- non-Admin Super sudah didukung secara policy untuk enrollment MFA optional
  dan UI aktivasi eksplisit di `/profile/security` sudah tersedia;
- halaman yang menampilkan raw recovery codes sudah diberi hardening
  cache/no-store melalui `SensitiveAuthenticationResponseHeaders`.

## Related docs

- `docs/01-authentication/AI_AGENT_DATABASE_CONTEXT.md`
- `docs/01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`
- `docs/01-authentication/MFA_DECISIONS.md`
- `docs/01-authentication/LOGIN_EVENTS_TABLE.md`
- `docs/03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md`
- `docs/02-master-data/AI_AGENT_MASTER_ORGANIZATION_CONTEXT.md`
- `docs/99-legacy/OLD_PROJECT_REFERENCE.md`
