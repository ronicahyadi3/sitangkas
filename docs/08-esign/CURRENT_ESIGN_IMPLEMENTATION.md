# Kondisi Implementasi eSign/TTE Saat Ini

Tanggal snapshot: **21 September 2026**.

Status: **backend in progress**. Boundary provider, schema/model/state service,
authorization, signing session, private artifact persistence, secret store,
endpoint internal, dan asynchronous signing job sudah berada di working tree.
Sebanyak 13 migration tabel canonical sudah diterapkan pada database lokal;
dua migration index mapping legacy tetap `Pending` untuk deployment wave
terpisah. Workflow/artifact belum diprovisikan dari controller payment, worker
server belum diaktifkan/dibuktikan, visible
QR/footer dan public verification belum dibuat, serta vertical slice baru belum
dijalankan end-to-end melalui pipeline canonical.

Dokumen ini adalah handoff kondisi kode aktual. Untuk keputusan bisnis dan
target final tetap baca:

1. `README.md`;
2. `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`;
3. `ESIGN_V2_CONTRACT_AND_BACKEND.md`;
4. `ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md`;
5. `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`;
6. `ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md`;
7. `ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`;
8. `ESIGN_V2_IMPLEMENTATION_PLAN.md`.

Jika ada perbedaan antara tracker lama dan dokumen ini, verifikasi kode,
migration status, dan route aktual. Jangan menganggap komponen sudah deployed
hanya karena class-nya tersedia di repository.

## 1. Ringkasan posisi phase

| Phase | Kondisi aktual | Catatan |
|---:|---|---|
| 0 | Sebagian selesai | Containment lokal selesai; rotasi/revoke credential eksternal tetap tanggung jawab pemilik/deployment. |
| 1 | Minimum selesai | Invisible NIK+passphrase satu PDF dan verify minimum pernah dibuktikan; visible coordinate, limit, timeout matrix, encrypted PDF, dan multi-file belum final. |
| 2 | Kode selesai untuk scope awal | `EsignGateway`, `BsreClient`, DTO, mapper, payload invisible satu file, error taxonomy, dan config tersedia. |
| 3 | Schema aktif, runtime belum diprovisikan | Sebanyak 13 tabel canonical, model, enum cast, transition/persistence, artifact storage, provider response, event, dan compatibility writer tersedia. Dua migration index mapping legacy masih `Pending`; mapping runner dan reconciliation belum ada. |
| 4 | Sebagian besar kode selesai | Policy, authorization service, signer resolver, encrypted ephemeral session, context revalidation, dan private preview tersedia. Belum terhubung ke workflow nyata dari controller payment. |
| 5 | Kode vertical slice tersedia, belum lulus acceptance | Endpoint internal, encrypted secret TTL, `202 Accepted`, queue job, sign-verify-finalize, polling, dan legacy projection tersedia. Belum diuji end-to-end karena schema/data/worker belum aktif. |
| 6 | Belum | Visible placement QR/footer, coordinate transform, policy delivery PDF berbasis `pdf_watermark_required`, watermark/COPY-ID/cache/audit, verify endpoint publik, guest/authenticated delivery, dan legacy QR resolver belum dibuat. |
| 7 | Belum lulus | Backend Ready Gate masih terhalang deployment index legacy, worker, provisioning, reconciliation, observability, performance proof, credential rotation, dan test yang diizinkan. |
| 8-10 | Belum | Dependency dan komponen Svelte/Vite eSign belum dipasang. |
| 11 | Belum | Pilot payment belum dipilih/diaktifkan. |
| 12 | Belum | Rollout, mapping legacy resumable, reporting cutover, dan decommission belum berjalan. |

Kesimpulan posisi: dari sisi source code pekerjaan sudah mencapai **Phase 5
parsial**, tetapi dari sisi runtime/deployment masih berada pada **penutupan
Phase 3 menuju Phase 4**.

## 2. Keputusan bisnis yang tetap berlaku

