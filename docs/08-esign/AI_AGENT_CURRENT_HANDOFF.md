# Handoff Aktual eSign, TTE, Secure PDF Viewer, dan Watermark

Tanggal verifikasi terakhir: **27 September 2026**.

Dokumen ini adalah pintu masuk kondisi aktual untuk AI agent yang melanjutkan
pekerjaan eSign/PDF. Dokumen desain tetap menjadi sumber keputusan rinci, tetapi
bila tracker lama berbeda dengan fakta source, route, migration, atau database,
gunakan urutan berikut:

1. keputusan eksplisit pengguna paling baru;
2. dokumen ini untuk snapshot aktual;
3. `CURRENT_ESIGN_IMPLEMENTATION.md` untuk detail implementasi;
4. dokumen desain domain terkait;
5. kode, route, schema, dan konfigurasi runtime yang diverifikasi ulang.

Jangan menyalin credential, password, NIK signer, passphrase, token, atau isi
`.env` ke dokumentasi, log, response, job payload, maupun source code.

## 1. Ringkasan eksekutif

- Integrasi baru memakai eSign Client BSSN/BSrE `2.2.0` dengan endpoint API
  `v2` dan concrete client bernama `BsreClient`.
- Backend provider, schema canonical, workflow/step, attempt, multi-operation,
  prepared rendition, worker serial, partial resume, final verification,
  compatibility projection, dan endpoint internal tersedia di source.
- Frontend TTE memakai Svelte island melalui Vite di dalam aplikasi Blade,
  Bootstrap 5, dan custom Argon Dashboard Pro 2. Frontend bukan SPA/SvelteKit.
- F0-F13 frontend selesai di source. Snapshot konfigurasi lokal saat verifikasi:
  frontend aktif, multi-operation aktif, queue `signatures`, maksimum lima QR,
  footer default 7,5 pt, QR profile `malangkota-logo-v1`, dan contract proof
  produksi nonaktif.
- Entry point final action per dokumen berada pada modal Detail Dokumen, bukan
  kolom action main table payment.
- Editor/signing legacy sudah dicabut dari halaman payment. General secure PDF
  viewer menggantikan viewer `.view-pdf`/arbitrary URL pada scope yang sudah
  dipindahkan.
- R0-R8 secure viewer/delivery selesai di source. R9 preflight LS SPP ORIGINAL
  sudah lulus secara read-only terhadap artifact canonical dan checksum.
- Migration `user_positions.pdf_watermark_required` sudah diterapkan pada batch
  27. Seluruh 1.514 posisi pada snapshot database masih `false`.
- Watermark derivative, COPY-ID, persistent delivery session, append-only
  document access audit, queue `pdf-delivery`, dan public-watermarked guest
  delivery belum diimplementasikan.
- Full acceptance manual workflow LS SPP BP/BPP -> PPTK -> PA/KPA dan rollout
  lintas payment belum boleh dianggap selesai.
- Public `/verify/{public_id}`, legacy QR redirect, mapping semua file legacy,
  serta decommission folder/tabel lama masih backlog.
- Larangan membuat atau menjalankan automated test suite tetap aktif.

## 2. Keputusan bisnis yang tidak boleh diubah diam-diam

### 2.1 Provider dan metode tanda tangan

1. Client konkret bernama `BsreClient`, bukan `BsreV22Client`.
2. Browser tidak pernah berkomunikasi langsung dengan BSrE.
3. Scope awal hanya NIK + passphrase. NIK diselesaikan backend dari user signer;
   modal tidak menyediakan input NIK.
4. TOTP dan metode email tetap backlog setelah jalur awal stabil.
5. Scope bisnis awal hanya `SELF_SIGN`. `PREPARE_FOR_SIGNER`, proxy-sign, dan
   Admin Super menandatangani untuk orang lain tidak diimplementasikan.
6. Passphrase tidak boleh masuk database, session, log, event, response,
   `failed_jobs`, atau serialized queue payload. Passphrase hanya boleh berada
   pada secret store terenkripsi dengan opaque reference dan TTL terbatas.

### 2.2 Signer, Admin Super, dan workflow

1. Signer menempatkan QR/footer sendiri, memasukkan passphrase sendiri, dan
   menandatangani certificate miliknya sendiri.
2. Admin Super tidak mempunyai hak TTE universal.
3. Admin Super pada posisi bisnis nyata miliknya diperlakukan sebagai pengguna
   biasa. Acting like adalah effective context berbeda dan tidak mengubah
   certificate owner.
