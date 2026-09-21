# Runbook Mapping TTE Resumable dan Zero-Downtime

Tanggal keputusan: **18 September 2026**.

Status: **keputusan arsitektur dan runbook implementasi; migration DDL schema
kontrol, checkpoint `current_stage`, indeks claim/resume, serta enum status/stage
sudah dibuat dan tabel kontrol canonical sudah diterapkan pada database lokal.
Dua migration index mapping legacy masih `Pending`. Runner, transition service, queue,
command, dashboard, dan proses mapping belum diimplementasikan**.

Dokumen ini menjadi sumber keputusan untuk seluruh mapping database legacy,
rekonstruksi version chain, migrasi file PDF, pengisian signature read model,
consumer cutover, lifecycle tabel kompatibilitas, serta decommission folder
lama. Agent wajib membaca
`README.md`, `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`,
`ESIGN_V2_CONTRACT_AND_BACKEND.md`, dan
`ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md` lebih dahulu.

## 1. Keputusan yang dikunci

1. SITANGKAS adalah aplikasi layanan yang harus tetap berjalan selama mapping.
2. Seluruh mapping wajib resumable, idempotent, observable, dapat dipause, dan
   tidak membutuhkan restart dari awal setelah kegagalan sebagian.
3. Unit checkpoint utama adalah satu dokumen beserta seluruh attempt dan
   artifact chain-nya; file besar tetap mempunyai checkpoint per artifact.
4. Queue dianggap **at-least-once**, bukan exactly-once. Duplicate execution
   harus aman melalui unique constraint, fingerprint, lease, lock, dan
   pemeriksaan state.
5. Mapping historical berjalan pada queue berprioritas rendah yang terpisah dari
   traffic pengguna dan queue TTE aktif.
6. Panggilan sign BSrE tidak pernah menjadi bagian mapping dan tetap tidak boleh
   auto-retry. Read-only verification pada artifact lama boleh retry dengan rate
   limit dan backoff.
7. File sumber dan row legacy tidak diubah/dihapus selama mapping.
8. `before_signs` dan `after_signs` tetap append-only sesuai
   `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`. Mapping tidak memberi izin
   menghentikan write atau drop. Penghapusan folder `File_{TYPE}` tetap phase
   decommission terpisah setelah seluruh acceptance gate file lulus.
9. Schema change mengikuti expand-migrate-contract. DDL dan data backfill tidak
   boleh digabung dalam satu migration.
10. Semua progress dan exception disimpan di database; log proses bukan satu-
    satunya checkpoint.

## 2. Arti "resume pada kondisi apa pun"

Kondisi berikut wajib pulih otomatis atau melalui resume operator tanpa
mengulang seluruh run:

- command dihentikan normal;
- operator meminta pause;
- queue worker mati atau timeout;
- server restart;
- deployment/restart Supervisor;
- job terkirim dua kali;
- transaksi database rollback;
- database/storage mengalami gangguan sementara;
- copy file terputus dan meninggalkan temporary file;
- destination final sudah ada akibat eksekusi sebelumnya;
- verify read-only provider timeout/unavailable;
- beban aplikasi atau ruang disk melewati threshold dan run auto-pause.

Kondisi berikut tidak dapat diselesaikan hanya dengan resume dan wajib masuk
`needs_review` atau restore:

- source file satu-satunya hilang atau corrupt;
- source dan destination sama-sama rusak;
- database migration control hilang;
- storage/database mengalami kehilangan permanen;
- hash source berubah setelah inventory;
- relasi legacy ambigu dan tidak dapat dibuktikan;
- izin filesystem/credential dicabut permanen.

Backup, immutable manifest, dan restore drill adalah bagian desain resumability,
bukan pekerjaan terpisah yang opsional.

## 3. Strategi zero-downtime: expand-migrate-contract

### Expand

- buat tabel canonical dan migration-control baru;
- tambahkan code path canonical tanpa mematikan legacy;
- arahkan TTE/file baru langsung ke schema/storage canonical;
- pertahankan legacy read dan compatibility write sementara;
- buat route `/verify/{public_id}` dan legacy resolver sebelum file dipindahkan.

### Migrate

- ambil immutable high-watermark row legacy;
- inventory filesystem dan buat manifest;
- mapping historical dalam batch kecil;
- copy-verify-activate file tanpa direct move;
- verifikasi parity data/file/report;
- catch up row yang lahir setelah high-watermark;
- jalankan canonical-first read dengan legacy fallback.

