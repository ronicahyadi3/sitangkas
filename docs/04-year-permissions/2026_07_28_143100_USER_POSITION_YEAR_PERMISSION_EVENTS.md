# Migration 2026_07_28_143100 — `user_position_year_permission_events`

## 1. Tujuan tabel

Tabel ini menyimpan histori event permission tahun secara append-only.

Tabel ini menjawab:

- kapan permission diminta;
- siapa yang memberi atau mencabut;
- kapan permission digunakan;
- action dan resource apa yang diproses;
- request, session, IP, dan perangkat apa yang terkait;
- apakah event berhasil, gagal, atau ditolak.

Tabel ini bukan sumber utama authorization. Authorization membaca `user_position_year_permissions`.

## 2. Sifat append-only

Tabel hanya mempunyai:

```text
occurred_at
created_at
```

Tabel tidak mempunyai:

```text
updated_at
deleted_at
```

Event yang sudah dibuat tidak boleh diubah atau dihapus oleh alur aplikasi normal. Koreksi dilakukan dengan menambahkan event baru.

## 3. Penjelasan kolom

### Identitas event

#### `id`

Primary key internal.

#### `event_uuid`

UUID unik untuk korelasi dengan application log, reverse proxy, SIEM, atau sistem monitoring.

#### `user_position_year_permission_id`

Permission yang menjadi sumber event. Foreign key memakai `restrictOnDelete()` agar histori event tidak hilang.

Kolom ini wajib. Karena itu, event `denied` hanya dapat dicatat terhadap permission record yang sudah ada.

### Jenis dan hasil event

#### `event_type`

Nilai yang diperbolehkan:

| Event | Makna |
|---|---|
| `requested` | Permintaan dibuat |
| `granted` | Izin diberikan |
| `used` | Izin digunakan untuk operasi historis |
| `denied` | Penggunaan permission ditolak |
| `revoked` | Izin dicabut |
| `expired` | Izin kedaluwarsa |
| `rejected` | Permintaan ditolak |
| `cancelled` | Permintaan dibatalkan |

#### `result`

Nilai yang diperbolehkan:

```text
success
failed
denied
```

#### `occurred_at`

Waktu bisnis ketika event terjadi.

### Aktor

#### `actor_user_id`

User pelaku event.

#### `actor_position_id`

Posisi yang digunakan pelaku pada saat event terjadi.

Menyimpan keduanya diperlukan karena audit harus mengetahui orang dan kapasitas kewenangannya.

### Aktivitas dan resource

#### `action`

Aksi yang dilakukan, misalnya:

```text
create
update
delete
restore
upload
submit
verify
approve
sign
```

#### `resource_type`

Nama domain resource, bukan nama class yang mudah berubah jika memungkinkan.

Contoh:

```text
document
archive
payment_submission
```

#### `resource_id`

ID resource yang dimodifikasi. String digunakan agar dapat menampung numeric ID, UUID, atau ULID.

### Alasan dan pesan

#### `reason_code`

Kode terstruktur untuk laporan dan pencarian.

Contoh:

```text
permission_expired
permission_revoked
outside_valid_period
role_action_denied
```

#### `message`

Pesan manusia yang aman untuk audit. Jangan memasukkan data rahasia.

### Korelasi request

#### `request_id`

UUID satu HTTP request.

#### `correlation_id`

UUID satu alur yang dapat melintasi beberapa request atau service.

#### `session_id_hash`

SHA-256 dari session ID. Session ID mentah tidak boleh disimpan.

#### `route_name`

Nama route Laravel.

#### `request_path`

Path request tanpa credential atau data rahasia.

#### `request_method`

Metode HTTP seperti GET, POST, PUT, PATCH, atau DELETE.

#### `http_status`

Status HTTP 100–599. Dijaga oleh check constraint.

#### `ip_address`

Alamat IPv4 atau IPv6 pelaku.

#### `user_agent`

User agent request. Data ini dapat panjang sehingga menggunakan `TEXT`.

### Integritas perubahan

#### `before_state_hash`

SHA-256 dari representasi kanonis state sebelum perubahan.

#### `after_state_hash`

SHA-256 dari representasi kanonis state setelah perubahan.

Hash bersifat opsional dan tidak menggantikan audit log perubahan field.

#### `event_hash`

Hash opsional event untuk pemeriksaan integritas. Jika digunakan, aplikasi harus mempunyai aturan kanonisasi field yang konsisten.

### Timestamp penyimpanan

#### `created_at`

Waktu event disimpan ke database. Dapat berbeda sedikit dari `occurred_at`.

## 4. Indeks

### `ix_upype_permission_time`

Riwayat event sebuah permission secara kronologis.

### `ix_upype_actor_user_time`

Audit aktivitas seorang user.

### `ix_upype_actor_position_time`

Audit aktivitas berdasarkan posisi.

### `ix_upype_event_result_time`

Pelaporan event dan hasil dalam rentang waktu.

### `ix_upype_resource_time`

Mencari penggunaan permission pada resource tertentu.

### `ix_upype_request_id`

Korelasi dengan application log satu request.

## 5. Event yang wajib dibuat

### Permission diminta

```text
event_type = requested
result     = success
```

### Permission diberikan

```text
event_type = granted
result     = success
```

### Permission berhasil digunakan

```text
event_type   = used
result       = success
action       = update
resource_type= document
resource_id  = 123
```

### Penggunaan ditolak

```text
event_type = denied
result     = denied
reason_code= permission_expired
```

### Permission dicabut

```text
event_type = revoked
result     = success
```

### Permission kedaluwarsa

```text
event_type = expired
result     = success
```

## 6. Transaksi yang direkomendasikan

Saat izin berhasil digunakan, lakukan dalam satu transaksi:

1. ubah resource historis;
2. increment `usage_count`;
3. update `last_used_at` dan aktor terakhir;
4. insert event `used`.

Jika perubahan resource gagal, transaksi harus rollback agar ringkasan dan event tidak menyatakan keberhasilan palsu.

## 7. Data yang dilarang

Jangan menyimpan:

- password;
- passphrase TTE;
- access token;
- refresh token;
- API key;
- cookie mentah;
- session ID mentah;
- Authorization header;
- payload autentikasi;
- dokumen atau data pribadi lengkap yang tidak diperlukan.

## 8. Kolom yang sengaja tidak tersedia

Tabel ini tidak menyediakan `metadata`.

Atribut audit penting harus mempunyai kolom terstruktur. Bila ada kebutuhan audit baru yang stabil dan sering digunakan, tambahkan kolom khusus melalui migration baru, bukan JSON bebas.

## 9. Larangan implementasi

AI agent dilarang:

- menggunakan tabel event sebagai sumber authorization terkini;
- memperbarui atau soft-delete event;
- menghapus event saat permission dicabut;
- menyimpan session ID atau token mentah;
- menganggap `message` sebagai pengganti `reason_code`;
- memasukkan seluruh payload request ke event;
- menambahkan kolom `metadata` tanpa keputusan desain eksplisit.