4. Acting Admin Super tidak dapat dipakai untuk TTE.
5. TTE multi-signer wajib berurutan. Output signer sebelumnya menjadi source
   signer berikutnya.
6. Step berikutnya baru di-assign dan diaktifkan saat handoff, bukan otomatis
   ketika TTE step sebelumnya selesai.
7. Setiap submit/handoff harus memastikan step canonical terkait sudah
   `completed` sebelum projection `submit`, `assigned_to`, atau `users_to`
   berubah.
8. SP2D hanya ditandatangani BUD atau Kuasa BUD yang dipilih oleh Verifikator.
9. Reject step mengembalikan paket kepada pembuat untuk revisi. BUD/Kuasa BUD
   dapat menolak seluruh paket sebelum BANK mengisi `finished_at`.
10. Cancel/close sebelum final sign hanya membersihkan state sementara dan tidak
    membuat attempt, `document_process`, atau audit bisnis.

### 2.3 Asynchronous signing dan failure semantics

1. Endpoint final sign memvalidasi intent, menyimpan attempt/secret reference,
   dispatch setelah commit, lalu mengembalikan `202 Accepted`.
2. HTTP ke BSrE tetap sinkron di dalam dedicated worker `signatures`.
3. Setelah `202`, proses server tetap berjalan bila modal/browser ditutup,
   koneksi user terputus, atau user logout.
4. Sign provider tidak memakai blind retry. Job sign memakai `tries=1`.
5. Failure sebelum provider dispatch dapat retry dengan aman dan meminta
   passphrase baru bila diperlukan.
6. Outcome setelah request mungkin terkirim menjadi `unknown` atau
   `reconciliation_required`; browser tidak boleh menekan retry biasa karena
   berisiko tanda tangan ganda.
7. Verify yang read-only boleh memakai retry terkendali.
8. Vendor code `2031` dipetakan menjadi invalid passphrase yang retryable.

## 3. Kontrak canonical dan compatibility legacy

### 3.1 Tabel canonical

Domain canonical aktif meliputi:

- `document_artifacts` untuk byte/path/version chain;
- `document_signing_workflows`, `document_signing_steps`, dan workflow events;
- `esign_attempts`, signature properties, signature operations, provider
  responses, attempt events, dan legacy links;
- artifact signatures/certificates;
- artifact decorations dan decoration placements;
- `esign_migration_runs` dan `esign_migration_items` sebagai schema kontrol
  mapping resumable.

`esign_attempts.document_id` wajib untuk attempt baru, immutable, dan terindeks
untuk reporting. Attempt legacy orphan boleh tetap nullable. Multi-QR mempunyai
operation checkpoint sendiri; detail per QR tidak dipaksakan ke tabel legacy.

### 3.2 Enam tabel operasional lama tetap hidup

Tabel berikut tetap dipertahankan:

- `document`;
- `document_process`;
- `anggaran_kegiatan`;
- `anggaran_kegiatan_temp`;
- `before_signs`;
- `after_signs`.

Keputusan final saat ini:

1. `document` dan dua tabel anggaran tetap menjadi projection operasional bagi
   controller/Blade yang belum seluruhnya direkonstruksi.
2. `document_process`, `before_signs`, dan `after_signs` adalah compatibility
   ledger append-only. History invalid, orphan, ambigu, atau tidak lengkap
   tidak dihapus dan tidak diperbaiki otomatis.
3. Satu attempt multi-QR tetap memproyeksikan satu `before_signs`, maksimal satu
   terminal `after_signs`, dan satu event `TTE` setelah aggregate sukses.
4. Detail operation QR, response aman BSrE, retry, dan failure disimpan pada
   schema canonical.
5. Tidak ada izin untuk drop/freeze tabel legacy. Decommission membutuhkan
   keputusan pengguna baru setelah mapping, parity, consumer cutover, backup,
   restore proof, dan rollback gate lulus.

## 4. Artifact, storage, mapping, dan URL verifikasi

1. Canonical PDF berada pada private storage dengan target layout:

   ```text
   {source|signed|failed-output}/{YYYY}/{MM}/{uuid-prefix}/{uuid}.pdf
   ```

2. Tahun artifact ditentukan terutama dari event `UPLOAD` atau `TTE` pada
   `document_process`, bukan hanya dari nama folder lama.
