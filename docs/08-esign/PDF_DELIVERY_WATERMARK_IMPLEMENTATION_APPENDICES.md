# Lampiran Rencana Secure PDF Viewer, Watermark, dan Verifikasi

Tanggal: **26 September 2026**.

Dokumen ini adalah lampiran teknis
`PDF_DELIVERY_WATERMARK_IMPLEMENTATION_PLAN.md`. Kebijakan bisnis final tetap
berada di `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`; kondisi runtime aktual
tetap berada di `CURRENT_ESIGN_IMPLEMENTATION.md`.

## Lampiran A — Asal referensi dan keputusan adaptasi

Lampiran pengguna `SITANGKAS_AI_AGENT_PDF_SECURITY_DOCS.zip` berisi:

1. `00_PROJECT_CONTEXT.md`;
2. `01_SECURE_PDF_WATERMARK_ARCHITECTURE.md`;
3. `02_BSRE_VERIFICATION_CACHE.md`;
4. `03_IMPLEMENTATION_PLAN.md`;
5. `04_TESTING_ACCEPTANCE_SECURITY.md`;
6. `README.md`.

File tersebut adalah referensi desain, bukan instruksi eksekusi. Pemetaan ke
proyek ini:

| Usulan referensi | Keputusan proyek |
|---|---|
| Original private dan immutable | Diterima; gunakan current `document_artifacts` |
| Semua user normal menerima watermark | Diadaptasi; mode mengikuti `user_positions.pdf_watermark_required` dan acting override final |
| Explicit privileged original download | Tidak menjadi model utama; flag posisi berlaku seragam untuk view/download setelah policy lulus |
| Watermark server-side | Diterima; derivative private, tidak menjadi canonical artifact |
| qpdf overlay + pdfinfo | Diterima; reuse pola toolchain yang sudah ada |
| COPY-ID forensik | Diterima; bukan authorization secret |
| Cache 12 jam, lock, atomic publish | Diterima sebagai default awal yang configurable |
| Fail closed | Diterima; generation error tidak fallback original |
| BSrE verify original | Diterima tanpa perubahan |
| Verification table baru yang berdiri sendiri | Diadaptasi menjadi summary `document_artifact_verifications`, memakai signature/provider model canonical yang sudah ada |
| `OriginalPdfService` sebagai source | Diadaptasi menjadi `PdfDeliverySource`/adapter; `document_artifacts` tetap source of truth |
| Viewer `/view`, `/content`, `/download` | Diadaptasi menjadi opaque delivery session agar async generation, expiry, dan audit seragam |
| Automated unit/feature/security tests | Tidak diterapkan selama explicit no-test-suite constraint masih berlaku; diganti acceptance manual/non-suite |

## Lampiran B — Daftar enum dan value

Enum PHP menggunakan TitleCase key sesuai konvensi proyek.

### `PdfDeliveryMode`

| Key | Stored value | Arti |
|---|---|---|
| `Original` | `original` | Exact current canonical artifact |
| `IdentifiedWatermarked` | `identified_watermarked` | Derivative dengan identity snapshot authenticated user |
| `PublicWatermarked` | `public_watermarked` | Derivative tanpa identitas personal untuk akses publik yang authorized |
| `Denied` | `denied` | Tidak ada PDF yang boleh dikirim |

`Denied` boleh menjadi decision value tetapi tidak perlu disimpan sebagai
delivery session sukses. Penolakan dicatat pada access event.

### `PdfDeliveryPurpose`

| Key | Stored value |
|---|---|
| `View` | `view` |
| `Download` | `download` |
| `SigningPreview` | `signing_preview` |

### `PdfDeliveryStatus`

| Key | Stored value |
|---|---|
| `Requested` | `requested` |
| `Preparing` | `preparing` |
| `Ready` | `ready` |
| `Failed` | `failed` |
| `Expired` | `expired` |

### `PdfWatermarkCopyStatus`

