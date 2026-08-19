# MFA Reset Command Runbook

Dokumen ini adalah panduan operator/admin teknis untuk mereset MFA user
SITANGKAS melalui Artisan.

Catatan state saat ini: reset MFA dari browser sudah tersedia melalui
Management Users khusus Admin Super. Command ini tetap dipertahankan sebagai
jalur operator/server, fallback teknis, dan SOP production.

AI agent wajib membaca dokumen ini sebelum menjalankan, mengubah, atau
merekomendasikan penggunaan command reset MFA.

## Command Resmi

```bash
php artisan auth:mfa-reset --nik=3513170305950003
php artisan auth:mfa-reset --user-id=1
php artisan auth:mfa-reset --nik=3513170305950003 --actor-user-id=1 --reason="Authenticator hilang" --force
```

Command:

```bash
php artisan auth:mfa-reset
```

Class:

```text
app/Console/Commands/Auth/ResetUserMfaCommand.php
```

Action:

```text
app/Actions/Auth/ResetUserMfa.php
```

## Kapan Command Ini Dipakai

Gunakan command ini hanya ketika:

- user kehilangan akses Google Authenticator/aplikasi TOTP;
- recovery code user habis, hilang, atau tidak bisa dipakai;
- enrollment MFA user rusak atau pending setup tersangkut;
- operator perlu mencabut semua session user setelah insiden MFA;
- Admin Super perlu dipaksa setup MFA ulang.

Jangan gunakan command ini untuk:

- mengganti password user;
- membuka lock akun;
- mengganti posisi/jabatan user;
- membuat recovery code baru tanpa reset MFA;
- bypass MFA Admin Super;
- menjalankan maintenance massal tanpa prosedur approval terpisah.

## Opsi Command

| Opsi | Wajib | Fungsi |
|---|---:|---|
| `--nik=` | Salah satu dari `--nik` atau `--user-id` | Target user berdasarkan NIK. |
| `--user-id=` | Salah satu dari `--nik` atau `--user-id` | Target user berdasarkan primary key `users.id`. |
| `--actor-user-id=` | Tidak | User operator/admin teknis yang dicatat sebagai `actor_user_id` audit. |
| `--reason=` | Tidak, tetapi direkomendasikan | Alasan reset yang disimpan di metadata audit. |
| `--force` | Wajib di production | Melewati prompt konfirmasi dan wajib untuk production. |

Aturan target:

- wajib memakai tepat salah satu dari `--nik` atau `--user-id`;
- jika dua-duanya dipakai, command gagal;
- jika tidak ada target, command gagal;
- jika target tidak ditemukan, command gagal tanpa mengubah data.

## Checklist Sebelum Reset

1. Pastikan permintaan reset berasal dari kanal resmi.
2. Cocokkan identitas user target, minimal NIK dan nama.
3. Pastikan user memang kehilangan authenticator atau recovery code.
4. Tentukan `actor-user-id` operator/admin teknis bila tersedia.
5. Tulis `reason` yang ringkas dan jelas.
6. Pastikan operator memahami bahwa user akan logout dan harus setup MFA ulang.

Contoh reason yang baik:

```bash
--reason="Authenticator hilang"
--reason="Recovery code habis"
--reason="Reset MFA atas permintaan user melalui tiket INC-2026-001"
```

Jangan memasukkan password, secret, OTP, recovery code mentah, token, atau data
rahasia lain ke `--reason`.

## Cara Pakai Di Local/Non-Production

Mode interaktif:

```bash
php artisan auth:mfa-reset --nik=3513170305950003 --actor-user-id=1 --reason="Authenticator hilang"
```

Command akan menampilkan tabel identitas target dan meminta konfirmasi:

```text
Reset MFA untuk Nama User (3513170305950003) dan revoke session aktif?
```

Jawab `yes` atau `y` hanya jika target sudah benar.

Mode non-interaktif:

```bash
php artisan auth:mfa-reset --nik=3513170305950003 --actor-user-id=1 --reason="Authenticator hilang" --force --no-interaction
```

Gunakan mode non-interaktif hanya untuk prosedur operator yang sudah jelas.

## Cara Pakai Di Production

Di production, `--force` wajib.

```bash
php artisan auth:mfa-reset --nik=3513170305950003 --actor-user-id=1 --reason="Authenticator hilang" --force
```

Rekomendasi production:

- selalu isi `--actor-user-id`;
- selalu isi `--reason`;
- jalankan dari terminal server atau environment operator resmi;
- simpan nomor tiket/helpdesk di `--reason` jika ada;
- jangan menjalankan command massal tanpa decision/approval terpisah.

Jika `--force` tidak diberikan di production, command harus berhenti dengan
pesan:

```text
Production wajib memakai opsi --force untuk reset MFA.
```

## Efek Ke Database

Command akan mengubah user target:

- `mfa_secret` menjadi null;
- `mfa_enabled_at` menjadi null;
- `mfa_confirmed_at` menjadi null;
- `mfa_last_used_at` menjadi null;
- `mfa_recovery_codes` menjadi null;
- `mfa_recovery_codes_generated_at` menjadi null;
- `mfa_pending_secret` menjadi null;
- `mfa_pending_secret_created_at` menjadi null;
- `remember_token` dirotasi;
- `remember_token_expires_at` menjadi null;
- `sessions_invalidated_at` diisi waktu reset;
- `updated_by_user_id` diisi dari `--actor-user-id` jika diberikan.

Jika session driver adalah `database` dan tabel `sessions` punya kolom
`user_id`, command juga menghapus session database milik user target.

## Efek Ke User

Setelah reset:

- user harus login ulang jika session aktif ikut dicabut;
- remember-me lama tidak berlaku lagi;
- user harus setup MFA ulang pada flow berikutnya jika policy mewajibkan MFA;
- recovery code lama tidak bisa dipakai lagi;
- QR MFA lama tidak boleh dianggap valid.

Untuk Admin Super, reset MFA berarti user akan diarahkan ke setup MFA sebelum
boleh masuk `login.post`.

## Audit Event

Command wajib mencatat event ke `login_events`.

Nilai utama:

| Field | Nilai |
|---|---|
| `event_type` | `mfa_reset` |
| `result` | `success` |
| `auth_method` | `mfa_reset` |
| `mfa_method` | `totp` |
| `mfa_result` | `revoked` |
| `source_channel` | `console` |
| `route_name` | `auth:mfa-reset` |
| `request_path` | `artisan auth:mfa-reset` |
| `http_method` | `CONSOLE` |

Metadata audit berisi informasi non-secret seperti:

- `target_user_id`;
- `target_nik`;
- `target_email`;
- `mfa_was_enrolled`;
- `pending_enrollment_was_present`;
- `previous_recovery_code_count`;
- `revoked_database_session_count`;
- `remember_token_rotated`;
- `sessions_invalidated_at`;
- `reason`.

Audit tidak boleh berisi:

- secret TOTP;
- pending secret;
- OTP;
- recovery code mentah;
- remember token;
- session ID mentah;
- password.

## Checklist Setelah Reset

1. Pastikan output command menunjukkan `MFA user berhasil direset`.
2. Catat `Audit event ID` dari output command.
3. Beri tahu user untuk login ulang.
4. Minta user setup MFA ulang melalui halaman resmi aplikasi.
5. Untuk Admin Super, pastikan user tidak dapat masuk `login.post` sebelum MFA
   setup/verified.
6. Jika reset dilakukan karena insiden, lanjutkan investigasi audit login user.

## Contoh Output Berhasil

Output berhasil akan menampilkan tabel ringkas seperti:

```text
MFA user berhasil direset.

Target user ID             1
NIK                        3513170305950003
Nama                       Admin Super
MFA sebelumnya aktif       ya
Pending setup sebelumnya   tidak
Recovery code lama         8
Session database direvoke  1
Remember token dirotasi    ya
Audit event ID             123
Reset at                   2026-08-02 22:00:00
```

Jangan meminta command menampilkan secret baru. Reset MFA tidak membuat secret
baru; secret baru dibuat saat user melakukan setup MFA ulang.

## Error Yang Umum

### Target Tidak Jelas

Penyebab:

- `--nik` dan `--user-id` tidak diberikan;
- `--nik` dan `--user-id` diberikan bersamaan.

Pesan:

```text
Gunakan tepat salah satu opsi: --nik atau --user-id.
```

Solusi:

Jalankan ulang dengan tepat satu target.

### User Target Tidak Ditemukan

Pesan:

```text
User target tidak ditemukan.
```

Solusi:

Periksa NIK atau user ID di database/sumber resmi.

### Actor User Tidak Ditemukan

Pesan:

```text
Actor user tidak ditemukan.
```

Solusi:

Periksa `--actor-user-id` atau jalankan tanpa opsi itu jika operator belum punya
akun user internal yang sesuai.

### Production Tanpa Force

Pesan:

```text
Production wajib memakai opsi --force untuk reset MFA.
```

Solusi:

Jalankan ulang dengan `--force` setelah target dan alasan diverifikasi.

## Larangan Untuk AI Agent

- Jangan menjalankan command reset MFA tanpa instruksi eksplisit user/operator.
- Jangan menebak NIK, user ID, atau actor user ID.
- Jangan menjalankan reset massal tanpa decision/approval terpisah.
- Jangan memperluas UI reset MFA browser di luar Management Users Admin Super
  tanpa decision permission/approval baru.
- Jangan membuat, memodifikasi, atau menjalankan test suite tanpa konfirmasi
  eksplisit user.
- Jangan menulis secret, OTP, recovery code mentah, token, atau session ID mentah
  ke dokumentasi, log, audit, atau output command.
