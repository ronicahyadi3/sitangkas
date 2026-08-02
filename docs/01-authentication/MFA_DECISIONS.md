# MFA Decisions

Dokumen ini adalah keputusan arsitektur wajib untuk Multi-Factor Authentication
SITANGKAS. AI agent harus membaca dokumen ini sebelum mengubah login, Admin
Super acting context, remember-me, session middleware, atau audit autentikasi
yang berkaitan dengan MFA.

## Status keputusan

Status: accepted.

Keputusan utama:

- MFA menggunakan TOTP standard yang kompatibel dengan Google Authenticator.
- MFA wajib untuk real active position Admin Super.
- MFA tersedia untuk user non-Admin Super, tetapi belum wajib secara global.
- Jika user non-Admin Super mengaktifkan MFA secara sukarela, challenge MFA
  tetap harus dipakai saat interactive login sesuai policy.

Catatan penting:

Google Authenticator di sini berarti aplikasi authenticator TOTP, bukan Google
OAuth, bukan login memakai akun Google, dan bukan integrasi ke server Google.
Secret TOTP dibuat oleh aplikasi SITANGKAS, lalu user scan QR di Google
Authenticator atau aplikasi TOTP kompatibel lain.

## Ringkasan cepat untuk AI agent

| Area | Keputusan |
|---|---|
| Metode utama | TOTP, kompatibel Google Authenticator |
| Enforcement awal | Wajib untuk real active position Admin Super |
| Non-Admin Super | Fitur disediakan, enrollment optional |
| Remember me Admin Super | Tidak boleh bypass MFA dan tetap tidak berlaku |
| Acting context Admin Super | Tidak boleh dibuka sebelum MFA valid |
| Audit | Gunakan `login_events` dengan event MFA |
| Secret | Encrypted di database, tidak pernah dicatat di audit/log |

## Decision MFA-1 - Gunakan TOTP kompatibel Google Authenticator

Keputusan:

SITANGKAS memakai TOTP sebagai metode MFA awal. User dapat memakai Google
Authenticator, Microsoft Authenticator, 1Password, Bitwarden, atau aplikasi
authenticator lain yang kompatibel dengan TOTP.

AI agent tidak boleh:

- mengimplementasikan MFA sebagai Google OAuth;
- menganggap MFA membutuhkan koneksi ke server Google;
- menyimpan kode OTP yang diketik user;
- menyimpan secret TOTP di session, audit metadata, log, atau cache tanpa
  enkripsi;
- mengirim secret MFA ke frontend setelah setup selesai.

Konsekuensi:

- QR code hanya dipakai saat setup/enrollment;
- kode 6 digit diverifikasi server-side;
- secret MFA harus disimpan encrypted;
- recovery codes harus disimpan encrypted atau hashed, bukan plain text yang
  bisa dibaca ulang bebas.

## Decision MFA-2 - MFA wajib untuk Admin Super

Keputusan:

MFA wajib jika real active position user adalah Admin Super. Keputusan ini harus
dibaca dari Real User Position, bukan dari nama user, NIK khusus, account type,
atau acting context.

Sumber kebenaran:

- `CurrentUserContext::realActivePosition($request)`;
- `AdminSuperPositionScope::isAdminSuperJabatan(...)`;
- konfigurasi MFA resmi pada `config('auth.mfa')`.

Catatan fail-closed:

Jika `MFA_METHOD` tidak didukung, Admin Super tetap dianggap membutuhkan MFA
selama `MFA_ENABLED=true` dan `MFA_ADMIN_SUPER_REQUIRED=true`. Kondisi config
salah tidak boleh membuat Admin Super otomatis lolos ke `login.post`.

Flow resmi Admin Super:

1. User submit credential login.
2. Sistem memilih real active position.
3. Jika real active position bukan Admin Super, lanjut sesuai policy non-admin.
4. Jika real active position adalah Admin Super dan MFA belum siap, redirect ke
   MFA setup.
