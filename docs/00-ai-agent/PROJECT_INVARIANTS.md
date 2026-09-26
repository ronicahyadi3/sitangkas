# Project Invariants For AI Agents

Ini ringkasan aturan yang tidak boleh dilanggar lintas domain.

## Akun dan autentikasi

- `users` menyimpan state akun saat ini, bukan histori lengkap.
- Histori autentikasi ditulis ke `login_events`.
- `login_events` bersifat append-only: tidak ada update/delete alur normal.
- Route Management Users untuk parameter `{user}` dan nested `{position}`
  memakai encrypted route key; numeric route param polos tidak boleh diterima.
- Encrypted route key hanya obfuscation URL. Authorization dan scope tetap wajib
  dicek pada setiap aksi.
- Enrichment `login_events` mengikuti
  `../01-authentication/LOGIN_EVENTS_ENRICHMENT_POLICY.md`; `null` pada kolom
  device, GeoIP, ASN, VPN/proxy/Tor, risk, timezone, fingerprint, integrity,
  atau retention berarti data belum tersedia atau belum diperiksa, bukan bug.
- IP risk enrichment untuk VPN/proxy/Tor sedang di-hold; jangan mengaktifkan
  provider berbayar, free-tier, atau provider parsial tanpa persetujuan user
  dan decision baru.
- Password, token, cookie, session id mentah, passphrase, dan credential tidak boleh disimpan di metadata, notes, atau log.

## Master data

- `jabatans`, `instansis`, dan `unit_kerjas` adalah master konteks kerja.
- Business logic memakai `kode` yang stabil, bukan numeric `id` atau `nama`.
- Master yang sudah dipakai tidak dihapus fisik. Gunakan `is_active = false` atau soft delete administratif bila perlu.
- `unit_kerjas.instansi_id` harus konsisten dengan `user_positions.instansi_id`.

## Posisi pengguna

- `user_positions.is_active` berarti posisi tersedia, bukan posisi session yang sedang dipakai.
- Posisi aktif request disimpan di session sebagai `active_user_position_id`.
- Satu user boleh memiliki banyak posisi aktif.
- Kombinasi operasional `user_id`, `jabatan_id`, `instansi_id`,
  `unit_kerja_id` harus memiliki tepat satu posisi canonical. Import legacy
  yang disetujui dapat mempertahankan row duplikat sebagai alias non-selectable
  setelah forward migration canonical/alias tersedia.
- Pada import legacy, satu NIK tidak boleh mempunyai dua posisi aktif pada
  kombinasi jabatan, instansi, dan unit kerja yang sama. Setelah organisasi
  dinormalisasi, row aktif dengan `created_at` terbaru menjadi canonical; bila
  sama, legacy ID terbesar menang. Row lama tetap ada sebagai alias
  soft-deleted agar referensi dokumen lama tidak terputus.
- Untuk import `dump-keuangan-202609090855.sql`, kontrak identitas yang disetujui
  adalah `legacy users.id = target user_positions.id`, sedangkan
  `user_positions.user_id` tetap menunjuk akun target hasil distinct NIK.
- Kolom legacy `uuid` dan `access` tidak digunakan. `access` tidak boleh
  dipetakan ke `tahun_aktif` atau permission tahun historis.
- Sejak reset 2026-09-09, tabel `users` dan `user_positions` sengaja kosong agar
  seluruh akun dan posisi berikutnya berasal dari import legacy. Jangan jalankan
  seeder akun/posisi atau membuat data manual sebelum import selesai.
- Detail dan blocker import users legacy berada di
  `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md`. Pipeline read-only sudah
  mencakup source reader, organization resolver, account aggregator, position
  classifier, analyzer `--dry-run`, dan `LegacyUserImportValidator`.
- Action write legacy `ImportLegacyUsers` sudah tersedia. Safety gate
  `legacy_import.execution.enabled` saat ini `true` karena diaktifkan operator
  untuk maintenance attempt 2026-09-16. AI agent tidak boleh menjalankan
  `--commit` secara otomatis; setelah commit berhasil atau attempt dibatalkan,
  gate wajib dikembalikan ke `false`. Opsi
  `--commit` dan `--fingerprint=` sudah terdaftar pada command. Mode commit
  wajib menghitung ulang analyzer read-only, membandingkan fingerprint penuh,
  mensyaratkan 0 blocker, dan meminta konfirmasi ketik interaktif berupa
  `IMPORT LEGACY USERS {prefix fingerprint}`. `--no-interaction` wajib ditolak;
  setelah konfirmasi command memanggil action, structured log, dan writer
  laporan `completed`/`failed`.
