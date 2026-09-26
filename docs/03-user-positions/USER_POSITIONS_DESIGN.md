# SITANGKAS — Desain `user_positions` dan Dokumen SK

## 1. Tujuan utama

`user_positions` adalah daftar **konteks kerja yang sah dan tersedia** bagi sebuah akun SITANGKAS.

Satu akun `users` dapat memiliki satu atau banyak `user_positions`. Setiap posisi dibentuk oleh kombinasi:

- `user_id`
- `jabatan_id`
- `instansi_id`
- `unit_kerja_id`

Contoh satu pengguna:

- KPA — BKAD — Sekretariat
- PPTK — BKAD — Bidang Anggaran
- Auditor — Inspektorat — Inspektorat Pembantu I

Semua posisi tersebut boleh `is_active = true` secara bersamaan. `is_active` berarti **tersedia untuk dipilih**, bukan posisi yang sedang dipakai.

## 2. Posisi yang sedang dipakai

Posisi yang sedang dipakai tidak disimpan sebagai `is_selected` atau `is_current` pada `user_positions` karena:

1. Seorang pengguna dapat membuka beberapa sesi/perangkat dengan konteks berbeda.
2. Mengubah satu posisi akan memerlukan update massal untuk mengosongkan posisi lain.
3. Risiko dua record terpilih bersamaan meningkat pada request paralel.
4. Kondisi request/session tidak seharusnya menjadi status master penugasan.

Gunakan aturan berikut:

- Simpan `active_user_position_id` di session setelah login.
- Saat login, pilih posisi aktif dengan `last_used_at` terbaru.
- Jika seluruh `last_used_at` masih `NULL`, pilih record dengan `created_at` atau `id` terkecil.
- Saat pengguna beralih posisi, validasi kepemilikan dan ketersediaannya, ubah session, lalu update `last_used_at`.
- Update `last_used_at` tanpa mengubah `updated_at`, karena pemakaian posisi bukan perubahan data master.

Contoh pemilihan awal:

```php
$position = UserPosition::query()
    ->where('user_id', auth()->id())
    ->where('is_active', true)
    ->whereNull('deleted_at')
    ->where(function ($query) {
        $query->whereNull('started_at')
            ->orWhereDate('started_at', '<=', today());
    })
    ->where(function ($query) {
        $query->whereNull('ended_at')
            ->orWhereDate('ended_at', '>=', today());
    })
    ->orderByDesc('last_used_at')
    ->orderBy('id')
    ->firstOrFail();

session(['active_user_position_id' => $position->id]);
```

Contoh pergantian posisi:

```php
DB::transaction(function () use ($requestedPositionId) {
    $position = UserPosition::query()
        ->whereKey($requestedPositionId)
        ->where('user_id', auth()->id())
        ->where('is_active', true)
        ->whereNull('deleted_at')
        ->firstOrFail();

    session(['active_user_position_id' => $position->id]);

    DB::table('user_positions')
        ->where('id', $position->id)
        ->update(['last_used_at' => now()]);
});
```

## 3. Aturan data

### Kombinasi posisi unik

Kombinasi berikut harus unik:

```text
user_id + jabatan_id + instansi_id + unit_kerja_id
```

Apabila posisi manual/canonical yang sama pernah di-soft-delete lalu diperlukan
kembali, aplikasi harus memakai `withTrashed()`, melakukan `restore()`, dan
mengaktifkannya kembali. Jangan membuat record operasional duplikat.

Pengecualian yang sudah disetujui tetapi belum diimplementasikan berlaku untuk
import `dump-keuangan-202609090855.sql`. Row legacy dengan konteks sama harus
tetap dipertahankan sebagai alias non-selectable agar ID yang dirujuk
`document` dan `document_process` tidak berubah. Current unique constraint
masih memblokir pengecualian ini. Baca
`../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md` sebelum mengubah schema.

### Arti `is_active`

- `true`: posisi tersedia untuk dipilih.
- `false`: posisi masih tercatat, tetapi tidak boleh digunakan.

Penonaktifan sementara sebaiknya memakai `is_active = false`, `deactivated_at`, `deactivated_by_user_id`, dan `deactivation_reason`.

Soft delete hanya dipakai untuk record salah, duplikat, atau penghapusan administratif yang memang harus disembunyikan dari operasi normal.

Flow Management Users saat ini membuat akun terlebih dahulu lalu menambahkan
posisi melalui modal posisi. Posisi pertama user baru harus lewat
`UserManagementAccessService::canAttachPositionToUser()` agar PA/KPA bisa
menempelkan posisi awal hanya pada scope yang mereka kelola. Audit create posisi
menyimpan metadata `is_initial_position`.

### Kebijakan watermark PDF per posisi

Target schema menambahkan tepat satu kolom:

```text
pdf_watermark_required BOOLEAN NOT NULL DEFAULT FALSE
```

Keputusan bisnisnya:

- `true`: setiap PDF yang boleh diakses posisi tersebut—preview, view, maupun
  download—harus diberikan sebagai derivative watermark server-side;
- `false`: exact current canonical artifact boleh diberikan setelah Policy
  dokumen lulus;
- flag hanya memilih rendition dan tidak memberi capability akses;
- tidak ada flag view/download terpisah dan tidak ada endpoint original bypass
  untuk posisi berflag `true`;
- posisi existing dan posisi baru default `false`;
- nilai `true` hanya diaktifkan manual melalui Management User untuk posisi
  terpilih setelah enforcement watermark siap;
