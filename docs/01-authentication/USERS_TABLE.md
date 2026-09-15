# SITANGKAS — Dokumentasi Tabel `users`

## 1. Maksud tabel

Tabel `users` adalah master akun autentikasi SITANGKAS berdasarkan struktur legacy yang sudah ada.

Tabel ini menyimpan:

- identitas dasar pengguna;
- kredensial autentikasi yang sudah di-hash;
- tipe dan status akun;
- state keamanan akun saat ini;
- ringkasan login terakhir;
- kebijakan password;
- verifikasi identitas;
- sumber data dan sinkronisasi;
- aktor administrasi akun.

Tabel ini **tidak menyimpan seluruh histori login**. Histori autentikasi disimpan pada `login_events`.

---

## 2. Aturan utama bagi AI agent

1. Perlakukan satu row `users` sebagai **current account state**.
2. Jangan menambahkan satu kolom untuk setiap login atau logout.
3. Jangan menyimpan array histori login pada JSON di tabel ini.
4. Jangan menyimpan password asli, token asli, cookie, atau passphrase TTE.
5. Gunakan `status` untuk keadaan administratif akun dan kolom lock untuk keadaan keamanan saat ini.
6. Gunakan `deleted_at` hanya untuk soft delete data akun, bukan untuk sekadar menonaktifkan pengguna.
7. Untuk menonaktifkan akun yang masih harus dipertahankan, gunakan `status = inactive` atau `suspended`.
8. Setiap perubahan administratif penting tetap perlu ditulis pada `audit_logs` ketika tabel audit umum tersedia.

---

## 3. Kamus kolom

### 3.1 Primary key

#### `id`

- Tipe: `BIGINT UNSIGNED`.
- Fungsi: primary key internal.
- Digunakan oleh foreign key pada tabel lain.
- Jangan ditampilkan sebagai bukti identitas pengguna.

---

### 3.2 Identitas legacy

#### `nik`

- Tipe: string maksimal 25 karakter.
- Unique.
- Digunakan sebagai identitas utama pada struktur legacy.
- Tidak boleh berisi password atau identifier sementara yang tidak terdokumentasi.
- Validasi dan normalisasi dilakukan di layer aplikasi.

#### `nip`

- Tipe: string maksimal 30 karakter.
- Nullable dan indexed.
- Dapat kosong untuk pengguna non-ASN atau akun fungsional/service.
- Belum dibuat unique karena data legacy masih memiliki kemungkinan duplikasi yang harus dibersihkan dan diverifikasi.

#### `nama`

- Tipe: string.
- Indexed untuk pencarian awalan dan pengurutan tertentu.
- Menyimpan nama pengguna atau nama akun sesuai struktur legacy.

#### `email`

- Nullable dan unique.
- Digunakan untuk komunikasi akun, verifikasi, atau reset password bila tersedia.
- Jangan mengasumsikan seluruh pengguna memiliki email karena data legacy sebagian besar belum memilikinya.

---

### 3.3 Klasifikasi dan status akun

#### `account_type`

Nilai awal:

- `personal`: akun pribadi milik satu orang;
- `functional`: akun fungsi legacy atau akun bersama dalam masa transisi;
- `service`: akun integrasi atau proses otomatis;
- `emergency`: akun darurat/break-glass.

Gunakan PHP backed enum dan validation rule agar nilai konsisten.

#### `status`

Nilai awal:

- `pending`: akun dibuat tetapi belum siap digunakan;
- `active`: akun dapat digunakan jika kontrol lain terpenuhi;
- `inactive`: akun dinonaktifkan secara administratif;
- `locked`: akun sedang dikunci karena kontrol keamanan;
- `suspended`: akun dihentikan sementara karena alasan administratif atau investigasi.

Status `active` tidak otomatis berarti login pasti diterima. Sistem juga harus memeriksa:

- `locked_until`;
- masa berlaku password;
- `must_change_password` sesuai alur;
- soft delete;
- kebijakan role/unit lain.

#### `status_changed_at`

Waktu perubahan status akun terakhir.

#### `status_changed_by_user_id`

User administrator yang melakukan perubahan status terakhir.

- Self-reference ke `users.id`.
- Nullable agar proses sistem atau migrasi legacy tetap dapat dicatat.
- `nullOnDelete` mempertahankan akun yang diubah meskipun aktor kemudian dihapus.

#### `status_reason`

Alasan perubahan status dalam bentuk teks singkat dan jelas.

Contoh:

