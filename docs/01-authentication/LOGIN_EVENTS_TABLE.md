# SITANGKAS — Dokumentasi Tabel `login_events`

## 1. Maksud tabel

`login_events` adalah log autentikasi lengkap dan terpisah dari `users`.

Setiap row adalah satu kejadian autentikasi pada waktu tertentu, misalnya:

- login berhasil;
- login gagal;
- login diblokir;
- logout;
- lockout;
- unlock;
- session timeout;
- session revoked;
- MFA challenge;
- MFA berhasil/gagal;
- impersonation mulai/selesai.

Tabel ini digunakan untuk:

- audit keamanan;
- investigasi insiden;
- monitoring login mencurigakan;
- rekonstruksi urutan kejadian;
- analisis perangkat dan jaringan;
- korelasi dengan log aplikasi, reverse proxy, dan SIEM;
- pelaporan kepatuhan.

---

## 2. Karakteristik wajib

### Append-only

Event baru ditambahkan dengan `INSERT`.

Event lama tidak diedit dan tidak di-soft-delete melalui aplikasi normal.

### User dapat null

`user_id` harus nullable karena:

- identifier login tidak ditemukan;
- akun sudah dihapus;
- event terjadi sebelum user berhasil diidentifikasi;
- data berasal dari sistem eksternal yang belum dipetakan.

### Event type terpisah dari result

Contoh:

| `event_type` | `result` | Makna |
|---|---|---|
| `login` | `success` | Login berhasil |
| `login` | `failed` | Kredensial atau kontrol login gagal |
| `login` | `blocked` | Request login diblokir sebelum autentikasi selesai |
| `logout` | `success` | Logout normal |
| `lockout` | `blocked` | Akun/rute diblokir karena rate limit atau kebijakan |
| `session_timeout` | `expired` | Session kedaluwarsa |
| `session_revoked` | `revoked` | Session dicabut |

Jangan menggunakan `result = logout`. Logout adalah jenis event, bukan hasil.

---

## 3. Kamus kolom

### 3.1 Identitas event

#### `id`

Primary key internal `BIGINT UNSIGNED`.

#### `event_uuid`

UUID unik untuk satu event.

Digunakan untuk:

- korelasi lintas layanan;
- referensi audit tanpa mengekspos ID berurutan;
- deduplikasi event dari queue/integrasi.

Event UUID harus dibuat sebelum insert dan tidak boleh berubah.

---

### 3.2 Subjek dan aktor

#### `user_id`

User yang menjadi subjek event.

Contoh:

- pengguna yang login;
- pengguna yang session-nya dicabut;
- pengguna yang di-impersonate.

Nullable.

#### `actor_user_id`

User yang menyebabkan tindakan terhadap subjek bila berbeda.

Contoh:

- administrator memulai impersonation terhadap user lain;
- administrator mencabut session user;
- administrator melakukan unlock akun.

Untuk login normal, biasanya null. Jangan otomatis menyalin `user_id` ke `actor_user_id`.

#### `login_identifier_type`

Jenis identifier yang dipakai:

- `nik`;
- `nip`;
- `email`;
- `username`;
- `token`;
- `service_account`.

#### `login_identifier`

Identifier yang digunakan pada proses login.

Rekomendasi:

- dienkripsi di layer aplikasi;
- dimasking ketika ditampilkan;
- nullable untuk event yang tidak memerlukan identifier, misalnya logout tertentu.

Jangan menyimpan password pada kolom ini.

#### `login_identifier_hash`

HMAC-SHA-256 dari identifier yang sudah dinormalisasi.

Digunakan untuk pencarian/korelasi tanpa membuka nilai identifier.

#### `user_context`

Snapshot JSON konteks user saat event terjadi.

Contoh:

```json
{
  "nama": "Nama Pengguna",
  "account_type": "personal",
  "status": "active",
  "roles": ["KPA"],
  "organization_unit_id": 25,
  "organization_unit_name": "Kelurahan Tlogowaru",
  "tahun_aktif": 2026
}
```

Gunakan snapshot karena role, unit, nama, atau status dapat berubah setelah event.

