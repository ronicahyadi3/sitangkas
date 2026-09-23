# Lifecycle Dokumen TTE, QR, Storage, dan Kompatibilitas Laporan

Tanggal keputusan awal: **17 September 2026**. Pembaruan terakhir:
**23 September 2026**.

Status: **keputusan arsitektur dan hasil analisis; 13 migration tabel canonical
sudah diterapkan pada database lokal dan dilengkapi direct document lookup/
checkpoint. Dua migration index mapping legacy tetap `Pending`. PHP enum,
model/cast/relasi, transition/persistence service,
artifact storage runtime, compatibility writer, encrypted secret store,
endpoint internal, dan asynchronous signing job sudah dibuat pada source.
Workflow provisioning dari payment, public verification route, historical
mapping/reorganisasi storage, reconciliation, serta runtime deployment belum
selesai**.

Dokumen ini adalah sumber keputusan untuk lifecycle file sebelum/sesudah TTE,
version chain PDF, QR verifikasi, histori attempt, dan kompatibilitas
`before_signs`/`after_signs` dengan aplikasi lain. Agent yang menyentuh salah
satu area tersebut wajib membaca dokumen ini setelah `README.md` dan
`PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`, lalu
`ESIGN_V2_CONTRACT_AND_BACKEND.md`.

Desain approved untuk prepared footer, beberapa QR dalam satu signer/step,
intermediate checkpoint, dan final projection berada di
`ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`. Desain itu belum
diimplementasikan dan memperluas flow satu-signature pada dokumen ini.

## 1. Tujuan dan batas

Rancangan harus sekaligus menjamin:

- setiap byte PDF sebelum dan sesudah TTE dapat ditelusuri;
- setiap penandatangan menghasilkan versi immutable baru;
- QR di dalam setiap versi selalu menunjuk versi yang benar;
- keberhasilan, kegagalan, timeout ambigu, dan retry tercatat terpisah;
- file invalid/zero-byte historis tidak dihapus, dipindah, atau ditimpa;
- aplikasi lain tetap dapat membaca `before_signs` dan `after_signs` melalui
  compatibility contract append-only;
- file berada di private storage dan path fisik tidak menjadi public contract;
- laporan baru dapat menampilkan input, output, status, response aman, dan
  seluruh histori attempt.

Dokumen ini tidak memberikan izin untuk menjalankan migration, memindahkan
file, menghapus file invalid, memanggil sign production, atau mengubah consumer
aplikasi lain.

## 2. Keputusan pengguna yang sudah dikunci

1. Integrasi baru memakai eSign Client `2.2.0`/API v2 dengan concrete client
   bernama `BsreClient`.
2. Vertical slice pertama hanya NIK + passphrase dan invisible signing. Metode
   TOTP/email tetap backlog.
3. Frontend TTE dan validasi kelak tetap berbentuk modal, memakai Svelte island
   melalui Vite, serta mengikuti Bootstrap 5 dan custom Argon Dashboard Pro 2.
4. Implementasi wajib backend-first.
5. File historis invalid adalah bukti aktivitas sensitif dan **tidak boleh
   dihapus**.
6. Penyimpanan canonical baru tidak perlu dipisah berdasarkan tipe dokumen.
7. URL QR baru tidak lagi memakai `/File_{TYPE}/sign/{uuid}.pdf` sebagai format
   canonical.
8. Seluruh data `before_signs` dan `after_signs` dipetakan ke schema canonical
   tanpa mengubah atau menghapus row sumber.
9. `before_signs` dan `after_signs` tetap dipertahankan sebagai compatibility
   ledger append-only. Keduanya bukan state machine utama, tetapi tetap ditulis
   agar aplikasi/laporan lama tidak terputus.
10. Penghentian write, read-only archive, atau drop kedua tabel tidak termasuk
    scope saat ini dan membutuhkan gate lengkap serta keputusan pengguna baru.
    Reporting API/view canonical tetap menjadi jalur yang direkomendasikan bagi
    consumer baru.
11. Struktur baru harus tetap memperlihatkan file sebelum TTE, file hasil TTE,
   keberhasilan/kegagalan, dan setiap percobaan ulang.
12. Seluruh file lama pada folder berdasarkan tipe dokumen akan dimapping dan
    disalin ke layout canonical berdasarkan lifecycle, tahun, bulan, dan prefix
    UUID.
13. Tahun folder ditentukan **per artifact**, bukan dari tipe dokumen atau
    aktivitas terakhir dokumen. `document_process` action `UPLOAD`/`TTE` adalah
    sumber utama, dengan fallback terkontrol.
14. Folder legacy `File_{TYPE}` ditargetkan untuk dihapus setelah copy, hash/PDF
    verification, path activation, backup, dan rollback window lulus. Folder
    lama tidak dipertahankan sebagai desain akhir.
15. QR baru memakai canonical URL
    `https://sitangkas.malangkota.go.id/verify/{public_id}`. Nilai `public_id`
    adalah UUID/random opaque identifier immutable dan satu nilai selalu
    menunjuk satu exact artifact version.
16. URL QR legacy berdasarkan tipe dan UUID akan di-resolve melalui mapping lalu
    redirect ke canonical URL exact artifact. Gunakan `302` selama migrasi dan
    baru gunakan `301` setelah parity mapping stabil.
17. Halaman verify publik hanya menampilkan status validasi minimum, nomor
    dokumen bila ada, nama signer, dan tanggal signature. NIK, path, nominal,
    response vendor, serta metadata internal tidak ditampilkan.
18. Guest hanya dapat menerima PDF jika public-access policy exact artifact
    mengizinkan, dan byte yang dikirim selalu memakai public watermark. Guest
    tidak pernah menerima original/private path; dokumen nonpublik tetap
    menampilkan ajakan login. User login wajib lolos policy dokumen lalu delivery
    mode ditentukan oleh satu flag `user_positions.pdf_watermark_required`.
19. Halaman verify publik memakai Blade + Bootstrap 5/custom Argon Dashboard
    Pro 2. Svelte tetap untuk modal TTE/validasi interaktif, bukan halaman
    publik sederhana ini.
