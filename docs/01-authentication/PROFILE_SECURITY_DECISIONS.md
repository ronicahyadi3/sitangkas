# Profile Security Decisions

Dokumen ini adalah keputusan resmi untuk halaman keamanan akun setelah user
berhasil login. AI agent wajib membaca dokumen ini sebelum membuat fitur
regenerasi recovery code, status MFA, atau pengaturan keamanan akun lain.

## Status

Decision accepted.

Route GET `/profile/security` dengan name `profile.security` sudah dibuat dan
sudah memakai `App\Http\Controllers\Profile\SecurityController`.
`Profile\SecurityController` sudah return view
`resources/views/profile/security.blade.php`. View sudah menampilkan status
MFA, metode aktif, waktu MFA terakhir dipakai, waktu recovery codes dibuat,
jumlah recovery codes tersisa, ringkasan session MFA, dan konteks real user.
View juga sudah menampilkan tombol `Aktifkan MFA` atau `Lanjutkan Setup MFA`
untuk user non-Admin Super yang belum enroll MFA ketika policy
`config('auth.mfa.non_admin.available')` aktif.
Route POST `/profile/security/mfa/recovery-codes` dengan name
`profile.security.mfa.recovery_codes.regenerate` sudah dibuat dan terhubung ke
`Profile\MfaRecoveryCodeController@store`. Action
`App\Actions\Auth\RegenerateMfaRecoveryCodes` sudah tersedia dan dipakai oleh
controller tersebut. Request
`App\Http\Requests\Profile\RegenerateMfaRecoveryCodesRequest` sudah dibuat
untuk route POST regeneration. Controller
`App\Http\Controllers\Profile\MfaRecoveryCodeController` sudah dibuat dengan
method `store()` yang memanggil action dan menyiapkan flash data recovery codes
satu request. View profile security sudah memiliki form regenerate recovery
codes dan panel flash untuk menampilkan raw recovery codes baru satu kali
setelah POST berhasil. Response yang menampilkan raw recovery codes sudah
memakai header no-store melalui
`App\Services\Auth\SensitiveAuthenticationResponseHeaders`.

## Tujuan

Halaman Profile Security menjadi tempat resmi untuk user yang sudah login
melihat dan mengelola keamanan akunnya sendiri.

Scope awal halaman ini:

- menampilkan status MFA user;
- menampilkan ringkasan metode MFA aktif;
- menampilkan waktu terakhir MFA dipakai;
- menampilkan waktu recovery codes terakhir dibuat;
- menampilkan jumlah recovery codes tersisa;
- menyediakan aksi mulai/lanjut setup MFA optional untuk user non-Admin Super
  yang belum enroll;
- menyediakan aksi regenerate recovery codes.

Halaman ini tidak menggantikan flow login, MFA challenge, MFA setup pertama,
atau reset MFA operator.

## Keputusan Utama

Gunakan halaman khusus:

- route GET: `/profile/security`;
- route name: `profile.security`;
- view: `resources/views/profile/security.blade.php`;
- layout: `layouts.app`.

Gunakan endpoint POST khusus untuk regenerasi recovery code:

- route POST: `/profile/security/mfa/recovery-codes`;
- route name: `profile.security.mfa.recovery_codes.regenerate`;
- caller action: `App\Actions\Auth\RegenerateMfaRecoveryCodes`.

Endpoint ini hanya boleh dipakai oleh user yang sudah login, punya posisi aktif,
dan session MFA-nya valid.

## Struktur Controller Yang Direkomendasikan

Gunakan namespace controller yang bermakna:

- `App\Http\Controllers\Profile\SecurityController`;
- `App\Http\Controllers\Profile\MfaRecoveryCodeController`.

Alasan:

- `Profile\SecurityController` jelas menjadi halaman ringkasan keamanan akun;
- `Profile\MfaRecoveryCodeController` jelas menangani resource recovery codes;
- tidak mencampur pengaturan akun setelah login dengan controller `Auth` yang
  fokus pada login, challenge, setup, dan logout.

