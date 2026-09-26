# Kondisi Implementasi eSign/TTE Saat Ini

Tanggal snapshot kode dan dokumentasi: **26 September 2026**.

Status: **backend in progress**. Boundary provider, schema/model/state service,
authorization, signing session, private artifact persistence, secret store,
endpoint internal, dan asynchronous signing job sudah berada di working tree.
Sebanyak 13 migration tabel canonical sudah diterapkan pada database lokal;
dua migration index mapping legacy tetap `Pending` untuk deployment wave
terpisah. Boundary upload controller payment umumnya mengantrekan provisioning
source artifact/workflow/step setelah commit; `Payment\LS\SPP::store()` kini
memprovisikan canonical workflow langsung di dalam transaksi vertical slice.
Dedicated worker `signatures` sudah
ditambahkan ke `composer run dev`, dijalankan pada environment lokal, dan satu
upload NPD `GU_SKPD` terkontrol sudah membuktikan artifact, workflow, dua step,
dan event benar-benar terbentuk. Ini belum berarti process manager production
sudah dikonfigurasi. Visible QR/footer, prepared rendition, operation
persistence, worker serial, partial resume, dan aktivasi public ID setelah final
verify tersedia di source; public verification route belum dibuat dan feature
flag multi-operation masih default nonaktif. Workflow hasil provisioning tetap
`draft` dengan step pertama `pending` sampai
signer BP/BPP membuka signing session. Untuk vertical slice **LS SPP jalur BP/BPP**,
lazy activation signer pertama, submit gate, assignment signer berikutnya saat
handoff, dan penahanan step berikutnya setelah TTE sudah diimplementasikan.
Vertical slice tersebut belum dijalankan sukses end-to-end sampai provider.
SPP LS sudah mempunyai direct
canonical source upload, canonical replacement dengan parent/current version
chain, draft-workflow rebind/revision cycle, serta authenticated content/download
route pada working tree. Formula `storage_path_sha256` pada persistence dan integrity service sudah
disatukan melalui helper shared; route tersebut belum lulus acceptance runtime.
Provisioning file legacy yang hanya mempunyai nama fisik UUID tidak lagi
mengisi UUID tersebut sebagai `original_name`; nilainya disimpan sebagai
`legacy_stored_name`, sedangkan upload runtime memakai nama client sebenarnya.

Desain visible terbaru sudah berada di source backend: editor membuka artifact
melalui binary stream, footer editable hanya sebelum TTE pertama, prepared
rendition exact, dan satu signer dapat mempunyai beberapa QR yang dieksekusi
serial dalam satu attempt. Feature flag runtime masih default `false`, vertical
slice operasional belum dijalankan, dan frontend Svelte F0-F13 sudah tersedia
di source sampai progress authoritative, partial resume, terminal result, dan
modal validasi canonical authenticated.
Recovery attempt setelah full page reload masih menunggu endpoint discovery
backend. Target lengkap berada di
`ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`.

Dokumen ini adalah handoff kondisi kode aktual. Untuk keputusan bisnis dan
target final tetap baca:

1. `README.md`;
2. `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`;
3. `PDF_DELIVERY_WATERMARK_IMPLEMENTATION_PLAN.md` dan
   `PDF_DELIVERY_WATERMARK_IMPLEMENTATION_APPENDICES.md` bila menyentuh secure
   viewer, delivery session, watermark, COPY-ID, access audit, atau persistent
   verification summary;
4. `ESIGN_V2_CONTRACT_AND_BACKEND.md`;
5. `ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`;
6. `ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md`;
7. `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`;
8. `ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md`;
9. `ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`;
10. `ESIGN_V2_IMPLEMENTATION_PLAN.md`.

Jika ada perbedaan antara tracker lama dan dokumen ini, verifikasi kode,
migration status, dan route aktual. Jangan menganggap komponen sudah deployed
hanya karena class-nya tersedia di repository.

## 1. Ringkasan posisi phase

| Phase | Kondisi aktual | Catatan |
|---:|---|---|
| 0 | Sebagian selesai | Containment lokal selesai; rotasi/revoke credential eksternal tetap tanggung jawab pemilik/deployment. |
| 1 | Minimum selesai | Invisible NIK+passphrase satu PDF dan verify minimum pernah dibuktikan; visible coordinate, limit, timeout matrix, encrypted PDF, dan multi-file belum final. |
| 2 | Kode selesai untuk scope awal | `EsignGateway`, `BsreClient`, DTO, mapper, payload invisible satu file, error taxonomy, dan config tersedia. |
| 3 | Schema aktif, provisioning runtime terbukti lokal | Sebanyak 13 tabel canonical, model, enum cast, transition/persistence, artifact storage, provider response, event, compatibility writer, dan job provisioning idempotent tersedia. Worker lokal dan satu upload terkontrol lulus; dua migration index mapping legacy, production process manager, mapping runner, dan reconciliation belum selesai. |
| 4 | Kode LS SPP tersedia, belum lulus acceptance | Policy, authorization service, signer resolver, encrypted ephemeral session, context revalidation, private preview, definition registry, lazy activation BP/BPP, dan assignment PPTK/PA/KPA saat handoff tersedia. Workflow upload sengaja tetap draft sampai signing session pertama. |
| 5 | Kode vertical slice dan multi-operation tersedia, belum lulus acceptance | Endpoint internal, encrypted secret TTL, `202 Accepted`, operation persistence, queue worker serial, final verify/promotion, partial resume, polling, aggregate legacy projection, submit gate, dan handoff canonical LS SPP tersedia. Feature flag multi-operation masih default nonaktif dan sign canonical belum diuji end-to-end. |
| 6 | Sebagian untuk LS SPP/visible backend | Authenticated current-artifact content/download, prepared rendition, QR authoritative, footer renderer, progress, resume, serta endpoint/modal validasi artifact tersedia. Formula hash path sudah konsisten, tetapi acceptance runtime belum dilakukan. Frontend F0-F13 tersedia di source; full-page active-attempt recovery masih menunggu endpoint backend. General delivery policy watermark/COPY-ID/audit, public verify route, guest delivery, dan legacy QR resolver belum dibuat. |
| 7 | Belum lulus | Backend Ready Gate masih terhalang production process manager/shared cache, visible placement, acceptance end-to-end LS SPP, deployment index legacy, reconciliation, observability, performance proof, credential rotation, dan test yang diizinkan. |
| 8-10 | Sebagian di source | Dependency dan frontend Svelte/Vite sampai modal validasi canonical F13 sudah dipasang; Backend Ready Gate, aktivasi feature flag, acceptance manual, dan rollout payment belum dilakukan. |
| 11 | Belum dijalankan | Target pilot backend dipilih: LS SPP jalur BP -> PPTK -> PA. Belum diaktifkan untuk layanan operasional. |
| 12 | Belum | Rollout, mapping legacy resumable, reporting cutover, dan decommission belum berjalan. |

Kesimpulan posisi: dari sisi source code pekerjaan sudah mencapai **Phase 5
parsial**. Dari sisi runtime lokal, provisioning Phase 3 sudah terbukti; dari
sisi deployment production dan workflow yang benar-benar signable pekerjaan
masih berada pada **Phase 4 menuju Phase 5**.

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
- Pada mayoritas workflow payment, signer aktif melakukan TTE lebih dahulu,
  lalu melakukan `SUBMIT` untuk handoff ke posisi berikutnya. Jangan memakai
  asumsi umum `upload -> submit -> TTE`. Pengecualian preparer-only, verify,
  routing, dan SP2D harus mengikuti matrix/controller serta histori
  `document_process`.
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
  exact original setelah authorization. Seluruh posisi existing dan posisi baru
  default `false`; nilai `true` hanya diaktifkan manual melalui Management User.
  Guest public selalu watermark. Admin Super saat acting like diperlakukan
  sebagai `false`; posisi bisnis nyata miliknya mengikuti flag posisi tersebut.
  Tahap P0 penguncian kontrak dokumentasi selesai 26 September 2026, sedangkan
  implementasi runtime belum dimulai. Urutan operasional, schema target, kontrak
  endpoint, transition, queue, frontend general viewer, rollout, dan acceptance
  manual sudah dikunci di `PDF_DELIVERY_WATERMARK_IMPLEMENTATION_PLAN.md` serta
  `PDF_DELIVERY_WATERMARK_IMPLEMENTATION_APPENDICES.md`.
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
- success mempromosikan result artifact menjadi current dan menyelesaikan step;
- bila masih ada step berikutnya, step tersebut tetap `pending` dan workflow
  tetap `active` sampai handoff legacy/canonical menetapkan signer serta
  mengaktifkannya; hanya step terakhir yang menyelesaikan workflow;
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