3. Semua mapping database dan file harus resumable, idempotent, zero-downtime,
   mempunyai checkpoint/high-watermark, lease/heartbeat, crash-safe `.part`,
   checksum, pause/resume, catch-up, `needs_review`, dan recovery manifest.
4. Mapping selalu copy-verify-activate; tidak menghapus source.
5. Snapshot folder public lokal hanya sebagian dari arsip 2024-sekarang dan
   tidak boleh dianggap parity penuh.
6. Runner/command mapping file historis belum diimplementasikan walaupun tabel
   kontrolnya sudah ada.
7. QR baru memakai target stabil:

   ```text
   https://sitangkas.malangkota.go.id/verify/{public_id}
   ```

8. Setiap QR mempunyai public ID exact artifact/signature operation. Beberapa
   QR dalam satu klik TTE tetap mempunyai URL masing-masing.
9. URL legacy `/File_{TYPE}/sign/{uuid}.pdf` kelak harus di-resolve melalui
   exact mapping lalu redirect `302` ke `/verify/{public_id}`. Redirect `301`
   hanya setelah parity stabil.
10. Halaman verify publik hanya menampilkan status, nomor dokumen bila ada,
    nama signer, dan tanggal TTE. Download untuk user login tetap melalui
    authorization; guest hanya boleh memperoleh public-watermarked derivative
    bila dokumennya memang public.
11. Public verify dan legacy redirect belum diimplementasikan.

## 5. Editor visible dan multi-QR

### 5.1 Source PDF dan geometry

1. Editor tidak menerima upload PDF atau physical path dari browser.
2. Backend me-resolve exact canonical artifact dan mengirim binary
   `application/pdf`; PDF tidak dikirim sebagai Base64 JSON.
3. Browser hanya mengedit overlay. Backend memvalidasi top-left coordinates,
   page metadata, safe area, collision, revision, dan source fingerprint.
4. Backend membuat exact prepared rendition. Perubahan QR/footer membuat
   prepared revision lama tidak berlaku.

### 5.2 QR

1. Satu step/signer boleh mempunyai beberapa QR pada satu atau beberapa
   halaman.
2. Satu klik `Tandatangani Sekarang` dan satu passphrase membuat satu attempt
   dengan N operation serial.
3. Output operation QR 1 menjadi input operation QR 2, dan seterusnya.
4. QR final memakai logo Kota Malang di tengah dengan profile backend
   `malangkota-logo-v1`.
5. QR dapat dipindah, di-resize, dan dihapus langsung dari overlay.
6. QR baru ditempatkan di pusat area halaman yang paling terlihat pada
   workspace, bukan di pusat seluruh dokumen atau layar.

### 5.3 Footer

1. Saat PDF belum mempunyai TTE, QR pertama membuat footer default pada seluruh
   halaman.
2. Footer tidak ditambahkan lagi pada PDF yang sudah mempunyai TTE.
3. Text, font whitelist, ukuran, bold, italic, underline, posisi, dan ukuran box
   dapat diedit.
4. Default font size adalah 7,5 pt dan langkah perubahan 0,1 pt.
5. Teks tetap rata tengah; editor dan prepared renderer memakai wrapping/box
   yang sama.
6. Footer dapat dihapus per halaman sehingga scope menjadi selected pages.
7. Keputusan apakah seluruh footer boleh dihapus masih terbuka dan harus dikunci
   sebelum acceptance F14.

### 5.4 Confirmation dan result

1. Tetap satu modal dengan state internal, bukan modal editor ditumpuk modal
   passphrase.
2. Prepared preview, informasi dokumen/signer, ringkasan QR/footer, dan input
   passphrase berada pada tahap Konfirmasi yang sama.
3. Tidak ada checkbox `Saya telah memeriksa dokumen`; klik tombol final adalah
   afirmasi eksplisit dan frontend mengirim `affirmed=true`.
4. Setelah sukses, tombol `Selesai` berada pada body bersama notifikasi sukses,
   bukan pada modal footer.

## 6. Frontend aktual

F0-F13 selesai di source:

| Tahap | Kondisi |
|---|---|
| F0 | Kontrak frontend-backend dan gap dikunci |
| F1 | Bridge action LS SPP dengan `step_public_id` |
| F2 | Svelte/Vite island global dan lazy loader |
| F3 | Event bridge Blade/DataTable/Svelte |
| F4 | Shell modal Bootstrap/Argon responsive |
| F5 | Typed API, response guard, dan error normalization |
| F6 | Binary PDF.js viewer, lazy canvas/thumbnail, cleanup |
| F7 | Geometry canonical top-left/point |
| F8 | Multi-QR drag/resize/delete/order |
| F9 | Footer editable/resize/delete selected pages |
| F10 | Prepared rendition dan confirmation terpadu |
| F11 | Idempotent final submit dan `202` |
| F12 | Polling, progress, partial resume, terminal result |
| F13 | Validasi exact artifact dan private preview |
| F14 | Acceptance manual LS SPP belum lengkap |
| F15 | Public verify/result delivery belum |
| F16 | Rollout payment/retirement belum |

Layout aktual:

- desktop memakai panel thumbnail, workspace, dan inspector; thumbnail serta
  inspector tetap/floating terhadap scroll workspace dan mempunyai scroll
  internal;
- workspace saja yang menggulir halaman PDF;
- semua halaman berada dalam document flow dan canvas dirender lazy;
- halaman dapat diaktifkan dari workspace maupun thumbnail tanpa memaksa scroll
  kembali ke atas;
- tablet/mobile memakai workspace fullscreen dan menyembunyikan thumbnail serta
  inspector pada viewport sempit; drawer/offcanvas belum dibuat;
- toolbar memakai indikator `aktif/total`, navigasi, reset/tambah QR, dan zoom;
- general viewer menampilkan informasi dokumen, validation summary, source, dan
  delivery mode.

Runtime snapshot lokal 27 September 2026:

```text
SIGNATURE_FRONTEND_ENABLED=true
SIGNATURE_MULTI_OPERATION_ENABLED=true
SIGNATURE_QUEUE=signatures
SIGNATURE_CONTRACT_PROOF_ENABLED=false
max_operations=5
footer_default_font_size_pt=7.5
```

Nilai deployment harus selalu diverifikasi ulang melalui `config()`. Jangan
menyalin nilai credential provider dari `.env` ke dokumen.

## 7. Relokasi action dokumen R0-R9

| Tahap | Hasil aktual |
|---:|---|
| R0 | Ownership UI dikunci: main table untuk paket, modal Detail untuk action dokumen |
| R1 | Inventaris read-only route/source/consumer/bypass selesai |
| R2 | `document_contract` typed per row Detail/DetailTbp selesai |
| R3 | Resolver canonical -> legacy-private -> legacy-public selesai |
| R4 | Action/capability resolver view/download/verify/sign selesai |
| R5 | General secure PDF viewer Svelte read-only selesai |
| R6 | Row Detail/DetailTbp memakai viewer/editor baru; signing legacy dicabut |
| R7 | Modal coordinator parent Detail -> viewer/editor selesai |
| R8 | History dan direct legacy surfaces dipindahkan; `.view-pdf` dan Blade viewer lama dicabut |
| R9 | LS SPP ORIGINAL preflight canonical/checksum lulus; acceptance user nyata masih perlu |

Viewer universal saat ini menerima opaque encrypted ID dan resource terkontrol:
`document`, `billing`, `spj_fungsional`, serta exact `history`. Endpoint selalu
melakukan authorization ulang dan tidak mengekspor disk/path fisik. Response
binary memakai no-store/nosniff/same-origin dan header
`X-Sitangkas-Pdf-Delivery-Mode: original`.

History tidak selalu diarahkan ke current PDF. Resolver memakai exact artifact
mapping bila tersedia, lalu exact `document_process.src_name` legacy-private
atau legacy-public secara read-only. History invalid tetap dipertahankan.

## 8. Kebijakan PDF original dan watermark

### 8.1 Keputusan final

1. Tepat satu flag posisi dipakai:

   ```text
   user_positions.pdf_watermark_required
   ```

2. Flag berlaku seragam untuk view, download, dan signing preview.
3. Flag bukan permission; authorization dokumen selalu diperiksa lebih dahulu.
4. Posisi nyata `false` menerima exact current canonical/original setelah
   authorization lulus.
5. Posisi nyata `true` kelak hanya menerima identified-watermarked derivative.
6. Admin Super acting like selalu efektif `false`, tetapi tetap harus lulus
   effective document policy.
7. Guest tidak pernah menerima original; dokumen public kelak memakai public
   watermark, dokumen private ditolak/login.
8. Watermark derivative tidak pernah menjadi source signing atau verify BSrE.

### 8.2 Status implementasi

- migration flag sudah `Ran` pada batch 27;
- 1.514 posisi existing bernilai `false`; tidak ada posisi `true` pada snapshot;
- model mempunyai boolean cast;
- mode `original` sudah ada pada backend contract, TypeScript guard, UI info,
  log context, dan response header;