Controller GET sebaiknya hanya membaca state user dan menampilkan view.
Controller POST sebaiknya memanggil action, menerima hasil raw recovery codes,
dan menampilkan codes baru satu kali. Controller POST boleh memakai flash data
satu request setelah redirect ke `profile.security` agar browser tidak
melakukan resubmit POST saat refresh.

## Request Yang Direkomendasikan

Tambahkan request:

- `App\Http\Requests\Profile\RegenerateMfaRecoveryCodesRequest`.

Request ini bertugas sebagai guard awal:

- memastikan user sudah login;
- memastikan user punya real active position;
- memastikan request tidak berasal dari flow yang salah.

Validasi paling kritis tetap berada di
`RegenerateMfaRecoveryCodes`, karena action tersebut adalah guard reusable yang
tidak boleh bergantung pada controller tertentu. Karena action ini juga mencatat
audit event blocked, syarat seperti user harus enroll MFA, tidak ada pending
enrollment, dan session harus verified via TOTP tetap wajib divalidasi ulang di
action, bukan hanya di request.

## Middleware Wajib

Route GET `/profile/security`:

- `auth`;
- `single.device.session`;
- `active.position`;
- `mfa.verified`.

Route POST `/profile/security/mfa/recovery-codes`:

- `auth`;
- `single.device.session`;
- `active.position`;
- `mfa.verified`;
- throttle auth/security, misalnya `throttle:auth-mfa` atau limiter khusus
  `profile-security`.

Catatan penting: `mfa.verified` saja belum cukup untuk regenerasi recovery code.
Action `RegenerateMfaRecoveryCodes` tetap wajib memastikan session MFA verified
via `totp`, bukan via `recovery_code`.

## Aturan Recovery-Code Regeneration

Regenerasi recovery codes hanya boleh terjadi jika:

- user sudah enroll MFA;
- tidak ada pending enrollment;
- user memiliki real active position yang valid;
- session MFA masih valid;
- session MFA verified menggunakan metode TOTP;
- request berasal dari halaman keamanan akun setelah login.

Regenerasi recovery codes tidak boleh dilakukan jika:

- user belum enroll MFA;
- user baru verified MFA menggunakan recovery code;
- user sedang dalam flow `/login/mfa`;
- user berada di flow setup MFA pertama;
- user lupa authenticator dan tidak punya recovery code.

Jika user sudah tidak punya akses authenticator maupun recovery code, jalur
resmi tetap command operator:

```bash
php artisan auth:mfa-reset
```

Runbook ada di `MFA_RESET_COMMAND_RUNBOOK.md`.

## Aturan Tampilan Raw Recovery Codes

Raw recovery codes hanya boleh ditampilkan sekali setelah regenerate berhasil.

Yang boleh dilakukan:

- tampilkan codes baru pada response langsung setelah POST berhasil;
- atau tampilkan codes baru melalui flash data satu request setelah redirect ke
  `profile.security`;
- response yang menampilkan raw recovery codes wajib memakai header no-store:
  `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private`,
  `Pragma: no-cache`, `Expires: 0`, dan `Surrogate-Control: no-store`;
- beri peringatan agar user menyimpan codes dengan aman;
- tampilkan jumlah codes tersisa di halaman status;
- catat audit non-secret lewat `login_events`.

Yang tidak boleh dilakukan:

- menyimpan raw recovery codes di database;
- menyimpan raw recovery codes di audit/log;
- menyimpan raw recovery codes di session jangka panjang;
- menyimpan raw recovery codes di query string;
- mengirim raw recovery codes ke `old()` validation input;
- menyimpan raw recovery codes di browser storage;
- menampilkan ulang raw recovery codes setelah halaman di-refresh.

## Batasan Scope Awal

Jangan memasukkan fitur berikut ke implementasi awal tanpa decision baru:

- ganti password;
- edit profil user;
- disable MFA mandiri;
- reset MFA melalui browser;
- admin reset MFA UI;
- passkey/WebAuthn;
- security key fisik;
- device-bound authenticator;
- push MFA.

Halaman ini harus kecil, fokus, dan langsung berguna untuk status MFA dan
regenerasi recovery codes.

