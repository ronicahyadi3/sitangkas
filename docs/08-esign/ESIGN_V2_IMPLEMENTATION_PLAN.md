# Rencana Implementasi eSign Client 2.2.0

Tanggal snapshot: **23 September 2026**.

Status: **rencana kerja dan tracker. Kondisi source code aktual berada pada
`CURRENT_ESIGN_IMPLEMENTATION.md`: Phase 2 selesai untuk scope awal, fondasi
source Phase 3-5 sudah tersedia dan 13 tabel canonical sudah diterapkan pada
database lokal. Dua migration index legacy masih `Pending`; source
artifact/workflow/step provisioning dari upload payment sudah dibuat dan sudah
dibuktikan secara lokal oleh dedicated worker melalui satu upload NPD
`GU_SKPD`. Untuk LS SPP, lazy activation, submit gate, assignment/activation
PPTK dan PA/KPA saat handoff sudah tersedia. Vertical slice sign masih belum
lulus end-to-end, dan Phase 6-12 belum diimplementasikan. Desain approved untuk
binary PDF editor, editable footer, serta multi-QR serial satu signer berada di
`ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`**.

Dokumen ini mengarahkan agent pada urutan kerja, dependency, acceptance, dan
blocker. Baca `README.md`, `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`, backend
contract, `ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`, dan frontend modal
design lebih dahulu.

## 1. Prinsip eksekusi

- Periksa `git status` dan pertahankan perubahan pengguna yang sudah ada.
- Gunakan Laravel Boost `application-info`, `search-docs`, dan schema tools
  sesuai instruksi project sebelum perubahan Laravel.
- Jangan mengubah dependency sampai pekerjaan implementasi memang diotorisasi.
- Gunakan Artisan `make:* --no-interaction` untuk scaffold class Laravel.
- Gunakan `apply_patch` untuk edit manual.
- Jika mengubah PHP, jalankan Pint sesuai instruksi project.
- Jangan membuat, memodifikasi, atau menjalankan test tanpa konfirmasi eksplisit
  pengguna; test plan di bawah bukan izin eksekusi.
- Jangan melakukan request sign ke BSrE production sebagai bagian test otomatis.
- Implementasi wajib backend-first. Jangan memasang Svelte, membuat modal, atau
  menghubungkan tombol payment sebelum backend melewati **Backend Ready Gate**
  pada Phase 7.

### Urutan master

| Urutan | Phase | Fokus | Hasil utama | Boleh lanjut bila |
|---:|---|---|---|---|
| 0 | Security containment | Credential dan source lama | Credential terekspos dirotasi, secret baru aman | Tidak memakai credential lama |
| 1 | Sandbox contract proof | Perilaku riil API v2 | Kontrak response/error/limit/koordinat | Ambiguitas kritis vendor terjawab |
| 2 | Backend boundary | Client provider | `EsignGateway`, `BsreClient`, DTO, mapper | Provider dapat dipanggil secara terisolasi |
| 3 | Persistence/state | Migration, model, lock | Attempt, state machine, fingerprint, storage reference | Duplicate dan state `unknown` terkontrol |
| 4 | Authorization/session | Policy dan source of truth | Signing session terotorisasi dan preview private | Browser tidak menentukan signer/path/state |
| 5 | Backend invisible flow | API sign end-to-end | Prepare, sign, verify, finalize tanpa UI baru | Invisible sign lengkap lewat API internal |
| 6 | Backend visible/verify/delivery | Placement, footer, multi-QR serial, validasi, dan PDF rendition | Prepared rendition, operation checkpoint, verify, artifact version, watermark/cache/audit | Semua kemampuan UI memiliki API stabil |
| 7 | Backend Ready Gate | Hardening dan operasional | Backend aman, observable, terdokumentasi | Gate backend dinyatakan lulus |
| 8 | Frontend foundation | Svelte/Vite island | Root, API client, Bootstrap/Argon modal shell | UI dapat memakai API tanpa mengetahui vendor |
| 9 | Signing modal | Flow invisible | Modal prepare/confirm/sign/result | State sukses/gagal/unknown benar |
| 10 | Visible editor dan validation | PDF/placement/verify UI | Editor visible dan modal validasi | Matrix desktop/mobile/aksesibilitas lulus |
| 11 | Pilot payment | Integrasi satu flow | Pilot LS dan observability | Stabil pada traffic nyata terbatas |
| 12 | Rollout/cutover | Semua caller dan operasi | Caller v1 dialihkan; compatibility ledger tetap append-only | Tidak ada caller v1 tanpa canonical dual-write |

Urutan ini bersifat dependency, bukan sekadar nomor pekerjaan. Pekerjaan UI
boleh didesain, tetapi implementasi frontend tidak dimulai sebelum response
schema, application error code, route, authorization, dan state backend stabil.

### Checkpoint aktual 23 September 2026

- **Sudah dibuat di source:** schema migration, enum, model/cast/relasi,
  workflow/step/attempt transition service, artifact persistence, provider
  response/signature persistence, authorization/policy, signer resolver,
  ephemeral signing session, private preview, encrypted TTL secret store,
  compatibility writer, endpoint internal, polling, dan dedicated
  `PerformEsignAttempt` job, definition registry payment, serta job provisioning
  source artifact/workflow/step setelah upload commit.
- **Schema aktif:** 13 migration tabel canonical sudah diterapkan pada database
  lokal dalam batch 9-21. Dua migration index mapping legacy tetap `Pending`
  untuk wave deployment terpisah.