Kontrak `storage_path_sha256` memakai SHA-256 dari
`storage_disk:file_path`. Persistence dan integrity service sekarang memanggil
`DocumentArtifactStoragePath::checksum()` yang sama. Query read-only membuktikan
4/4 artifact lokal cocok dengan formula tersebut dan 0 memakai formula path
saja, sehingga tidak diperlukan migrasi hash untuk data lokal saat snapshot.

Private preview saat ini memakai streamed inline response exact source dengan
`no-store` dan tidak membocorkan physical path. Endpoint aktual ini **belum**
menegakkan `pdf_watermark_required`; sebelum rollout ia harus dipindahkan ke
delivery policy canonical. Backend sign tetap membaca exact original secara
internal meskipun preview user berupa derivative watermark.

Yang sudah tersedia untuk upload baru:

- `Payment\LS\SPP::store()` menulis file utama SPP langsung sebagai source
  artifact private lalu memanggil `ProvisionCanonicalDocumentAction` di dalam
  transaksi yang sama; ia tidak membuat file baru di `public/File_SPP`;
- `Payment\LS\SPP::update()` membuat versi `before_sign` baru untuk replacement
  file utama, mempertahankan parent artifact, memindahkan current pointer,
  mengikat ulang workflow draft yang masih bersih, atau membuat revision cycle
  setelah workflow ditolak;
- create dan update SPP memakai helper transaksi yang sama untuk mengunci sumber
  pagu, menghitung ulang realisasi, dan menolak commit bila nominal melampaui
  sisa pagu setelah lock;
- route `document.ls.spp.content` dan `document.ls.spp.download` memilih tepat
  satu current artifact `before_sign`/`after_sign`, menjalankan policy, dan
  melakukan integrity-checked stream;
- upload dan replacement SPJ LS langsung membentuk current artifact private;
  route `document.ls.spj.content` dan `document.ls.spj.download` memakai
  resolver, policy, serta integrity-checked stream yang sama;
- upload dan replacement BMD LS langsung membentuk current artifact private;
  route `document.ls.bmd.content` dan `document.ls.bmd.download` memakai
  resolver, policy, serta integrity-checked stream yang sama;
- rollback create SPP dapat membersihkan staging/final source yang belum
  mempunyai row artifact tanpa menghapus artifact persisten;
- seluruh 52 pemanggilan upload controller payment melewati
  `DocumentHistoryService::upload()` dan mengantrekan
  `ProvisionCanonicalDocument` setelah transaksi commit;
- source PDF disalin dengan stream ke layout canonical, divalidasi header PDF,
  ukuran, dan SHA-256, tanpa menghapus/memindah sumber legacy;
- definition registry versi 1 membentuk urutan signer untuk UP, GU_SKPD,
  GU_UK, LS, LS_GAJI, TU, dan KKPD;
- workflow dibuat `draft`, step dibuat sequential `pending`, assignment exact
  dari uploader/`users_to` dipakai bila role cocok, dan sisanya disimpan
  `unresolved`/`partial` tanpa tebakan;
- `SPJ` dan `BMD` LS sudah direct private upload tanpa workflow TTE dan memakai
  authenticated delivery route dengan cutover per row;
- Billing LS tetap berada pada field row SPJ dan tidak membuat row atau
  `src_type=BILLING`. Upload/replacement baru memakai artifact type `attachment`
  di private storage serta route `document.ls.billing.content`/`download`.
  `/File_Billing` hanya menjadi fallback data historis sebelum backfill;
- job memakai queue `signatures`, unique per document, row locking, retry
  terbatas, dan dapat diulang secara idempotent.

Yang belum tersedia/dibuktikan:

- acceptance runtime lengkap untuk lazy activation BP/BPP, TTE asynchronous,
  submit gate, assignment PPTK, assignment PA/KPA, dan handoff terakhir;
- sinkronisasi assignment/handoff canonical di luar vertical slice LS SPP;
- sinkronisasi checkpoint preparer-only, verify, routing, dan SP2D yang tidak
  boleh dipaksa mengikuti pola generik;
- historical file mapper/copy runner;
- scheduled cleanup staging;
- orphan/stuck artifact reconciliation;
- general public/authenticated artifact delivery untuk payment/type selain
  vertical slice LS SPP;
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

Definition matrix payment/src type sudah dikodekan pada registry, tetapi parity
authorization dan assignment setiap cabang tetap harus dibuktikan saat submit,
pilot, dan rollout. Hook upload tidak berarti workflow sudah boleh diaktifkan.

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

`placement_required=true` memakai kontrak visible. Sign visible hanya dapat
berjalan bila ada prepared revision/hash yang valid dan feature flag
`SIGNATURE_MULTI_OPERATION_ENABLED=true`. Default flag tetap `false`, sehingga
deployment yang belum menjalankan preflight gagal tertutup dengan
`esign.visible_worker_not_ready`.

Session visible sudah menyediakan authorized binary preview URL, signature
state server-side, ordered QR placements, footer capability/default/whitelist,
prepared rendition revision/hash, QR image authoritative, serta batas
placement. Browser tidak mengunggah atau mengirim Base64 PDF.

Runtime canonical sudah memakai:

- `esign_signature_operations` dan progress counter attempt;
- status `partially_signed` serta artifact type `intermediate_sign`;
- snapshot footer `document_artifact_decorations` dan placement per halaman;
- foreign key operation pada provider response dan attempt event;
- worker serial/checkpoint-aware, partial resume dengan passphrase baru, final
  verify/promotion, dan aktivasi public ID setelah sukses.

Yang belum tersedia adalah resolver publik `/verify/{public_id}`, modal
frontend, endpoint validasi canonical final, reconciliation/observability, dan
acceptance vertical slice operasional.

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
| `POST` | `/esign/internal/signing-sessions/{uuid}/renditions` | `esign.internal.signing-sessions.renditions.store` | Validasi plan dan membuat prepared rendition, HTTP 201. |
| `GET` | `/esign/internal/signing-sessions/{uuid}/renditions/{revision}/preview` | `esign.internal.signing-sessions.renditions.preview` | Stream exact prepared PDF private. |
| `GET` | `/esign/internal/signing-sessions/{uuid}/renditions/{revision}/operations/{index}/qr` | `esign.internal.signing-sessions.renditions.qr` | Stream QR PNG authoritative per operasi. |
| `POST` | `/esign/internal/signing-sessions/{uuid}/sign` | `esign.internal.signing-sessions.sign` | Membuat attempt dan queue job, HTTP 202. |
| `DELETE` | `/esign/internal/signing-sessions/{uuid}` | `esign.internal.signing-sessions.destroy` | Menutup persiapan tanpa audit, HTTP 204. |
| `GET` | `/esign/internal/attempts/{public_id}` | `esign.internal.attempts.show` | Polling status attempt aman. |
| `POST` | `/esign/internal/attempts/{public_id}/resume` | `esign.internal.attempts.resume` | Menyimpan secret baru dan melanjutkan dari checkpoint, HTTP 202. |

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

Perintah worker lokal yang sudah dijalankan dan ditambahkan ke script
`composer run dev`:

```bash
php artisan queue:work signatures --queue=signatures --sleep=1 --timeout=900 --memory=256 --no-interaction
```