## Hubungan Dengan Flow Login

`/login/mfa` tetap menjadi flow challenge saat login.

`/login/mfa/setup` tetap menjadi flow setup MFA pertama.

`/profile/security` adalah flow pengelolaan keamanan akun setelah user masuk ke
area internal aplikasi.

AI agent tidak boleh memindahkan tombol regenerate recovery codes ke
`/login/mfa`, karena recovery-code regeneration membutuhkan session yang sudah
verified via TOTP dan konteks aplikasi internal yang stabil.

## Hubungan Dengan Admin Super

Admin Super tetap wajib MFA sebelum masuk `login.post`.

Jika Admin Super belum MFA verified:

- jangan izinkan akses `login.post`;
- jangan izinkan akses `/profile/security`;
- arahkan ke flow MFA yang sudah ada.

Jika Admin Super verified via recovery code:

- boleh masuk aplikasi selama policy mengizinkan;
- tidak boleh regenerate recovery codes;
- harus memakai authenticator TOTP lagi untuk regenerate.

## Urutan Implementasi Yang Direkomendasikan

1. Route GET `/profile/security` dengan name `profile.security` sudah dibuat.
2. Route sudah memakai `App\Http\Controllers\Profile\SecurityController`.
3. `App\Http\Controllers\Profile\SecurityController` sudah dibuat.
4. Controller sudah return view `resources/views/profile/security.blade.php`.
5. View minimal sudah dibuat memakai `layouts.app`.
6. Data halaman untuk status MFA, metode aktif, waktu terakhir dipakai, waktu
   recovery codes dibuat, dan jumlah recovery codes tersisa sudah
   diimplementasikan.
7. `App\Http\Requests\Profile\RegenerateMfaRecoveryCodesRequest` sudah dibuat.
8. `App\Http\Controllers\Profile\MfaRecoveryCodeController` sudah dibuat.
9. Route POST `/profile/security/mfa/recovery-codes` dengan name
   `profile.security.mfa.recovery_codes.regenerate` sudah dibuat.
10. POST route sudah terhubung ke `RegenerateMfaRecoveryCodes` melalui
    `Profile\MfaRecoveryCodeController@store`.
11. Raw recovery codes baru sudah ditampilkan satu kali setelah POST berhasil
    melalui flash data `mfa_recovery_codes_regenerated`.
12. Item navigasi internal "Keamanan Akun" sudah ditambahkan ke dropdown akun
    navbar dan sidebar.
13. Tombol `Aktifkan MFA` atau `Lanjutkan Setup MFA` untuk user non-Admin Super
    yang belum enroll sudah ditambahkan ke view profile security.
14. Header no-store untuk response yang menampilkan raw recovery codes sudah
    ditambahkan melalui `SensitiveAuthenticationResponseHeaders`.
15. Docs current implementation sudah diupdate setelah route/controller/view
    selesai.
16. Jalankan verifikasi non-test saja: lint PHP, Pint dirty, route list, dan
    view cache/clear.

Test suite tidak boleh dibuat, dimodifikasi, atau dijalankan tanpa konfirmasi
eksplisit dari user.

## Verifikasi Non-Test Yang Direkomendasikan

Gunakan verifikasi berikut setelah implementasi:

```bash
php -l app/Http/Controllers/Profile/SecurityController.php
php -l app/Http/Controllers/Profile/MfaRecoveryCodeController.php
php -l app/Http/Requests/Profile/RegenerateMfaRecoveryCodesRequest.php
vendor/bin/pint --dirty --format agent
php artisan route:list --path=profile/security --except-vendor
php artisan view:cache
php artisan view:clear
```

Jangan menjalankan `php artisan test`, Pest, PHPUnit, atau browser test tanpa
konfirmasi eksplisit dari user.

## Related Docs

- `MFA_DECISIONS.md`
- `MFA_RECOMMENDATIONS.md`
- `MFA_RESET_COMMAND_RUNBOOK.md`
- `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`
- `LOGIN_EVENTS_TABLE.md`
- `USERS_TABLE.md`
