# Current Auth Context Implementation

Last updated: 2026-08-18.

Dokumen ini menjelaskan kondisi implementasi login context SITANGKAS saat ini.
AI agent harus membaca file ini setelah `AUTH_CONTEXT_DECISIONS.md` sebelum
mengubah login, dashboard, navbar, middleware context, atau modul yang membaca
posisi aktif user.

## Ringkasan Status

Status implementasi: active implementation.

Flow yang sudah ada:

- login credential melalui `POST /login`;
- pemilihan posisi nyata melalui `GET/POST /login/context`;
- pemilihan acting context Admin Super melalui `GET/POST /login/post`;
- endpoint options untuk postLogin di bawah `/login/post/options/*`;
- dashboard internal memakai middleware `has.position`, `mfa.verified`,
  `active.position`, dan `password.fresh`;
- route authenticated memakai middleware `account.accessible` dan
  `single.device.session`;
- layout, navbar, sidebar, dan dashboard membaca context melalui
  `App\Services\Auth\CurrentUserContext`.
- enrichment awal `login_events` sudah aktif untuk parsing user-agent melalui
  `matomo/device-detector`, client timezone dari form login, request network,
  dan application version dari metadata deploy.

AI agent tidak boleh menganggap `resources/views/auth/postLogin.blade.php`
sebagai file usang. File itu adalah view resmi untuk Admin Super acting context.

## Route Aktif

Route penting:

- `GET /login` -> `login`;
- `POST /login` -> `login.store`;
- `POST /logout` -> `logout`;
- `GET /login/context` -> `login.context`;
- `POST /login/context` -> `login.context.store`;
- `GET /login/mfa` -> `login.mfa`;
- `POST /login/mfa` -> `login.mfa.store`;
- `GET /login/mfa/setup` -> `login.mfa.setup`;
- `POST /login/mfa/setup` -> `login.mfa.setup.store`;
- `GET /login/no-active-position` -> `login.no_active_position`;
- `GET /profile/security` -> `profile.security`;
- `POST /profile/security/mfa/recovery-codes` ->
  `profile.security.mfa.recovery_codes.regenerate`;
- `GET /login/post` -> `login.post`;
- `POST /login/post` -> `login.post.store`;
- `GET /login/post/options/instansi` -> `login.post.options.instansi`;
- `GET /login/post/options/unit-kerja` -> `login.post.options.unit_kerja`;
- `GET /login/post/options/special-users` -> `login.post.options.special_users`;
- route authenticated memakai middleware `web`, `auth`, `account.accessible`,
  dan `single.device.session`;
- `GET /dashboard` -> `dashboard`, tambahan middleware `has.position`,
  `mfa.verified`, `active.position`, dan `password.fresh`;
- `GET /profile/security` -> `profile.security`, tambahan middleware
  `has.position`, `mfa.verified`, `active.position`, dan `password.fresh`;
- `POST /profile/security/mfa/recovery-codes` ->
  `profile.security.mfa.recovery_codes.regenerate`, tambahan middleware
  `has.position`, `mfa.verified`, `active.position`, `password.fresh`, dan
  `throttle:auth-mfa`;
- route Management Users berada di prefix `users.*` dan memakai middleware
  `user.management`;
- route keamanan akun Management Users mencakup force password change,
  lock/unlock, dan reset MFA browser untuk Admin Super;
- route parameter Management Users `{user}` dan nested `{position}` memakai
  encrypted route key dari model binding `User` dan `UserPosition`; numeric ID
  polos ditolak oleh binding;
- endpoint `users.datatable` memakai server-side Yajra DataTables, filter
  `status`, `account_type`, `jabatan_id`, `instansi_id`, `unit_kerja_id`, dan
  `position_state`, serta presenter `UserDatatablePresenter`;
- route `users.audit-trail` menampilkan audit administrasi dari tabel
  `user_management_audit_events`;
- `login.post`, `login.post.store`, dan `login.post.options.*` memakai
  middleware `mfa.verified`.

## File Implementasi Utama

Auth actions:

- `app/Actions/Auth/AuthenticateSession.php`;
- `app/Actions/Auth/EnforceSingleDeviceAuthentication.php`;
- `app/Actions/Auth/RecordAuthenticationEvent.php`;
- `app/Actions/Auth/StartTotpEnrollment.php`;
- `app/Actions/Auth/ConfirmTotpEnrollment.php`;
- `app/Actions/Auth/StartMfaChallenge.php`;
- `app/Actions/Auth/VerifyTotpChallenge.php`;
- `app/Actions/Auth/VerifyRecoveryCodeChallenge.php`;
- `app/Actions/Auth/RegenerateMfaRecoveryCodes.php`;
- `app/Actions/Auth/ResetUserMfa.php`.

Console commands:

- `app/Console/Commands/Auth/ResetUserMfaCommand.php` dengan command
  `php artisan auth:mfa-reset`;