Worker lokal terbukti mengambil job `ProvisionCanonicalDocument`. Opsi
`--tries` sengaja tidak dioverride sehingga batas pada class job tetap berlaku:
provisioning `tries=5`, sedangkan signing `tries=1`. Worker production tetap
harus dikelola Supervisor/systemd/service manager sesuai OS deployment;
`composer run dev` dan PID lokal bukan mekanisme availability production.

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

Model/tabel `esign_attempt_signature_properties` dan writer runtime placement
sudah tersedia melalui persistence visible attempt. Flow produksi tetap belum
aktif karena feature flag default `false` dan vertical slice belum diterima.

Parity report canonical-versus-legacy belum dibuat. Compatibility writer sudah
ada, tetapi belum dibuktikan melalui vertical slice meskipun tabel link sudah
aktif.

## 13A. Vertical slice canonical LS SPP

Scope implementasi saat ini hanya dokumen induk `payment_type=LS` dan
`src_type=SPP`, dengan dua definisi urutan wajib:

```text
jalur BP  : BP  -> PPTK -> PA
jalur BPP : BPP -> PPTK -> KPA
```

Lifecycle yang wajib dipertahankan:

| Pemicu | Workflow | Step terkait | Dampak legacy/canonical |
|---|---|---|---|
| Upload SPP | `draft` | BP/BPP assigned, seluruh step `pending` | Artifact source dan workflow dibuat; dokumen masih dapat diedit. |
| BP/BPP nyata membuka signing session | `active` | Step pertama `active` | Lazy activation; Admin Super acting tidak boleh mengaktifkan atau TTE. |
| TTE BP/BPP sukses | `active` | BP/BPP `completed`, PPTK tetap `pending` | Result artifact menjadi current; tidak ada auto-activation step berikutnya. |
| BP/BPP submit ke PPTK | `active` | PPTK di-assign dan menjadi `active` | Submit gate membuktikan TTE sukses, lalu canonical dan legacy diubah atomik. |
| TTE PPTK sukses | `active` | PPTK `completed`, PA/KPA tetap `pending` | Menunggu handoff PPTK; signer kepala belum boleh TTE. |
| PPTK submit | `active` | PA atau KPA di-assign dan menjadi `active` | Jalur BP memilih PA; jalur BPP memilih KPA. Harus ada tepat satu posisi aktif yang valid. |
| TTE PA/KPA sukses | `completed` | Step terakhir `completed` | Workflow selesai dan current artifact adalah hasil signer terakhir. |
| PA/KPA submit kembali ke BP/BPP | `completed` | Tidak membuat step canonical baru | Handoff administratif legacy, bukan tanda tangan kedua BP/BPP. |
| BP/BPP submit final ke PPK-SKPD | `completed` | Semua step harus mempunyai proof sukses | Gate final memeriksa workflow lengkap sebelum `assigned_to` berubah. |

Komponen implementasi:

- `LsSppWorkflowHandoffService` menangani lazy activation, assignment, dan
  aktivasi step pada handoff;
- `LsSppSubmitGate` memblokir submit bila urutan, assignment, attempt,
  artifact hasil, projection link, status legacy, atau current artifact tidak
  konsisten;
- `CreateSigningSession` menjalankan lazy activation sebelum authorization
  final lalu tetap melakukan seluruh pemeriksaan signer identity;
- `EsignAttemptPersistenceService` hanya menyelesaikan step yang berhasil dan
  tidak lagi mengaktifkan step berikutnya;
- `LsSppCompatibilityProjector` menulis status legacy serta event `TTE` secara
  idempotent dan memakai urutan lock document terlebih dahulu;
- `Payment\LS\SPP::submit_pptk()` dan `submit()` menjalankan gate, assignment,
  perubahan `document.submit`/`assigned_to`/`users_to`, serta histori `SUBMIT`
  di dalam transaksi yang sama.

Aturan compatibility fallback:

- dokumen murni legacy tanpa workflow **dan** tanpa artifact canonical tetap
  mengikuti mekanisme lama;
- bila artifact canonical sudah ada tetapi workflow hilang, operasi gagal
  tertutup dengan HTTP `409`; tidak boleh diam-diam kembali ke legacy;
- error gate memakai `LsSppSubmitGateException` dengan `reason_code` terstruktur;
- kegagalan gate atau assignment me-roll back perubahan canonical, projection
  `document`, dan `document_process` bersama-sama;
- assignment PPTK divalidasi terhadap posisi canonical aktif, role, unit, dan
  instansi. Assignment PA/KPA harus unik; nol atau lebih dari satu kandidat
  menghasilkan `409`, bukan pemilihan acak;
- event `step_assigned` menyimpan assignment lama/baru, source artifact, aktor
  efektif, aktor nyata, dan konteks acting untuk audit.

Kondisi data lokal saat snapshot: dua workflow LS SPP yang telah ada masih
`draft`; keduanya akan diaktifkan secara lazy saat signer pertama yang tepat
membuka session. Pemeriksaan read-only menemukan tepat satu kandidat PA pada
unit masing-masing. Tidak ada aktivasi permanen atau TTE produksi yang dilakukan
saat verifikasi implementasi ini.

Tahap ini tidak menambah migration. Event `step_assigned` dapat disimpan karena
kolom `document_signing_workflow_events.event_type` bertipe string; enum PHP
hanya menambah nilai domain yang dikenali aplikasi.

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
- schema aktif memiliki 48 foreign key; sebelum controlled proof seluruh tabel
  canonical masih kosong, lalu proof membuat satu artifact/workflow/event dan
  dua step;
- dua migration index mapping legacy tetap `Pending` untuk wave terpisah.
- dedicated worker lokal `signatures` berhasil hidup dengan connection dan
  queue `signatures`, timeout 900 detik, serta `retry_after` 960 detik;
- controlled upload NPD `GU_SKPD` melalui method `store()` controller asli
  menghasilkan HTTP 200, record `document`, dan event legacy `UPLOAD`;
- job provisioning diselesaikan worker dalam sekitar 900 ms tanpa failed job;
- source artifact private tersedia, berukuran 102106 byte, dan SHA-256 file
  canonical sama dengan file upload legacy serta metadata database;
- workflow NPD `PPTK -> PA`, dua step sequential, dan event
  `workflow_created` benar-benar terbentuk;
- PHP lint, Laravel Pint, service-container resolution, route inspection, dan
  `git diff --check` lulus setelah submit gate/handoff LS SPP ditambahkan;
- query read-only menemukan dua workflow LS SPP berstatus `draft` dengan step
  pertama BP assigned dan step berikutnya unresolved sebagaimana desain;
- kedua unit workflow LS SPP tersebut masing-masing mempunyai tepat satu
  kandidat PA aktif;
- rollback proof untuk lazy activation mengubah workflow/step menjadi
  `active` di dalam transaksi, lalu mengembalikannya ke `draft`/`pending`
  setelah rollback. Bukti ini tidak meninggalkan mutasi database permanen.

Yang **tidak** dilakukan pada implementasi terbaru:

- tidak menjalankan dua migration index mapping pada tabel legacy besar;
- tidak menjalankan Pest/PHPUnit/test suite sesuai instruksi pengguna;
- tidak memanggil sign/verify BSrE production;
- tidak menjalankan signing session/TTE canonical end-to-end;
- tidak menjalankan submit/handoff LS SPP secara permanen;
- tidak menguji middleware/form melalui browser karena browser automation dan
  sesi login tidak tersedia; controlled upload memanggil controller asli dengan
  `Request`, `UploadedFile`, posisi PPTK aktif, transaksi, dan service asli;
- tidak memasang process manager worker pada server production.

### Bukti controlled provisioning 22 September 2026

Data ini adalah **proof record**, bukan paket layanan riil dan tidak boleh
dipakai sebagai baseline ID lintas environment:

| Entitas | Bukti lokal |
|---|---|
| Dokumen | ID `482571`, nomor `CANONICAL-PROOF-20260922-011140-G9W1BB`, `GU_SKPD/NPD` |
| Legacy upload event | `document_process` ID `2302194`, actor position `352`, jabatan PPTK |
| Source artifact | ID `1`, public ID `bf9ae169-298b-409c-8429-79ecce2dbff6`, tipe `before_sign`, version 1/current |
| Artifact path | `documents/source/2026/09/bf/bf9ae169-298b-409c-8429-79ecce2dbff6.pdf` pada disk private |
| SHA-256 | `436f1f3bce1cfa1f591d78b0a6de5c6f3de24a086d931efc36ca1490e17f70f1` |
| Workflow | ID `1`, public ID `abbcc8ee-0671-4227-afdb-df25174f4b0d`, status `draft`, assignment `partial` |
| Step 1 | PPTK, assigned user/position `352`, status `pending`, source artifact ID `1` |
| Step 2 | PA, assignment unresolved, status `pending` |
| Event | ID `1`, `workflow_created`, actor bukan acting |
| Queue akhir | queue `signatures` kosong, `failed_jobs=0`, worker error log kosong |

File legacy upload tetap ada karena controller dan UI lama masih bergantung
pada `document.src_name`. File canonical adalah copy immutable pada private
storage. Jangan menghapus salah satunya hanya untuk membersihkan proof tanpa
keputusan eksplisit dan pemeriksaan dependency.

## 16A. Implementasi contract proof visible per 24 September 2026

Tahap pertama backend visible contract sudah **lulus terhadap provider** melalui
run live `033474f4-287f-408b-9f54-88d963d1880d`. Implementasi saat ini:

- command `esign:prove-visible-contract` memakai sample default
  `public/sample belum tte.pdf` atau path PDF eksplisit;
- tanpa `--live`, command hanya menjalankan preflight lokal, membuat dua QR
  default pada halaman 1 dan 2, dan tidak menghubungi BSrE;
- mode live dikunci oleh `SIGNATURE_CONTRACT_PROOF_ENABLED=false`, wajib dari
  terminal interaktif, meminta NIK/passphrase melalui hidden prompt, dan
  meminta confirmation phrase sesuai jumlah operasi;
- NIK/passphrase tidak tersedia sebagai CLI option dan tidak ditulis ke report,
  log, database, cache, maupun queue;
- setiap QR diproses sebagai **satu request sign visible + satu PDF** secara
  serial; output operasi ke-i menjadi input operasi ke-i+1;
- baseline diverifikasi sebelum sign, lalu setiap output diverifikasi dan wajib
  menambah tepat satu signature; pelanggaran invariant menghentikan rangkaian;
- QR, setiap intermediate PDF, final PDF, dan report tersanitasi disimpan pada
  disk private di `esign-contract-proofs/YYYY/MM/{run_uuid}`;
- runtime signing visible tersedia di source, tetapi feature flag
  `SIGNATURE_MULTI_OPERATION_ENABLED` tetap default `false` sampai preflight dan
  acceptance operasional selesai.

Kontrak payload internal sekarang mendukung `INVISIBLE` dan `VISIBLE`, tetapi
menolak lebih dari satu `signatureProperties` dalam satu provider request.
Pembatasan ini dipertahankan karena strategi satu request per QR sudah terbukti
berhasil dan memberi checkpoint yang deterministik.

Hasil tersanitasi run live:

| Pemeriksaan | Hasil |
|---|---|
| Baseline source | `NO_SIGNATURE`, 0 signature, verify 388 ms |
| Operasi 1 | HTTP 200, sign 869 ms, verify 605 ms, menjadi 1 signature `VALID` |
| Operasi 2 | HTTP 200, sign 703 ms, verify 879 ms, menjadi 2 signature `VALID` |
| Final PDF | 243.583 byte, SHA-256 `234b96617bfc375e246fc7aa971f414e387c98a5dcc6f1eb571c8252d9073866` |
| Visual | QR terlihat pada halaman 1 dan 2; origin provider terbukti `top_left` |

Koordinat proof `(36,36,100,100)` menimpa konten sample pada kiri atas. Itu
bukan default UI final; Stage 3 wajib menerapkan safe area, collision rule, dan
preview exact sebelum submit.

Perintah operator:

```text
php artisan esign:prove-visible-contract --no-interaction
php artisan esign:prove-visible-contract --live
```

Mode live baru boleh dijalankan setelah environment operator menetapkan
`SIGNATURE_CONTRACT_PROOF_ENABLED=true` dan config cache direfresh. Credential
harus diketik saat prompt; jangan menaruhnya dalam command history.

## 16B. Implementasi schema multi-operation per 24 September 2026

Tahap 2 sudah diterapkan secara additive pada batch migration 22 sampai 26:

- `esign_signature_operations` menyimpan satu operasi provider per QR,
  placement property, input/output artifact dan hash, status, correlation,
  error aman, timestamp checkpoint, serta waktu aktivasi public ID;
- `esign_attempts` mempunyai `planned_signature_count`,
  `completed_signature_count`, dan `current_signature_index`;
- `document_artifact_decorations` menyimpan snapshot immutable teks/footer,
  font whitelist key, ukuran/style, renderer version, dan configuration hash;
- `document_artifact_decoration_placements` menyimpan geometry per halaman,
  dimensi halaman, rotation, dan coordinate origin;
- `esign_provider_responses` dan `esign_attempt_events` dapat menunjuk langsung
  ke operasi signature untuk audit per QR;
- enum/model/cast/relasi untuk operasi, decoration, `partially_signed`, dan
  `intermediate_sign` sudah tersedia;
- status endpoint attempt mengembalikan progress dan flag
  `requires_passphrase` untuk state partial;
- jalur invisible lama tetap kompatibel: attempt tanpa operation row memakai
  default satu signature dan counter diselesaikan saat sukses.

Semantik counter adalah 0-based: `current_signature_index` menunjuk operasi
pertama yang belum selesai dan boleh sama dengan `planned_signature_count`
setelah seluruh operasi selesai. Operation `completed` immutable; hanya waktu
aktivasi public ID yang boleh diisi sekali setelah final verify.

Migration dijalankan memakai `--path` satu per satu. Dua migration indeks
legacy `2026_09_18_034231` dan `2026_09_18_034233` tetap pending dan tidak ikut
dijalankan.

## 16C. Placement/footer dan prepared rendition per 26 September 2026

Tahap 3 backend sudah diimplementasikan tanpa membuka guard final sign visible:

- signing session sekarang menyimpan snapshot geometri tiap halaman,
  `signature_state`, dan jumlah signature canonical terverifikasi;
- geometri authoritative dibaca server-side memakai `pdfinfo`, bukan ukuran DOM
  atau klaim frontend;
- kontrak editor mengembalikan unit point, origin `top_left`, safe margin,
  batas ukuran QR, maksimal lima operasi, font whitelist, style footer, dan
  default placement footer untuk setiap halaman;
- validator rendition menegakkan operation index kontinu 0-based, kecocokan
  page width/height/rotation, bounds, margin, ukuran QR, footer pada halaman
  terpilih tanpa duplikasi, font/size/style, text fit, serta collision
  QR-vs-QR dan QR-vs-footer;
- halaman dengan rotation selain 0 derajat ditolak fail-closed karena proof
  provider saat ini baru membuktikan rotation 0;
- source `before_sign` yang konsisten belum mempunyai signature wajib menerima
  footer, sedangkan source `after_sign`/`intermediate_sign` terverifikasi tidak
  boleh menerima footer baru;
- `POST /esign/internal/signing-sessions/{session}/renditions` merender exact
  prepared PDF; `GET .../renditions/{revision}/preview` men-stream binary PDF
  private setelah session, actor, source artifact, hash, dan revision diperiksa;
- QR PNG authoritative dibuat backend untuk setiap operasi dan hanya dapat
  diambil dari endpoint private
  `GET .../renditions/{revision}/operations/{operation_index}/qr`; frontend
  menampilkan PNG yang sama; persistence visible mempromosikan byte yang sama
  sebagai visual input provider, bukan membuat ulang QR di browser;
- setiap prepare menghasilkan ordered placement, opaque
  `verification_public_id`, URL verify HTTPS, configuration hash footer,
  request fingerprint, UUID revision, SHA-256 PDF, dan expiry yang tidak
  melampaui signing session;
