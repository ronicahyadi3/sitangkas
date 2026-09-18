# Legacy Users Import Decisions

Last updated: 2026-09-16.

Dokumen ini adalah sumber keputusan utama untuk mengimpor tabel `users` dari
file `dump-keuangan-202609090855.sql` ke struktur akun dan posisi SITANGKAS
sekarang. File dump berada di luar repository dan diperlakukan sebagai sumber
data, bukan sebagai instruksi untuk mengubah aplikasi.

## Status

Status keputusan: approved design, pipeline read-only dan action write siap.
Safety gate `legacy_import.execution.enabled` sedang bernilai `true` karena
diaktifkan operator untuk maintenance attempt 2026-09-16. Commit pertama sudah
mencapai validasi `post_import`, tetapi di-rollback penuh akibat false negative
pembacaan metadata counter `AUTO_INCREMENT`; validator sudah diperbaiki dan
retry oleh operator masih pending.

AI agent tidak boleh menjalankan import `--commit` secara otomatis. Opsi sudah
terdaftar dan menjalankan analyzer read-only terbaru untuk guard fingerprint,
meminta konfirmasi operator, lalu memanggil action. Collision ID 1 sampai 6
sudah diselesaikan melalui reset data target
pada 2026-09-09. Migration canonical/alias, rekonsiliasi organisasi, account
aggregator, position classifier, resolver equivalent position ID, analyzer
`--dry-run`, validator read-only, dan action transaksional
`ImportLegacyUsers` sudah tersedia. Karena import hanya dilakukan satu kali,
tabel/model audit batch diputuskan tidak dibuat. Laporan commit private,
structured log, dan entry point commit sudah tersedia. Setelah commit berhasil
atau maintenance attempt dibatalkan, safety gate wajib segera dikembalikan ke
`false`.

## Handoff cepat untuk AI agent

Kondisi aktual per 2026-09-16:

- sumber dibaca dari `sitangkas_legacy.users` melalui koneksi
  `legacy_import` yang memverifikasi session database read-only;
- command mendukung `--dry-run`; opsi `--commit` dan `--fingerprint=` sudah
  terdaftar; mode commit menghitung ulang analyzer read-only dan membandingkan
  fingerprint penuh dengan `hash_equals()`, mensyaratkan 0 blocker, lalu meminta
  konfirmasi ketik yang terikat pada prefix fingerprint. Setelah konfirmasi,
  command memanggil `ImportLegacyUsers` serta writer laporan;
- safety gate saat ini `true`, diaktifkan operator untuk maintenance attempt;
  jangan menganggap ini konfigurasi permanen dan kembalikan ke `false` setelah
  commit berhasil atau attempt dibatalkan;
- target `users` berisi 0 row dan `user_positions` berisi 0 row;
- dry-run terakhir lulus dengan 0 blocker;
- fingerprint source yang masih berlaku adalah
  `db31cc474ae465954797422f204143f669c125f1363e218e59796cb5e9f7ccf3`;
- commit 2026-09-16 05:23 UTC menerima konfirmasi operator dan seluruh insert
  berada dalam transaksi, tetapi `post_import` memblokir dua pemeriksaan
  metadata counter `AUTO_INCREMENT`; transaksi di-rollback dan laporan gagal
  tersimpan di `storage/app/private/legacy-import/users/commits`;
- akar masalah commit gagal tersebut adalah
  `information_schema.TABLES.AUTO_INCREMENT` dapat belum mencerminkan explicit
  ID yang belum commit. Validator sekarang mencocokkan ID maksimum dan atribut
  `AUTO_INCREMENT` dari `information_schema.COLUMNS`; jangan mengembalikan
  pemeriksaan counter lama ke dalam transaksi;
- rollback gagal tersebut dapat memajukan counter InnoDB, tetapi tidak
  meninggalkan row dan tidak mengubah explicit legacy ID pada retry. Tidak
  diperlukan truncate/reset sequence sebelum retry selama target tetap kosong;
- baseline tetap 1.514 source row, 1.053 akun canonical, 1.143 posisi
  canonical, dan 371 alias legacy;
- seluruh ID pada `document.uploaded_by`, `document.users_to`, dan
  `document_process.id_user` tercakup oleh 1.514 ID posisi hasil proyeksi;
- `LegacyUserImportValidator` sudah terintegrasi ke analyzer dan hasilnya masuk
  ke laporan JSON private pada `storage/app/private/legacy-import/users`;
- `LegacyUserImportCommitReportWriter` sudah tersedia untuk menulis laporan
  commit `completed` atau `failed` ke direktori private
  `legacy-import/users/commits`; writer sudah terhubung ke jalur command commit;
- strategi password produksi sudah dikunci ke
  `preserve_legacy_hash_force_change`; validator dan ringkasan command dry-run
  menampilkan strategi efektif tanpa menulis password atau hash;
- keputusan bisnis status akun sudah disetujui: akun aktif hanya bila mempunyai
  minimal satu posisi canonical aktif; baseline hasilnya 930 akun `active` dan
  123 akun `inactive`;
- `LegacyUserAccountStatusResolver` sudah menjadi sumber tunggal proyeksi
  status; validator dan dry-run mengunci count, reason, serta checksum resolusi
  `abc04401cdbb24b113b2b013edcc34edf9cdc43ee354e3a078560e961d00c569`;
- keputusan bisnis SK legacy sudah disetujui: hanya file fisik yang tersedia
  dan valid yang boleh masuk sistem; path tanpa file, file rusak, file partial,
  dan file fisik orphan tidak dibuatkan `user_position_documents`;
- import users/positions tetap memakai `file_sk_strategy=defer`; dokumen SK
  diimpor melalui command/action terpisah setelah posisi tersedia;
- analyzer SK read-only sudah tersedia melalui
  `legacy:import-user-position-documents --dry-run`; hasil terakhir lulus dengan
  0 blocker dan tidak mengubah database maupun direktori sumber;
- override shared password hanya tersedia untuk development lokal, default
  nonaktif, dan diblokir bila environment bukan `local`;
- pada environment lokal saat maintenance attempt terakhir, override shared
  password efektif sedang aktif dan `must_change_password` efektif `false`;
  jangan mencatat nilai password/hash dan jangan memakai mode ini di produksi;
- `App\Actions\LegacyImport\ImportLegacyUsers` sudah memiliki advisory lock,
  bulk insert transaksional, dan validasi pasca-insert. Gate saat ini aktif
  sementara; strategi status akun dan strategi file SK `defer` sudah dikunci;
- action hanya direferensikan oleh command import resmi dan menerima fingerprint
  persetujuan sebagai argumen wajib; route, controller, scheduler, job, dan
  Tinker tetap bukan entry point yang diizinkan;
- sesuai instruksi pengguna, AI agent tidak boleh membuat, mengubah, atau
  menjalankan test suite/Pest/PHPUnit tanpa permintaan eksplisit.

File utama pipeline saat ini:

```text
app/Console/Commands/LegacyImport/ImportLegacyUsersCommand.php
app/Actions/LegacyImport/AnalyzeLegacyUsers.php
app/Actions/LegacyImport/ImportLegacyUsers.php
app/Actions/LegacyImport/AnalyzeLegacyUserPositionDocuments.php
app/Console/Commands/LegacyImport/ImportLegacyUserPositionDocumentsCommand.php
app/Services/LegacyImport/LegacyUserSourceReader.php
app/Services/LegacyImport/LegacyOrganizationResolver.php
app/Services/LegacyImport/LegacyUserAccountAggregator.php
app/Services/LegacyImport/LegacyUserAccountStatusResolver.php
app/Services/LegacyImport/LegacyUserPositionClassifier.php
app/Services/LegacyImport/LegacyUserImportValidator.php
app/Services/LegacyImport/LegacyUserImportPasswordPolicyResolver.php
app/Services/LegacyImport/LegacyUserImportCommitReportWriter.php
app/Services/LegacyImport/LegacyPdfInspector.php
app/Data/LegacyImport/LegacyUserImportValidation.php
app/Data/LegacyImport/LegacyUserImportPlan.php
app/Data/LegacyImport/LegacyUserImportPasswordPolicy.php
app/Data/LegacyImport/LegacyUserAccountStatus.php
app/Data/LegacyImport/LegacyUserAccountStatusResolution.php
app/Data/LegacyImport/LegacyUserPositionDocumentAnalysis.php
app/Data/LegacyImport/LegacyUserPositionDocumentReference.php
app/Data/LegacyImport/LegacyUserImportResult.php
app/Exceptions/LegacyImport/LegacyUserImportBlockedException.php
config/legacy_import.php
```

## Struktur sumber

Tabel `users` legacy memiliki 16 kolom:

| No. | Kolom | Tipe sumber |
|---:|---|---|
| 1 | `id` | `bigint unsigned` |
| 2 | `uuid` | `char(36) nullable` |
| 3 | `name` | `varchar(255)` |
| 4 | `status` | `int` |
| 5 | `access` | `varchar(5) nullable` |
| 6 | `nip` | `varchar(18) nullable` |
| 7 | `nik` | `varchar(16) nullable` |
| 8 | `id_jabatan` | `int nullable` |
| 9 | `id_instansi` | `int nullable` |
| 10 | `id_unit_kerja` | `int nullable` |
| 11 | `file_sk` | `varchar(255)` |
| 12 | `email` | `varchar(255)` |
| 13 | `password` | `varchar(255)` |
| 14 | `created_at` | `timestamp nullable` |
| 15 | `updated_at` | `timestamp nullable` |
| 16 | `deleted_at` | `timestamp nullable` |

Snapshot analisis sumber:

- 1.514 baris dengan ID lengkap `1` sampai `1514`;
- 1.053 NIK berbeda;
- 314 kelompok NIK memiliki lebih dari satu baris;
- 1.143 kombinasi konteks posisi berbeda setelah rekonsiliasi organisasi;
- 239 kelompok mengulang kombinasi NIK, jabatan, instansi, dan unit yang sama;
- kelompok duplikat mencakup 610 row, sehingga terdapat 371 row berlebih;
- 34 kelompok memiliki lebih dari satu row aktif dan menghasilkan 34 row aktif
  berlebih yang harus dijadikan alias nonaktif;
- 450 baris memiliki `deleted_at`;
- 61 NIK hanya memiliki baris yang sudah soft-delete;
- tidak ada NIK atau email dengan format tidak valid;
- tidak ada email yang dipakai oleh lebih dari satu NIK;
- 17 row NIP masih berformat nonstandar dan menjadi warning non-blocking;
- 313 kelompok NIK mempunyai perbedaan profil yang sudah diselesaikan secara
  deterministik oleh account aggregator;
- 51 baris mempunyai pasangan unit kerja dan instansi yang tidak sesuai master
  target dan seluruhnya cocok dengan allowlist.

## Keputusan identitas

Kontrak identitas yang disetujui adalah:

```text
legacy users.id = target user_positions.id
target user_positions.user_id = target users.id
```

Akun target dibentuk satu kali untuk setiap NIK, sedangkan seluruh 1.514 baris
legacy tetap menjadi row `user_positions` dengan primary key legacy yang sama.
ID akun target memakai ID row profil canonical. Dengan demikian, setiap
`target users.id` sama dengan satu `target user_positions.id` milik akun
tersebut, tetapi posisi lain untuk NIK yang sama tetap mempunyai ID legacy
masing-masing dan menunjuk `user_id` akun canonical.

Contoh konseptual:

```text
legacy id=100, NIK=A, jabatan=PPTK, created_at lebih lama
legacy id=900, NIK=A, jabatan=KPA, created_at lebih baru

target users id=900, NIK=A
target user_positions id=100, user_id=900, jabatan=PPTK
target user_positions id=900, user_id=900, jabatan=KPA
```

Alasan mempertahankan ID posisi adalah kompatibilitas data transaksi legacy:

- `document_process.id_user` menunjuk ID row `users` legacy;
- `document.uploaded_by`, `document.users_to`, dan kolom aktor legacy lain juga
  memakai identitas posisi tersebut;
- kode histori saat ini melakukan join `document_process.id_user` ke
  `user_positions.id`.

Setelah import, posisi baru/manual tidak boleh memakai ulang rentang ID legacy.

## Arsitektur sumber import

File `dump-keuangan-202609090855.sql` adalah sumber data resmi. Laravel tidak
boleh mengeksekusi atau mem-parsing dump langsung pada database aplikasi. File
sudah di-restore ke database staging `sitangkas_legacy` dan harus dibaca melalui
koneksi Laravel read-only.

```text
dump-keuangan-202609090855.sql
    -> restore ke sitangkas_legacy
    -> koneksi read-only legacy_import
    -> analyzer --dry-run
    -> future import action transaksional
    -> future activation --commit
```

Staging `sitangkas_legacy.users` diverifikasi ulang pada 2026-09-14 dan sudah
sesuai dengan snapshot sumber: 1.514 row, 1.514 ID unik, ID lengkap 1 sampai
1514, 1.053 NIK berbeda, 16 kolom, 239 kelompok konteks duplikat, dan 371 row
berlebih. Staging ini menjadi sumber query importer. Importer tetap wajib
menjalankan preflight count/range dan mencatat checksum file sumber sebelum
`--commit` agar perubahan staging setelah verifikasi dapat terdeteksi.

Selesai 2026-09-14: `LegacyUserRow` menjadi DTO immutable untuk 14 kolom yang
digunakan, sedangkan `LegacyUserSourceReader` menyediakan `count()`, `find()`,
dan iterasi `lazy()` berdasarkan ID. Reader tidak mengekspos query builder,
tidak memilih `uuid` atau `access`, dan menolak bekerja bila sesi koneksi bukan
read-only. Koneksi `legacy_import` menginisialisasi sesi MySQL dengan
`SET SESSION TRANSACTION READ ONLY`. Produksi tetap wajib memakai akun MySQL
khusus yang hanya memiliki grant `SELECT`; session read-only adalah lapisan
tambahan, bukan pengganti pembatasan grant.