- perubahan flag wajib melalui flow management terotorisasi dengan reason dan
  audit before/after.

Admin Super saat memakai posisi bisnis nyata miliknya mengikuti flag posisi
nyata. Admin Super saat **acting like** adalah pengecualian final: delivery mode
selalu diperlakukan sebagai `pdf_watermark_required=false`, tetapi scope posisi
efektif dan Policy dokumen tetap harus lulus. Guest bukan row `user_positions`;
guest hanya dapat menerima dokumen public-access dan selalu memakai public
watermark. Detail canonical berada di
`../08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`.

### Masa berlaku

Posisi dianggap dapat dipakai jika seluruh kondisi terpenuhi:

```text
is_active = true
AND deleted_at IS NULL
AND (started_at IS NULL OR started_at <= hari ini)
AND (ended_at IS NULL OR ended_at >= hari ini)
```

## 4. Konsistensi organisasi

`instansi_id` tetap dipertahankan sesuai kebutuhan aplikasi dan dump lama. Karena `unit_kerjas` juga memiliki `instansi_id`, migration memakai composite foreign key:

```text
user_positions(unit_kerja_id, instansi_id)
→ unit_kerjas(id, instansi_id)
```

Database akan menolak posisi yang memasangkan unit kerja dengan instansi yang salah.

## 5. Mengapa SK dipisahkan

`file_sk_path` tidak disimpan pada `user_positions`. Dokumen ditempatkan di `user_position_documents` karena:

1. Satu posisi dapat memiliki SK awal, perubahan, pencabutan, atau dokumen pendukung.
2. File memiliki metadata sendiri: nomor, tanggal, penerbit, ukuran, MIME type, checksum, versi, pengunggah, dan verifikator.
3. Query login dan pergantian posisi tidak perlu membaca row yang lebar atau metadata dokumen.
4. Dokumen dapat diverifikasi, diarsipkan, atau diganti tanpa mengubah master posisi.
5. Checksum SHA-256 dapat mendeteksi file berubah atau diunggah dua kali.
6. Histori dokumen lebih mudah diaudit.

### Kapan `file_sk_path` boleh tetap disatukan?

Hanya apabila secara permanen dipastikan satu posisi selalu memiliki tepat satu file dan tidak memerlukan nomor SK, versi, verifikasi, checksum, serta histori. Kondisi SITANGKAS tidak memenuhi batasan tersebut, sehingga pemisahan lebih disarankan.

## 6. Pemetaan dump lama

Kolom lama:

```text
user_positions.file_sk_path
```

dipindahkan menjadi record:

```text
user_position_documents.user_position_id = user_positions.id
user_position_documents.document_type = appointment_sk
user_position_documents.storage_disk = private
user_position_documents.file_path = path hasil salinan pada disk private
user_position_documents.original_name = basename(file_sk_path lama)
user_position_documents.version = 1
user_position_documents.is_primary = true
user_position_documents.verification_status = draft
user_position_documents.source_system = legacy
user_position_documents.external_id = legacy-user:{legacy_id}:file-sk
```

Keputusan import 2026-09-15: importer users/positions memakai strategi `defer`.
Dokumen diproses terpisah dan hanya file fisik yang tersedia serta valid yang
boleh disalin. Path legacy yang missing, PDF rusak, `.filepart`, dan file fisik
orphan tidak dibuatkan row target. `public/SuratKeterangan` hanya staging source;
path publik lama tidak boleh menjadi `file_path` final. Detail snapshot dan
manifest berada di `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md`.

`started_at`, `ended_at`, dan `is_active` dari dump tetap dapat dipetakan langsung, tetapi data soft-delete yang masih `is_active = 1` harus dibersihkan terlebih dahulu.

## 7. Audit aktivitas bisnis

Setiap tabel transaksi atau `audit_logs` harus menyimpan `user_position_id` yang sedang dipakai saat aktivitas terjadi. Menyimpan `user_id` saja tidak cukup karena satu pengguna dapat bertindak dengan beberapa jabatan dan unit kerja.

Minimum konteks audit aktivitas:

```text
user_id
user_position_id
jabatan_id snapshot
instansi_id snapshot
unit_kerja_id snapshot
request_id
ip_address
created_at
```

Snapshot berguna agar audit tetap menjelaskan konteks lama walaupun master jabatan atau unit kerja berubah nama.

## 8. Catatan performa

Indeks utama disusun mengikuti query aktual:

- `(user_id, is_active, last_used_at)` untuk login dan pemilih posisi.
- `(unit_kerja_id, jabatan_id, is_active)` untuk daftar pemegang jabatan per unit.
- `(instansi_id, is_active)` untuk administrasi instansi.
- `(ended_at, is_active)` untuk proses kedaluwarsa otomatis.
- `(user_id, jabatan_id, instansi_id, unit_kerja_id, is_active, deleted_at)`
  untuk filter dan validasi posisi pada Management Users.
- `(instansi_id, unit_kerja_id, jabatan_id, is_active, deleted_at, user_id)`
  untuk filter scope Management Users berdasarkan instansi, unit kerja, jabatan,
  dan status posisi.

Tidak dibuat indeks tunggal tambahan pada semua foreign key karena unique/composite index dan foreign key yang ada sudah memenuhi sebagian besar pola akses. Penambahan indeks baru harus didasarkan pada `EXPLAIN ANALYZE`, bukan perkiraan semata.