20. Seluruh TTE runtime dijalankan asynchronous melalui dedicated queue. HTTP
    BSrE tetap synchronous di worker; browser menerima `202` dan memantau status
    attempt. Putusnya koneksi user setelah commit tidak menghentikan proses.
21. Passphrase hanya boleh dipersist sementara pada secret store/cache private
    terenkripsi ber-TTL; tidak boleh masuk row attempt/event, compatibility
    table, log, session, `failed_jobs`, atau serialized job payload.
22. Flag posisi `pdf_watermark_required` berlaku seragam untuk
    preview/view/download. `true` selalu watermark; `false` boleh exact original
    canonical setelah authorization. Admin Super acting selalu efektif `false`,
    sedangkan posisi bisnis nyata miliknya mengikuti flag posisi tersebut.
23. Watermark derivative tidak menjadi artifact version, tidak masuk version
    chain, dan tidak pernah menjadi source TTE atau verifikasi BSrE. Detail
    COPY-ID, audit, cache 12 jam, dan cleanup berada di
    `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`.
24. Editor tidak mengunggah file. Backend mengirim exact authorized PDF sebagai
    binary stream; Base64 hanya digunakan saat server berkomunikasi dengan
    BSrE.
25. Footer dibuat satu kali pada semua halaman sebelum TTE pertama dan dapat
    diedit text/font/size/style/posisinya. Artifact yang sudah signed tidak
    mendapat footer baru atau perubahan footer.
26. Beberapa QR untuk signer/step yang sama merupakan satu attempt dengan N
    operasi serial. Intermediate result immutable menjadi input operasi
    berikutnya; hanya hasil terakhir dapat menjadi `after_sign`/current.
27. Satu attempt multi-QR tetap menghasilkan satu row compatibility
    `before_signs`, maksimal satu terminal `after_signs`, dan satu action `TTE`
    pada `document_process` hanya saat sukses. Detail per QR berada di canonical
    schema.

## 3. Status pekerjaan terkait

- Phase 0 containment lokal selesai; rotasi/revoke credential yang pernah
  terekspos tetap menjadi tindakan eksternal pemilik.
- Phase 1 minimum telah membuktikan status user, Basic Auth gagal, request
  TOTP, certificate chain, verify unsigned/signed/invalid, dan invisible sign
  NIK+passphrase satu PDF pada endpoint yang diotorisasi pemilik.
- Fondasi Phase 2 sudah memiliki `EsignGateway`, `BsreClient`, DTO, payload
  builder, response mapper, error taxonomy, timeout, dan safe telemetry.
- DDL attempt/artifact/workflow/event/migration-control sudah diterapkan;
  `esign_attempts.document_id`, indeks laporan, serta
  `esign_migration_items.current_stage` sudah tersedia pada schema aktif.
- PHP enum domain, model/cast/relasi, transition service, artifact persistence,
  compatibility writer, encrypted signing session/secret store, internal
  endpoint, dan asynchronous signing job sudah dibuat pada source.
- Dua migration index mapping legacy masih `Pending`. Provisioning artifact/
  workflow/step dari controller payment sudah dibuat dan dibuktikan lokal pada
  satu upload NPD `GU_SKPD`. Lazy activation, submit gate, serta assignment
  signer saat handoff sudah dibuat untuk LS SPP; production process manager/
  shared cache, acceptance end-to-end, public verification route,
  reconciliation, mapping runner, dan frontend belum selesai.
- Credential, NIK lengkap, passphrase, Basic Auth, dan response mentah tidak
  boleh ditulis ke dokumentasi ini atau dokumentasi lanjutan.

## 4. Bukti schema dan volume legacy

Snapshot read-only 17 September 2026:

- `document`: 482.568 row; nilai `status` legacy masih dapat berupa daftar
  dipisahkan koma dan `signed_at` belum menjadi bukti status yang dapat
  diandalkan;
- `before_signs`: 590.818 row dan hanya mempunyai primary key sebagai indeks;
- `after_signs`: 588.882 row dan hanya mempunyai primary key sebagai indeks;
- `document_process`: sekitar 2,29 juta row dan mempunyai indeks
  `id_dokumen`;
- tabel legacy tidak memiliki foreign key yang cukup untuk menjelaskan satu
  attempt secara eksplisit;
- maksimum histori attempt yang teramati untuk satu dokumen adalah 142;
- terdapat hasil zero-byte dan kelompok success identik yang harus tetap
  dianggap histori sampai direkonsiliasi.

Semantik legacy yang harus dipertahankan untuk consumer:

- `before_signs` menyimpan metadata input sebelum panggilan TTE;
- `after_signs` menyimpan metadata/response/status sesudah panggilan TTE;
- aplikasi eksternal memakai kedua tabel untuk laporan proses dan file;
- penambahan schema baru tidak boleh diam-diam mengubah arti kolom/status lama.

Compatibility writer runtime sudah dibuat, tetapi sebelum diaktifkan pada
produksi tetap inventarisasi dan validasi query nyata semua consumer:
kolom yang dipilih, join, filter status, interpretasi sukses/gagal, rentang
waktu, pengurutan, dan kebutuhan path file. Asumsi tanpa inspeksi consumer
tidak cukup untuk menyatakan backward compatible.

## 5. Bukti storage yang sudah dimigrasikan

File 90 hari terakhir telah ditempatkan pada:

```text
storage/app/private/documents
```

Snapshot analisis:

- 74.045 source file, sekitar 32,40 GiB;
- 74.187 signed file, sekitar 53,52 GiB;
- total 148.232 file, sekitar 85,92 GiB;
- 109 signed file berukuran zero-byte;
- enam zero-byte tercatat sebagai current filename dokumen aktif.

Rekonsiliasi rentang 90 hari yang dianalisis:

- 70.570 row `before_signs`;
- 67.990 row success `after_signs`;
- 67.981 event TTE di `document_process` untuk 47.038 dokumen;
- 67.469 signed file cocok tepat dengan histori TTE;
- 244 pasangan histori TTE tidak mempunyai file yang cocok;
- 6.718 signed file tidak mempunyai pasangan histori TTE exact pada rentang
  recent/180 hari yang diperiksa.

