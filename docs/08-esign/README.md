# eSign Client 2.2.0 / TTE

Tanggal snapshot: **27 September 2026**.

Status: **backend, frontend F0-F13, visible multi-QR, dan secure viewer ORIGINAL
tersedia di source; rollout operasional masih bertahap**. Seluruh migration
canonical, index mapping, multi-operation/decorations, serta
`pdf_watermark_required` sudah diterapkan pada database lokal. Frontend dan
multi-operation aktif pada snapshot konfigurasi lokal, sedangkan contract proof
live nonaktif. R0-R8 relokasi action/viewer selesai di source; R9 preflight LS
SPP ORIGINAL sudah lulus pada artifact canonical dengan checksum sesuai.
Worker/process manager production, acceptance manual LS SPP BP/BPP -> PPTK ->
PA/KPA, reconciliation, public verification, mapping historis resumable, dan
watermark derivative/COPY-ID/audit delivery masih belum selesai. Baca
`AI_AGENT_CURRENT_HANDOFF.md` lebih dahulu, lalu
`CURRENT_ESIGN_IMPLEMENTATION.md` untuk detail source dan batas operasional.

Cluster ini adalah source of truth untuk perombakan proses Tanda Tangan
Elektronik (TTE) SITANGKAS dari integrasi lama menuju eSign Client `2.2.0`
dengan endpoint API `v2` dan frontend modal berbasis Svelte/Vite.

## Urutan baca wajib

Agent yang menyentuh TTE, validasi PDF, modal eSign, file hasil sign, atau
integrasi BSrE wajib membaca berurutan:

1. dokumen ini;
2. [handoff aktual untuk AI agent](AI_AGENT_CURRENT_HANDOFF.md);
3. [kondisi implementasi aktual](CURRENT_ESIGN_IMPLEMENTATION.md);
4. [kontrak frontend-backend V1](ESIGN_FRONTEND_BACKEND_CONTRACT_V1.md) bila
   menyentuh endpoint internal, type TypeScript, request/response, binary media,
   error normalization, polling, action capability, atau gap frontend;
5. [kebijakan delivery PDF, watermark, dan cache verifikasi](PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md)
   bila menyentuh `pdf_watermark_required`, preview/view/download PDF, guest,
   Admin Super acting, COPY-ID, cache derivative, atau cache verifikasi BSrE;
   untuk implementasi lanjutkan ke
   [rencana secure PDF viewer/watermark](PDF_DELIVERY_WATERMARK_IMPLEMENTATION_PLAN.md)
   dan
   [lampiran teknisnya](PDF_DELIVERY_WATERMARK_IMPLEMENTATION_APPENDICES.md),
   serta baca
   [inventaris read-only R1](PDF_DELIVERY_R1_READ_ONLY_INVENTORY.md) sebelum
   mengubah entry point, route, source resolver, atau fallback legacy;
6. [kontrak dan arsitektur backend](ESIGN_V2_CONTRACT_AND_BACKEND.md);
7. [desain editor visible dan multi-QR satu signer](ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md)
   bila menyentuh source PDF editor, binary preview, placement, footer,
   beberapa QR dalam satu step, worker sequential, partial signing, atau
   frontend Svelte;
8. [matriks authorization dan workflow TTE](ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md)
   bila menyentuh signer, Admin Super, posisi aktif, penempatan QR/footer,
   urutan TTE, pembatalan/retry, atau akses view/download;
9. [kontrak enam tabel operasional legacy dan audit canonical](LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md)
   bila menyentuh `document`, `document_process`, `anggaran_kegiatan`,
   `anggaran_kegiatan_temp`, `before_signs`, `after_signs`, dual-write, atau
   compatibility ledger;
10. [lifecycle dokumen, QR, storage, dan kompatibilitas laporan](ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md)
   bila menyentuh file sebelum/sesudah TTE, `document_artifacts`, attempt/event,
   URL verifikasi, `before_signs`/`after_signs`, backfill, atau aplikasi laporan;
11. [runbook mapping resumable dan zero-downtime](ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md)
   bila menyentuh backfill, file copy, queue mapping, checkpoint, lease,
   pause/resume, throttling, catch-up, recovery, atau decommission;