- **Sudah dibuktikan lokal:** dedicated worker `signatures` memproses satu job
  `ProvisionCanonicalDocument` dari upload NPD `GU_SKPD`; source artifact,
  workflow `PPTK -> PA`, dua step, dan event `workflow_created` terbentuk,
  queue kembali kosong, dan tidak ada failed job.
- **Sudah dibuat untuk LS SPP:** workflow tetap draft sampai BP/BPP nyata
  membuka signing session; setelah itu first step aktif secara lazy. TTE sukses
  menyelesaikan current step tetapi membiarkan next step pending. Submit gate
  lalu mengikat serta mengaktifkan PPTK atau PA/KPA pada handoff atomik bersama
  projection legacy.
- **Belum dibuktikan:** production process manager/shared cache dan pipeline
  signing/handoff canonical LS SPP end-to-end.
- **Sengaja fail-closed:** step `placement_required=true` belum dapat sign dan
  menghasilkan `esign.visible_placement_not_ready` sampai backend visible
  placement selesai.
- **Desain baru belum dibuat:** browser binary preview tanpa upload, editable
  footer hanya sebelum TTE pertama, prepared revision, N QR dalam satu attempt,
  operation checkpoint/intermediate, `partially_signed`, dan partial resume.
- **Belum dibuat:** reconciliation `unknown`, stuck recovery, scheduled cleanup,
  health/metric/alert, migration/management `pdf_watermark_required`, PDF
  watermark/cache/audit, public verify/delivery, legacy QR resolver, mapping
  runner, frontend Svelte, pilot, dan rollout.
- **Larangan tetap:** jangan mulai frontend sebelum Backend Ready Gate dan
  jangan menjalankan migration/test/live sign tanpa otorisasi yang sesuai.

## 2. Phase 0 - security containment

Status snapshot: **containment lokal selesai; tindakan credential eksternal
masih pending**. Baca
[laporan Phase 0](PHASE_0_SECURITY_CONTAINMENT_REPORT.md).

Hasil wajib:

- daftar credential lama yang terekspos tanpa menyalin nilainya ke report;
- passphrase signer dan Basic Auth lama sudah dirotasi/revoke bila masih valid;
- hardcoded/default credential tidak berada pada code path baru;
- secret baru tersedia melalui environment/secret store;
- config cache dan worker direstart setelah perubahan;
- pencarian secret pada source/log/artifact dilakukan dengan output tersanitasi;
- keputusan Git history cleanup dikoordinasikan.

Blocker: jangan mengaktifkan provider baru memakai credential yang pernah
tertanam dalam source lama.

## 3. Phase 1 - sandbox contract proof

Status snapshot: **kontrak minimum live production NIK+passphrase invisible
signing selesai; matrix lanjutan masih pending**. Baca
[laporan Phase 1](PHASE_1_SANDBOX_CONTRACT_REPORT.md). Pemilik menyatakan tidak
tersedia environment development dan mengarahkan probe terbatas ke production.
Status, certificate chain, sign satu file, verify unsigned, dan verify signed
sudah terbukti. Visible placement, encrypted/modified PDF, timeout, limit, dan
multi-file belum terbukti dan tidak masuk vertical slice pertama.

Bangun matrix manual/contract test terkontrol untuk development eSign Client:

1. Basic Auth benar/salah;
2. user status NIK terdaftar/tidak/expired/revoked;
3. invisible NIK + passphrase sukses;
4. passphrase salah;
5. PDF invalid/empty/oversize;
6. visible portrait dan landscape;
7. rotated page dan ukuran page berbeda;
8. verify PDF tanpa/dengan password;
9. timeout sebelum connect dan setelah request terkirim;
10. response content type dan body setiap skenario;
11. satu vs beberapa `file[]`;
12. satu vs beberapa `signatureProperties[]`;
13. partial failure pada multi-file;
14. rate/size/time limits.

Catat hanya sanitized response schema, HTTP status, safe code, content type, dan
latency. Jangan mencatat passphrase atau PDF base64.

Output phase:

- response decoder contract;
- error mapping v2;
- coordinate transform;
- file/total request limit;
- keputusan multi-file;
- keputusan retry/reconciliation;
- konfirmasi production endpoint/certificate/TLS.

## 4. Phase 2 - backend foundation

Progress 17 September 2026:

- selesai: `services.bsre_esign` terstruktur, timeout koneksi/operasi,
  seluruh endpoint v2 yang telah diprobe, lokasi, dan default reason;
- selesai: `BsreConfiguration` immutable dengan validasi fail-closed dan
  redaksi credential saat di-debug;
- selesai: lazy singleton binding untuk `BsreConfiguration`;
- selesai: `EsignGateway` dan binding menuju `BsreClient`;
- selesai: DTO request yang meredaksi NIK, passphrase, dan PDF saat debug serta
  DTO response yang tidak mengekspos Base64;
- selesai: payload builder untuk NIK+passphrase, invisible, satu PDF;
- selesai: mapper ketat sign/status/verify termasuk normalisasi typo vendor
  `signatureAlgoritm` dan `timestampInfomation`;
- selesai: error taxonomy aman, aturan sign 5xx/connection menjadi
  `esign.outcome_unknown`, dan telemetry tanpa body/credential;
- selesai: boundary HTTP injectable dapat memakai `Http::fake()`; diagnostic
  fake lulus tanpa jaringan dan live read-only verify unsigned/signed lulus;
- belum: Pest contract test karena project mensyaratkan izin eksplisit sebelum
  membuat atau menjalankan test suite.

Implementasikan:

- config `services.bsre_esign`; **selesai**
- `EsignGateway`; **selesai**
- `BsreClient`; **selesai**
- request/response DTO; **selesai**
- payload builder; **selesai untuk scope NIK+passphrase/invisible/satu-file**
- response mapper; **selesai untuk kontrak live yang sudah terbukti**
- exception/error taxonomy yang aman; **selesai**
- service provider binding konfigurasi dan gateway; **selesai**
- HTTP fake boundary untuk test mendatang; **selesai**

Acceptance:

- tidak ada secret/default credential dalam source;
- timeout explicit per operasi;
- sign tidak auto-retry;
- request/response body sensitif tidak masuk log;
- client tidak mengetahui Document/Eloquent/UI;
- client dapat diganti fake melalui interface.

Catatan implementasi:

- tidak ada auto-retry di `BsreClient`, termasuk operasi read-only; nilai
  `retryable` hanya memberi keputusan kepada orchestration layer mendatang;
- `esign.invalid_passphrase` sudah tersedia sebagai taxonomy, tetapi mapper
  belum mengklasifikasikan response vendor ke code itu karena skenario
  passphrase salah belum dibuktikan live;
- visible signing, TOTP, email, encrypted verify, dan multi-file tetap di luar
  scope.

## 5. Phase 3 - schema dan state

Progress 21 September 2026: 13 migration tabel canonical dan migration-control
sudah diterapkan pada database lokal. Dua migration indeks mapping legacy
sudah dibuat tetapi tetap `Pending` untuk deployment wave terpisah. DDL
mencakup `esign_attempts.document_id`, indeks `(document_id, created_at)`, dan
checkpoint `esign_migration_items.current_stage`. PHP enum, model/cast/relasi,
immutable guard, workflow/step/attempt transition service, artifact/provider
response/signature persistence, encrypted secret/session store, compatibility
writer, dan asynchronous signing job sudah dibuat. Mapping runner,
reconciliation, provisioning workflow dari payment, parity report, dan
operational deployment belum dibuat/selesai.

Setelah membaca migration readiness dan schema detail:

- inventaris query aplikasi lain yang membaca `before_signs`/`after_signs` dan
  kunci contract laporan sebelum mengubah schema legacy;
- buat `document_artifacts`, `esign_attempts`, `esign_attempt_events`, dan
  `esign_attempt_legacy_links` atau schema final ekuivalen;
- buat `esign_migration_runs` dan `esign_migration_items` untuk checkpoint,
  high-watermark, lease, heartbeat, retry/manual-review, dan resume;
- tambahkan index audit legacy berdasarkan query nyata;
- buat model dengan fillable/casts/hidden yang eksplisit;
- buat state transition service/action;
- implementasikan request fingerprint dan atomic lock;
- implementasikan compatibility dual-writer `before_signs`/`after_signs` dan
  parity report; kedua tabel tetap menjadi contract aplikasi lain dan ledger
  append-only sesuai keputusan aktif;
- gunakan SHA-256 untuk artifact baru.

Acceptance:

- duplicate click/request tidak menghasilkan concurrent sign;
- state `unknown` tersedia;
- query attempt per dokumen memakai index;
- migration memperhitungkan sekitar 590 ribu row tabel legacy;
- high-watermark + catch-up menjaga write baru tetap berjalan tanpa downtime;
- item state/lease/checkpoint tersimpan sehingga restart tidak mengulang run;
- input/output artifact dan status success/failed/unknown dapat disajikan dalam
  laporan tanpa membaca raw response;
- file invalid/zero-byte dipertahankan sebagai evidence non-current;
- storage year ditentukan per artifact; `document_process` `UPLOAD`/`TTE`
  menjadi sumber utama dan fallback tidak boleh menebak relasi ambigu;
- mapping mengikuti `ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`, dapat pause/resume,
  aman terhadap duplicate job/crash, dan tidak menghapus source;
- rollback dan deployment lock impact dinilai.

Target Phase 3/12 adalah menjadikan schema canonical sebagai state/audit
terstruktur sambil tetap mempertahankan `before_signs` dan `after_signs` sebagai
compatibility ledger append-only. Mapping atau consumer cutover tidak memberi
izin menghentikan write maupun drop. Perubahan lifecycle tabel memerlukan gate
pada `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md` dan keputusan pengguna baru.

## 6. Phase 4 - authorization dan signing session

Status source 21 September 2026: **sebagian besar sudah dibuat, belum lulus
runtime acceptance**. `EsignAuthorizationService`, policy workflow/step/
artifact/attempt, `SignerIdentityResolver`, `CreateSigningSession`,
`ResolveSigningSession`, encrypted ephemeral session store, conflict check, dan
private preview tersedia. Blocker utamanya adalah belum ada provisioning
workflow/step/artifact dari controller payment dan runtime acceptance.

Baca `ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md` sebelum mengimplementasikan
phase ini. Scope hanya `SELF_SIGN`; jangan membuat `PREPARE_FOR_SIGNER`.

Implementasikan:

- policy/authorization dokumen TTE;
- `SignerIdentityResolver`;
- `CreateSigningSession` untuk signer yang sedang login;
- session UUID, expiry, artifact hash/version, actor/effective context;
- preview private terotorisasi;
- conflict bila artifact berubah;
- route/controller/Form Request internal.

Acceptance:

- user biasa maupun Admin Super tidak dapat mengirim NIK bebas;
- document ID, path, workflow, signer, scope, dan year resolved server-side;
- cross-unit/cross-year/invalid-stage ditolak;
- signing session tidak menyimpan passphrase/base64;
- context placement/preview sebelum sign bersifat ephemeral; tutup/Batal tidak
  membuat audit, history, atau `esign_attempts`;