Rekonsiliasi source pada rentang analisis:

- 67.718 nama source unik dari `before_signs`;
- 74.045 source file fisik;
- 47.476 pasangan cocok;
- 20.242 metadata before tidak menemukan source pada subset migrasi;
- 26.569 source tidak mempunyai `before_signs` recent yang cocok;
- sebagian besar source tambahan berasal dari tipe non-TTE seperti Billing,
  BMD, SPJ, dan SPJ fungsional.

Angka tersebut adalah bukti perlunya registrasi/reconciliation, bukan izin
untuk menghapus orphan atau invalid file.

### Aturan cut-off 90 hari

Pemindahan berdasarkan tanggal tidak boleh memutus version chain. Jika final
artifact masuk rentang 90 hari, seluruh ancestor yang diperlukan untuk
menjelaskan chain—source dan intermediate signed PDF—harus ikut tersedia atau
diregistrasikan. Prinsip ini disebut **transitive closure** version chain.

Subset di project baru saat ini hanya sekitar 90 hari. Database memang memiliki
metadata 2024-2026, tetapi metadata tidak dapat menggantikan PDF fisik yang
belum disalin. Sebelum folder lama dihapus, seluruh file 2024-2025 dan ancestor
chain yang masih berada pada project/storage lama harus diinventarisasi,
disalin, dan diverifikasi. `availability_status=missing` berarti metadata sudah
diregistrasikan, bukan bukti file aman untuk dihapus dari sumber.

### Bukti tahun dari `document_process`

Query read-only 17 September 2026 menunjukkan rentang
`document_process.created_at` dari 24 Oktober 2024 sampai 9 September 2026:

| Tahun | `UPLOAD` | `TTE` |
|---:|---:|---:|
| 2024 | 67.926 | 78.872 |
| 2025 | 276.538 | 317.934 |
| 2026 | 137.780 | 157.856 |

Temuan korelasi:

- tidak ditemukan `id_dokumen` dengan history melintasi dua calendar year pada
  snapshot aktif, tetapi desain tidak boleh mengandalkan kebetulan ini;
- tidak ditemukan perbedaan tahun antara `document.created_at` dengan row
  `before_signs`/`after_signs` yang berelasi pada snapshot;
- 543.637 success `after_signs` cocok dengan event `TTE` berdasarkan
  `id_dokumen` dan timestamp yang sama, seluruhnya pada tahun yang sama;
- `document_process.src_name` pada action `TTE` menyimpan filename output;
- `after_signs.src_name` menyimpan filename input, sedangkan MD5 success dapat
  merepresentasikan output. Karena itu `after_signs.src_name` tidak boleh
  dipakai sendirian untuk menentukan path hasil sign.

Tahun physical partition menggunakan waktu artifact dibuat. Business/fiscal
year tetap metadata terpisah dan tidak boleh disimpulkan dari folder.

## 6. Bukti version chain dan QR pada sample TBP

Sample yang dianalisis:

```text
File_TBP/signs/0ad10727-53af-4e11-9445-13c4cadfd3f0.pdf
```

PDF final mempunyai dua halaman, dua QR embedded, dua `ByteRange`, dan dua
signature `ETSI.CAdES.detached`. Chain fisik dan database yang ditemukan:

1. source unsigned: `80dbe214-a027-4bee-8c62-c836a60e16a4.pdf`, 87.566 byte,
   tanpa QR/signature, MD5 cocok dengan `before_signs`;
2. output penandatangan pertama:
   `e519fd0b-ce72-4a70-b84e-2e3a994a457b.pdf`, 701.875 byte, satu QR dan satu
   signature, MD5 cocok dengan `after_signs` serta menjadi input before
   berikutnya;
3. output penandatangan kedua/final:
   `0ad10727-53af-4e11-9445-13c4cadfd3f0.pdf`, 1.237.375 byte, dua QR dan dua
   signature, MD5 cocok dengan `after_signs`.

Implikasi:

- satu dokumen bisnis dapat mempunyai banyak artifact fisik;
- output attempt N menjadi input attempt N+1;
- setiap artifact signed harus immutable;
- setiap QR harus dapat menyelesaikan exact artifact version yang ditunjuk,
  bukan selalu current/latest artifact;
- `src_name` tunggal pada `document` tidak cukup untuk menyimpan seluruh chain.

## 7. Perilaku URL legacy yang harus dipertahankan

Project lama membuat QR di browser dan memakai URL hardcoded. Perilaku
production yang telah diamati:

```text
/File_TBP/sign/{uuid}.pdf
```

memberikan halaman HTML verifikasi, sedangkan:

```text
/File_TBP/signs/{uuid}.pdf
```

memberikan file PDF fisik. Perbedaan singular `sign` dan plural `signs` adalah
kontrak nyata, bukan typo yang boleh disatukan sembarangan.

QR lama sudah menjadi bagian byte PDF signed. Mengubah QR atau PDF lama akan
merusak integritas tanda tangan. Karena itu:

- URL legacy harus tetap dapat diselesaikan;
- domain lama harus dipertahankan atau diarahkan secara stabil;
- buat legacy alias/resolver dari tipe + UUID menuju artifact yang tepat;
- route legacy melakukan redirect `302` menuju canonical URL setelah exact
  mapping ditemukan; ubah menjadi `301` hanya setelah mapping stabil;
- redirect tidak boleh menuju latest artifact jika QR menunjuk versi lama;
- file legacy boleh dimigrasikan ke storage canonical hanya melalui proses
  copy-verify-activate; URL tetap diselesaikan melalui metadata, bukan path.

## 8. URL verifikasi canonical baru

Format canonical yang dikunci:

```text
https://sitangkas.malangkota.go.id/verify/{public_id}
```

`public_id` adalah UUID/random opaque identifier immutable yang dibuat backend
sebelum QR dibubuhkan. Jangan memakai path, tipe dokumen, primary key berurutan,
atau nilai `src_name` mutable sebagai public contract.