| Key | Stored value |
|---|---|
| `Requested` | `requested` |
| `Processing` | `processing` |
| `Ready` | `ready` |
| `Failed` | `failed` |
| `Expired` | `expired` |
| `Cleaned` | `cleaned` |

### `DocumentArtifactVerificationStatus`

| Key | Stored value |
|---|---|
| `Unverified` | `unverified` |
| `Pending` | `pending` |
| `Valid` | `valid` |
| `InvalidSignature` | `invalid_signature` |
| `NoSignature` | `no_signature` |
| `VerificationError` | `verification_error` |

### `DocumentAccessEventType`

```text
ViewRequested
ViewServed
DownloadRequested
DownloadServed
DeliveryDenied
WatermarkQueued
WatermarkGenerationStarted
WatermarkGenerated
WatermarkFailed
VerificationRequested
VerificationCompleted
VerificationFailed
OriginalServed
WatermarkedCopyServed
SessionExpired
DerivativeCleaned
```

## Lampiran C — Transition matrix

### Delivery session

| Dari | Ke | Pemicu |
|---|---|---|
| `Requested` | `Ready` | Mode original dan integrity/auth lulus |
| `Requested` | `Preparing` | Watermark cache miss dan job diterbitkan |
| `Requested` | `Failed` | Source/integrity/persistence gagal setelah session dibuat |
| `Preparing` | `Ready` | Derivative terbit atomic dan checksum tersimpan |
| `Preparing` | `Failed` | Generation terminal failure |
| `Failed` | `Preparing` | Retry teknis terotorisasi, belum expired, source hash sama |
| `Requested/Preparing/Ready/Failed` | `Expired` | `expires_at` terlewati |

Terminal: `Expired`. `Ready` hanya dapat kembali diproses dengan delivery
session baru; jangan memodifikasi identity/decision session lama.

### Watermark copy

| Dari | Ke | Pemicu |
|---|---|---|
| `Requested` | `Processing` | Worker memperoleh atomic lock |
| `Processing` | `Ready` | qpdf check, checksum, dan atomic publish lulus |
| `Processing` | `Failed` | Generation gagal |
| `Failed` | `Processing` | Retry teknis valid |
| `Ready/Failed` | `Expired` | TTL terlewati |
| `Expired` | `Cleaned` | File derivative dihapus/terkonfirmasi tidak ada |

### Artifact verification

| Dari | Ke | Pemicu |
|---|---|---|
| `Unverified` | `Pending` | Verification job diterbitkan |
| `Pending` | `Valid` | Provider menyatakan semua signature yang dinilai valid |
| `Pending` | `InvalidSignature` | Provider memberi hasil signature invalid |
| `Pending` | `NoSignature` | PDF tidak mempunyai signature |
| `Pending` | `VerificationError` | Timeout/network/provider/parser failure |
| `VerificationError` | `Pending` | Retry sesuai `next_retry_at` |
| Status terminal | `Pending` | Explicit refresh policy/version/source hash berubah |

`Valid`, `InvalidSignature`, dan `NoSignature` tidak diubah menjadi
`VerificationError` hanya karena refresh berikutnya gagal. Error refresh
dicatat tanpa menghapus evidence terakhir.

## Lampiran D — Schema dictionary dan index

Nama/tipe final wajib diverifikasi terhadap database driver sebelum migration
dibuat. Semua FK memakai aturan restrict/null yang mempertahankan audit.

### D.1 `user_positions`

Tambahan:

| Kolom | Tipe | Aturan |
|---|---|---|
| `pdf_watermark_required` | boolean | non-null, default `true`, indexed hanya jika query management memerlukannya |

Backfill dilakukan terpisah dari DDL jika ada pengecualian posisi yang harus
`false`. Default aman tetap `true`; jangan menanam pengecualian bisnis dalam
migration schema.

### D.2 `document_pdf_delivery_sessions`

