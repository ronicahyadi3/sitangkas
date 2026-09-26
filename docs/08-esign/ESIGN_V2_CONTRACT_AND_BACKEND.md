# Kontrak eSign Client 2.2.0 dan Arsitektur Backend

Tanggal snapshot kontrak: **18 September 2026**. Kondisi implementasi terakhir:
**23 September 2026**.

Dokumen ini menetapkan boundary, kontrak internal, keamanan, state, dan pola
integrasi backend. Baca `README.md`, `CURRENT_ESIGN_IMPLEMENTATION.md`, dan
`PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md` pada folder ini lebih dahulu untuk
membedakan target kontrak dari kondisi source dan deployment aktual.

Extension visible/multi-QR yang sudah disetujui berada di
`ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`. Extension tersebut belum ada di
source/schema. Bagian attempt/payload satu-call di dokumen ini tetap menjelaskan
baseline implementasi saat ini dan tidak boleh dibaca sebagai penolakan desain
multi-QR target.

## 1. Ringkasan perubahan dari integrasi lama

| Area | Integrasi lama | Target 2.2.0 / API v2 |
|---|---|---|
| Sign | `POST /api/sign/pdf` | `POST /api/v2/sign/pdf` |
| Format sign | `multipart/form-data` | JSON |
| File | binary field `file` | base64 dalam `file[]` |
| Properti | field datar | `signatureProperties[]` |
| Spesimen | binary `imageTTD` | `imageBase64` |
| Mode | `visible` / `invisible` | contoh memakai `VISIBLE` / `INVISIBLE` |
| Verify | multipart `signed_file` ke `/api/sign/verify` | JSON base64 ke `/api/v2/verify/pdf` |
| Identitas | NIK + passphrase | contoh NIK/email + passphrase/TOTP |
| Multi-file | satu file | array file, semantik detail belum terkonfirmasi |

Basic Authentication tetap berada pada collection level. Browser tidak boleh
mengetahui credential Basic Auth atau menghubungi eSign Client secara langsung.

## 2. Endpoint v2 pada koleksi

### Scope rollout awal yang direkomendasikan

| Kebutuhan | Method dan endpoint | Payload utama |
|---|---|---|
| Sign NIK + passphrase | `POST /api/v2/sign/pdf` | `nik`, `passphrase`, `signatureProperties[]`, `file[]` |
| Verify PDF | `POST /api/v2/verify/pdf` | `file`, optional `password` |
| Check user status | `POST /api/v2/user/check/status` | `nik` atau `email` |
| Certificate chain | `GET /api/v2/user/certificate/chain/:id` | path identity; pastikan varian email ke sandbox |

Mode berikut tidak termasuk baseline pertama dan tidak boleh diaktifkan tanpa
keputusan lanjutan:

- NIK + TOTP;
- email + passphrase;
- email + TOTP;
- seal enrollment/revoke/activation;
- seal PDF/hash.

### Contoh payload sign minimum dari koleksi

Invisible:

```json
{
  "nik": "<resolved server-side>",
  "passphrase": "<ephemeral>",
  "signatureProperties": [
    {
      "tampilan": "INVISIBLE"
    }
  ],
  "file": [
    "<pdf-base64>"
  ]
}
```

Visible:

```json
{
  "nik": "<resolved server-side>",
  "passphrase": "<ephemeral>",
  "signatureProperties": [
    {
      "imageBase64": "<image-base64>",
      "tampilan": "VISIBLE",
      "page": 1,
      "originX": 0.0,
      "originY": 0.0,
      "width": 100.0,
      "height": 75.0,
      "location": "Pemerintah Kota Malang",
      "reason": "Dokumen telah disetujui dan ditandatangani secara elektronik",
      "contactInfo": null
    }
  ],
  "file": [
    "<pdf-base64>"
  ]
}
```

Jangan menyalin string literal `"null"` dari collection. Gunakan JSON `null`
atau hilangkan property setelah perilakunya dibuktikan di sandbox.

## 3. Temuan kualitas koleksi yang wajib diingat

Koleksi tidak menyediakan kontrak production lengkap:

1. title masih `2.2.0-beta For Development`;
2. contoh response sign NIK + passphrase kosong;
3. contoh response verify tidak tersedia;
4. ada endpoint dalam folder v2 yang masih memakai path tanpa `/v2`;
5. penggunaan `null` dan string `"null"` tidak konsisten;
6. nama variable subscriber tidak konsisten;
7. contoh jumlah `file[]` dan `signatureProperties[]` tidak selalu sama;
8. tidak ada limit ukuran, timeout, rate limit, atau daftar error resmi.

Konsekuensi: implementasi response decoder, multi-file, visible coordinate, dan
error mapping harus mempunyai contract test sandbox sebelum cutover.