12. [rancangan frontend modal](ESIGN_V2_FRONTEND_MODAL.md) bila menyentuh Blade,
    Svelte, Vite, PDF viewer, koordinat, atau UX;
13. [urutan implementasi frontend dan migrasi dari UI legacy](ESIGN_FRONTEND_IMPLEMENTATION_AND_LEGACY_MIGRATION_PLAN.md)
    bila menyentuh `.sign`/`.signModal`, `esign.blade.php`, `signed.js`,
    `bundle.js`, bridge DataTable, state modal, atau cutover frontend;
14. [rancangan visual dan interaksi frontend](ESIGN_FRONTEND_VISUAL_AND_INTERACTION_DESIGN.md)
    bila menyentuh layout editor, prepared confirmation, passphrase, progress,
    notifikasi, hasil TTE, validasi, responsive UI, atau accessibility;
15. [rencana implementasi](ESIGN_V2_IMPLEMENTATION_PLAN.md);
16. [laporan Phase 0](PHASE_0_SECURITY_CONTAINMENT_REPORT.md) sebelum memakai
    credential atau memulai sandbox;
17. [laporan Phase 1](PHASE_1_SANDBOX_CONTRACT_REPORT.md) sebelum mengunci
    response decoder, error mapping, koordinat, limit, atau multi-file;
18. `../00-ai-agent/PROJECT_INVARIANTS.md`;
19. `../01-authentication/AUTH_CONTEXT_DECISIONS.md` dan
    `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` bila menyentuh
    signer, posisi aktif, atau Admin Super acting context;
20. `../06-migrations/FRESH_INSTALL_READINESS.md` sebelum membuat atau mengubah
    migration;
21. `../99-legacy/OLD_PROJECT_REFERENCE.md` hanya untuk memahami perilaku lama.

Jika TTE dipanggil dari payment LS, baca juga:

1. `../99-legacy/PAYMENT_LS_CURRENT_IMPLEMENTATION.md`;
2. `../99-legacy/PAYMENT_LS_ANALYSIS.md`;
3. `../99-legacy/PAYMENT_LS_IMPLEMENTATION_PLAN.md`.

## Keputusan yang sudah dikunci oleh pengguna

1. Integrasi lama tidak disalin mentah; proses TTE dibangun ulang untuk project
   ini.
2. Provider yang dituju adalah eSign Client `2.2.0`, menggunakan endpoint
   `/api/v2/*` dari koleksi yang diberikan.
3. Concrete service client wajib dinamai `BsreClient`, bukan
   `BsreV22Client`.
4. Vertical slice signing pertama hanya memakai NIK + passphrase. NIK + TOTP,
   email + passphrase, dan email + TOTP menjadi backlog pengembangan setelah
   alur awal stabil.
5. Laravel tetap menjadi backend, source of truth dokumen, authorization
   boundary, dan satu-satunya pihak yang berkomunikasi dengan eSign Client.
6. Frontend TTE menggunakan Svelte yang dibangun melalui Vite. Vite bukan
   alternatif Svelte; Vite adalah bundler, Svelte adalah framework UI.
7. Project tidak diubah menjadi SPA penuh dan tidak memakai SvelteKit untuk
   pekerjaan ini. Svelte dipasang sebagai island pada halaman Blade.
8. Proses TTE dan validasi tetap tampil dalam modal seperti project lama.
9. UX lama dipertahankan sebagai referensi perilaku, tetapi implementasi baru
   tidak boleh bergantung pada jQuery, global mutable state, atau bundle PDF
   lama.
10. Modal signing menggunakan satu modal dengan beberapa tahap internal, bukan
   modal bertumpuk.
11. Modal validasi menampilkan PDF dan hasil validasi dalam satu modal.
12. Sistem visual utama frontend adalah Bootstrap 5 dengan custom Argon
    Dashboard Pro 2 yang sudah dimuat oleh layout aplikasi. Svelte hanya
    mengelola state dan interaksi; markup, komponen, spacing, warna, tipografi,
    modal, dan dark mode harus mengikuti main CSS aplikasi.