Jangan memasukkan password, token, cookie, passphrase, atau data sensitif yang tidak perlu.

---

### 3.3 Jenis kejadian dan hasil

#### `event_type`

Jenis kejadian autentikasi.

Nilai awal yang direkomendasikan:

- `login`;
- `logout`;
- `lockout`;
- `unlock`;
- `session_timeout`;
- `session_revoked`;
- `mfa_challenge`;
- `mfa_recovery_codes_regenerated`;
- `mfa_reset`;
- `mfa_verified`;
- `impersonation_started`;
- `impersonation_ended`.

#### `result`

Hasil event.

Nilai awal:

- `success`;
- `failed`;
- `blocked`;
- `expired`;
- `revoked`;
- `cancelled`.

#### `failure_code`

Kode kegagalan yang stabil dan machine-readable.

Contoh:

- `invalid_credentials`;
- `user_not_found`;
- `account_inactive`;
- `account_locked`;
- `password_expired`;
- `captcha_failed`;
- `rate_limited`;
- `mfa_failed`;
- `session_invalid`;
- `service_account_web_login_denied`.

Jangan menggunakan pesan bebas sebagai satu-satunya dasar analitik.

#### `message`

Pesan ringkas untuk manusia.

- Maksimal 500 karakter.
- Jangan memuat password, token, atau stack trace lengkap.
- Hindari membocorkan apakah identifier tertentu valid kepada UI publik; detail audit internal tetap dapat menggunakan `failure_code` yang sesuai kebijakan.

#### `attempt_number`

Nomor percobaan dalam **rangkaian kegagalan atau throttle window yang sedang aktif**.

Aturan:

- default `1`;
- bukan total login seumur hidup;
- reset setelah login berhasil atau window berakhir;
- harus konsisten dengan `users.consecutive_failed_login_count` bila user ditemukan;
- untuk logout/session event biasanya tetap `1`.

---

### 3.4 Metode autentikasi

#### `auth_guard`

Laravel guard atau konteks autentikasi, misalnya:

- `web`;
- `api`;
- `sanctum`;
- `admin`.

#### `auth_method`

Metode utama:

- `password`;
- `sso`;
- `token`;
- `api_key`;
- `passkey`;
- `remember_token`;
- `mfa_recovery_codes`;
- `mfa_reset`.

#### `auth_provider`

Provider autentikasi, misalnya:

- `eloquent`;
- `ldap`;
- `oauth`;
- nama IdP/SSO.

#### `remember_me`

Menandakan opsi remember-me pada login.

Nullable bila tidak relevan.

#### `mfa_method`

Metode MFA:

- `totp`;
- `email_otp`;
- `sms_otp`;
- `webauthn`;
- `recovery_code`.

#### `mfa_result`

Hasil MFA, misalnya:

- `success`;
- `failed`;
- `skipped`;
- `not_required`;
- `cancelled`.

Catatan MFA TOTP:

- `mfa_challenge` dipakai saat setup/challenge dimulai, diblokir, atau expired.
- `mfa_verified` dipakai saat kode TOTP/recovery code diverifikasi, baik hasilnya
  `success` maupun `failed`.
- `mfa_recovery_codes_regenerated` dipakai saat user yang sudah MFA verified via
  TOTP membuat recovery codes baru. Event ini tidak boleh menyimpan recovery code
  mentah.
- `mfa_reset` dipakai saat operator/admin teknis mereset enrollment MFA melalui
  command Artisan. Event ini harus memakai `source_channel = console` dan tidak
  boleh menyimpan secret/recovery code mentah.

---

### 3.5 Korelasi request, session, dan token

#### `request_id`

UUID untuk satu HTTP request.

Gunakan middleware agar setiap request memiliki request ID yang konsisten.

#### `correlation_id`

UUID untuk satu alur bisnis yang dapat melintasi beberapa request, queue, atau service.

#### `session_id_hash`

Hash/HMAC session ID.

- Jangan simpan session ID asli.
- Digunakan untuk menelusuri login, request, logout, timeout, dan revoke pada session yang sama.