Format `/verify/{public_id}.pdf` secara teknis dapat didukung, tetapi bukan
pilihan utama karena endpoint memberikan halaman HTML verifikasi dan ekstensi
`.pdf` menyesatkan browser/cache/scanner. Jika kompatibilitas membutuhkan
suffix `.pdf`, perlakukan sebagai alias, bukan lokasi file.

Kontrak konseptual:

```text
GET /verify/{public_id}          halaman verifikasi exact artifact (public)
GET /verify/{public_id}/download delivery PDF (public/auth policy + rendition resolver)
```

Aturan:

- public URL tidak sama dengan storage path;
- `public_id` dapat sama dengan artifact UUID untuk sederhana, atau memakai
  token random terpisah bila policy memerlukannya;
- backend yang mereservasi ID, membuat URL melalui named route/config, dan
  menghasilkan QR;
- JavaScript tidak boleh menentukan UUID/path canonical;
- QR permanen tidak memakai temporary signed URL yang kedaluwarsa;
- halaman verifikasi tidak mengekspos private URL/path file kepada guest;
- exact artifact ditampilkan lebih dahulu; halaman boleh memberi tautan bahwa
  versi lebih baru tersedia;
- token UUID adalah bearer locator, bukan authorization;
- guest melihat action PDF hanya jika public-access policy lulus; action itu
  menghasilkan public-watermarked derivative. Jika tidak, guest melihat login
  action dan setelah login kembali ke intended verify URL;
- user authenticated baru melihat action download bila policy dokumen lulus;
  rendition kemudian ditentukan dari flag posisi/acting context;
- delivery di-stream dari private storage, tidak membocorkan path, dan dicatat
  dalam audit append-only.

### Isi halaman verify publik

Konten yang diizinkan:

- status `Valid`, `Invalid`, `Belum dapat diverifikasi`, atau
  `File tidak tersedia`;
- nomor dokumen dari `document.nomor` bila tersedia; baris dapat dihilangkan
  jika nilainya kosong;
- daftar nama signer yang berasal dari certificate/verified PDF signature;
- tanggal signature yang berasal dari PDF/provider dan dinormalisasi ke
  `Asia/Jakarta`;
- action public-watermarked PDF bagi guest yang diizinkan, action login untuk
  dokumen nonpublik, atau download bagi authenticated authorized user.

Jangan tampilkan NIK, email, private path, filename internal, nominal, unit
kerja, actor internal, passphrase, raw certificate, raw provider response,
exception, atau hash internal. `after_signs.created_at` hanya fallback tanggal
historis bila timestamp signature tidak tersedia dan provenance fallback harus
tetap tercatat secara internal.

Setiap artifact menampilkan signature yang benar-benar terdapat pada versi
tersebut. QR versi pertama tidak boleh menampilkan signer yang baru ada pada
versi berikutnya.

### Kontrak redirect legacy

Mapping eksplisit direkomendasikan melalui
`document_artifact_legacy_urls`:

```text
id
document_artifact_id
legacy_type
legacy_uuid
legacy_path
created_at
```

Gunakan unique constraint `(legacy_type, legacy_uuid)` dan indeks
`legacy_uuid`. Resolver tidak boleh membentuk private path langsung dari route
parameter, melakukan recursive filesystem scan, atau menjadi open redirect.
Mapping yang tidak ditemukan menghasilkan response 404 generik tanpa path.

## 9. Layout storage canonical baru

Keputusan: satu logical root boleh dipakai dan tipe dokumen diletakkan sebagai
metadata database, tetapi semua file tidak boleh ditempatkan dalam satu flat
directory. UUID collision bukan masalah utama; directory listing, backup,
restore, antivirus, maintenance, dan pertumbuhan file adalah risikonya.

Layout yang direkomendasikan:

```text
storage/app/private/documents/
├── source/2026/09/80/{artifact_uuid}.pdf
├── signed/2026/09/0a/{artifact_uuid}.pdf
├── failed-output/2026/09/ab/{artifact_uuid}.pdf
└── staging/{temporary_uuid}.pdf
```

Dua karakter awal UUID menjadi shard wajib pada layout canonical v1 agar
operasi directory, backup, antivirus, dan restore tetap stabil saat volume
bertambah.

Aturan storage:

- type dokumen berada di database, bukan menjadi keharusan folder;
- setiap versi memakai filename UUID baru dan tidak pernah overwrite;
- original, staging, signed, dan failed output tetap private;
- file lama pertama-tama diregistrasikan di path existing, kemudian disalin
  bertahap ke path canonical melalui copy-verify-activate;
- setelah seluruh gate migrasi lulus, folder legacy berdasarkan tipe dokumen
  dihapus dan tidak menjadi layout aktif;
- `disk` + `path` adalah implementation detail, bukan URL;
- file invalid/zero-byte ditandai secara logis dan dipertahankan;
- cleanup hanya boleh menangani staging yang terbukti aman sesuai retention,
  bukan bukti audit historis;
- SHA-256 dipakai untuk artifact baru; MD5 legacy hanya disimpan untuk
  reconciliation, bukan jaminan integritas baru.

## 10. Model data target

Tiga konsep tidak boleh digabungkan:

1. artifact: byte/file dan version chain;
2. attempt: satu percobaan proses TTE;
3. event: histori append-only dari attempt.

### `document_artifacts`

Katalog seluruh versi file, termasuk file source, signed, dan failed output.
Field konseptual:

```text
id
uuid
public_id
document_id
parent_artifact_id
produced_by_attempt_id
artifact_type                 source|signed|failed_output
version
signature_sequence
document_type
disk
path
original_name
mime_type
size_bytes
sha256
legacy_md5
availability_status
verification_status
is_current
preservation_required
legacy_path
source_system
artifact_created_at
storage_year
storage_month
migration_batch_id
migration_status
migrated_at
created_at
updated_at
```

Index/constraint minimum harus mempertimbangkan:

- unique `uuid` dan `public_id`;
- unique `disk,path`;
- lookup `document_id,version`;
- lookup `document_id,is_current`;
- `parent_artifact_id` dan `produced_by_attempt_id`;
- satu current artifact per dokumen harus dijaga secara transaksional sesuai
  kemampuan database/schema final.