Selesai 2026-09-14: command berikut sudah tersedia dan hanya melakukan operasi
baca pada database sumber dan target:

```bash
php artisan legacy:import-users --dry-run
```

Analyzer memeriksa snapshot sumber, rentang dan keunikan ID, format identitas,
konflik profil/email, referensi master, mismatch organisasi, proyeksi posisi
canonical/alias, dan status. Laporan JSON disimpan pada disk `local` di
`storage/app/private/legacy-import/users`; laporan tidak memuat NIK, email,
password, atau hash password secara langsung. Identitas sensitif di laporan
direpresentasikan dengan HMAC fingerprint dan legacy row ID.

Dry-run aktual pertama menghasilkan fingerprint kolom terpakai
`92b5bbebd7894fda3dbc21a25ef4883cec44dbdddfbf4035f0fecfa91d2e34d6`.
Pada saat itu import masih diblokir oleh satu NIK tidak valid pada legacy row ID
1364.

Setelah data legacy diperbaiki, baseline yang disetujui menghasilkan fingerprint
kolom terpakai
`db31cc474ae465954797422f204143f669c125f1363e218e59796cb5e9f7ccf3`.
Dry-run menghasilkan 0 blocker, 1.053 akun, 1.143 posisi canonical, 371 alias,
239 kelompok konteks duplikat, dan 51 koreksi organisasi allowlist. Warning yang
masih ada adalah 17 row NIP nonstandar dan 313 kelompok konflik profil per NIK;
keduanya harus ditangani oleh aturan aggregator dan tidak perlu mengubah seluruh
row legacy secara manual.

## Keputusan kolom

| Kolom sumber | Tujuan | Keputusan |
|---|---|---|
| `id` | `user_positions.id` | Wajib dipertahankan persis |
| `uuid` | Tidak ada | Tidak digunakan dan tidak disimpan |
| `name` | `users.nama` | Digunakan melalui pemilihan akun canonical |
| `status` | `user_positions.is_active` dan agregasi `users.status` | Digunakan setelah transformasi |
| `access` | Tidak ada | Tidak digunakan dan tidak boleh dipetakan ke `tahun_aktif` |
| `nip` | `users.nip` | Digunakan setelah rekonsiliasi per NIK |
| `nik` | `users.nik` | Wajib; dasar deduplikasi akun |
| `id_jabatan` | `user_positions.jabatan_id` | Wajib |
| `id_instansi` | `user_positions.instansi_id` | Wajib; mismatch harus direkonsiliasi |
| `id_unit_kerja` | `user_positions.unit_kerja_id` | Wajib |
| `file_sk` | `user_position_documents` | Import terpisah; hanya file fisik valid, missing di-skip |
| `email` | `users.email` | Digunakan setelah validasi unique dan format |
| `password` | `users.password` | Pertahankan hash canonical dan wajib ganti password |
| `created_at` | `users` dan `user_positions` | Posisi memakai nilai row; akun memakai agregasi |
| `updated_at` | `users` dan `user_positions` | Posisi memakai nilai row; akun memakai agregasi |
| `deleted_at` | Terutama `user_positions.deleted_at` | Dipertahankan untuk histori |

Keputusan final yang tidak boleh diubah tanpa persetujuan baru:

- `uuid` tidak digunakan pada tabel target, metadata, atau `external_id`;
- `access` tidak digunakan;
- `access` bukan `tahun_aktif` dan bukan permission tahun historis;
- seluruh ID legacy `1` sampai `1514` dipertahankan pada
  `user_positions.id`;
- akun `users` dibentuk berdasarkan NIK unik.

## Transformasi status

Sumber mempunyai `status=1` pada 1.421 baris dan `status=0` pada 93 baris.
Karena satu row legacy mewakili akun sekaligus posisi, rekomendasi transformasi
adalah:

```text
status=1 dan deleted_at NULL -> posisi dapat menjadi aktif
status=0 atau deleted_at terisi -> posisi tidak aktif
```

Status akun diturunkan dari seluruh hasil klasifikasi posisi milik NIK tersebut,
bukan dari satu row profil pemenang. Keputusan final 2026-09-15:

```text
account_status_strategy = active_if_any_active_position_else_inactive
```

Aturan semantiknya:

- `active` hanya bila ada minimal satu posisi dengan `is_canonical=true`,
  `is_active=true`, `deleted_at=null`, dan referensi organisasi valid;
- `inactive` bila posisi canonical tersedia tetapi semuanya nonaktif;
- `inactive` bila seluruh posisi legacy sudah terhapus;
- posisi alias tidak pernah membuat akun aktif;
- `pending`, `locked`, dan `suspended` tidak boleh disimpulkan dari status
  legacy karena ketiganya mempunyai arti keamanan/administratif baru.

Baseline yang disetujui adalah 930 akun aktif dan 123 akun inactive. Dari 123
akun inactive, 62 mempunyai row nondeleted tetapi tidak aktif dan 61 hanya
mempunyai histori row terhapus. Importer harus mengisi alasan deterministik:

```text
legacy_import:has_active_canonical_position
legacy_import:no_active_canonical_position
legacy_import:all_positions_deleted
```

`status_changed_at` memakai waktu import dan `status_changed_by_user_id` tetap
`null` karena perubahan dibuat oleh proses sistem. Implementasi
`LegacyUserAccountStatusResolver`, integrasi action, validator sebelum dan
sesudah import, serta ringkasan dry-run selesai pada 2026-09-15. Checksum
resolusi status yang dikunci adalah
`abc04401cdbb24b113b2b013edcc34edf9cdc43ee354e3a078560e961d00c569`.
Perubahan hasil resolver harus membuat dry-run gagal sampai snapshot baru
ditinjau dan disetujui.

## Agregasi akun canonical

Selesai 2026-09-14: `LegacyUserAccountAggregator` menghasilkan tepat satu calon
akun untuk setiap NIK valid dengan aturan deterministik berikut:

1. Prioritaskan row `status=1` dan `deleted_at IS NULL`.
2. Jika tidak ada, prioritaskan row nondeleted meskipun `status=0`.
3. Jika seluruh row terhapus, gunakan row histori terhapus.
4. Di dalam tier yang sama, pilih `created_at` terbaru.
5. Jika `created_at` sama atau kosong, pilih legacy ID terbesar.

Nama, NIP, email, dan hash password diambil utuh dari row pemenang; field
profil tidak dicampur dari beberapa row. Nama dinormalisasi dengan whitespace
tunggal, NIP di-trim, dan email di-trim serta dibuat lowercase. Password hash
hanya berada pada properti privat DTO dan tidak pernah masuk laporan dry-run.
Keputusan produksi sudah dikunci untuk mempertahankan hash canonical tersebut
dan mewajibkan perubahan password setelah login pertama.