### Contract

- pastikan delta legacy yang belum termapping nol dan parity dual-write lulus;
- pindahkan seluruh consumer ke reporting contract baru;
- pertahankan compatibility write append-only sesuai keputusan aktif;
- observasi canonical read dan compatibility parity;
- freeze/read-only/archive/drop tabel hanya dapat menjadi proposal terpisah
  setelah keputusan pengguna baru;
- hapus folder `File_{TYPE}` melalui command decommission terkontrol.

Tidak ada rename/drop/delete pada dua phase pertama.

## 4. Schema kontrol resumability

Nama final mengikuti inspeksi convention project, tetapi dua konsep berikut
wajib tersedia.

### `esign_migration_runs`

Satu row merepresentasikan satu run/version mapping:

```text
id / uuid
migration_type
source_system
status
high_watermark
last_checkpoint
total_items
processed_items
succeeded_items
failed_items
needs_review_items
lease_token
lease_owner
leased_until
heartbeat_at
started_at
paused_at
completed_at
parameters JSON
error_summary
created_by_user_id
created_at
updated_at
```

Status run:

```text
pending
running
paused
completed
completed_with_exceptions
failed
```

Versi/rule/configuration hash yang dibutuhkan runner disimpan pada `parameters`.
Jika migration version atau rule berubah, buat run baru; jangan melanjutkan
checkpoint memakai algoritma berbeda tanpa rekonsiliasi.

### `esign_migration_items`

Satu row menyimpan checkpoint unit dokumen/artifact:

```text
id
esign_migration_run_id
source_key
source_table
source_id nullable
source_updated_at nullable
legacy_document_id nullable
status
current_stage
attempt_count
document_signing_workflow_id nullable
document_artifact_id nullable
esign_attempt_id nullable
source_sha256 nullable
destination_sha256 nullable
lease_token nullable
lease_owner nullable
leased_until nullable
heartbeat_at nullable
next_retry_at nullable
reason_code nullable
message nullable
metadata JSON nullable
started_at nullable
completed_at nullable
created_at
updated_at
```

Status item final:

```text
pending | processing | succeeded | retryable_failed | needs_review | failed
```

Checkpoint `current_stage` final:

```text
discovered -> metadata_mapped -> file_copied -> checksum_verified
           -> canonical_activated -> completed
```

Constraint/index yang sudah berada pada migration:

- unique `(esign_migration_run_id, source_key)`;
- index `(esign_migration_run_id, status, current_stage, id)` untuk claim/resume;
- index `(status, next_retry_at)` untuk retry dispatcher;
- index `(status, leased_until)` untuk stale lease recovery;
- index `(source_table, source_id)` untuk exact legacy lookup.

Kolom/indeks tambahan hanya boleh dibuat dari kebutuhan query runner/report
nyata. Jangan menganggap field konseptual lama yang tidak tercantum di atas
sudah tersedia pada DDL.

Jangan simpan exception mentah, PDF/base64, NIK lengkap, passphrase, Basic Auth,
atau response BSrE mentah pada tabel kontrol.

## 5. High-watermark dan live traffic

Saat run dibuat, simpan maksimum primary key yang akan diproses:

```text
before_signs.id <= captured maximum
after_signs.id <= captured maximum
document_process.id <= captured maximum
```

Tujuannya menghasilkan snapshot batas stabil tanpa menghentikan insert baru.
Data yang masuk setelah batas:

- ditulis langsung ke canonical pipeline bila caller sudah dimigrasi;
- tetap masuk compatibility flow selama transisi;
- diproses oleh catch-up run jika masih berasal dari caller legacy.

Mapping initial belum selesai bukan alasan menghentikan layanan. Read path selama
transisi:

```text
lookup canonical artifact
    ↓ bila belum canonical_activated
fallback ke legacy metadata/path
```

Setelah initial run selesai, ulangi catch-up sampai seluruh row legacy sudah
termapping dan tidak ada caller yang menulis tanpa canonical dual-write.

## 6. Unit kerja dan pairing data legacy

Unit utama adalah `document_id`, bukan satu row `before_signs`. Seluruh row
berikut dibaca bersama secara deterministik:

- `document` termasuk soft-deleted bila relevan;
- `document_process` ordered by `created_at,id`;
- `before_signs` ordered by `created_at,id`;
- `after_signs` ordered by `created_at,id`;
- seluruh source/signed filename yang mungkin terkait;
- current dan ancestor files pada filesystem inventory.

Alasan: output attempt N adalah input attempt N+1. Memetakan row secara terpisah
dapat memutus parent chain atau menggandakan attempt.

Gunakan `esign_attempt_legacy_links` dan unique legacy IDs untuk membuktikan
idempotency. Re-run tidak membuat artifact/attempt/event baru jika source key
sudah terhubung dengan canonical record yang valid.

Status rekonsiliasi minimum:

```text
matched
input_only
output_only
metadata_only
file_missing
file_invalid
ambiguous
duplicate_candidate
hash_mismatch
needs_review
```

Mapping ambigu tidak boleh diselesaikan dengan tebakan untuk mengejar angka
100%. Exception yang lengkap lebih benar daripada chain palsu.

## 7. State machine item

Status item dan checkpoint adalah dua kolom berbeda. Happy path:

```text
status: pending -> processing -> succeeded

current_stage: discovered
  -> metadata_mapped
  -> file_copied
  -> checksum_verified
  -> canonical_activated
  -> completed
```

Status operasional lain:

```text
retryable_failed
needs_review
failed
```

Checkpoint disimpan setelah setiap transition dalam transaksi pendek. External
I/O, copy file, hash file, parsing PDF, atau request verify tidak boleh berada
dalam transaksi database panjang.

Resume membaca `current_stage` dan memvalidasi post-condition stage tersebut:

- `metadata_mapped`: canonical rows dan legacy links tersedia;
- `file_copied`: temporary/final destination tersedia;
- `checksum_verified`: size/hash/PDF/signature checks lulus;
- `canonical_activated`: canonical path/current pointer benar;
- `completed`: parity item final lulus.

Jika checkpoint berkata `file_copied` tetapi destination hilang, jangan lanjut
buta; ubah status menjadi `retryable_failed` dan copy ulang dari source yang
diverifikasi.

## 8. Lease, lock, dan duplicate execution

Worker mengklaim item memakai lease:

```text
lease_owner = worker identifier
leased_until = now + lease duration
```

Worker memperbarui heartbeat/lease pada pekerjaan yang aman untuk diperpanjang.
Jika worker mati, lease kedaluwarsa dan worker lain dapat mengambil item dari
checkpoint terakhir.

Selain lease:

- gunakan lock per `document_id` untuk mencegah dua worker merekonstruksi chain
  yang sama;
- unique constraint menjadi pertahanan terakhir terhadap duplicate row;
- gunakan transaksi pendek/`lockForUpdate()` hanya saat state transition;
- jangan menahan lock saat copy/hash/verify;
- scheduler-level `withoutOverlapping()` bukan pengganti item lease.

Queue/job boleh dieksekusi lebih dari sekali. Handler wajib melakukan
ensure/check/upsert, bukan create tanpa pemeriksaan.

## 9. Algoritma copy file yang crash-safe

Destination sementara:

```text
{canonical-path}/{uuid}.pdf.part
```

Alur:

1. verifikasi source path berasal dari inventory/metadata, bukan input bebas;
2. pastikan ruang disk berada di atas safety threshold;
3. copy source ke `.part` tanpa memodifikasi source;
4. hitung destination size dan SHA-256;
5. cocokkan dengan source size/SHA-256;
6. parse PDF non-zero dan periksa signature count/`ByteRange` sesuai capability;
7. rename `.part` ke final path secara atomik pada disk yang sama;
8. dalam transaksi pendek, aktifkan `document_artifacts.path` dan migration
   stage;
9. uji exact artifact lookup/route;
10. tandai completed; legacy row tetap tersedia sebagai compatibility ledger,
    sedangkan file source tetap tersedia sampai gate migrasi file lulus.

Recovery:

- `.part` tidak lengkap: hapus hanya temporary explicit path lalu copy ulang;
- `.part` lengkap/hash cocok: lanjut verifikasi;
- final destination ada/hash cocok: skip copy dan lanjut activation;
- final destination ada/hash berbeda: `needs_review`, jangan overwrite;
- DB belum aktif tetapi final file sudah ada: validasi lalu aktifkan;
- DB aktif tetapi checkpoint tertinggal: validasi invariants lalu complete.