13. Tailwind bukan basis styling modal eSign. Keberadaan dependency Tailwind di
    repository tidak boleh membuat agent mencampur utility Tailwind ke komponen
    eSign atau membuat design system kedua.
14. Urutan implementasi tetap backend-first. Kontrak backend visible,
    prepared rendition, operation persistence, worker serial, polling, dan
    resume yang sudah tersedia cukup untuk memulai fondasi/komponen frontend.
    Pilot real, aktivasi feature flag, dan rollout tetap menunggu gate backend
    operasional terkait.
15. Seluruh data `before_signs` dan `after_signs` dimapping ke schema canonical
    tanpa mengubah atau menghapus sumber. Keduanya tetap menjadi compatibility
    ledger append-only, sedangkan canonical menjadi state/audit terstruktur.
    Tidak ada drop dalam scope aktif; freeze/read-only/archive/drop memerlukan
    gate lengkap dan keputusan pengguna baru. Kontrak enam tabel legacy berada
    di `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`.
16. Seluruh file lama akan dimapping dan dimigrasikan dari folder `File_{TYPE}`
    ke layout private canonical
    `{source|signed|failed-output}/{YYYY}/{MM}/{uuid-prefix}/{uuid}.pdf`.
    Tahun ditentukan per artifact dengan `document_process` action
    `UPLOAD`/`TTE` sebagai sumber utama. Migrasi wajib copy-verify-activate;
    folder lama baru dihapus setelah backup, restore, legacy resolver, manifest,
    dan rollback gate lulus.
17. QR baru memakai `/verify/{public_id}` dan selalu menunjuk exact immutable
    artifact version. URL QR lama redirect `302` melalui exact legacy mapping,
    lalu dapat menjadi `301` setelah parity stabil. Halaman verify publik hanya
    menampilkan status, nomor dokumen bila ada, nama signer, dan tanggal
    signature. Guest hanya boleh menerima PDF jika policy public-access untuk
    dokumen tersebut lulus, dan byte PDF-nya selalu memakai public watermark;
    guest tidak pernah menerima original atau private path. Dokumen yang tidak
    publik tetap mengarahkan guest ke login. User login tetap wajib lulus policy
    dokumen sebelum delivery mode ditentukan.
18. Semua mapping database/file wajib resumable dan zero-downtime menggunakan
    persistent checkpoint, high-watermark, item state machine, lease, lock,
    idempotency, crash-safe `.part` copy, pause/resume, throttling, catch-up,
    observability, dan recovery manifest. Mapping tidak pernah menghapus source;
    decommission adalah phase/command terpisah setelah acceptance gate lulus.
19. Implementasi awal hanya mempunyai mode bisnis `SELF_SIGN`.
    `PREPARE_FOR_SIGNER` tidak diimplementasikan dan menjadi backlog opsional.
20. Semua signer, termasuk Admin Super, menempatkan QR/footer sendiri, melihat
    preview rendition sesuai kebijakan delivery PDF, mengafirmasi dokumen, dan
    memasukkan passphrase miliknya sendiri. Backend selalu menandatangani exact
    original canonical artifact, bukan derivative watermark.
21. Modal sign tidak meminta NIK dari user biasa maupun Admin Super. Backend
    menyelesaikan NIK dari user terautentikasi; acting context tidak mengubah
    certificate owner.
22. Admin Super tidak mempunyai proxy-signing atau hak sign universal. Hak
    administratif melihat/mengelola dokumen tidak otomatis memberi hak TTE;
    Admin Super hanya dapat sign bila dirinya signer sah pada step aktif.
23. TTE multi-signer selalu berurutan. Output step sebelumnya menjadi source
    artifact step berikutnya; setiap step mempunyai placement QR/footer sendiri.
    Pada mayoritas flow, signer melakukan TTE terlebih dahulu lalu `SUBMIT`
    untuk handoff. Jangan mengaktifkan signer pertama hanya pada submit bila
    uploader memang signer pertama yang sah. Flow preparer-only, verify,
    routing, dan SP2D mengikuti matrix khususnya.
24. Matrix final per payment/src type harus dibuktikan dari controller backend
    lama dan urutan `document_process`, bukan dari tombol Blade saja.