- runbook penggunaan command berada di `docs/01-authentication/MFA_RESET_COMMAND_RUNBOOK.md`.
- `app/Console/Commands/Auth/GeoIpStatusCommand.php` dengan command
  `php artisan auth:geoip-status`;
- runbook penggunaan command berada di
  `docs/01-authentication/GEOIP_MAXMIND_RUNBOOK.md`.

Controllers:

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`;
- `app/Http/Controllers/Auth/LoginContextController.php`;
- `app/Http/Controllers/Auth/AdminSuperActingContextController.php`;
- `app/Http/Controllers/Auth/MissingActivePositionController.php`;
- `app/Http/Controllers/Auth/MfaChallengeController.php`;
- `app/Http/Controllers/Auth/TotpEnrollmentController.php`;
- `app/Http/Controllers/DashboardController.php`;
- `app/Http/Controllers/Profile/SecurityController.php`;
- `app/Http/Controllers/Profile/MfaRecoveryCodeController.php`;
- `app/Http/Controllers/Users/UserController.php`;
- `app/Http/Controllers/Users/UserPositionController.php`;
- `app/Http/Controllers/Users/UserSecurityController.php`.

Requests:

- `app/Http/Requests/Auth/StoreAuthenticatedSessionRequest.php`;
- `app/Http/Requests/Auth/StoreLoginContextRequest.php`;
- `app/Http/Requests/Auth/StoreAdminSuperActingContextRequest.php`;
- `app/Http/Requests/Auth/VerifyMfaChallengeRequest.php`;
- `app/Http/Requests/Auth/ConfirmTotpEnrollmentRequest.php`;
- `app/Http/Requests/Profile/RegenerateMfaRecoveryCodesRequest.php`.

Services and middleware:

- `app/Services/Auth/CurrentUserContext.php`;
- `app/Services/Auth/AdminSuperPositionScope.php`;
- `app/Services/Auth/AuthenticationEventContext.php`;
- `app/Services/Auth/ApplicationVersionContext.php`;
- `app/Services/Auth/IpGeolocationContext.php`;
- `app/Services/Auth/IpRiskContext.php`;
- `app/Services/Auth/LoginEventIntegrity.php`;
- `app/Services/Auth/LoginEventRetention.php`;
- `app/Services/Auth/RequestNetworkContext.php`;
- `app/Services/Auth/UserAgentContext.php`;
- `app/Services/Auth/ClientSignalContext.php`;
- `app/Services/Auth/RememberMePolicy.php`;
- `app/Services/User/UserManagementAccessService.php`;
- `app/Services/User/UserManagementAuditLogger.php`;
- `app/Support/UserManagement/UserDatatablePresenter.php`;
- `app/Http/Middleware/EnsureActiveUserPosition.php`;
- `app/Http/Middleware/EnsureMfaVerified.php`;
- `app/Http/Middleware/EnsureAccountIsAccessible.php`;
- `app/Http/Middleware/EnsurePasswordIsFresh.php`;
- `app/Http/Middleware/EnsureSingleDeviceSession.php`;
- `app/Http/Middleware/EnsureUserHasSelectablePosition.php`;
- `app/Http/Middleware/EnsureUserManagementAccess.php`.

Views:

- `resources/views/auth/login.blade.php`;
- `resources/views/auth/context.blade.php`;
- `resources/views/auth/mfa-challenge.blade.php`;
- `resources/views/auth/mfa-setup.blade.php`;
- `resources/views/auth/postLogin.blade.php`;
- `resources/views/layouts/app.blade.php`;
- `resources/views/inc/navbar.blade.php`;
- `resources/views/inc/sidebar.blade.php`;
- `resources/views/dashboard/index.blade.php`;
- `resources/views/users/index.blade.php`;
- `resources/views/users/audit-trail.blade.php`.

## Login Events Enrichment Saat Ini

Policy resmi enrichment audit login berada di
`docs/01-authentication/LOGIN_EVENTS_ENRICHMENT_POLICY.md`.

Status implementasi saat ini:

- `config/auth.php` sudah memiliki policy nested
  `config('auth.audit.login_events.enrichment')`;
- `config('auth.audit.hash_key')` adalah sumber baru audit hash key;
- `config('auth.audit_hash_key')` masih tersedia sebagai compatibility key;
- `.env.example` sudah memuat placeholder config enrichment login events;
- package `matomo/device-detector` sudah terpasang;
- package `geoip2/geoip2` sudah terpasang untuk MaxMind local database lookup;
- `App\Services\Auth\AuthenticationEventContext` menjadi aggregator awal
  default context audit;
- `App\Services\Auth\RequestNetworkContext` sudah menormalisasi request network
  audit, termasuk `request_id`, `correlation_id`, `ip_address`,
  `proxy_ip_address`, dan `forwarded_for`;
- `config/trustedproxy.php` sudah tersedia dan membaca `TRUSTED_PROXIES`
  untuk trusted proxy Laravel;
- `App\Services\Auth\UserAgentContext` sudah mem-parse `user_agent` menjadi
  kolom device/browser/platform;
- `App\Services\Auth\ClientSignalContext` sudah mengambil `client_timezone`
  dari form login atau header `X-Client-Timezone`;
- `App\Services\Auth\ApplicationVersionContext` sudah membentuk
  `application_version` dari config deploy metadata;
- `App\Services\Auth\IpGeolocationContext` sudah menyiapkan enrichment
  GeoIP/ASN dari MaxMind local DB, dengan default disabled dan fail-safe
  `null`;
- `App\Services\Auth\IpRiskContext` sudah menyiapkan enrichment IP risk
  audit-only, dengan default disabled dan fail-safe `null`;
- `App\Services\Auth\LoginEventRetention` sudah menyiapkan
  `retention_until` dari config hari retensi;
- `App\Services\Auth\LoginEventIntegrity` sudah menyiapkan `event_hash`
  HMAC-SHA256 dari payload canonical event;
- `App\Actions\Auth\RecordAuthenticationEvent` tetap menjadi action pencatat
  event dan hanya mengambil enrichment dari service.

Kolom yang mulai bisa terisi atau sudah disiapkan oleh enrichment saat ini:

- `device_type`;
- `device_name`;
- `browser_name`;
- `browser_version`;
- `platform_name`;
- `platform_version`;
- `client_timezone`;
- `request_id`;
- `correlation_id`;
- `ip_address`;
- `proxy_ip_address`;
- `forwarded_for`;
- `application_version`;
- `network_asn`;
- `network_organization`;
- `country_code`;
- `region`;
- `city`;
- `is_vpn`;
- `is_proxy`;
- `is_tor`;
- `risk_score`;
- `event_hash`;
- `retention_until`.

Catatan `client_timezone`:

- form `resources/views/auth/login.blade.php` mengisi hidden input
  `client_timezone` dari
  `Intl.DateTimeFormat().resolvedOptions().timeZone`;
- request login hanya membatasi field sebagai string nullable maksimal 100
  karakter supaya timezone invalid tidak menggagalkan login;
- `ClientSignalContext` memvalidasi nilai terhadap daftar timezone PHP sebelum
  menyimpannya ke `login_events`;
- jika nilai kosong, dimanipulasi, tidak dikenal, atau collector browser gagal,
  kolom tetap `null`.

Catatan request network:

- `request_id` hanya diisi dari header `X-Request-Id` jika nilainya UUID valid;
- `correlation_id` hanya diisi dari header `X-Correlation-Id` jika nilainya
  UUID valid;
- `forwarded_for` hanya diisi dari `X-Forwarded-For` jika request berasal dari
  trusted proxy, atau jika config audit
  `capture_untrusted_forwarded_for` diaktifkan eksplisit;
- `proxy_ip_address` hanya diisi dari `REMOTE_ADDR` jika request berasal dari
  trusted proxy dan forwarded chain valid;
- set `TRUSTED_PROXIES` di production sesuai IP/CIDR reverse proxy atau load
  balancer resmi. Biarkan kosong untuk local/direct-to-app deployment.

Catatan application version:

- sumber resmi berada di
  `config('auth.audit.login_events.enrichment.application')`;
- env yang tersedia adalah `APP_VERSION`, `APP_BUILD_NUMBER`, dan
  `APP_BUILD_COMMIT`;
- format akhir di `login_events.application_version` dibuat oleh
  `ApplicationVersionContext`, maksimal 50 karakter;
- contoh hasil: `2026.08.04+build.17.sha.a1b2c3d4e5f6`;
- jika metadata deploy belum diset, kolom tetap `null`;
- AI agent tidak boleh hardcode versi aplikasi di controller, action, atau
  seeder.

Catatan GeoIP/ASN:

- sumber resmi config berada di
  `config('auth.audit.login_events.enrichment.geoip')`;
- provider yang didukung saat ini adalah `maxmind` dengan local database;
- env yang tersedia adalah `AUTH_LOGIN_EVENT_GEOIP_ENABLED`,
  `AUTH_LOGIN_EVENT_GEOIP_CITY_DATABASE_PATH`,
  `AUTH_LOGIN_EVENT_GEOIP_ASN_DATABASE_PATH`, dan
  `AUTH_LOGIN_EVENT_GEOIP_CACHE_TTL_SECONDS`;
- `AUTH_LOGIN_EVENT_GEOIP_DATABASE_PATH` tetap tersedia sebagai legacy fallback
  untuk city database;
- command status resmi adalah `php artisan auth:geoip-status`;
- IP private, loopback, reserved, config disabled, database belum tersedia,
  atau lookup gagal akan menghasilkan `null`;
- GeoIP/ASN tidak boleh dipakai sebagai lokasi presisi user.

Catatan IP risk/VPN/proxy/Tor:

- decision resmi berada di `docs/01-authentication/IP_RISK_DECISIONS.md`;
- sumber resmi config berada di
  `config('auth.audit.login_events.enrichment.ip_risk')`;
- mode resmi saat ini adalah `audit`;
- blocking login berdasarkan IP risk belum boleh dibuat;
- default env tetap `AUTH_LOGIN_EVENT_IP_RISK_ENABLED=false`;
- provider resmi saat ini adalah `none`;
- enrichment IP risk sengaja di-hold sampai ada persetujuan user terkait
  provider, biaya, privacy, token, timeout/cache, dan mapping response;
- provider teknis `maxmind_anonymous_ip` pernah disiapkan sebagai fondasi, tetapi
  belum approved untuk diaktifkan karena MaxMind Anonymous IP tidak tersedia
  sebagai GeoLite gratis;
- `null` berarti belum diperiksa, provider disabled, provider gagal, IP tidak
  public, allowlist dilewati, atau data tidak tersedia;
- `false` hanya berarti provider sudah memeriksa dan tidak mendeteksi sinyal;
- `is_vpn`, `is_proxy`, `is_tor`, dan `risk_score` yang bernilai `null` adalah
  kondisi by design selama provider masih di-hold;
- `risk_score` skala resmi 0-100, tetapi tetap `null` sampai ada
  provider/scoring resmi.

Catatan event integrity dan retention:

- sumber resmi config berada di
  `config('auth.audit.login_events.enrichment.integrity')`;
- `event_hash` dihitung oleh `LoginEventIntegrity` sebelum insert jika
  `AUTH_LOGIN_EVENT_HASH_ENABLED=true`;
- `event_hash` memakai HMAC-SHA256 dengan `AUDIT_HASH_KEY`;
- payload hash canonical mengecualikan `event_hash` dan `login_identifier`;
- `login_identifier_hash` tetap masuk payload canonical;
- `retention_until` dihitung oleh `LoginEventRetention` dari
  `occurred_at + AUTH_LOGIN_EVENT_RETENTION_DAYS`;
- jika `AUTH_LOGIN_EVENT_RETENTION_DAYS` kosong, nol, negatif, atau invalid,
  `retention_until` tetap `null`;
- tidak ada job penghapusan otomatis pada tahap ini.

Kolom enrichment yang masih belum diimplementasikan:

- `device_fingerprint_hash`.

AI agent tidak boleh mengisi kolom-kolom tersebut dengan default palsu. Ikuti
`LOGIN_EVENTS_ENRICHMENT_POLICY.md` sebelum implementasi tahap berikutnya.

Config:

- `config/position_rules.php`.
- `config/auth.php`;
- `config/session.php`;
- `.env.example`;
- `.env.production.example`.

## Production Env Hardening

Kondisi saat ini:

- `.env.example` memakai default lebih aman untuk debug dan session encryption,
  tetapi tetap ditujukan untuk local onboarding.
- `.env.production.example` adalah template deployment production.
- `AUDIT_HASH_KEY` wajib di production dan harus berbeda dari `APP_KEY`.
- `RecordAuthenticationEvent` hanya boleh fallback dari `AUDIT_HASH_KEY` ke
  `APP_KEY` ketika aplikasi berjalan di environment local.
- Jika production tidak punya `AUDIT_HASH_KEY`, audit authentication akan fail
  closed dengan exception agar tidak menulis HMAC memakai key yang salah.
- `config/session.php` default mengenkripsi session dan mengaktifkan secure cookie
  otomatis saat `APP_ENV=production` bila env tidak mengoverride.

## Remember Me dan Single-Device

Kondisi saat ini:

- `remember me` tetap tersedia di form login;
- backend menjadi sumber kebenaran apakah request remember diterima;
- policy berada di `config('auth.remember_me')` dan
  `App\Services\Auth\RememberMePolicy`;
- default policy: remember enabled, single-device enabled, Admin Super tidak
  boleh remember, context non-Admin Super boleh dipulihkan otomatis, dan durasi
  remember cookie default 24 jam melalui `AUTH_REMEMBER_ME_DURATION_MINUTES=1440`;
- guard `web` membaca `config('auth.guards.web.remember')` untuk membatasi
  umur remember cookie baru;
- `users.remember_token_expires_at` dipakai sebagai server-side expiry untuk
  remembered login;
- `AuthenticateSession` mengisi expiry ketika remember-me diterima dan
  mengosongkannya ketika remember-me ditolak;
- session yang memakai remember-me diberi marker
  `auth_remember_session_expires_at`, sehingga session aktif yang berasal dari
  remember-me tetap punya batas absolute sesuai
  `users.remember_token_expires_at`;
- `EnsureSingleDeviceSession` memutus remembered login jika expiry null,
  session marker expired, atau sudah lewat waktu;
- logout normal mengosongkan `remember_token_expires_at`;
- `App\Actions\Auth\EnforceSingleDeviceAuthentication` merotasi
  `remember_token`, menghapus session lama user dari tabel `sessions`, dan
  memberi marker session aktif serta mengatur expiry remember token;
- `App\Http\Middleware\EnsureSingleDeviceSession` mengecek session revoked,
  memulihkan context non-Admin Super untuk login otomatis via remember cookie,
  memblokir remember cookie yang mengarah ke Admin Super, serta memblokir
  remember cookie/session yang sudah melewati `remember_token_expires_at`.

Aturan penting:

- jika posisi otomatis saat login adalah Admin Super karena `last_used_at`
  terakhir menunjuk Admin Super, nilai remember dipaksa `false`;
- jika user mengganti real active position ke Admin Super melalui
  `login.context`, remember cookie/token dimatikan;
- saat Admin Super submit `login.post`, remember cookie/token juga dimatikan
  sebagai safeguard;
- Admin Super acting context tidak pernah dipulihkan dari remember cookie;
- ketika user login di device baru, device lama logout pada request berikutnya
  karena session lama direvoke dan remember token lama tidak valid lagi;
- jika request via remember cookie atau session remembered melewati
  `remember_token_expires_at`, user dilogout, remember token/cookie dibersihkan, dan audit
  `session_revoked/revoked` dicatat dengan failure code `remember_me_expired`.

## MFA Policy Dan Storage

Kondisi saat ini:

- policy MFA resmi berada di `config('auth.mfa')`;
- `App\Services\Auth\MfaPolicy` sudah menjadi pembaca policy MFA runtime;
- `App\Services\Auth\TotpAuthenticator` sudah menjadi adapter internal untuk
  generate secret, provisioning URI, optional inline QR, dan verifikasi kode
  TOTP melalui `pragmarx/google2fa-laravel`;
- `App\Services\Auth\MfaSession` sudah menyimpan state MFA verified di session
  key `auth_mfa_verified`, terikat ke `user_id`, real `user_position_id`,
  metode, `verified_at`, dan `expires_at`;
- `App\Actions\Auth\StartTotpEnrollment` dan `ConfirmTotpEnrollment` sudah
  tersedia sebagai action setup/confirm TOTP;
- `App\Actions\Auth\StartMfaChallenge`, `VerifyTotpChallenge`, dan
  `VerifyRecoveryCodeChallenge` sudah tersedia sebagai action challenge MFA;
- `VerifyRecoveryCodeChallenge` mengonsumsi recovery code satu kali dengan
  `lockForUpdate()`, menyimpan ulang sisa hash recovery code, menandai session
  MFA verified dengan method `recovery_code`, dan mencatat audit;
- `App\Actions\Auth\RegenerateMfaRecoveryCodes` sudah tersedia untuk membuat
  ulang recovery codes bagi user yang sudah enroll MFA dan session MFA-nya
  verified via TOTP. Action ini mengganti semua recovery code lama, menyimpan
  hash baru, update `mfa_recovery_codes_generated_at`, mengembalikan recovery
  code mentah hanya untuk ditampilkan sekali oleh caller, dan mencatat audit
  `mfa_recovery_codes_regenerated`;
- `App\Actions\Auth\ResetUserMfa` sudah tersedia untuk jalur Management Users
  dan command `php artisan auth:mfa-reset`. Action ini reset enrollment MFA,
  menghapus secret/pending secret/recovery codes, memutar remember token,
  mengisi `sessions_invalidated_at`, menghapus session database target bila
  session driver database, menolak target tanpa MFA aktif/pending, dan mencatat
  audit `mfa_reset`;
- `App\Services\Auth\SensitiveAuthenticationResponseHeaders` sudah tersedia
  untuk memberi header `Cache-Control: no-store`, `Pragma: no-cache`,
  `Expires: 0`, dan `Surrogate-Control: no-store` pada response autentikasi
  yang membawa recovery codes mentah;
- `App\Http\Controllers\Auth\MfaChallengeController` sudah menyediakan
  `GET/POST /login/mfa` untuk challenge TOTP atau recovery code;
- `App\Http\Requests\Auth\VerifyMfaChallengeRequest` sudah memvalidasi
  `challenge_method`, kode authenticator, recovery code, dan memastikan real
  active position dari session masih valid;
- `App\Http\Controllers\Auth\TotpEnrollmentController` sudah menyediakan
  `GET/POST /login/mfa/setup` untuk enrollment TOTP;
- `App\Http\Requests\Auth\ConfirmTotpEnrollmentRequest` sudah memvalidasi kode
  authenticator dan memastikan real active position dari session masih valid;
- `resources/views/auth/mfa-setup.blade.php` sudah menampilkan QR jika tersedia,
  fallback manual key/provisioning URI, form kode TOTP, dan recovery codes sekali
  setelah setup berhasil. Response setup berhasil sudah memakai header no-store;
- `resources/views/auth/mfa-challenge.blade.php` sudah menampilkan form
  challenge TOTP dan form recovery code satu kali untuk user yang sudah enroll
  MFA;
- `App\Http\Middleware\EnsureMfaVerified` sudah dialias sebagai `mfa.verified`;
- `mfa.verified` sudah dipasang pada `dashboard`, `login.post`,
  `login.post.store`, dan `login.post.options.*`;
- `mfa.verified` mencatat audit `mfa_challenge/expired` dengan failure code
  `mfa_session_expired` ketika session MFA yang terikat ke user dan real active
  position sudah kedaluwarsa;
- metode MFA adalah TOTP kompatibel Google Authenticator;
- Admin Super wajib MFA menurut policy, non-Admin Super tersedia/optional;
- migration file field storage MFA sudah dibuat untuk tabel `users`;
- `User` model menyembunyikan dan mengenkripsi field secret/recovery code MFA;
- enforcement runtime MFA sudah aktif untuk Admin Super sebelum `login.post`.
- rekomendasi lanjutan MFA seperti reset MFA Artisan,
  QR branded, WebAuthn, security key, device-bound authenticator, dan push MFA
  terdokumentasi di `MFA_RECOMMENDATIONS.md`.

Aturan penting:

- jangan membuat `config/mfa.php` terpisah tanpa decision baru;
- jangan mengarahkan Admin Super ke `login.post` setelah runtime MFA dipasang
  sebelum session MFA valid;
- jangan menyimpan secret TOTP, kode OTP, recovery code mentah, atau QR
  provisioning URI ke audit/log.
- output `manual_entry_key`, `provisioning_uri`, `inline_qr_code`, dan
  `recovery_codes` dari action MFA hanya boleh ditampilkan ke user pada flow
  setup yang sesuai, tidak boleh dicatat ke audit/log.
- recovery code pada challenge MFA hanya boleh dipakai satu kali, tidak boleh
  dikembalikan ke HTML melalui `old()`, dan tidak boleh dicatat ke audit/log.
- regeneration recovery codes hanya boleh dilakukan setelah session MFA verified
  via TOTP. Session yang verified via recovery code tidak boleh membuat recovery
  codes baru.
- route GET `/profile/security` dengan name `profile.security` sudah dibuat
  dan sudah memakai `App\Http\Controllers\Profile\SecurityController`;
- `Profile\SecurityController` sudah return view
  `resources/views/profile/security.blade.php`;
- view profile security sudah menampilkan status MFA, metode aktif, waktu MFA
  terakhir dipakai, waktu recovery codes dibuat, jumlah recovery codes tersisa,
  ringkasan session MFA, dan konteks real user;
- view profile security sudah menampilkan tombol `Aktifkan MFA` atau
  `Lanjutkan Setup MFA` untuk user non-Admin Super yang belum enroll MFA ketika
  policy `config('auth.mfa.non_admin.available')` aktif;
- `App\Http\Requests\Profile\RegenerateMfaRecoveryCodesRequest` sudah dibuat
  untuk route POST regeneration. Request ini memastikan user valid,
  route yang dipakai adalah `profile.security.mfa.recovery_codes.regenerate`,
  dan real active position tersedia;
- `App\Http\Controllers\Profile\MfaRecoveryCodeController` sudah dibuat dengan
  method `store()` yang memanggil `RegenerateMfaRecoveryCodes`, lalu redirect ke
  `profile.security` dengan flash data raw recovery codes satu request;
- route POST `/profile/security/mfa/recovery-codes` dengan name
  `profile.security.mfa.recovery_codes.regenerate` sudah dibuat dan terhubung
  ke `Profile\MfaRecoveryCodeController@store`;
- view profile security sudah memiliki form regenerate recovery codes dan panel
  flash untuk menampilkan raw recovery codes baru satu kali setelah POST
  berhasil. Response profile security yang sedang menampilkan raw recovery codes
  dari flash data sudah memakai header no-store;
- layout internal sudah menampilkan link "Keamanan Akun" di dropdown akun
  navbar dan sidebar dengan target route `profile.security`;
- reset MFA dari browser sudah tersedia melalui Management Users dan dibatasi
  untuk Admin Super. Jalur command Artisan `auth:mfa-reset` tetap tersedia untuk
  operator/server.
- route setup MFA tidak boleh dianggap sebagai pengganti challenge MFA; user
  yang sudah enroll MFA tetap perlu melewati `login.mfa` jika session MFA belum
  valid.
- route challenge/setup/logout/login context dikecualikan dari redirect loop
  middleware MFA.
- endpoint `login.post.options.*` yang terkena middleware MFA akan menerima JSON
  berisi `redirect_to` bila request mengharapkan JSON dan MFA belum valid.

## CurrentUserContext Contract

`CurrentUserContext` sekarang mempunyai dua makna context yang berbeda:

### Real Active Position

Method:

```php
$context->realActivePosition($request);
```

Makna:

- selalu membaca posisi nyata dari tabel `user_positions`;
- sumber session adalah `active_user_position_id`;
- wajib milik user yang sedang login;
- wajib aktif, efektif, tidak soft-deleted, dan relasi master aktif;
- dipakai untuk audit, validasi kepemilikan posisi, dan pengecekan apakah user
  sedang memakai real position Admin Super.

### Effective Active Position

Method:

```php
$context->activePosition($request);
```

Makna:

- context operasional yang harus dipakai dashboard, navbar, sidebar, policy, dan
  query modul bisnis;
- untuk user non Admin Super, nilainya sama dengan real active position;
- untuk Admin Super dengan acting context lengkap, nilainya adalah overlay dari
  pilihan `login.post`;
- untuk Admin Super tanpa acting context lengkap, middleware internal harus
  redirect ke `login.post`.

## Aturan ID Yang Wajib Dipahami

Untuk user biasa atau real active position non Admin Super:

- `activePosition()->id` = `user_positions.id` yang aktif;
- `activePosition()->jabatan_id` = jabatan asli dari posisi aktif;
- `activePosition()->instansi_id` = instansi asli dari posisi aktif;
- `activePosition()->unit_kerja_id` = unit kerja asli dari posisi aktif.

Untuk Admin Super setelah memilih acting context di `login.post`:

- `activePosition()->id` tetap `user_positions.id` real Admin Super;
- `activePosition()->real_user_position_id` berisi id real Admin Super;
- `activePosition()->jabatan_id` berisi jabatan acting yang dipilih;
- `activePosition()->instansi_id` berisi instansi acting yang dipilih;
- `activePosition()->unit_kerja_id` berisi unit kerja acting yang dipilih;
- `activePosition()->is_acting_context` bernilai `true`;
- `activePosition()->acting_context` berisi snapshot session acting.

Konsekuensi:

- jangan memakai `activePosition()->id` sebagai id posisi acting Admin Super;
- untuk filter data operasional, pakai `jabatan_id`, `instansi_id`, dan
  `unit_kerja_id` dari `activePosition()`;
- untuk audit posisi asli, pakai `realActivePosition()` atau
  `real_user_position_id`;
- jika modul membutuhkan `user_position_id` yang benar-benar menunjuk row
  pelaksana bisnis, desain audit modul harus eksplisit membedakan real
  Admin Super dan acting context.

## Flow Login Saat Ini

Saat login credential sukses:

1. `AuthenticateSession` memilih real active position dari `user_positions`
   berdasarkan posisi yang tersedia dan `last_used_at` terbaru.
2. `RememberMePolicy` menentukan apakah request remember boleh diterima.
3. `EnforceSingleDeviceAuthentication` merotasi `remember_token`, menghapus
   session lama user, dan menyiapkan marker session aktif.
4. Session diregenerasi.
5. Session `acting_*` lama dibersihkan.
6. Session `active_user_position_id` dan `tahun_aktif` diisi.
7. Login success dicatat melalui `RecordAuthenticationEvent`.
8. Redirect mengikuti route intended, lalu dashboard middleware menentukan apakah
   perlu lanjut ke dashboard atau ke `login.post`.

Catatan:

- Jika real active position adalah Admin Super dan belum ada acting context,
  `EnsureActiveUserPosition` mengarahkan ke `login.post`.
- Jika real active position bukan Admin Super, acting context lama dibersihkan.
- Jika real active position adalah Admin Super, remember request dipaksa `false`
  walaupun checkbox dikirim dari browser.

## Flow Ganti Real Position

`login.context` hanya menerima `user_position_id`.

Saat user memilih posisi:

1. `StoreLoginContextRequest` memvalidasi posisi milik user.
2. `LoginContextController` memanggil `CurrentUserContext::activatePosition()`.
3. Acting context lama dibersihkan.
4. `last_used_at` posisi diperbarui.
5. Audit context switch dicatat.
6. Jika posisi yang dipilih adalah Admin Super, remember cookie/token dimatikan
   lalu redirect ke `login.post`.
7. Jika bukan Admin Super, redirect ke dashboard.

## Flow Admin Super Acting Context

`login.post` hanya untuk user yang real active position-nya adalah Admin Super.

GET `login.post`:

- membaca real active position melalui `realActivePosition()`;
- memastikan jabatan real position adalah `ADMIN_SUPER`;
- menampilkan pilihan jabatan dari `AdminSuperPositionScope::jabatanOptions()`;
- view yang dipakai adalah `resources/views/auth/postLogin.blade.php`.

POST `login.post.store`:

- request yang dipakai adalah `StoreAdminSuperActingContextRequest`;
- authorization memakai `realActivePosition()`, bukan `activePosition()`;
- payload divalidasi oleh `AdminSuperPositionScope::validateCombination()`;
- session `acting_*` lama dibersihkan;
- session `acting_*` baru disimpan;
- session diregenerasi;
- remember cookie/token dimatikan sebagai safeguard Admin Super;
- event context switch dicatat;
- redirect ke dashboard.

Endpoint options:

- `instansiOptions()` memakai `jabatan_id`;
- `unitKerjaOptions()` memakai `jabatan_id` dan `instansi_id`;
- `specialUserPositionOptions()` memakai `jabatan_id` dan `unit_kerja_id`;
- semua options tetap mengecek real Admin Super melalui `realActivePosition()`.

## Session Keys

Real context:

- `active_user_position_id`;
- `tahun_aktif`.

Acting context Admin Super:

- `acting_jabatan_id`;
- `acting_jabatan_name`;
- `acting_instansi_id`;
- `acting_unit_kerja_id`;
- `acting_pptk_user_position_id`;
- `acting_bud_user_position_id`;
- `acting_selected_at`.

Daftar key resmi dibaca dari:

```php
config('position_rules.manual_context.session_keys')
```

AI agent tidak boleh menambah akses langsung `session('acting_*')` di controller,
view, middleware, policy, atau modul bisnis baru. Gunakan `CurrentUserContext`.

## Middleware Context

`EnsureActiveUserPosition` sekarang menjalankan aturan berikut:

- user harus authenticated;
- session harus punya `active_user_position_id` dan `tahun_aktif`;
- real active position harus masih valid;
- jika real active position non Admin Super, acting context dibersihkan dan request
  boleh lanjut;
- jika real active position Admin Super dan route bukan route acting/context/logout,
  acting context wajib lengkap;
- Admin Super tanpa acting context diarahkan ke `login.post`;
- Admin Super dengan acting context invalid diarahkan ulang ke `login.post`.

## Layout, Navbar, Sidebar, Dashboard

Kondisi saat ini:

- `layouts/app.blade.php` membuat `window.currentUserContext` dari
  `CurrentUserContext::snapshot()`;
- snapshot berisi effective context, `tahun_aktif`, `is_acting_context`,
  `real_user_position_id`, dan `acting_context`;
- `inc/navbar.blade.php` membaca effective role dari `activePosition()`;
- navbar Admin Super mengarahkan menu context ke `login.post`;
- `inc/sidebar.blade.php` membaca role efektif dari `activePosition()`;
- `dashboard/index.blade.php` membaca `activeUserPosition` dari
  `DashboardController` dan tombol context Admin Super mengarah ke `login.post`.

## Audit Authentication

Audit yang sudah dipakai:

- login success/failed/blocked/lockout;
- login otomatis melalui remember cookie untuk non-Admin Super;
- context switch `login.context`;
- context switch Admin Super `login.post`;
- session revoked karena single-device atau remember policy;
- logout.

Untuk Admin Super acting context, event context switch menyimpan metadata:

- `context_type = admin_super_acting_context`;
- `real_user_position_id`;
- `acting_context`;
- `tahun_aktif`.

Logout saat ini membaca `activePosition()`, sehingga untuk Admin Super yang sedang
acting, posisi yang dikirim ke audit adalah effective position overlay. Metadata
logout tetap berisi `active_user_position_id` session real position. Jika audit
logout membutuhkan snapshot lebih kaya, tambahkan metadata eksplisit
`real_user_position_id` dan `acting_context`.

## Validasi Yang Sudah Dilakukan Pada Snapshot Ini

Validasi terakhir yang dilakukan:

- `vendor/bin/pint --dirty --format agent`;
- `php -l` untuk file PHP auth/context yang berubah;
- `php artisan route:list --name=login.post --except-vendor --no-interaction -v`;
- `php artisan route:list --path=dashboard --except-vendor --no-interaction -v`;
- autoload PHP method check untuk method penting `CurrentUserContext`.

Tidak dilakukan:

- test suite Pest/PHPUnit;
- browser/smoke test;
- migration fresh ulang.

Alasan: user menetapkan bahwa AI agent tidak boleh membuat, memodifikasi, atau
menjalankan test suite/test command tanpa konfirmasi eksplisit terlebih dahulu.

## Hal Yang Harus Diperhatikan Agent Berikutnya

- `AUTH_CONTEXT_DECISIONS.md` adalah keputusan arsitektur; file ini adalah
  snapshot implementasi terkini.
- Jika ada konflik antara file ini dan kode aktual, baca kode aktual lalu update
  docs sebelum melanjutkan perubahan besar.
- Jangan mengubah `activePosition()` kembali menjadi real position only.
- Jangan membaca `acting_*` langsung di modul bisnis.
- Jangan membuat row `user_positions` baru untuk pilihan acting Admin Super.
- Jangan mengganti `login.post` menjadi alias dari `login.context`.
- Jangan menjalankan test suite tanpa konfirmasi user.
