# AI Agent Context: User Positions and Position Documents

## Purpose

Dokumen ini adalah sumber konteks utama bagi AI agent yang mengembangkan, meninjau, atau memodifikasi fitur posisi pengguna pada aplikasi SITANGKAS.

Tiga migration yang dibahas harus dipahami sebagai satu rangkaian desain:

1. `2026_07_28_140000_add_unit_kerja_instansi_candidate_key.php`
2. `2026_07_28_140100_create_user_positions_table.php`
3. `2026_07_28_140200_create_user_position_documents_table.php`

Urutan tersebut tidak boleh dibalik karena migration kedua bergantung pada candidate key yang dibuat oleh migration pertama, dan migration ketiga bergantung pada tabel `user_positions` yang dibuat oleh migration kedua.

---

## Domain meaning

### `users`

Menyimpan akun yang berhasil diautentikasi ke aplikasi.

### `user_positions`

Menyimpan seluruh konteks kerja yang sah dan dapat dipilih oleh satu pengguna.

Satu pengguna dapat mempunyai satu atau beberapa posisi. Setiap posisi dibentuk oleh kombinasi:

- `user_id`
- `jabatan_id`
- `instansi_id`
- `unit_kerja_id`

Contoh:

```text
User: Ahmad

Posisi yang tersedia:
1. KPA  | BKAD | Sekretariat
2. PPTK | BKAD | Bidang Anggaran
3. BPP  | BKAD | Unit Kerja A
```

Semua posisi tersebut dapat mempunyai `is_active = true` secara bersamaan.

### `user_position_documents`

Menyimpan dokumen penetapan, perubahan, pencabutan, dan dokumen pendukung untuk suatu posisi pengguna.

Satu posisi dapat mempunyai banyak dokumen dan banyak versi dokumen.

---

## Critical rule: available position is not current session position

`user_positions.is_active` berarti posisi masih tersedia dan boleh dipilih pengguna.

`is_active` tidak berarti posisi tersebut sedang digunakan pada request atau session saat ini.

Posisi yang sedang digunakan harus disimpan di session aplikasi:

```php
session([
    'active_user_position_id' => $userPosition->id,
]);
```

AI agent tidak boleh menambahkan atau menggunakan kolom berikut pada `user_positions` untuk kebutuhan session:

```text
is_current
is_selected
currently_used
active_in_session
```

Alasannya, satu pengguna dapat membuka beberapa session atau perangkat yang masing-masing memakai posisi berbeda.

---

## Critical rule: real position versus effective context

Project saat ini memakai `App\Services\Auth\CurrentUserContext` sebagai sumber
context resmi.

Untuk user non Admin Super:

- `CurrentUserContext::realActivePosition($request)` mengembalikan row
  `user_positions` aktif;
- `CurrentUserContext::activePosition($request)` mengembalikan row yang sama;
- `id`, `jabatan_id`, `instansi_id`, dan `unit_kerja_id` semuanya berasal dari
  `user_positions` aktif.

Untuk Admin Super dengan acting context:

- `realActivePosition()` tetap mengembalikan row `user_positions` asli milik
  Admin Super;
- `activePosition()` mengembalikan effective context overlay dari pilihan
  `login.post`;
- `activePosition()->id` tetap id real `user_positions` Admin Super;
- `activePosition()->jabatan_id`, `activePosition()->instansi_id`, dan
  `activePosition()->unit_kerja_id` mengikuti pilihan acting context;
- `activePosition()->real_user_position_id` menunjuk id real `user_positions`
  Admin Super;
- `activePosition()->is_acting_context` bernilai `true`.

AI agent harus membedakan kebutuhan berikut:

- filter operasional dashboard/modul: pakai `jabatan_id`, `instansi_id`, dan
  `unit_kerja_id` dari `activePosition()`;
- audit posisi asli user: pakai `realActivePosition()` atau
  `real_user_position_id`;
- validasi apakah Admin Super boleh membuka `login.post`: pakai
  `realActivePosition()`, bukan `activePosition()`;
- jangan membuat row `user_positions` baru untuk acting context Admin Super.