| Kolom | Tipe/aturan |
|---|---|
| `id` | bigint PK |
| `public_id` | UUID/ULID unique immutable |
| `document_id` | FK/index immutable |
| `document_artifact_id` | FK/index immutable |
| `user_id` | nullable FK/index; null hanya public session |
| `real_user_position_id` | nullable FK/index |
| `effective_user_position_id` | nullable FK/index |
| `purpose` | string enum/index |
| `delivery_mode` | string enum/index |
| `status` | string enum/index |
| `source_sha256` | char(64), indexed sesuai query reconciliation |
| `policy_version` | string |
| `resource_type` | string controlled |
| `resource_key` | string controlled/opaque |
| `safe_filename` | string; bukan source of identity |
| `requested_at` | datetime/index |
| `ready_at` | nullable datetime |
| `failed_at` | nullable datetime |
| `expires_at` | datetime/index |
| `last_accessed_at` | nullable datetime |
| `failure_code` | nullable string |
| timestamps | standard |

Index minimum:

```text
unique(public_id)
index(document_id, requested_at)
index(document_artifact_id, status)
index(user_id, expires_at)
index(effective_user_position_id, expires_at)
index(status, expires_at)
index(source_sha256, policy_version)
```

### D.3 `document_watermark_copies`

| Kolom | Tipe/aturan |
|---|---|
| `id` | bigint PK |
| `public_id` | UUID/ULID unique |
| `document_pdf_delivery_session_id` | FK unique atau indexed sesuai reuse final |
| `document_id` | FK/index immutable untuk laporan cepat |
| `document_artifact_id` | FK/index immutable |
| `copy_code` | string unique, random, display-safe |
| `identity_snapshot` | JSON; tidak menerima input bebas browser |
| `watermark_profile` | string |
| `watermark_version` | string |
| `renderer_version` | string |
| `source_sha256` | char(64) |
| `cache_fingerprint` | char(64), indexed |
| `storage_disk` | controlled string |
| `storage_path` | private relative path, hidden dari API |
| `file_sha256` | nullable char(64) |
| `file_size` | nullable unsigned bigint |
| `status` | string enum/index |
| `generation_attempts` | unsigned small int default 0 |
| `generated_at` | nullable datetime |
| `failed_at` | nullable datetime |
| `expires_at` | datetime/index |
| `cleaned_at` | nullable datetime |
| `failure_code` | nullable string |
| timestamps | standard |

Index minimum:

```text
unique(public_id)
unique(copy_code)
index(document_artifact_id, created_at)
index(cache_fingerprint, status)
index(status, expires_at)
index(document_id, created_at)
```

### D.4 `document_access_events`

| Kolom | Tipe/aturan |
|---|---|
| `id` | bigint PK |
| `public_id` | UUID/ULID unique |
| `document_id` | nullable FK/index |
| `document_artifact_id` | nullable FK/index |
| `document_pdf_delivery_session_id` | nullable FK/index |
| `document_watermark_copy_id` | nullable FK/index |
| `user_id` | nullable FK/index |
| `real_user_position_id` | nullable FK |
| `effective_user_position_id` | nullable FK |
| `event_type` | string enum/index |
| `result` | string (`success`, `denied`, `failed`) |
| `purpose` | nullable string enum |
| `delivery_mode` | nullable string enum |
| `request_id` | nullable string/index |
| `ip_address` | nullable; retention/privacy policy berlaku |
| `user_agent` | nullable bounded string |
| `failure_code` | nullable string/index |
| `metadata` | nullable JSON, allowlist only |
| `occurred_at` | datetime/index |

Tidak menyediakan update/delete melalui aplikasi biasa. Jika retention kelak
memerlukan purge, gunakan command governance khusus, bukan cascading delete.

### D.5 `document_artifact_verifications`

| Kolom | Tipe/aturan |
|---|---|
| `id` | bigint PK |
| `document_artifact_id` | FK/index |
| `source_sha256` | char(64) |
| `provider` | string |
| `verification_version` | string |
| `status` | string enum/index |
| `signature_count` | nullable unsigned int |
| `last_successful_status` | nullable terminal status bila refresh error |
| `attempt_count` | unsigned int default 0 |
| `last_attempted_at` | nullable datetime |
| `verified_at` | nullable datetime |
| `next_retry_at` | nullable datetime/index |
| `last_error_code` | nullable string |
| `last_error_at` | nullable datetime |
| `summary` | nullable JSON; normalized, tanpa raw secret/provider payload |
| timestamps | standard |