- Validator pasca-import wajib tetap dijalankan di dalam transaksi sebelum
  commit. Validator mencocokkan jumlah row dan fingerprint SHA-256 akun,
  posisi, serta target gabungan; memvalidasi password tanpa mencatat material
  password/hash; memeriksa status, canonical/alias, referensi histori
  `document`/`document_process`; mencocokkan ID maksimum; dan memastikan kolom
  primary key memakai atribut `AUTO_INCREMENT`. Jangan memakai nilai
  `information_schema.TABLES.AUTO_INCREMENT` sebagai blocker sebelum commit
  karena metadata tersebut dapat stale selama explicit-ID insert masih berada
  dalam transaksi. Blocker invariant wajib me-rollback seluruh import.
- Attempt commit 2026-09-16 05:23 UTC di-rollback penuh pada stage
  `post_import`; target sesudahnya tetap 0 `users` dan 0 `user_positions`.
  Penyebabnya hanya dua false negative counter `AUTO_INCREMENT`. Validator
  sudah diganti menjadi pemeriksaan ID maksimum dan atribut schema. Jangan
  reset/truncate target atau menghidupkan kembali pemeriksaan counter tersebut.
- Fingerprint source maintenance attempt saat ini adalah
  `db31cc474ae465954797422f204143f669c125f1363e218e59796cb5e9f7ccf3`.
  Fingerprint boleh dipakai ulang hanya jika dry-run terbaru tetap sama dan
  blocker tetap 0.
- Import users legacy bersifat one-time. Jangan membuat tabel/model
  `legacy_user_import_batches` atau audit event per row. Bukti eksekusi memakai
  laporan JSON private, structured log yang disanitasi, fingerprint sumber,
  backup target, dan transaksi rollback.
- Laporan commit users wajib ditulis melalui
  `LegacyUserImportCommitReportWriter` ke disk private. Laporan gagal hanya
  menerima stage, failure code, jumlah blocker, dan fingerprint opsional;
  jangan meneruskan exception mentah atau material sensitif.
- Strategi password produksi import legacy sudah final:
  `preserve_legacy_hash_force_change`. Hash canonical dipertahankan dan seluruh
  akun hasil import wajib mengganti password setelah login pertama.
- Shared password import hanya merupakan override development lokal. Default
  harus nonaktif, nilai password tidak boleh berada di repository/audit/log,
  dan resolver wajib memblokir override pada environment selain `local`.
- Dry-run wajib memvalidasi password policy dan hanya menampilkan metadata
  strategi; password maupun hash tidak boleh masuk laporan JSON atau console.
- Status akun legacy sudah diputuskan: `active` hanya jika minimal satu posisi
  canonical aktif, nondeleted, dan referensinya valid; selain itu `inactive`.
  Baseline yang harus divalidasi adalah 930 active dan 123 inactive, terdiri
  dari 62 akun tanpa posisi aktif dan 61 akun dengan seluruh row terhapus.
- File SK legacy hanya boleh diimpor bila file fisiknya tersedia dan valid.
  Missing, rusak, `.filepart`, dan file orphan di-skip serta dilaporkan; jangan
  menghapus atau memperbarui sumber legacy yang read-only.
- `public/SuratKeterangan` hanya staging source, bukan storage final. Dokumen SK
  hasil import wajib berada pada disk private dan diunduh melalui authorization.
- Import users/positions memakai `file_sk_strategy=defer`; dokumen diproses oleh
  analyzer/action terpisah setelah `user_positions` tersedia.
- Keputusan `file_sk_strategy=defer` sudah dikunci. Gate users importer yang
  sedang aktif tidak mengizinkan import file SK; dokumen tetap proses terpisah.
- Action import legacy hanya boleh dipanggil oleh
  `legacy:import-users --commit` setelah guard fingerprint dan konfirmasi
  operator. Jangan memanggilnya dari route, controller, scheduler, job, Tinker,
  atau command lain.
- Sebelum import user legacy, target `users` dan `user_positions` harus tetap
  kosong. Validator preflight harus lulus dan fingerprint sumber harus sama
  dengan snapshot yang disetujui.
- Dokumen SK disimpan di `user_position_documents`, bukan di `user_positions`.
- Flow Management Users membuat akun terlebih dahulu, lalu menambahkan posisi.
  Jangan membuat posisi awal otomatis tanpa decision baru.