- Admin Super tidak mendapat proxy-signing atau hak sign universal;
- posisi bisnis nyata milik user Admin Super diperlakukan sebagai posisi user
  biasa, bukan acting like; acting context ditandai terpisah;
- certificate owner selalu real authenticated user yang sah pada step aktif;
- signer menempatkan QR/footer untuk step miliknya sendiri;
- workflow multi-signer berjalan sequential.

## 7. Phase 5 - backend invisible signing flow

Status source 23 September 2026: **kode vertical slice tersedia, belum lulus
end-to-end acceptance**. Route aktual memakai prefix `/esign/internal`, secret
store terenkripsi ber-TTL dan job `PerformEsignAttempt` sudah dibuat. Schema
canonical sudah aktif dan provisioning runtime lokal sudah terbukti. Workflow
LS SPP aktif secara lazy, sedangkan PPTK/PA/KPA aktif saat handoff. Worker
production belum dikelola process manager dan belum ada signing attempt
canonical end-to-end.

Tujuan phase ini adalah menyelesaikan vertical slice backend tanpa modal baru.
Implementasikan route/controller/Form Request tipis di atas Action dan service
yang sudah dibuat.

Kontrak endpoint internal konseptual:

| Method | Endpoint | Tanggung jawab |
|---|---|---|
| `POST` | `/esign/internal/signing-sessions` | Authorize step dan membuat ephemeral session |
| `GET` | `/esign/internal/signing-sessions/{uuid}` | Membaca capability/state/session aman |
| `GET` | `/esign/internal/signing-sessions/{uuid}/preview` | Stream artifact private terotorisasi |
| `POST` | `/esign/internal/signing-sessions/{uuid}/sign` | Validasi final, buat attempt/secret TTL, enqueue sign, kembalikan `202` |
| `DELETE` | `/esign/internal/signing-sessions/{uuid}` | Menutup preparation tanpa audit/history |
| `GET` | `/esign/internal/attempts/{uuid}` | Membaca hasil/status tanpa raw response vendor |

Nama route final mengikuti convention project. Semua endpoint write memakai
CSRF, authorization, rate limiter yang sesuai, dan JSON response bernama.

Urutan implementasi internal:

1. `CreateSigningSession` resolve dokumen, real actor/certificate owner, posisi
   aktif, assignment signer, workflow, artifact, version, dan SHA-256;
2. siapkan context preview ephemeral tanpa record `esign_attempts` atau audit;
3. response prepare memberikan UUID, capability, preview URL, expiry, dan data
   signer yang sudah dimasking;
4. `SignDocument` memperoleh atomic lock dan memvalidasi ulang expiry,
   authorization, workflow, serta artifact hash;
5. ambil passphrase hanya dari `SignDocumentRequest`; enkripsi ke secret
   store/cache private ber-TTL dan jangan memasukkannya ke DTO persistence,
   model, context/log, event, `failed_jobs`, atau serialized queue payload;
6. saat tombol sign ditekan, buat attempt persisten `prepared`, isi immutable
   `document_id` dari workflow, dispatch `PerformEsignAttempt` setelah commit,
   lalu kembalikan `202 Accepted`;
7. worker mengambil secret melalui opaque reference, mengubah step/attempt ke
   `signing`, melepas lock/transaksi, lalu memanggil `BsreClient::sign()` secara
   sinkron di worker;
8. validasi hasil PDF dan simpan pada private staging;
9. ubah ke `validating`, verify output, pindahkan ke artifact final/version
   baru, dan jalankan `FinalizeSignedDocument` dalam transaksi pendek;
10. tulis history/audit dan ubah state menjadi `succeeded`;
11. kegagalan deterministik menjadi `failed`, sedangkan transport ambiguity
    setelah request mungkin terkirim menjadi `unknown` dan step
    `reconciliation_required`;
12. hapus secret pada terminal state; status dibaca frontend melalui polling
    dan optional realtime notification.

Khusus LS SPP, setelah item 10:

1. current step menjadi `completed`, tetapi next step tetap `pending`;
2. submit gate membuktikan attempt sukses, after-sign artifact, current pointer,
   compatibility link, dan projection `TTE`;
3. handoff BP/BPP menetapkan PPTK, sedangkan handoff PPTK menetapkan PA/KPA;
4. assignment, event `step_assigned`, activation next step, kolom legacy, dan
   histori `SUBMIT` commit atau rollback bersama;
5. workflow baru `completed` pada sukses step terakhir; handoff final ke
   PPK-SKPD wajib memeriksa keseluruhan workflow.

Acceptance:

- flow invisible dapat dijalankan lewat API internal tanpa Svelte;
- passphrase hanya dipersist sementara pada secret store terenkripsi ber-TTL;
  job payload/database/log/event tidak membawa secret;
- tidak ada external HTTP call di dalam transaksi database;
- double click/concurrent request hanya menghasilkan satu vendor call;
- sign tidak mempunyai automatic retry;
- job sign memakai `tries=1`; restart/retry tidak mengirim ulang attempt yang
  sudah memasuki `signing`;
- browser/modal boleh ditutup setelah `202` tanpa menghentikan proses server;
- hasil kosong, bukan PDF, malformed, atau gagal verify tidak pernah final;
- artifact signed hanya tersedia melalui download/preview terotorisasi;
- response frontend tidak mengandung path storage, NIK lengkap, atau raw vendor
  payload.

## 8. Phase 6 - backend visible signing dan verification

Implementasikan seluruh capability yang diperlukan UI sebelum UI dibuat:

- lakukan contract proof terkontrol untuk coordinate visible dan beberapa sign
  serial pada satu PDF; array Postman tidak membuktikan multi-placement;