Constraint/index:

```text
unique(document_artifact_id, provider, verification_version)
index(status, next_retry_at)
index(source_sha256, provider)
```

## Lampiran E — Service/action/job map

Nama final mengikuti konvensi sibling source ketika implementasi.

```text
app/Contracts/Document/PdfDeliverySource.php
app/Data/Document/ResolvedPdfDeliverySource.php
app/Data/Document/PdfDeliveryDecision.php
app/Enums/Document/PdfDeliveryMode.php
app/Enums/Document/PdfDeliveryPurpose.php
app/Enums/Document/PdfDeliveryStatus.php
app/Enums/Document/PdfWatermarkCopyStatus.php
app/Enums/Document/DocumentAccessEventType.php
app/Enums/Document/DocumentArtifactVerificationStatus.php

app/Actions/Document/IssuePdfDelivery.php
app/Actions/Document/ServePdfDeliveryContent.php
app/Actions/Document/ServePdfDeliveryDownload.php
app/Actions/Document/ExpirePdfDelivery.php

app/Services/Document/DocumentArtifactPdfDeliverySource.php
app/Services/Document/PdfDeliveryAuthorizationService.php
app/Services/Document/PdfDeliveryDecisionService.php
app/Services/Document/PdfDeliveryPersistenceService.php
app/Services/Document/PdfWatermarkProfileBuilder.php
app/Services/Document/PdfWatermarkOverlayRenderer.php
app/Services/Document/PdfWatermarkStorage.php
app/Services/Document/DocumentAccessRecorder.php

app/Jobs/Document/GeneratePdfWatermark.php
app/Jobs/Document/VerifyDocumentArtifact.php

app/Console/Commands/Document/CleanupPdfDeliveries.php
app/Http/Requests/Document/StorePdfDeliveryRequest.php
app/Http/Controllers/Document/PdfDeliveryController.php
app/Http/Controllers/Document/PdfDeliveryContentController.php
app/Http/Controllers/Document/PdfDeliveryDownloadController.php
app/Http/Controllers/Document/DocumentArtifactVerificationController.php
app/Policies/Document/PdfDeliveryPolicy.php
```

Jangan membuat class baru jika sibling/current service sudah menyediakan
boundary yang sama. `CurrentDocumentArtifactResolver`,
`DocumentArtifactIntegrityService`, `PdfPageGeometryInspector`, dan normalized
BSrE verifier harus digunakan/diekstrak, bukan diduplikasi.

## Lampiran F — API contract examples

### F.1 Create delivery

```http
POST /documents/{document}/pdf-deliveries
Accept: application/json
Content-Type: application/json
X-CSRF-TOKEN: ...
```

```json
{
  "purpose": "view"
}
```

Ready response:

```json
{
  "data": {
    "id": "01K...",
    "status": "ready",
    "purpose": "view",
    "delivery_mode": "identified_watermarked",
    "content_url": "/pdf-deliveries/01K.../content",
    "download_url": "/pdf-deliveries/01K.../download",
    "expires_at": "2026-09-26T11:00:00+07:00",
    "copy_id": "STG-7K3M-9Q2D-X8NP",
    "document": {
      "title": "SPP",
      "number": "..."
    },
    "verification": {
      "status": "pending",
      "status_url": "/document-artifacts/.../verification"
    }
  }
}
```

Preparing response:

```http
HTTP/1.1 202 Accepted
Retry-After: 2
```

```json
{
  "data": {
    "id": "01K...",
    "status": "preparing",
    "poll_url": "/pdf-deliveries/01K...",
    "poll_after_ms": 2000,
    "expires_at": "2026-09-26T11:00:00+07:00"
  }
}
```

### F.2 Delivery status