5. Jika real active position adalah Admin Super dan MFA sudah aktif tetapi belum
   verified pada session saat ini, redirect ke MFA challenge.
6. Setelah MFA verified, Admin Super boleh masuk ke `login.post`.
7. Setelah acting context dipilih, Admin Super boleh masuk dashboard dan modul
   internal.

AI agent tidak boleh mengarahkan Admin Super langsung ke `login.post` atau
dashboard sebelum MFA valid.

## Decision MFA-3 - Non-Admin Super optional tetapi disiapkan

Keputusan:

Fitur MFA harus dibangun generic untuk semua user, tetapi enforcement global
untuk non-Admin Super belum wajib.

Aturan:

- non-Admin Super boleh enroll MFA jika fitur tersedia;
- non-Admin Super yang belum enroll MFA boleh login normal selama
  `config('auth.mfa.non_admin.required') = false`;
- non-Admin Super yang sudah enroll MFA harus mengikuti challenge saat
  interactive login jika `config('auth.mfa.non_admin.enforce_when_enabled') = true`;
- jika nanti organisasi mewajibkan MFA untuk semua user, cukup ubah policy/config
  tanpa mengganti struktur database besar-besaran.

Konsekuensi:

- migration dan User model harus disiapkan untuk semua user;
- UI setup MFA sebaiknya tidak memakai istilah khusus Admin Super;
- middleware/policy yang menentukan wajib atau tidak harus membaca konfigurasi
  dan posisi aktif yang tervalidasi.

## Decision MFA-4 - Remember me tidak boleh menjadi MFA

Keputusan:

Remember me bukan faktor autentikasi kedua. Remember me hanya mekanisme
pemulihan login pada device yang pernah diautentikasi, dan tetap tunduk pada
kebijakan auth SITANGKAS.

Aturan Admin Super:

- remember me tetap tidak berlaku untuk real active position Admin Super;
- remembered login tidak boleh memulihkan Admin Super session;
- MFA Admin Super tidak boleh dibypass oleh cookie remember;
- Admin Super tidak memiliki trusted-device exemption pada implementasi awal.

Aturan non-Admin Super:

- remember me tetap boleh sesuai `RememberMePolicy`;
- jika non-Admin Super mengaktifkan MFA, interactive login harus challenge MFA
  sesuai policy;
- remembered login non-Admin Super boleh dianggap trusted-device hanya jika
  konfigurasi nanti mengizinkan secara eksplisit;
- single-device enforcement tetap berlaku sehingga login di device baru harus
  mencabut session/remember device lama.

AI agent tidak boleh mengubah kebijakan remember-me Admin Super tanpa decision
baru.

## Decision MFA-5 - MFA verified adalah state session terbatas

Keputusan:

Status MFA verified disimpan di session, bukan di kolom permanen yang berarti
"user selalu verified".

Session state MFA minimal harus mengikat:

- `user_id`;
- real `user_position_id`;
- metode MFA;
- waktu verified;
- waktu kedaluwarsa atau TTL policy.

Implementasi state session resmi saat ini berada di
`App\Services\Auth\MfaSession` dengan session key `auth_mfa_verified`.

Aturan:

- logout harus menghapus state MFA session;
- mengganti real active position harus menghapus atau mengevaluasi ulang state
  MFA;
- berpindah ke real active position Admin Super harus memicu MFA jika belum valid;
- acting context Admin Super tidak memperpanjang MFA secara otomatis kecuali ada
  policy eksplisit;
- jika TTL MFA expired, Admin Super harus challenge ulang sebelum akses route
  sensitif.

TTL awal yang direkomendasikan:

- Admin Super: 30 menit atau sesuai
  `config('auth.mfa.admin_super.verified_ttl_minutes')`;
- non-Admin Super: mengikuti policy terpisah jika MFA optional diaktifkan.

## Decision MFA-6 - Setup MFA untuk Admin Super harus fail closed

Keputusan:

Jika Admin Super belum enroll MFA, sistem harus mengarahkan ke setup MFA sebelum
Admin Super dapat memakai acting context atau dashboard.