- tambah migration additive `esign_signature_operations`, progress counter
  attempt, status `partially_signed`, artifact `intermediate_sign`, serta
  `document_artifact_decorations`; jangan edit migration yang sudah diterapkan;
- `SignaturePlacementData` dengan page, canonical coordinate, width, height,
  display mode, reason, `operation_index`, dan reserved public ID yang
  tervalidasi;
- transform canonical coordinate ke contract BSrE berdasarkan hasil sandbox;
- validasi page exists, finite number, minimum/maximum size, bounds, rotation,
  dan capability signer/document;
- source image/specimen/QR yang dibuat atau dipilih server; jangan menerima
  path/image arbitrer dari browser;
- browser memperoleh PDF sebagai authorized binary stream dari exact artifact;
  jangan mengirim PDF Base64 melalui JSON atau menerima upload editor;
- signature presence diputuskan backend. PDF unsigned memperoleh footer pada
  semua halaman bersama QR pertama; footer mendukung text, font whitelist,
  size, bold/italic/underline, default bottom safe area, dan posisi per halaman;
  PDF signed tidak boleh mendapat footer baru atau perubahan footer;
- endpoint prepared rendition memvalidasi seluruh placement/footer, merender
  footer server-side, menyimpan revision/hash, dan mengembalikan exact preview;
  perubahan editor menginvalidasi revision lama;
- satu signer/step boleh mempunyai N placement. Buat satu attempt dengan N
  operations; jalankan satu request BSrE per QR secara serial dengan output
  sebelumnya sebagai input berikutnya;
- simpan intermediate output sebagai checkpoint immutable non-current. Resume
  dari operation pertama yang belum completed; jangan mengulang operation
  completed atau menjalankan request paralel;
- secret passphrase multi-operation dapat dibaca terbatas sampai terminal/TTL,
  tidak diambil destruktif setelah operasi pertama; expiry setelah partial
  meminta re-entry dan resume attempt yang sama;
- outcome ambigu pada operasi mana pun menghentikan pipeline sebagai `unknown`;
  jangan lanjut ke QR berikut atau blind retry;
- final artifact baru dipromosikan dan step diselesaikan setelah seluruh
  operasi serta final verify berhasil;
- mode invisible tetap tidak memerlukan placement;
- endpoint verify berdasarkan document/artifact ID, bukan upload ulang dari
  browser;
- typed `VerificationResultData` untuk valid/invalid/no-signature/error;
- cache verify hanya berdasarkan immutable original artifact version/SHA-256;
- job verifikasi unique/locked dan asynchronous; bukti sandbox 3,99 MiB dengan
  8 signature memerlukan sekitar 40-43 detik sehingga request view/delivery
  tidak boleh menunggu BSrE;
- migration additive `user_positions.pdf_watermark_required BOOLEAN NOT NULL
  DEFAULT TRUE`, explicit audited backfill posisi existing, management UI/API
  terotorisasi, serta audit perubahan before/after;
- satu delivery policy untuk preview/view/download: posisi nyata `true` selalu
  watermark, posisi nyata `false` boleh original, Admin Super acting selalu
  efektif `false`, Admin Super pada posisi nyata mengikuti flag posisi itu, dan
  guest selalu public-watermarked setelah public-access policy lulus;
- inventaris application-wide seluruh PDF user-facing: artifact TTE,
  payment/lampiran, SK posisi, laporan/export, preview, Base64/Blob, thumbnail,
  temporary URL, serta static/legacy path; PDF non-artifact harus memakai
  `PdfDeliverySource` adapter dan policy/resolver yang sama;
- renderer watermark server-side yang mempertahankan vector/text/signature dan
  menangani MediaBox/CropBox/Rotate/UserUnit/mixed-size pages;
- watermark authenticated berisi label SITANGKAS, random COPY-ID, nama, jabatan,
  dan unit kerja; jangan tampilkan NIK/NIP/email/IP/session/raw database ID;
- cache derivative private fixed 12 jam dengan key artifact/principal/context/
  policy/template, atomic lock, temp file + atomic publish, cleanup command dan
  scheduler; setiap kegagalan fail-closed tanpa original fallback;
- `document_watermark_copies`, `document_access_events`, dan
  `document_artifact_verifications` atau schema ekuivalen yang memenuhi kontrak
  canonical untuk COPY-ID, audit append-only, dan cache status verify;
- route public `/verify/{public_id}` menampilkan metadata exact artifact dengan status,
  nomor dokumen bila ada, nama signer, dan tanggal signature;
- `document_artifact_signatures` menjadi read model hasil verifikasi agar QR
  scan tidak memanggil BSrE setiap request;
- route delivery memakai policy dokumen terlebih dahulu lalu resolver rendition
  tunggal. Guest hanya memperoleh public-watermarked PDF jika public-access
  policy mengizinkan; selain itu menerima intended-login. Authenticated delivery
  mengikuti flag/acting rule dan seluruh hasil diaudit;
- legacy route tipe+UUID melakukan exact mapping dan redirect `302`; gunakan
  `301` hanya setelah parity mapping lulus;
- halaman public verify server-rendered memakai Blade + Bootstrap/Argon, bukan
  Svelte island;
- job `VerifySignedDocument` hanya bila asynchronous verify dibutuhkan; job ini
  tidak membawa passphrase dan harus idempotent/unique;
- invalidasi cache ketika artifact version berubah.

Acceptance matrix backend:

- satu QR dan beberapa QR pada signer/step yang sama;
- progress counter, crash-resume dari checkpoint, secret expiry setelah partial,
  serta outcome unknown pada operasi tengah;