Baseline agregasi saat ini:

- 1.053 akun dengan 1.053 ID unik;
- 930 akun mempunyai minimal satu row aktif;
- 62 akun memakai fallback row nondeleted-inactive;
- 61 akun memakai fallback row histori terhapus;
- 314 akun dibentuk dari lebih dari satu row sumber;
- 9 row canonical mempunyai NIP nonstandar dan tetap ditampilkan sebagai
  warning untuk review;
- checksum ID akun canonical adalah
  `fdf4b66ce4980887fc7e693773a56c3a3eeb92d4888e5f7e849561e6431c15f8`.

Laporan hanya menyimpan HMAC fingerprint NIK, ID row sumber, field yang
berkonflik, tier pemenang, dan checksum ID. Nilai NIK, nama, email, serta hash
password tidak ditulis ke laporan.

## Keputusan posisi dengan konteks sama

Keputusan final per 2026-09-14:

> Satu NIK tidak boleh memiliki lebih dari satu posisi aktif untuk kombinasi
> jabatan, instansi, dan unit kerja yang sama.

Konteks dibandingkan setelah rekonsiliasi organisasi. Perbedaan nama, gelar,
teks sekolah, email, atau file SK tidak membuat konteks posisi baru selama NIK,
jabatan, instansi, dan unit kerja tetap sama.

Pemilihan canonical bersifat deterministik:

1. Dari row `status=1` dan `deleted_at IS NULL`, pilih `created_at` terbaru.
2. Jika `created_at` sama, pilih legacy `id` terbesar.
3. Jika tidak ada row aktif, pilih row nondeleted terbaru sebagai canonical
   nonaktif.
4. Jika seluruh row sudah terhapus, pilih row terbaru sebagai canonical
   historis dan tetap nonaktif.

Semua row selain pemenang tetap diimpor agar ID legacy dan relasi dokumen tidak
hilang. Row tersebut menjadi alias non-selectable. Untuk alias yang sudah
memiliki `deleted_at`, pertahankan tanggal sumber. Untuk alias yang masih aktif
di sumber, hasil transformasi menggunakan:

```text
is_canonical = false
canonical_user_position_id = ID pemenang
is_active = false
ended_at = tanggal created_at pemenang
deactivated_at = tanggal created_at pemenang
deactivation_reason = superseded_by_newer_legacy_position
legacy_duplicate_reason = duplicate_context_older_record
deleted_at = tanggal created_at pemenang
```

Perubahan `deleted_at` tersebut adalah hasil rekonsiliasi target, bukan nilai
asli sumber. Importer wajib menyimpan `original_deleted_at`, nilai hasil, ID
canonical, dan alasan transformasi pada laporan private. Tidak ada posisi yang
dihapus fisik dan tidak ada ID pada `document` atau `document_process` yang
ditulis ulang.

## Kesiapan schema saat ini

Collision primary key dan blocker schema canonical/alias sudah selesai.
Migration forward telah membuat constraint hanya berlaku pada posisi canonical,
sehingga 371 alias legacy dapat mempertahankan primary key sumber. Resolver
organisasi juga telah menangani 51 koreksi allowlist tanpa mismatch tak dikenal
atau referensi master yang hilang.

Validator read-only sudah tersedia dan memeriksa target serta relasi dokumen.
Action import transaksional juga sudah dibuat, tetapi safety gate masih menolak
seluruh eksekusi. Pekerjaan yang masih wajib sebelum import `--commit` tersedia
adalah mengunci aturan transformasi yang belum diputuskan, membuat audit
batch/laporan final, dan mereview action sebelum mengeksposnya melalui command.
Integrasi resolver pada jalur `Data` dan pembayaran yang ditemukan saat audit
sudah selesai. Modul bisnis baru yang membaca kolom posisi legacy wajib
menggunakan kontrak resolver yang sama.

## Validator import read-only

Selesai 2026-09-14: `App\Services\LegacyImport\LegacyUserImportValidator`
menjadi satu gerbang invariant untuk proyeksi, preflight, dan validasi hasil
insert pada action transaksional yang akan dibuat nanti. Service ini tidak
memanggil `insert`, `update`, `delete`, transaction commit, atau DDL.

API validator:

- `validateProjection()` memeriksa DTO hasil aggregator dan classifier tanpa
  mengakses database;
- `validateBeforeImport()` memeriksa snapshot yang disetujui, invariant
  proyeksi, target kosong, collision ID, dan cakupan referensi histori;
- `validateAfterImport()` sudah disiapkan untuk dipanggil di dalam transaksi
  sebelum commit dan membandingkan row target dengan proyeksi immutable.

Preflight saat ini memblokir import bila menemukan salah satu kondisi berikut:

- fingerprint/count/range sumber berbeda dari `config/legacy_import.php`;
- NIK atau email canonical duplikat, nama/password kosong, atau email tidak
  valid;
- posisi tidak mempunyai akun, akun tidak mempunyai posisi dengan ID miliknya,
  bentuk canonical/alias tidak valid, atau target alias tidak sesuai konteks;
- strategi, count, reason, checksum, atau hubungan status akun dengan posisi
  canonical final berbeda dari snapshot yang disetujui;
- strategi password produksi berubah, strategi efektif tidak dikenal, override
  development tidak konsisten atau digunakan di luar `local`, force-change
  produksi tidak aktif, atau hash proyeksi tidak dikenali;
- tabel target tidak kosong atau terdapat collision primary key;
- ID aktor pada `document.uploaded_by`, `document.users_to`, atau
  `document_process.id_user` tidak tersedia dalam proyeksi posisi.

Validasi pasca-insert memeriksa ID hilang/tidak dikenal, mismatch field akun,
status beserta reason dan metadata perubahannya, mismatch posisi, orphan posisi,
kontrak `users.id = user_positions.id`, serta orphan referensi histori.
Preflight password memeriksa strategi dan format hash untuk seluruh akun
proyeksi. Nilai NIK/email bermasalah dilaporkan sebagai HMAC fingerprint;
password dan hash tidak masuk laporan maupun output command.

Baseline preflight terakhir:

```text
Validator: LULUS
Blocker: 0
Target users: 0
Target user_positions: 0
Uncovered document.uploaded_by IDs: 0
Uncovered document.users_to IDs: 0
Uncovered document_process.id_user IDs: 0
```

## Action import transaksional

Selesai 2026-09-16: `App\Actions\LegacyImport\ImportLegacyUsers` sudah terhubung
ke command resmi setelah fingerprint guard dan konfirmasi operator. Action
menggunakan plan immutable yang sama dengan dry-run melalui
`AnalyzeLegacyUsers::plan()`, sehingga analyzer dan importer tidak mempunyai
aturan agregasi atau klasifikasi yang berbeda. Fingerprint persetujuan diterima
sebagai argumen wajib dan dibandingkan lagi dengan plan action sebelum transaksi.

Urutan internal action:

1. Tolak eksekusi bila `legacy_import.execution.enabled` belum `true`, masih ada
   `pending_decisions`, atau strategi eksekusi tidak dikenal.
2. Pastikan koneksi target adalah MySQL dan ambil advisory lock terparameterisasi
   `sitangkas:legacy-users-import`.
3. Bangun plan dari source read-only, bandingkan ulang fingerprint persetujuan,
   dan tolak plan yang mempunyai blocker.
4. Mulai satu transaksi target dan jalankan ulang `validateBeforeImport()`.
5. Bulk insert akun dalam chunk.
6. Bulk insert posisi canonical terlebih dahulu agar target self foreign key
   tersedia.
7. Bulk insert alias legacy setelah canonical.
8. Jalankan `validateAfterImport()` sebelum transaksi boleh commit.
9. Lempar `LegacyUserImportBlockedException` untuk memicu rollback penuh bila
   satu invariant gagal.
10. Lepaskan advisory lock pada blok `finally`.

Action memakai Query Builder `insert`, bukan Eloquent per row, agar 2.567 row
target tidak menghasilkan query dan model event satu per satu. `insertOrIgnore`
tidak digunakan; collision atau constraint error harus menggagalkan transaksi.
Ukuran insert default 250 row, transaction retry default 1, dan lock timeout
default 0 detik.

Konfigurasi efektif maintenance attempt saat ini:

```text
legacy_import.execution.enabled = true (sementara)
password_strategy = preserve_legacy_hash_force_change
account_status_strategy = active_if_any_active_position_else_inactive
file_sk_strategy = defer
development_password_override.enabled = true (environment local)
development_password_override.force_change = false
```

`file_sk_strategy=defer` berarti import akun dan posisi tidak membaca, menyalin,
atau menulis metadata dokumen SK. Dokumen SK tetap menjadi proses terpisah
setelah `user_positions` tersedia. Penguncian keputusan ini tidak mengaktifkan
safety gate dengan sendirinya; gate aktif sekarang karena tindakan eksplisit
operator dan wajib dikembalikan ke `false` setelah maintenance.

Keputusan password produksi sudah final:

- hash password row akun canonical dipertahankan;
- setiap akun hasil import mempunyai `must_change_password=true`;
- strategi efektif produksi adalah `preserve_legacy_hash_force_change`.

Keputusan bisnis berikut sudah final:

- status akun: `active_if_any_active_position_else_inactive`, sudah dipasang ke
  config dan tervalidasi;
- file SK pada importer users: `defer`, yang berarti action ini tidak menulis
  `user_position_documents`; nilai config sudah dikunci, tetapi safety gate
  import tetap nonaktif.

Untuk development lokal tersedia override terpisah:

```text
LEGACY_IMPORT_DEV_SHARED_PASSWORD_ENABLED=false
LEGACY_IMPORT_DEV_SHARED_PASSWORD=
LEGACY_IMPORT_DEV_FORCE_PASSWORD_CHANGE=false
```

Override hanya boleh aktif saat `APP_ENV=local`, shared password minimal 16
karakter, dan password di-hash satu kali untuk seluruh akun. Resolver akan
memblokir proses bila override aktif di environment selain `local`, password
kosong, atau panjangnya kurang dari 16 karakter. Nilai password dan hash tidak
boleh masuk source control, exception context, audit, laporan JSON, atau output
command. `.env.example` wajib mempertahankan nilai password kosong.

Action memvalidasi format hash dengan `password_get_info()` sebelum insert,
tanpa memasukkan nilai hash ke exception atau hasil. Akun memakai ID profil
canonical, sedangkan posisi memakai seluruh ID legacy. Posisi canonical selalu
diinsert sebelum alias. Hasil action berupa `LegacyUserImportResult` yang hanya
mencatat count, fingerprint sumber, keputusan non-rahasia, dan hasil validator.
Pemeriksaan read-only 2026-09-15 menemukan 0 hash tak dikenal pada 1.053 akun
canonical. Dry-run menampilkan tabel `Kebijakan Password Import` berisi strategi
configured/effective, status override, force-change, environment, jumlah akun,
dan jumlah hash tidak valid tanpa material rahasia.

Jangan mengaktifkan konfigurasi tersebut hanya untuk mencoba action. Resolver,
validator status, analyzer file SK, entry point commit, structured log, dan
writer laporan commit sudah selesai. Eksekusi final tetap memerlukan backup
target, verifikasi pasca-import, review prosedur maintenance window, dan aktivasi
safety gate secara terkontrol.

## Keputusan pencatatan import one-time

Keputusan pengguna 2026-09-16: import legacy users hanya dijalankan satu kali,
sehingga tabel/model `legacy_user_import_batches` dan audit event per row tidak
dibuat. Kompleksitas skema permanen tersebut tidak sebanding dengan kebutuhan
operasional satu kali.

Bukti eksekusi minimum tetap wajib tersedia melalui:

- laporan dry-run JSON private yang sudah ada;
- laporan commit JSON private untuk hasil berhasil maupun gagal;
- structured log Laravel berisi waktu mulai/selesai, fingerprint, status,
  durasi, dan count, tanpa password, hash, NIK lengkap, atau data sensitif;
- backup database target sebelum commit;
- fingerprint sumber lengkap yang wajib sama dengan dry-run yang disetujui;
- transaksi database dan rollback penuh ketika invariant gagal.

Laporan commit ditulis di luar transaksi data agar informasi kegagalan tetap
tersedia setelah rollback. Nama laporan harus mengandung timestamp, status, dan
prefix fingerprint. Keputusan melewati tabel audit ini tidak mengurangi
preflight, advisory lock, validasi target kosong, konfirmasi operator, maupun
safety gate.

Konfirmasi operator commit bersifat interaktif dan case-sensitive. Setelah
fingerprint guard lulus, operator wajib mengetik:

```text
IMPORT LEGACY USERS {12-karakter-prefix-fingerprint}
```

Mode `--no-interaction` harus gagal tertutup dan tidak boleh mempunyai bypass
sebelum ada keputusan operasional baru.

Pada 2026-09-09, tiga akun target dan enam posisi target ID 1 sampai 6 dihapus
secara permanen agar seluruh data `users` dan `user_positions` nantinya berasal
dari legacy. Lima metadata `user_position_documents` yang bergantung pada posisi
tersebut ikut dihapus dan session aktif terinvalidasi oleh foreign key cascade.
Tabel `document` dan `document_process` tidak diubah.

Sebelum reset dibuat backup terenkripsi Laravel pada
`storage/app/private/pre-legacy-user-reset-20260909-065208.json.enc` dengan
SHA-256
`617abd08b3c0e578125bebdfec2fb9f4a6d1475d5034795fcb055c02cd7f73ff`.
Backup hanya dapat dipulihkan selama `APP_KEY` yang dipakai saat backup tetap
tersedia dan rahasia.

Migration yang sudah pernah dijalankan tidak boleh diedit. Buat forward
migration baru untuk perubahan constraint dan kolom canonical/alias.