### `document_artifact_signatures`

Read model terstruktur untuk signature yang benar-benar terdapat pada satu
artifact. Tabel ini diturunkan dari hasil verifikasi PDF, bukan menggantikan
`document_artifacts` atau `esign_attempts`.

```text
id
document_artifact_id
sequence
signer_name
signed_at
signed_at_source
signature_format
integrity_valid
certificate_trusted
verification_status
verified_at
created_at
updated_at
```

Gunakan unique/index `(document_artifact_id, sequence)`. Jangan simpan NIK
lengkap, raw certificate, atau raw response BSrE. Halaman verify membaca tabel
ini agar tidak memanggil BSrE pada setiap QR scan. Artifact baru mengisinya saat
final verification; artifact legacy diisi melalui backfill/on-demand verify
terkontrol dengan cache berdasarkan immutable artifact/SHA-256.

### `esign_attempts`

Satu row adalah satu kali percobaan TTE. Retry manual selalu membuat row baru.
Field konseptual:

```text
id / uuid
correlation_id
document_id signed INT nullable untuk legacy orphan; wajib dan immutable untuk runtime baru
input_artifact_id
output_artifact_id
actor_user_id
actor_position_id
effective_context snapshot/reference
signer_reference aman
signature_sequence
auth_mode
display_mode
request_fingerprint
state
provider_http_status
provider_safe_code
provider_safe_message
failure_category
retryable
started_at
provider_responded_at
completed_at
failed_at
created_at
updated_at
```

State minimum:

```text
prepared -> signing -> validating -> succeeded|failed|unknown
```

`unknown` wajib dipakai ketika koneksi putus/timeout setelah request mungkin
sudah diterima provider. Jangan auto-retry sign dari state ini.

Indeks runtime/report yang sudah dikunci adalah
`ix_esign_attempts_document_time (document_id, created_at)`. `document_id`
disalin dari workflow oleh persistence service dan tidak boleh diterima sebagai
nilai authoritative dari frontend.

### `esign_attempt_events`

Append-only chronology untuk setiap transition dan return yang aman disimpan:

```text
id
esign_attempt_id
sequence
event_type
from_state
to_state
http_status
provider_code
safe_message
latency_ms
sanitized_metadata JSON nullable
created_at
```

Index minimum:

- unique `esign_attempt_id,sequence`;
- `event_type,created_at` untuk operasi/audit;
- jangan menyimpan passphrase, Basic Auth, NIK lengkap, PDF/base64, request
  mentah, response mentah, atau exception transport mentah.

## 11. Definisi input, success, failure, dan invalid output

Dokumen sebelum TTE selalu tersedia melalui
`esign_attempts.input_artifact_id`, termasuk ketika provider menolak request
dan tidak ada output.

Attempt hanya `succeeded` setelah seluruh kondisi berikut terpenuhi:

1. provider mengembalikan respons sukses yang dapat dipetakan;
2. output PDF tersedia dan tidak kosong;
3. output berhasil ditulis ke private staging;
4. PDF dapat diparse dan struktur dasarnya valid;
5. verifikasi signature memenuhi policy;
6. output `document_artifacts` tersimpan;
7. current pointer dan audit/compatibility write selesai secara konsisten.

Kondisi lain:

- penolakan deterministik provider: `failed`;
- output kosong/malformed/tidak lolos verify: attempt `failed`, file tetap dapat
  diregistrasikan sebagai `failed_output` dengan
  `preservation_required=true`;
- timeout ambigu setelah request terkirim: `unknown`;
- kegagalan sebelum request provider: `failed` dan input artifact tetap ada;
- `HTTP 200` saja bukan bukti TTE berhasil.

## 12. Kompatibilitas `before_signs` dan `after_signs`

### Keputusan operasional

Struktur baru menjadi source of truth internal, tetapi legacy contract tetap
ditulis melalui compatibility adapter. `before_signs` dan `after_signs` tetap
append-only agar aplikasi lain dan laporan lama terus berjalan. Jangan
mengganti, menghentikan write, rename, atau menghapus tabel legacy tanpa gate
lengkap dan keputusan pengguna baru. Kontrak seluruh tabel operasional berada
di `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`.

Aplikasi baru tetap diarahkan ke reporting API, view, atau projection yang
bersumber dari schema canonical. Pemindahan consumer mengurangi technical debt,
tetapi tidak otomatis memberi izin drop tabel.

Alur dual-write konseptual:

```text
Sebelum provider call
├── register/resolve input document_artifact
├── create esign_attempt
├── append attempt event
└── write before_signs compatible row

Sesudah provider response/failure
├── append attempt event
├── update attempt state/summary
├── register output artifact jika ada
└── write after_signs compatible row
```

External HTTP call tidak boleh berada di dalam transaksi database panjang.
Gunakan transaksi pendek untuk persist pre-call, lepas transaksi saat memanggil
provider, lalu transaksi pendek untuk hasil/finalisasi. Atomic lock dan request
fingerprint mencegah double sign.

### Link legacy ke schema baru

Pilihan paling aman adalah tabel mapping tanpa mengubah kontrak fisik tabel
legacy:

```text
esign_attempt_legacy_links
- esign_attempt_id
- before_sign_id nullable
- after_sign_id nullable
- input_artifact_id nullable
- output_artifact_id nullable
- match_confidence nullable
- created_at
```

Alternatif berupa nullable `esign_attempt_id`/artifact ID pada tabel legacy
hanya boleh dipakai setelah dampaknya ke aplikasi lain diperiksa. Jangan
menambahkan kolom JSON histori besar ke `before_signs`/`after_signs` dan jangan
menyimpan banyak return dalam satu row. Satu retry adalah attempt baru; satu
transition/return adalah event baru.

### Laporan kompatibel

Struktur baru dapat memperlihatkan:

- document ID dan tipe;
- attempt UUID dan nomor retry;
- input filename/path/hash/size;
- output filename/path/hash/size bila ada;
- state berhasil/gagal/unknown;
- provider safe code/message dan HTTP status;
- verification status;
- started/completed time dan latency;
- version/signature sequence;
- seluruh event historis.

