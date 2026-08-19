# Migration Documentation

## Migration

`2026_07_28_140200_create_user_position_documents_table.php`

## Table created

`user_position_documents`

## Business purpose

Tabel ini menyimpan dokumen yang menjadi dasar, perubahan, pencabutan, atau pendukung suatu posisi pengguna.

Dokumen dipisahkan dari `user_positions` agar:

- satu posisi dapat memiliki banyak dokumen;
- satu dokumen dapat memiliki beberapa versi;
- metadata file dan verifikasi tidak membebani query pemilihan posisi;
- histori SK tetap terjaga;
- integritas file dapat diperiksa menggunakan SHA-256;
- pengunggah dan verifikator dapat diaudit.

---

## Relationship

```text
user_positions
    └── user_position_documents
```

Foreign key:

```text
user_position_documents.user_position_id
    → user_positions.id
```

Delete behavior: `RESTRICT`.

Posisi tidak boleh dihapus secara fisik ketika masih mempunyai dokumen.

---

## Column dictionary

### `id`

Internal primary key.

### `user_position_id`

Posisi yang didukung oleh dokumen.

Satu posisi dapat mempunyai banyak dokumen.

---

## Document classification

### `document_type`

Jenis dokumen.

Nilai yang ditetapkan oleh desain:

```text
appointment_sk
amendment_sk
revocation_sk
supporting
```

Makna:

- `appointment_sk`: SK penetapan awal.
- `amendment_sk`: SK perubahan penetapan.
- `revocation_sk`: SK pencabutan posisi.
- `supporting`: dokumen pendukung lain.

Default: `appointment_sk`.

Gunakan PHP backed enum atau validation rule pada aplikasi. Migration menggunakan string agar penambahan tipe tidak memerlukan perubahan struktur database.

---

## Document identity

### `document_number`

Nomor resmi dokumen atau SK.

### `document_date`

Tanggal dokumen diterbitkan.

### `issued_by`

Nama pihak atau instansi penerbit.

Jangan menganggap `document_number` selalu unik secara global karena format dan nomor dapat berbeda antarinstansi.

---

## Effective period

### `effective_from`

Tanggal awal dokumen mulai berlaku.

### `effective_until`

Tanggal akhir dokumen berlaku.

Database menerapkan check constraint:

```text
effective_until IS NULL
OR effective_from IS NULL
OR effective_until >= effective_from
```

Periode dokumen tidak otomatis menggantikan `user_positions.started_at` dan `user_positions.ended_at`.

Aplikasi harus menentukan secara eksplisit apakah verifikasi dokumen akan memperbarui periode posisi.

Jangan melakukan perubahan periode posisi secara diam-diam hanya karena dokumen diunggah.

---

## File storage metadata

### `storage_disk`

Nama disk Laravel Storage.

Default: `private`.

### `file_path`

Path file relatif pada disk penyimpanan.

Wajib terisi.

Jangan menyimpan file binary atau Base64 di kolom ini.

### `original_name`

Nama file ketika diunggah oleh pengguna.

Wajib terisi untuk kebutuhan audit dan tampilan.

### `stored_name`

Nama file fisik yang dibuat aplikasi, jika berbeda dari nama asli.

### `mime_type`

MIME type hasil pemeriksaan server.

Jangan hanya percaya MIME type dari browser.

### `extension`

Ekstensi file yang dinormalisasi.

### `size_bytes`

Ukuran file dalam byte.

### `file_sha256`

Checksum SHA-256 file dalam format hexadecimal 64 karakter.

Digunakan untuk:

- mendeteksi file yang sama;
- memeriksa integritas;
- membantu audit perubahan file.

Jangan menggunakan checksum sebagai pengganti malware scanning atau validasi PDF.

---

## Versioning and primary document

### `version`

Nomor versi dokumen.

Default: `1`.

Aplikasi harus menentukan strategi versi, misalnya menaikkan versi untuk dokumen dengan jenis yang sama pada posisi yang sama.

### `is_primary`

