# SITANGKAS — AI Agent Database Context

## 1. Tujuan dokumen

Dokumen ini adalah konteks wajib bagi AI agent yang membaca, membuat, meninjau, atau mengubah kode yang berkaitan dengan autentikasi SITANGKAS.

Dua tabel utama yang dibahas adalah:

- `users`: menyimpan **kondisi akun terkini** dan ringkasan keamanan yang diperlukan dalam proses autentikasi.
- `login_events`: menyimpan **riwayat setiap kejadian autentikasi** secara terpisah dan append-only untuk kebutuhan audit.

Untuk fitur Management Users, audit administratif detail berada di
`user_management_audit_events`. Tabel tersebut tidak menggantikan
`login_events`; aksi keamanan akun yang berdampak pada autentikasi/session tetap
dicatat ke `login_events`.

Dokumen rinci:

- [`USERS_TABLE.md`](USERS_TABLE.md)
- [`LOGIN_EVENTS_TABLE.md`](LOGIN_EVENTS_TABLE.md)
- [`AUTH_CONTEXT_DECISIONS.md`](AUTH_CONTEXT_DECISIONS.md)
- [`CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`](CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md)
- [`MANAGEMENT_USERS_CURRENT_STATE.md`](MANAGEMENT_USERS_CURRENT_STATE.md)

Migration acuan:

- `0001_01_01_000000_create_users_table.php`
- `2026_07_28_120000_create_login_events_table.php`
- `2026_08_12_025830_create_user_management_audit_events_table.php`

---

## 2. Prinsip desain utama

### 2.1 Current state vs event history

AI agent **WAJIB** membedakan dua jenis data berikut.

| Jenis data | Tabel | Contoh |
|---|---|---|
| Kondisi akun saat ini | `users` | status akun, jumlah gagal login berturut-turut, waktu terkunci, login terakhir |
| Riwayat autentikasi | `login_events` | login berhasil, login gagal, logout, lockout, session timeout, session revoked, reset MFA |
| Audit administrasi Management Users | `user_management_audit_events` | create/update/delete user, create/deactivate posisi, grant/revoke izin historis |

Aturan sederhana:

> Bila nilainya menggambarkan kondisi akun **sekarang**, simpan di `users`.
>
> Bila nilainya menjelaskan **apa yang terjadi pada suatu waktu**, tulis event baru di `login_events`.
>
> Bila event berasal dari administrasi Management Users, tulis detailnya ke
> `user_management_audit_events`; untuk aksi keamanan akun, tetap tulis event
> autentikasi yang relevan ke `login_events`.

Contoh benar:

- `users.last_login_at` menyimpan waktu login berhasil terakhir.
- `login_events` menyimpan seluruh login berhasil sebelumnya.
- `users.consecutive_failed_login_count` menyimpan jumlah gagal login berturut-turut saat ini.
- `login_events` menyimpan setiap percobaan login gagal secara individual.

### 2.2 Append-only audit log

`login_events` dirancang sebagai catatan audit append-only.

AI agent **DILARANG**:

- memperbarui event lama untuk mengubah hasil;
- melakukan soft delete terhadap event;
- menghapus event melalui fitur aplikasi biasa;
- menyimpan seluruh histori autentikasi di kolom JSON pada `users`;
- menggunakan `login_events` sebagai pengganti tabel session aktif.

Koreksi atas event lama harus dibuat sebagai event baru atau dicatat melalui mekanisme audit administratif yang terpisah.

### 2.3 Data sensitif

AI agent **DILARANG menyimpan** data berikut pada `users`, `login_events`, `metadata`, atau log aplikasi:

- password asli;
- password yang gagal dimasukkan;
- passphrase TTE;
- cookie mentah;
- session ID mentah;
- access token atau refresh token mentah;
- API key mentah;
- Authorization header;
- token CAPTCHA mentah;
- isi penuh request autentikasi;
- data rahasia lain yang tidak diperlukan untuk audit.

Identifier, session ID, token ID, dan fingerprint yang perlu dikorelasikan harus disimpan dalam bentuk hash/HMAC, bukan nilai mentah.

---

## 3. Batas tanggung jawab tabel

### `users`

Tabel `users` bertanggung jawab atas:

- identitas akun berdasarkan struktur legacy SITANGKAS;
- status dan klasifikasi akun;
- kredensial yang sudah di-hash;
- kebijakan password;
- state penguncian saat ini;
- ringkasan login terakhir;
- verifikasi identitas;
- sumber dan sinkronisasi data;
- aktor pembuat, pengubah, dan penghapus akun.