Flow setup:

1. Sistem membuat secret TOTP sementara.
2. User scan QR di authenticator app.
3. User memasukkan kode 6 digit.
4. Jika kode valid, secret disimpan encrypted dan `mfa_enabled_at` serta
   `mfa_confirmed_at` diisi.
5. Recovery codes dibuat dan ditampilkan sekali.
6. Session ditandai MFA verified.
7. Admin Super diarahkan ke `login.post`.

Larangan:

- jangan mengaktifkan MFA hanya karena QR sudah ditampilkan;
- jangan menyimpan secret final sebelum kode pertama berhasil diverifikasi,
  kecuali secret disimpan sebagai pending secret yang jelas dan aman;
- jangan membiarkan Admin Super melewati setup MFA menuju dashboard.

## Decision MFA-7 - Audit MFA wajib memakai `login_events`

Keputusan:

Semua kejadian MFA penting dicatat ke `login_events` melalui
`RecordAuthenticationEvent`.

Event yang digunakan:

- `mfa_challenge` saat challenge dimulai, diblokir, atau expired;
- `mfa_verified` saat kode/recovery code berhasil atau gagal diverifikasi;
- `mfa_recovery_codes_regenerated` saat user yang sudah MFA verified membuat
  recovery codes baru;
- `mfa_reset` saat operator/admin teknis mereset MFA via command Artisan.

Field penting:

- `event_type`;
- `result`;
- `failure_code`;
- `message`;
- `mfa_method = totp` atau `recovery_code`;
- `mfa_result`;
- `user_context`;
- `metadata` hanya untuk data non-secret.

AI agent tidak boleh mencatat:

- secret TOTP;
- kode OTP;
- recovery code mentah;
- QR provisioning URI;
- isi penuh request MFA;
- session id mentah.

## Decision MFA-8 - Route dan middleware harus terpisah dari login context

Keputusan:

MFA adalah step autentikasi, bukan bagian dari form acting context Admin Super.
Route/controller MFA harus terpisah dari `login.context` dan `login.post`.

Nama route yang direkomendasikan:

- `GET /login/mfa` dengan name `login.mfa`;
- `POST /login/mfa` dengan name `login.mfa.store`;
- `GET /login/mfa/setup` dengan name `login.mfa.setup`;
- `POST /login/mfa/setup` dengan name `login.mfa.setup.store`;
- optional route recovery code/regenerate di area authenticated.

Middleware yang direkomendasikan:

- `EnsureMfaVerified` atau nama lebih spesifik yang proper;
- middleware harus berjalan setelah `auth` dan sebelum route sensitif;
- route MFA setup/challenge/logout harus dikecualikan dari redirect loop.

Route yang wajib dilindungi untuk Admin Super:

- `login.post`;
- `login.post.store`;
- endpoint options `login.post.options.*`;
- dashboard dan route internal yang memakai `active.position`;
- route context switch saat hasilnya memilih real active position Admin Super.

## Decision MFA-9 - Config menjadi sumber policy

Keputusan:

Policy MFA harus diletakkan di `config/auth.php` pada key `mfa`, bukan
hardcoded di controller dan bukan file config terpisah.

Sumber policy resmi:

```php
config('auth.mfa')
```

Config yang diimplementasikan:

```php
'mfa' => [
    'enabled' => env('MFA_ENABLED', true),
    'method' => env('MFA_METHOD', 'totp'),
    'admin_super' => [
        'required' => env('MFA_ADMIN_SUPER_REQUIRED', true),
        'verified_ttl_minutes' => max(1, (int) env('MFA_ADMIN_SUPER_VERIFIED_TTL_MINUTES', 30)),
        'allow_trusted_device' => env('MFA_ADMIN_SUPER_ALLOW_TRUSTED_DEVICE', false),
    ],
    'non_admin' => [
        'available' => env('MFA_NON_ADMIN_AVAILABLE', true),
        'required' => env('MFA_NON_ADMIN_REQUIRED', false),
        'enforce_when_enabled' => env('MFA_NON_ADMIN_ENFORCE_WHEN_ENABLED', true),
        'verified_ttl_minutes' => max(1, (int) env('MFA_NON_ADMIN_VERIFIED_TTL_MINUTES', 30)),
        'allow_trusted_device' => env('MFA_NON_ADMIN_ALLOW_TRUSTED_DEVICE', true),
    ],
    'totp' => [
        'digits' => (int) env('MFA_TOTP_DIGITS', 6),
        'period_seconds' => (int) env('MFA_TOTP_PERIOD_SECONDS', 30),
        'window' => (int) env('MFA_TOTP_WINDOW', 1),
    ],
    'recovery_codes' => [
        'count' => (int) env('MFA_RECOVERY_CODE_COUNT', 8),
    ],
],
```

AI agent boleh menyesuaikan nama key bila implementasi menemukan struktur yang
lebih rapi, tetapi makna policy di atas tidak boleh hilang.

## Decision MFA-10 - Package boleh dipakai setelah persetujuan

Keputusan:

Implementasi TOTP boleh memakai package, tetapi penambahan dependency tetap harus
mendapat persetujuan user.

Opsi yang cocok:

- `pragmarx/google2fa-laravel` untuk integrasi Google Authenticator/TOTP pada
  flow auth custom;
- package QR code tambahan jika dibutuhkan untuk membuat QR provisioning URI;
- Laravel Fortify hanya dipertimbangkan ulang jika project ingin mengadopsi flow
  auth Fortify secara lebih luas.

Rekomendasi untuk SITANGKAS:

Gunakan service/action custom di atas package TOTP yang ringan, karena auth
SITANGKAS sudah mempunyai reCAPTCHA, audit event, remember-me policy,
single-device enforcement, dan Admin Super acting context yang spesifik.

## Decision MFA-11 - Recovery code regeneration hanya untuk session MFA valid

Keputusan:

Recovery code regeneration adalah rotasi recovery codes untuk user yang masih
punya akses akun dan sudah berhasil MFA pada session saat ini. Fitur ini bukan
jalur recovery untuk user yang sudah terkunci total.

Syarat wajib:

- user harus authenticated;
- user harus sudah enroll MFA;
- real active user position harus valid;
- session harus sudah MFA verified via TOTP untuk user dan real active position
  yang sama melalui `MfaSession::isVerifiedFor(..., MfaPolicy::METHOD_TOTP)`;
- session yang verified hanya melalui `recovery_code` tidak boleh regenerate
  recovery codes;
- route yang nanti memanggil action ini wajib memakai middleware `auth`,
  `single.device.session`, dan `mfa.verified`;
- Admin Super tetap tidak boleh regenerate recovery codes sebelum MFA valid.

Perilaku saat regenerate:

- semua recovery code lama langsung dicabut;
- recovery code baru dibuat sesuai `config('auth.mfa.recovery_codes.count')`;
- recovery code baru disimpan dalam bentuk hash di `users.mfa_recovery_codes`;
- `users.mfa_recovery_codes_generated_at` diperbarui;
- recovery code mentah hanya dikembalikan oleh action untuk ditampilkan sekali
  pada flow yang sesuai;
- recovery code mentah tidak boleh disimpan di session jangka panjang, audit,
  log, query string, atau browser storage.

Audit:

- event type: `mfa_recovery_codes_regenerated`;
- result: `success`, `blocked`, atau `failed`;
- auth method: `mfa_recovery_codes`;
- mfa method: `recovery_code`;
- mfa result: mengikuti `result`;
- metadata hanya boleh berisi data non-secret seperti jumlah code lama, jumlah
  code baru, real user position id, dan snapshot MFA session.

Jika user sudah tidak punya akses authenticator maupun recovery code, jangan
pakai regeneration. Jalur resmi tetap command operator
`php artisan auth:mfa-reset`.

## Status implementasi saat ini

Pada saat dokumen ini ditulis:

- `config('auth.mfa')` sudah menjadi sumber policy MFA resmi;
- `App\Services\Auth\MfaPolicy` sudah dibuat untuk membaca policy MFA,
  menentukan required/available MFA, enrollment state, TTL verified session,
  trusted-device allowance, parameter TOTP, dan jumlah recovery code;
- `App\Services\Auth\TotpAuthenticator` sudah dibuat sebagai adapter internal
  untuk package `pragmarx/google2fa-laravel`;
- `App\Services\Auth\MfaSession` sudah dibuat untuk menyimpan state MFA verified
  yang terikat ke user, real user position, metode, waktu verified, dan waktu
  kedaluwarsa;
- `App\Actions\Auth\StartTotpEnrollment` sudah dibuat untuk membuat/reuse pending
  secret, provisioning URI, optional inline QR, dan audit setup start tanpa
  menyimpan secret di audit;
- `App\Actions\Auth\ConfirmTotpEnrollment` sudah dibuat untuk memvalidasi kode
  pertama terhadap pending secret, mengaktifkan MFA, membuat recovery codes,
  menandai session MFA verified, dan mencatat audit;
- `App\Actions\Auth\StartMfaChallenge` sudah dibuat untuk mencatat audit saat
  challenge MFA dimulai;
- `App\Actions\Auth\VerifyTotpChallenge` sudah dibuat untuk memvalidasi kode
  TOTP login berikutnya, update `mfa_last_used_at`, menandai session MFA
  verified, dan mencatat audit;
- `App\Actions\Auth\VerifyRecoveryCodeChallenge` sudah dibuat untuk memvalidasi
  recovery code satu kali, menghapus code yang sudah dipakai, update
  `mfa_last_used_at`, menandai session MFA verified dengan method
  `recovery_code`, dan mencatat audit;
- `App\Actions\Auth\RegenerateMfaRecoveryCodes` sudah dibuat untuk membuat ulang
  recovery codes hanya setelah session MFA verified via TOTP, mengganti seluruh
  recovery code lama, menyimpan hash baru, update
  `mfa_recovery_codes_generated_at`, mengembalikan recovery code mentah sekali
  kepada caller, dan mencatat audit `mfa_recovery_codes_regenerated`;
- `App\Actions\Auth\ResetUserMfa` sudah dibuat untuk reset enrollment MFA,
  menghapus secret/pending secret/recovery codes, memutar remember token,
  mengisi `sessions_invalidated_at`, mencabut session database jika tersedia,
  dan mencatat audit `mfa_reset`;
- `php artisan auth:mfa-reset` sudah dibuat untuk operator/admin teknis dengan
  opsi `--nik`, `--user-id`, `--actor-user-id`, `--reason`, dan `--force`;
- runbook penggunaan command reset MFA tersedia di
  `MFA_RESET_COMMAND_RUNBOOK.md`;
- `GET /login/mfa` dengan name `login.mfa` sudah dibuat untuk challenge MFA
  generic;
- `POST /login/mfa` dengan name `login.mfa.store` sudah dibuat untuk verifikasi
  challenge TOTP atau recovery code dengan throttle `auth-mfa`;
- `GET /login/mfa/setup` dengan name `login.mfa.setup` sudah dibuat untuk
  memulai enrollment TOTP;
- `POST /login/mfa/setup` dengan name `login.mfa.setup.store` sudah dibuat untuk
  konfirmasi kode TOTP pertama dengan throttle `auth-mfa-setup`;
- `App\Http\Controllers\Auth\TotpEnrollmentController` sudah dibuat sebagai
  controller setup TOTP;
- `App\Http\Requests\Auth\ConfirmTotpEnrollmentRequest` sudah dibuat untuk
  validasi input kode authenticator dan mengambil real active user position dari
  session;
- `resources/views/auth/mfa-setup.blade.php` sudah dibuat sebagai UI setup TOTP,
  menampilkan QR jika renderer tersedia, fallback manual key/provisioning URI,
  dan recovery codes sekali setelah setup berhasil;
