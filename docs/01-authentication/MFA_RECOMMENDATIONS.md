# MFA Recommendations For AI Agents

Dokumen ini wajib dibaca sebelum AI agent mengubah flow MFA, Google
Authenticator, QR code, recovery code, reset MFA, remember-me, login context
Admin Super, atau middleware auth internal.

Sumber keputusan policy MFA resmi tetap `config('auth.mfa')` dan
`MFA_DECISIONS.md`. Dokumen ini menjelaskan rekomendasi implementasi dan batas
aman agar agent berikutnya tidak salah menafsirkan arah MFA SITANGKAS.

## Ringkasan Rekomendasi Utama

- Tetap gunakan TOTP kompatibel Google Authenticator sebagai MFA fase awal.
- Jangan gunakan Gmail, Google OAuth, atau koneksi ke server Google untuk MFA.
- Jangan menganggap Google Authenticator bisa dikunci server agar hanya ada satu
  device atau satu akun di aplikasi authenticator.
- Server hanya boleh menjamin satu secret MFA aktif per user, bukan mengontrol
  isi aplikasi Google Authenticator di perangkat user.
- Admin Super wajib MFA sebelum masuk `login.post` dan sebelum memilih acting
  context.
- Non-Admin Super boleh disiapkan MFA secara optional, dan enforcement global
  baru boleh diaktifkan lewat policy/config.
- Remember-me tidak boleh bypass MFA Admin Super.
- Recovery code boleh disediakan sebagai fallback, tetapi harus one-time,
  dibatasi, diaudit, dan tidak boleh menjadi cara login permanen.
- Reset MFA lebih aman dimulai dari command Artisan untuk operator, bukan UI
  browser admin umum.
- Recovery-code regeneration harus ditempatkan di halaman profile/security,
  bukan di flow `login.mfa`; baca `PROFILE_SECURITY_DECISIONS.md`.
- Passkey/WebAuthn/FIDO2 adalah kandidat fase lanjutan, bukan pengganti cepat
  untuk implementasi TOTP yang sudah berjalan.

## Rekomendasi 1 - Pertahankan TOTP Google Authenticator Untuk Fase Awal

TOTP adalah pilihan paling tepat untuk kondisi SITANGKAS saat ini karena:

- sudah diimplementasikan melalui adapter internal `TotpAuthenticator`;
- kompatibel dengan Google Authenticator dan aplikasi TOTP lain;
- tidak butuh email, Gmail, SMS, push provider, atau koneksi pihak ketiga saat
  validasi kode;
- cocok dengan auth custom SITANGKAS yang sudah punya audit event,
  single-device session, remember-me policy, dan Admin Super acting context;
- ringan secara performa karena verifikasi hanya menghitung kode berdasarkan
  secret lokal dan waktu.

Yang tidak boleh dilakukan:

- mengubah MFA TOTP menjadi Google OAuth tanpa decision baru;
- menambahkan kewajiban Gmail untuk user;
- mengirim secret TOTP ke Google;
- mencatat secret, provisioning URI, QR code, OTP, atau recovery code mentah ke
  log/audit.

## Rekomendasi 2 - Google Authenticator Tidak Membutuhkan Gmail

Google Authenticator adalah aplikasi pembuat kode TOTP offline. Saat user scan
QR, aplikasi hanya menyimpan secret TOTP dan metadata issuer/account name.

Gmail tidak menambah keamanan untuk flow ini karena:

- kode TOTP tidak divalidasi oleh server Google;
- SITANGKAS tidak perlu mengetahui akun Google user;
- menambahkan Gmail berarti menambah dependency identitas eksternal yang belum
  menjadi kebutuhan sistem.

Jika nanti dibutuhkan identitas eksternal, itu harus menjadi decision terpisah
seperti SSO/OIDC, bukan bagian dari MFA TOTP.

## Rekomendasi 3 - Jangan Menjanjikan Lock Satu Device Google Authenticator

Dengan TOTP standard, server tidak bisa mengetahui:

- berapa perangkat yang sudah men-scan QR;
- apakah QR disimpan sebagai screenshot;
- apakah secret disalin manual ke aplikasi authenticator lain;
- apakah dalam satu Google Authenticator terdapat satu atau banyak akun.

Yang bisa dan harus dijamin oleh server:

- hanya satu secret MFA aktif untuk satu user;
- QR/setup hanya tampil saat user belum enroll MFA atau setelah reset resmi;
- setelah enrollment berhasil, secret pending dibersihkan;
- re-enrollment hanya boleh melalui flow reset yang diaudit;
- session aplikasi tetap single-device sesuai policy login SITANGKAS;
- remember-me tetap mengikuti batas waktu dan single-device enforcement.

Jika kebutuhan bisnis benar-benar mengharuskan "satu device MFA yang terikat
perangkat", TOTP bukan alat yang tepat. Gunakan device-bound authenticator,
passkey/WebAuthn, security key, atau push MFA dengan device registration.

## Rekomendasi 4 - QR Code Branded Hanya Untuk UX, Bukan Security

SITANGKAS sudah memakai `endroid/qr-code` untuk membuat QR TOTP branded dari
provisioning URI, dengan logo:

```text
public/assets/img/Logo_Kota_Malang_color.png
```

Aturan teknis:

- QR dibuat dari provisioning URI milik `TotpAuthenticator::inlineQrCode()`;
- output QR boleh berupa inline PNG data URI hanya pada halaman setup;
- logo harus kecil, idealnya sekitar 15 sampai 20 persen ukuran QR;
- error correction harus cukup tinggi agar QR tetap bisa discan;
- manual entry key tetap wajib tersedia sebagai fallback;
- jangan simpan QR/provisioning URI ke database, audit, atau log.

Logo di tengah QR membantu user mengenali aplikasi SITANGKAS, tetapi tidak
membuktikan keamanan. Security tetap berasal dari secret TOTP, validasi server,
rate limiting, session policy, dan audit.

## Rekomendasi 5 - Recovery Code Challenge Perlu, Tetapi Harus Ketat

Recovery code challenge berarti user yang sudah enroll MFA dapat memakai recovery
code satu kali di halaman `login.mfa` jika perangkat authenticator hilang.

Ini berguna karena:

- user tidak langsung terkunci total saat kehilangan authenticator;
- operator tidak selalu perlu reset MFA manual;
- fallback tetap berada di flow MFA yang sudah rate-limited dan diaudit.

Risikonya:

- jika recovery code disimpan user dengan buruk, code bisa dicuri;
- jika code bisa dipakai berulang, recovery code menjadi password kedua yang
  lemah;
- jika tidak diaudit, penyalahgunaan sulit dilacak.

Syarat implementasi:

- recovery code harus one-time use;
- simpan hashed atau encrypted, jangan plain text;
- tampilkan hanya sekali saat setup/regenerate;
- regenerate recovery codes hanya boleh untuk user yang sudah MFA verified via
  TOTP pada session saat ini;
- batasi jumlah code melalui `config('auth.mfa.recovery_codes.count')`;
- challenge recovery code harus memakai throttle yang sama atau lebih ketat dari
  challenge TOTP;
- setiap percobaan sukses/gagal harus dicatat ke `login_events`;
- setelah recovery code dipakai oleh Admin Super, sarankan atau wajibkan
  re-enrollment TOTP;
- jangan membuat recovery code menjadi cara login permanen.

Rekomendasi event audit:

- `mfa_challenge/success` dengan `mfa_method = recovery_code`;
- `mfa_challenge/failed` dengan failure code seperti `invalid_recovery_code`;
- `mfa_recovery_code_used/success` jika konstanta event khusus dibuat;
- `mfa_recovery_codes_regenerated/success` saat code baru dibuat.

## Rekomendasi 6 - Reset MFA Lebih Aman Dimulai Dari Artisan

Untuk fase awal, reset MFA sebaiknya dibuat sebagai command Artisan, bukan UI
browser admin umum.

Contoh command yang direkomendasikan:

```bash
php artisan auth:mfa-reset --nik=3513170305950003
php artisan auth:mfa-reset --user-id=1
php artisan auth:mfa-reset --nik=3513170305950003 --actor-user-id=1 --reason="Authenticator hilang" --force
```

Perilaku command yang disarankan:

- cari user berdasarkan `nik` atau `user_id`;
- tampilkan identitas target sebelum reset;
- minta konfirmasi eksplisit di terminal;
- di production, wajibkan opsi seperti `--force` atau konfirmasi tambahan;
- clear `mfa_secret`, `mfa_pending_secret`, recovery code, dan timestamp MFA;
- revoke session/remember token user jika policy mengharuskan;
- catat audit event melalui `RecordAuthenticationEvent`;
- tampilkan hasil singkat tanpa membocorkan secret baru.

Status implementasi saat ini:

- command `php artisan auth:mfa-reset` sudah tersedia;
- runbook penggunaan command berada di `MFA_RESET_COMMAND_RUNBOOK.md`;
- target user wajib memakai tepat salah satu opsi `--nik` atau `--user-id`;
- `--actor-user-id` optional untuk mengisi `actor_user_id` audit;
- `--reason` optional dan disimpan di metadata audit;
- `--force` wajib di production dan melewati prompt konfirmasi interaktif;
- action `App\Actions\Auth\ResetUserMfa` menghapus MFA secret, pending secret,
  recovery codes, timestamp MFA, memutar remember token, mengosongkan
  `remember_token_expires_at`, mengisi `sessions_invalidated_at`, menghapus
  session database target bila session driver database, dan mencatat event
  `mfa_reset` dengan `source_channel = console`.

Alasan lebih aman dari UI browser:

- akses server lebih terbatas daripada akses aplikasi web;
- mengurangi risiko admin web salah reset user;
- lebih mudah diaudit sebagai operasi operator;
- cocok untuk fase awal saat belum ada role/permission admin internal yang
  matang.

UI reset MFA boleh dibuat nanti setelah ada decision baru tentang permission,
approval, audit, dan operator workflow.

## Rekomendasi 7 - Alternatif Yang Pernah Dipertimbangkan

### Passkey/WebAuthn/FIDO2

Kelebihan:

- phishing-resistant;
- bisa device-bound;
- user tidak mengetik OTP;
- cocok untuk Admin Super jangka panjang.

Kekurangan:

- implementasi lebih kompleks;
- butuh flow register, challenge, recovery, dan browser compatibility yang
  matang;
- support perangkat user harus dipastikan;
- perlu kebijakan reset yang sangat hati-hati.

Rekomendasi:

Gunakan sebagai fase lanjutan untuk Admin Super atau role sangat sensitif,
setelah TOTP stabil.

### Security Key Fisik

Kelebihan:

- security paling kuat untuk akun sangat sensitif;
- benar-benar possession factor;
- risiko phishing rendah.

Kekurangan:

- butuh pembelian, distribusi, inventaris, dan SOP kehilangan key;
- user perlu membawa device fisik;
- onboarding dan reset lebih berat.

Rekomendasi:

Cocok untuk Admin Super tertinggi atau operator produksi, bukan rollout awal
untuk semua user.

### Device-Bound Authenticator

Kelebihan:

- paling mendekati kebutuhan "hanya satu device MFA";
- server bisa menyimpan identitas device terdaftar;
- bisa dikombinasikan dengan biometric/platform authenticator.

Kekurangan:

- tidak bisa dicapai dengan Google Authenticator TOTP biasa;
- perlu flow device registration, device revocation, dan recovery yang kuat;
- implementasinya lebih besar dari TOTP.

Rekomendasi:

Pertimbangkan jika requirement satu device MFA menjadi wajib secara formal.

### Push MFA Dengan Device Registration

Kelebihan:

- UX lebih mudah dari mengetik OTP;
- server bisa mengikat perangkat;
- bisa memakai number matching atau approval detail.

Kekurangan:

- butuh mobile app/provider push;
- butuh infrastruktur push notification;
- rawan MFA fatigue jika tanpa number matching;
- lebih mahal dan kompleks.

