# Migration 2026_07_28_143000 — `user_position_year_permissions`

## 1. Tujuan tabel

Tabel ini menyimpan pengecualian akses tulis untuk data tahun historis pada satu posisi pengguna.

Tabel ini adalah **current permission state**, bukan log event lengkap.

Contoh:

```text
Posisi penerima : KPA — Kelurahan Tlogowaru
Tahun data      : 2025
Jenis izin      : historical_write
Status          : active
Berlaku         : 28–30 Juli 2026
Alasan          : Koreksi hasil pemeriksaan
```

## 2. Tabel yang harus tersedia lebih dahulu

- `users`
- `user_positions`

Migration event harus dijalankan setelah migration ini.

## 3. Penjelasan kolom

### Identitas

#### `id`

Primary key internal.

#### `user_position_id`

Posisi pengguna yang menerima izin. Permission tidak diberikan secara global kepada seluruh posisi milik user.

Foreign key memakai `restrictOnDelete()` agar posisi yang sudah mempunyai histori permission tidak dapat dihapus secara fisik.

### Target permission

#### `tahun`

Tahun data yang dibuka untuk modifikasi. Menggunakan `UNSIGNED SMALLINT` agar ringkas.

Check constraint membatasi nilai antara 2000 dan 2200.

#### `permission_type`

Saat ini hanya mendukung:

```text
historical_write
```

Artinya membuka penguncian read-only tahun historis. Action sebenarnya tetap diperiksa dari jabatan/role.

#### `status`

Nilai yang diperbolehkan:

| Status | Makna |
|---|---|
| `pending` | Permintaan belum diputuskan |
| `active` | Izin dapat digunakan |
| `revoked` | Izin dicabut |
| `expired` | Masa izin berakhir |
| `rejected` | Permintaan ditolak |
| `cancelled` | Permintaan dibatalkan |

### Masa berlaku

#### `valid_from`

Waktu awal permission dapat digunakan. Jika NULL, aplikasi dapat memperlakukannya sama dengan `granted_at`.

#### `valid_until`

Batas akhir permission. Jika NULL, permission berlaku sampai dicabut, tetapi praktik yang direkomendasikan adalah memberikan batas waktu.

Check constraint memastikan `valid_until >= valid_from` jika keduanya diisi.

### Dasar administratif

#### `reason`

Alasan bisnis atau administratif pemberian izin.

#### `reference_number`

Nomor surat, nota dinas, disposisi, tiket, berita acara, laporan pemeriksaan, atau dasar formal lain.

#### `reference_date`

Tanggal dokumen referensi.

### Audit permohonan

#### `requested_by_user_id`

User yang meminta izin.

#### `requested_by_position_id`

Posisi yang digunakan saat meminta izin.

#### `requested_at`

Waktu permintaan dibuat.

### Audit pemberian

#### `granted_by_user_id`

User yang menyetujui dan mengaktifkan izin.

#### `granted_by_position_id`

Posisi/kewenangan pemberi izin pada saat keputusan.

#### `granted_at`

Waktu izin diberikan.

#### `grant_notes`

Catatan keputusan pemberian izin.

### Audit pencabutan

#### `revoked_by_user_id`

User yang mencabut izin.

#### `revoked_by_position_id`

Posisi yang digunakan saat mencabut izin.

#### `revoked_at`

Waktu pencabutan.

#### `revocation_reason`

Alasan pencabutan.

### Ringkasan penggunaan

#### `usage_count`

Jumlah operasi yang berhasil menggunakan permission. Nilai ini adalah cache/ringkasan, bukan pengganti event audit.

#### `last_used_at`

Waktu permission terakhir berhasil digunakan.

#### `last_used_by_user_id`

User yang terakhir menggunakan permission.

#### `last_used_by_position_id`

Posisi yang terakhir menggunakan permission. Normalnya sama dengan `user_position_id`, tetapi tetap dicatat untuk audit eksplisit.

### Audit record

#### `created_by_user_id`

User yang membuat record permission.

#### `updated_by_user_id`

User yang terakhir memperbarui state record.

#### `created_at`, `updated_at`

Timestamp Laravel.

### Generated column

#### `active_unique_marker`

Generated column:

```sql
CASE WHEN status = 'active' THEN 1 ELSE NULL END
```

Digunakan untuk mencegah dua permission aktif bagi kombinasi yang sama tanpa menghalangi penyimpanan histori revoked, expired, rejected, atau cancelled.

Kolom ini tidak boleh diisi manual dari aplikasi.

## 4. Unique constraint

```text
user_position_id
+ tahun
+ permission_type
+ active_unique_marker
```

Konsekuensi:

- hanya satu permission aktif untuk posisi, tahun, dan tipe yang sama;
- beberapa record historis tetap dapat disimpan;
- pemberian izin ulang dibuat sebagai record baru setelah izin sebelumnya tidak aktif.

## 5. Indeks

### `ix_upyp_position_year_status`

```text
(user_position_id, tahun, status)
```

Digunakan oleh query authorization utama.

### `ix_upyp_year_status_expiry`

```text
(tahun, status, valid_until)
```

Digunakan untuk dashboard dan proses expiration.

### `ix_upyp_granter_time`

```text
(granted_by_user_id, granted_at)
```

Digunakan untuk audit pemberian izin oleh administrator.

### `ix_upyp_last_used`

Digunakan untuk monitoring penggunaan terakhir.

## 6. Query authorization yang direkomendasikan

```php
$permission = UserPositionYearPermission::query()
    ->where('user_position_id', $activePositionId)
    ->where('tahun', $targetYear)
    ->where('permission_type', 'historical_write')
    ->where('status', 'active')
    ->where(function ($query) {
        $query->whereNull('valid_from')
            ->orWhere('valid_from', '<=', now());
    })
    ->where(function ($query) {
        $query->whereNull('valid_until')
            ->orWhere('valid_until', '>=', now());
    })
    ->first();
```

Query ini hanya dijalankan untuk target tahun sebelum tahun berjalan.

## 7. Larangan implementasi

AI agent dilarang:

- menganggap record permission sebagai pengganti role/jabatan;
- memberi izin berdasarkan `user_id` tanpa memeriksa posisi session;
- menghapus record permission yang sudah pernah berlaku;
- mengubah record revoked menjadi active untuk pemberian ulang;
- mengisi `active_unique_marker` secara manual;
- menggunakan `updated_at` sebagai satu-satunya histori perubahan;
- menambahkan `source_system`, `external_id`, `last_synced_at`, atau `metadata`.
