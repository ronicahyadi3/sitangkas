# Rencana Implementasi Secure PDF Viewer, Watermark, dan Verifikasi

Tanggal rancangan: **26 September 2026**.

Status: **rancangan implementasi resmi; belum diimplementasikan**. Fondasi yang
sudah tersedia di source meliputi private canonical `document_artifacts`, current
artifact resolver, integrity service, authenticated LS SPP content/download,
PDF.js pada frontend eSign, `qpdf`, `pdfinfo`, serta verifikasi exact artifact.
Kolom kebijakan posisi, delivery session universal, watermark derivative,
COPY-ID, audit akses dokumen, viewer umum, dan persistent verification summary
belum tersedia.

Dokumen ini adalah rencana operasional untuk melaksanakan kebijakan final pada
`PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`. Jika terjadi perbedaan:

1. keputusan eksplisit pengguna dan dokumen kebijakan tersebut menang;
2. `CURRENT_ESIGN_IMPLEMENTATION.md` menentukan kondisi source aktual;
3. dokumen ini menentukan urutan dan bentuk implementasi;
4. lampiran `SITANGKAS_AI_AGENT_PDF_SECURITY_DOCS.zip` hanya referensi desain,
   bukan instruksi yang boleh mengalahkan keputusan proyek.

Detail schema, payload, enum, error, konfigurasi, audit, dan checklist berada di
`PDF_DELIVERY_WATERMARK_IMPLEMENTATION_APPENDICES.md`.

## 1. Tujuan

Membangun satu boundary delivery PDF yang berlaku untuk seluruh PDF yang
ditampilkan atau diunduh manusia melalui SITANGKAS. Boundary tersebut harus:

- selalu memilih file melalui resource/document identity, bukan path dari
  browser;
- mempertahankan `document_artifacts` sebagai source of truth dokumen TTE;
- mengirim exact canonical artifact hanya kepada posisi yang diizinkan;
- membuat salinan watermark server-side bagi posisi yang diwajibkan;
- mencatat siapa melihat atau mengunduh salinan;
- memverifikasi tanda tangan terhadap exact canonical artifact;
- tidak menghambat queue TTE;
- tetap dapat dilanjutkan setelah browser terputus;
- dapat diadopsi bertahap tanpa menghentikan layanan payment lama.

## 2. Batas domain yang tidak boleh dicampur

### 2.0 Entry point paket dan dokumen

Keputusan R0 tanggal 26 September 2026 mengunci ownership UI berikut:

| Surface | Tanggung jawab | Bukan tanggung jawab |
|---|---|---|
| Main table payment | Status dan action paket: Detail Dokumen, history, edit/hapus sesuai workflow, submit/handoff, reject, dan verifikasi proses bisnis | TTE dokumen, validasi tanda tangan BSrE, view PDF tertentu, atau download PDF tertentu |
| Modal Detail Dokumen | Daftar anggota keluarga dokumen, status TTE per dokumen, serta trigger Tampilkan/TTE sesuai capability backend | Menentukan authorization dari tombol/role numeric atau melayani byte PDF langsung |
| General secure PDF viewer | View PDF, verification summary/signer, dan download bila `can_download=true` | Placement QR/footer, passphrase, atau perintah sign |
| Editor TTE | Placement QR/footer, prepared confirmation, passphrase, attempt/progress/resume, dan result | General download atau menjadi viewer arsip utama |

`app/Http/Controllers/Data/Detail.php` dan adapter detail khusus tetap menjadi
read boundary keluarga dokumen. Keputusan capability nantinya diekstrak ke
resolver/policy backend universal; Blade/JavaScript tidak boleh menebak hak TTE
dari `src_type`, warna status, CSV legacy, atau ID jabatan saja.

Modal detail tidak ditumpuk pasif di bawah viewer/editor. Modal coordinator
menyimpan context paket, menyembunyikan detail ketika surface anak dibuka, lalu
membuka kembali dan me-refresh read model setelah anak ditutup/selesai.

R0 hanya mengunci kontrak dan tidak mengubah runtime. Pemindahan tombol,
penghapusan direct download, serta cutover main table dilakukan per
`payment_type:src_type` setelah replacement lulus gate. Ini mencegah hilangnya
layanan untuk dokumen yang workflow/artifact/delivery canonical-nya belum siap.