Menandai dokumen utama yang ditampilkan untuk posisi.

Migration tidak membatasi hanya satu record `is_primary = true` melalui database.

Karena itu, AI agent wajib mengelola perubahan dokumen utama dalam transaksi:

```php
DB::transaction(function () use ($position, $document) {
    $position->documents()
        ->where('is_primary', true)
        ->update(['is_primary' => false]);

    $document->update(['is_primary' => true]);
});
```

Aturan bisnis yang direkomendasikan:

- maksimum satu dokumen utama yang belum dihapus per posisi;
- dokumen utama harus relevan dengan status posisi;
- dokumen `rejected` tidak boleh menjadi dokumen utama.

---

## Verification

Catatan implementasi saat ini: Management Users baru memakai upload file SK dan
metadata dokumen. Workflow verifikasi dokumen di bawah ini adalah desain schema
dan rekomendasi lanjutan, bukan fitur UI yang sudah aktif.

### `verification_status`

Status verifikasi dokumen.

Nilai desain:

```text
draft
verified
rejected
archived
```

Default: `draft`.

### `verified_at`

Waktu keputusan verifikasi.

### `verified_by_user_id`

Aktor yang melakukan verifikasi.

### `verification_notes`

Catatan hasil verifikasi atau alasan penolakan.

Saat status menjadi `verified`:

```text
verification_status = verified
verified_at = waktu sekarang
verified_by_user_id = aktor
```

Saat status menjadi `rejected`, isi alasan yang jelas pada `verification_notes`.

Jangan menghapus dokumen hanya karena ditolak. Pertahankan sebagai histori audit.

---

## Upload audit

### `uploaded_at`

Waktu file benar-benar diterima dan tersimpan.

### `uploaded_by_user_id`

Aktor yang mengunggah file.

`created_by_user_id` dan `uploaded_by_user_id` dapat berbeda, misalnya record dibuat melalui integrasi tetapi file diunggah administrator.

---

## Source and metadata

### `source_system`

Sumber record dokumen.

Contoh:

```text
manual
legacy
bkpsdm
api
```

### `external_id`

Identifier dokumen pada sistem sumber.

### `last_synced_at`

Waktu sinkronisasi terakhir.

### `metadata`

JSON untuk atribut tambahan yang jarang digunakan sebagai filter utama.

Contoh yang diperbolehkan:

```json
{
  "legacy_path": "uploads/sk/old-file.pdf",
  "import_batch": "legacy-2026-07-28"
}
```

Jangan menyimpan data yang sering digunakan pada filter atau laporan hanya di dalam `metadata`. Buat kolom normal bila data tersebut penting untuk query.

Jangan menyimpan password, token, passphrase, cookie, credential, atau file Base64 di `metadata`.

---

## Record lifecycle audit

### `created_by_user_id`

Aktor pembuat record.

### `updated_by_user_id`

Aktor terakhir yang mengubah metadata record.

### `deleted_by_user_id`

Aktor yang melakukan soft delete.

### `created_at`, `updated_at`

Timestamp Laravel.

### `deleted_at`

Soft delete.

Dokumen tidak boleh hard-delete melalui alur bisnis normal. Soft delete digunakan untuk file salah, duplikat, atau pembatalan administratif.

File fisik tidak boleh langsung dihapus sebelum kebijakan retensi dan audit dipenuhi.

---

## Unique constraints

### File hash per position

```text
(user_position_id, file_sha256)
```

Nama constraint:

```text
uq_user_position_documents_file_hash
```

Tujuan: mencegah file yang sama ditempel dua kali pada posisi yang sama.

Catatan penting:

- `file_sha256` nullable.
- Pada MySQL, beberapa record dengan `file_sha256 = NULL` tetap dapat lolos unique constraint.
- Aplikasi sebaiknya selalu menghitung SHA-256 sebelum insert agar deduplikasi efektif.

### External source identity

```text
(source_system, external_id)
```

Nama constraint:

```text
uq_user_position_documents_source_external
```

Digunakan untuk sinkronisasi idempotent.