## Keputusan dan snapshot file SK legacy

Keputusan final pengguna 2026-09-15:

> File SK yang mempunyai file fisik dan valid boleh digunakan oleh sistem.
> Nilai `file_sk` yang tidak mempunyai file fisik tidak perlu dibuatkan dokumen
> target. Database legacy tetap read-only dan tidak boleh dibersihkan oleh
> importer.

Sumber fisik yang dianalisis:

```text
D:\Project Aplications\sitangkas\public\SuratKeterangan
```

Folder tersebut hanya boleh diperlakukan sebagai staging source. Ia berada di
web root dan tidak boleh menjadi storage final karena file dapat dilayani web
server tanpa authorization. Hasil import wajib disalin ke disk private dan
download dilakukan melalui controller/policy berotorisasi.

Snapshot read-only 2026-09-15:

```text
Legacy row / file_sk terisi       1514 / 1514
Nilai file_sk unik                1514
Path filename-only                1511
Path dengan prefix file_sk/          3
File fisik total                   741 (sekitar 350,5 MiB)
PDF                                740
File .filepart                       1
Row cocok berdasarkan basename     522
Row tanpa file fisik               992
File fisik tidak direferensikan    219 (termasuk .filepart)
Case-only mismatch                   0
Path traversal/absolute              0
```

Selesai 2026-09-15: snapshot tersebut sekarang dapat direproduksi dengan:

```bash
php artisan legacy:import-user-position-documents --dry-run
```

Command hanya menerima mode `--dry-run`, membaca kolom `id` dan `file_sk`
melalui sesi database legacy read-only, memindai filesystem tanpa mutasi, dan
menulis laporan JSON ke
`storage/app/private/legacy-import/user-position-documents`. PDF diperiksa dari
ekstensi, MIME aktual, header `%PDF-`, serta parser Poppler `pdfinfo` dengan
argument array terparameterisasi. Path traversal, prefix tak dikenal, symlink,
file tak terbaca, collision basename, referensi ambigu, case mismatch, atau
perubahan snapshot menjadi blocker.

Baseline analyzer yang dikunci:

```text
Reference SHA-256  aeadb2ad456c67e9d19ebb3c10f4c3ee431b7cb2ed9401edd719df661316d911
Manifest SHA-256   ee93c6fb181222bd1466d294d7371321f933f6a9631b1812212937b6f1e4c39e
Available valid    518
Missing            992
Invalid referenced 4 (legacy ID 283, 1404, 1456, 1470)
Blocker             0
```

Executable `pdfinfo` harus tersedia di `PATH` atau ditentukan melalui
`LEGACY_IMPORT_PDFINFO_BINARY`. Direktori staging dapat dioverride melalui
`LEGACY_IMPORT_SK_SOURCE_DIRECTORY`; nilai kosong memakai default
`public/SuratKeterangan`. Kedua nilai hanya mengatur analyzer dan tidak
mengaktifkan import.

Validasi ringan dan parser menemukan:

```text
PDF lolos parser                    730 dari 740
PDF gagal parser                     10
File cocok yang bersih ketat        518 dari 522
Checksum isi unik                   186 dari 740 PDF
Kelompok isi duplikat                61
File dalam kelompok isi duplikat    615
```

Tiga PDF gagal parser yang masih direferensikan row aktif adalah legacy ID
`1404`, `1456`, dan `1470`; error menunjukkan trailer/xref tidak terbaca. Legacy
ID `283` mempunyai header tidak standar walaupun parser masih dapat membacanya
dan harus masuk review. File
`46384a6b-e74c-4cca-83a1-823eec1ed169.pdf.filepart` wajib diabaikan. Tujuh PDF
gagal parser lainnya tidak direferensikan database legacy.

Sebanyak 218 PDF fisik tidak direferensikan nilai `file_sk`; file tersebut tidak
boleh ditebak kepemilikannya atau diimpor. Ada 40 kelompok checksum duplikat yang
melibatkan 388 dari 522 file yang direferensikan. Nama UUID berbeda bukan bukti isi
berbeda; duplicate content dicatat sebagai warning, bukan otomatis dihapus.

Empat file berubah setelah timestamp dump 2026-09-09, termasuk satu `.filepart`.
Karena itu angka 518 adalah snapshot analisis, belum invariant final. Sebelum
import dokumen, hentikan penulisan, buat salinan staging immutable, lalu kunci
manifest berdasarkan nama, ukuran, SHA-256, dan waktu modifikasi.

Importer users/positions harus tetap memakai `file_sk_strategy=defer`. Setelah
FK posisi tersedia, buat pipeline terpisah:

```text
legacy:import-user-position-documents --dry-run
legacy:import-user-position-documents --commit
```

Analyzer dokumen harus mengklasifikasikan `available_valid`, `missing`,
`invalid_pdf`, `incomplete`, `orphan_physical_file`, dan `duplicate_content`.
Hanya `available_valid` yang boleh ditulis. Missing/rusak/partial/orphan di-skip
dan dicatat pada laporan private; bukan blocker untuk import users/positions.

Mapping dokumen target minimum:

```text
user_position_id    = legacy users.id
document_type       = appointment_sk
storage_disk        = private
verification_status = draft
source_system       = legacy
external_id         = legacy-user:{legacy_id}:file-sk
version             = 1
is_primary          = true
```

Simpan MIME, extension, size, dan SHA-256 dari file aktual. Nomor SK, tanggal,
dan penerbit tetap `null` bila tidak tersedia; jangan menebak metadata dari nama
file. Dokumen yang menempel pada posisi alias tetap mempertahankan legacy ID dan
tampilan canonical harus memakai equivalent position IDs agar dokumen terlihat.

Issue produksi terkait: action Management Users saat ini masih menyimpan upload
SK baru melalui `store('sk', 'public')`. Implementasi tersebut tidak mengubah
keputusan import legacy, tetapi harus dipindahkan ke disk private dan dilayani
melalui endpoint download berotorisasi sebelum aplikasi dinyatakan siap
produksi.

## Canonical dan alias posisi

Semua 1.514 row tetap disimpan, tetapi hanya satu row untuk setiap kombinasi
konteks yang boleh menjadi posisi canonical dan selectable.

Rancangan minimum yang direkomendasikan:

```text
is_canonical
canonical_user_position_id
legacy_duplicate_reason
```

Aturannya:

- 1.143 row menjadi posisi canonical berdasarkan konteks unik;
- 371 row menjadi alias legacy dan tetap mempertahankan primary key sumber;
- bila satu konteks mempunyai beberapa row aktif, row aktif dengan `created_at`
  terbaru menjadi canonical dan row aktif lama di-soft-delete pada waktu
  pemenang dibuat;
- alias legacy tidak boleh aktif atau muncul di pemilih posisi;
- alias menunjuk posisi canonical pada konteks yang sama;
- transaksi baru selalu menyimpan ID posisi canonical;
- histori lama tetap join ke ID legacy yang tepat.