Tabel `users` **bukan** tempat untuk menyimpan seluruh histori login.

### `login_events`

Tabel `login_events` bertanggung jawab atas:

- setiap kejadian login, logout, lockout, unlock, timeout, revoke, MFA, dan event autentikasi lain;
- hasil suatu event;
- identifier yang digunakan;
- konteks pengguna saat event terjadi;
- metode autentikasi;
- request, session, dan correlation identifier;
- jaringan dan perangkat;
- CAPTCHA dan risk information;
- integritas, waktu, serta retensi event.

Tabel `login_events` **bukan** sumber utama untuk menentukan apakah akun saat ini boleh login. Keputusan autentikasi harus membaca state pada `users`.

### `user_management_audit_events`

Tabel `user_management_audit_events` bertanggung jawab atas audit administrasi
Management Users:

- create/update/delete user;
- create/update/activate/deactivate/delete posisi;
- grant/revoke izin tahun historis;
- force change password, lock/unlock, dan reset MFA dari UI;
- snapshot `before_state` dan `after_state`;
- reason, message, metadata, serta request context non-secret.

Tabel ini **bukan** pengganti `login_events` untuk histori autentikasi.

---

## 4. Urutan proses autentikasi yang benar

Untuk detail kondisi implementasi login context saat ini, terutama pemisahan
`realActivePosition()` dan `activePosition()`, baca
`CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`.

### 4.1 Login berhasil

1. Normalisasi identifier login.
2. Verifikasi rate limit dan kondisi lock pada `users`.
3. Verifikasi kredensial dan kontrol keamanan lain.
4. Buat session autentikasi.
5. Perbarui `users` tanpa mengubah makna `updated_at` untuk perubahan profil:
   - `last_login_at = now()`;
   - `consecutive_failed_login_count = 0`;
   - `last_failed_login_at = null`;
   - lepaskan lock otomatis jika kebijakan mengizinkan.
6. Tambahkan satu event `login/success` ke `login_events`.

### 4.2 Login gagal

1. Normalisasi identifier login tanpa menyimpan password.
2. Tingkatkan `users.consecutive_failed_login_count` hanya jika akun ditemukan.
3. Perbarui `users.last_failed_login_at` bila akun ditemukan.
4. Bila ambang penguncian tercapai, ubah state lock pada `users`.
5. Tambahkan satu event `login/failed` ke `login_events`.
6. Bila akun dikunci atau request diblokir, tambahkan event `lockout/blocked` atau `login/blocked` sesuai penyebab.

### 4.3 Logout

1. Ambil user dan session aktif sebelum session dihancurkan.
2. Tambahkan event `logout/success`.
3. Hapus atau invalidasi session aktif.
4. Jangan menghapus event login sebelumnya.

### 4.4 Session timeout atau revoke

Gunakan event yang berbeda:

- `session_timeout/expired` untuk session habis karena tidak aktif atau kedaluwarsa;
- `session_revoked/revoked` untuk session dicabut sistem atau administrator;
- `logout/success` hanya untuk logout normal yang disengaja pengguna.

---

## 5. Konvensi nilai

### `account_type`

Nilai awal yang diperbolehkan:

- `personal`: akun pribadi milik satu orang;
- `functional`: akun fungsi atau akun bersama legacy selama masa transisi;
- `service`: akun integrasi atau proses otomatis;
- `emergency`: akun darurat/break-glass.

Jangan menambah nilai baru tanpa memperbarui dokumentasi, validasi aplikasi, enum PHP, dan pengujian.

### `users.status`

Nilai awal yang diperbolehkan:

- `pending`;
- `active`;
- `inactive`;
- `locked`;
- `suspended`.

### `login_events.event_type`

Nilai yang direkomendasikan:

- `login`;
- `logout`;
- `lockout`;
- `account_locked`;
- `account_unlocked`;
- `password_change_forced`;
- `context_switched`;
- `session_timeout`;
- `session_revoked`;
- `mfa_challenge`;
- `mfa_recovery_codes_regenerated`;
- `mfa_reset`;
- `mfa_verified`;

### `login_events.result`

Nilai yang direkomendasikan:

- `success`;
- `failed`;
- `blocked`;
- `expired`;
- `revoked`;
- `cancelled`.

`event_type` menjawab **apa yang terjadi**. `result` menjawab **bagaimana hasilnya**.

---

## 6. Normalisasi dan hashing

### Identifier login

Sebelum dibuat hash:

- email atau username: `trim` lalu lowercase;
- NIK/NIP: hapus karakter non-digit sesuai kebijakan aplikasi;
- jangan mengubah identifier asli yang perlu ditampilkan, kecuali masking/enkripsi.

Gunakan HMAC-SHA-256 dengan kunci audit khusus, bukan hash tanpa kunci:

```php
$normalized = mb_strtolower(trim($identifier));
$hash = hash_hmac(
    'sha256',
    $normalized,
    config('security.audit_hash_key')
);
```

Sediakan environment variable tersendiri:

```env
AUDIT_HASH_KEY=<random-secret-yang-berbeda-dari-APP_KEY>
```

Kunci tersebut tidak boleh dimasukkan ke database atau log.

### Session dan token

- Simpan hanya `session_id_hash` dan `token_id_hash`.
- Jangan simpan session/token asli pada event.
- Gunakan algoritma dan kunci yang sama secara konsisten agar korelasi dapat dilakukan.

### Event hash

`event_hash` boleh dihitung dari payload event yang sudah dinormalisasi untuk mendeteksi perubahan data.

Event hash bukan pengganti kontrol akses database, backup, atau immutable storage.

---

## 7. Transaksi dan konsistensi

Perubahan state `users` dan penulisan `login_events` sebaiknya dilakukan dalam transaksi yang sama apabila memungkinkan.

Contoh login gagal untuk akun yang ditemukan:

```php
DB::transaction(function () use ($user, $eventData) {
    $user->increment('consecutive_failed_login_count');
    $user->forceFill([
        'last_failed_login_at' => now(),
    ])->saveQuietly();

    LoginEvent::create($eventData);
});
```

Untuk event keamanan kritis, kegagalan pencatatan log harus ditangani secara eksplisit. Jangan diam-diam mengabaikan kegagalan insert audit.

---

## 8. Waktu

- Simpan waktu server dalam UTC bila arsitektur aplikasi mendukungnya.
- Konversi ke `Asia/Jakarta` pada layer presentasi.
- Gunakan `occurred_at` sebagai waktu kejadian.
- Gunakan `created_at` sebagai waktu record diterima/ditulis ke database.
- Kedua nilai dapat berbeda pada event yang datang dari queue atau integrasi.

---

## 9. Retensi

`retention_until` menentukan kapan event memenuhi syarat untuk dipindahkan atau dihapus oleh proses retensi resmi.

AI agent tidak boleh membuat job penghapusan tanpa mempertimbangkan:

- kebijakan audit instansi;
- kebutuhan pemeriksaan internal/eksternal;
- incident response;
- backup dan arsip;
- persetujuan pemilik data.

Penghapusan harus dilakukan oleh proses khusus dan akun database dengan hak terbatas, bukan controller biasa.

---

## 10. Larangan desain bagi AI agent

AI agent dilarang melakukan hal berikut tanpa keputusan arsitektur eksplisit:

1. Menggabungkan kembali `login_events` ke `users`.
2. Menghapus `user_id` nullable dari `login_events`; login gagal dapat terjadi untuk identifier yang tidak terdaftar.
3. Menganggap `actor_user_id` sama dengan `user_id`.
4. Menjadikan `attempt_number` sebagai total login seumur hidup.
5. Menyimpan semua informasi jaringan/perangkat dalam satu string.
6. Menggunakan kolom `metadata` untuk data yang sering difilter atau dilaporkan.
7. Mengubah event audit yang sudah ditulis.
8. Mencatat secret atau payload autentikasi mentah.
9. Mempercayai `X-Forwarded-For` tanpa trusted proxy configuration.
10. Memperbarui `users.updated_at` setiap kali hanya mencatat aktivitas login, kecuali kebijakan aplikasi memang menganggapnya perubahan akun.

---

## 11. Definition of done bagi perubahan autentikasi

Perubahan dianggap selesai jika:

- state akun terkini disimpan pada `users`;
- event autentikasi yang relevan ditulis ke `login_events`;
- tidak ada secret yang tercatat;
- identifier dan session/token dikorelasikan secara aman;
- event type dan result digunakan dengan benar;
- pengujian yang relevan direkomendasikan untuk login berhasil, gagal,
  blocked/lockout, logout, timeout, dan revoke, tetapi AI agent tidak boleh
  membuat, memodifikasi, atau menjalankan test suite/test command tanpa
  konfirmasi eksplisit dari user;
- indeks dan query tidak menggunakan `metadata` untuk filter utama;
- audit tetap dapat dibaca walaupun user di-soft-delete atau relasi user menjadi null.