#### `token_id_hash`

Hash/HMAC token identifier.

- Jangan simpan bearer token asli.
- Gunakan identifier token atau hash aman yang dapat dikorelasikan.

#### `source_channel`

Kanal sumber event:

- `web`;
- `mobile`;
- `api`;
- `cli`;
- `queue`;
- `sso`;
- `system`.

#### `route_name`

Nama route Laravel, misalnya `login.store`.

#### `request_path`

Path request tanpa secret query parameter.

Jangan menyimpan URL lengkap jika mengandung token atau data rahasia.

#### `http_method`

Metode HTTP, misalnya `POST`, `GET`, atau `DELETE`.

#### `http_status`

Status HTTP hasil request.

Contoh:

- `200`/`204` berhasil;
- `401` tidak terautentikasi;
- `403` dilarang;
- `422` validasi gagal;
- `429` rate limited.

---

### 3.6 Informasi jaringan

#### `ip_address`

IP client yang sudah diperoleh melalui konfigurasi trusted proxy yang benar.

#### `proxy_ip_address`

IP reverse proxy atau hop tepercaya terakhir.

#### `forwarded_for`

Array JSON daftar IP dari header forwarded.

Penting:

- jangan mempercayai header ini dari proxy yang tidak dipercaya;
- simpan hanya setelah normalisasi;
- batasi panjang/jumlah hop untuk mencegah abuse.

#### `network_asn`

Autonomous System Number, misalnya `AS7713`.

#### `network_organization`

Nama organisasi jaringan/ISP.

Sumber resmi saat enrichment aktif:

- `App\Services\Auth\IpGeolocationContext`;
- MaxMind GeoLite2 ASN local database;
- path database dari
  `config('auth.audit.login_events.enrichment.geoip.asn_database_path')`.

#### `country_code`

Kode negara ISO dua huruf.

#### `region`, `city`

Lokasi perkiraan dari IP.

Sumber resmi saat enrichment aktif:

- `App\Services\Auth\IpGeolocationContext`;
- MaxMind GeoLite2 City local database;
- path database dari
  `config('auth.audit.login_events.enrichment.geoip.city_database_path')`.

Data geolokasi IP bersifat perkiraan dan tidak boleh dianggap sebagai lokasi presisi pengguna.

#### `is_vpn`, `is_proxy`, `is_tor`

Hasil deteksi opsional dari sumber keamanan jaringan.

Sumber resmi:

- `App\Services\Auth\IpRiskContext`;
- `config('auth.audit.login_events.enrichment.ip_risk')`;
- `docs/01-authentication/IP_RISK_DECISIONS.md`.

Nullable berarti belum diperiksa, provider disabled, provider gagal, IP tidak
public, allowlist dilewati, atau data tidak tersedia.

Status saat ini: provider IP risk sengaja di-hold. Selama
`AUTH_LOGIN_EVENT_IP_RISK_ENABLED=false` dan provider `none`, nilai `null`
adalah kondisi yang benar.

`false` hanya boleh tersimpan jika provider sudah memeriksa dan menyatakan
tidak terdeteksi.

#### `risk_score`

Skor risiko integer 0–100.

- `0`: risiko terendah;
- `100`: risiko tertinggi;
- nullable: belum dihitung.

Definisi skor harus terdokumentasi pada service keamanan. Jangan mencampur skala provider yang berbeda tanpa normalisasi.

Saat ini `risk_score` tetap `null` karena provider IP risk di-hold dan belum
ada provider/scoring resmi 0-100 yang disetujui.

---

### 3.7 Perangkat dan user agent

#### `user_agent`

Raw user-agent string.

Batasi penggunaan dan jangan menganggapnya selalu benar karena dapat dipalsukan.

#### `device_type`

Contoh:

- `Desktop`;
- `Mobile`;
- `Tablet`;
- `Bot`;
- `Unknown`.

#### `device_name`

Nama/model perangkat jika dapat diidentifikasi secara wajar.

#### `browser_name`, `browser_version`

Nama dan versi browser hasil parsing.