- Posisi pertama user baru dari Management Users harus lewat
  `UserManagementAccessService::canAttachPositionToUser()`.

## Login context dan Admin Super

- `/positions` adalah route canonical untuk memilih posisi nyata dari
  `user_positions`; `/login/context` hanya legacy redirect/compatibility.
- `login.post` adalah flow resmi Admin Super untuk memilih acting context manual.
- MFA memakai TOTP kompatibel Google Authenticator; wajib untuk real active
  position Admin Super dan optional/enrollable untuk user non-Admin Super.
- Policy MFA resmi berada di `config('auth.mfa')`.
- Admin Super tidak boleh masuk `login.post`, dashboard, atau route internal
  sensitif sebelum MFA session valid.
- Admin Super acting context adalah overlay session sementara, bukan record `user_positions`.
- Modul internal tidak boleh membaca session `acting_*` langsung; gunakan service context resmi.
- `CurrentUserContext::realActivePosition()` berarti posisi asli dari `user_positions`.
- `CurrentUserContext::activePosition()` berarti effective context yang dipakai modul.
- Untuk Admin Super acting context, `activePosition()->id` tetap id real Admin
  Super; scope operasional harus memakai `jabatan_id`, `instansi_id`, dan
  `unit_kerja_id` effective dari `activePosition()`.
- Saat real active position berubah dari atau ke Admin Super, acting context harus dibersihkan sesuai `../01-authentication/AUTH_CONTEXT_DECISIONS.md`.
- Secret TOTP, kode OTP, recovery code mentah, QR provisioning URI, dan payload
  MFA mentah tidak boleh disimpan di audit/log.
- Lock/unlock akun dari Management Users adalah Admin Super only.
- Reset password dari Management Users adalah Admin Super only; password
  sementara hanya boleh ditampilkan satu kali dan tidak boleh masuk audit/log.
- Reset MFA dari Management Users adalah Admin Super only; PA/KPA tidak boleh
  reset MFA user lain.
- Remember-me default 24 jam dan harus configurable; sumber policy adalah
  `config('auth.remember_me.duration_minutes')`, guard `web.remember`, dan
  `users.remember_token_expires_at`.

## Izin tahun historis

- Izin tahun historis melekat pada `user_position_id`, bukan hanya `user_id`.
- Permission tahun historis tidak memperluas role dasar.
- Semua pemberian, penggunaan, penolakan, pencabutan, dan kedaluwarsa permission harus dapat diaudit.

## Migration dan install

- Fresh install harus divalidasi dari urutan migration dan foreign key, bukan hanya dari `php -l`.
- Jika migration menyentuh tabel master atau posisi pengguna, baca `../06-migrations/FRESH_INSTALL_READINESS.md`.
- Jangan menjalankan migration production sebelum konflik order, missing table, dan duplicate constraint selesai.

## BSrE, TTE, dan validasi dokumen

- Sumber keputusan integrasi berada di `../08-esign/README.md` dan dokumen
  detail yang dirujuknya. Collection Postman 2.2.0-beta dan project lama hanya
  merupakan bukti referensi, bukan spesifikasi produksi yang lengkap.
- Kondisi source/deployment terakhir wajib dibaca dari
  `../08-esign/CURRENT_ESIGN_IMPLEMENTATION.md`. Pada snapshot 23 September
  2026, fondasi source Phase 3-5 dan 13 tabel canonical sudah tersedia pada
  database lokal. Dua migration index mapping legacy masih `Pending`;
  provisioning serta dedicated worker sudah dibuktikan lokal. Vertical slice
  LS SPP mempunyai lazy activation, submit gate, dan assignment/activation
  signer saat handoff, tetapi belum lulus TTE end-to-end. Visible/public
  verification, reconciliation, dan frontend belum ada.
  Jangan menyamakan keberadaan class/route dengan fitur production-ready.
- Integrasi baru menargetkan eSign Client 2.2.0/API v2. Concrete service client
  wajib bernama `BsreClient`, bukan `BsreV22Client`.
- Vertical slice pertama hanya mengimplementasikan signing NIK + passphrase.
  NIK + TOTP, email + passphrase, dan email + TOTP tetap backlog eksplisit;
  jangan memperluas scope sebelum jalur awal stabil.
- Browser tidak boleh memanggil BSrE secara langsung. Basic Auth, endpoint
  internal provider, signer identity sensitif, dan material credential hanya
  boleh berada pada boundary backend dan secret store/environment server.