- authorization saat ini fail-closed bila posisi nyata tiba-tiba bernilai
  `true`, karena renderer watermark belum tersedia;
- Admin Super acting tetap mengikuti override original;
- command `pdf-delivery:pilot-ls-original` sudah lulus untuk LS SPP canonical,
  termasuk pemeriksaan header PDF, ukuran, dan SHA-256;
- Management User toggle/audit flag belum tersedia; jangan mengubah posisi
  menjadi `true` secara manual;
- persistent delivery session, watermark copies, access events, verification
  summary, renderer, cache/lock/job/cleanup belum tersedia.

Status phase P0-P18:

| Phase | Status |
|---:|---|
| P0 | Selesai: kontrak bisnis |
| P1 | Selesai: inventaris read-only |
| P2 | Selesai dan migration diterapkan: flag default false |
| P3 | Belum: schema session/copy/access/verification |
| P4 | Selesai di source: universal source adapter/resolver |
| P5 | Sebagian: authorization dan ORIGINAL bridge; decision watermark belum |
| P6 | Sebagian: opaque direct content/download; persistent session/audit belum |
| P7 | Belum: persistent asynchronous artifact verification |
| P8 | Selesai di source: viewer/cutover legacy; manual cross-payment pending |
| P9 | Preflight source lulus; manual acceptance user/posisi nyata pending |
| P10-P18 | Belum |

## 9. Endpoint dan command penting

Endpoint internal TTE yang tersedia:

```text
POST   /esign/internal/signing-sessions
GET    /esign/internal/signing-sessions/{session}
DELETE /esign/internal/signing-sessions/{session}
GET    /esign/internal/signing-sessions/{session}/preview
POST   /esign/internal/signing-sessions/{session}/renditions
GET    /esign/internal/signing-sessions/{session}/renditions/{revision}/preview
GET    /esign/internal/signing-sessions/{session}/renditions/{revision}/operations/{index}/qr
POST   /esign/internal/signing-sessions/{session}/sign
GET    /esign/internal/attempts/{attempt}
POST   /esign/internal/attempts/{attempt}/resume
GET    /esign/internal/artifacts/{artifact}/verification
GET    /esign/internal/artifacts/{artifact}/verification/preview
```

Endpoint delivery/viewer:

```text
GET /document/pdf/{document}/{resource}/viewer
GET /document/pdf/{document}/{resource}/content
GET /document/pdf/{document}/{resource}/download
GET /document/history-pdf/{history}/viewer
GET /document/history-pdf/{history}/content
GET /document/history-pdf/{history}/download
```

Command operasional yang tersedia:

```text
esign:prove-visible-contract
esign:cleanup-prepared-renditions
pdf-delivery:pilot-ls-original
```

`esign:prove-visible-contract --live` hanya boleh dijalankan secara terkontrol
oleh operator dengan feature gate dan credential runtime; jangan menjalankannya
sebagai verifikasi otomatis.

## 10. Acceptance yang sudah dan belum terbukti

Sudah terbukti:

- contract proof invisible/visible yang didokumentasikan;
- schema canonical dan multi-operation terpasang;
- provisioning terkontrol menghasilkan artifact/workflow/step/event;
- prepared rendition dan serial operation tersedia di source;
- TypeScript, Vite build, PHP lint/Pint, route inspection, Blade compilation,
  migration pretend/status, serta static invariant checks pada implementasi
  terakhir;
- R9 command read-only lulus pada LS SPP canonical dengan delivery mode
  `original`, source `canonical`, dan SHA-256 cocok.

Belum boleh diklaim:

- full F14 BP/BPP -> PPTK -> PA/KPA pada traffic nyata terkontrol;
- process manager production, shared cache/lock, worker heartbeat, monitoring,
  alert, dan graceful restart;
- recovery setelah full page reload;
- reconciliation outcome `unknown` dan stuck recovery;
- public `/verify/{public_id}` dan legacy QR redirect;
- watermark renderer/COPY-ID/delivery queue/cache/cleanup;
- P8 acceptance manual lintas semua payment dan authorization negative cases;
- mapping seluruh data/file 2024-sekarang dan parity external consumer;
- rollout action TTE canonical selain workflow yang telah diprovisikan.

## 11. Urutan pekerjaan paling tepat berikutnya