### 2.1 General secure PDF viewer

Viewer umum dipakai untuk melihat, memeriksa status verifikasi, melihat signer,
dan mengunduh PDF. Viewer tidak mempunyai penempatan QR/footer, passphrase,
prepared rendition, atau perintah TTE.

### 2.2 Editor TTE

Editor TTE tetap mengelola QR, footer, prepared rendition, passphrase, attempt,
progress, dan resume. Komponen rendah seperti PDF.js loader, page canvas,
navigation, dan zoom boleh digunakan bersama, tetapi viewer umum tidak boleh
bergantung pada state machine editor TTE.

### 2.3 Server-to-server original processing

Signing, BSrE verification, reconciliation, backup, dan pemeliharaan terkontrol
selalu memakai exact canonical artifact. Jalur ini bukan endpoint browser dan
tidak pernah memakai derivative watermark sebagai sumber.

## 3. Prinsip final

1. Original/canonical artifact immutable; watermark tidak pernah mengubahnya.
2. Watermark adalah derivative private dan bukan `document_artifacts` baru.
3. Satu flag posisi `user_positions.pdf_watermark_required` berlaku seragam
   untuk view, download, dan signing preview.
   Default posisi lama dan baru adalah `false`; nilai `true` hanya diaktifkan
   manual melalui Management User untuk posisi terpilih.
4. Backend menentukan mode; frontend tidak dapat memilih original/watermark.
5. Authorization dokumen diperiksa sebelum mode delivery.
6. COPY-ID adalah identitas forensik, bukan authorization secret.
7. Cache miss atau kegagalan watermark tidak boleh fallback ke original.
8. PDF dikirim sebagai binary `application/pdf`, bukan Base64 JSON.
9. Verifikasi BSrE selalu terhadap SHA-256 exact canonical artifact.
10. `document_process` tetap history bisnis; audit akses memakai event khusus.
11. File invalid/history legacy tidak dihapus oleh implementasi ini.
12. Tidak dibuat atau dijalankan test suite otomatis sampai pengguna mencabut
    larangan tersebut secara eksplisit.

## 4. Arsitektur target

```text
Blade/payment trigger
        |
        v
SecurePdfViewerModal (Svelte/PDF.js)
        |
        | POST create delivery
        v
IssuePdfDelivery action
        |
        +--> PdfDeliverySource resolver
        |       +--> DocumentArtifact adapter
        |       +--> Transitional legacy/report adapter
        |
        +--> document authorization
        +--> canonical artifact integrity
        +--> PdfDeliveryDecision
                |
                +--> ORIGINAL -------------------+
                |                                |
                +--> IDENTIFIED_WATERMARKED      |
                |       +--> queue pdf-delivery  |
                |       +--> qpdf overlay        |
                |       +--> private derivative  |
                |                                |
                +--> PUBLIC_WATERMARKED           |
                                                 v
                                      authorized binary response
                                                 |
                                                 v
                                         Svelte PDF.js viewer

Canonical artifact -----------------> asynchronous BSrE verification
                                             |
                                             v
                              persistent verification summary/signers
```

## 5. Keputusan delivery

Urutan resolver wajib:

1. resolve resource/document dan current canonical source;
2. verifikasi source berada di private storage dan lolos integrity check;
3. authorize subject, organisasi, tahun, lifecycle, view/download purpose;
4. guest yang benar-benar public selalu menerima `PUBLIC_WATERMARKED`;
5. authenticated request wajib mempunyai real position valid;
6. Admin Super acting like mengikuti override final `ORIGINAL` yang sudah
   diputuskan pengguna, tetapi tetap harus lulus effective document policy;
7. posisi bisnis nyata membaca `pdf_watermark_required` dari posisi tersebut;
8. `true` menghasilkan `IDENTIFIED_WATERMARKED`;
9. `false` menghasilkan `ORIGINAL`;
10. kondisi ambigu atau data tidak lengkap menghasilkan `DENIED`.

Browser dilarang mengirim `watermark=false`, delivery mode, storage disk/path,
artifact authoritative, user-position bebas, atau filename authoritative.