- `Pegawai pindah unit kerja`;
- `Akun dikunci sementara untuk investigasi`;
- `Akun functional legacy dihentikan`.

Jangan simpan secret atau data sensitif yang tidak diperlukan.

---

### 3.4 Kredensial dan kebijakan password

#### `password`

- Harus berisi hasil hashing Laravel `Hash::make()`.
- Tidak boleh dienkripsi reversibel.
- Tidak boleh dicatat pada audit log.

#### `password_changed_at`

Waktu terakhir password benar-benar diganti.

#### `password_expires_at`

Waktu kedaluwarsa password menurut kebijakan yang berlaku.

- Nullable bila tidak ada masa kedaluwarsa.
- Jangan menghitung masa berlaku hanya dari `updated_at`.

#### `must_change_password`

Bernilai `true` ketika pengguna wajib mengganti password, misalnya setelah:

- pembuatan akun;
- reset administrator;
- migrasi akun legacy;
- insiden keamanan.

Set menjadi `false` setelah pengguna berhasil mengganti password melalui alur resmi.

#### `password_reset_at`

Waktu reset password terakhir oleh administrator atau proses sistem.

Pada flow Management Users saat ini, Admin Super dapat menjalankan reset
password administratif. Flow tersebut mengganti password ke temporary password,
menandai `must_change_password = true`, mengisi kolom reset ini, mencabut
session target, dan menampilkan temporary password satu kali di UI.

#### `password_reset_by_user_id`

Aktor yang melakukan reset password.

- Jangan simpan password baru pada log.
- Jangan simpan temporary password pada audit/log/metadata.
- Setelah reset, biasanya set `must_change_password = true`.

#### `sessions_invalidated_at`

Waktu ketika seluruh session lama dianggap tidak berlaku.

Digunakan untuk:

- logout dari semua perangkat;
- reset password;
- perubahan hak sensitif;
- incident response.

Saat memvalidasi session/token, bandingkan waktu penerbitannya dengan kolom ini bila mekanisme aplikasi mendukung.

#### `remember_token`

Kolom standar Laravel untuk remember-me.

Jangan pernah mencatat nilainya pada log.

#### `remember_token_expires_at`

Waktu server-side expiry untuk remember-me.

Aturan:

- default durasi remember adalah 24 jam melalui `AUTH_REMEMBER_ME_DURATION_MINUTES=1440`;
- kolom ini harus diisi ketika remember-me diterima untuk real active position
  non-Admin Super;
- kolom ini harus dikosongkan ketika remember-me ditolak, logout, token dirotasi,
  atau user berpindah ke real active position Admin Super;
- jika request via remember cookie melewati waktu ini, sistem harus logout,
  membersihkan token/cookie, dan mencatat audit session revoked.

#### `email_verified_at`

Waktu verifikasi email.

Nullable karena data legacy tidak seluruhnya memiliki email.

---

### 3.5 State keamanan akun terkini

#### `consecutive_failed_login_count`

Jumlah gagal login berturut-turut untuk akun saat ini.

Aturan:

- bertambah pada kegagalan yang relevan;
- reset ke `0` setelah login berhasil;
- bukan jumlah gagal login seumur hidup;
- histori setiap kegagalan ada di `login_events`.

#### `last_failed_login_at`

Waktu kegagalan login terakhir untuk akun yang ditemukan.

#### `locked_at`

Waktu akun mulai dikunci.

#### `locked_until`

Waktu berakhirnya penguncian otomatis.

- Nullable untuk tidak terkunci atau lock tanpa batas waktu.
- Aplikasi harus menentukan kebijakan unlock otomatis dengan jelas.

#### `lock_reason`

Alasan penguncian, misalnya:

- `too_many_failed_attempts`;
- `manual_security_lock`;
- `suspicious_activity`.

Gunakan kode atau teks pendek yang konsisten.

#### `last_login_at`

Waktu login berhasil terakhir.

Kolom ini adalah denormalisasi untuk dashboard/query cepat. Detail lengkap login terakhir tetap ada di `login_events`.

Jangan menganggap null berarti akun tidak pernah ada; akun legacy dapat memiliki histori yang belum diimpor.

---

### 3.6 MFA TOTP

Kolom MFA disiapkan untuk TOTP kompatibel Google Authenticator. Policy resmi
berada di `config('auth.mfa')`.

Untuk rekomendasi implementasi lanjutan seperti recovery-code challenge, reset
MFA Artisan, QR branded, WebAuthn, security key, device-bound authenticator, dan
push MFA, baca `MFA_RECOMMENDATIONS.md`.