Selesai 2026-09-14: `LegacyUserPositionClassifier` menghasilkan proyeksi lengkap
untuk seluruh 1.514 legacy ID tanpa query tambahan. Classifier memakai pemetaan
akun canonical dan hasil organisasi yang sudah dihitung analyzer, kemudian
mengelompokkan konteks berdasarkan `user_id`, jabatan, instansi canonical, dan
unit kerja.

Baseline klasifikasi saat ini:

- 1.514 row terklasifikasi dengan 1.514 ID unik;
- 1.143 posisi canonical, termasuk 955 canonical aktif;
- 371 posisi alias dalam 239 kelompok konteks duplikat;
- 337 alias mempertahankan `deleted_at` asli legacy;
- 34 alias aktif ganda diberi soft-delete rekonsiliasi pada waktu
  `created_at` pemenang;
- 0 row tidak terklasifikasi dan 0 timestamp rekonsiliasi hilang;
- 0 akun tanpa posisi canonical dan 0 alias tanpa target canonical;
- checksum klasifikasi posisi adalah
  `7efe79d9528bb96ea9284ef5c078292d65ae6e0b8c1d7caaee1d7082c741873a`.

Checksum mencakup pemetaan ID posisi ke akun, konteks organisasi, status,
canonical/alias, target alias, hasil soft-delete, dan alasan rekonsiliasi.
Perubahan hasil classifier harus membuat dry-run gagal sampai snapshot baru
ditinjau dan disetujui.

Keunikan operasional harus berlaku hanya untuk posisi canonical. Implementasi
database dapat memakai generated nullable marker agar MySQL mengizinkan banyak
alias, tetapi tetap menolak lebih dari satu canonical pada konteks yang sama.

Selesai 2026-09-14: migration
`2026_09_14_030702_add_canonical_alias_columns_to_user_positions_table` sudah
diterapkan. Migration menambahkan `is_canonical`,
`canonical_user_position_id`, `legacy_duplicate_reason`, generated column
`canonical_context_marker`, self foreign key, dan unique constraint
`uq_user_positions_canonical_context`. Model `UserPosition` juga membatasi
`availableForSelection()` hanya pada row canonical.

## Resolver identitas dokumen

Menonaktifkan alias saja tidak cukup. Dokumen lama dapat menunjuk alias, sementara
user memakai posisi canonical. Selesai 2026-09-14:
`App\Services\User\PositionIdentityResolver` menjadi service terpusat untuk
mengembalikan posisi canonical beserta seluruh alias pada konteks yang sama.

```text
equivalent IDs = canonical ID + alias legacy dalam konteks yang sama
```

API resolver yang tersedia:

- `canonicalPosition()` dan `canonicalId()` untuk menormalisasi alias;
- `equivalentIds()` untuk memperoleh ID canonical dan seluruh alias;
- `contains()` dan `areEquivalent()` untuk pemeriksaan authorization;
- `whereEquivalent()` untuk menambahkan `whereIn` terparameterisasi pada query
  Eloquent atau query builder.

Relasi `UserPosition::canonicalPosition()` dan `aliases()` memakai
`withTrashed()` karena alias legacy memang non-selectable dan dapat berstatus
soft-deleted. Resolver menolak alias yang target canonical atau konteks
`user_id`, `jabatan_id`, `instansi_id`, dan `unit_kerja_id`-nya tidak konsisten.
Query histori atau ownership harus memakai equivalent IDs. Jangan menyatukan
seluruh posisi berdasarkan NIK karena posisi beda jabatan, instansi, atau unit
memiliki scope berbeda.

Contoh pemakaian:

```php
$resolver->whereEquivalent($query, 'document.uploaded_by', $activePosition);

if ($resolver->contains($activePosition, (int) $document->uploaded_by)) {
    // Posisi aktif berhak membaca histori canonical maupun aliasnya.
}
```

Selesai 2026-09-14: resolver sudah diintegrasikan ke query ownership dan gate
authorization pada controller `Data` serta controller pembayaran yang
membandingkan `document.uploaded_by` atau `document.users_to`. Perbandingan SQL
memakai equivalent IDs, sedangkan pemeriksaan record memakai `contains()`.
Acting context PPTK/BUD memakai relasi aktual `actingPptkUserPosition` dan
`actingBudUserPosition`, bukan nama relasi legacy yang tidak tersedia.

Join histori `document_process.id_user` tetap menunjuk row posisi asli agar
atribusi pelaku tidak berubah. Join tampilan penerima dokumen juga tidak boleh
mengecualikan `user_positions.deleted_at`, karena alias legacy memang
soft-deleted. Transaksi baru tetap menyimpan satu ID canonical; equivalent IDs
hanya digunakan untuk membaca dan mengotorisasi histori lama.

Migration `2026_09_14_070936_add_position_ownership_indexes_to_legacy_document_tables`
sudah diterapkan dan menambahkan index `document (uploaded_by, deleted_at)`,
`document (users_to, deleted_at)`, serta
`document_process (id_user, created_at)`.

## Rekonsiliasi organisasi

Mismatch yang sudah diketahui:

```text
unit 24: source instansi 1, target instansi 5
unit 25: source instansi 1, target instansi 6
unit 26: source instansi 1, target instansi 7
unit 27: source instansi 1, target instansi 8
unit 28: source instansi 1, target instansi 9
```

Keputusan import adalah mempertahankan ID posisi dan unit kerja, tetapi memakai
`unit_kerjas.instansi_id` canonical. Lima pasangan di atas menjadi allowlist.
Mismatch yang tidak ada dalam allowlist harus menghentikan import. Setiap
transformasi harus muncul pada laporan dry-run dan laporan rekonsiliasi final.

Selesai 2026-09-14: `LegacyOrganizationResolver` memuat referensi jabatan,
instansi, dan unit kerja satu kali per proses analyzer, memeriksa referensi yang
hilang atau nonaktif, lalu menetapkan instansi canonical dari
`unit_kerjas.instansi_id`. Baseline terbaru menghasilkan 51 koreksi yang
seluruhnya diizinkan, 0 mismatch tak dikenal, dan 0 referensi hilang.

## Urutan implementasi import

1. Selesai secara keputusan 2026-09-15: strategi password, status akun, dan
   perlakuan file SK sudah disetujui. Resolver status, analyzer dokumen
   read-only, dan penguncian `file_sk_strategy=defer` sudah selesai.
2. Selesai 2026-09-14: staging `sitangkas_legacy.users` sudah berisi 1.514 row
   dengan ID lengkap 1 sampai 1514 dan koneksi Laravel `legacy_import` sudah
   tersedia. Credential produksi tetap harus memakai user MySQL read-only dan
   preflight verification wajib dijalankan pada setiap eksekusi command.
3. Selesai 2026-09-14: forward migration canonical/alias, canonical-only unique
   constraint, self foreign key, dan index pemilih posisi sudah diterapkan.