25. User Admin Super yang memilih posisi bisnis nyata yang memang assigned
    kepadanya beroperasi sebagai pengguna biasa pada posisi tersebut, bukan
    acting like. Acting like hanya untuk effective position yang bukan posisi
    nyata miliknya. Keduanya tidak mengubah certificate owner.
26. Admin Super boleh create/upload melalui posisi nyata atau acting position
    yang sesuai role dan scope; tidak ada proxy-sign atau bypass workflow.
27. SP2D ditandatangani tepat satu BUD atau Kuasa BUD sesuai assignment
    Verifikator BUD.
28. Batal/Tutup saat placement atau preview sebelum final sign tidak membuat
    audit event, history, atau `esign_attempts`; temporary context dibersihkan.
29. Reject langkah mengembalikan paket kepada pembuat untuk revisi/resubmit.
    BUD/Kuasa BUD penerima assignment dapat menolak seluruh paket sebelum BANK
    menetapkan `finished_at`, tanpa menghapus artifact/history lama.
30. Scope unit berlaku selama paket masih di unit; setelah diteruskan ke induk
    SKPD, induk dapat melihat seluruh unit turunannya, sedangkan unit saudara
    tidak saling melihat.
31. Data legacy ambigu tidak dihapus/dikoreksi otomatis; gunakan
    `needs_review` dan resolusi manual beraudit.
32. Seluruh TTE produksi berjalan asynchronous dari sudut pandang pengguna.
    Endpoint final sign hanya memvalidasi, membuat attempt `prepared`, menyimpan
    secret sementara secara aman, dispatch job setelah commit, lalu mengembalikan
    `202 Accepted`. HTTP ke BSrE tetap sinkron di dalam dedicated queue worker.
33. Setelah request final berhasil di-commit, proses tetap berjalan ketika modal
    ditutup, browser ditutup, user logout, atau koneksi internet user terputus.
    Jika request belum diterima server, tidak ada attempt; idempotency key dipakai
    agar pengiriman ulang tidak membuat attempt ganda.
34. Passphrase dilarang masuk database, model, event, log, session, response,
    `failed_jobs`, atau serialized queue payload. Satu-satunya persistence yang
    diizinkan adalah secret store/cache private terenkripsi dengan opaque
    reference dan TTL terbatas. Job hanya membawa attempt ID/secret reference.
35. Job sign tidak melakukan blind retry (`tries=1`) dan HTTP sign tidak
    auto-retry. Error ambigu setelah request mungkin terkirim menjadi attempt
    `unknown` serta step `reconciliation_required`; retry baru dilarang sampai
    rekonsiliasi selesai. Verify yang read-only boleh retry terkendali.
36. Enum dan transition matrix workflow, step, attempt, artifact, provider,
    audit event, serta mapping legacy sudah final. Nilai database tetap string;
    enum PHP berada di `app/Enums/Esign`.
37. `esign_attempts.document_id` adalah signed `INT`, nullable hanya untuk
    histori legacy orphan, wajib untuk attempt baru, immutable setelah insert,
    dan memakai indeks `(document_id, created_at)` untuk laporan. Immutability
    sudah ditegakkan pada model serta persistence service; tabel canonical
    sudah aktif tetapi belum dibuktikan melalui vertical slice runtime.
38. `esign_migration_items.current_stage` menjadi persistent checkpoint dengan
    urutan `discovered -> metadata_mapped -> file_copied -> checksum_verified ->
    canonical_activated -> completed`. Runner/resume belum dibuat.
39. Delivery PDF authenticated memakai satu flag target
    `user_positions.pdf_watermark_required`, bukan flag terpisah untuk view dan
    download. `true` berarti seluruh preview/view/download PDF wajib derivative
    watermark server-side tanpa original bypass; `false` berarti exact original
    canonical boleh dikirim setelah authorization dokumen lulus. Flag tidak
    memberi hak akses dan tidak menggantikan Policy.
40. Nilai default posisi existing dan posisi baru adalah
    `pdf_watermark_required=false`. Watermark authenticated bersifat opt-in;
    hanya posisi terpilih yang diubah manual menjadi `true` melalui Management
    User dan perubahan tersebut wajib teraudit.