## 6. Model data target

Implementasi menggunakan migration additive baru; migration yang sudah deployed
tidak diedit.

### 6.1 `user_positions.pdf_watermark_required`

Boolean non-null dengan default `false`. Model harus mempunyai default
attribute, fillable, dan boolean cast. Perubahan administratif flag dicatat
melalui audit management user yang sudah ada. Seluruh row existing tetap
`false`; tidak ada backfill massal ke `true`. Toggle `true` baru boleh digunakan
setelah enforcement watermark untuk scope terkait siap tanpa original bypass.

### 6.2 `document_pdf_delivery_sessions`

Record jangka pendek untuk seluruh mode delivery. Session mengikat artifact,
actor, active/effective position, purpose, keputusan mode, policy version,
source SHA-256, state, dan expiration. Public identifier wajib opaque.

Keputusan identity dan policy pada session immutable. Field operasional seperti
status, ready/failed time, dan last access boleh berubah hanya melalui service
transition yang terkontrol.

### 6.3 `document_watermark_copies`

Record derivative watermark yang terkait delivery session. COPY-ID, identity
snapshot, source hash, profile/version, output checksum, private path, ukuran,
generation status, dan expiry disimpan. Derivative tidak menjadi current
artifact dan tidak masuk version chain dokumen.

### 6.4 `document_access_events`

Ledger append-only untuk requested, queued, generated, served, downloaded,
denied, failed, expired, dan cleanup. Event menyimpan actor/effective context,
artifact, session/copy, purpose, delivery mode, request correlation, dan safe
error code. Event tidak menyimpan passphrase, token, isi PDF, atau credential.

### 6.5 `document_artifact_verifications`

Read model persistent untuk status terakhir berdasarkan artifact SHA, provider,
dan verification policy version. Detail signer/certificate yang sudah tersedia
tetap memakai model canonical yang ada; tabel ini tidak membuat subsistem
signature kedua.

## 7. Backend boundaries

### 7.1 Source resolver

Contract `PdfDeliverySource` mengembalikan controlled resource key, subject
authorization, private storage reference, safe filename, MIME, SHA-256, ukuran,
dan metadata. Implementasi pertama menggunakan `document_artifacts` melalui
`CurrentDocumentArtifactResolver`.

Status 26 September 2026: kontrak, DTO hasil immutable, registry legacy
terkontrol, dan adapter current canonical artifact telah selesai di source.
Binding container sekarang menunjuk composite resolver dengan urutan canonical
artifact, legacy-private, lalu legacy-public. Billing dan SPJ Fungsional
memakai resource key attachment yang terkontrol; TBP memakai resource dokumen
yang sama. `Data\Detail` dan `Data\DetailTbp` telah memakai hasil resolver untuk
`source_state`, tetapi action HTML legacy belum dicabut. Belum ada content
session universal atau cutover viewer pada tahap ini.

PDF yang belum menjadi artifact masuk melalui adapter transisi. Adapter tidak
boleh menjadikan `document.src_name` atau physical path sebagai public identity.
Source legacy hanya boleh dibaca; implementasi ini tidak menghapus/memindahkan
history.

### 7.2 Authorization dan decision

`PdfDeliveryAuthorizationService` memakai policy/domain authorization yang ada.
`PdfDeliveryDecisionService` hanya memilih mode setelah authorization lulus.
Controller harus tipis dan menggunakan Form Request, Gate/Policy, action, dan
dependency injection.

### 7.3 Session issuance

`IssuePdfDelivery` melakukan resolve, authorize, integrity check, decision,
session persistence, audit, dan dispatch generation/verification. ORIGINAL dapat
langsung `READY`; watermark cache miss mengembalikan `202 Accepted`.

Setiap content/download request harus mengecek kembali session owner, position
context, expiry, current authorization, dan artifact integrity. Session bukan
pengganti authorization.

### 7.4 Watermark profile

Authenticated watermark memuat nama tampilan, jabatan/unit seperlunya, waktu
pembuatan salinan, dan COPY-ID. Public watermark tidak memuat identitas
personal. NIK, email, credential, token, numeric internal ID, dan path dilarang.

Default visual:

- diterapkan pada setiap halaman;
- semi-transparan dan tetap terbaca;
- tidak menutup konten utama secara berlebihan;
- mendukung portrait, landscape, rotation, dan mixed page sizes;
- menggunakan font server-side yang dikontrol dan mampu menangani teks UTF-8;
- profile dan renderer mempunyai version agar cache dapat diinvalidasi.

### 7.5 Renderer

`PdfWatermarkOverlayRenderer` dibuat terpisah dari `PreparedPdfRenderer` agar
footer TTE dan watermark delivery tidak mempunyai tanggung jawab campuran.
Renderer boleh memakai pola temporary directory, qpdf process, timeout,
`PdfPageGeometryInspector`, checksum, dan qpdf validation yang sudah terbukti.

Urutan render:

1. baca exact source melalui integrity service;
2. inspect page geometry/rotation;
3. buat overlay halaman yang geometrinya identik;
4. gabungkan dengan `qpdf --overlay`;
5. jalankan `qpdf --check`;
6. hitung output SHA-256 dan ukuran;
7. tulis private temporary file;
8. atomic publish ke final private path;
9. transisikan copy/session menjadi `READY`;
10. bersihkan temporary file pada semua outcome.

### 7.6 Cache, lock, dan queue

Cache fingerprint minimal terdiri dari source SHA-256, COPY-ID, watermark
profile/version, dan renderer version. File derivative awalnya hidup 12 jam dan
tidak memakai sliding TTL. Satu delivery session memakai satu COPY-ID sehingga
view dan download dari session yang sama konsisten.

Job `GeneratePdfWatermark` memakai queue `pdf-delivery`, uniqueness/atomic lock,
idempotent state check, retry teknis dengan exponential backoff, timeout yang
lebih kecil daripada queue `retry_after`, dan explicit `failed()` handler.
Queue ini tidak boleh memakai worker `signatures`.

### 7.7 Verification

Verification summary diproses oleh queue `pdf-verification`. PDF boleh langsung
dibuka sementara UI menunjukkan `PENDING`. Provider error menjadi retryable
`VERIFICATION_ERROR` dengan `next_retry_at`; hasil valid yang telah terbukti
tidak ditimpa error hanya karena request provider berikutnya gagal.

Signing worker yang sudah menghasilkan verified result harus memproyeksikan
summary agar viewer tidak memanggil BSrE ulang tanpa alasan. Derivative watermark
tidak pernah diverifikasi sebagai original.

## 8. Endpoint target

```text
POST /documents/{document}/pdf-deliveries
GET  /pdf-deliveries/{delivery}
GET  /pdf-deliveries/{delivery}/content
GET  /pdf-deliveries/{delivery}/download
GET  /document-artifacts/{artifact}/verification
```

Create request hanya menerima `purpose` yang diizinkan. `content` dan
`download` menggunakan opaque delivery binding dan mengembalikan binary PDF.
Status `202` digunakan ketika derivative/verification belum siap. API error
berbentuk envelope dan code stabil, sedangkan pesan provider/path internal
tidak dikirim ke browser.

Response PDF minimal memakai `Content-Type: application/pdf`, safe
`Content-Disposition`, `X-Content-Type-Options: nosniff`, private/no-store
browser cache policy sesuai threat model, dan CSP/header yang konsisten dengan
aplikasi. Dukungan HTTP range ditambahkan setelah boundary authorization dan
integrity stabil; Base64 bukan fallback.

## 9. Frontend target

General viewer dibuat sebagai Svelte island baru, misalnya di
`resources/js/documents/pdf-viewer`, dan dimount satu kali oleh layout. Ia boleh
berbagi primitive PDF.js dengan eSign tetapi tidak mengimpor editor placement.

Komponen target:

```text
SecurePdfViewerModal
|- PdfViewerToolbar
|- PdfPageCanvas/PdfDocumentViewport
|- PdfViewerThumbnails
|- PdfDocumentInformation
|- PdfVerificationSummary
|- PdfPreparationState
`- PdfDeliveryFailure
```

State frontend:

```text
Idle -> CreatingSession -> PreparingDocument -> LoadingPdf -> Ready
                                              |              |
                                              v              v
                                            Failed         Expired