```json
{
  "data": {
    "id": "01K...",
    "status": "ready",
    "delivery_mode": "identified_watermarked",
    "content_url": "/pdf-deliveries/01K.../content",
    "download_url": "/pdf-deliveries/01K.../download",
    "copy_id": "STG-7K3M-9Q2D-X8NP"
  }
}
```

### F.3 Verification status

```json
{
  "data": {
    "status": "valid",
    "source_sha256": "...",
    "signature_count": 2,
    "verified_at": "2026-09-26T10:15:00+07:00",
    "cached": true,
    "signers": [
      {
        "name": "Nama Penandatangan",
        "signed_at": "2026-09-26T09:50:00+07:00",
        "status": "valid"
      }
    ]
  }
}
```

Raw provider payload tidak dikirim secara default.

## Lampiran G — Error catalog

| Code | HTTP | Retry | UI aman |
|---|---:|---|---|
| `pdf_delivery.document_not_found` | 404 | tidak | Dokumen tidak tersedia. |
| `pdf_delivery.access_denied` | 404 | tidak | Dokumen tidak tersedia. |
| `pdf_delivery.position_invalid` | 409/403 internal contract | setelah memilih posisi | Posisi aktif tidak dapat digunakan. |
| `pdf_delivery.source_unavailable` | 409 | operator | File dokumen belum tersedia. |
| `pdf_delivery.integrity_failed` | 409 | operator | Integritas dokumen tidak dapat dipastikan. |
| `pdf_delivery.session_expired` | 410 | buat session baru | Sesi dokumen telah berakhir. Buka kembali dokumen. |
| `pdf_delivery.preparing` | 202 | polling | Salinan aman sedang disiapkan. |
| `pdf_delivery.watermark_failed` | 500 | server/operator | Salinan dokumen gagal disiapkan. |
| `pdf_delivery.renderer_unavailable` | 503 | server/operator | Layanan penyiapan dokumen tidak tersedia. |
| `pdf_delivery.file_too_large` | 422 | operator/limit | Dokumen melebihi batas pemrosesan. |
| `pdf_delivery.page_limit_exceeded` | 422 | operator/limit | Jumlah halaman melebihi batas pemrosesan. |
| `pdf_verification.pending` | 202 | polling | Dokumen sedang diverifikasi. |
| `pdf_verification.provider_unavailable` | 503 | background retry | Verifikasi sementara belum tersedia. |
| `pdf_verification.failed` | 502/503 | policy | Status verifikasi belum dapat dipastikan. |

Log internal boleh menyimpan exception class, request/session/artifact public
identifier, dan safe context. Jangan log passphrase, PDF bytes, credential,
raw Authorization header, atau unrestricted provider response.

## Lampiran H — Configuration contract

Nama akhir dapat disesuaikan dengan satu file config `pdf_delivery.php` atau
section yang konsisten. Service hanya memakai `config()`, bukan `env()`.

```dotenv
PDF_DELIVERY_ENABLED=false
PDF_DELIVERY_SESSION_TTL_MINUTES=60
PDF_DELIVERY_QUEUE=pdf-delivery
PDF_DELIVERY_JOB_TIMEOUT_SECONDS=180
PDF_DELIVERY_JOB_TRIES=3
PDF_DELIVERY_JOB_BACKOFF_SECONDS=15,60,180

PDF_WATERMARK_ENABLED=false
PDF_WATERMARK_VERSION=v1
PDF_WATERMARK_RENDERER_VERSION=qpdf-overlay-v1
PDF_WATERMARK_CACHE_HOURS=12
PDF_WATERMARK_LOCK_SECONDS=240
PDF_WATERMARK_STORAGE_DISK=private
PDF_WATERMARK_MAX_FILE_SIZE_MB=50
PDF_WATERMARK_MAX_PAGES=500
PDF_WATERMARK_PROCESS_TIMEOUT_SECONDS=120
PDF_WATERMARK_QPDF_BINARY=qpdf
PDF_WATERMARK_PDFINFO_BINARY=pdfinfo
PDF_WATERMARK_FONT_PATH=

PDF_VERIFICATION_QUEUE=pdf-verification
PDF_VERIFICATION_RETRY_DELAY_SECONDS=90
PDF_VERIFICATION_MAX_AGE_MINUTES=60
```