#### `mfa_enabled_at`

Waktu MFA aktif untuk user.

#### `mfa_confirmed_at`

Waktu user berhasil mengonfirmasi setup MFA dengan kode TOTP pertama.

#### `mfa_last_used_at`

Waktu MFA terakhir berhasil dipakai.

#### `mfa_secret`

Secret TOTP yang harus disimpan encrypted pada model.

Larangan:

- jangan dicatat di audit/log;
- jangan dikirim ke frontend setelah setup selesai;
- jangan dipakai tanpa cast encrypted di model.

#### `mfa_recovery_codes`

Recovery codes yang harus disimpan encrypted/array atau hashed sesuai
implementasi. Recovery code mentah hanya boleh ditampilkan sekali saat dibuat.

#### `mfa_recovery_codes_generated_at`

Waktu recovery codes terakhir dibuat ulang.

#### `mfa_pending_secret`

Secret sementara saat setup MFA belum dikonfirmasi.

#### `mfa_pending_secret_created_at`

Waktu pending secret dibuat. Pending secret harus kedaluwarsa sesuai policy setup
dan tidak boleh dipakai sebagai MFA aktif sebelum kode pertama valid.

---

### 3.7 Verifikasi identitas

#### `identity_verified_at`

Waktu identitas pengguna diverifikasi.

Verifikasi dapat berupa pencocokan dengan:

- dokumen resmi;
- BKPSDM;
- administrator berwenang;
- sumber resmi lain.

#### `identity_verified_by_user_id`

User yang memverifikasi identitas.

Nullable untuk verifikasi sistem atau data legacy yang belum memiliki aktor tercatat.

---

### 3.8 Sumber dan sinkronisasi data

#### `source_system`

Sistem asal record pengguna.

Nilai awal yang disarankan:

- `legacy`;
- `manual`;
- `bkpsdm`;
- `singo`;
- `api`.

#### `external_id`

ID pengguna pada sistem sumber.

Kombinasi `source_system + external_id` dibuat unique untuk mencegah satu data eksternal diimpor berulang.

#### `last_synced_at`

Waktu sinkronisasi terakhir dengan sumber eksternal.

Jangan memperbarui nilai ini untuk perubahan manual yang bukan hasil sinkronisasi.

---

### 3.9 Preferensi legacy

#### `tahun_aktif`

Tahun anggaran yang sedang dipilih pengguna pada aplikasi legacy.

Penting:

- kolom ini adalah preferensi/konteks tampilan;
- kolom ini bukan sumber otorisasi;
- hak akses tahun anggaran harus diperiksa pada tabel hak akses atau penugasan yang sesuai.

---

### 3.10 Aktor administrasi akun

#### `created_by_user_id`

User yang membuat akun.

#### `updated_by_user_id`

User terakhir yang mengubah data administratif akun.

Jangan memperbarui kolom ini hanya karena `last_login_at` berubah.

#### `deleted_by_user_id`

User yang melakukan soft delete.

Saat soft delete dilakukan, isi `deleted_by_user_id` dan `deleted_at` dalam alur yang konsisten.

#### `created_at` dan `updated_at`

Timestamp standar Laravel.

`updated_at` harus menggambarkan perubahan record akun, bukan setiap aktivitas request pengguna.

#### `deleted_at`

Soft delete.

- Tidak sama dengan `inactive`.
- Gunakan untuk data akun yang dihapus secara logis.
- Histori login tetap dipertahankan; foreign key pada event menggunakan `nullOnDelete` jika user benar-benar dihapus secara fisik.

---

## 4. Relasi

Self-reference:

- `status_changed_by_user_id -> users.id`;
- `password_reset_by_user_id -> users.id`;
- `identity_verified_by_user_id -> users.id`;
- `created_by_user_id -> users.id`;
- `updated_by_user_id -> users.id`;
- `deleted_by_user_id -> users.id`.

Relasi eksternal:

- `login_events.user_id -> users.id`;
- `login_events.actor_user_id -> users.id`;
- `sessions.user_id -> users.id`.

---

## 5. Index dan tujuan performa

### Unique/index bawaan kolom

- `nik`: unique lookup;
- `nip`: pencarian pegawai;
- `nama`: pencarian/pengurutan;
- `email`: unique lookup.

### Composite indexes

#### `ix_users_status_type_deleted`

Kolom:

```text
status, account_type, deleted_at
```