Rekomendasi:

Belum perlu untuk fase sekarang. Masuk akal jika SITANGKAS nanti punya mobile
app resmi atau provider MFA enterprise.

## Rekomendasi 8 - Urutan Implementasi Lanjutan

Urutan paling proper setelah kondisi sekarang:

1. Review manual flow MFA Admin Super yang belum enroll dan sudah enroll.
2. Recovery-code challenge di halaman `login.mfa` sudah diimplementasikan.
3. Command Artisan reset MFA untuk operator sudah diimplementasikan.
4. Decision `MFA-11` dan action `RegenerateMfaRecoveryCodes` sudah
   diimplementasikan.
5. Implementasikan halaman profile/security sesuai
   `PROFILE_SECURITY_DECISIONS.md` sebagai rumah status MFA user.
6. Tambahkan route/controller/view recovery-code regeneration di halaman
   profile/security dengan middleware `mfa.verified` dan action
   `RegenerateMfaRecoveryCodes`.
7. Evaluasi WebAuthn/security key untuk Admin Super tingkat tinggi.

Catatan penting: jangan membuat, memodifikasi, atau menjalankan test suite/test
command tanpa konfirmasi eksplisit dari user.

## File Implementasi Yang Perlu Diketahui

- `config/auth.php` pada key `mfa`;
- `app/Services/Auth/MfaPolicy.php`;
- `app/Services/Auth/MfaSession.php`;
- `app/Services/Auth/TotpAuthenticator.php`;
- `app/Actions/Auth/StartTotpEnrollment.php`;
- `app/Actions/Auth/ConfirmTotpEnrollment.php`;
- `app/Actions/Auth/StartMfaChallenge.php`;
- `app/Actions/Auth/VerifyTotpChallenge.php`;
- `app/Actions/Auth/VerifyRecoveryCodeChallenge.php`;
- `app/Actions/Auth/RegenerateMfaRecoveryCodes.php`;
- `app/Actions/Auth/ResetUserMfa.php`;
- `app/Console/Commands/Auth/ResetUserMfaCommand.php`;
- `docs/01-authentication/MFA_RESET_COMMAND_RUNBOOK.md`;
- `docs/01-authentication/PROFILE_SECURITY_DECISIONS.md`;
- `app/Http/Controllers/Auth/TotpEnrollmentController.php`;
- `app/Http/Controllers/Auth/MfaChallengeController.php`;
- `app/Http/Requests/Auth/ConfirmTotpEnrollmentRequest.php`;
- `app/Http/Requests/Auth/VerifyMfaChallengeRequest.php`;
- `app/Http/Middleware/EnsureMfaVerified.php`;
- `resources/views/auth/mfa-setup.blade.php`;
- `resources/views/auth/mfa-challenge.blade.php`;
- `public/assets/img/Logo_Kota_Malang_color.png`;
- `routes/web.php`;
- `app/Providers/AppServiceProvider.php`;
- `app/Actions/Auth/RecordAuthenticationEvent.php`;
- `app/Models/LoginEvent.php`;
- `app/Models/User.php`.

## Larangan Untuk AI Agent

- Jangan menganggap `config/mfa.php` ada atau perlu dibuat; sumber policy resmi
  adalah `config('auth.mfa')`.
- Jangan melewati `MfaPolicy` saat menentukan apakah MFA wajib.
- Jangan melewati `MfaSession` saat membaca/menulis state verified MFA.
- Jangan melewati `TotpAuthenticator` untuk generate secret, provisioning URI,
  QR, atau verifikasi kode.
- Jangan membuka akses `login.post` untuk Admin Super sebelum MFA valid.
- Jangan membuat endpoint reset MFA browser tanpa decision permission dan audit.
- Jangan menampilkan QR/setup ulang kepada user yang sudah enroll MFA kecuali
  setelah reset resmi.
- Jangan memperlakukan remember-me sebagai MFA.
- Jangan menjalankan test suite tanpa konfirmasi eksplisit user.