- satu compatibility before/after/TTE event per aggregate, bukan per QR;
- public ID unik per QR diaktifkan atomik dan resolve final exact artifact serta
  signature spesifik;
- A4 portrait dan landscape;
- rotation 0/90/180/270;
- mixed page sizes;
- page pertama/tengah/terakhir;
- placement dekat semua tepi;
- NaN/infinite/negative/out-of-bounds/oversize ditolak;
- artifact berubah setelah session dibuat menghasilkan conflict;
- verify tidak mengharuskan browser mengirim file PDF;
- response verification sudah disanitasi dan stabil untuk frontend;
- guest tidak pernah memperoleh original/private path, authenticated unauthorized
  tetap ditolak, seluruh authorized delivery tercatat, dan no-watermark original
  hanya diberikan kepada context yang efektif `false`;
- view dan download untuk context yang sama menghasilkan keputusan rendition
  yang sama; tidak ada split flag atau endpoint bypass;
- marked/original/public watermark diuji untuk inline, attachment, range request,
  cache hit/miss/concurrency, cleanup, position switch, revoked position, serta
  kegagalan renderer/storage/cache;
- tidak ada bypass pada SK posisi, laporan/export, payment attachment, Blob/
  Base64, thumbnail/page image, temporary URL, atau static legacy PDF;
- setiap QR versi lama/baru resolve exact artifact, bukan latest artifact.

## 9. Phase 7 - Backend Ready Gate

Frontend baru boleh dimulai setelah semua gate berikut terpenuhi:

### Contract gate

- endpoint, method, request, response, HTTP status, dan application error code
  sudah dibekukan dan didokumentasikan;
- response sandbox sign/verify/status telah dapat di-decode secara deterministik;
- aturan coordinate, ukuran file, timeout, dan single/multi-file sudah diputuskan;
- `EsignGateway` dapat diganti fake tanpa mengubah Action/controller.

### Security dan correctness gate

- policy dan Form Request mengotorisasi setiap endpoint;
- server menentukan document, signer, path, workflow, dan capability;
- passphrase tidak ditemukan pada database, serialized queue payload,
  `failed_jobs`, log, session, event, atau exception context; secret store/cache
  private terenkripsi ber-TTL adalah satu-satunya persistence sementara;
- atomic lock/fingerprint dan state `unknown` bekerja;
- file private, versioning, SHA-256, staging, verify, dan finalization bekerja;
- resolver delivery tunggal, acting override `false`, guest public watermark,
  cache/audit/cleanup, dan larangan original bypass dibuktikan fail-closed;
- credential lama sudah dirotasi dan secret production tidak berada di source.

### Performance dan operations gate

- memory/latency diuji terhadap ukuran historis terbesar yang relevan;
- base64 dibuat satu kali dan tidak tercopy ke log/error;
- health check read-only, metric latency/error/state, cleanup staging, dan alert
  `unknown` tersedia;
- terdapat prosedur reconciliation untuk attempt `unknown`; sign tetap tidak
  di-retry otomatis;
- queue `signatures`, worker heartbeat, secret TTL, job timeout, dan stuck
  attempt recovery tersedia;
- deployment migration/index tabel besar mempunyai backup, lock assessment,
  dan rollback plan.

### Quality gate

- test backend yang telah mendapat izin eksplisit menggunakan `Http::fake()`
  dan `Http::preventStrayRequests()` serta lulus;
- static analysis/formatting yang berlaku lulus;
- tidak ada request otomatis ke BSrE production;
- API handoff untuk frontend memuat contoh sukses, gagal, conflict, dan unknown.

Jika salah satu gate belum lulus, status tetap **backend in progress** dan agent
tidak boleh memasang frontend baru untuk menutupi backend yang belum stabil.

## 10. Phase 8 - frontend Svelte/Vite foundation

Phase ini dimulai hanya setelah Backend Ready Gate dan perubahan dependency
diotorisasi:

- pasang Svelte dan Vite plugin yang kompatibel dengan Vite project;
- gunakan TypeScript;
- tambah entry/dynamic import eSign;
- buat typed API client langsung dari contract backend yang sudah dibekukan;
- buat `EsignApp`, event adapter, dan satu root global;
- buat shell dua modal dengan markup/class Bootstrap 5 dan skin Argon;
- gunakan Bootstrap JavaScript API tanpa plugin jQuery;
- tambahkan CSS khusus yang minimal dan ter-scope di bawah `.esign-ui`;
- ikuti `body.dark-version`; jangan memakai utility Tailwind pada eSign;
- lazy-load bundle hanya pada halaman yang memiliki capability TTE/validasi.

Acceptance:

- tidak ada pengetahuan endpoint/credential BSrE di browser;
- tidak ada jQuery di dalam modul Svelte;
- modal dapat dibuka dari adapter dan lifecycle Bootstrap dapat dibersihkan;
- Bootstrap/Argon/font/icon tidak dibundel ulang;
- CSS tidak memengaruhi Blade, Argon, DataTables, atau Select2 di luar root.

## 11. Phase 9 - frontend signing modal

Implementasikan mode invisible terlebih dahulu untuk membuktikan UI terhadap API
yang sudah stabil:

1. event open membawa encrypted document reference saja;
2. modal memanggil prepare session;
3. preview terotorisasi sesuai rendition hasil resolver backend dan metadata aman
   ditampilkan; backend sign tetap memakai original canonical;
4. tahap konfirmasi meminta reason dan passphrase;
5. passphrase dikirim satu kali ke endpoint sign; respons `202` membawa attempt
   UUID dan frontend langsung membersihkan passphrase;