- Integrasi target adalah eSign Client `2.2.0` API v2.
- Concrete provider client bernama `BsreClient`.
- Scope metode awal hanya NIK + passphrase dengan satu PDF.
- NIK selalu diselesaikan backend dari authenticated user; frontend tidak
  mengirim NIK.
- Mode bisnis hanya `SELF_SIGN`. `PREPARE_FOR_SIGNER` tidak dibuat.
- Admin Super tidak dapat proxy-sign melalui acting context.
- Admin Super yang memakai posisi bisnis nyata miliknya diperlakukan sebagai
  pengguna biasa pada posisi tersebut.
- TTE multi-signer selalu sequential; output step sebelumnya menjadi source
  step berikutnya.
- Semua signer menempatkan QR/footer miliknya sendiri pada tahap visible.
- Closing placement/preview sebelum final sign tidak membuat attempt/audit.
- TTE berjalan asynchronous dari perspektif browser, tetapi HTTP BSrE tetap
  synchronous di dedicated worker.
- Sign tidak memiliki blind/automatic retry.
- `before_signs` dan `after_signs` tetap compatibility ledger append-only.
- Enam tabel legacy operasional tetap dipertahankan sesuai kontrak:
  `document`, `document_process`, `anggaran_kegiatan`,
  `anggaran_kegiatan_temp`, `before_signs`, dan `after_signs`.
- Artifact berada pada private storage dan tidak dikelompokkan berdasarkan
  tipe dokumen.
- URL QR target adalah `/verify/{public_id}` untuk exact immutable artifact.
- Target delivery PDF memakai satu flag posisi `pdf_watermark_required` untuk
  seluruh preview/view/download. Flag `true` selalu watermark; `false` boleh
  exact original setelah authorization. Guest public selalu watermark. Admin
  Super saat acting like diperlakukan sebagai `false`; posisi bisnis nyata
  miliknya mengikuti flag posisi tersebut. Seluruh rancangan ini belum
  diimplementasikan.
- Frontend target adalah Svelte island melalui Vite pada Blade, bukan SPA dan
  bukan SvelteKit.
- Tampilan eSign memakai Bootstrap 5 + custom Argon Dashboard Pro 2; Tailwind
  bukan basis styling eSign.

## 3. Provider boundary yang sudah tersedia

Komponen utama:

- `App\Contracts\Esign\EsignGateway`;
- `App\Services\Esign\BsreClient`;
- `App\Services\Esign\BsreConfiguration`;
- `App\Services\Esign\EsignPayloadBuilder`;
- `App\Services\Esign\BsreResponseMapper`;
- DTO pada `app/Data/Esign`;
- `App\Exceptions\Esign\EsignOperationException` dan taxonomy
  `EsignErrorCode`.

Kontrak yang aktif pada scope awal:

- NIK 16 digit dari backend;
- passphrase ephemeral;
- tepat satu PDF;
- tepat satu `signatureProperties` dengan tampilan `INVISIBLE`;
- sign dan verify API v2;
- tidak ada `Http::retry()` pada operasi sign;
- response vendor tidak diteruskan mentah ke browser.

Yang belum dianggap terbukti/final:

- koordinat visible pada seluruh page rotation;
- file/request limit resmi vendor;
- rate limit vendor;
- multi-file pairing dan partial success;
- encrypted/password-protected PDF matrix;
- error code resmi seluruh kegagalan v2;
- wrong-passphrase mapping yang sepenuhnya dibuktikan.

## 4. Schema canonical dan model

Migration DDL tabel canonical yang sudah diterapkan pada database lokal
mencakup:

- `document_artifacts`;
- `document_signing_workflows`;
- `document_signing_steps`;
- `esign_attempts`;
- `esign_attempt_signature_properties`;
- `esign_provider_responses`;
- `esign_attempt_events`;
- `document_artifact_signatures`;
- `document_artifact_signature_certificates`;
- `esign_attempt_legacy_links`;
- `esign_migration_runs`;
- `esign_migration_items`;
- `document_signing_workflow_events`;
- indeks mapping pada tabel legacy sign dan `document_process`.