#### `platform_name`, `platform_version`

Nama dan versi OS/platform.

#### `client_timezone`

Timezone yang dilaporkan client, misalnya `Asia/Jakarta`.

#### `accept_language`

Header preferensi bahasa client.

#### `device_fingerprint_hash`

Hash fingerprint first-party yang terbatas untuk keamanan.

Larangan:

- jangan menyimpan fingerprint mentah;
- jangan menggunakan teknik invasif tanpa dasar kebutuhan dan kebijakan privasi;
- jangan menjadikan fingerprint satu-satunya bukti identitas.

---

### 3.8 CAPTCHA dan perlindungan bot

#### `captcha_provider`

Contoh:

- `recaptcha_v3`;
- `turnstile`;
- `hcaptcha`.

#### `captcha_score`

Decimal 0.000–1.000 bila provider memakai skor.

Nullable bila tidak diperiksa atau provider tidak memakai skor.

#### `captcha_action`

Action yang diverifikasi, misalnya `login`.

#### `captcha_success`

Hasil validasi CAPTCHA.

Nullable bila CAPTCHA tidak digunakan.

#### `captcha_error_codes`

Array JSON kode error dari provider.

Jangan simpan token CAPTCHA mentah.

---

### 3.9 Konteks aplikasi dan integritas

#### `application_version`

Versi aplikasi yang menghasilkan event.

Sumber resmi:

- `config('auth.audit.login_events.enrichment.application.version')`;
- `config('auth.audit.login_events.enrichment.application.build_number')`;
- `config('auth.audit.login_events.enrichment.application.build_commit')`.

Nilai akhir dibentuk oleh `App\Services\Auth\ApplicationVersionContext`.
Contoh hasil: `2026.08.04+build.17.sha.a1b2c3d4e5f6`.
Jika metadata deploy belum tersedia, nilai boleh `null`.

#### `environment`

Contoh:

- `production`;
- `staging`;
- `development`;
- `testing`.

#### `server_node`

Nama node/server/container yang menangani event.

#### `metadata`

JSON untuk atribut tambahan yang jarang dicari.

Gunakan hanya bila:

- atribut belum pantas menjadi kolom permanen;
- tidak sering digunakan pada filter/report;
- tidak mengandung secret.

Bila atribut sering dipakai pada `WHERE`, dashboard, agregasi, atau index, tambahkan sebagai kolom normal pada migration baru.

#### `event_hash`

Hash/HMAC payload event yang sudah dinormalisasi.

Digunakan untuk pemeriksaan integritas tambahan.

Sumber resmi:

- `App\Services\Auth\LoginEventIntegrity`;
- `config('auth.audit.login_events.enrichment.integrity.event_hash_enabled')`;
- `AUDIT_HASH_KEY`.

Aturan payload:

- dihitung sebelum insert;
- memakai HMAC-SHA256;
- mengecualikan `event_hash`;
- mengecualikan `login_identifier` karena field itu memakai encrypted cast;
- menyertakan `login_identifier_hash`;
- menyertakan payload version `v1`.

Tidak menggantikan:

- permission database;
- audit storage eksternal;
- backup;
- digital signature resmi.

#### `occurred_at`

Waktu kejadian sebenarnya.

Menggunakan presisi mikrodetik.

#### `retention_until`

Tanggal event memenuhi syarat retensi/pengarsipan/penghapusan.

Bukan perintah otomatis untuk menghapus tanpa kebijakan resmi.

Sumber resmi:

- `App\Services\Auth\LoginEventRetention`;
- `config('auth.audit.login_events.enrichment.integrity.retention_days')`.

Jika `AUTH_LOGIN_EVENT_RETENTION_DAYS` kosong atau bukan angka positif, nilai
tetap `null`.

#### `created_at`

Waktu record ditulis ke database.

Tidak ada `updated_at` dan `deleted_at` karena tabel append-only.

---

## 4. Relasi

- `user_id -> users.id`, nullable, `nullOnDelete`;
- `actor_user_id -> users.id`, nullable, `nullOnDelete`.