Untuk consumer baru, sediakan API read-only atau reporting projection/view
seperti `v_esign_attempt_reports`. Direct cross-application table access adalah
technical debt; jangan memutus akses lama sebelum pengganti tervalidasi.

Tahap cutover consumer dan evaluasi lifecycle:

1. inventaris query dan semantics consumer lama;
2. implementasikan schema baru + compatibility writer;
3. jalankan dual-write tanpa mengubah consumer;
4. bandingkan jumlah attempt, success, failure, file input/output, dan status
   per periode;
5. sediakan reporting API/view versioned;
6. migrasikan consumer satu per satu dengan observability dan fallback;
7. tetap jalankan compatibility write append-only sambil mengukur parity;
8. buat backup final dan simpan export/checksum serta mapping report;
9. bila kelak diusulkan freeze/read-only/archive, lakukan sebagai phase terpisah
   setelah observation, restore drill, retention review, dan persetujuan semua
   consumer;
10. drop bukan target aktif. Drop hanya boleh dipertimbangkan melalui keputusan
    pengguna baru setelah seluruh gate pada dokumen kontrak tabel legacy lulus.

## 13. Flow backend target dengan QR

```text
Authorize document dan signer
    ↓
Resolve immutable input artifact
    ↓
Create attempt + reserve output artifact UUID/public_id
    ↓
Create /verify/{public_id} QR di backend
    ↓
Embed QR ke staging PDF
    ↓
Record prepared input hash dan compatible before_signs
    ↓
Call BsreClient satu kali tanpa transaksi DB terbuka
    ↓
Record sanitized provider event
    ↓
Persist output/failed-output sebagai artifact baru
    ↓
Verify PDF hasil
    ↓
Finalize current artifact + compatible after_signs
    ↓
Activate public verification record
```

Output attempt N menjadi input attempt N+1. QR pada output N tetap menunjuk
output N walaupun dokumen telah memiliki output N+1.

### Extension approved: beberapa QR dalam satu attempt

Flow di atas tetap menjelaskan satu signature per attempt pada desain awal.
Untuk signer/step yang membutuhkan beberapa QR, flow berikut menggantikannya:

```text
Authorize dan resolve exact source
    -> validate placements/footer
    -> render serta hash exact prepared before_sign
    -> create one attempt + N operations + reserve N public_id
    -> write one before_signs projection
    -> sign QR1 -> immutable intermediate 1
    -> sign QR2 memakai intermediate 1 -> intermediate 2
    -> ... -> sign QRN
    -> verify final PDF dan cocokkan seluruh signature
    -> promote one final after_sign/current artifact
    -> write one after_signs + one document_process TTE projection
    -> activate all public_id atomically
```

Setiap HTTP sign tetap dilakukan tanpa transaksi database terbuka. Output
operasi sebelumnya selalu menjadi input operasi berikutnya. Intermediate tidak
menjadi current. Dalam satu attempt, setiap QR resolve final exact artifact dan
signature spesifiknya. Dalam workflow multi-signer, output attempt signer
sebelumnya tetap menjadi source attempt signer berikutnya. Detail checkpoint,
partial resume, dan state berada di
`ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`.

## 14. Strategi registrasi/backfill legacy

Seluruh implementasi pada bagian ini wajib mengikuti
`ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`. Runbook tersebut mengunci checkpoint,
high-watermark, state machine, lease, idempotency, crash-safe copy,
pause/resume, throttling, catch-up, observability, recovery, dan pemisahan
decommission.

Backfill tidak boleh berupa satu migration DDL+DML besar. Gunakan command/action
terkontrol dengan checkpoint, chunk by ID/path, idempotency, dry-run, laporan
tersanitasi, dan tanpa mengubah file sumber.

Urutan:

1. buat schema dan indeks baru melalui migration terpisah;
2. registrasikan file di lokasi existing tanpa mengubah byte/path lebih dahulu;
3. match berdasarkan document ID, src name, MD5 legacy, size, timestamp, serta
   urutan `before_signs`/`after_signs`/`document_process`;
4. tandai confidence dan exception, jangan menebak relasi ambigu;
5. bangun parent chain hanya untuk match deterministik;
6. registrasikan invalid/zero-byte sebagai preservable evidence;
7. rekonsiliasi hitungan dan hash;
8. aktifkan dual-write untuk data baru;
9. copy artifact yang match deterministik ke layout canonical;
10. verifikasi size, SHA-256, PDF readability, dan signature/`ByteRange` tanpa
    mengubah byte;
11. aktifkan path canonical secara transaksional dan uji route lama/baru;
12. jangan menjadikan backfill sempurna sebagai syarat menyimpan attempt baru.

Database besar memerlukan indeks berdasarkan query nyata. Kandidat legacy:

- `before_signs (id_data, deleted_at, created_at)`;
- `after_signs (id_data, deleted_at, created_at)`;
- `after_signs (status, created_at)` bila laporan benar-benar memakainya.

Pembuatan indeks harus dinilai terhadap versi MySQL/MariaDB, lock, waktu
deployment, disk sementara, dan query production.

### Aturan penentuan tahun per artifact

Source PDF menggunakan prioritas:

1. `document_process.created_at` dari action `UPLOAD` dengan `src_name` sama;
2. `document.created_at`;
3. `before_signs.created_at` paling awal untuk filename/input tersebut;
4. filesystem timestamp hanya sebagai fallback terakhir.

Signed PDF menggunakan prioritas:

1. `document_process.created_at` dari action `TTE` dengan output `src_name`
   sama;
2. `after_signs.created_at` dari attempt success yang terpasang;
3. filesystem timestamp hanya sebagai fallback terakhir.

Failed output menggunakan waktu attempt/response yang terpasang. Jika tidak ada
tanggal deterministik, artifact masuk exception `ambiguous`; agent tidak boleh
menebak tahun. Source Desember yang ditandatangani Januari boleh berada pada
`source/YYYY/12` dan `signed/YYYY+1/01`; hubungan dijaga oleh
`parent_artifact_id`.