Model Eloquent, explicit fillable/hidden/cast, dan relasi sudah tersedia pada
`app/Models/Esign`. Model penting menggunakan enum cast. Model append-only
menolak update/delete bila sesuai fungsi auditnya. `DocumentArtifact` melarang
perubahan identity metadata dan delete. `EsignAttempt` menolak perubahan
`document_id` setelah insert.

`esign_attempts.document_id`:

- diisi server-side dari workflow;
- immutable untuk runtime baru;
- terindeks bersama `created_at` untuk laporan;
- nullable hanya untuk pemetaan legacy orphan;
- harus sama dengan document pada workflow, source artifact, dan result
  artifact.

## 5. State machine dan persistence service

Service yang sudah tersedia:

- `DocumentSigningWorkflowTransitionService`;
- `DocumentSigningStepTransitionService`;
- `EsignAttemptPersistenceService`;
- `DocumentArtifactPersistenceService`;
- `EsignProviderResponsePersistenceService`;
- `DocumentArtifactSignaturePersistenceService`;
- `LegacyEsignLedgerWriter`.

State attempt:

```text
prepared -> signing -> validating -> succeeded
         |          |              -> failed
         |          -> failed
         |          -> unknown
         -> failed
```

Karakteristik persistence:

- transaksi pendek dan `lockForUpdate()` dipakai untuk perubahan state;
- external HTTP tidak dipanggil di dalam transaksi DB;
- request memakai UUID idempotency key dan SHA-256 fingerprint;
- blocking attempt mencegah concurrent sign pada step yang sama;
- provider response dan event disimpan append-only;
- raw PDF/Base64, passphrase, Basic Auth, dan raw vendor body tidak disimpan;
- success mempromosikan result artifact menjadi current, menyelesaikan step,
  lalu mengaktifkan step berikutnya atau menyelesaikan workflow;
- failure deterministik mengembalikan step ke `active` sesuai transition;
- outcome ambigu mengubah step ke `reconciliation_required`;
- retry bisnis membuat attempt baru, bukan menghidupkan kembali attempt lama.

Reconciliation service untuk mengubah attempt `unknown` berdasarkan bukti
belum dibuat.

## 6. Artifact persistence dan integritas file

`DocumentArtifactPersistenceService` sudah menyediakan:

- staging isi/stream PDF;
- finalisasi source artifact `before_sign`;
- finalisasi output attempt sebagai `after_sign` atau `failed_output`;
- SHA-256, size, magic bytes `%PDF-`, version, parent chain, dan current flag;
- staging `.part` dan final move pada private local disk;
- output valid baru dipromosikan setelah verify sukses.

Layout runtime baru:

```text
documents/staging/{public_id}.pdf.part
documents/source/{YYYY}/{MM}/{uuid-prefix}/{public_id}.pdf
documents/signed/{YYYY}/{MM}/{uuid-prefix}/{public_id}.pdf
documents/failed-output/{YYYY}/{MM}/{uuid-prefix}/{public_id}.pdf
```

`DocumentArtifactIntegrityService` memverifikasi metadata path, ukuran,
SHA-256, header PDF, dan batas ukuran aplikasi sebelum preview/sign. Default
batas aplikasi saat ini 50 MiB; nilai ini belum berarti limit resmi BSrE.

Private preview saat ini memakai streamed inline response exact source dengan
`no-store` dan tidak membocorkan physical path. Endpoint aktual ini **belum**
menegakkan `pdf_watermark_required`; sebelum rollout ia harus dipindahkan ke
delivery policy canonical. Backend sign tetap membaca exact original secara
internal meskipun preview user berupa derivative watermark.

Yang belum tersedia:

- source artifact provisioning dari upload/controller payment;
- historical file mapper/copy runner;
- scheduled cleanup staging;
- orphan/stuck artifact reconciliation;
- public/authenticated artifact download route.
- migration/management `user_positions.pdf_watermark_required`;
- resolver delivery mode termasuk pengecualian Admin Super acting dan guest;
- renderer watermark server-side, COPY-ID, cache derivative 12 jam, cleanup,
  audit akses append-only, dan cache/job verifikasi BSrE asynchronous.

## 7. Authorization dan signer identity

`EsignAuthorizationService` dan policy canonical sudah tersedia untuk:

- view/download workflow;
- view/preview/download artifact;
- view attempt;
- place/sign/retry/reject step;
- reject paket SP2D menurut invariant yang sudah disepakati.

Prasyarat SELF_SIGN yang ditegakkan:

- akun aktif dan tidak locked;
- real authenticated position tersedia dan valid;
- acting context Admin Super tidak boleh sign;
- user dan real position sama dengan assignment step;
- role, unit kerja, dan instansi cocok;
- workflow dan step sedang aktif/current;
- selected year dan izin tulis tahun cocok;
- source artifact current dan terikat ke workflow/step;
- seluruh step sebelumnya sudah selesai/skipped;
- tidak ada blocking attempt;
- retry hanya untuk attempt failed yang `retryable=true`.

`SignerIdentityResolver` mengambil NIK dari authenticated user, memvalidasi 16
digit, dan hanya mengekspos bentuk masked ke session/client. Full NIK tidak
diterima dari request final sign.

Authorization matrix per seluruh kombinasi payment/src type masih harus
dibuktikan saat provisioning dan rollout. Policy yang ada belum berarti semua
controller payment telah terintegrasi.

## 8. Signing session ephemeral

Action/service yang tersedia:

- `CreateSigningSession`;
- `ResolveSigningSession`;
- `EphemeralSigningSessionStore`;
- `SignerIdentityResolver`;
- `SigningSessionData` dan `SigningSessionContextData`.

Signing session:

- UUID acak;
- encrypted di cache;
- default TTL 15 menit;
- dimiliki actor user tertentu;
- memuat workflow lock version, step ID, source artifact ID/version/SHA-256,
  real position, effective scope, selected year, resolved signer, dan expiry;
- tidak memuat passphrase, PDF/Base64, atau full NIK;
- direvalidasi terhadap DB dan request context pada show/preview/sign;
- dibuang jika context/artifact/role berubah;
- DELETE session hanya membersihkan cache dan tidak membuat audit/history.

`placement_required=true` boleh terlihat saat prepare, tetapi endpoint sign
saat ini sengaja mengembalikan conflict
`esign.visible_placement_not_ready`. Ini adalah fail-closed sampai Phase 6
selesai.

## 9. Endpoint internal yang sudah terdaftar

Semua route berada pada web middleware sehingga mendapat session authentication
dan CSRF untuk write request. Middleware bersama:

```text
auth
account.accessible
single.device.session
has.position
mfa.verified
active.position
password.fresh
```

Route aktual:

| Method | URI | Route name | Hasil |
|---|---|---|---|
| `POST` | `/esign/internal/signing-sessions` | `esign.internal.signing-sessions.store` | Membuat ephemeral session, HTTP 201. |
| `GET` | `/esign/internal/signing-sessions/{uuid}` | `esign.internal.signing-sessions.show` | Membaca ulang context session aman. |
| `GET` | `/esign/internal/signing-sessions/{uuid}/preview` | `esign.internal.signing-sessions.preview` | Stream exact source artifact. |
| `POST` | `/esign/internal/signing-sessions/{uuid}/sign` | `esign.internal.signing-sessions.sign` | Membuat attempt dan queue job, HTTP 202. |
| `DELETE` | `/esign/internal/signing-sessions/{uuid}` | `esign.internal.signing-sessions.destroy` | Menutup persiapan tanpa audit, HTTP 204. |
| `GET` | `/esign/internal/attempts/{public_id}` | `esign.internal.attempts.show` | Polling status attempt aman. |