6. UI menampilkan `prepared/queued`, `signing`, `validating`, `succeeded`,
   `failed`, atau `unknown` melalui polling/optional realtime;
7. modal dapat ditutup tanpa membatalkan background job; saat dibuka kembali UI
   mengambil status attempt dari server;
8. success event dikirim ke adapter halaman untuk refresh data;
9. close/unmount membersihkan passphrase, object URL, AbortController, dan state.

Acceptance:

- modal tidak bertumpuk;
- submit ganda dicegah di UI dan tetap ditolak backend;
- passphrase tidak masuk store persisten, URL, console, analytics, atau browser
  storage;
- raw vendor error tidak tampil;
- unknown outcome mengunci retry cepat dan menampilkan attempt reference;
- focus, keyboard, backdrop, close guard, mobile, dan dark mode bekerja.

## 12. Phase 10 - frontend visible editor dan validation modal

### Visible editor

- PDF hanya berasal dari authorized backend binary stream; tidak ada upload,
  replace file, path, atau Base64 JSON;
- lazy-load PDF renderer/worker;
- render halaman aktif/sekitar secara virtual dan thumbnail resolusi rendah;
- sediakan zoom/page navigation serta DOM overlay drag/resize untuk satu atau
  beberapa QR;
- simpan placement dalam coordinate canonical, bukan CSS pixel;
- preview client-side tidak menggantikan backend bounds validation;
- tampilkan capability visible/invisible yang diberikan backend.
- pada PDF unsigned, QR pertama membuat footer seluruh halaman dengan editor
  text, font, size, bold/italic/underline, posisi per halaman, apply-all, dan
  reset; pada PDF signed tidak tampilkan editor footer;
- di dalam tahap confirmation, tampilkan authorized rendition dari exact
  prepared artifact backend bersama informasi signer dan passphrase. Setiap
  perubahan placement/footer harus meminta revision baru;
- confirmation menjelaskan jumlah operasi, tidak menampilkan checkbox afirmasi,
  dan klik tombol final mengirim `affirmed=true`; progress menampilkan
  `i dari N`;
- state `partially_signed` meminta passphrase baru untuk resume attempt yang
  sama, sedangkan `unknown` tidak menyediakan blind retry;
- custom CSS harus ter-scope dan mengikuti Bootstrap 5/custom Argon, tanpa
  Tailwind atau nested modal.

### Validation modal

- load PDF dan hasil verify secara paralel;
- tampilkan valid/invalid/no-signature/error secara berbeda;
- render signer, waktu, reason, certificate, dan status sebagai escaped text;
- cache berdasarkan artifact version/hash;
- revoke object URL dan terminate worker saat close.
- jangan memberi toggle watermark/original; bersihkan blob/object URL ketika
  modal tutup, user berganti posisi/context, artifact berubah, atau unmount.

Acceptance:

- matrix portrait/landscape/rotation/mixed-size/edge/zoom lulus;
- browser tidak download lalu upload ulang PDF untuk verify;
- modal responsive memakai Bootstrap/Argon pada desktop, tablet, dan mobile;
- screen reader, keyboard, focus return, `aria-live`, dan reduced motion bekerja;
- bundle PDF tidak masuk halaman tanpa fitur eSign.

## 13. Phase 11 - pilot satu payment

Pilot yang direkomendasikan adalah satu flow LS yang mempunyai artifact,
authorization, dan workflow paling stabil.

Urutan:

1. aktifkan feature flag hanya untuk scope pilot;
2. pasang adapter tombol TTE/validasi baru tanpa menjalankan handler legacy
   bersamaan;
3. gunakan proof invisible yang sudah tersedia sebagai baseline, lalu jalankan
   visible satu QR pada scope pilot;
4. setelah visible satu QR stabil, buktikan multi-QR serial pada dokumen uji
   yang memang membutuhkan beberapa placement untuk signer sama;
5. pantau latency per operasi/aggregate, memory, progress, partial/unknown,
   staging/intermediate cleanup, dan
   hasil verify;
6. bandingkan audit/history/workflow dengan expected behavior lama;
7. tetapkan rollback trigger dan rollback window.

Pilot dianggap lulus bila tidak ada credential leakage, duplicate sign,
artifact corrupt/0-byte, authorization bypass, atau attempt unknown yang tidak
dapat direkonsiliasi.

## 14. Phase 12 - rollout lintas payment dan production cutover

1. inventaris semua include, button, route, dan global function TTE lama;
2. kelompokkan payment berdasarkan artifact dan workflow yang serupa;
3. migrasikan satu kelompok per batch dengan feature flag;
4. verifikasi role, position, unit, year, stage, dan signer setiap kelompok;
5. observasi sebelum melanjutkan batch berikutnya;
6. hapus dual handler dan route legacy hanya setelah tidak ada caller;
7. hapus bundle/component lama setelah rollback window berakhir;
8. siapkan health check read-only, dashboard attempt, alert, retention, cleanup,
   reconciliation, capacity baseline, TLS/whitelist, dan runbook operator;
9. jalankan uji penerapan BSrE/SPS dan checklist cutover/rollback;
10. nonaktifkan endpoint v1 setelah bukti pencarian code dan traffic memastikan
    tidak ada caller tersisa.
11. alihkan seluruh consumer laporan `before_signs`/`after_signs` ke reporting
    contract yang bersumber dari tabel canonical bila siap, sambil tetap
    mempertahankan compatibility write append-only;
12. buat backup final, checksum mapping, restore drill, dan rollback window;
13. copy-verify-activate seluruh file dari `File_{TYPE}` ke layout canonical
    berdasarkan artifact year/month, termasuk arsip 2024-2025 yang belum ada
    pada subset 90 hari;