- hanya satu prepared revision aktif per session. Prepare baru menghapus file
  revision lama setelah revision baru durable; tutup session menghapusnya
  tanpa attempt/event/audit bisnis;
- cleanup langsung memverifikasi ownership actor; UUID session milik actor lain
  tidak dapat dipakai untuk menghapus cache atau file prepared rendition;
- prepared file disimpan privat di struktur tanggal/session/revision dan
  dilindungi metadata cache terenkripsi serta atomic cache lock;
- command `esign:cleanup-prepared-renditions` default dry-run; opsi `--delete`
  dijadwalkan hourly untuk membersihkan file expired berdasarkan retention;
- renderer memakai core PDF font `Helvetica`, `Times`, atau `Courier`, style
  bold/italic/underline, teks rata tengah, placement selected-pages, overlay
  `qpdf`, dan validasi output `qpdf --check`;
- smoke render lokal terhadap `public/sample belum tte.pdf` menghasilkan PDF
  valid dua halaman, SHA source dan prepared berbeda, serta ukuran prepared
  102.296 byte. Tidak ada panggilan sign provider atau record database dibuat.

Environment server wajib menyediakan binary `pdfinfo` (Poppler) dan `qpdf`,
atau mengatur `SIGNATURE_PDFINFO_BINARY`/`SIGNATURE_QPDF_BINARY` ke path absolut.
Ketiadaan binary menghentikan prepare rendition; tidak ada fallback browser.
Snapshot immutable `document_artifact_decorations` ditulis saat prepared
rendition dipromosikan menjadi artifact canonical. `SignDocument` menerima
kontrak visible hanya ketika prepared revision/hash valid dan feature flag
multi-operation aktif; selain itu proses gagal tertutup.

## 16D. Persistence, worker serial, dan resume per 24 September 2026

Tahap backend visible lanjutan sudah tersedia pada source:

- `VisibleEsignAttemptPersistenceService` mempromosikan prepared rendition,
  snapshot property/operation/footer, dan menulis before ledger satu kali;
- `PerformEsignAttemptAction` memproses operation secara serial, menyimpan
  intermediate artifact/checkpoint, berhenti pada outcome ambigu, melakukan
  final verification/promotion, dan mengaktifkan public ID hanya setelah sukses;
- `EsignAttemptPersistenceService` menjaga counter, status operasi, immutable
  checkpoint, finalisasi, dan aggregate compatibility semantics;
- `ResumeEsignAttempt` serta endpoint resume meminta passphrase baru dan
  melanjutkan dari completed prefix tanpa mengulang operasi sukses;
- status endpoint mengirim planned/completed/current index, status per operasi,
  `requires_passphrase`, `resume_url`, dan `requires_reconciliation`;
- QR authoritative dapat memakai profile berlogo Malang dan byte visual yang
  dipersistensikan dipakai sebagai input provider.

Keberadaan source bukan bukti deployment. Feature flag masih default `false`,
public verification resolver belum ada, dan controlled vertical slice belum
dijalankan.

## 16E. Snapshot frontend dan failure semantics per 26 September 2026

Frontend F0-F13 sudah terhubung di source untuk vertical slice LS SPP, dengan
batas berikut:

- modal memakai hampir seluruh viewport; desktop digeser secara terukur untuk
  mengimbangi sidebar aplikasi, sedangkan tablet/mobile menjadi fullscreen;
- header, stepper, dan footer modal dipadatkan agar workspace PDF lebih luas;
- desktop mempertahankan thumbnail dan inspector sebagai panel tetap dengan
  scroll internal; hanya workspace/page viewport yang menjadi area scroll PDF;
- pada viewport di bawah 1200 px, thumbnail dan inspector saat ini disembunyikan
  agar workspace tidak berantakan. Drawer/offcanvas belum dibuat;
- seluruh halaman PDF berada dalam document flow dan canvas dirender lazy saat
  mendekati viewport. Ini menggantikan implementasi awal tiga-page window yang
  menyebabkan dokumen delapan halaman terlihat seperti hanya dua/tiga halaman;
- halaman dapat diaktifkan langsung dari canvas workspace, thumbnail, selector,
  atau tombol navigasi. Aktivasi halaman yang sudah terlihat tidak memaksa
  scroll kembali ke bagian atas halaman;
- toolbar menempatkan navigasi dan indikator `aktif/total` di kiri,
  `Reset Posisi`/`Tambah QR` di tengah, dan zoom di kanan;
- QR baru ditempatkan di pusat area halaman yang paling terlihat pada viewport,
  bukan pusat seluruh PDF. QR mempunyai resize dan tombol hapus langsung pada
  overlay, selain kontrol inspector;
- footer unsigned pertama kali tetap dibuat dari default backend pada seluruh
  halaman. Setiap placement dapat digeser, di-resize melalui empat sudut, dan
  dihapus langsung dari overlay sehingga page scope dapat menjadi selected
  pages. Teks rata tengah; default font 7,5 pt; perubahan ukuran 0,1 pt;
- backend/persistence menyimpan footer sebagai `SelectedPages` dan hanya menulis
  decoration/footer-applied bila ada placement. Source saat ini juga menerima
  array placement kosong. Keputusan apakah pengguna boleh menghapus **semua**
  footer belum dikunci dan wajib dipastikan sebelum pilot F14;
- prepared renderer dan preview memakai placement/box yang sama dengan editor,
  line wrapping dan estimasi lebar font yang sama, sehingga posisi footer tidak
  boleh berubah antara editor dan Konfirmasi;
- Konfirmasi tetap satu tahap berisi prepared preview, informasi dokumen,
  ringkasan operasi/footer, dan passphrase; tidak ada modal kedua dan tidak ada
  checkbox afirmasi;
- ketika attempt sukses, footer modal disembunyikan. Ringkasan sukses dan tombol
  `Selesai` berada di body tengah; tombol tersebut menutup modal dan memicu
  completion event;
- vendor code BSrE `2031` dipetakan menjadi invalid passphrase yang retryable.
  Local failure sebelum request provider tercatat sebagai
  `esign.local_pre_provider_processing_failed` dan dapat dicoba ulang; failure
  setelah provider dispatch tetap `unknown`/reconciliation untuk mencegah TTE
  ganda. `esign.local_processing_failed` tidak boleh dipaksa retry dari browser;
- endpoint 422 prepared rendition tetap fail-closed dan frontend menampilkan
  normalized field error; frontend tidak mengubah plan atau retry otomatis;
- modal validasi F13 memakai exact private artifact dan tidak upload ulang Blob,
  tetapi action canonical masih LS SPP. Tahap P0 general secure PDF
  viewer/watermark telah selesai sebagai kontrak dokumentasi; P1-P18 runtime
  belum diimplementasikan.

Feature flag tetap `false`. Perubahan source/UI di atas belum menggantikan
acceptance manual F14, production worker/process manager, shared cache,
observability, dan controlled end-to-end provider proof.

## 17. Blocker operasional dan pekerjaan yang belum ada

### Prioritas langsung

1. konfigurasikan shared cache dan production process manager untuk worker
   `signatures`, termasuk graceful restart dan monitoring;
2. jalankan controlled vertical slice LS SPP jalur BP dari lazy activation,
   TTE, submit ke PPTK, TTE PPTK, submit ke PA, TTE PA, sampai handoff final;
3. implementasikan reconciliation attempt `unknown`, stuck recovery, cleanup,
   dan observability;
4. mulai P1 secure PDF delivery: inventaris seluruh view/download/report/
   attachment/direct URL sebelum membuat migration watermark;
5. implementasikan `/verify/{public_id}` dan policy result delivery; endpoint
   validasi exact artifact authenticated F13 sudah tersedia;
6. jadwalkan dua migration index mapping legacy sebagai deployment wave
   terpisah setelah capacity/lock review.

### Backend lanjutan

- parity proof definition workflow untuk seluruh cabang payment;
- assignment sync/activation adapter pada submit/verify controller payment;
- perluasan endpoint verification berbasis artifact ke seluruh payment;
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