### Migrasi fisik dan penghapusan folder `File_{TYPE}`

Target final adalah tidak ada folder penyimpanan berdasarkan tipe dokumen.
Migrasi wajib memakai pola:

```text
inventory -> map -> copy -> verify -> activate -> observe -> delete legacy
```

Jangan memakai direct move. Setiap batch harus mempunyai checkpoint,
idempotency, `migration_batch_id`, manifest source/destination, size, SHA-256,
dan hasil verifikasi. Sebelum path diaktifkan:

- source dan destination tersedia dan mempunyai size/SHA-256 sama;
- PDF non-zero dapat dibaca;
- signature count dan `ByteRange` tidak berubah;
- parent/version chain telah diregistrasikan;
- route `/verify/{public_id}` dan resolver URL legacy dapat membuka exact
  artifact;
- duplicate filename lintas folder telah diperiksa berdasarkan hash, bukan
  diasumsikan sama hanya karena UUID.

Status rekonsiliasi minimum:

```text
matched
metadata_only
file_missing
ambiguous
duplicate_filename
hash_mismatch
invalid
```

Artifact unresolved dapat ditaruh sementara pada area private
`documents/unresolved/{filesystem_year}/`, tetapi tidak boleh menjadi current
atau dihapus sampai direkonsiliasi. File invalid/zero-byte tetap dipertahankan
sebagai evidence pada `failed-output` atau status preservable yang ekuivalen.

Folder legacy baru boleh dihapus setelah seluruh artifact di dalamnya berstatus
verified/activated atau exception preservable sudah mempunyai salinan
canonical; full backup, restore drill, resolver URL lama, parity manifest, dan
rollback window harus lulus. Penghapusan dilakukan oleh command/operator
terkontrol per explicit path, bukan recursive glob luas.

## 15. Security dan privacy public verification

- Halaman verifikasi publik menampilkan metadata minimum yang memang disetujui.
- Jangan mengekspos NIK lengkap, private path, response vendor, actor internal,
  atau exception.
- UUID yang sulit ditebak mengurangi enumeration tetapi tidak menggantikan
  authorization.
- PDF guest hanya boleh tersedia setelah public-access policy lulus dan selalu
  public-watermarked. Guest tidak pernah menerima original/private path; status
  login saja juga tidak memberi akses lintas unit/role/tahun.
- Link/action delivery dirender server-side setelah authorization. Posisi nyata
  `true` selalu watermark, posisi nyata `false` boleh original, Admin Super
  acting efektif `false`, dan semua delivery tercatat.
- Gunakan read-only DB account/scoped API credential untuk aplikasi laporan.
- Jangan menyimpan passphrase pada attempt/event/legacy table.
- Generate QR dan public URL di backend; jangan menerima URL/path dari browser.
- Legacy URL resolver harus menolak traversal dan hanya memakai lookup metadata.
- Terapkan rate limiter, generic not-found response, output escaping, serta
  `noindex, nofollow` pada halaman publik.
- Catat setiap preview/view/download terotorisasi dalam audit append-only tanpa
  memasukkan isi PDF atau secret; simpan delivery mode dan COPY-ID bila ada.

## 16. Performa dan operasional

- Jangan meletakkan 148 ribu+ file dalam satu flat directory.
- Gunakan exact indexed lookup `public_id`, bukan scan filesystem.
- Stream file dari storage; jangan memuat PDF besar penuh ke memory untuk
  download.
- Watermark dibuat server-side sebagai derivative vector-preserving dan disimpan
  di cache private fixed 12 jam menggunakan lock, temp file, serta atomic
  publish. Cache key wajib mengikat artifact/hash, principal/context, policy,
  dan template; error harus fail-closed tanpa fallback original.
- Select kolom laporan yang dibutuhkan; hindari mengambil metadata JSON besar
  untuk listing.
- Gunakan eager loading/batched queries untuk daftar attempt dan artifact.
- Backfill menggunakan `chunkById()`/cursor sesuai sifat operasi.
- Sign asynchronous dari sisi user dan berjalan pada dedicated queue. Worker
  memanggil BSrE secara sinkron setelah mengambil passphrase dari secret store
  terenkripsi ber-TTL; job payload hanya membawa opaque reference/attempt ID.
- Job sign memakai satu attempt pengiriman (`tries=1`) dan provider sign tidak
  auto-retry. Verify/notifikasi yang read-only dapat diproses terpisah dan retry
  terkendali tanpa membawa passphrase.
- Verifikasi viewer berjalan asynchronous/unique terhadap original immutable
  artifact dan memakai cache status terpisah. Jangan menjalankan BSrE pada setiap
  page load; pembuktian sandbox 3,99 MiB/8 signature memerlukan sekitar 40-43
  detik.
- Simpan summary pada `esign_attempts`; gunakan events hanya saat drill-down
  agar laporan harian tidak melakukan aggregate event mahal.
- Sediakan reconciliation dan alert untuk `unknown`, missing output, invalid
  file, chain putus, dan dual-write mismatch.

## 17. Urutan implementasi yang direkomendasikan

1. Inventaris consumer `before_signs`/`after_signs` dan kunci contract report.
2. Terapkan policy yang sudah dikunci: verify metadata publik minimal, satu
   resolver delivery setelah authorization, guest public watermark, serta flag
   posisi tunggal dengan Admin Super acting efektif `false`.
3. State machine serta schema `document_artifacts`, `esign_attempts`,
   `esign_attempt_events`, dan `esign_attempt_legacy_links` sudah dibuat;
   terapkan migration setelah deployment review.
4. Migration/indeks sudah dibuat; deployment plan, backup, dan lock assessment
   tabel besar masih wajib diselesaikan.
5. Repository/service artifact serta runtime storage path strategy sudah
   dibuat; source provisioning sudah dibuktikan lokal, sedangkan historical
   mapper belum dibuat.
6. Attempt transition service, lock, fingerprint, dan event writer sudah
   dibuat pada source dan menunggu runtime proof.
7. Compatibility writer legacy sudah dibuat; inventaris consumer dan parity
   report belum selesai.