41. Admin Super yang benar-benar berada pada mode **acting like** selalu
    diperlakukan sebagai `pdf_watermark_required=false`, sehingga menerima exact
    original setelah authorization lulus. Jika Admin Super memilih posisi bisnis
    nyata yang assigned kepadanya, gunakan nilai flag posisi nyata tersebut.
42. Guest/no-login tidak mempunyai posisi: jika dokumen memang public-access,
    seluruh preview/view/download selalu memakai public watermark. Jika policy
    publik tidak lulus, PDF tidak boleh diberikan.
43. Detail format watermark, COPY-ID, audit delivery append-only, cache derivative
    12 jam, verifikasi BSrE asynchronous, cleanup, dan acceptance criteria berada
    di `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`. Fitur ini masih rancangan dan
    belum boleh dianggap telah diimplementasikan.
44. Editor tidak mengunggah PDF. Backend me-resolve exact canonical artifact
    paket BP/BPP lalu browser memuatnya sebagai authorized binary
    `application/pdf`; Base64 hanya digunakan pada boundary backend-BSrE.
45. Pada PDF tanpa TTE, penambahan QR pertama juga membuat footer di seluruh
    halaman. Text, font whitelist, size, bold, italic, underline, dan posisi
    footer dapat diedit sebelum TTE pertama. PDF yang sudah mempunyai TTE tidak
    mendapat footer baru dan footer lama tidak diubah.
46. Browser hanya menampilkan overlay edit. Backend memvalidasi koordinat dan
    merender exact prepared preview; perubahan placement/footer membatalkan
    prepared revision lama. Cancel sebelum final sign membersihkan temporary
    context tanpa attempt atau audit bisnis.
47. Satu signer/posisi boleh mempunyai beberapa QR dalam satu workflow step.
    Satu klik TTE dan satu passphrase membuat satu attempt berisi N operasi
    provider serial: output QR sebelumnya menjadi input QR berikutnya.
48. Multi-QR satu PDF bukan multi-file provider. Sampai kontrak provider
    dibuktikan, jangan memasangkan banyak `signatureProperties` dengan satu
    file dalam satu request atau menjalankan panggilan sign paralel.
49. Multi-QR membutuhkan migration additive operation/checkpoint, counter
    attempt, artifact `intermediate_sign`, status `partially_signed`, serta
    snapshot decoration/footer. Desainnya berada di
    `ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`; schema dan runtime backend
    terkait sudah tersedia di source tetapi belum aktif secara operasional.
50. Final artifact menjadi current dan workflow step selesai hanya setelah
    seluruh operasi QR sukses serta verifikasi final lulus. Attempt partial
    resume dari operasi pertama yang belum selesai; outcome ambigu berhenti
    sebagai `unknown` dan tidak boleh blind retry.
51. Untuk satu attempt multi-QR, compatibility projection tetap satu
    `before_signs`, maksimal satu terminal `after_signs`, dan satu event `TTE`
    pada `document_process` hanya saat sukses; detail per QR hanya berada di
    schema canonical.
52. Frontend visible tetap Svelte island dalam modal Bootstrap 5/custom Argon.
    CSS editor harus scoped, tidak memakai Tailwind, tidak membuat modal
    bertumpuk, dan menampilkan progress seperti `2 dari 3`.
53. Prepared rendition tetap wajib pada backend, tetapi tidak menjadi layar
    review terpisah. Prepared preview, informasi dokumen/signer, dan passphrase
    digabung pada satu tahap Konfirmasi.
54. Frontend tidak menampilkan checkbox `Saya telah memeriksa dokumen` atau
    checkbox afirmasi lain. Klik tombol `Tandatangani Sekarang` merupakan
    afirmasi eksplisit dan handler mengirim `affirmed=true`.
55. Main table payment hanya menangani status/action paket dan satu pintu
    `Detail Dokumen`. TTE, validasi tanda tangan BSrE, dan Tampilkan PDF berada
    pada row jenis dokumen di modal detail; tombol `verify_data` proses bisnis
    tidak boleh disamakan dengan validasi kriptografis BSrE.