- fondasi Svelte 5/plugin Vite/TypeScript dan root island global sudah dipasang;
- shell modal Bootstrap/Argon empat tahap dimuat lazy dari event canonical,
  sudah responsive, mempunyai state body/footer, close guard, dan focus
  restoration, serta sudah memanggil signing-session authoritative;
- typed API client F5 sudah tersedia untuk seluruh endpoint session/rendition,
  sign, attempt, resume, serta binary PDF/PNG. Client menegakkan same-origin,
  CSRF, status/media type, runtime response guard, AbortController, dan error
  normalization;
- PDF viewer F6 sudah memuat authorized binary melalui PDF.js worker lokal,
  menampilkan seluruh halaman dalam flow dengan canvas lazy/intersection,
  memvirtualisasi thumbnail, serta membersihkan fetch/render/document resource
  secara deterministik;
- F7-F9 sudah menghubungkan geometry canonical top-left/pt, editor multi-QR,
  collision/safe area, urutan operasi, QR pada pusat viewport terlihat, dan
  footer global selected-pages yang posisi/ukuran setiap halamannya dapat
  disesuaikan atau dihapus;
- F10 sudah menghubungkan canonical prepare request dan panel konfirmasi
  terpadu: prepared PDF binary, QR PNG authoritative berlogo Kota Malang,
  validasi plan fail-closed, ringkasan signer/dokumen/operasi/footer, serta
  input passphrase yang baru aktif setelah exact preview siap;
- F11 sudah menghubungkan final submit idempotent, payload prepared
  revision/hash, response `202`, pembersihan passphrase, attempt state
  in-memory, `Retry-After`, dan fail-closed unknown outcome;
- F12 sudah menghubungkan polling sesuai hint backend, progress operation nyata,
  partial resume dengan passphrase baru, terminal result, completion event, dan
  fail-closed reconciliation. Attempt dapat dipulihkan setelah modal dibuka
  ulang pada halaman yang sama, tetapi belum setelah full page reload;
- F13 sudah menyediakan endpoint verification exact artifact, private preview,
  cache berdasarkan SHA-256/policy version, serta modal status dan signer table.
  Action canonical masih terbatas pada LS SPP;
- adapter tombol canonical baru tersedia untuk LS SPP sebagai rollout pertama;
  payment lain masih fail-closed sampai backend workflow masing-masing siap.

Rancangan visual final berada di
`ESIGN_FRONTEND_VISUAL_AND_INTERACTION_DESIGN.md`: empat tahap UI, prepared
preview dan passphrase digabung pada Konfirmasi, serta tidak ada checkbox
afirmasi.

Frontend Tahap F0 sudah selesai. Endpoint aktif, typed request/response, binary
media, status attempt/operation, klasifikasi error, dan gap register dikunci di
`ESIGN_FRONTEND_BACKEND_CONTRACT_V1.md`; type TypeScript canonical berada di
`resources/js/esign/types.ts`. F1 bridge action LS SPP juga selesai di source:
capability signer dihitung tanpa N+1 oleh `LsSppSigningActionResolver`, row
DataTable hanya membawa `step_public_id` dan boolean capability, serta delegated
listener menerbitkan `sitangkas:esign:open`. Action masih tersembunyi karena
`SIGNATURE_FRONTEND_ENABLED` default `false`. F2 juga sudah selesai di source:
layout authenticated mempunyai satu root global, loader hanya mengimpor Svelte
dan CSS eSign saat event open pertama, dan shell modal mengikuti Bootstrap 5 /
Argon tanpa Tailwind. `pdfjs-dist` tersedia sebagai dependency tetapi belum
masuk initial bundle. F3 juga selesai di source: empat lifecycle event mempunyai
typed contract dan runtime validator, loader membedakan state signing/validation,
serta completion adapter me-reload hanya DataTable yang opt-in tanpa mengenal
global `mainTable` atau `tteDocumentTable`. Event completion memakai UUID
`step_public_id`, `attempt_id`, dan `result_artifact_id`; raw integer dokumen dan
secret tidak pernah masuk event. LS SPP main table dan modal detail adalah
adapter opt-in pertama. F4 juga sudah selesai di source: satu modal responsive
Bootstrap/Argon mempunyai empat tahap visual, state body/footer terpisah,
close guard untuk request prepare/submit, focus restoration, dark mode, dan
reduced-motion support. Kontrol aksi masih disabled dan data tidak difabrikasi
karena F5 API client/session state sekarang juga selesai di source. Modal telah
membuat session authoritative dan baru berpindah ke editor setelah response
lolos validator. F6 juga sudah selesai di source: viewer memakai binary tanpa
Base64/object URL, lazy PDF.js worker lokal, seluruh halaman dalam flow dengan
canvas intersection-lazy, thumbnail idle/intersection, zoom responsive, DPR
cap, dan layer canvas/placement terpisah. F7-F9 juga selesai: transform geometry
canonical, constraint, verifikasi metadata PDF, editor beberapa QR,
keyboard/drag/resize/hapus, ordering, serta footer editable/resize/hapus per
halaman telah aktif di source. F10 selesai dengan
POST prepared rendition, pemeriksaan kesetaraan plan, prepared PDF/QR
authoritative, dan konfirmasi/passphrase dalam modal yang sama. F11 juga selesai
dengan submit idempotent, response `202 Accepted`, pembersihan secret, dan
penahanan outcome ambigu tanpa retry otomatis. F12 juga selesai di source
dengan polling authoritative, progress per operation, partial resume, terminal
result, dan event refresh halaman. F13 menyediakan validasi artifact tanpa
upload ulang beserta preview PDF private dan pembedaan invalid dari provider
unavailable. Kembali ke editor atau menutup modal membuang prepared reference
dan secret dari memory. Feature flag tetap `false`.

Audit lintas payment menegaskan bahwa editor/session contract bersifat generik,
tetapi action resolver, activation/handoff, submit gate, dan compatibility
projector baru lengkap untuk LS SPP. `Data\Detail` dapat menjadi titik adapter
bersama setelah resolver digeneralisasi secara batch; `Data\DetailTbp` juga
harus ikut ditangani. Rollout tidak boleh menjadi global dan wajib memakai
allowlist per `payment_type:src_type` setelah backend workflow masing-masing
siap.

R0 relokasi entry point dokumen dikunci pada 26 September 2026 sebagai kontrak
dokumentasi tanpa perubahan runtime. Main table payment adalah surface
paket/alur; modal Detail Dokumen adalah surface action per dokumen; general
secure viewer adalah surface tunggal view/validasi BSrE/download; editor TTE
adalah surface placement/prepare/passphrase/sign. Tombol canonical LS SPP yang
masih berada di main table merupakan adapter transisi dan baru dipindahkan
setelah resolver detail, action row, modal coordinator, dan replacement viewer
lulus gate. Direct download lama juga tidak dicabut pada R0 agar layanan tipe
dokumen yang belum mempunyai delivery canonical tidak terputus. Tahap berikutnya
R1/P1 audit read-only lintas seluruh payment, `Data\Detail`, `Data\DetailTbp`,
history, attachment, report/export, route, database, serta snapshot
public/private juga selesai pada 26 September 2026 dan dicatat di
`PDF_DELIVERY_R1_READ_ONLY_INVENTORY.md`. Snapshot `public/File_*` sengaja hanya
sebagian dan tidak boleh dipakai sebagai parity report. Audit menemukan raw
public path masih luas, viewer/editor legacy menunjuk endpoint yang tidak lagi
terdaftar, serta canonical artifact baru mencakup 11 dokumen.

R2 contract data per row Detail Dokumen selesai di source pada 26 September
2026. `Data\Detail` dan `Data\DetailTbp` menambahkan `document_contract` versi 1
secara additive. Contract memakai opaque document ID, status/capability
terstruktur, source state, action mode, canonical public ID, disabled reason,
dan attachment terstruktur tanpa nama/path file. Exact signable step LS SPP
diproyeksikan secara batch. UI lama masih membaca HTML `status`/`action`; belum
ada runtime cutover atau pencabutan direct download.