`external_id = NULL` diperbolehkan untuk data manual.

---

## Performance indexes

### `ix_user_position_documents_primary_status`

```text
(user_position_id, is_primary, verification_status)
```

Untuk mengambil dokumen utama dan status verifikasinya pada suatu posisi.

### `ix_user_position_documents_number_date`

```text
(document_number, document_date)
```

Untuk pencarian dokumen berdasarkan nomor dan tanggal.

### `ix_user_position_documents_expiry`

```text
(effective_until, verification_status)
```

Untuk mencari dokumen terverifikasi yang mendekati atau melewati akhir masa berlaku.

---

## File upload rules

AI agent wajib:

1. Memvalidasi ukuran file.
2. Memvalidasi MIME type menggunakan pemeriksaan server.
3. Menggunakan nama file storage yang tidak berasal langsung dari input pengguna.
4. Menyimpan file pada disk privat.
5. Menghitung SHA-256 setelah file tersimpan.
6. Menyimpan `size_bytes`, `mime_type`, dan `original_name`.
7. Mengisi `uploaded_at` dan `uploaded_by_user_id`.
8. Menjalankan authorization berdasarkan `user_position_id`.
9. Membersihkan file yang sudah terunggah apabila transaksi database gagal.
10. Menggunakan signed URL sementara atau controller streaming untuk download.

AI agent tidak boleh:

- menyimpan file pada folder publik tanpa kontrol akses;
- mempercayai ekstensi file saja;
- menggunakan `original_name` sebagai nama fisik tanpa normalisasi;
- menyimpan path absolut server yang mengekspos struktur internal;
- memperbolehkan path traversal;
- mengganti file lama secara fisik tanpa membuat versi atau histori;
- menghapus record dokumen yang ditolak;
- menetapkan dokumen `rejected` sebagai `is_primary = true`.

---

## Recommended upload flow

```text
1. Authorize user position.
2. Validate file.
3. Generate safe stored name.
4. Store file on private disk.
5. Calculate SHA-256 and file metadata.
6. Begin database transaction.
7. Insert document record.
8. Optionally update primary document in same transaction.
9. Commit transaction.
10. If database operation fails, remove uploaded file.
```

---

## Recommended document verification flow

```php
DB::transaction(function () use ($document, $notes) {
    $document->update([
        'verification_status' => 'verified',
        'verified_at' => now(),
        'verified_by_user_id' => auth()->id(),
        'verification_notes' => $notes,
        'updated_by_user_id' => auth()->id(),
    ]);
});
```

Apabila verifikasi dokumen menyebabkan posisi aktif, perubahan pada `user_positions` harus dilakukan secara eksplisit dalam transaksi yang sama.

---

## Audit query examples

Dokumen utama suatu posisi:

```sql
SELECT *
FROM user_position_documents
WHERE user_position_id = ?
  AND is_primary = 1
  AND deleted_at IS NULL
ORDER BY version DESC, id DESC;
```

Dokumen yang belum diverifikasi:

```sql
SELECT *
FROM user_position_documents
WHERE verification_status = 'draft'
  AND deleted_at IS NULL
ORDER BY uploaded_at ASC;
```

Dokumen kedaluwarsa:

```sql
SELECT *
FROM user_position_documents
WHERE effective_until < CURRENT_DATE
  AND verification_status = 'verified'
  AND deleted_at IS NULL;
```

---

## AI agent prohibited assumptions

AI agent must not assume:

- satu posisi hanya mempunyai satu dokumen;
- satu file path sudah cukup untuk audit;
- dokumen terbaru selalu dokumen utama;
- dokumen terverifikasi otomatis mengaktifkan posisi;
- periode dokumen dan periode posisi selalu identik;
- `file_sha256` selalu terisi tanpa dihitung oleh aplikasi;
- soft delete record berarti file fisik boleh langsung dihapus;
- `metadata` boleh menampung semua atribut tanpa desain kolom;
- dokumen dapat dibaca hanya karena pengguna mengetahui URL.