Digunakan untuk daftar akun berdasarkan status dan tipe, terutama akun yang belum dihapus.

#### `ix_users_account_type_deleted`

Kolom:

```text
account_type, deleted_at
```

Ditambahkan untuk filter Management Users server-side, terutama ketika tabel
users ditampilkan melalui Yajra DataTables dan daftar akun perlu difilter
berdasarkan tipe akun tanpa membaca row soft-deleted.

#### `ix_users_lock_status`

Kolom:

```text
locked_until, status
```

Digunakan oleh proses pemeriksaan/unlock akun terkunci.

#### `ix_users_last_login_status`

Kolom:

```text
last_login_at, status
```

Digunakan untuk laporan akun lama tidak aktif dan monitoring login.

#### `uq_users_source_external`

Kolom:

```text
source_system, external_id
```

Mencegah duplikasi hasil sinkronisasi.

---

## 6. Invariant yang harus dijaga aplikasi

AI agent harus mempertahankan aturan berikut:

1. `status = locked` harus memiliki alasan yang dapat diaudit; biasanya `locked_at` terisi.
2. `consecutive_failed_login_count` reset setelah login berhasil.
3. `password_reset_at` harus disertai `must_change_password = true` kecuali kebijakan menyatakan lain.
4. Perubahan `status` harus mengisi `status_changed_at`, aktor jika ada, dan alasan.
5. Soft delete administratif harus mengisi `deleted_by_user_id`.
6. `source_system` dan `external_id` tidak boleh berubah sembarangan setelah mapping integrasi terbentuk.
7. `last_login_at` hanya diperbarui setelah autentikasi berhasil.
8. Login gagal untuk identifier yang tidak ditemukan tidak membuat record user baru.
9. `tahun_aktif` tidak digunakan sebagai satu-satunya dasar authorization.
10. Aktivitas autentikasi selalu menghasilkan `login_events` yang sesuai.

---

## 7. Contoh perubahan state

### Login berhasil

```php
DB::table('users')
    ->where('id', $userId)
    ->update([
        'last_login_at' => now(),
        'consecutive_failed_login_count' => 0,
        'last_failed_login_at' => null,
    ]);
```

Catatan: query builder tidak otomatis memperbarui `updated_at` kecuali kolom tersebut dimasukkan secara eksplisit.

### Penguncian akun otomatis

```php
DB::table('users')
    ->where('id', $userId)
    ->update([
        'status' => 'locked',
        'locked_at' => now(),
        'locked_until' => now()->addMinutes(30),
        'lock_reason' => 'too_many_failed_attempts',
        'status_changed_at' => now(),
        'status_reason' => 'Akun dikunci otomatis karena gagal login berulang.',
    ]);
```

### Reset password oleh administrator

```php
$user->forceFill([
    'password' => $temporaryPassword,
    'password_reset_at' => now(),
    'password_reset_by_user_id' => auth()->id(),
    'must_change_password' => true,
    'sessions_invalidated_at' => now(),
])->save();
```

Password sementara tidak boleh dicatat ke audit atau log aplikasi.
Contoh ini mengandalkan cast `password => hashed` pada model `User`. Jika
memakai query builder langsung, hash password secara eksplisit sebelum update.

---

## 8. Query audit/monitoring yang umum

### Akun aktif yang lama tidak login

```sql
SELECT id, nik, nip, nama, last_login_at
FROM users
WHERE status = 'active'
  AND deleted_at IS NULL
  AND (last_login_at IS NULL OR last_login_at < NOW() - INTERVAL 90 DAY)
ORDER BY last_login_at;
```

### Akun masih terkunci

```sql
SELECT id, nik, nama, locked_at, locked_until, lock_reason
FROM users
WHERE status = 'locked'
  AND deleted_at IS NULL
  AND (locked_until IS NULL OR locked_until > NOW());
```

### Akun fungsional legacy

```sql
SELECT id, nik, nama, status, last_login_at
FROM users
WHERE account_type = 'functional'
  AND deleted_at IS NULL;
```

---

## 9. Hal yang tidak boleh ditambahkan ke `users`

- daftar seluruh IP login;
- seluruh user agent;
- histori CAPTCHA;
- histori session;
- daftar kegagalan login;
- payload request;
- token/API key;
- password history dalam bentuk plaintext;
- log aktivitas berbentuk JSON yang terus membesar.

Data tersebut harus berada pada tabel khusus, terutama `login_events`, `sessions`, atau audit log umum.