- `App\Http\Controllers\Auth\MfaChallengeController` sudah dibuat sebagai
  controller challenge MFA;
- `App\Http\Requests\Auth\VerifyMfaChallengeRequest` sudah dibuat untuk validasi
  input `challenge_method`, kode authenticator, recovery code, dan mengambil
  real active user position dari session;
- `resources/views/auth/mfa-challenge.blade.php` sudah dibuat sebagai UI
  challenge TOTP dan recovery code;
- `App\Http\Middleware\EnsureMfaVerified` sudah dibuat dan dialias sebagai
  middleware route `mfa.verified`;
- middleware `mfa.verified` sudah dipasang pada `dashboard`, `login.post`,
  `login.post.store`, dan endpoint options `login.post.options.*`;
- middleware `mfa.verified` mencatat audit `mfa_challenge/expired` dengan
  failure code `mfa_session_expired` ketika session MFA untuk user dan real
  position yang sama sudah kedaluwarsa;
- `login_events` sudah memiliki field dan konstanta untuk MFA;
- migration file storage MFA dan `remember_token_expires_at` sudah dibuat untuk
  tabel `users`;
- Admin Super yang belum setup MFA diarahkan ke `login.mfa.setup` sebelum
  `login.post`;
- Admin Super yang sudah enroll MFA tetapi session MFA belum valid diarahkan ke
  `login.mfa` sebelum `login.post`.

## Status implementasi dan urutan lanjutan

Kondisi runtime saat ini:

1. Storage MFA dan `remember_token_expires_at` sudah tersedia di migration dan
   database lokal aktif.
2. Setup TOTP, branded QR, manual key, konfirmasi kode pertama, dan recovery
   codes awal sudah diimplementasikan.
3. Challenge MFA sudah mendukung TOTP dan recovery code satu kali pakai.
4. Admin Super wajib MFA sebelum `login.post`, dashboard, profile security, dan
   endpoint internal yang memakai `mfa.verified`.
5. Halaman `/profile/security` sudah tersedia sebagai rumah status MFA dan
   recovery-code regeneration.
6. Recovery-code regeneration sudah tersedia melalui
   `RegenerateMfaRecoveryCodes` dan hanya boleh berjalan jika session MFA
   verified via TOTP.
7. Reset MFA resmi sudah tersedia melalui command operator
   `php artisan auth:mfa-reset`.
8. Tombol `Aktifkan MFA` atau `Lanjutkan Setup MFA` untuk user non-Admin Super
   yang belum enroll sudah tersedia di `/profile/security`.
9. Response yang menampilkan raw recovery codes setelah setup pertama atau
   regenerate sudah memakai header no-store melalui
   `SensitiveAuthenticationResponseHeaders`.

Urutan lanjutan yang direkomendasikan setelah review manual:

1. Pastikan migration storage MFA sudah dijalankan pada setiap environment
   target, bukan hanya lokal.
2. Evaluasi apakah flash session untuk raw recovery codes perlu diganti dengan
   response langsung atau mekanisme one-time nonce yang lebih ketat.
3. Perluas enforcement ke route internal baru dengan middleware `mfa.verified`
   bila route tersebut dapat diakses Admin Super atau user non-Admin Super yang
   sudah enroll MFA.
4. Baca `MFA_RECOMMENDATIONS.md` sebelum menambah WebAuthn, security key,
   device-bound authenticator, trusted device, atau push MFA.

AI agent tidak boleh membuat, memodifikasi, atau menjalankan test suite/test
command tanpa konfirmasi eksplisit user.

## Related docs

- `MFA_RECOMMENDATIONS.md`
- `MFA_RESET_COMMAND_RUNBOOK.md`
- `PROFILE_SECURITY_DECISIONS.md`
- `AUTH_CONTEXT_DECISIONS.md`
- `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`
- `LOGIN_EVENTS_TABLE.md`
- `USERS_TABLE.md`
- `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md`