Array `signatureProperties[]` dan `file[]` juga tidak membuktikan bahwa beberapa
visible placement dapat diterapkan ke satu PDF dalam satu request. Keputusan
target adalah satu request sign per QR secara serial, dengan output sebelumnya
sebagai input berikutnya, sampai uji terkontrol membuktikan kontrak lain yang
aman.

## 4. Boundary dan struktur class

Gunakan struktur project yang sudah ada (`Actions`, `Contracts`, `Data`,
`Services`, `Jobs`, `Http`). Concrete client wajib bernama `BsreClient`.

```text
app/
|-- Actions/Esign/
|   |-- CreateSigningSession.php
|   |-- SignDocument.php
|   `-- FinalizeSignedDocument.php
|-- Contracts/Esign/
|   `-- EsignGateway.php
|-- Data/Esign/
|   |-- SignRequestData.php
|   |-- SignResultData.php
|   |-- VerifyPdfData.php
|   |-- UserStatusRequestData.php
|   |-- UserStatusData.php
|   |-- VerificationResultData.php
|   |-- SignatureInformationData.php
|   |-- CertificateDetailData.php
|   `-- TimestampInformationData.php
|-- Services/Esign/
|   |-- BsreClient.php
|   |-- BsreResponseMapper.php
|   |-- EsignPayloadBuilder.php
|   |-- EphemeralSigningSecretStore.php
|   |-- SignerIdentityResolver.php
|   `-- SignedDocumentStorage.php
|-- Jobs/Esign/
|   |-- PerformEsignAttempt.php
|   `-- VerifySignedDocument.php
`-- Http/
    |-- Controllers/Esign/
    `-- Requests/Esign/
```

Gunakan dependency injection dan bind `EsignGateway` ke `BsreClient` dalam
service provider. Controller hanya mengurus HTTP; authorization, resolution,
vendor call, persistence, dan state transition tidak ditempatkan di controller.

Baseline contract:

```php
interface EsignGateway
{
    public function sign(SignRequestData $request): SignResultData;

    public function verify(VerifyPdfData $request): VerificationResultData;