- Konfigurasi runtime eSign hanya dibaca melalui `services.bsre_esign` dan
  `BsreConfiguration`. Saat aktif, URL, credential service, TLS, timeout,
  default reason/location, dan seluruh path `/api/v2/*` harus lulus validasi
  fail-closed sebelum provider dipanggil. Source/example selalu default
  `BSRE_ESIGN_ENABLED=false` dan `BSRE_ESIGN_ALLOW_INSECURE_HTTP=false`.
- Endpoint HTTP yang diberikan pemilik hanya diizinkan melalui opt-in lokal
  `BSRE_ESIGN_ALLOW_INSECURE_HTTP=true`; jangan menyalin pengecualian ini ke
  environment lain tanpa keputusan deployment eksplisit.
- Boundary provider resmi adalah `App\Contracts\Esign\EsignGateway` dengan
  implementasi `App\Services\Esign\BsreClient`. Consumer bisnis tidak boleh
  bergantung langsung pada Laravel HTTP client atau response vendor.
- Scope payload sign yang aktif hanya NIK+passphrase, invisible, dan tepat satu
  PDF. `BsreClient` tidak melakukan auto-retry. Connection/5xx sign menjadi
  `esign.outcome_unknown`; raw request/response dan exception transport tidak
  boleh disimpan atau diteruskan.
- Target visible/multi-QR yang sudah disetujui berada di
  `../08-esign/ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`, tetapi belum
  diimplementasikan. Jangan menganggap target tersebut sebagai capability
  source/schema saat ini dan jangan menghapus fail-closed visible sebelum
  contract proof serta seluruh gate backend lulus.
- Editor TTE tidak menerima upload atau path PDF dari browser. Backend harus
  resolve exact canonical artifact paket BP/BPP; browser memuat authorized
  binary `application/pdf`. Base64 hanya boleh dibuat pada boundary backend ke
  BSrE, bukan sebagai transport JSON PDF ke browser.
- Signature presence untuk aturan footer ditentukan backend. PDF tanpa TTE
  membuat satu footer pada seluruh halaman ketika QR pertama ditambah; text,
  font whitelist, size, bold/italic/underline, dan posisi dapat diedit sebelum
  TTE pertama. PDF yang sudah signed tidak mendapat footer baru atau perubahan
  footer. Unknown verification wajib fail-closed.
- Preview overlay browser bukan byte authoritative. Backend memvalidasi seluruh
  placement/footer, merender exact prepared preview, menghitung hash/revision,
  dan final sign hanya menerima prepared revision yang masih valid. Cancel
  sebelum attempt dibuat membersihkan temporary state tanpa audit bisnis.
- Satu signer/step boleh mempunyai N QR. Ini tetap satu workflow step dan satu
  `esign_attempts`, tetapi memiliki N operasi provider serial. Output operasi
  sebelumnya menjadi input berikutnya; jangan menjalankan paralel atau membuat
  attempt/event legacy per QR.
- Target multi-QR memerlukan migration additive
  `esign_signature_operations`, attempt progress counters, status
  `partially_signed`, artifact `intermediate_sign`, dan
  `document_artifact_decorations`. Migration canonical yang sudah diterapkan
  tidak boleh diedit. Sampai target dibuat, status attempt aktif tetap matrix
  minimum yang ada.
- Step hanya selesai dan final artifact hanya current setelah semua operasi QR
  sukses serta final verify lulus. Operasi completed tidak boleh diulang.
  Partial yang aman melanjutkan attempt sama dari checkpoint; outcome ambigu
  tetap `unknown`, menghentikan operasi berikutnya, dan menunggu reconciliation.
- Satu aggregate multi-QR hanya memproyeksikan satu `before_signs`, satu
  terminal `after_signs` sesuai contract laporan legacy, dan satu
  `document_process` action `TTE` hanya saat sukses. Intermediate dan detail
  operasi hanya canonical.
- Seluruh TTE runtime berjalan asynchronous dari sisi user melalui dedicated
  queue `signatures`; HTTP BSrE tetap sinkron di dalam worker. Setelah request
  final commit dan `202`, tutup modal/browser atau putus koneksi user tidak
  membatalkan proses server.
- Passphrase signer hanya boleh disimpan sementara pada secret store/cache
  private terenkripsi ber-TTL. Passphrase tidak boleh masuk database, session
  Laravel, event/audit, log, exception context, frontend persistence,
  `failed_jobs`, atau serialized queue payload; job hanya membawa opaque secret
  reference/attempt ID dan secret dihapus pada terminal state.