Rate limiter aktual:

- prepare: 30 request/menit/user;
- preview: 60 request/menit/user;
- sign: 5 request/menit/user;
- status: 120 request/menit/user.

### Prepare request

```json
{
  "step_public_id": "uuid"
}
```

Response client mencakup:

- `session_id`;
- `signer_name`;
- `masked_nik`;
- `placement_required`;
- `artifact_version`;
- `artifact_sha256`;
- `expires_at`;
- `preview_url`;
- `sign_url`.

### Final sign request

```json
{
  "affirmed": true,
  "idempotency_key": "uuid-created-by-client",
  "preview_sha256": "64-character-sha256",
  "passphrase": "sent-once-over-HTTPS"
}
```

Passphrase tidak boleh dicatat pada docs operasional, log, browser storage,
analytics, atau error report. Field `passphrase` sudah ditambahkan ke daftar
Laravel `dontFlash`.

Response HTTP 202:

```json
{
  "data": {
    "attempt_id": "public-uuid",
    "status": "prepared",
    "status_url": "https://application/esign/internal/attempts/public-uuid"
  }
}
```

### Polling response

Field aman:

- `attempt_id`;
- `attempt_number`;
- `status`;
- `retryable`;
- `requires_reconciliation`;
- `error_code`;
- `result_artifact_id`;
- `started_at`;
- `completed_at`;
- `next_poll_after_ms`.

Polling 2 detik hanya disarankan untuk `prepared`, `signing`, dan
`validating`. `unknown` menghentikan polling normal dan membutuhkan
reconciliation.

## 10. Secret store dan queue signing

`EphemeralSigningSecretStore`:

- mengenkripsi payload menggunakan Laravel Encrypter;
- menyimpan ciphertext di cache private;
- default TTL 30 menit;
- key memakai opaque UUID;
- mengikat secret ke actor user ID;
- worker mengambil dengan operasi one-time `pull`;
- secret dihapus pada terminal/failed handling atau dibiarkan expire jika
  storage gagal dibersihkan;
- passphrase tidak masuk model, event, response, log, atau queue payload.

`PerformEsignAttempt`:

- connection `signatures`;
- queue `signatures`;
- database driver pada konfigurasi saat ini;
- `after_commit=true`;
- timeout 900 detik;
- `retry_after` 960 detik;
- `tries=1`;
- implements `ShouldBeUnique`;
- memakai `WithoutOverlapping` berdasarkan attempt ID;
- payload hanya `attemptId` dan opaque `secretReference`.

Perintah worker yang direncanakan:

```bash
php artisan queue:work signatures --queue=signatures --tries=1 --timeout=900
```

Worker harus dikelola Supervisor/service manager di server. Keberadaan config
tidak membuktikan worker sedang berjalan.

Default cache project saat snapshot adalah `database`. Untuk multi-server,
cache signing session, secret, unique job, dan overlap lock wajib memakai store
shared yang konsisten.

## 11. Orkestrasi `SignDocument`

`SignDocument` melakukan:

1. idempotent replay check;
2. resolve dan revalidate signing session;
3. menolak visible placement yang belum siap;
4. membandingkan preview SHA-256;
5. memilih policy sign pertama atau retry;
6. membaca ulang integritas source PDF;
7. menaruh passphrase pada encrypted TTL secret store;
8. membuat attempt `prepared` dalam transaksi;
9. menulis compatibility `before_signs`;
10. dispatch `PerformEsignAttempt` setelah commit;
11. mengembalikan attempt yang sama bila idempotency key yang sama dikirim
    ulang dengan payload identik.

Request fingerprint mencakup document, workflow, step, source artifact/hash,
signer, dan mode. Idempotency key dengan payload berbeda menghasilkan
conflict.

## 12. Eksekusi `PerformEsignAttemptAction`

Worker melakukan:

1. memastikan attempt masih `prepared`;
2. memuat ulang signer, real position, dan source artifact;
3. memastikan actor=signer, position sama, bukan acting, akun/position masih
   aktif, dan NIK valid;