Feature flag default `false` sampai migration, worker, route, config, renderer,
manual acceptance, dan pilot siap. Flag bukan authorization; jika feature
dimatikan, route pilot harus kembali ke jalur legacy yang telah ditetapkan
secara eksplisit, bukan diam-diam mengirim original kepada watermark-required
position.

## Lampiran I — Private storage dan cache fingerprint

Contoh struktur:

```text
storage/app/private/pdf-delivery/
`-- 2026/
    `-- 09/
        `-- {artifact-public-id}/
            `-- {watermark-copy-public-id}.pdf
```

Tidak ada symlink public. Path tidak masuk payload browser. Temporary file
dibuat dengan permission terbatas dan nama random.

Fingerprint konseptual:

```text
SHA256(
  source_sha256
  + copy_id
  + delivery_mode
  + watermark_profile
  + watermark_version
  + renderer_version
)
```

COPY-ID berbeda menghasilkan derivative berbeda meskipun source sama.

## Lampiran J — Watermark visual profiles

### Authenticated identified

```text
SALINAN SITANGKAS
{DISPLAY_NAME} - {POSITION_NAME}
{UNIT_NAME}
{GENERATED_AT_WIB}
COPY-ID: {COPY_ID}
```

### Public

```text
SALINAN PUBLIK SITANGKAS
{GENERATED_AT_WIB}
COPY-ID: {COPY_ID}
```

Rule visual:

- opacity dan contrast harus tetap terbaca saat print grayscale;
- jangan menutupi QR/signature secara dominan;
- gunakan repeated/diagonal placement yang adaptif terhadap page geometry;
- footer watermark kecil boleh digunakan sebagai identitas stabil tambahan;
- hindari data personal berlebihan;
- viewer menampilkan badge dan COPY-ID di luar canvas juga;
- jangan memakai CSS-only watermark sebagai boundary keamanan.

## Lampiran K — Frontend contract dan UX

Folder konseptual:

```text
resources/js/documents/pdf-viewer/
|- api/client.ts
|- api/guards.ts
|- api/errors.ts
|- components/SecurePdfViewerModal.svelte
|- components/PdfViewerToolbar.svelte
|- components/PdfViewerCanvas.svelte
|- components/PdfViewerThumbnails.svelte
|- components/PdfDocumentInformation.svelte
|- components/PdfVerificationSummary.svelte
|- components/PdfPreparationState.svelte
|- components/PdfDeliveryFailure.svelte
|- stores/pdfViewerStore.ts
|- types.ts
`- mount.ts
```

Trigger transitional:

```html
<button
    type="button"
    class="view-pdf"
    data-document-id="{opaque-id}"
    data-purpose="view"
>
    Lihat dokumen