- Job sign memakai `tries=1` dan `BsreClient` tidak auto-retry sign. Timeout atau
  koneksi ambigu setelah request mungkin terkirim menjadi attempt `unknown` dan
  step `reconciliation_required`; sign baru dilarang sampai reconciliation.
- Signing attempt mengikuti state minimum `prepared -> signing -> validating ->
  succeeded|failed|unknown`. Timeout setelah request terkirim harus menjadi
  `unknown`; jangan otomatis mengulangi sign karena dapat menghasilkan tanda
  tangan ganda. Matrix ini menggambarkan implementasi saat ini; target multi-QR
  menambahkan `partially_signed` melalui migration/enum/service additive.
- Enum/state final berada di `app/Enums/Esign`: workflow
  `draft|active|completed|rejected|needs_review`; step
  `pending|active|signing|reconciliation_required|completed|rejected|skipped|needs_review`;
  attempt `prepared|signing|validating|succeeded|failed|unknown`. Transition
  hanya boleh melalui persistence/state service, bukan update bebas controller.
- `esign_attempts.document_id` memakai signed `INT`, nullable hanya untuk
  histori legacy orphan, wajib untuk runtime baru, immutable setelah insert,
  dan diindeks bersama `created_at`. Nilainya diambil dari workflow server-side,
  bukan dari frontend.
- File sumber, signed PDF, dan bukti verifikasi disimpan pada storage private.
  Integritas file baru memakai SHA-256, staging sebelum final, versioning, dan
  download melalui authorization; jangan memakai public path mentah.
- Kebijakan delivery PDF canonical berada di
  `../08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`. Authorization akses
  dokumen harus lulus lebih dahulu; flag watermark hanya menentukan byte yang
  dikirim dan tidak pernah memberi capability view/download.
- Kebijakan tersebut berlaku application-wide untuk setiap PDF user-facing:
  artifact TTE, payment/lampiran, dokumen SK, laporan/export, preview, route
  legacy, Base64/Blob, temporary URL, dan thumbnail/page-image berisi dokumen.
  Internal server-to-server sign/verify memakai original dan tidak boleh menjadi
  endpoint browser.
- Hanya ada satu flag target pada posisi:
  `user_positions.pdf_watermark_required` dengan default `false` untuk seluruh
  posisi existing dan posisi baru. Jangan memecahnya menjadi flag view dan
  download karena byte yang dapat dilihat juga dapat disimpan. Nilai `true`
  hanya diaktifkan manual per posisi melalui Management User dan wajib
  teraudit.
- Untuk authenticated user dengan posisi nyata aktif dan flag `true`, setiap
  preview/view/download PDF wajib berupa derivative watermark server-side;
  tidak ada endpoint, range request, temporary URL, atau fallback yang boleh
  membocorkan original. Dengan flag `false`, exact current canonical artifact
  boleh dikirim setelah authorization lulus dan delivery tetap diaudit.
- Admin Super dalam mode **acting like** selalu diperlakukan sebagai
  `pdf_watermark_required=false`. Admin Super yang memilih posisi bisnis nyata
  miliknya mengikuti nilai flag pada posisi nyata itu. Pengecualian delivery
  acting tidak mengubah scope, capability, signer identity, atau aturan TTE.
- Guest/no-login hanya dapat menerima PDF setelah public-access policy dokumen
  lulus, dan hasilnya selalu public-watermarked. Guest tidak pernah menerima
  original. Tanpa posisi aktif yang valid, authenticated delivery harus
  fail-closed kecuali rule sistem yang dinyatakan eksplisit.
- Original berarti exact current canonical `document_artifacts` byte yang
  integrity-check-nya lulus; jangan mengambil `src_name`, public legacy path,
  atau file terbaru berdasarkan tebakan. Watermark adalah derivative disposable,
  bukan artifact version dan tidak pernah menjadi input TTE/verifikasi BSrE.
- COPY-ID watermark adalah identifier audit acak, bukan user/document ID mentah
  dan bukan authorization token. Audit view/download/original/watermarked wajib
  append-only. Cache derivative harus user/context/artifact/policy aware, memakai
  lock + atomic publish, TTL tetap 12 jam, serta fail-closed tanpa fallback ke
  original.