4. mengambil passphrase satu kali dari secret store;
5. membaca source PDF dan memverifikasi size/SHA-256/header;
6. transition attempt ke `signing`;
7. memanggil `EsignGateway::sign()` di luar transaksi DB;
8. menyimpan metadata response sign yang aman;
9. transition ke `validating`;
10. menulis signed bytes ke staging;
11. memanggil verify pada hasil signed;
12. membuat `after_sign` bila valid atau `failed_output` bila verify gagal;
13. menyimpan provider response, signature, dan certificate read model;
14. transition ke `succeeded` atau `failed`;
15. pada ambiguity sign menggunakan `unknown` dan
    `reconciliation_required`;
16. menulis compatibility ledger/history;
17. menghapus opaque secret reference.

Verify failure setelah output sign diterima tidak melakukan automatic re-sign.
Output disimpan sebagai failed evidence dan attempt dibuat non-retryable agar
tidak menghasilkan signature ganda.

## 13. Compatibility projection yang sudah dibuat

`LegacyEsignLedgerWriter` melakukan dual-write runtime:

- `before_signs` dibuat ketika attempt canonical berhasil dibuat;
- `after_signs` dibuat saat worker menangani outcome sukses, failed, atau
  unknown; outcome unknown diproyeksikan sebagai status legacy gagal sambil
  canonical tetap mempertahankan state `unknown`;
- `document_process` action `TTE` dibuat hanya setelah signature dan verify
  sukses;
- `document.signed_at` diisi hanya ketika workflow canonical selesai;
- `esign_attempt_legacy_links` menghubungkan row legacy dengan attempt,
  artifact, workflow, dan step canonical;
- operasi dibuat idempotent berdasarkan link per attempt/tabel;
- row legacy lama tidak diubah atau dihapus.

Implementasi saat ini menulis **NIK masked**, bukan full NIK, pada compatibility
row baru. Sebelum aktivasi produksi, validasi bahwa consumer laporan eksternal
tidak mengandalkan full NIK pada row baru. Jangan mengubah kembali menjadi full
NIK tanpa review keamanan dan contract consumer.

Writer `document_process` saat ini memilih `document.src_name` lebih dahulu.
Sebelum pilot, bandingkan dengan kontrak laporan lama yang pada sebagian data
menyimpan filename output TTE. Perbedaan ini harus diputuskan sebagai adapter
compatibility, bukan dengan mengubah artifact canonical.

Model/tabel `esign_attempt_signature_properties` sudah tersedia, tetapi writer
runtime untuk placement belum ada karena flow aktif masih invisible dan visible
placement belum diimplementasikan.

Parity report canonical-versus-legacy belum dibuat. Compatibility writer sudah
ada, tetapi belum dibuktikan melalui vertical slice meskipun tabel link sudah
aktif.

## 14. Failure semantics saat ini

| Kondisi | State/aksi |
|---|---|
| Secret expired sebelum vendor call | Attempt `failed`, `retryable=true`; user dapat membuat attempt baru. |
| Kegagalan bisnis deterministik provider | Attempt `failed`; retry mengikuti taxonomy dan policy. |
| Timeout/reset/5xx sign yang outcome-nya ambigu | Attempt `unknown`; step `reconciliation_required`; tidak auto-retry. |
| Verify provider gagal setelah signed output diterima | Output `failed_output`, attempt `failed` non-retryable; perlu pemeriksaan, tidak re-sign otomatis. |
| Verify menghasilkan kesimpulan invalid | Output `failed_output`, attempt `failed` dengan `esign.result_invalid`. |
| Kegagalan lokal sebelum vendor | Attempt ditutup `failed` bila persistence masih tersedia. |
| Kegagalan lokal saat state `signing` | Diarahkan ke `unknown` bila outcome vendor mungkin ambigu. |
| Job framework gagal tidak normal | Secret cleanup dicoba; log aman hanya attempt ID dan exception class. |