    public function checkUserStatus(UserStatusRequestData $request): UserStatusData;
}
```

Kontrak tersebut sudah diimplementasikan. Request DTO menyimpan material
sensitif sebagai private property, memakai `SensitiveParameter`, dan meredaksi
debug output. `SignResultData::toArray()` hanya membawa metadata/hash; binary PDF
diambil eksplisit melalui accessor untuk diteruskan ke private staging.

Nama class tidak mengandung `V22`. Versi dan endpoint berada di konfigurasi,
tetapi konfigurasi production harus mengarah eksplisit ke `/api/v2/*`.

## 5. Konfigurasi

Gunakan `config/services.php`; aplikasi hanya membaca `config()`, bukan `env()`
langsung.

Baseline environment variable:

```text
BSRE_ESIGN_ENABLED=false
BSRE_ESIGN_BASE_URL=
BSRE_ESIGN_USERNAME=
BSRE_ESIGN_PASSWORD=
BSRE_ESIGN_VERIFY_TLS=true
BSRE_ESIGN_ALLOW_INSECURE_HTTP=false
BSRE_ESIGN_CONNECT_TIMEOUT=10
BSRE_ESIGN_VERIFY_CONNECT_TIMEOUT=5
BSRE_ESIGN_STATUS_TIMEOUT=30
BSRE_ESIGN_TOTP_TIMEOUT=30
BSRE_ESIGN_CERTIFICATE_TIMEOUT=30
BSRE_ESIGN_SIGN_TIMEOUT=30
BSRE_ESIGN_VERIFY_TIMEOUT=120
BSRE_ESIGN_LOCATION="Pemerintah Kota Malang"
BSRE_ESIGN_DEFAULT_REASON="Dokumen telah disetujui dan ditandatangani secara elektronik"
BSRE_ESIGN_SIGN_ENDPOINT=/api/v2/sign/pdf
BSRE_ESIGN_VERIFY_ENDPOINT=/api/v2/verify/pdf
BSRE_ESIGN_USER_STATUS_ENDPOINT=/api/v2/user/check/status
BSRE_ESIGN_TOTP_ENDPOINT=/api/v2/sign/get/totp
BSRE_ESIGN_CERTIFICATE_CHAIN_ENDPOINT=/api/v2/user/certificate/chain
SIGNATURE_PROCESSING_MODE=async
SIGNATURE_QUEUE=signatures
SIGNATURE_JOB_TIMEOUT_SECONDS=900
SIGNATURE_JOB_TRIES=1
SIGNATURE_ASYNC_SECRET_TTL_MINUTES=30
```

Tidak boleh ada fallback username/password. Jangan menaruh secret pada variable
berawalan `VITE_` karena nilai Vite tersedia untuk browser.

`BsreConfiguration` menjadi boundary immutable untuk seluruh consumer backend.
Saat integrasi aktif, konfigurasi ditolak sebelum request provider bila URL,
credential service, TLS, timeout, reason/location, atau endpoint `/api/v2/*`
tidak valid. Resolver tetap dapat dibuat ketika feature flag mati agar command
aplikasi lain tidak rusak.

HTTP hanya diizinkan melalui `BSRE_ESIGN_ALLOW_INSECURE_HTTP=true` yang eksplisit.
Pengecualian ini aktif pada `.env` lokal saat ini karena endpoint yang diberikan
pemilik masih HTTP; template source dan deployment tetap `false`. Gunakan HTTPS
atau jaringan internal terlindungi untuk deployment final. `verify_tls=false`
ditolak ketika integrasi aktif.

NIK dan passphrase signer bukan konfigurasi deployment. NIK harus diselesaikan
dari signer terotorisasi. Passphrase diterima hanya pada request final sign,
dienkripsi ke secret store/cache private ber-TTL, dan diakses worker melalui
opaque reference. Passphrase tidak boleh masuk database, session, log, event,
`failed_jobs`, atau serialized queue payload.

## 6. Source of truth signer

### User normal

- NIK berasal dari akun backend yang sah, bukan request frontend.
- Posisi efektif, unit, instansi, tahun, workflow, dan izin dokumen diverifikasi
  server-side.
- Frontend hanya menerima NIK yang sudah dimasking bila perlu ditampilkan.
- Passphrase diinput oleh pemilik sertifikat saat sign dan tidak disimpan.

### Admin Super acting context

`CurrentUserContext::activePosition()` pada acting context adalah overlay; ID-nya
tetap ID posisi Admin Super nyata. Jangan menganggap user relation pada clone
tersebut otomatis merupakan signer bisnis.

Bedakan acting context dari posisi bisnis nyata milik user. Bila user Admin
Super juga mempunyai posisi PA/PPTK/BP/dan seterusnya yang benar-benar assigned
kepadanya lalu memilih posisi itu, ia beroperasi sebagai pengguna biasa pada
posisi nyata tersebut dan `is_acting=false`. Acting like hanya berlaku ketika
effective position bukan posisi nyata yang assigned kepada user. Admin Super
boleh create/upload melalui kedua context selama role, scope organisasi, tahun,
dan workflow posisi efektif mengizinkannya.

Kebijakan final yang dikunci adalah satu mode bisnis `SELF_SIGN` untuk semua
pengguna. `PREPARE_FOR_SIGNER` tidak diimplementasikan. Admin Super:

- tidak memilih NIK atau signer target untuk menandatangani;
- tidak memasukkan passphrase milik orang lain;
- tidak mendapat hak sign universal dari role administratifnya;
- menempatkan QR/footer, memeriksa preview, dan memasukkan passphrase sendiri;
- hanya dapat sign bila real user/certificate owner tersebut merupakan signer
  sah pada workflow step aktif.

Frontend tidak menampilkan input NIK untuk Admin Super. Backend menyelesaikan
NIK dari user terautentikasi. Acting context tidak boleh mengganti certificate
owner menjadi pejabat yang sedang diperankan. Bila Admin Super juga mempunyai
posisi bisnis yang sah, authorization tetap harus membuktikan posisi, unit,
workflow, dan assignment signer tersebut.

Rincian matrix dan aturan sequential berada di
`ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md`.

## 7. Signing session internal

Signing session adalah context singkat dan non-secret, bukan tempat menyimpan
passphrase. Sebelum tombol sign ditekan, session/context preview harus ephemeral
dan bukan record audit atau `esign_attempts`. Session dapat memuat:

- UUID acak;
- document ID dan versi/hash artifact;
- original actor dan real position;
- effective role/organization/year snapshot;
- resolved signer identity reference;
- capability visible/invisible;
- expiry;
- status consumed/expired.

State teknis `prepared` pada attempt persisten baru dibuat ketika pengguna
menekan tombol sign, tepat sebelum vendor call. Istilah ini tidak berarti fitur
`PREPARE_FOR_SIGNER`, tidak membuat handoff Admin Super, dan tidak mengizinkan
orang lain memasukkan passphrase untuk signer.

Menutup modal saat placement/preview cukup membuang context ephemeral dan
temporary resource. Jangan menulis audit event, history, atau attempt.

Session tidak boleh memuat:

- passphrase;
- credential Basic Auth;
- file PDF base64;
- image base64;
- NIK lengkap bila tidak dibutuhkan persistence resmi.

## 8. Enum dan state machine final

Nilai database tetap string. PHP backed enum canonical berada di
`app/Enums/Esign`; jangan memakai MySQL native enum dan jangan memperbarui kolom
status langsung dari controller/job.

### Workflow

Status `document_signing_workflows.status`:

```text
draft | active | completed | rejected | needs_review
```

Transition legal:

| Dari | Ke | Pemicu |
|---|---|---|
| baru | `draft` | workflow dan urutan step dibentuk |
| `draft` | `active` | seluruh prasyarat start terpenuhi |
| `draft` | `needs_review` | mapping/assignment ambigu |
| `active` | `completed` | step wajib terakhir selesai |
| `active` | `rejected` | penolakan bisnis yang sah |
| `active` | `needs_review` | invariant runtime/data dilanggar |
| `needs_review` | `draft`/`active` | resolusi manual beraudit |

`completed` dan `rejected` terminal. Revisi setelah reject membuat cycle baru;
workflow lama tidak diaktifkan kembali. Tidak ada status `cancelled` pada scope
aktif.

### Step

Status `document_signing_steps.status`:

```text
pending | active | signing | reconciliation_required
completed | rejected | skipped | needs_review
```

Transition legal:

| Dari | Ke | Pemicu |
|---|---|---|
| baru | `pending` | step dibentuk |
| `pending` | `active` | step sebelumnya selesai |
| `pending` | `skipped` | aturan workflow menyatakan step opsional tidak diperlukan |
| `active` | `signing` | final sign diterima dan attempt dibuat |
| `active` | `rejected` | signer menolak secara bisnis |
| `signing` | `completed` | attempt dan verifikasi berhasil |
| `signing` | `active` | kegagalan deterministik; retry memakai attempt baru |
| `signing` | `reconciliation_required` | hasil provider ambigu |
| `reconciliation_required` | `completed` | terbukti sudah signed |
| `reconciliation_required` | `active` | terbukti tidak signed |
| nonterminal | `needs_review` | invariant/data tidak konsisten |

Kegagalan teknis attempt tidak menjadi step `rejected`. Hanya satu step boleh
berada pada `active`, `signing`, atau `reconciliation_required` dalam satu
workflow.

### Attempt

Status `esign_attempts.status` yang **sudah diimplementasikan saat snapshot**:

```text
prepared -> signing -> validating -> succeeded
                    |              `-> failed
                    `-> unknown
```

Definisi:

- `prepared`: authorization dan artifact snapshot sudah selesai;
- `signing`: request vendor sedang/baru dikirim;
- `validating`: output diterima dan berada di staging;
- `succeeded`: output valid, tersimpan final, dan workflow selesai;
- `failed`: kegagalan deterministik; vendor menolak atau payload tidak valid;
- `unknown`: koneksi putus/timeout setelah request mungkin telah diterima vendor.

Jangan mengubah `unknown` menjadi `failed` dan jangan auto-retry. Digital signing
tidak dapat diasumsikan idempotent.

Transition legal:

| Dari | Ke | Pemicu |
|---|---|---|
| baru | `prepared` | request final valid dan transaksi commit |
| `prepared` | `signing` | worker mulai vendor call |
| `prepared` | `failed` | validasi lokal/secret gagal sebelum vendor call |
| `signing` | `validating` | output provider diterima |
| `signing` | `failed` | provider memberi kegagalan deterministik |
| `signing` | `unknown` | timeout/reset setelah request mungkin terkirim |
| `validating` | `succeeded` | output tersimpan dan verify lulus |
| `validating` | `failed` | output kosong/rusak/tidak valid |
| `unknown` | `succeeded`/`failed` | hasil reconciliation yang terbukti |

Retry selalu membuat row attempt baru. Attempt `failed`/`succeeded` tidak
diubah kembali menjadi `prepared`/`signing`.

Target multi-QR menambah `partially_signed`, operation state, progress counter,
dan intermediate checkpoint secara additive. Dalam target itu, kegagalan aman
setelah minimal satu operasi tidak selalu membuat attempt baru: user memasukkan
passphrase kembali dan attempt yang sama resume dari operasi pertama yang belum
completed. Outcome ambigu tetap `unknown` dan tidak boleh dilanjutkan sebelum
reconciliation.

### Supporting enum yang sudah dibuat

| Enum | Nilai database |
|---|---|
| `DocumentArtifactType` | `before_sign`, `attachment`, `intermediate_sign`, `after_sign`, `failed_output` |
| `EsignProviderOperation` | `check_user_status`, `sign`, `verify` |
| `EsignProviderOutcome` | `success`, `business_failure`, `technical_failure`, `invalid_response`, `unknown` |
| `EsignMigrationRunStatus` | `pending`, `running`, `paused`, `completed`, `completed_with_exceptions`, `failed` |
| `EsignMigrationItemStatus` | `pending`, `processing`, `succeeded`, `retryable_failed`, `needs_review`, `failed` |
| `EsignMigrationStage` | `discovered`, `metadata_mapped`, `file_copied`, `checksum_verified`, `canonical_activated`, `completed` |

`DocumentSigningWorkflowEventType` memuat `workflow_created`,
`workflow_activated`, `step_activated`, `step_completed`, `step_rejected`,
`workflow_completed`, `workflow_rejected`, `review_required`,
`review_resolved`, dan `legacy_mapped`.

`EsignAttemptEventType` memuat `attempt_prepared`, `request_started`,
`provider_responded`, `output_received`, `validation_started`,
`attempt_succeeded`, `attempt_failed`, `outcome_unknown`, dan
`reconciliation_resolved`.

Enum sudah dihubungkan ke Eloquent cast pada model canonical. Migration tetap
memakai literal string agar menjadi snapshot schema mandiri. Tiga belas tabel
canonical sudah aktif pada database lokal, tetapi cast/relasi/state service
belum dibuktikan melalui vertical slice runtime.

## 9. Alur sign yang wajib

1. Resolve encrypted document ID menjadi dokumen.
2. Authorize real actor/certificate owner, posisi aktif, unit/instansi, tahun,
   workflow, dan assignment signer step aktif.
3. Resolve satu artifact canonical dari private storage.
4. Hitung SHA-256 artifact; jangan memakai MD5 untuk integrity baru.
5. Bentuk request fingerprint dari dokumen, versi/hash, signer, dan stage.
6. Peroleh atomic lock dan tolak attempt aktif/selesai yang duplikat.
7. Setelah pengguna menekan tombol sign, dalam transaksi singkat buat attempt
   persisten `prepared`; salin `workflow.document_id` ke
   `esign_attempts.document_id`; context preview sebelumnya tetap bukan audit
   record.
8. Enkripsi passphrase pada secret store/cache private dengan TTL dan simpan
   hanya opaque reference yang diperlukan dispatch; jangan serialisasi
   passphrase ke job payload.
9. Dispatch `PerformEsignAttempt` ke queue `signatures` setelah commit dan
   kembalikan `202 Accepted` beserta attempt UUID.
10. Worker memperoleh lock singkat, memastikan attempt masih `prepared`, dan
    mengambil secret sementara. Jika secret expired sebelum vendor call,
    finalkan sebagai kegagalan deterministik dan minta user memasukkan ulang.
11. Lepaskan transaksi/row lock sebelum external HTTP call, ubah attempt/step ke
    `signing`, lalu panggil `BsreClient::sign()` secara sinkron di dalam worker.
12. Baca file satu kali, encode base64 satu kali, dan decode response secara
    ketat berdasarkan contract yang terbukti.
13. Validasi ukuran, base64, MIME/magic bytes `%PDF-`, dan file non-kosong.
14. Simpan hasil ke private staging dengan nama yang dibuat server lalu ubah
    state menjadi `validating`.
15. Verify hasil tanpa passphrase di pipeline worker atau job read-only
    idempotent terpisah. UI tetap membaca `validating` sampai verify selesai.
16. Setelah valid, finalisasi artifact dan database dalam transaksi singkat.
17. Tulis history/audit, ubah attempt `succeeded`, dan selesaikan step. Pada LS
    SPP, step berikutnya baru di-assign/diaktifkan oleh handoff service setelah
    submit gate lulus; jangan mengaktifkannya otomatis dari finalisasi TTE.
18. Hapus secret segera pada terminal state, bersihkan staging yang tidak lagi
    diperlukan, dan kirim pembaruan status melalui polling/realtime.

Pemanggilan BSrE bersifat sinkron hanya di dalam worker; keseluruhan pengalaman
pengguna asynchronous. Setelah transaksi request final commit, kehilangan
koneksi browser, tutup modal, logout, atau navigasi tidak menghentikan job.

External HTTP call tidak boleh dilakukan sambil menahan row lock atau transaksi
database panjang.

Flow di atas adalah baseline satu operasi. Untuk target multi-QR, langkah
vendor-call sampai output persistence diulang serial per operation dengan
artifact intermediate immutable. Hanya output terakhir diverifikasi sebagai
final, dipromosikan current, dan diproyeksikan sekali ke tabel legacy. Algoritma,
schema, partial failure, secret reuse, dan acceptance berada di
`ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`.

## 10. Idempotency dan retry

- UI mencegah double click, tetapi backend tetap wajib mempunyai atomic lock.
- Gunakan request fingerprint/unique active marker untuk mencegah duplicate sign.
- Jangan automatic retry endpoint sign pada timeout, connection reset, 5xx, atau
  response yang tidak dapat dipastikan.
- Status user, certificate check, dan verify yang bersifat read-only boleh
  memakai retry terbatas dengan backoff/jitter.
- Attempt `unknown` membutuhkan reconciliation atau keputusan operator.
- User tidak boleh langsung mengulang attempt `unknown` tanpa pemeriksaan.
- Signing job memakai `tries=1`; rerun job tidak boleh mengirim ulang attempt
  yang sudah mencapai `signing`.
- Idempotency key dari frontend mengembalikan attempt yang sama bila response
  `202` hilang dan request final dikirim ulang.

## 11. HTTP client

`BsreClient` wajib:

- memakai `baseUrl()` dan `withBasicAuth()` dari config;
- mempunyai `connectTimeout` dan timeout per operasi;
- mengirim `Accept: application/json` bila contract mensyaratkan;
- tidak log request body;
- memetakan transport failure terpisah dari vendor/business failure;
- mencatat latency, endpoint logical name, HTTP status, safe vendor code, dan
  correlation ID;
- menangani JSON error walaupun HTTP status tidak ideal;
- tidak mengandalkan perbandingan string response utuh;
- dapat diganti dengan fake melalui interface dalam test.

Implementasi saat ini menginjeksi `Illuminate\Http\Client\Factory`, sehingga
`Http::fake()` dan `preventStrayRequests()` tetap dapat mengisolasi boundary.
Tidak ada `retry()` di concrete client. Operasi read-only yang gagal dapat
ditandai `retryable`, tetapi retry/backoff baru boleh dilakukan orchestration
layer dengan policy eksplisit. Sign tidak pernah auto-retry.

Payload sign yang aktif hanya:

- NIK 16 digit dari backend;
- passphrase ephemeral;
- satu `signatureProperties` dengan `tampilan=INVISIBLE`;
- satu PDF Base64 dalam `file[]`.

Builder menolak PDF kosong/non-PDF, NIK malformed, dan passphrase kosong sebelum
request jaringan dibuat.

Target visible akan memakai satu `signatureProperties` visible dan satu PDF per
panggilan operasi QR. Beberapa QR tidak berarti beberapa file dan tidak boleh
dijalankan paralel terhadap source yang sama.

## 12. Response dan error aplikasi

Jangan meneruskan raw response vendor ke browser. Gunakan response stabil:

```json
{
  "ok": false,
  "message": "Passphrase TTE tidak sesuai.",
  "data": null,
  "error": {
    "code": "esign.invalid_passphrase",
    "retryable": true,
    "attempt_id": "uuid"
  }
}
```

Kategori minimum:

- `esign.invalid_passphrase`;
- `esign.user_not_registered`;
- `esign.certificate_unavailable`;
- `esign.certificate_expired`;
- `esign.document_invalid`;
- `esign.provider_rejected`;
- `esign.provider_unavailable`;
- `esign.result_invalid`;
- `esign.outcome_unknown`;
- `esign.concurrent_attempt`;
- `esign.unauthorized`.

Error code historis seperti 2011/2021/2031/2041 dapat menjadi petunjuk, tetapi
tidak boleh dianggap identik pada v2 sebelum sandbox membuktikannya.

Mapper saat ini sengaja konservatif. HTTP 5xx/408 pada sign serta connection
failure sign menjadi `esign.outcome_unknown` dan tidak retryable. Gangguan
transport/read-only menjadi `esign.provider_unavailable`. Klasifikasi khusus
`esign.invalid_passphrase` belum diaktifkan karena response salah-passphrase v2
belum dibuktikan.

Response verify dinormalisasi ke DTO internal. Typo vendor
`timestampInfomation` menjadi `timestampInformation`, sedangkan
`certificateDetails[].signatureAlgoritm` menjadi `signatureAlgorithm`.
`lastSignature` tetap dipetakan tetapi tidak dipakai untuk menentukan tanda
tangan terbaru.

## 13. Storage

- Lifecycle, layout path canonical, public ID, version chain, preservation
  invalid file, dan compatibility legacy dirinci pada
  `ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md`; dokumen tersebut
  wajib dibaca sebelum implementasi storage/artifact.
- Seluruh backfill/migrasi file mengikuti
  `ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`: persistent checkpoint,
  high-watermark, lease, idempotency, crash-safe copy, pause/resume, catch-up,
  dan decommission terpisah adalah requirement, bukan optimasi opsional.
- Original, staging, dan signed artifact berada di private storage.
- Browser mengakses preview/view/download hanya melalui endpoint delivery
  terotorisasi. Temporary signed URL tidak boleh membypass resolver delivery
  atau membocorkan original kepada posisi yang wajib watermark.
- Satu flag `user_positions.pdf_watermark_required` berlaku bagi semua bentuk
  delivery authenticated: `true` selalu derivative watermark server-side;
  `false` boleh exact original setelah document policy lulus. Flag bukan
  authorization. Admin Super acting selalu efektif `false`; Admin Super pada
  posisi bisnis nyata mengikuti flag posisi nyata.
- Guest hanya dapat menerima PDF jika public-access policy exact artifact lulus,
  dan hasilnya selalu public-watermarked. Guest tidak pernah menerima original
  atau private path.
- Backend sign dan verifikasi BSrE selalu memakai original canonical artifact;
  derivative watermark bukan artifact version dan tidak boleh menjadi source
  sign/verify.
- QR canonical memakai `/verify/{public_id}` untuk exact immutable artifact.
  Halaman verify bersifat publik tetapi hanya menampilkan status, nomor bila
  ada, nama signer, dan tanggal signature. PDF publik bersifat opt-in melalui
  public-access policy dan selalu public-watermarked; delivery authenticated
  tetap mengikuti policy dokumen dan resolver flag posisi.
- `document_artifact_signatures` menyimpan read model hasil verifikasi per
  artifact agar public QR scan tidak memanggil BSrE setiap request.
- Legacy tipe+UUID di-resolve melalui mapping dan redirect `302`; `301` hanya
  setelah parity stabil dan redirect tidak pernah otomatis menuju latest.
- Path, folder, dan filename tidak pernah berasal langsung dari request client.
- Simpan artifact signed sebagai versi baru; jangan overwrite bukti lama.
- Revisi konten setelah TTE menghasilkan versi unsigned baru.
- Cleanup gagal tidak boleh menghapus original atau signed artifact yang sah.
- Jangan menyimpan PDF/base64 di log, database audit, queue payload, atau error
  report.

## 14. Audit dan observability

Audit minimal menyimpan:

- attempt UUID dan correlation ID;
- document ID, artifact version, input/output SHA-256;
- original actor user/position;
- effective role, instansi, unit, dan tahun;
- certificate owner/signer reference yang aman;
- NIK masked atau keyed hash bila diperlukan;
- auth mode dan visible/invisible;
- state transition;
- vendor safe code, HTTP status, dan latency;
- started/completed timestamps;
- hasil persistence dan verify.

Audit delivery PDF append-only juga menyimpan artifact/version/hash, aksi,
delivery mode (`original|watermarked|public_watermarked`), COPY-ID bila ada,
real/effective context termasuk acting, hasil authorization, request/correlation
ID, waktu, dan metadata jaringan yang telah dibatasi kebijakan. Jangan memakai
COPY-ID sebagai authorization token atau menampilkan raw internal ID pada
watermark.

Jangan simpan passphrase, Basic Auth, PDF/image base64, full request, session ID
mentah, atau exception yang mengandung payload.

## 15. Schema aktif dan data historis

Snapshot read-only database pada 17 September 2026:

- `before_signs`: 590.818 row, 386.441 dokumen, maksimum size historis sekitar
  10,3 MiB;
- `after_signs`: 588.882 row;
- 554.678 sukses dan 34.204 gagal;
- kegagalan passphrase salah: 20.012;
- hasil file 0-byte: 3.907;
- maksimum catatan attempt per dokumen: 142;
- 121 kelompok success identik perlu audit lebih lanjut.

`before_signs` dan `after_signs` hanya memiliki primary key. Untuk lookup audit
legacy, rencanakan indeks minimal:

- `before_signs (id_data, created_at)`;
- `after_signs (id_data, created_at)`;
- `after_signs (status, created_at)` bila sesuai query aktual.

Pembuatan indeks pada tabel besar harus mengikuti readiness migration dan
dijadwalkan agar tidak mengganggu production.

## 16. Tabel attempt baru

Migration `esign_attempts` sudah diterapkan pada database lokal. Kolom penting
yang tersedia meliputi:

- UUID/correlation ID;
- `document_id` signed `INT` nullable, step ID, dan source/result artifact ID;
- original actor user/position;
- effective context snapshot/reference;
- signer reference dan NIK masked/hash;
- input/output SHA-256;
- request fingerprint;
- auth mode dan display mode;
- state;
- vendor HTTP status dan safe code/message;
- latency dan timestamps;
- staging/final artifact reference;
- failure category dan retryable flag.

Index minimum:

- unique UUID;
- `(document_id, created_at)` dengan nama
  `ix_esign_attempts_document_time` sudah dibuat;
- state + created time;
- request fingerprint/active uniqueness sesuai strategi MySQL.

`before_signs`/`after_signs` tetap ditulis melalui compatibility adapter sebagai
ledger append-only, tetapi bukan state machine utama.

Untuk attempt runtime baru, `document_id` wajib berasal dari workflow dan tidak
boleh berasal dari request frontend. Nullable hanya mempertahankan hasil mapping
legacy orphan. Setelah insert nilainya immutable dan persistence service wajib
memastikan `attempt.document_id == workflow.document_id ==
source/result_artifact.document_id` untuk data runtime. Model, immutable guard,
dan persistence service tersebut sudah dibuat pada source; migration dan
runtime database canonical belum diaktifkan/dibuktikan.

Keputusan lanjutan: gunakan `document_artifacts` untuk file/version chain,
`esign_attempts` untuk summary satu percobaan, dan `esign_attempt_events` untuk
chronology append-only. Karena aplikasi lain masih membaca
`before_signs`/`after_signs`, compatibility dual-write wajib dipertahankan dan
dipantau dengan parity report. Detail schema konseptual, link legacy, dan
acceptance berada di
`ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md`.

Keputusan final aktif: kedua tabel tetap append-only sebagai compatibility
ledger. Mapping, exception classification, report parity, atau consumer cutover
tidak otomatis menghentikan write dan tidak memberi izin drop. Setiap perubahan
lifecycle memerlukan seluruh gate dan keputusan pengguna baru sebagaimana
dikunci pada `LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md`.

## 17. Performa

- Base64 menambah ukuran kira-kira sepertiga sebelum overhead JSON/copy memory.
- Batasi berdasarkan hasil sandbox serta PHP memory, proxy, dan web server.
- Jangan menetapkan batas 25 MiB sebagai fakta vendor tanpa konfirmasi.
- Mulai satu file per vendor request untuk mengurangi ambiguity dan memory spike.
- Jangan mengirim `OPDF` dan `domPDF` bersamaan.
- Encode file sekali dan jangan memasukkannya ke exception/log.
- Seluruh sign berjalan pada dedicated queue `signatures`; worker mengambil
  passphrase dari secret store terenkripsi ber-TTL melalui opaque reference.
- Serialized job payload, database queue, `failed_jobs`, log, dan event tidak
  boleh membawa passphrase.
- Gunakan `tries=1` untuk job sign dan `BSRE_ESIGN_RETRY_TIMES=0`; verify,
  notifikasi, dan cleanup yang tidak membawa passphrase boleh retry terkendali.
- Verifikasi viewer harus memakai cache status per immutable original artifact
  dan job asynchronous unique/locked. Hasil sandbox sekitar 40-43 detik untuk
  PDF 3,99 MiB dengan 8 signature tidak boleh menahan delivery/view response.
- Cache derivative watermark menggunakan identity artifact + principal/context +
  policy/template version, fixed TTL 12 jam, lock, file sementara, dan atomic
  publish. Kegagalan render/cache harus fail-closed tanpa fallback original.
- Jika `VerifySignedDocument` memakai database queue, `retry_after` harus lebih
  besar daripada timeout job.

## 18. Rotasi dan pembersihan credential lama

Project lama memiliki NIK/passphrase hardcoded dan default Basic Auth pada
source. Nilainya sengaja tidak dicatat di docs ini.

Sebelum integrasi baru dipakai:

1. identifikasi credential yang pernah masuk source, config, log, backup, atau
   Git history;
2. revoke/rotate passphrase signer yang terekspos;
3. rotate credential aplikasi eSign/Basic Auth;
4. masukkan credential baru melalui secret environment;
5. hapus hardcoded/default credential dari source aktif;
6. bersihkan deployment artifact, config cache, log, queue/failed job, dan backup
   yang tidak semestinya;
7. restart worker/process setelah config berubah;
8. uji dengan endpoint status yang aman sebelum sign nyata;
9. koordinasikan rewrite Git history bila diperlukan; rotasi tetap harus
   dilakukan lebih dahulu.

## 19. Larangan eksplisit

- Jangan menaruh credential BSrE pada frontend atau variable `VITE_*`.
- Jangan mengirim request langsung dari browser ke eSign Client.
- Jangan menerima bebas NIK, path file, filename, location, workflow state, atau
  signed destination dari browser.
- Jangan menyimpan passphrase dalam signing session, database, serialized queue
  payload, `failed_jobs`, session, log, localStorage, atau sessionStorage.
  Pengecualian tunggal adalah secret store/cache private terenkripsi ber-TTL
  yang hanya dapat diakses backend worker dan dihapus pada terminal state.
- Jangan auto-retry operasi sign.
- Jangan menandai dokumen signed sebelum output valid dan persistence selesai.
- Jangan mencari dokumen hanya dengan `src_name`.
- Jangan memakai `_location` dari client.
- Jangan menyalin response array positional dan exact-string error dari legacy.
- Jangan mengaktifkan multi-file/seal/OTP sebelum contract terkait dibuktikan.
- Jangan membuat flag watermark terpisah untuk view dan download.
- Jangan memakai `pdf_watermark_required` sebagai izin akses, memproses
  derivative watermark sebagai input sign/verify, atau menyediakan endpoint
  original alternatif bagi guest/posisi yang wajib watermark.