4. Selesai 2026-09-14: migration index ownership menambahkan index
   `document_process (id_user, created_at)` dan dua index posisi pada
   `document.uploaded_by` serta `document.users_to`.
5. Sebagian selesai 2026-09-14: koneksi, expected count/range, mapping
   organisasi, direktori laporan, dan fingerprint kolom terpakai tersedia.
   Checksum file dump resmi masih perlu dikunci sebelum `--commit`.
6. Selesai 2026-09-14: DTO immutable dan source reader read-only untuk tabel
   `users` staging sudah dibuat serta diverifikasi membaca 1.514 row.
7. Selesai 2026-09-14: analyzer `--dry-run` menghasilkan laporan identitas,
   organisasi, konteks posisi, status, dan referensi master tanpa mengubah
   target.
8. Selesai 2026-09-14: organization resolver memakai master
   `unit_kerjas.instansi_id`, hanya memperbaiki lima pasangan allowlist, dan
   menghentikan kelayakan row bila ada referensi hilang atau mismatch tak
   dikenal.
9. Selesai 2026-09-14: account aggregator menghasilkan satu calon `users` per
   NIK, memakai ID row profil canonical, memilih profil utuh secara
   deterministik, dan tidak mengekspos password pada laporan.
10. Selesai 2026-09-14: position classifier menjaga seluruh explicit legacy ID,
    menghasilkan canonical/alias secara deterministik, dan mencatat perubahan
    soft-delete tanpa menulis database target.
11. Selesai 2026-09-14: resolver equivalent position IDs menormalisasi alias,
    menyediakan query helper, dan menjaga kesamaan scope authorization tanpa
    mengubah ID pada tabel dokumen.
12. Selesai 2026-09-14: validator read-only memeriksa projection invariant,
    snapshot sumber, target kosong, collision ID, cakupan referensi histori,
    dan menyediakan validasi pasca-insert untuk dipanggil sebelum commit.
13. Status historis 2026-09-14: Artisan command
    `legacy:import-users --dry-run` tersedia. Opsi commit saat itu masih
    diblokir dan dirancang hanya memakai hasil dry-run bersih dengan checksum
    sumber yang sama.
14. Status historis 2026-09-15: import action transaksional sudah mempunyai
    advisory lock, pemeriksaan ulang fingerprint dan preflight, bulk insert
    akun, insert canonical lalu alias, validasi pasca-insert, serta rollback
    penuh bila satu invariant gagal. Password policy resolver, local-only shared
    password guard, validasi policy, dan ringkasan dry-run sudah tersedia.
    Safety gate pada tahap tersebut masih disabled.
15. Selesai 2026-09-15: `LegacyUserAccountStatusResolver` memakai hasil posisi
    canonical, mengisi `status_reason`, dan menghasilkan invariant 930 active /
    123 inactive beserta checksum dan ringkasan status dry-run.
16. Selesai 2026-09-16: config status dikunci ke
    `active_if_any_active_position_else_inactive` dan keputusan status dihapus
    dari `pending_decisions`. Config file SK juga dikunci ke `defer` dan
    keputusan tersebut dihapus dari `pending_decisions`; safety gate import
    pada tahap tersebut tetap nonaktif.
17. Selesai 2026-09-15: analyzer/manifest SK read-only memvalidasi database,
    filesystem, PDF, checksum, duplicate content, dan menghasilkan laporan
    private tanpa menulis `user_position_documents`.
18. Selesai secara keputusan 2026-09-16: tidak membuat tabel/model audit batch
    maupun audit event per row karena import bersifat one-time. Bukti eksekusi
    memakai laporan JSON private dan structured log yang disanitasi.
19. Selesai 2026-09-16: `LegacyUserImportCommitReportWriter` dapat mencatat
    hasil `completed` atau `failed` di luar transaksi, tanpa exception mentah,
    password, hash, NIK lengkap, atau payload row. Writer sudah dihubungkan ke
    command commit.
20. Selesai 2026-09-16: opsi command `--dry-run` dan `--commit` sudah saling
    eksklusif, sedangkan `--fingerprint=` wajib berupa SHA-256 penuh untuk mode
    commit. Safety gate pada saat implementasi awal tetap `false`.
21. Selesai 2026-09-16: mode commit menghitung ulang analyzer, menulis
    laporan private terbaru, membandingkan fingerprint penuh dengan
    `hash_equals()`, dan mensyaratkan 0 blocker. Analyzer juga memverifikasi
    target kosong serta koneksi source read-only. Konfirmasi operator wajib
    interaktif dan harus cocok dengan frasa yang memuat prefix fingerprint;
    action hanya dipanggil setelah seluruh guard tersebut lulus.
22. Selesai 2026-09-16: mode commit memanggil `ImportLegacyUsers` dengan
    fingerprint wajib, menulis structured log mulai/selesai/gagal, serta
    menyimpan laporan `completed` atau `failed`. Exception mentah tidak masuk
    laporan/log.
23. Selesai 2026-09-16: validator pasca-import berjalan sebelum transaksi
    di-commit dan memverifikasi count akun/posisi, fingerprint SHA-256 proyeksi
    terhadap target, kecocokan hash password tanpa menuliskan hash ke laporan,
    status akun, bentuk canonical/alias, cakupan referensi histori, ID maksimum,
    serta atribut `AUTO_INCREMENT` pada kolom primary key. Nilai counter dari
    `information_schema.TABLES` tidak dijadikan blocker di dalam transaksi
    karena metadata tersebut dapat belum mencerminkan explicit-ID insert yang
    belum commit. Dengan atribut schema dan ID maksimum tervalidasi, InnoDB akan
    menggunakan ID berikutnya di atas ID import tertinggi. Satu kegagalan
    menggagalkan transaksi penuh.
24. Sedang berlangsung 2026-09-16: safety gate sudah diaktifkan operator untuk
    maintenance window. Attempt pertama di-rollback pada `post_import` akibat
    false negative metadata counter dan validator sudah diperbaiki. Tahap
    berikutnya adalah operator mengulang commit dengan fingerprint yang sama
    selama source tidak berubah, memvalidasi login/scope/histori, lalu
    menonaktifkan kembali gate dan keluar maintenance.
25. Setelah posisi tersedia, buat action/command import dokumen terpisah dengan
    staging filesystem, transaksi metadata, cleanup rollback, dan postflight
    checksum.
26. Pindahkan file valid ke storage private dan validasi download berotorisasi
    sebelum menghapus staging; file missing/rusak/orphan tetap hanya di laporan.

## Parameter operasional yang masih harus dikunci

- manifest/fingerprint final setelah folder SK dibekukan;
- lokasi staging immutable dan disk/path private tujuan dokumen;
- rentang final ID untuk posisi manual setelah ID legacy dicadangkan.

AI agent tidak boleh meminta ulang keputusan bisnis status akun atau perlakuan
file SK yang sudah disetujui. Parameter operasional di atas harus diverifikasi
sebelum importer produksi diaktifkan.