</button>
```

Jangan menaruh URL/path file pada data attribute.

UI states dan aksi:

| State | Tampilan | Aksi |
|---|---|---|
| `creating_session` | Skeleton + “Memeriksa akses dokumen...” | Tutup |
| `preparing` | Progress indeterminate + “Menyiapkan salinan aman...” | Tutup; background tetap berjalan |
| `loading_pdf` | Canvas skeleton | Tutup |
| `ready` | PDF, badge mode, verify summary, download bila boleh | Navigasi/zoom/download/tutup |
| `verification_pending` | PDF tetap tampil + status pending | Poll/realtime refresh |
| `failed` | Safe error + correlation code | Coba lagi jika retryable/tutup |
| `expired` | Session ended | Buka ulang/tutup |

Responsive:

- desktop: thumbnails, workspace, information panel;
- tablet: workspace + drawer/offcanvas;
- mobile: workspace penuh + compact toolbar + bottom sheet;
- native PDF embed bukan fallback utama;
- Bootstrap 5 + custom Argon tetap design system, bukan Tailwind.

## Lampiran L — Inventory template P0

Setiap PDF consumer dicatat dengan format:

| Field | Isi |
|---|---|
| Domain/payment | LS/TU/GU/... |
| Screen/controller | Lokasi pemanggil |
| User action | View/download/print/preview |
| Current URL/route | Route yang dipakai sekarang |
| Source | artifact/private/public/legacy/report |
| Current authorization | Policy/query/manual condition |
| Uses arbitrary path | Ya/tidak |
| Original exposed | Ya/tidak/tidak pasti |
| Guest reachable | Ya/tidak |
| Verification behavior | Browser upload/server artifact/tidak ada |
| Target adapter | Artifact/legacy/report |
| Target rollout wave | P10/P11/... |
| Owner | Tim/modul |
| Status | inventoried/blocked/migrated/closed |

Inventaris mencari minimal:

```text
response()->file / download / streamDownload
Storage::url / temporaryUrl
public_path / storage_path
Content-Type application/pdf
.pdf URL
data-url / view-pdf / embed / iframe / object
Blob / createObjectURL / FileReader / Base64
attachment/report/export generators
verification preview and signing preview
legacy /File_{TYPE}/sign/... paths
```

## Lampiran M — Manual acceptance matrix

Tidak dibuat automated test file. Semua baris dicatat dalam acceptance report
operasional ketika implementasi/pilot berlangsung.

### Authorization dan mode

- authenticated position flag `true`: view watermark;
- flag `true`: download watermark;
- flag `false`: view/download exact canonical;
- Admin Super acting: final override sesuai kebijakan;
- Admin Super real business position: ikuti flag posisi;
- user tanpa posisi valid: denied;
- creator/same unit/parent SKPD/workflow participant matrices;
- lintas unit/instansi tanpa hak: denied/404;
- guest public: public watermark;
- guest private: denied;
- guessed/modified delivery public ID: denied;
- delivery milik user/position lain: denied;
- authorization dicabut setelah session dibuat: content/download denied.

### Integrity dan watermark

- source hash sebelum/sesudah sama;
- derivative hash berbeda dari source;
- setiap halaman mempunyai watermark;
- portrait, landscape, 90/180/270 rotation;
- mixed page sizes/CropBox/MediaBox;
- PDF tanpa TTE dan multi-signature PDF;
- qpdf output valid dan dapat dibuka PDF.js;
- COPY-ID visible dan cocok dengan database;
- Unicode name/unit tidak rusak;
- generation timeout/failure tidak fallback original;
- cache hit menggunakan copy/session yang sama;
- request concurrent hanya menghasilkan satu output final;
- temporary/partial file tidak dapat dilayani;
- cleanup tidak menyentuh canonical/history file.

### Performance/resume

- cache miss mengembalikan 202 dan UI tetap responsif;
- browser ditutup saat job berjalan, job selesai di server;
- reopen session/poll mendapat state authoritative;
- worker restart dan retry tidak menduplikasi output/event terminal;
- queue `pdf-delivery` tidak menahan `signatures`;
- large PDF/page limit menghasilkan error aman;
- PDF.js tidak merender seluruh canvas sekaligus;
- modal close membersihkan document/task/object URL/timer.

### Verification

- signed valid, invalid signature, unsigned/no signature;
- provider timeout/down/error parser;
- retry memakai backoff;
- last successful evidence tidak hilang karena provider down;
- verification key mengikuti source SHA/provider/version;
- watermark derivative tidak pernah dikirim ke provider;
- known result dari signing dipakai kembali;
- raw provider error tidak bocor ke UI.

### Legacy/bypass

- `.view-pdf` tidak lagi menerima arbitrary URL pada migrated scope;
- direct public URL tidak mengirim original;
- old download/inline/temporary URL ditutup atau diarahkan melalui policy;
- browser tidak mengunggah ulang Blob untuk verify;
- storage symlink tidak mencapai private files;
- `local.serve=true` dan temporary serving diaudit;
- route verification preview mengikuti delivery policy;
- laporan/export/attachment/thumbnail masuk boundary.

## Lampiran N — Rollout dan rollback

### Rollout wave

1. read-only inventory;
2. schema/model/config dengan feature flag off;
3. services/routes/jobs dengan flag off;
4. controlled CLI/manual proof renderer;
5. LS SPP internal pilot;
6. LS SPP selected users/positions;
7. keluarga LS;
8. TU/GU/UP/KKPD;
9. non-payment/report/attachment;
10. signing preview;
11. guest/public path;
12. legacy bypass closure.

### Rollback principle

- rollback aplikasi tidak menghapus canonical artifact atau audit;
- disable new session issuance jika renderer/worker bermasalah;
- session yang sudah `READY` tetap dilayani hanya bila policy/config
  mengizinkan;
- watermark-required position tidak boleh dialihkan ke original saat rollback;
- legacy route hanya boleh dipakai bila ia sendiri memenuhi policy yang sama;
- migration destructive/decommission tidak dilakukan pada wave awal;
- forward-fix dipakai untuk data audit/append-only yang sudah terbit.

## Lampiran O — Operasional dan observability

Metric minimum:

```text
pdf_delivery_sessions_created_total by mode/purpose
pdf_delivery_denied_total by safe reason
pdf_watermark_generation_seconds
pdf_watermark_cache_hit_ratio
pdf_watermark_failed_total by safe code
pdf_delivery_ready_latency_seconds
pdf_verification_latency_seconds
pdf_verification_status_total
pdf_delivery_queue_depth / oldest age
pdf_verification_queue_depth / oldest age
pdf_derivative_disk_bytes
pdf_derivative_cleanup_lag
```

Alert minimum:

- renderer/qpdf unavailable;
- failure rate meningkat;
- queue oldest age melewati SLA;
- disk private mendekati limit;
- cleanup tidak berjalan;
- integrity failure;
- verification provider failure spike;
- original served kepada watermark-required position (critical invariant).

Operator runbook harus menyediakan pencarian dengan request ID, delivery public
ID, COPY-ID, artifact public ID, safe failure code, dan waktu. Operator tidak
perlu membaca passphrase atau membuka raw PDF untuk diagnosis umum.

## Lampiran P — Keputusan deployment yang harus dikunci sebelum P5

Rekomendasi default dicantumkan; bila keadaan server berbeda, dokumentasikan
keputusan baru sebelum coding tahap terkait.

| Keputusan | Rekomendasi |
|---|---|
| Existing position default | `pdf_watermark_required=true` |
| Derivative file TTL | 12 jam fixed, bukan sliding |
| Delivery session TTL | 60 menit |
| View dan download session | Boleh memakai derivative/COPY-ID yang sama dalam session |
| Watermark queue | Dedicated `pdf-delivery` |
| Verification queue | Dedicated `pdf-verification` |
| Source transport | Binary HTTP; bukan Base64 |
| Browser cache | Private/no-store sesuai security header final |
| Cache/lock multi-node | Shared store wajib |
| Derivative storage multi-node | Shared private disk/object storage atau node-affinity yang terdokumentasi |
| Audit retention | Jangan purge sampai kebijakan resmi disetujui |
| Dependency baru | Jangan ditambah sebelum qpdf overlay proof menunjukkan kebutuhan |
| Pilot | LS SPP canonical |

## Lampiran Q — AI agent handoff checklist

Sebelum bekerja:

1. baca `README.md` cluster eSign;
2. baca `CURRENT_ESIGN_IMPLEMENTATION.md`;
3. baca `PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`;
4. baca implementation plan dan lampiran ini;
5. baca docs auth/position/artifact/migration sesuai file yang disentuh;
6. verifikasi source dan migration status aktual;
7. jangan menganggap status rancangan sebagai implementasi;
8. jangan membuat/menjalankan test suite;
9. jangan mengubah migration yang sudah deployed;
10. jangan menghapus history/invalid legacy file;
11. implementasikan satu phase/gate secara koheren;
12. perbarui `CURRENT_ESIGN_IMPLEMENTATION.md` setelah source benar-benar
    berubah dan acceptance yang relevan dijalankan.