Jangan menjalankan recursive delete dengan glob atau path hasil concatenation
yang belum divalidasi. Cleanup dan legacy deletion memakai command berbeda dari
mapping.

## 10. Queue dan scheduler

Gunakan queue terpisah, misalnya:

```text
esign-migration
```

Queue ini berprioritas di bawah request/TTE aktif dan tidak membawa passphrase.
Aturan:

- job kecil dan bounded, idealnya satu document chain atau satu artifact;
- `retry_after` harus lebih besar daripada job timeout;
- retry error sementara memakai exponential backoff;
- job mempunyai failure handler yang menyimpan safe error code/state;
- duplicate dispatch aman karena unique key + lease + idempotency;
- read-only verify provider memakai rate limiter;
- error deterministik seperti hash mismatch langsung `needs_review` dan tidak
  berputar tanpa batas.

Dispatcher scheduler memakai `withoutOverlapping()` dan `onOneServer()` bila
deployment multi-server serta shared lock tersedia. Scheduler hanya mengisi
queue/memulihkan stale leases; progress truth tetap berada pada tabel kontrol.

## 11. Command operasional

Nama final mengikuti convention project. Capability minimum:

```text
php artisan esign:migrate-artifacts \
  --run={uuid} \
  --resume \
  --batch=250 \
  --max-runtime=50 \
  --only-year=2024 \
  --retry-errors \
  --stop-after={count} \
  --no-delete
```

Mode yang diperlukan:

- `--dry-run`: inventory/mapping plan tanpa write canonical/file;
- create run: capture high-watermark/config hash/manifest;
- `--resume`: lanjut run yang sama dari checkpoint;
- `--retry-errors`: hanya error retryable yang waktunya sudah tiba;
- pause: ubah run menjadi `paused` dan isi `paused_at`, bukan kill paksa;
- status: progress/counters/heartbeat tanpa payload sensitif;
- reconcile: hitung parity canonical vs source/manifest;
- decommission folder: command berbeda dengan explicit target dan safety gate;
- perubahan lifecycle tabel: hanya setelah keputusan pengguna baru.

Menjalankan `--resume` berulang harus menghasilkan state akhir yang sama.
Mapping command tidak mempunyai opsi yang menghapus source.

## 12. Pause, resume, dan graceful stop

Saat pause diminta:

1. run berubah `paused` dan `paused_at` diisi;
2. dispatcher berhenti membuat claim baru;
3. worker yang sudah mempunyai lease menyelesaikan unit aktif yang pendek;
4. worker menyimpan checkpoint dan melepas lease;
5. status tetap `paused`; status command dapat menampilkan lease aktif yang
   belum selesai tanpa membuat state tambahan `pausing`.

Resume:

1. validasi configuration hash, schema version, manifest, source availability,
   disk space, dan queue health;
2. pulihkan lease yang stale;
3. ubah run menjadi `running`;
4. dispatch item dari current stage/next retry time.

Deployment harus memberi graceful termination pada worker. Karena satu job
bounded, worker dapat selesai sebelum restart. Jika tetap terbunuh, lease expiry
dan idempotency menangani recovery.

## 13. Throttling dan perlindungan layanan

Parameter harus configurable tanpa mengubah algoritma mapping:

- jumlah worker/concurrency;
- batch size;
- maximum runtime per dispatch/job;
- memory limit;
- jeda antarbatch;
- maksimum query time;
- verify request rate;
- minimum free disk bytes/percentage;
- jam operasi/aggressiveness profile;
- maksimum retry dan error berturut-turut;
- stale lease threshold.

Mulai dengan batch konservatif dan naikkan hanya berdasarkan metrik. Mapping
auto-pause jika:

- free disk melewati threshold;
- DB latency atau application error rate meningkat;
- hash mismatch/manual-review rate melewati threshold;
- heartbeat/queue abnormal;
- source storage tidak stabil;
- operator mengaktifkan emergency pause.

Auto-pause tidak mengubah item valid yang sudah completed.

## 14. Database performance dan DDL

- scan legacy memakai primary-key cursor/`chunkById()`, bukan `OFFSET`;
- select hanya kolom yang diperlukan;
- hindari N+1 per row;
- prefetch seluruh row satu dokumen secara bounded;
- simpan summary counters pada run, tetapi rekonsiliasi final tetap menghitung
  dari item/source;