- Keputusan lifecycle detail berada di
  `../08-esign/ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md`.
  `document_artifacts` mengatalogkan byte/version chain, `esign_attempts`
  menyimpan summary percobaan, dan `esign_attempt_events` menyimpan chronology
  append-only. Ketiganya tidak boleh digabung menjadi JSON histori pada tabel
  legacy.
- Aplikasi lain masih bergantung pada `before_signs` dan `after_signs` untuk
  laporan. Pertahankan contract dan compatibility dual-write append-only;
  jangan menghapus atau mengubah semantik kolom/status lama sepihak. Mapping,
  parity, dan consumer cutover tidak otomatis menghentikan write atau memberi
  izin drop. Freeze/read-only/archive/drop memerlukan seluruh gate dan keputusan
  pengguna baru pada
  `../08-esign/LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`.
- Enam tabel `document`, `document_process`, `anggaran_kegiatan`,
  `anggaran_kegiatan_temp`, `before_signs`, dan `after_signs` tetap menjadi
  kontrak operasional/kompatibilitas. Tidak boleh rename, drop, mengubah tipe,
  atau mengubah arti kolom/status tanpa keputusan baru dan analisis consumer.
- `before_signs`, `after_signs`, dan `document_process` bersifat append-only
  pada alur normal. Invalid, zero-byte, orphan, dan ambigu dipertahankan sebagai
  evidence serta diberi `needs_review`, bukan dihapus/diperbaiki otomatis.
- `document` tetap operational projection untuk controller/Blade lama;
  workflow/step canonical menjadi state terstruktur TTE. Kolom CSV `status`,
  `submit`, dan `assigned_to` tetap ditulis untuk kompatibilitas dan wajib
  direkonsiliasi dengan canonical.
- `anggaran_kegiatan_temp` tetap katalog pagu/rekening dan
  `anggaran_kegiatan` tetap alokasi rekening per SPP. Audit perubahan anggaran
  memakai event terpisah; jangan memasukkan histori besar ke row operasional.
- File invalid/zero-byte dan artifact historis adalah evidence sensitif. Jangan
  menghapus, menimpa, atau memindahkannya; registrasikan status secara logis dan
  jangan menjadikannya current artifact.
- URL QR canonical baru adalah `/verify/{public_id}` dan harus resolve exact
  immutable artifact version. URL legacy `/File_{TYPE}/sign/{uuid}.pdf` tetap
  harus bekerja melalui resolver/alias karena QR lama tidak dapat diubah tanpa
  merusak PDF signed.
- Legacy URL melakukan exact mapping dan redirect `302` selama migrasi; `301`
  hanya setelah parity stabil. Jangan mengarahkan QR versi lama ke latest
  artifact.
- Halaman verify publik hanya menampilkan status validasi, nomor dokumen bila
  ada, nama signer dari verified PDF/certificate, dan tanggal signature dalam
  `Asia/Jakarta`. Jangan tampilkan NIK, nominal, path, response vendor, actor
  internal, raw certificate, atau hash internal.
- PDF publik hanya boleh tersedia bagi guest jika public-access policy exact
  artifact lulus, dan selalu berupa public-watermarked derivative. Untuk
  dokumen nonpublik guest hanya melihat login action. Semua route delivery
  dirender server-side, tidak membocorkan private path, dan diaudit; login saja
  tidak memberi akses lintas unit/role/tahun.
- `document_artifact_signatures` adalah read model signature per exact artifact
  agar halaman QR tidak memanggil BSrE setiap request. Halaman public verify
  memakai Blade + Bootstrap/Argon; Svelte tetap untuk modal internal.
- Verifikasi BSrE selalu dijalankan terhadap original canonical artifact, bukan
  watermark derivative. Karena pembuktian sandbox pada PDF 3,99 MiB/8 signature
  memerlukan sekitar 40-43 detik, viewer tidak boleh memblokir request delivery;
  gunakan status cache terpisah dan job asynchronous/unique dengan lock. Status
  stale/error tidak boleh diubah menjadi klaim `valid` atau membuka original.
- Satu logical storage root diperbolehkan, tetapi jangan membuat satu flat
  directory. File baru dipartisi berdasarkan role (`source`, `signed`,
  `failed-output`) dan tanggal/prefix; tipe dokumen menjadi metadata database.
- Tahun/bulan storage ditentukan per artifact. Prioritas source adalah
  `document_process` action `UPLOAD`, lalu `document.created_at`,
  `before_signs`, dan filesystem; prioritas signed adalah `document_process`
  action `TTE`, lalu paired `after_signs`, dan filesystem. Jangan memakai
  aktivitas terakhir dokumen atau menebak match ambigu.