Raw exception message, request body, passphrase, full NIK, PDF, dan vendor body
tidak boleh masuk log.

## 15. Config runtime baru

Variable non-secret yang sudah ditambahkan ke `.env.example`:

```text
ESIGN_ARTIFACT_DISK=private
ESIGN_ARTIFACT_ROOT=documents
ESIGN_ARTIFACT_TIMEZONE=Asia/Jakarta
ESIGN_SIGNING_SESSION_CACHE_STORE=
ESIGN_SIGNING_SESSION_TTL_MINUTES=15
SIGNATURE_QUEUE_CONNECTION=signatures
SIGNATURE_QUEUE=signatures
SIGNATURE_QUEUE_RETRY_AFTER_SECONDS=960
SIGNATURE_JOB_TIMEOUT_SECONDS=900
SIGNATURE_SECRET_CACHE_STORE=
SIGNATURE_ASYNC_SECRET_TTL_MINUTES=30
SIGNATURE_MAX_FILE_SIZE_MB=50
```

`BSRE_ESIGN_ENABLED` pada `.env.example` tetap `false` sebagai default aman.
Environment deployment yang hendak mengaktifkan provider harus menetapkan nilai
secara eksplisit dan melewati preflight configuration. Jangan mendokumentasikan
nilai credential, passphrase, atau alamat internal deployment di file ini.

## 16. Verifikasi yang sudah dilakukan pada kode terbaru

- enam route internal terdaftar dengan middleware lengkap;
- config `esign` dan connection `signatures` dapat dibaca Laravel;
- default cache terdeteksi `database`;
- container Laravel dapat me-resolve `SignDocument`,
  `PerformEsignAttemptAction`, secret store, dan policy;
- PHP lint lulus untuk file eSign terkait;
- Laravel Pint lulus;
- `git diff --check` lulus;
- dry-run 13 migration canonical berhasil;
- 13 migration tabel canonical berhasil diterapkan dalam batch 9-21;
- schema aktif memiliki 48 foreign key dan masih kosong;
- dua migration index mapping legacy tetap `Pending` untuk wave terpisah.

Yang **tidak** dilakukan pada implementasi terbaru:

- tidak menjalankan dua migration index mapping pada tabel legacy besar;
- tidak menjalankan Pest/PHPUnit/test suite sesuai instruksi pengguna;
- tidak memanggil sign/verify BSrE production;
- tidak menjalankan endpoint end-to-end dengan canonical database;
- tidak mengaktifkan queue worker server.

## 17. Blocker operasional dan pekerjaan yang belum ada

### Prioritas langsung

1. buat source artifact dan workflow provisioning dari controller payment;
2. aktifkan dedicated queue worker dan shared cache yang sesuai deployment;
3. jalankan controlled invisible vertical slice;
4. implementasikan reconciliation dan stuck recovery;
5. jadwalkan dua migration index mapping legacy sebagai deployment wave
   terpisah setelah capacity/lock review.

### Backend lanjutan

- workflow definition nyata untuk setiap payment/src type;
- integration adapter pada controller payment;
- visible placement DTO/validation/coordinate transform;
- QR dan footer server-side;
- persistence `esign_attempt_signature_properties` dari placement nyata;
- endpoint verification berbasis artifact;
- `/verify/{public_id}`;
- authenticated/guest authorized PDF delivery, enforcement satu flag
  `pdf_watermark_required`, acting override `false`, public watermark, dan audit;
- renderer watermark/COPY-ID, cache derivative 12 jam, lock/atomic publish,
  cleanup, serta verifikasi BSrE asynchronous terhadap original;
- legacy QR URL exact resolver + redirect 302;
- cache invalidation verify;
- health check, metrics, alert, retention, cleanup staging, worker heartbeat;
- reconciliation attempt `unknown`;
- parity report legacy/canonical;
- performance/memory test file historis terbesar.

### Mapping dan rollout