- index baru pada tabel canonical dibuat saat table creation;
- jangan berasumsi `ALTER TABLE` legacy 590 ribu+ row selalu online;
- periksa engine/version dan kemampuan `INSTANT`/`INPLACE`/`LOCK=NONE` sebelum
  menambah index legacy;
- bila online DDL tidak terbukti aman, lakukan one-pass primary-key extraction
  atau gunakan strategi online schema change yang disetujui operator.

Create new table biasanya lebih aman daripada mengubah tabel legacy besar,
tetapi tetap harus melewati deployment review dan capacity check.

## 15. Read/write compatibility selama mapping

Data baru:

- canonical writer adalah target akhir;
- compatibility write `before_signs`/`after_signs` tetap append-only sesuai
  keputusan aktif, termasuk setelah consumer baru memakai reporting canonical;
- source of truth state tetap `esign_attempts` dan artifacts/events terkait.

Data baca:

- canonical-first saat artifact sudah `canonical_activated`;
- fallback legacy hanya untuk item belum selesai;
- jangan menyajikan artifact `file_copied` tetapi belum
  `checksum_verified/canonical_activated`;
- exact QR version tidak boleh berubah karena mapping;
- delivery tetap melalui public/auth policy, private stream, dan resolver
  `pdf_watermark_required`/acting/guest pada
  `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`; mapping tidak boleh membuka
  direct original bypass.

Failure satu item tidak boleh menutup akses dokumen lain atau menghentikan
proses TTE aktif.

## 16. Observability dan dashboard operasi

Metrik minimum:

```text
run status
last heartbeat
high-watermarks
total/pending/processing/completed
retryable failed/manual review
throughput per minute
average/p95 item duration
current batch/year
stale leases
missing source
hash mismatch
invalid/zero-byte preserved
source/canonical parity
free disk space
queue depth
estimated completion
```

Alert minimum:

- heartbeat berhenti;
- stale lease meningkat;
- destination hash mismatch;
- source berubah/hilang;
- ruang disk rendah;
- application/DB latency melewati threshold;
- error rate tinggi;
- item processing terlalu lama;
- parity source/canonical menurun;
- parity mismatch canonical-versus-compatibility meningkat atau tidak
  terselesaikan.

Log hanya menyimpan run/item/document/artifact identifier aman, stage, safe
error code, durasi, dan correlation ID. Jangan log raw response, NIK,
passphrase, Basic Auth, isi file, atau filesystem secret.

## 17. Recovery matrix

| Kondisi | Perilaku wajib |
|---|---|
| Worker mati | Lease kedaluwarsa; worker lain resume dari checkpoint |
| Server restart | Run tetap persisted; dispatcher resume setelah preflight |
| Deployment | Graceful stop; stale lease recovery bila worker terbunuh |
| Duplicate job | Unique constraint/idempotency menghasilkan no-op atau lanjut stage |
| DB timeout | Transaksi rollback; item menjadi retryable |
| Storage timeout | Item retryable; source tidak berubah |
| `.part` tertinggal | Verifikasi dan lanjut atau hapus hanya temp explicit lalu ulang |
| Final destination sudah ada, hash cocok | Skip copy dan lanjut verify/activate |
| Final destination sudah ada, hash berbeda | Manual review; jangan overwrite |
| Source hilang | `file_missing`/manual review; tidak dianggap sukses |
| Source berubah sejak manifest | Auto-pause/manual review |
| Disk rendah | Auto-pause sebelum copy |
| Verify provider unavailable | Backoff/rate-limited retry read-only |
| Relasi legacy ambigu | Manual review; jangan tebak |
| Database/storage hilang | Restore backup+manifest lalu resume |

## 18. Lifecycle tabel dan decommission folder tetap proses terpisah

Mapping `completed` tidak otomatis memberi izin menghentikan compatibility
write, rename, archive, atau drop `before_signs`/`after_signs`. Keputusan aktif
adalah mempertahankan kedua tabel append-only. Perubahan lifecycle tabel hanya
boleh menjadi phase baru setelah seluruh gate berikut lulus dan pengguna
memberikan keputusan eksplisit baru:

- initial + catch-up run selesai;
- tidak ada pending/retryable item;
- seluruh manual-review diputuskan/didokumentasikan;
- parity row, file count, size, SHA-256, signature, route, dan report lulus;
- seluruh consumer/caller sudah cutover;
- canonical read tanpa fallback telah melewati observation window;
- final backup dan restore drill lulus;
- explicit target table dan rollback plan disetujui operator.

Tanpa keputusan baru, tabel tetap append-only. Penghapusan folder adalah concern
berbeda dan memakai command decommission yang memvalidasi resolved absolute
paths satu per satu serta tidak memakai recursive target luas. Invalid/zero-byte
yang merupakan evidence tetap harus mempunyai canonical preservable copy
sebelum folder legacy dihapus.

## 19. Urutan implementasi

1. Finalkan schema canonical dan mapping rules/version.
2. Migration-control tables dan enum status/stage sudah dibuat; berikutnya
   implementasikan state transition service yang menegakkan urutan stage.
3. Implementasikan dry-run inventory dan immutable manifest.
4. Implementasikan high-watermark capture.
5. Implementasikan claim/lease/heartbeat/stale recovery.
6. Implementasikan document-chain mapper yang idempotent.
7. Implementasikan crash-safe copy `.part` dan SHA-256/PDF verification.
8. Implementasikan activation transaction dan canonical-first fallback read.
9. Implementasikan queue `esign-migration`, retry/backoff, throttling, dan
   scheduler dispatcher.
10. Implementasikan pause/resume/status/reconcile command.
11. Implementasikan metrics, dashboard, alert, dan audit aman.
12. Jalankan dry-run dan failure-injection terkontrol pada salinan data sesuai
    izin testing/operasional project.
13. Pilot batch kecil satu tahun/jenis tanpa delete.
14. Validasi traffic aplikasi tidak terdampak.
15. Jalankan initial mapping, catch-up, consumer cutover, dan observation sambil
    mempertahankan compatibility write append-only.
16. Implementasikan decommission folder hanya setelah gate file terpisah;
    jangan membuat drop table tanpa keputusan pengguna baru.

## 20. Acceptance gate resumability

Mapping belum boleh dijalankan penuh di production sebelum bukti berikut ada:

- stop/resume dari setiap stage menghasilkan state akhir yang sama;
- worker kill dan server restart tidak kehilangan progress;
- duplicate job tidak membuat duplicate row/file/event;
- stale lease dapat direcovery;
- source tidak pernah dimodifikasi;
- destination tidak diaktifkan sebelum size/hash/PDF verification lulus;
- crash sebelum/sesudah atomic rename dan DB activation dapat dipulihkan;
- satu item error tidak menghentikan item lain;
- manual-review item tetap terlihat dan tidak dihitung sukses;
- pause/resume tidak memerlukan restart aplikasi;
- high-watermark dan catch-up menangani write baru;
- canonical-first + legacy fallback menjaga layanan tetap tersedia;
- mapping queue tidak menghambat request/TTE aktif;
- disk/DB pressure dapat memicu auto-pause;
- progress, heartbeat, throughput, exception, dan parity observable;
- backup serta restore drill dengan manifest berhasil;
- decommission folder tidak otomatis berjalan dari mapping command dan mapping
  tidak mengubah lifecycle tabel compatibility;
- tidak ada credential, NIK lengkap, passphrase, atau PDF mentah di log/control
  tables/queue payload.

## 21. Larangan eksplisit

- Jangan membuat one-shot script tanpa checkpoint database.
- Jangan memakai `OFFSET` untuk scan jutaan row yang berubah.
- Jangan menganggap queue exactly-once.
- Jangan membuka transaksi DB selama copy/hash/verify external I/O.
- Jangan direct-move source legacy.
- Jangan overwrite destination dengan hash berbeda.
- Jangan menebak mapping ambigu.
- Jangan menjalankan mapping pada queue `signatures` atau queue traffic utama.
- Jangan menghapus source dari command mapping.
- Jangan freeze, rename, archive, atau drop tabel compatibility karena counter
  terlihat 100%. Perubahan itu memerlukan seluruh gate dan keputusan pengguna
  baru. Jangan menghapus folder tanpa parity manifest, backup/restore,
  compatibility route, dan rollback gate.
- Jangan menjanjikan resume dari permanent data loss tanpa backup yang dapat
  dipulihkan.