### Critical rule: PDF watermark policy

Target schema mempunyai tepat satu boolean pada posisi:

```text
user_positions.pdf_watermark_required BOOLEAN NOT NULL DEFAULT FALSE
```

Kolom ini menentukan rendition PDF **setelah** Policy dokumen mengizinkan aksi;
kolom ini bukan permission. Berlaku untuk seluruh preview, view, dan download:

- seluruh posisi existing dan posisi baru default `false`;
- nilai `true` hanya diaktifkan manual melalui Management User;
- posisi nyata aktif dengan nilai `true`: hanya derivative watermark server-side;
- posisi nyata aktif dengan nilai `false`: exact original canonical boleh dikirim;
- Admin Super dalam mode **acting like**: selalu efektif `false`, terlepas dari
  nilai row posisi real Admin Super;
- Admin Super pada posisi bisnis nyata miliknya: mengikuti nilai row posisi itu;
- guest tidak memiliki posisi dan, jika public-access policy lulus, selalu
  menerima public-watermarked derivative;
- authenticated user tanpa posisi aktif valid: delivery fail-closed.

Jangan membuat `pdf_view_watermark_required` atau
`pdf_download_watermark_required`. Byte PDF yang tampil di viewer dapat disimpan,
sehingga kedua jalur wajib memakai resolver yang sama. Nilai default `false`
berlaku untuk posisi existing dan posisi baru; aktivasi `true` dilakukan manual
per posisi dan hasilnya diaudit. Detail delivery, COPY-ID, cache, dan audit
berada di `../08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`.

---

## Position selection flow

### Setelah login berhasil

1. Ambil semua posisi milik pengguna yang:
   - `is_active = true`
   - `deleted_at IS NULL`
   - `started_at IS NULL` atau `started_at <= hari ini`
   - `ended_at IS NULL` atau `ended_at >= hari ini`
2. Jika tidak ada posisi, hentikan akses ke area bisnis dan tampilkan pesan bahwa posisi belum ditetapkan.
3. Jika hanya ada satu posisi, gunakan posisi tersebut.
4. Jika ada beberapa posisi, pilih posisi dengan `last_used_at` terbaru.
5. Simpan `user_position_id` ke session.
6. Perbarui `last_used_at` tanpa mengubah `updated_at`.

Contoh query:

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
    ->first();
```

### Saat berganti posisi

Route canonical untuk UI berganti posisi adalah `GET/POST /positions`.
`GET /login/context` hanya legacy redirect/compatibility.

AI agent wajib memvalidasi bahwa posisi:

- benar-benar milik `auth()->id()`;
- masih aktif;
- belum soft-delete;
- berada dalam periode berlaku;
- seluruh relasi jabatan, instansi, dan unit kerjanya masih valid.

Setelah valid:

```php
session([
    'active_user_position_id' => $position->id,
]);
```

Kemudian perbarui `last_used_at` menggunakan query builder atau update yang tidak menyentuh `updated_at`.

---

## Authorization rule

Setiap request bisnis yang bergantung pada posisi wajib memverifikasi `active_user_position_id` dari session.

Jangan hanya percaya ID dari form, URL, cookie, atau JavaScript.

Minimal verifikasi:

```php
$activePosition = UserPosition::query()
    ->whereKey(session('active_user_position_id'))
    ->where('user_id', auth()->id())
    ->where('is_active', true)
    ->whereNull('deleted_at')
    ->firstOrFail();