- Target akhir memigrasikan semua file dari `File_{TYPE}` ke
  `{source|signed|failed-output}/{YYYY}/{MM}/{uuid-prefix}/{uuid}.pdf` melalui
  copy-verify-activate. Project baru saat ini hanya memuat subset sekitar 90
  hari; file 2024-2025 harus diambil dan diverifikasi dari source/archive lama
  sebelum folder legacy boleh dihapus.
- Jangan direct-move atau menghapus folder legacy sebelum size/SHA-256,
  signature/`ByteRange`, chain, manifest, backup/restore, route compatibility,
  dan rollback window lulus. Invalid/zero-byte tetap merupakan evidence yang
  wajib dipertahankan.
- Semua mapping/backfill TTE wajib mengikuti
  `../08-esign/ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`: persistent checkpoint per
  dokumen/artifact, high-watermark, item state machine, lease expiry, unique
  source key, idempotent handler, heartbeat, pause/resume, throttling, dan
  recovery manifest adalah requirement production.
- Checkpoint item mapping disimpan pada `esign_migration_items.current_stage`
  dengan urutan `discovered -> metadata_mapped -> file_copied ->
  checksum_verified -> canonical_activated -> completed`. Status item final
  adalah `pending|processing|succeeded|retryable_failed|needs_review|failed`.
- Queue mapping bersifat at-least-once dan terpisah dari queue TTE/traffic
  utama. Duplicate job harus aman; `retry_after` harus melebihi timeout. Sign
  BSrE tetap tidak auto-retry, sedangkan mapping/read-only verify boleh retry
  secara rate-limited dengan backoff.
- Mapping berjalan dengan expand-migrate-contract, canonical-first read dengan
  legacy fallback, serta high-watermark + catch-up. Satu item gagal tidak boleh
  menghentikan layanan atau item lain.
- Mapping command tidak pernah menghapus source. Tabel compatibility tetap
  append-only sesuai keputusan aktif. Delete folder legacy hanya melalui
  decommission command/phase terpisah setelah parity, backup/restore,
  compatibility route, observation, dan rollback gate lulus. Perubahan
  lifecycle tabel memerlukan keputusan pengguna baru.
- Server adalah sumber kebenaran untuk dokumen, posisi halaman, signer,
  authorization, workflow state, dan nama/path file. Jangan mempercayai NIK,
  path, status, atau koordinat tanpa validasi dari browser.
- Implementasi awal hanya mempunyai mode bisnis `SELF_SIGN`.
  `PREPARE_FOR_SIGNER` tidak boleh diimplementasikan tanpa keputusan scope baru.
- Semua signer, termasuk Admin Super, menempatkan QR/footer sendiri, memeriksa
  preview, dan memasukkan passphrase miliknya sendiri. Modal tidak meminta NIK;
  backend menyelesaikan NIK dari real authenticated user/certificate owner.
- Admin Super acting context tidak memberi hak proxy-sign, impersonation, atau
  hak sign universal. Acting context tidak mengubah certificate owner. Admin
  Super hanya dapat sign bila dirinya signer sah pada workflow step aktif.
- Bila user Admin Super mempunyai dan memilih posisi bisnis nyata yang assigned
  kepadanya, ia beroperasi sebagai pengguna biasa pada posisi tersebut dan
  bukan acting like. Acting hanya untuk effective position yang bukan posisi
  nyata miliknya. Create/upload diperbolehkan melalui kedua context sesuai role
  dan scope, tetapi TTE tetap `SELF_SIGN` tanpa proxy certificate.
- Menutup/Batal pada placement atau preview sebelum final sign tidak membuat
  audit, history, atau `esign_attempts`. Attempt persisten baru dibuat saat
  tombol sign ditekan; temporary context dibersihkan langsung atau melalui TTL.
- SP2D mempunyai tepat satu signer BUD atau Kuasa BUD sesuai assignment
  Verifikator BUD. Penerima assignment dapat menolak seluruh paket sebelum BANK
  menetapkan `finished_at`; artifact/history lama tidak dihapus.
- Reject langkah hanya dilakukan penerima setelah submit dan mengembalikan paket
  kepada pembuat untuk revisi/resubmit. Data legacy yang ambigu ditandai
  `needs_review`, bukan dihapus atau diperbaiki otomatis.