Fondasi R3 source resolver selesai di source pada 26 September 2026. Kontrak
`PdfDeliverySource`, DTO internal immutable `ResolvedPdfDeliverySource`,
registry mapping legacy terkontrol, serta resolver artifact canonical telah
dibuat. Resolver canonical hanya menerima current artifact PDF pada disk
private, memvalidasi invariant metadata/path, dan fail-closed jika current
artifact ambigu atau rusak. Dokumen tanpa artifact menghasilkan `null` agar
composite resolver pada lanjutan R3 dapat mencoba adapter legacy yang
terotorisasi; ini bukan fallback raw path dari browser. `SPJ_BPP` tidak
diaktifkan dalam registry karena sumber aktifnya masih perlu review. Belum ada
runtime cutover ke resolver ini, adapter legacy, atau perubahan UI. R2/R3
relokasi ini berbeda dari P2 workstream watermark yang menambahkan flag
posisi.

Lanjutan R3 source fallback selesai di source pada 26 September 2026. Binding
`PdfDeliverySource` sekarang menggunakan composite resolver dengan urutan
canonical artifact, legacy-private, lalu legacy-public. Resolver canonical
juga mendukung attachment Billing dan SPJ Fungsional melalui resource key
terkontrol. `Data\Detail` dan `Data\DetailTbp` memakai resolver tersebut sebagai
sumber `source_state`, termasuk TBP, sementara kolom HTML action/download lama
tetap dipertahankan agar UI legacy belum terputus. Contract baru tidak
mengekspor disk atau path fisik.

Acceptance read-only membuktikan canonical, legacy-public, TBP, Billing, dan
SPJ Fungsional dapat di-resolve pada snapshot lokal. Cabang legacy-private
belum mempunyai sampel layout `File_*` pada storage lokal ini, walaupun disk
dan resolvernya sudah aktif. Artifact attachment canonical belum mempunyai row
pada snapshot database. `SPJ_BPP`, file hilang/tidak terbaca/bukan PDF, history
legacy, serta static public bypass masih menjadi sumber atau surface yang belum
diselesaikan. Tidak ada file yang dipindahkan atau dihapus pada R3 ini.

Boundary delivery/viewer PDF universal untuk mode ORIGINAL selesai di source
pada 26 September 2026. Dua endpoint authenticated
`document.pdf.content` dan `document.pdf.download` menerima hanya opaque
encrypted document ID dan resource key terkontrol (`document`, `billing`, atau
`spj_fungsional`). Endpoint memuat `PdfDeliverySource`, mengotorisasi ulang
user serta posisi/tahun aktif pada setiap request, kemudian mengalirkan binary
PDF tanpa Base64 dan tanpa mengekspor disk/path fisik. Raw numeric document ID
ditolak, response memakai `no-store`, `nosniff`, dan same-origin resource
policy, sedangkan download tetap merupakan capability terpisah.

Untuk canonical artifact, delivery memakai pemeriksaan integrity dan policy
eSign yang sudah ada. Untuk fallback legacy, akses dibatasi pada dua disk
legacy yang diizinkan, metadata size/header PDF diperiksa kembali, lalu stream
dibuka server-side. `document_contract` versi 1 sekarang menambahkan
`delivery.content_url` dan `delivery.download_url` pada dokumen utama dan
attachment; URL download hanya diterbitkan ketika capability lama mengizinkan,
tetapi endpoint tetap melakukan authorization authoritative secara mandiri.

Implementasi ini adalah bridge ORIGINAL additive, belum persistent delivery
session final. Flag `pdf_watermark_required`, keputusan ORIGINAL/WATERMARK,
derivative watermark, access event append-only, viewer Svelte umum, Range
request, cutover tombol legacy, dan penutupan static `/File_*` tetap tahap
berikutnya. Karena itu route lama tidak dihapus dan enforcement watermark belum
boleh dinyatakan aktif.

R4 action/capability resolver universal selesai di source pada 26 September
2026. `DocumentActionResolver` sekarang menjadi pengambil keputusan backend
untuk `view`, `download`, `verify`, dan `sign` pada dokumen utama, Billing, serta
SPJ Fungsional. Resolver memakai source R3, authorization delivery yang sama
dengan endpoint binary, user/posisi/tahun aktif, status acting, feature flag,
dan exact canonical `step_public_id`. Setelah cutover R6, TTE tidak lagi memakai
registry aturan signer legacy. Admin Super dalam konteks acting selalu
memperoleh `sign.allowed=false`; attachment tidak pernah signable.

`document_contract` tetap mempertahankan `capabilities`, `delivery`, dan
`disabled_reasons` versi transisi, tetapi sekarang juga memuat objek `actions`
authoritative. Setiap action berisi `allowed`, URL atau canonical public ID yang
relevan, mode `canonical|legacy_transition|none`, serta safe disabled reason.
Tidak ada disk/path fisik yang diserialisasi. `Data\Detail` dan
`Data\DetailTbp` tidak lagi mengirim keputusan `legacyCanSign` atau
`canDownload` ke builder; keduanya memberikan actor dan resolver menghitung
keputusan sendiri.

Mode top-level `legacy_transition` tetap dipakai untuk menandai bahwa sumber PDF
masih dibaca dari fallback legacy-private/legacy-public. Mode tersebut bukan
izin TTE: `actions.sign.mode` selalu `none` untuk source noncanonical. Canonical
signing action yang benar-benar lengkap saat ini masih LS SPP.

R5 general secure PDF viewer selesai di source pada 26 September 2026 sebagai
Svelte island read-only yang terpisah dari state machine editor TTE. Island
dimount satu kali oleh layout dan di-load secara lazy melalui event
`sitangkas:pdf-viewer:open`. Payload hanya diterima bila action view R4 aktif,
URL same-origin cocok dengan route delivery universal, resource key termasuk
allowlist, serta kontrak download/verification konsisten. Viewer mengambil PDF
binary `application/pdf` tanpa Base64, memakai PDF.js worker lokal, lazy-render
halaman/canvas, thumbnail, navigasi, zoom, active-page tracking, dan cleanup
request/document ketika modal ditutup.

Panel informasi memuat validasi artifact secara paralel sehingga kegagalan atau
latensi validasi tidak menahan tampilan PDF. Hasil menampilkan status TTE,
jumlah dan identitas signer, waktu, serta integritas; tombol download hanya
muncul dari action `download.allowed=true`. Layout memakai Bootstrap 5/custom
Argon, panel desktop, drawer tablet, workspace penuh mobile, dan dark-mode.
Event `sitangkas:pdf-viewer:closed` sudah tersedia untuk modal coordinator.

R5 belum mengganti tombol `.view-pdf`, direct download legacy, atau action pada
row Detail/DetailTbp. Integrasi trigger, modal coordinator, dan cutover bertahap
tetap R6 dan seterusnya agar layanan lama tidak terputus sebelum replacement
terhubung.

R6 integrasi dan cutover action row Detail Dokumen selesai di source pada 26
September 2026. `detail.blade.php` dan `detailTbp.blade.php` sekarang merender
kolom status/action hanya dari `document_contract` R4 melalui satu renderer
frontend. Dokumen utama, Billing, dan SPJ Fungsional membuka secure viewer R5
memakai payload terenkode yang divalidasi ulang oleh action bridge. Tombol
download langsung tidak lagi dirender pada row karena download hanya tersedia
di dalam viewer sesuai `actions.download`.

Action TTE hanya dirender bila backend mengirim `sign.allowed=true`,
`mode=canonical`, dan exact `step_public_id`; kliknya memakai bridge editor TTE
baru. Source `legacy_transition` tetap dapat dibaca untuk kebutuhan view, tetapi
selalu memperoleh `actions.sign.mode=none`. Bila kontrak row hilang atau rusak,
renderer menampilkan kondisi aman dan tidak menghidupkan kembali HTML lama.
Kedua tabel ikut refresh tanpa reset halaman setelah event TTE sukses.