`nullOnDelete` menjaga log tetap ada ketika record user dihapus secara fisik.

Snapshot `user_context` dan identifier hash membantu audit tetap bermakna saat relasi user sudah null.

---

## 5. Index dan pola query

### `ix_login_events_user_time`

```text
user_id, occurred_at
```

Riwayat event seorang user.

### `ix_login_events_actor_time`

```text
actor_user_id, occurred_at
```

Aktivitas administratif/impersonation oleh aktor tertentu.

### `ix_login_events_identifier_time`

```text
login_identifier_hash, occurred_at
```

Percobaan login untuk identifier yang sama, termasuk ketika user tidak ditemukan.

### `ix_login_events_ip_time`

```text
ip_address, occurred_at
```

Investigasi aktivitas dari satu IP.

### `ix_login_events_type_result_time`

```text
event_type, result, occurred_at
```

Dashboard dan laporan login berhasil/gagal/blocked.

### `ix_login_events_session_time`

```text
session_id_hash, occurred_at
```

Rekonstruksi lifecycle satu session.

### Index tunggal

- `request_id`: korelasi request;
- `occurred_at`: timeline/retensi;
- `retention_until`: proses retensi.

Jangan menambahkan index ke setiap kolom boolean atau metadata tanpa bukti pola query.

---

## 6. Matriks event yang direkomendasikan

| Kondisi | `event_type` | `result` | `failure_code` |
|---|---|---|---|
| Login benar | `login` | `success` | null |
| Password salah | `login` | `failed` | `invalid_credentials` |
| Identifier tidak ditemukan | `login` | `failed` | `user_not_found` |
| Akun inactive | `login` | `blocked` | `account_inactive` |
| Akun locked | `login` | `blocked` | `account_locked` |
| Password expired | `login` | `blocked` | `password_expired` |
| CAPTCHA gagal | `login` | `blocked` atau `failed` sesuai kebijakan | `captcha_failed` |
| Rate limit | `lockout` | `blocked` | `rate_limited` |
| Logout manual | `logout` | `success` | null |
| Session habis | `session_timeout` | `expired` | `session_expired` |
| Admin cabut session | `session_revoked` | `revoked` | `revoked_by_admin` |
| MFA salah | `mfa_verified` | `failed` | `mfa_failed` |
| MFA berhasil | `mfa_verified` | `success` | null |
| Recovery codes dibuat ulang | `mfa_recovery_codes_regenerated` | `success` | null |
| Reset MFA via Artisan | `mfa_reset` | `success` | null |

---

## 7. Contoh payload event

### Login berhasil

```php
[
    'event_uuid' => (string) Str::uuid(),
    'user_id' => $user->id,
    'login_identifier_type' => 'nik',
    'login_identifier' => encrypt($normalizedIdentifier),
    'login_identifier_hash' => $identifierHash,
    'user_context' => [
        'nama' => $user->nama,
        'account_type' => $user->account_type,
        'status' => $user->status,
        'tahun_aktif' => $user->tahun_aktif,
    ],
    'event_type' => 'login',
    'result' => 'success',
    'attempt_number' => 1,
    'auth_guard' => 'web',
    'auth_method' => 'password',
    'remember_me' => $remember,
    'request_id' => request()->attributes->get('request_id'),
    'session_id_hash' => $sessionHash,
    'source_channel' => 'web',
    'route_name' => request()->route()?->getName(),
    'request_path' => request()->path(),
    'http_method' => request()->method(),
    'http_status' => 200,
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'occurred_at' => now(),
];
```

### Login gagal untuk user tidak ditemukan

```php
[
    'event_uuid' => (string) Str::uuid(),
    'user_id' => null,
    'login_identifier_type' => 'nik',
    'login_identifier' => encrypt($normalizedIdentifier),
    'login_identifier_hash' => $identifierHash,
    'event_type' => 'login',
    'result' => 'failed',
    'failure_code' => 'user_not_found',
    'message' => 'Autentikasi tidak berhasil.',
    'attempt_number' => $attemptNumber,
    'auth_guard' => 'web',
    'auth_method' => 'password',
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'occurred_at' => now(),
];
```