14. hapus folder legacy hanya setelah manifest/hash parity, restore drill,
    compatibility URL, dan rollback window lulus;
15. rekonsiliasi canonical-versus-compatibility secara berkelanjutan; tidak ada
    freeze/archive/drop tabel dalam scope aktif. Perubahan tersebut hanya boleh
    diusulkan setelah gate lengkap dan keputusan pengguna baru.

Jangan menganggap pilot LS membuktikan authorization dan workflow payment lain.

## 15. Test plan (memerlukan izin eksplisit)

### Backend unit

- payload invisible/visible;
- coordinate transform;
- response mapper sukses/error/malformed;
- signer resolver authenticated user + posisi aktif tanpa NIK manual;
- request fingerprint;
- state transitions;
- sanitization/redaction.

### Backend feature

- prepare session authorized/forbidden/conflict;
- sign sukses dengan `Http::fake()`;
- invalid passphrase/certificate/provider error;
- timeout menjadi unknown;
- duplicate request ditolak;
- verify dan private preview/download;
- matrix delivery posisi flag true/false, Admin Super acting, Admin Super posisi
  nyata, guest public/nonpublic, serta authenticated tanpa posisi;
- COPY-ID/audit append-only, cache derivative 12 jam, lock/concurrency, cleanup,
  dan fail-closed tanpa original fallback;
- cache/job verifikasi asynchronous selalu membaca original canonical artifact;
- audit/history/compatibility writes;
- `Http::preventStrayRequests()` memastikan test tidak menyentuh vendor.

### Frontend unit/component

- modal states dan transitions;
- passphrase cleanup;
- API error mapping;
- event open/completed;
- coordinate conversion;
- close/focus/keyboard behavior;
- object URL/PDF worker cleanup.

### Browser/manual

- seluruh coordinate matrix;
- responsive modal;
- screen reader/focus/keyboard;
- slow network/offline/timeout;
- double click dan navigation while signing;
- DataTable refresh adapter;
- no secret pada storage/console/network logs selain request body HTTPS yang
  memang menuju backend sign.

## 16. Definition of done

Integrasi tidak boleh disebut selesai sebelum:

- endpoint v2 dan response contract terbukti di sandbox;
- credential lama sudah dirotasi/dibersihkan;
- `BsreClient` dan boundary backend baru aktif;
- source of truth document/signer/authorization berada di server;
- hanya mode `SELF_SIGN` yang aktif; `PREPARE_FOR_SIGNER` tidak tersedia;
- signer termasuk Admin Super menempatkan QR/footer dan memasukkan passphrase
  sendiri tanpa input NIK manual;
- passphrase hanya berada sementara pada encrypted TTL secret store; tidak masuk
  database, serialized queue payload, `failed_jobs`, session, event, atau log;
- seluruh TTE user berjalan asynchronous melalui queue `signatures`, tetap
  berjalan setelah koneksi user terputus, dan tidak melakukan blind retry;
- state unknown dan duplicate protection bekerja;
- private artifact versioning dan verify bekerja;
- satu flag `pdf_watermark_required` menegakkan rendition yang sama untuk
  preview/view/download; acting Admin Super efektif `false`, guest public selalu
  watermark, dan seluruh delivery teraudit tanpa direct public/original bypass;
- modal sign dan validasi Svelte menggantikan UX lama;
- visible coordinates lulus matrix;
- browser memuat exact authorized PDF sebagai binary stream tanpa upload atau
  Base64 JSON;
- footer editable hanya dirender pada PDF unsigned dan exact prepared preview
  cocok dengan byte sebelum sign pertama;
- satu signer dapat menyelesaikan N QR serial dengan satu klik/passphrase,
  checkpoint dapat resume tanpa mengulang operasi completed, dan final artifact
  baru current setelah seluruh operasi serta verify lulus;
- satu attempt multi-QR hanya membuat satu projection before/after/TTE legacy;
- legacy caller sudah dimigrasi atau dinyatakan jelas belum;
- observability, cleanup, dan reconciliation tersedia;
- test yang disetujui sudah lulus;
- production uji penerapan/cutover selesai;
- dokumentasi diperbarui dari rencana menjadi kondisi aktual.

## 17. Pertanyaan/blocker yang harus diselesaikan

1. Apa response sukses/error resmi sign dan verify v2?
2. Bagaimana pairing dan partial success multi-file?
3. Apa limit file/request dan rate limit vendor?
4. Apa coordinate origin/unit pada seluruh rotation/page size?
5. Apa matrix final creator, signer sequential, verifier, rejector, dan
   capability akses untuk setiap `payment_type + src_type + workflow_variant`?
   Mode byte sesudah akses bukan blocker lagi: wajib mengikuti satu flag
   `pdf_watermark_required` dan aturan acting/guest yang sudah dikunci.
6. Nilai konfigurasi final per tipe dokumen untuk batas QR (baseline target
   maksimal lima), ukuran minimum, margin aman, font whitelist, footer default,
   page rotation, dan aturan overlap.
7. Berapa retention attempt, staging, sanitized vendor metadata, dan encrypted
   secret TTL final setelah observasi beban produksi?
8. Pilot backend pertama sudah dipilih: LS SPP jalur BP -> PPTK -> PA. Scope
   rollout operasional setelah proof tersebut masih harus ditetapkan.
9. Apakah provider mempertahankan seluruh signature valid pada rangkaian
   visible sign serial satu PDF, termasuk shape response dan latency setiap
   operasi? Ini wajib dibuktikan; array collection bukan jawaban kontrak.