```

Validasi periode berlaku juga harus diterapkan.

---

## Audit rule

Setiap transaksi atau audit aktivitas penting harus menyimpan setidaknya:

```text
user_id
user_position_id
```

Perubahan `pdf_watermark_required` juga wajib mempunyai audit before/after,
aktor, alasan, dan timestamp. Event akses PDF sendiri disimpan append-only pada
audit delivery yang dirancang di cluster eSign, bukan dengan mengubah
`last_used_at` atau master posisi.

Penyimpanan hanya `user_id` tidak cukup karena satu pengguna dapat bertindak dengan jabatan dan unit kerja berbeda.

Untuk audit jangka panjang, pertimbangkan snapshot konteks pada tabel transaksi atau audit log:

```text
jabatan_id
nama_jabatan
instansi_id
nama_instansi
unit_kerja_id
nama_unit_kerja
```

Snapshot diperlukan karena nama jabatan atau struktur organisasi dapat berubah di kemudian hari.

---

## Deactivation and deletion semantics

### Nonaktifkan posisi

Gunakan:

```text
is_active = false
deactivated_at = waktu saat ini
deactivated_by_user_id = aktor
deactivation_reason = alasan
```

Gunakan penonaktifan ketika posisi tidak lagi boleh dipilih, tetapi record masih valid sebagai histori administratif.

Pada Management Users, flow nonaktif posisi sudah memakai modal alasan dan
harus mengisi `deactivation_reason`. Hard delete tetap hanya untuk record salah,
duplikat, atau penghapusan administratif yang memang harus disembunyikan dari
operasi normal.

### Soft delete

Gunakan `deleted_at` hanya untuk record yang salah, duplikat, atau tidak seharusnya ada.

Saat soft delete, isi juga:

```text
deleted_by_user_id
```

### Restore

Untuk posisi manual/canonical, kombinasi konteks bersifat unik. Posisi yang
pernah dihapus dan diperlukan kembali harus di-restore, bukan dibuat sebagai
record operasional duplikat baru.

Alias non-selectable untuk mempertahankan ID row import legacy adalah
pengecualian terencana, bukan posisi operasional tambahan. Schema canonical dan
alias sudah tersedia melalui forward migration 2026-09-14, sedangkan proses
klasifikasi dan importnya harus mengikuti
`../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md`.

---

## Document handling rules

Dokumen SK tidak disimpan langsung sebagai `file_sk_path` pada `user_positions`.

Untuk import users legacy, keputusan 2026-09-15 adalah memakai
`file_sk_strategy=defer` pada importer users/positions. Hanya file fisik SK yang
tersedia dan valid yang boleh diimpor oleh pipeline dokumen terpisah; missing,
rusak, `.filepart`, dan orphan di-skip serta dilaporkan. Folder
`public/SuratKeterangan` hanya staging source dan tidak boleh menjadi storage
final. Baca `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md` sebelum mengubah
pipeline ini.

Known issue: `CreateManagedUserPosition` dan `UpdateManagedUserPosition` masih
memakai `store('sk', 'public')`. Jangan menganggap implementasi tersebut sudah
memenuhi invariant dokumen private. Sebelum produksi, pindahkan upload baru dan
download ke storage/endpoint berotorisasi dengan rencana migrasi file yang sudah
ada.

Gunakan `user_position_documents` karena satu posisi dapat memiliki:

- SK penetapan awal;
- SK perubahan;
- SK pencabutan;
- dokumen pendukung;
- beberapa versi dokumen.

File wajib disimpan pada storage privat. Database hanya menyimpan path dan metadata, bukan binary file.

Jangan menyimpan URL publik permanen pada `file_path`.

Untuk akses pengguna, utamakan streaming melalui controller yang memiliki
authorization check dan resolver delivery PDF. Temporary signed URL hanya boleh
dipakai jika tetap melewati keputusan `pdf_watermark_required`; URL tersebut
tidak boleh membuka original langsung bagi posisi berflag `true` atau guest.

State Management Users saat ini hanya upload file SK dan metadata
`document_type`, `document_number`, `document_date`, serta `issued_by`. Workflow
verifikasi dokumen belum dibuat dan tidak boleh diasumsikan tersedia.

---

## Sensitive data rules

AI agent tidak boleh menyimpan pada `metadata`, `notes`, atau kolom lain:

- password;
- passphrase TTE;
- access token;
- refresh token;
- API key;
- cookie;
- session ID mentah;
- Authorization header;
- credential layanan penyimpanan;
- isi file dalam Base64.

---

## Legacy users.sql identity contract

Keputusan khusus untuk `dump-keuangan-202609090855.sql` sudah disetujui dan
diimplementasikan bertahap. Opsi `--commit` dan `--fingerprint=` sudah
terdaftar. Mode commit menghitung ulang analyzer read-only dan memeriksa
fingerprint, meminta konfirmasi operator, lalu memanggil action yang masih
diblokir safety gate:

```text
legacy users.id = target user_positions.id
target user_positions.user_id = target users.id hasil distinct NIK
```

Kolom sumber `uuid` dan `access` tidak digunakan. Seluruh 1.514 row legacy harus
tetap dapat direpresentasikan sebagai posisi karena `document_process.id_user`
dan kolom aktor `document` memakai ID legacy tersebut.

Staging resmi untuk importer adalah `sitangkas_legacy.users`. Pada 2026-09-14
staging tersebut sudah diverifikasi memiliki 1.514 row, ID unik lengkap 1 sampai
1514, 1.053 NIK, dan struktur 16 kolom yang sesuai sumber. Akses importer harus
read-only dan setiap `--commit` tetap wajib menjalankan preflight verification.
`LegacyUserSourceReader` sekarang menjadi satu-satunya source reader aplikasi:
reader menghasilkan DTO immutable `LegacyUserRow`, membaca secara lazy menurut
ID, dan memverifikasi sesi `legacy_import` berstatus read-only. Jangan mengambil
data staging langsung dari controller atau memakai model Eloquent target.

Analyzer read-only tersedia melalui
`php artisan legacy:import-users --dry-run`. Laporan private terakhir memastikan
1.514 posisi menjadi 1.143 canonical dan 371 alias dalam 239 kelompok duplikat.
`LegacyOrganizationResolver` menetapkan instansi canonical berdasarkan unit
kerja; 51 mismatch organisasi seluruhnya cocok dengan allowlist, tanpa mismatch
tak dikenal atau referensi hilang. Baseline fingerprint saat ini adalah
`db31cc474ae465954797422f204143f669c125f1363e218e59796cb5e9f7ccf3` dan
dry-run menghasilkan 0 blocker.

`LegacyUserAccountAggregator` sudah menghasilkan satu akun per NIK dengan ID
row profil canonical. Rankingnya adalah aktif-nondeleted, nondeleted-inactive,
lalu histori terhapus; setiap tier memilih `created_at` terbaru dan legacy ID
terbesar sebagai tie-breaker. Profil selalu diambil utuh dari satu row. Baseline
menghasilkan 1.053 ID akun unik dengan checksum
`fdf4b66ce4980887fc7e693773a56c3a3eeb92d4888e5f7e849561e6431c15f8`.

`LegacyUserPositionClassifier` sudah memproyeksikan seluruh 1.514 row menjadi
1.143 posisi canonical dan 371 alias. Sebanyak 337 alias mempertahankan
`deleted_at` sumber dan 34 alias aktif ganda memperoleh soft-delete hasil
rekonsiliasi. Tidak ada row yang gagal diklasifikasikan, akun tanpa posisi
canonical, alias tanpa target, atau timestamp rekonsiliasi yang hilang. Checksum
klasifikasi adalah
`7efe79d9528bb96ea9284ef5c078292d65ae6e0b8c1d7caaee1d7082c741873a`.

Keputusan final 2026-09-14: satu NIK tidak boleh memiliki dua posisi aktif pada
kombinasi jabatan, instansi, dan unit kerja yang sama. Setelah organisasi
dinormalisasi, row aktif dengan `created_at` terbaru menjadi canonical; tie
diputuskan dengan legacy ID terbesar. Row lama tetap disimpan sebagai alias,
dinonaktifkan, dan di-soft-delete pada waktu canonical baru dibuat. `deleted_at`
sumber yang sudah ada tidak boleh ditimpa. Seluruh transformasi wajib masuk
laporan rekonsiliasi.

Collision posisi ID 1 sampai 6 sudah diselesaikan pada 2026-09-09 dengan
menghapus tiga akun dan enam posisi target setelah backup terenkripsi. Tabel
`users` dan `user_positions` saat ini sengaja kosong untuk menunggu import
legacy. Forward migration canonical/alias sudah diterapkan pada 2026-09-14.
`App\Services\User\PositionIdentityResolver` sudah tersedia untuk menormalisasi
alias ke canonical, mengambil seluruh equivalent IDs, memeriksa ekuivalensi, dan
menerapkan filter query histori. Relasi canonical/alias mencakup row
soft-deleted. Jangan menjalankan import `--commit` sebelum pengaman transaksi dan
validator pasca-import selesai.

Saat membaca histori yang menyimpan legacy position ID, jangan membandingkan
langsung dengan satu ID posisi aktif. Gunakan `equivalentIds()`, `contains()`,
atau `whereEquivalent()` dari resolver. Resolver sengaja memvalidasi kesamaan
user, jabatan, instansi, dan unit kerja agar alias tidak memperluas scope.

Integrasi pertama selesai pada 2026-09-14 untuk controller `Data` dan pembayaran:

- filter `document.uploaded_by` dan `document.users_to` memakai equivalent IDs;
- gate detail, hapus, tolak, verifikasi, edit, update, dan submit memakai
  `contains()` bila ownership ditentukan oleh ID posisi;
- acting PPTK/BUD dinormalisasi melalui `pptkActorPosition()` dan
  `budActorPosition()`;
- join `document_process.id_user` dan penerima dokumen tetap menerima posisi
  soft-deleted agar histori alias tidak hilang;
- transaksi baru tetap menyimpan ID canonical tunggal.

Index pendukung telah diterapkan untuk `document (uploaded_by, deleted_at)`,
`document (users_to, deleted_at)`, dan
`document_process (id_user, created_at)`.

Baca `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md` sebagai sumber keputusan
lengkap sebelum mengubah importer, `users`, `user_positions`, authorization
dokumen, atau relasi `document_process`.

## Source synchronization rules

Kolom berikut digunakan untuk impor dan sinkronisasi idempotent:

```text
source_system
external_id
last_synced_at
```

Contoh `source_system`:

```text
manual
legacy
bkpsdm
api
```

Saat sinkronisasi ulang, cari record berdasarkan `source_system + external_id` terlebih dahulu.

Jangan membuat record baru apabila mapping eksternal sudah ada.

`external_id` boleh `NULL` untuk data manual. Pada MySQL, unique index tetap mengizinkan lebih dari satu kombinasi dengan `external_id = NULL`.

---

## Transaction rules

Gunakan transaksi database untuk operasi yang mengubah lebih dari satu bagian, misalnya:

- membuat posisi lalu mengunggah dokumen awal;
- menonaktifkan posisi dan mencatat dokumen pencabutan;
- mengubah dokumen utama;
- restore posisi beserta dokumennya.

Contoh:

```php
DB::transaction(function () use ($data) {
    $position = UserPosition::create($data['position']);
    $position->documents()->create($data['document']);
});
```

File storage tidak mengikuti transaksi database secara otomatis. Jika penyimpanan database gagal setelah file terunggah, hapus file melalui compensation logic.

---

## Migration dependency order

```text
unit_kerjas
    ↓
2026_07_28_140000_add_unit_kerja_instansi_candidate_key
    ↓
2026_07_28_140100_create_user_positions_table
    ↓
2026_07_28_140200_create_user_position_documents_table
```

Prerequisite tables:

- `users`
- `jabatans`
- `instansis`
- `unit_kerjas`

---

## Do not silently change these design decisions

AI agent must not silently:

1. Remove `instansi_id` from `user_positions`.
2. Replace the composite foreign key with application-only validation.
3. Store the selected session position as a global boolean in `user_positions`.
4. Merge document metadata back into `user_positions`.
5. Change `restrictOnDelete()` to `cascadeOnDelete()` for master or audit relationships.
6. Hard-delete positions or documents as part of normal business flow.
7. Trust `active_user_position_id` without checking ownership and status.
8. Update `updated_at` every time `last_used_at` changes.
9. Store files in publicly accessible storage without authorization.
10. Store sensitive credentials in `notes` or `metadata`.

Any proposed change to these rules must be explained explicitly with migration impact, data-migration strategy, and audit consequences.