Pesan kepada pengguna sebaiknya tetap generik untuk mencegah account enumeration.

---

## 8. Query audit yang umum

### Histori autentikasi seorang user

```sql
SELECT *
FROM login_events
WHERE user_id = ?
ORDER BY occurred_at DESC;
```

### Login gagal dari IP tertentu dalam 24 jam

```sql
SELECT *
FROM login_events
WHERE ip_address = ?
  AND event_type = 'login'
  AND result IN ('failed', 'blocked')
  AND occurred_at >= NOW() - INTERVAL 24 HOUR
ORDER BY occurred_at DESC;
```

### Identifier yang sering gagal

```sql
SELECT login_identifier_hash, COUNT(*) AS total_failed
FROM login_events
WHERE event_type = 'login'
  AND result IN ('failed', 'blocked')
  AND occurred_at >= NOW() - INTERVAL 1 DAY
GROUP BY login_identifier_hash
HAVING COUNT(*) >= 5
ORDER BY total_failed DESC;
```

### Lifecycle session

```sql
SELECT event_type, result, failure_code, occurred_at, ip_address
FROM login_events
WHERE session_id_hash = ?
ORDER BY occurred_at;
```

### Event risiko tinggi

```sql
SELECT *
FROM login_events
WHERE risk_score >= 80
  AND occurred_at >= NOW() - INTERVAL 7 DAY
ORDER BY occurred_at DESC;
```

---

## 9. Privasi dan keamanan

AI agent harus mengikuti aturan berikut:

1. Jangan menampilkan identifier lengkap kepada user yang tidak berwenang.
2. Masking contoh NIK: `3573********0004`.
3. Batasi akses tabel log berdasarkan role audit/security.
4. Jangan mengekspos raw `user_context` dan `metadata` ke semua pengguna.
5. Jangan menyimpan password, token, cookie, passphrase, atau request body mentah.
6. Jangan menggunakan geolocation IP sebagai fakta pasti.
7. Jangan menganggap user agent dan fingerprint dapat dipercaya sepenuhnya.
8. Jangan menghapus event hanya karena user di-soft-delete.
9. Gunakan enkripsi untuk `login_identifier` dan HMAC untuk kolom hash.
10. Audit pembacaan/export log sensitif bila fitur audit umum telah tersedia.

---

## 10. Pemetaan struktur legacy

| Data legacy | Target baru |
|---|---|
| `user_id` | `user_id` |
| `nik` | `login_identifier`, `login_identifier_hash` |
| `ip_address` | `ip_address` |
| `user_agent` | `user_agent` |
| `device` | `device_type` atau `device_name` |
| `browser` | `browser_name` |
| `platform` | `platform_name` |
| `status = success` | `event_type = login`, `result = success` |
| `status = failed` | `event_type = login`, `result = failed` |
| `status = blocked` | `event_type = login/lockout`, `result = blocked` |
| `status = logout` | `event_type = logout`, `result = success` |
| `attempt` | `attempt_number` setelah makna dinormalisasi |
| `recaptcha_score` | `captcha_score` |
| `recaptcha_action` | `captcha_action` |
| `message` | `message`, dan bentuk kode pada `failure_code` |
| `created_at` | `occurred_at`, `created_at` |

Data legacy yang ambigu harus diberi catatan pada `metadata`, bukan ditebak sebagai fakta baru.

---

## 11. Larangan bagi AI agent

AI agent dilarang:

- menambahkan `updated_at` hanya untuk mengedit event;
- menambahkan `softDeletes` untuk penggunaan normal;
- menyimpan password atau token mentah;
- menjadikan `message` sebagai pengganti `failure_code`;
- mengubah event lama setelah investigasi;
- mempercayai forwarded headers tanpa trusted proxy;
- mengisi `is_vpn = false` ketika data sebenarnya belum diperiksa; gunakan null;
- memasukkan data yang sering dicari ke `metadata` hanya demi menghindari migration;
- menggunakan `attempt_number` sebagai penghitung global;
- menghapus event ketika relasi user hilang.