Seluruh 30 include `components.esign.esign` pada halaman payment telah dilepas.
Blade editor lama, bundle PDF editor lama, source map-nya, dan `signed.js` telah
dihapus dari runtime. `Data\Detail` tidak lagi membuat HTML `.signModal`, URL
`/File_*`, atau matriks numeric signer; `Data\DetailTbp` juga tidak lagi membuat
view/download public langsung. Query kedua controller memakai eager-loaded
`pdfDeliveryArtifacts` tanpa subquery alias lama yang tidak lagi dikonsumsi.

Konsekuensi fail-closed yang disengaja: tipe dokumen/payment yang belum memiliki
artifact, workflow, step, serta assignment canonical tetap dapat memakai viewer
transisi bila source-nya tersedia, tetapi belum dapat melakukan TTE. Saat ini
capability step canonical dari modal Detail baru lengkap untuk LS SPP. Rollout
tipe lain harus menambah provisioning/workflow canonical, bukan mengaktifkan
kembali editor legacy.

R6 belum menjadi modal coordinator. Pada R7, modal Detail harus disembunyikan
sebelum secure viewer/editor dibuka, context paket disimpan, lalu Detail dibuka
kembali dan direfresh setelah surface anak ditutup atau TTE selesai. Cutover
`.view-pdf` di luar Detail/DetailTbp tetap dilakukan bertahap setelah gate tiap
payment lulus. `pdfview.blade.php` tetap dipertahankan sementara hanya untuk
surface view PDF di luar Detail; ia bukan lagi jalur TTE.

Pilot real, aktivasi feature flag operasional, public verification, dan rollout
tetap menunggu gate backend terkait. Urutan rinci berada di
`ESIGN_FRONTEND_IMPLEMENTATION_AND_LEGACY_MIGRATION_PLAN.md`.

## 18. Urutan implementasi berikutnya

```text
1. [SELESAI] Contract proof visible coordinate + serial multi-QR satu PDF
2. [SELESAI] Migration additive operation/counter/partial/intermediate/decoration
3. [SELESAI] Placement/footer domain + exact prepared rendition
4. [SELESAI DI SOURCE] Persistence operation + worker serial checkpoint-aware + partial resume + final verify
5. [SELESAI DI SOURCE] Public ID activation setelah sukses + compatibility projection satu aggregate
6. [SELESAI] Audit dan penguncian kontrak frontend-backend F0
7. [SELESAI DI SOURCE] Bridge action LS SPP canonical untuk frontend
8. Shared cache + production process manager + operational preflight
9. Controlled LS SPP BP -> PPTK -> PA vertical slice
10. Reconciliation/stuck/cleanup/observability
11. Legacy mapping index deployment wave
12. Policy delivery PDF + watermark/cache/audit + verify/public route + legacy resolver
13. Backend Ready Gate operasional
14. [SELESAI DI SOURCE] Svelte/Vite foundation dan bridge Blade
15. [F4-F13 SELESAI DI SOURCE] Shell signing modal, typed API/session, PDF
    viewer, geometry/editor multi-QR/footer, prepared confirmation, dan final
    submit idempotent, polling/progress, partial resume, terminal result, dan
    modal validasi canonical
16. Pilot LS SPP operasional
17. Perluasan LS SPM/SP2D lalu rollout per payment
18. Resumable legacy mapping + reporting cutover
19. Folder decommission setelah seluruh gate
```

## 19. Larangan untuk agent berikutnya

- Jangan menjalankan migration hanya karena DDL tersedia; lakukan deployment
  review, backup, lock assessment, dan rollback plan.
- Jangan memanggil sign production untuk verifikasi otomatis.
- Jangan membuat/menjalankan test suite tanpa izin eksplisit pengguna.
- Jangan menyimpan/mengirim passphrase di queue payload atau database.
- Jangan mengubah `tries=1` menjadi blind retry.
- Jangan mengubah attempt `unknown` menjadi failed tanpa reconciliation.
- Jangan melewati validator prepared rendition atau mengaktifkan feature flag
  visible dengan koordinat tebakan.
- Jangan menganggap beberapa `signatureProperties` pada collection berarti
  multi-QR satu PDF dapat dipanggil sekali; gunakan serial setelah proof.
- Jangan menjalankan beberapa sign QR terhadap source yang sama secara paralel
  atau mengulang operation yang sudah completed.
- Jangan mengirim PDF sebagai Base64 JSON ke browser atau menerima upload PDF
  dari editor.
- Jangan menerima NIK, file path, workflow state, atau destination path dari
  frontend.
- Jangan mengaktifkan seluruh step setelah upload atau setelah satu TTE sukses.
  Pada LS SPP, first step aktif secara lazy ketika signer BP/BPP nyata membuka
  session; setiap step berikutnya baru di-assign dan diaktifkan saat handoff.
- Jangan mengembalikan auto-activation step berikutnya ke
  `EsignAttemptPersistenceService`; batas tersebut sengaja berada pada
  `LsSppWorkflowHandoffService`.
- Jangan mengubah `document.submit`, `assigned_to`, atau `users_to` untuk
  dokumen LS SPP canonical tanpa submit gate dan assignment service di transaksi
  yang sama.
- Jangan memakai fallback legacy bila artifact canonical sudah ada tetapi
  workflow hilang atau rusak; kondisi tersebut harus gagal tertutup.
- Jangan memasang dependency frontend sebelum Backend Ready Gate.
- Jangan menghapus, truncate, rename, freeze, atau drop tabel compatibility.
- Jangan menghapus/memindah source legacy dari mapping command.
- Jangan menghapus file invalid/zero-byte/orphan; itu evidence.
- Jangan menganggap working tree berarti sudah deployed atau committed.

## 20. Peta file implementasi

| Concern | Lokasi utama |
|---|---|
| Provider boundary | `app/Contracts/Esign`, `app/Services/Esign/BsreClient.php` |
| Visible contract proof | `app/Console/Commands/Esign/ProveVisibleSigningContractCommand.php`, `app/Actions/Esign/RunVisibleSigningContractProof.php`, `app/Services/Esign/ContractProof` |
| DTO dan enum | `app/Data/Esign`, `app/Enums/Esign` |
| Canonical models | `app/Models/Esign` |
| Persistence/state | `app/Services/Esign/Persistence` |
| Authorization | `app/Services/Esign/Authorization`, `app/Policies/Esign` |
| Signing session/action | `app/Actions/Esign`, `app/Services/Esign/EphemeralSigningSessionStore.php` |
| Visible placement/prepared rendition | `app/Services/Esign/VisibleSigningPlanValidator.php`, `app/Services/Esign/PreparedPdfRenderer.php`, `app/Services/Esign/EphemeralPreparedRenditionStore.php` |
| Secret store | `app/Services/Esign/EphemeralSigningSecretStore.php` |
| Worker | `app/Jobs/Esign/PerformEsignAttempt.php` |
| Internal HTTP | `app/Http/Controllers/Esign`, `app/Http/Requests/Esign`, `routes/web.php` |
| Artifact storage/integrity/path checksum | `app/Services/Esign/Persistence/DocumentArtifactPersistenceService.php`, `app/Services/Esign/DocumentArtifactIntegrityService.php`, `app/Support/Esign/DocumentArtifactStoragePath.php` |
| Compatibility writer | `app/Services/Esign/Persistence/LegacyEsignLedgerWriter.php` |
| LS SPP submit proof/gate | `app/Services/Esign/Authorization/LsSppSubmitGate.php` |
| LS SPP activation/assignment | `app/Services/Esign/LsSppWorkflowHandoffService.php` |
| LS SPP compatibility projection | `app/Services/Esign/Persistence/LsSppCompatibilityProjector.php` |
| LS SPP controller handoff | `app/Http/Controllers/Payment/LS/SPP.php` |
| Configuration | `config/services.php`, `config/esign.php`, `config/queue.php`, `.env.example` |
| Schema | `database/migrations/2026_09_18_*esign*`, document artifact/signing migrations |