56. General secure PDF viewer menjadi satu-satunya surface view, verification
    summary, dan download terotorisasi. Editor TTE tetap menjadi surface
    placement QR/footer, prepared confirmation, passphrase, attempt, progress,
    resume, dan result.
57. Entry point canonical dipindahkan per `payment_type:src_type`, dimulai dari
    LS SPP. Legacy dan canonical tidak boleh menangani row yang sama; action dan
    direct download lama baru dilepas setelah replacement terkait lulus gate dan
    rollback path tersedia.
58. Viewer/editor yang dibuka dari modal detail dikoordinasikan tanpa tumpukan
    Bootstrap modal pasif. Detail disembunyikan sementara, lalu dibuka kembali
    dan read model terkait di-refresh setelah child surface ditutup/selesai.

Catatan historis frontend 25 September 2026: F0-F5 selesai di source. Svelte island,
event bridge, adapter refresh opt-in, serta shell modal Bootstrap/Argon empat
tahap sudah tersedia. Typed API client dan signing-session state sudah
terhubung. F6 PDF viewer binary dengan worker lokal, thumbnail bertahap,
navigasi, zoom, dan cleanup resource juga selesai di source. F7 geometry
canonical adalah pekerjaan berikutnya. Pada tanggal catatan ini feature flag
frontend masih `false`; kondisi runtime lokal terkini tercatat pada bagian
status paling atas dan `AI_AGENT_CURRENT_HANDOFF.md`.

## Batas keputusan

Hal berikut belum boleh dianggap final hanya berdasarkan koleksi Postman:

- bentuk response sukses/gagal endpoint sign dan verify;
- aturan pasangan indeks `file[]` dan `signatureProperties[]`;
- perilaku partial success pada multi-file;
- orientasi dan satuan `originX`/`originY`;
- ukuran file dan jumlah file maksimum;
- daftar error code resmi versi 2.2.0;
- apakah response sign berupa PDF binary, base64, atau JSON wrapper;
- apakah beberapa visible placement pada satu PDF dapat diproses dalam satu
  request tanpa merusak signature sebelumnya.

Semua butir tersebut wajib dibuktikan dengan dokumentasi resmi tambahan atau
sandbox BSrE sebelum diaktifkan di production.

## Referensi sumber

### Koleksi yang diberikan pengguna

```text
C:\Users\StaDian\Downloads\Esign-Client for User 2.2.0-beta.postman_collection.json
```

Metadata hasil inspeksi:

- nama collection: `Esign-Client for User 2.2.0-beta For Development`;
- schema Postman: collection schema `v2.1.0` (ini versi format collection,
  bukan versi eSign Client);
- SHA-256:
  `72E68FE619C2BDBAFD695D28D51FEB5800F362F9DDB164E16A03553B59D8EE23`;
- authentication collection: HTTP Basic Auth;
- `baseURL`, username, dan password tidak diisi dalam lampiran;
- script collection tidak dijalankan saat analisis.

Koleksi adalah bahan kontrak dan smoke test, bukan source of truth production
yang lengkap karena masih berlabel beta dan tidak memiliki contoh response
sign/verify yang memadai.

### Project lama

Referensi utama read-only:

```text
C:\Apache24\htdocs\sitangkas
```

File perilaku lama yang relevan:

- `app/Services/Esign/BsreClient.php`;
- `app/Services/Esign/BsreResponse.php`;
- `app/Services/Tte/TteService.php`;
- `app/Http/Controllers/Esign/TteController.php`;
- `app/Http/Requests/Tte/TteSignRequest.php`;
- `resources/views/components/esign/esign.blade.php`;
- `resources/views/components/informations/pdfview.blade.php`;
- `public/assets/js/pdf/bundle.js`;
- `public/assets/js/signed.js`.

Jangan menyalin credential, default secret, hardcoded NIK/passphrase, path
publik, atau kontrak request lama dari file-file tersebut.

### Referensi resmi BSrE