```

Viewer menampilkan document identity, mode `Dokumen canonical` atau `Salinan
ber-watermark`, COPY-ID bila ada, verification state, signer summary, halaman,
zoom, dan download bila authorized. Desktop dapat memakai thumbnail/workspace/
information panel; tablet memakai drawer/offcanvas; mobile memakai workspace
penuh dan bottom sheet.

PDF.js wajib lazy-render, membatasi canvas aktif, membatalkan loading task,
destroy document, revoke object URL, dan menghentikan polling/timer saat modal
ditutup.

## 10. Bridge dan migrasi viewer legacy

Class `.view-pdf` dapat dipertahankan sebagai trigger sementara, tetapi
`data-url` diganti `data-document-id`/controlled resource identifier. Viewer
baru tidak mengambil arbitrary URL, tidak menyimpan global Blob Map, tidak
mengunggah ulang Blob untuk validasi, dan tidak memakai native `<embed>` sebagai
kontrak akhir.

Cutover dilakukan per payment. Direct public/static URL, temporary URL,
attachment, report/export, page-image/thumbnail, verification preview, dan
legacy download harus masuk inventory. Tidak ada jalur yang boleh dianggap
selesai hanya karena tombol utama sudah memakai viewer baru.

## 11. Hubungan dengan signing preview

Integrasi signing preview dilakukan setelah general delivery stabil. Posisi
watermark-required melihat derivative dengan geometri identik, tetapi placement
tetap disimpan dalam koordinat canonical. Backend menyiapkan dan menandatangani
canonical original + QR/footer, bukan derivative. Watermark tidak boleh masuk
ke signed output atau artifact version chain.

## 12. Cleanup dan deployment

Command `pdf-delivery:cleanup` menghapus hanya file derivative expired, berjalan
per chunk, idempotent, dan mencatat event/result. Scheduler memakai
`withoutOverlapping()` dan `onOneServer()` pada multi-node dengan shared cache.
Metadata audit tidak dihapus sampai retention audit disetujui.

Production harus mengonfirmasi:

- binary/path `qpdf`, `pdfinfo`, dan font;
- private disk tidak dapat dilayani langsung;
- `local.serve=true` tidak membuka bypass;
- single-node atau multi-node storage topology;
- shared lock/cache bila multi-node;
- worker `pdf-delivery` dan `pdf-verification`;
- disk capacity, queue latency, failed jobs, cleanup lag, generation duration,
  cache hit ratio, dan provider verification latency.

## 13. Urutan implementasi final

| Tahap | Pekerjaan | Outcome/gate | Status |
|---:|---|---|---|
| P0 | Kunci kontrak bisnis, default opt-in, invariant original/watermark, dan urutan rollout | Keputusan tidak ambigu dan konsisten lintas dokumentasi | Selesai 26 September 2026 |
| P1 | Inventaris seluruh PDF response, URL, viewer, download, report, attachment, guest, dan legacy consumer | Matriks repository/route/database/filesystem lokal tersedia di `PDF_DELIVERY_R1_READ_ONLY_INVENTORY.md`; filesystem public dicatat parsial, deployment/external-consumer tetap gate decommission | Selesai 26 September 2026 |
| P2 | Migration additive flag posisi default `false`, model default/cast, dan pemeriksaan schema | Seluruh posisi lama/baru tetap `false`; behavior runtime belum berubah | Belum |
| P3 | Schema session/copy/access-event/verification, model, enum, relation, dan transition service | Persistence canonical siap tanpa mengubah route lama | Belum |
| P4 | Source adapter dan current artifact resolver universal | Backend memperoleh exact source tanpa menerima path browser | Selesai di source 26 September 2026 |
| P5 | Authorization dan delivery decision ORIGINAL/WATERMARK/DENIED | Authorization ORIGINAL sudah aktif pada bridge endpoint; flag posisi dan keputusan WATERMARK/DENIED belum diimplementasikan | Sebagian: bridge ORIGINAL |
| P6 | Delivery session, audit append-only, opaque binding, content/download/status endpoint | Opaque direct content/download ORIGINAL tersedia tanpa path leak; persistent session, status, dan audit append-only belum tersedia | Sebagian: bridge ORIGINAL tanpa session |
| P7 | Persistent asynchronous artifact verification | Viewer tidak tertahan latency BSrE | Belum |
| P8 | Svelte secure viewer read-only dan legacy `.view-pdf` bridge | General viewer siap desktop/tablet/mobile | Belum |
| P9 | Pilot LS original dengan seluruh posisi masih `false` | Main viewer dan delivery contract terbukti sebelum watermark | Belum |
| P10 | COPY-ID, profile, overlay renderer, qpdf validation, private storage | Satu derivative dapat dibuat tanpa mengubah original | Belum |
| P11 | Cache fingerprint, lock, atomic publish, job, retry, dan cleanup | Concurrent generation idempotent dan resumable | Belum |
| P12 | Management User toggle dan audit before/after | Posisi pilot dapat diaktifkan manual menjadi `true` | Belum |
| P13 | Enforcement watermark pada delivery decision | Posisi `true` selalu watermark dan gagal secara fail-closed | Belum |
| P14 | Tutup direct/legacy original bypass pada scope termigrasi | Route lama tidak dapat melewati flag posisi | Belum |
| P15 | Pilot watermark LS untuk matrix `false`/`true`, signed/unsigned, view/download | Kebijakan opt-in terbukti end-to-end | Belum |
| P16 | Signing preview mengikuti delivery decision | Preview aman; canonical sign source tidak berubah | Belum |
| P17 | Rollout payment dan non-payment bertahap | Setiap source dipindahkan tanpa downtime | Belum |
| P18 | Operasionalisasi monitoring, recovery, dan capacity | Anomali original untuk posisi `true` terdeteksi sebagai critical | Belum |

Urutan rollout domain yang disarankan: LS SPP, keluarga LS, TU, GU SKPD, GU UK,
UP, KKPD, dokumen non-payment, laporan/export, attachment, lalu jalur guest dan
legacy redirect yang sudah mempunyai policy.

## 14. Gate setiap tahap

Tahap berikutnya tidak dimulai sebelum:

- perubahan bersifat additive dan tidak memutus layanan lama;
- policy dan source resolver tidak menerima path dari browser;
- source SHA-256 tetap sama sebelum/sesudah view, verify, dan watermark;
- generation gagal tidak pernah mengirim original;
- job dapat dijalankan ulang tanpa duplikasi/korupsi;
- content/download memeriksa authorization kembali;
- queue viewer tidak memblokir `signatures`;
- browser cleanup dan responsive behavior diperiksa;
- observability dan recovery operator tersedia untuk pilot.

## 15. Verifikasi tanpa automated test suite

Constraint proyek melarang pembuatan, perubahan, atau eksekusi Pest/PHPUnit dan
`php artisan test`. Verifikasi implementasi menggunakan:

- PHP syntax check dan Laravel Pint untuk PHP yang berubah;
- `php artisan route:list`, config inspection, migration `--pretend/status`;
- TypeScript/Svelte/build check yang tersedia;
- `qpdf --check`, pdfinfo, checksum, ukuran, dan manual PDF inspection;
- database query read-only untuk state/audit/index;
- browser Network/Console/Memory inspection;
- controlled queue worker dan failed-job inspection;
- manual acceptance matrix pada lampiran.

Larangan test suite tetap menang atas rekomendasi testing pada referensi ZIP.

## 16. Definition of done

Fitur baru hanya dinyatakan selesai ketika:

1. seluruh PDF user-facing telah terinventarisasi dan tidak ada original bypass;
2. mode ditentukan server dari authorization dan position context;
3. original canonical tidak berubah;
4. watermark setiap halaman benar untuk mixed size/rotation;
5. COPY-ID dapat ditelusuri ke snapshot actor/artifact;
6. view/download mempunyai audit yang dapat direkonsiliasi;
7. cache/lock/atomic publish/cleanup lulus acceptance;
8. verify selalu memakai original dan mempunyai persistent summary;
9. viewer lama tidak lagi menerima arbitrary `data-url`;
10. signing preview tidak mengubah source TTE;
11. pilot dan rollout disertai recovery/rollback operasional;
12. dokumentasi kondisi aktual diperbarui setelah setiap implementasi nyata.