8. Implementasikan migration/backfill/management `pdf_watermark_required`,
   renderer/COPY-ID, audit akses append-only, cache derivative 12 jam, cleanup,
   dan delivery policy fail-closed.
9. Implementasikan legacy URL resolver, route public `/verify/{public_id}` dan
   route delivery `/verify/{public_id}/download` dengan public/auth policy serta
   rendition resolver yang sama.
10. Implementasikan `document_artifact_signatures`, cache/job verifikasi
    asynchronous, halaman Blade Bootstrap/Argon, intended-login flow, dan audit
    seluruh delivery.
11. Buktikan kontrak visible coordinate dan beberapa sign serial pada satu PDF,
    lalu implementasikan QR generation/reservation per operation.
12. Buat migration additive operation/progress/intermediate/decoration,
    prepared footer rendition, checkpoint-aware worker, partial resume, dan
    atomic public ID activation sesuai
    `ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`.
13. Invisible signing orchestration sudah dibuat pada source; schema dan
    provisioning worker lokal sudah terbukti. LS SPP memakai lazy activation
    saat signing session pertama serta assignment/activation step berikutnya
    saat handoff. Berikutnya selesaikan visible QR/footer dan buktikan jalur
    BP -> PPTK -> PA secara end-to-end.
14. Registrasikan/backfill legacy secara bertahap di lokasi existing.
15. Inventaris dan tarik arsip fisik 2024-2025 yang belum ada pada subset 90
    hari di project baru.
16. Copy-verify-activate seluruh artifact ke layout canonical per tahun/bulan.
17. Lewati Backend Ready Gate, kemudian implementasikan modal Svelte/Vite.
18. Migrasikan consumer laporan hanya setelah parity tervalidasi, tetapi tetap
    pertahankan compatibility ledger append-only.
19. Evaluasi freeze/read-only hanya melalui keputusan baru; tidak ada drop tabel
    dalam scope implementasi aktif.
20. Hapus folder `File_{TYPE}` setelah migration manifest, backup, restore,
    compatibility route, dan rollback gate file lulus.

## 18. Acceptance gate

Rancangan ini belum dianggap selesai sampai:

- input artifact selalu dapat ditemukan untuk success maupun failure;
- output artifact hanya menjadi current setelah persistence dan verify lulus;
- attempt multi-QR hanya menjadi succeeded/current setelah semua operation dan
  final verify lulus; intermediate tidak pernah current;
- operation completed dapat di-resume dari checkpoint berikutnya tanpa
  mengulang signature, sedangkan outcome ambigu menghentikan pipeline;
- satu attempt multi-QR menghasilkan tepat satu compatibility before/after/TTE
  projection;
- retry menghasilkan attempt baru tanpa menimpa histori;
- ambiguous timeout menjadi `unknown` dan tidak auto-retry;
- QR exact-version dan legacy QR sama-sama dapat diselesaikan;
- legacy redirect memakai `302` selama migrasi dan hanya berubah `301` setelah
  exact mapping parity lulus;
- halaman public verify hanya menampilkan status, nomor bila ada, nama signer,
  dan tanggal signature dari exact artifact;
- guest tidak pernah menerima original/private path; public PDF hanya diberikan
  setelah public-access policy lulus dan selalu watermarked. Authenticated user
  tetap harus lolos policy, rendition mengikuti flag/acting rule, dan seluruh
  delivery tercatat;
- view/download memakai satu keputusan rendition; tidak ada split flag, direct
  public path, temporary URL bypass, atau fallback original;
- derivative watermark bukan artifact canonical/source TTE/verify; cache 12 jam,
  COPY-ID, concurrency lock, cleanup, dan failure mode telah diuji;
- QR scan tidak memanggil BSrE setiap request karena signature read model/cache
  terikat immutable artifact/SHA-256;
- file invalid/zero-byte tetap tersedia dan tidak current;
- dual-write menghasilkan laporan legacy yang setara;
- reporting projection baru memperlihatkan input, output, status, dan event;
- tidak ada credential/passphrase/raw PDF di database/log/event;
- backup, restore, retention, dan reconciliation telah diuji secara operasional;
- compatibility write tetap append-only dan parity-nya terpantau;
- perubahan lifecycle tabel legacy memerlukan backup final, checksum mapping,
  restore drill, retention review, observation window, persetujuan consumer,
  dan keputusan pengguna baru;
- setiap artifact mempunyai `artifact_created_at` dan canonical path yang
  diturunkan dengan aturan tahun yang terdokumentasi;
- seluruh file 2024-2025 tersedia secara fisik atau berstatus exception yang
  jelas; metadata database saja tidak dianggap berhasil dimigrasikan;
- size/SHA-256 dan struktur signature source-destination sama sebelum legacy
  path dinonaktifkan;
- folder `File_{TYPE}` tidak dihapus sebelum route QR/download lama dapat
  resolve artifact dari canonical storage dan rollback window selesai;
- mapping dapat dihentikan/restart pada setiap stage tanpa mengulang seluruh run
  atau membuat duplicate row/file/event;
- high-watermark dan catch-up menangani data baru selama layanan tetap berjalan;
- progress, heartbeat, stale lease, retryable failure, manual review, parity,
  dan disk pressure tersimpan/observable;
- mapping command tidak pernah menghapus source dan decommission memakai
  command/gate terpisah.

## 19. Keputusan terbuka

1. Consumer aplikasi lain memakai direct DB, replica, export, atau API?
2. Query dan definisi status sukses/gagal apa yang dipakai tiap consumer?
3. Berapa retention attempt event dan sanitized provider metadata?
4. Apakah internal artifact UUID dan public UUID memakai nilai yang sama atau
   dua UUID berbeda? Kontrak eksternal tetap bernama `public_id`.
5. Database engine/version production dan kemampuan online index apa yang
   tersedia?
6. Apakah reporting view cukup atau perlu API versioned dengan pagination?

Sampai pertanyaan tersebut diputuskan, agent tidak boleh menghapus kontrak
legacy atau melakukan backfill destruktif. Download PDF publik sudah diputuskan
tidak diizinkan.