- [Integrasi eSign BSrE](https://bsre.bssn.go.id/product/integrasi-esign-bsre/)
- [Pedoman Kriteria Integrasi Sistem](https://bsre.bssn.go.id/doc/juknis/PEDOMAN-KRITERIA-INTEGRASI-SISTEM.pdf)

## Status implementasi saat snapshot

- [x] Koleksi Postman 2.2.0-beta dianalisis secara read-only.
- [x] Integrasi dan UX project lama dianalisis secara read-only.
- [x] Kondisi schema dan volume audit aktif dianalisis secara read-only.
- [x] Nama `BsreClient`, Svelte/Vite island, dan UX modal diputuskan.
- [x] Bootstrap 5/custom Argon Dashboard Pro 2 dikunci sebagai sistem visual UI.
- [x] Urutan backend-first dan Backend Ready Gate dikunci.
- [x] Containment source/config/runtime lokal Phase 0 dilakukan dan dilaporkan.
- [x] Kontrak request collection dan matrix pembuktian Phase 1 diekstrak secara
      tersanitasi.
- [x] Status user, Basic Auth gagal, request TOTP, certificate chain, verify
      PDF unsigned/valid/invalid, dan invisible sign NIK+passphrase satu file
      diuji pada endpoint production atas arahan eksplisit pemilik.
- [x] Arsitektur target dan rencana implementasi didokumentasikan.
- [x] Lifecycle artifact/version chain, QR exact-version, storage terpadu,
      preservation file invalid, serta compatibility reporting lintas aplikasi
      didokumentasikan.
- [x] Requirement mapping resumable/zero-downtime, checkpoint, lease,
      pause/resume, throttling, recovery, dan decommission terpisah
      didokumentasikan.
- [x] Kontrak mempertahankan `document`, `document_process`,
      `anggaran_kegiatan`, `anggaran_kegiatan_temp`, `before_signs`, dan
      `after_signs`, termasuk append-only dan dual-write, didokumentasikan.
- [x] Kebijakan `SELF_SIGN` tunggal, larangan `PREPARE_FOR_SIGNER`/proxy-sign,
      signer menempatkan QR/footer sendiri, dan aturan Admin Super
      didokumentasikan.
- [x] Matriks authorization/workflow versi 1, Admin Super multi-position versus
      acting, assignment SP2D, reject/revision, retry, scope unit-induk,
      pembatalan preview tanpa record, dan `needs_review` didokumentasikan.
- [x] Konfigurasi `services.bsre_esign`, timeout per operasi, endpoint v2,
      validasi fail-closed, dan container binding `BsreConfiguration`
      diimplementasikan.
- [x] Boundary `EsignGateway`, concrete `BsreClient`, DTO sensitif, payload
      builder NIK+passphrase/invisible/satu-file, response mapper, taxonomy
      error, safe telemetry, dan binding container diimplementasikan.
- [x] HTTP fake diagnostic untuk status/verify/sign serta live read-only verify
      unsigned/signed melalui `BsreClient` lulus tanpa membuat signature baru.
- [ ] Credential lama dirotasi/revoke oleh pemilik dan secret sandbox baru
      diprovision melalui secret store.
- [ ] Kontrak sign, signed/encrypted verify, timeout, limit, koordinat, dan
      multi-file v2 dibuktikan lengkap.
- [x] Dependency Svelte dan PDF viewer modular dipasang.
- [x] Fondasi backend eSign v2 diimplementasikan.
- [ ] Test suite Pest untuk kontrak client dibuat dan dijalankan setelah izin
      eksplisit pengguna.
- [x] Model/cast/relasi canonical, state transition workflow/step/attempt,
      row locking, fingerprint, dan immutability `esign_attempts.document_id`
      diimplementasikan pada source; schema tabel canonical sudah diterapkan.
- [x] Policy/authorization SELF_SIGN, signer resolver, encrypted ephemeral
      signing session, context conflict check, dan private preview
      diimplementasikan.
- [x] Artifact staging/finalization/version chain, safe provider response,
      signature/certificate read model persistence, dan compatibility writer
      runtime diimplementasikan.
- [x] Endpoint internal prepare/show/preview/sign/close/status, encrypted TTL
      secret store, dedicated `PerformEsignAttempt`, `202 Accepted`, polling,
      uniqueness, overlap lock, dan `tries=1` diimplementasikan.
- [x] Controller payment memprovisikan source artifact, workflow, dan signer
      step canonical untuk dokumen runtime baru; satu NPD `GU_SKPD` terkontrol
      sudah membuktikan hasilnya pada database dan private storage lokal.
- [x] Dedicated worker `signatures` ditambahkan ke `composer run dev` dan
      terbukti memproses job provisioning lokal tanpa failed job.
- [ ] Worker `signatures` dikelola process manager pada server production dan
      memakai shared cache yang sesuai topology deployment.
- [x] Lazy first-signer activation dan next-signer assignment/activation saat
      submit/handoff diimplementasikan untuk vertical slice LS SPP BP/BPP.
- [x] Submit gate LS SPP memeriksa completed step, succeeded attempt,
      after-sign artifact, compatibility projection, current artifact, dan
      workflow lengkap sebelum legacy assignment berubah.
- [ ] Vertical slice signing canonical diuji end-to-end.
- [ ] Route delivery PDF terotorisasi, guest public-watermarked delivery,
      enforcement `pdf_watermark_required`, audit access, cache derivative,
      route `/verify/{public_id}`, dan resolver URL QR lama diimplementasikan.
- [ ] Migration-control transition service, resumable runner, queue, command,
      dashboard, reconciliation, serta decommission tooling diimplementasikan.
- [x] Frontend modal Svelte F2-F6 diimplementasikan di source; geometry,
      placement, konfirmasi, sign/progress, dan validasi masih bertahap.
- [x] Tiga belas migration tabel canonical dibuat, lolos lint/Pint/dry-run,
      dan diterapkan pada database lokal dalam batch 9-21.
- [ ] Dua migration indeks mapping legacy diterapkan melalui deployment wave
      terpisah setelah capacity/metadata-lock review.
- [x] `esign_attempts.document_id` nullable untuk legacy orphan dan indeks
      `(document_id, created_at)` ditambahkan; kewajiban/immutability attempt
      baru sudah ditegakkan model/persistence service dan menunggu pembuktian
      vertical slice runtime.
- [x] Checkpoint `esign_migration_items.current_stage` dan indeks
      `(esign_migration_run_id, status, current_stage, id)` ditambahkan.
- [x] Sebelas backed enum domain baru untuk workflow, step, attempt, artifact,
      provider, event, serta migration status/stage dibuat di `app/Enums/Esign`,
      melengkapi `EsignErrorCode` yang sudah ada.
- [x] Arsitektur TTE asynchronous, secret TTL terenkripsi, `202 Accepted`,
      polling status, dan larangan blind retry sudah dikunci serta
      diimplementasikan pada endpoint/job internal. Realtime status belum
      diimplementasikan dan tidak menjadi syarat polling awal.
- [x] Model, enum cast, relasi, state transition service, compatibility writer,
      immutable guard, encrypted ephemeral secret store, dan dedicated signing
      job dibuat.
- [ ] Reconciliation runner untuk attempt `unknown`, stuck recovery, staging
      cleanup, health/metric/alert, dan parity report dibuat.
- [x] Domain placement QR/footer, metadata halaman, coordinate validation,
      safe area/collision, exact prepared rendition private, revision/hash,
      preview, invalidation, dan cleanup diimplementasikan.
- [x] Desain source PDF backend, binary browser delivery, editable footer,
      exact prepared preview, dan multi-QR serial satu signer didokumentasikan.
- [x] Contract proof visible serial serta schema/model operation, progress,
      `partially_signed`, `intermediate_sign`, dan decoration/footer snapshot
      telah diterapkan secara additive.
- [x] Renderer footer dan prepared rendition exact diimplementasikan memakai
      `pdfinfo` + `qpdf`, private encrypted session metadata, binary preview,
      dan QR PNG authoritative private per operasi.
- [x] Operation persistence, worker serial checkpoint-aware, intermediate
      artifact, final verification/promotion, aktivasi public ID setelah sukses,
      compatibility projection aggregate, dan resume partial diimplementasikan
      pada source. Feature flag runtime masih default `false` dan acceptance
      vertical slice belum dilakukan.
- [ ] Uji penerapan BSrE dan cutover production selesai.