- TTE multi-signer selalu sequential. Result artifact satu step menjadi current
  artifact workflow dan, pada flow yang memakai handoff, baru diikat sebagai
  source step berikutnya saat assignment/handoff berhasil. Setiap step
  mempunyai placement sendiri.
- Khusus LS SPP, workflow upload tetap `draft`; signing session BP/BPP nyata
  mengaktifkan first step secara lazy. TTE sukses menyelesaikan current step
  tanpa mengaktifkan next step. Handoff BP/BPP mengikat PPTK, handoff PPTK
  mengikat PA/KPA, dan seluruh assignment canonical + projection legacy harus
  commit/rollback dalam satu transaksi setelah `LsSppSubmitGate` lulus.
- Jangan mengembalikan auto-activation next step ke attempt persistence, jangan
  mengubah `document.submit`/`assigned_to`/`users_to` tanpa canonical gate untuk
  dokumen LS SPP, dan jangan fallback ke legacy bila artifact canonical sudah
  ada tetapi workflow hilang.
- Matrix authorization/workflow canonical berada di
  `../08-esign/ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md`. Jangan menyimpulkan
  hak create/sign/download hanya dari tombol Blade atau numeric role legacy.
- Frontend TTE/validasi memakai Svelte sebagai island dalam halaman Blade dan
  dibangun dengan Vite yang sudah ada; jangan mengubah aplikasi menjadi SPA
  penuh atau memakai SvelteKit tanpa keputusan baru.
- Implementasi eSign wajib backend-first. Jangan memasang dependency Svelte atau
  membangun modal eSign sebelum Phase 0-7 dan Backend Ready Gate pada
  `../08-esign/ESIGN_V2_IMPLEMENTATION_PLAN.md` selesai.
- Bootstrap 5 dan custom Argon Dashboard Pro 2 adalah sistem visual utama UI
  TTE. Gunakan komponen/class dan Bootstrap JavaScript API yang sudah dimuat
  layout; Svelte hanya mengelola state/interaksi. Jangan memakai utility
  Tailwind atau membundel ulang Bootstrap/Argon untuk komponen eSign.
- Custom CSS eSign harus minimal, ter-scope di bawah `.esign-ui`/`.esign-modal`,
  memakai token Bootstrap/Argon, mewarisi Open Sans, dan mengikuti
  `body.dark-version`. Jangan mengubah asset Argon vendor secara langsung.
- UX mempertahankan modal seperti project lama, tetapi implementasi baru hanya
  mempunyai dua root modal: signing modal dengan internal state/step dan
  validation modal. Jangan mempertahankan nested modal, jQuery global,
  positional array payload, atau HTML string dari response.
- Kemampuan multi-file, aturan mapping `file[]` ke `signatureProperties[]`,
  response sukses/gagal, ukuran file, timeout, dan mode visible/invisible wajib
  dibuktikan di sandbox 2.2.0 sebelum production. Default awal adalah satu file
  per request provider.
- Credential lama yang pernah berada di kode/config harus dianggap terekspos:
  inventarisasi, revoke/rotate bila masih valid, pindahkan pengganti ke secret
  store, dan sanitasi history/deployment artifact sesuai runbook keamanan.
- Containment source/config/runtime lokal sudah dilakukan sesuai
  `../08-esign/PHASE_0_SECURITY_CONTAINMENT_REPORT.md`, tetapi rotasi credential
  BSrE masih direkomendasikan. Default source/example
  `BSRE_ESIGN_ENABLED` wajib tetap `false`. Pada 17 September 2026 pemilik
  secara eksplisit mengaktifkan `.env` lokal dan mengarahkan probe terbatas ke
  production karena tidak tersedia environment development. Pengecualian ini
  tidak boleh disalin otomatis ke deployment lain.
- Bukti request statis dan matrix live Phase 1 berada di
  `../08-esign/PHASE_1_SANDBOX_CONTRACT_REPORT.md`. Status, failed auth, TOTP,
  certificate chain, verify PDF unsigned/valid/invalid, dan invisible sign
  NIK+passphrase satu file sudah diprobe secara tersanitasi. Jangan menganggap
  visible signing, encrypted/modified verification, koordinat, timeout, limit,
  atau multi-file telah terbukti.

## Testing permission

- AI agent tidak boleh membuat, menambah, memodifikasi, scaffold, atau menjalankan test suite/test file/test command tanpa konfirmasi eksplisit dari user terlebih dahulu.
- Aturan ini mencakup Pest, PHPUnit, browser tests, smoke tests, `php artisan test`, dan filtered test command.