- migration run/item transition service;
- inventory, immutable manifest, dan high-watermark;
- claim/lease/heartbeat/stale recovery;
- document-chain mapper;
- crash-safe historical file copy;
- queue `esign-migration`;
- pause/resume/status/reconcile command;
- mapping dashboard dan manual-review tooling;
- pilot satu payment;
- rollout per payment;
- reporting consumer cutover;
- final backup/restore/parity/decommission folder.

### Frontend

- Svelte/plugin Vite belum dipasang;
- typed API client belum dibuat;
- signing modal belum dibuat;
- visible PDF editor belum dibuat;
- validation modal belum dibuat;
- adapter tombol payment belum dibuat.

Frontend tidak boleh dimulai sebelum Backend Ready Gate lulus dan perubahan
dependency mendapat otorisasi.

## 18. Urutan implementasi berikutnya

```text
1. Canonical source artifact/workflow/step provisioning
2. Queue worker + shared cache + operational preflight
3. Controlled invisible signing vertical slice
4. Reconciliation/stuck/cleanup/observability
5. Legacy mapping index deployment wave
6. Visible QR/footer placement backend
7. Policy delivery PDF + watermark/cache/audit + verify/public route + legacy resolver
8. Backend Ready Gate
9. Svelte/Vite foundation
10. Signing modal
11. Visible editor + validation modal
12. Pilot payment
13. Rollout per payment
14. Resumable legacy mapping + reporting cutover
15. Folder decommission setelah seluruh gate
```

## 19. Larangan untuk agent berikutnya

- Jangan menjalankan migration hanya karena DDL tersedia; lakukan deployment
  review, backup, lock assessment, dan rollback plan.
- Jangan memanggil sign production untuk verifikasi otomatis.
- Jangan membuat/menjalankan test suite tanpa izin eksplisit pengguna.
- Jangan menyimpan/mengirim passphrase di queue payload atau database.
- Jangan mengubah `tries=1` menjadi blind retry.
- Jangan mengubah attempt `unknown` menjadi failed tanpa reconciliation.
- Jangan melewati `esign.visible_placement_not_ready` dengan koordinat tebakan.
- Jangan menerima NIK, file path, workflow state, atau destination path dari
  frontend.
- Jangan menganggap controller payment sudah terintegrasi hanya karena endpoint
  internal tersedia.
- Jangan memasang dependency frontend sebelum Backend Ready Gate.
- Jangan menghapus, truncate, rename, freeze, atau drop tabel compatibility.
- Jangan menghapus/memindah source legacy dari mapping command.
- Jangan menghapus file invalid/zero-byte/orphan; itu evidence.
- Jangan menganggap working tree berarti sudah deployed atau committed.

## 20. Peta file implementasi

| Concern | Lokasi utama |
|---|---|
| Provider boundary | `app/Contracts/Esign`, `app/Services/Esign/BsreClient.php` |
| DTO dan enum | `app/Data/Esign`, `app/Enums/Esign` |
| Canonical models | `app/Models/Esign` |
| Persistence/state | `app/Services/Esign/Persistence` |
| Authorization | `app/Services/Esign/Authorization`, `app/Policies/Esign` |
| Signing session/action | `app/Actions/Esign`, `app/Services/Esign/EphemeralSigningSessionStore.php` |
| Secret store | `app/Services/Esign/EphemeralSigningSecretStore.php` |
| Worker | `app/Jobs/Esign/PerformEsignAttempt.php` |
| Internal HTTP | `app/Http/Controllers/Esign`, `app/Http/Requests/Esign`, `routes/web.php` |
| Artifact storage | `app/Services/Esign/Persistence/DocumentArtifactPersistenceService.php` |
| Compatibility writer | `app/Services/Esign/Persistence/LegacyEsignLedgerWriter.php` |
| Configuration | `config/services.php`, `config/esign.php`, `config/queue.php`, `.env.example` |
| Schema | `database/migrations/2026_09_18_*esign*`, document artifact/signing migrations |