1. Jalankan acceptance manual R9 viewer ORIGINAL memakai user/posisi nyata:
   view, validation, download, denied lintas scope, signed/unsigned, history,
   billing, dan SPJ Fungsional.
2. Jalankan F14 vertical slice LS SPP terkontrol sampai BP/BPP -> PPTK -> PA/KPA
   dan validasi projection canonical/legacy.
3. Pastikan production process manager worker `signatures`, shared cache/lock,
   queue monitoring, timeout, failed-job handling, dan graceful restart.
4. Implementasikan reconciliation `unknown`, stuck recovery, cleanup, metrics,
   alert, dan operator correlation lookup.
5. Lanjutkan secure delivery P3 lalu P5-P7 sebelum membuat watermark derivative:
   session/access audit/verification summary dan authoritative decision service.
6. Implementasikan P10-P13: renderer/COPY-ID, cache-lock-job-cleanup,
   Management User toggle beraudit, lalu enforcement tanpa original fallback.
7. Pilot watermark LS P15 sebelum signing preview P16 dan rollout P17.
8. Implementasikan public verification/legacy QR resolver dengan policy guest.
9. Bangun runner mapping resumable; pilot satu payment, catch-up, reporting
   cutover, parity, lalu decommission hanya dengan keputusan baru.

## 12. Keputusan yang masih terbuka

- apakah semua footer boleh dihapus atau wajib minimal satu footer;
- process manager/topologi multi-node production, shared cache, dan shared
  derivative storage;
- final matrix workflow/signing untuk payment selain vertical slice yang sudah
  dianalisis;
- klasifikasi artifact yang benar-benar public bagi guest;
- retention audit access, derivative, verification summary, dan cleanup;
- Range request strategy untuk PDF besar;
- recovery discovery attempt setelah full page reload;
- metode signer tambahan selain NIK + passphrase;
- apakah `PREPARE_FOR_SIGNER` kelak dibutuhkan; saat ini tetap tidak dibuat.

## 13. Larangan untuk agent berikutnya

- Jangan membuat, mengubah, atau menjalankan Pest/PHPUnit/test suite kecuali
  pengguna secara eksplisit mencabut larangan.
- Jangan menyalin secret dari `.env`, Postman collection, chat, log, atau
  project lama.
- Jangan mengaktifkan posisi `pdf_watermark_required=true` sebelum renderer dan
  seluruh route scope-nya fail-closed.
- Jangan mengirim original kepada posisi `true` atau guest sebagai fallback.
- Jangan menerima path, NIK, delivery mode, authoritative artifact, atau user
  position bebas dari browser.
- Jangan mengunggah ulang PDF dari editor/viewer untuk sign atau verify.
- Jangan menjalankan operasi multi-QR secara paralel.
- Jangan blind retry attempt sign atau mengubah `unknown` menjadi failed tanpa
  reconciliation.
- Jangan menghapus file/tabel/history legacy, termasuk record invalid.
- Jangan menjadikan main table payment sebagai lokasi final tombol TTE per
  dokumen.
- Jangan menghidupkan kembali `.sign`, `.signModal`, `bundle.js`, viewer
  arbitrary `data-url`, atau direct public `/File_*` sebagai solusi cepat.
- Jangan menganggap source-ready, route/class tersedia, atau preflight lulus
  sama dengan production rollout selesai.

## 14. Peta baca lanjutan

- Keputusan utama: `README.md`
- Kondisi implementasi rinci: `CURRENT_ESIGN_IMPLEMENTATION.md`
- Kontrak backend: `ESIGN_V2_CONTRACT_AND_BACKEND.md`
- Workflow/authorization: `ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md`
- Visible/multi-QR: `ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`
- Frontend/cutover legacy: `ESIGN_FRONTEND_IMPLEMENTATION_AND_LEGACY_MIGRATION_PLAN.md`
- Visual frontend: `ESIGN_FRONTEND_VISUAL_AND_INTERACTION_DESIGN.md`
- Compatibility tabel legacy: `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`
- Lifecycle/storage/reporting: `ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md`
- Mapping resumable: `ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`
- Kebijakan watermark: `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`
- Rencana watermark: `PDF_DELIVERY_WATERMARK_IMPLEMENTATION_PLAN.md`
- Inventaris source/route: `PDF_DELIVERY_R1_READ_ONLY_INVENTORY.md`
- Security containment: `PHASE_0_SECURITY_CONTAINMENT_REPORT.md`
- Provider proof: `PHASE_1_SANDBOX_CONTRACT_REPORT.md`

