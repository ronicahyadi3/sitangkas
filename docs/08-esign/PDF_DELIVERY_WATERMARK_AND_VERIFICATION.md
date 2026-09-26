# Delivery PDF, Watermark Forensik, dan Cache Verifikasi BSrE

Tanggal keputusan: **21 September 2026**. Kontrak default opt-in watermark dan
rencana implementasi diselaraskan pada **27 September 2026**.

Status: **kontrak arsitektur disetujui dan fondasi ORIGINAL sedang aktif
bertahap**. Migration `pdf_watermark_required` sudah diterapkan; seluruh posisi
masih `false`. General secure viewer, universal source resolver, authorization,
dan opaque binary delivery ORIGINAL sudah tersedia. Derivative watermark,
COPY-ID, persistent delivery session, audit akses append-only, persistent
verification summary, queue/cache/lock, dan cleanup watermark belum ada.
Seluruh agent wajib membedakan keputusan target pada dokumen ini dari kondisi
source aktual pada `CURRENT_ESIGN_IMPLEMENTATION.md`.

Rencana operasional dan lampiran implementasinya berada di:

- `PDF_DELIVERY_WATERMARK_IMPLEMENTATION_PLAN.md`;
- `PDF_DELIVERY_WATERMARK_IMPLEMENTATION_APPENDICES.md`.

Dokumen ini adalah source of truth untuk:

- keputusan apakah browser menerima artifact canonical/original atau salinan
  ber-watermark;
- `user_positions.pdf_watermark_required`;
- perilaku Admin Super acting context;
- akses PDF tanpa login;
- watermark server-side, COPY-ID, cache, audit, dan cleanup;
- verifikasi BSrE terhadap exact canonical artifact;
- hubungan viewer/download baru dengan signing preview dan jalur legacy.

Dokumen ini harus dibaca bersama:

1. `README.md`;
2. `CURRENT_ESIGN_IMPLEMENTATION.md`;
3. `ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md`;
4. `ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md`;
5. `ESIGN_V2_CONTRACT_AND_BACKEND.md`;
6. `ESIGN_V2_FRONTEND_MODAL.md`;
7. `ESIGN_V2_IMPLEMENTATION_PLAN.md`.

Setelah memahami kebijakan final pada dokumen ini, agent yang akan
mengimplementasikan fitur wajib membaca implementation plan dan appendices di
atas. Dokumen lampiran pengguna `SITANGKAS_AI_AGENT_PDF_SECURITY_DOCS.zip`
adalah referensi desain; ia tidak mengalahkan keputusan proyek atau kondisi
source aktual.

### Cakupan seluruh PDF aplikasi

Kebijakan ini berlaku pada **setiap PDF yang dikirim kepada manusia melalui
SITANGKAS**, bukan hanya hasil TTE. Cakupan mencakup:

- source dan signed `document_artifacts`;
- dokumen payment/lampiran;
- dokumen SK posisi;
- PDF laporan/export yang dihasilkan aplikasi;
- preview penandatanganan;
- route legacy, Blob/Base64 API, temporary URL, inline response, attachment,
  page image/thumbnail yang menampilkan isi dokumen, dan jalur publik.

Ikon/placeholder tanpa isi dokumen tidak perlu watermark. Komunikasi internal
server-to-server untuk sign/verify, proses backup, dan operasi pemeliharaan
terkontrol harus memakai original dan bukan user-facing delivery; jalur tersebut
tidak boleh dapat dipanggil sebagai endpoint browser.

`document_artifacts` tetap source utama bagi dokumen TTE. PDF lain yang belum
menjadi `document_artifacts` wajib masuk boundary yang sama melalui adapter
`PdfDeliverySource` yang menyediakan resource type/key terkontrol, private
storage reference, SHA-256, MIME/integrity result, filename aman, dan subject
authorization. Jangan memaksa seluruh domain memakai `document.src_name` atau
menjadikan path sebagai identity. Rollout boleh bertahap, tetapi fitur tidak
boleh dinyatakan selesai sebelum inventaris seluruh response `application/pdf`,
URL `.pdf`, generator laporan, dan static/legacy path lulus tanpa bypass.

## 1. Keputusan pengguna yang dikunci

1. Hanya ada **satu** penanda delivery PDF pada posisi pengguna:

   ```text
   user_positions.pdf_watermark_required
   ```

2. Penanda berlaku sama untuk seluruh view dan download PDF. Tidak boleh dibuat
   `pdf_view_watermark_required` dan `pdf_download_watermark_required`.
3. Alasan penyatuan: setiap PDF yang dapat dirender browser pada praktiknya juga
   dapat disimpan melalui Network, Blob, print, browser cache, atau capture.
   Memisahkan view dan download memberikan boundary keamanan yang semu.
4. Posisi login dengan `pdf_watermark_required=true` hanya menerima derivative
   ber-watermark untuk view, download, dan signing preview. Tidak ada route,
   query parameter, tombol tersembunyi, legacy URL, atau error fallback yang
   boleh mengirim original kepada posisi tersebut.
5. Posisi login dengan `pdf_watermark_required=false` menerima exact canonical
   artifact/original untuk view dan download setelah authorization dokumen
   lulus. Ini adalah hak sensitif dan seluruh aksesnya wajib diaudit.
6. **Admin Super yang sedang acting like selalu diperlakukan
   `pdf_watermark_required=false`**, terlepas dari nilai flag pada posisi nyata
   Admin Super. Keputusan ini eksplisit dari pengguna. Acting context tetap
   harus lulus authorization effective role, organisasi, tahun, dan dokumen.
7. Admin Super yang memilih posisi bisnis nyata miliknya dan tidak sedang
   acting mengikuti nilai `pdf_watermark_required` pada posisi nyata tersebut.
8. Request tanpa login tidak pernah menerima original. Guest hanya dapat
   menerima derivative dengan public watermark, dan hanya bila artifact memang
   lolos public-access policy.
9. Guest watermark bukan authorization. Dokumen privat tetap ditolak walaupun
   sistem mampu membuat watermark.
10. Tidak mempunyai posisi aktif, posisi tidak valid/nonaktif, artifact tidak
    dapat diselesaikan, atau policy tidak dapat menentukan hasil harus berakhir
    `DENIED`; jangan menganggap kondisi tersebut sebagai `false`.
11. Keputusan delivery selalu dibuat backend dari authenticated user, real
    position, effective/acting context, dan database. Browser tidak boleh
    mengirim atau memilih `watermark=false`, `delivery_mode=original`, path,
    filename authoritative, maupun `user_position_id` bebas.
12. Watermark adalah forensic marking dan deterrence, bukan DRM. Jangan
    mengklaim watermark mencegah seluruh penyalinan atau penghapusan oleh pihak
    yang menguasai file.
13. Kolom `pdf_watermark_required` memakai default `false` untuk posisi lama
    maupun posisi baru. Watermark authenticated bersifat **opt-in per posisi**;
    hanya posisi yang diaktifkan manual melalui Management User yang bernilai
    `true`.

Keputusan di atas menggantikan rancangan awal yang mengharuskan watermark untuk
semua user normal atau memisahkan normal download dan explicit original
download. Dalam kontrak final, nilai flag posisi dan override acting menentukan
delivery untuk view maupun download secara seragam.

## 2. Arti `original` dan artifact yang dilayani

Istilah `original` pada dokumen ini berarti **exact immutable canonical artifact
yang dipilih sebagai artifact aktif untuk akses**, bukan selalu file upload
pertama dan bukan sekadar `document.src_name`.

Contoh:

- dokumen belum TTE: current `before_sign` artifact;
- dokumen selesai satu atau beberapa step TTE: current/latest valid
  `after_sign` artifact;
- artifact gagal verifikasi: `failed_output` tidak boleh menjadi current dan
  tidak boleh dilayani sebagai original aktif;
- revisi dokumen: version chain menentukan current artifact secara eksplisit.

Resolver target harus menggunakan `document_artifacts`, workflow current
artifact, version, `is_current`, SHA-256, dan integrity check. Jangan menambah
`original_sha256` ke tabel legacy `document` sebagai sumber kebenaran baru;
`document_artifacts.file_sha256` sudah menjadi identitas byte canonical.

Nama konseptual service:

```text
CurrentDocumentArtifactResolver
DocumentArtifactIntegrityService
PdfDeliveryPolicy
PdfDeliveryService
```

## 3. Matriks delivery final

| Context | Prasyarat | Delivery view/download |
|---|---|---|
| Guest | Public-access policy lulus | `PUBLIC_WATERMARKED` |
| Guest | Artifact tidak public | `DENIED` |
| User login tanpa posisi valid | Apa pun | `DENIED` |
| User login, posisi nyata, flag `true` | Document policy lulus | `IDENTIFIED_WATERMARKED` |
| User login, posisi nyata, flag `false` | Document policy lulus | `ORIGINAL` |
| Admin Super acting like | Effective document policy lulus | `ORIGINAL` |
| Admin Super pada posisi bisnis nyata | Document policy lulus | Ikuti flag posisi nyata |

Precedence resolver wajib:

1. resolve artifact dan public/authenticated context;
2. authorize akses dokumen, organisasi, tahun, dan lifecycle;
3. guest yang authorized selalu `PUBLIC_WATERMARKED`;
4. authenticated user wajib mempunyai real position yang valid;
5. bila context adalah Admin Super acting like, hasil delivery `ORIGINAL`;
6. bila bukan acting, baca `pdf_watermark_required` dari active real
   `user_positions` row terbaru;
7. `true` menghasilkan `IDENTIFIED_WATERMARKED`, `false` menghasilkan
   `ORIGINAL`;
8. kondisi lain `DENIED`.

Contoh kontrak internal:

```text
PdfDeliveryPolicy::decide(
    actor,
    realPosition,
    effectivePosition,
    actingContext,
    artifact,
    action
) -> PdfDeliveryDecision
```

`action=VIEW|DOWNLOAD|SIGNING_PREVIEW` tetap disimpan untuk authorization dan
audit, tetapi tidak mengubah pilihan watermark/original. Satu flag berlaku
untuk semua action tersebut.

## 4. Perubahan `user_positions`

Schema aktif:

```text
pdf_watermark_required BOOLEAN NOT NULL DEFAULT FALSE
```

Migration additive
`2026_09_26_183341_add_pdf_watermark_required_to_user_positions_table.php`
sudah diterapkan pada batch 27. Snapshot 27 September 2026 menunjukkan 1.514
row posisi dan semuanya masih `false`. Model sudah mempunyai boolean cast;
kontrol Management User, audit perubahan flag, dan renderer watermark belum
selesai.

Default `false` adalah keputusan bisnis final untuk mempertahankan perilaku
existing. Seluruh row lama dan posisi baru mulai dari `false`. Hanya posisi
tertentu yang diaktifkan manual menjadi `true` melalui Management User. Begitu
bernilai `true`, seluruh jalur browser wajib fail closed: kegagalan atau cache
miss watermark tidak boleh mengirim original.

Aturan migration dan rollout:

1. migration `user_positions` yang sudah deployed tidak boleh diedit; buat
   migration baru melalui Artisan;
2. migration additive menggunakan `NOT NULL DEFAULT FALSE`, sehingga seluruh
   row existing dan row baru bernilai `false` tanpa data backfill terpisah;
3. nilai `true` hanya ditetapkan manual untuk posisi terpilih melalui Management
   User setelah renderer, queue, dan seluruh route delivery untuk scope pilot
   sudah siap;
4. model `UserPosition` harus mempunyai default/cast boolean yang konsisten;
5. Form Request Management Users hanya menerima boolean yang tervalidasi;
6. hanya actor berwenang yang boleh mengubah flag;
7. UI menggunakan label positif yang tidak ambigu:

   ```text
   Wajib watermark untuk seluruh akses PDF
   ```

8. perubahan flag wajib masuk audit management user dengan before/after,
   actor, real/effective position, reason bila policy administrasi
   mensyaratkan, request ID, IP, dan waktu;
9. policy delivery membaca nilai terbaru dari database pada setiap request PDF;
   jangan mengandalkan nilai lama dari payload frontend;
10. perubahan `true -> false` segera mengizinkan original pada request baru;
11. perubahan `false -> true` segera menghentikan original pada request baru;
12. derivative lama boleh menunggu cleanup tetapi tidak boleh memengaruhi
    keputusan delivery.

Management User tidak boleh mengaktifkan nilai `true` sebelum enforcement
watermark untuk scope tersebut siap. Setelah diaktifkan, route baru maupun route
legacy tidak boleh mempunyai original fallback.

Flag ini bukan pengganti authorization view/download. Nilai `false` tidak
memberi akses ke semua dokumen; flag hanya menentukan bentuk byte setelah
Policy menyatakan dokumen boleh diakses.

## 5. Public/guest access

Route guest hanya boleh melayani artifact yang secara eksplisit public menurut
policy bisnis, misalnya exact artifact yang dirujuk public verification page.
Mengetahui `public_id` tidak otomatis memberi hak mengunduh PDF.

Flow:

```text
Guest request
  -> resolve opaque public identifier
  -> public-access policy
  -> deny bila PDF tidak public
  -> resolve exact canonical artifact
  -> get/create PUBLIC_WATERMARKED derivative
  -> audit request
  -> stream no-store response
```

Public watermark tidak memuat identitas personal:

```text
SALINAN PUBLIK SITANGKAS | COPY-ID: {PUBLIC_COPY_ID}
```

Footer:

```text
SALINAN PUBLIK BER-WATERMARK
File original tersimpan di SITANGKAS
COPY-ID: {PUBLIC_COPY_ID}
```

Gunakan satu public derivative per artifact SHA-256, watermark version, dan
profile selama TTL. Jangan membuat file permanen per IP/request guest karena
menimbulkan pertumbuhan storage dan memasukkan data jaringan ke identity
visible. IP dan user-agent hanya masuk audit.

Route guest wajib rate-limited. Public response tetap
`Cache-Control: private, no-store, max-age=0, must-revalidate`; 12-hour cache
adalah server-side cache, bukan izin proxy/browser menyimpan PDF.

## 6. Authenticated forensic watermark

Konten utama yang direkomendasikan:

```text
SALINAN SITANGKAS | COPY-ID: STG-7K3M-9Q2D-X8NP
{USER_NAME} | {JABATAN} | {UNIT_KERJA}
```

Footer untuk page yang aman:

```text
SALINAN BER-WATERMARK
File original tersimpan di SITANGKAS
COPY-ID: STG-7K3M-9Q2D-X8NP
```

Visible watermark boleh memuat:

- nama pengguna;
- nama/kode jabatan;
- unit kerja atau instansi singkat;
- COPY-ID.

Visible watermark dilarang memuat:

- NIK/NIP;
- email;
- IP/proxy IP;
- session ID/token;
- raw database ID;
- physical path/URL internal;
- real-time timestamp.

Timestamp tidak menjadi bagian teks utama karena derivative mempunyai cache
TTL. Waktu pembuatan dan setiap view/download disimpan di database audit.

Nama/unit yang panjang harus dinormalisasi dan dipotong deterministik oleh
`WatermarkIdentityService`; input user tidak boleh memecahkan layout PDF.

## 7. Profil visual dan geometry

Watermark harus berupa vector/text overlay. Jangan merasterisasi source PDF.
Initial profile:

| Page profile | Rows | Rotation | Opacity | Footer |
|---|---:|---:|---:|---|
| `PORTRAIT` | 5 | -30 sampai -32 derajat | 0.08-0.10 | ya bila aman |
| `LANDSCAPE` | 3 | -26 sampai -28 derajat | 0.08-0.10 | tidak |
| `SHORT_LANDSCAPE` | 2 | -20 sampai -24 derajat | 0.07-0.09 | tidak |

Gunakan warna abu gelap/navy netral, font vector Unicode yang di-embed/subset,
dan font size adaptif. Profile final wajib diuji terhadap sample nyata; angka
di atas bukan alasan melewati visual acceptance.

Untuk setiap halaman baca dan hormati:

- `MediaBox`, termasuk origin yang tidak nol;
- `CropBox`;
- `/Rotate`;
- effective width/height;
- `/UserUnit` bila ada;
- page count dan resource limit.

`overlay.pdf` harus mempunyai jumlah halaman yang sesuai dan setiap overlay
page memakai geometry exact source page. Satu fixed A4 overlay atau repeat satu
page dilarang untuk dokumen mixed-size kecuali seluruh geometry sudah dibuktikan
kompatibel.

Watermark harus tersebar cukup untuk tetap terlihat pada scan/image-heavy PDF,
tetapi tidak boleh membuat QR TTE, barcode, angka rekening, nominal, atau tanda
tangan tidak dapat digunakan. Test annotation/form/rotated content wajib karena
annotation dapat dirender di atas page content.

## 8. Engine dan dependency

Target merge/overlay engine:

```text
qpdf
```

Metadata dapat memakai `qpdf --json`, `pdfinfo -box`, atau parser yang terbukti
menghasilkan geometry exact. Pilihan final harus dibuktikan dengan spike pada
mixed-page sample; jangan mengandalkan parsing output yang locale-dependent
tanpa test.

Qpdf menerima PDF overlay yang sudah dibuat. Project tetap membutuhkan overlay
generator yang mendukung arbitrary page size, opacity, rotation, Unicode, dan
deterministic output. Composer saat ini belum mempunyai writer yang dikunci
untuk kebutuhan ini; penambahan dependency memerlukan persetujuan pengguna.

External process wajib memakai Symfony Process dengan argument array, explicit
timeout, output limit, unique temp path, dan tanpa shell interpolation. Binary
path/version berasal dari config/env. Jangan hardcode `/usr/bin/qpdf` sebagai
satu-satunya nilai karena development juga dapat berjalan di Windows.

Deployment gate:

```text
qpdf tersedia dan version tervalidasi
pdfinfo/parser tersedia bila dipakai
proc_open/process execution diizinkan
web/worker user dapat read original
web/worker user dapat write/delete cache dan tmp
overlay generator/font tersedia
```

Pada snapshot 21 September 2026, `pdfinfo` tersedia pada workstation lokal,
tetapi `qpdf` belum terpasang. Ini blocker implementasi/benchmark, bukan blocker
untuk keputusan dokumentasi.

## 9. COPY-ID dan traceability

COPY-ID adalah identifier forensic, bukan authorization secret atau URL token.
Gunakan random identifier dengan entropy memadai, tidak mengekspose primary key,
misalnya:

```text
STG-7K3M-9Q2D-X8NP
STG-PUB-7K3M-9Q2D-X8NP
```

Jangan memakai delapan karakter pendek yang mudah bertabrakan pada volume
besar. Targetkan paling sedikit sekitar 80 bit random entropy sebelum encoding
dan enforce unique constraint.

Authenticated logical identity:

```text
document_artifact_id
+ artifact SHA-256
+ user_id
+ real user_position_id
+ effective organization/role snapshot
+ watermark version
+ watermark profile
+ rendered identity payload hash
```

Public logical identity:

```text
document_artifact_id
+ artifact SHA-256
+ PUBLIC audience
+ watermark version
+ watermark profile
```

Tabel target bila belum ada mapping ekuivalen:

```text
document_watermark_copies
```

Minimal field:

```text
id
copy_id unique
document_artifact_id
user_id nullable
real_user_position_id nullable
audience = AUTHENTICATED | PUBLIC
artifact_sha256
effective_context_snapshot JSON nullable
identity_payload_sha256
watermark_version
watermark_profile
created_at
```

Nama jabatan/unit pada investigasi harus dapat dibaca dari snapshot saat copy
dibuat, bukan hanya dari master data terbaru yang mungkin sudah berubah.

## 10. Watermark cache

Server-side TTL awal:

```text
12 hours = 43200 seconds
```

TTL dihitung tetap dari waktu file dibuat. Read/cache hit tidak memperpanjang
mtime/expiry.

Authenticated cache identity minimal:

```text
artifact_sha256
+ user_id
+ real_user_position_id
+ copy_id
+ identity_payload_sha256
+ watermark_version
+ watermark_profile
+ renderer version
```

Public cache identity minimal:

```text
artifact_sha256
+ PUBLIC
+ public_copy_id
+ watermark_version
+ watermark_profile
+ renderer version
```

Gunakan hash final sebagai filename dengan directory fan-out. Cache harus
memisahkan audience public dari authenticated agar file tidak tertukar.

Generation pattern:

```text
check valid cache
  -> miss
  -> acquire Cache::lock by cache identity
  -> recheck cache
  -> inspect original integrity and metadata
  -> create unique overlay temp
  -> qpdf to unique output temp
  -> qpdf --check / structural validation
  -> verify sensible page count/size
  -> atomic rename to final cache path
  -> cleanup temp in finally
```

Tidak pernah menulis langsung ke final filename. Kegagalan metadata, overlay,
qpdf, validation, temp storage, timeout, atau permission wajib fail closed;
jangan mengirim original sebagai fallback kepada context yang membutuhkan
watermark.

Database cache store project mendukung lock, tetapi multi-node deployment juga
harus memikirkan lokasi derivative file. Shared lock dengan local-only file
tidak membuat file node A tersedia pada node B. Pilih salah satu dan dokumentasi
deployment harus eksplisit:

- shared private derivative disk; atau
- node-scoped cache key/lock dan generation per node.

## 11. HTTP delivery dan route

Route target harus mengikuti naming/project policy dan tidak menduplikasi route
legacy secara buta. Konsep:

```text
GET  /documents/{document}/view
GET  /documents/{document}/content
GET  /documents/{document}/download
GET  /public/documents/{publicArtifact}/content   (hanya jika policy public)
```

`/view` mencatat satu human `VIEW` dan merender UI. `/content` melayani byte dan
tidak membuat event view baru untuk setiap range request. `/download` mencatat
satu `DOWNLOAD` per tindakan user.

Setiap content/download request wajib:

1. resolve user/context terbaru;
2. resolve exact artifact terbaru yang diminta;
3. authorize dokumen;
4. hitung ulang `PdfDeliveryDecision`;
5. pilih original atau derivative;
6. audit delivery mode;
7. stream file tanpa membaca seluruh file ke PHP string.

Header minimum:

```text
Content-Type: application/pdf
Content-Disposition: inline | attachment
Cache-Control: private, no-store, max-age=0, must-revalidate
Pragma: no-cache
X-Content-Type-Options: nosniff
```

Local file response harus diuji untuk HTTP Range karena PDF viewer dapat
mengirim byte-range requests. Gunakan BinaryFileResponse-compatible behavior
bila tersedia. Jangan mencatat range request sebagai human view.

Filename download harus disanitasi dan kompatibel dengan Symfony response;
physical path tidak boleh terlihat pada response/error.

## 12. Audit akses dokumen

`document_process` tetap compatibility history dan tidak cukup untuk forensic
access log. `user_management_audit_events` hanya untuk tindakan administrasi
management users. Buat append-only domain audit bila belum ada ekuivalen:

```text
document_access_events
```

Minimal field:

```text
event_uuid
document_id nullable
document_artifact_id nullable
resource_type
resource_key
source_sha256
actor_user_id nullable
real_user_position_id nullable
effective_context_snapshot JSON nullable
action = VIEW | DOWNLOAD | SIGNING_PREVIEW
delivery_mode = ORIGINAL | IDENTIFIED_WATERMARKED | PUBLIC_WATERMARKED
copy_id nullable
result
reason_code nullable
request_id/correlation_id
route/http method/status
ip_address
user_agent
occurred_at
created_at
```

Untuk artifact TTE, `document_artifact_id` wajib terisi. Untuk PDF domain lain,
`resource_type + resource_key + source_sha256` wajib mengidentifikasi source
secara stabil dan tidak berasal bebas dari request. Constraint aplikasi/schema
harus memastikan setiap event mempunyai tepat satu identity resource yang sah.

Untuk authenticated `ORIGINAL`, audit lebih penting karena tidak ada identity
forensic yang tertanam pada PDF. Acting Admin Super harus tercatat sebagai real
Admin Super sekaligus effective acting scope; jangan hanya menyimpan role/unit
effective.

Audit download berarti akses diotorisasi dan response mulai dilayani. Server
tidak boleh mengklaim browser berhasil menyimpan seluruh file bila transport
terputus setelah response dimulai.

## 13. Verifikasi BSrE exact artifact

Status verifikasi selalu menjelaskan exact canonical/original artifact, bukan
derivative ber-watermark. UI wajib menyatakan:

> Status TTE berlaku untuk file original. PDF yang ditampilkan dapat berupa
> salinan ber-watermark dari file original tersebut.

Status minimum:

```text
PENDING
VALID
INVALID_SIGNATURE
NO_SIGNATURE
VERIFICATION_ERROR
```

Semantik:

- `VALID`: signature pada exact artifact valid;
- `INVALID_SIGNATURE`: provider berhasil memeriksa dan menyimpulkan signature
  tidak valid;
- `NO_SIGNATURE`: pemeriksaan sukses dan tidak ada signature; ini final outcome,
  bukan error;
- `VERIFICATION_ERROR`: timeout/network/provider/malformed response; retryable;
- absence of row: belum diperiksa.

Gunakan `document_artifacts.file_sha256` sebagai identity byte. Jangan memakai
`document_id` saja dan jangan menghitung SHA-256 pada setiap view. Integrity
service tetap harus memvalidasi byte sebelum outbound provider call atau
watermark cache miss sesuai threat model.

Project sudah mempunyai `esign_provider_responses`,
`document_artifact_signatures`, certificate read model, dan `BsreClient`.
Jangan membuat duplikat raw response/signature subsystem. Tambahkan summary
state bila belum ada:

```text
document_artifact_verifications
```

Kandidat unique identity:

```text
(document_artifact_id, provider, verification_policy_version)
```

Summary dapat menyimpan:

```text
document_artifact_id
file_sha256 snapshot
provider
verification_policy_version
status
signature_count
latest_provider_response_id nullable
verified_at
last_attempt_at
next_retry_at
error_code/message sanitized
created_at/updated_at
```

Verify result yang sudah dihasilkan oleh signing worker harus memproyeksikan
summary ini dalam alur persistence yang sama sehingga viewer tidak memanggil
BSrE kembali untuk output yang sudah diverifikasi.

## 14. Verification execution policy

Verifikasi viewer harus resilient dan asynchronous. Bukti sandbox project
mencatat PDF sekitar 3,99 MB dengan delapan signature dapat membutuhkan sekitar
40-43 detik. Request UI tidak boleh memblokir selama itu.

Flow:

```text
open viewer
  -> authorize dan resolve artifact
  -> baca cached verification summary
  -> jika final: tampilkan
  -> jika error belum mencapai next_retry_at: tampilkan unavailable
  -> jika belum ada/retry due: create/mark PENDING dan dispatch unique job
  -> viewer tetap melayani original/watermarked menurut delivery policy
  -> UI poll ringan atau menerima Reverb update
```

Verification job:

- unique by artifact SHA-256 + provider + verification policy version;
- lock dan recheck database sebelum outbound call;
- explicit connect/read timeout berdasarkan bukti sandbox;
- verify boleh retry secara rate-limited dengan exponential/capped backoff;
- `retry_after` queue harus lebih besar dari job timeout;
- implement `failed()` dan simpan `VERIFICATION_ERROR` yang sanitized;
- jangan menggunakan retry policy sign untuk verify; sign tetap tidak blind
  retry karena risiko duplicate signature;
- tidak pernah mengirim derivative watermark ke BSrE sebagai original.

## 15. Signing preview

Satu flag juga berlaku untuk signing preview:

- posisi nyata dengan flag `true` melihat derivative exact-geometry
  ber-watermark;
- posisi nyata dengan flag `false` melihat original;
- Admin Super acting like melihat original sesuai override final
  `pdf_watermark_required=false`;
- guest tidak dapat signing.

Signing selalu memakai canonical original di server. Watermarked preview tidak
boleh menjadi input sign atau verify.

Karena browser posisi bertanda `true` tidak menerima byte original,
`preview_sha256` tidak boleh diperlakukan sebagai hash file yang dihitung dari
Blob browser. Signing session mengikat source artifact ID/version/SHA-256
server-side dan client mengafirmasi opaque preview token/COPY-ID. Koordinat
placement tetap dapat diterapkan bila derivative mempertahankan exact page
geometry.

Perubahan artifact, posisi, flag, acting context, workflow lock version, atau
authorization setelah preview harus menginvalidasi signing session.

## 16. Frontend viewer requirements

Viewer legacy saat ini fetch URL PDF menjadi Blob dan menyimpan Blob/object URL
dalam `Map`. Pola ini harus diganti agar keputusan delivery selalu berasal dari
endpoint canonical.

Frontend target:

- hanya memakai URL server canonical yang sudah diputuskan policy;
- tidak membentuk `/File_{TYPE}/...`;
- tidak mengirim ulang Blob ke endpoint BSrE verify;
- tidak menyimpan PDF di localStorage, IndexedDB, service-worker cache, atau
  global mutable state;
- revoke object URL dan buang Blob saat modal ditutup;
- buang seluruh cache viewer ketika active position/acting context berubah;
- jangan mempercayai nama/status/hash dari `data-*` sebagai authoritative;
- verification UI membaca normalized server result;
- status harus membedakan original verification dari displayed derivative;
- posisi flag `true` tidak pernah menerima tombol/URL original;
- original/watermark decision tidak boleh ditentukan dengan menyembunyikan
  tombol saja.

## 17. Legacy exposure dan migration gate

Selama `/File_{TYPE}/...`, `/File_{TYPE}/signs/...`, static alias, public
storage URL, atau route lama masih dapat mengirim artifact langsung, policy
baru dapat dilewati.

Sebelum rollout production:

1. inventaris seluruh public/static/legacy PDF URL dan consumer;
   sertakan generator laporan/export, dokumen SK, attachment, response Base64,
   thumbnail/page-image, dan seluruh response `application/pdf`;
2. migrasikan file ke `document_artifacts` pada private storage melalui
   copy-verify-activate;
3. ubah semua button/viewer/download ke route canonical;
4. redirect legacy QR hanya melalui exact mapping yang telah disepakati;
5. jangan menjadikan legacy direct file path sebagai fallback;
6. blokir static serving setelah parity, compatibility, rollback, dan consumer
   gate lulus;
7. verifikasi browser/network bahwa flag `true` dan guest tidak pernah menerima
   SHA-256/byte original.

## 18. Cleanup dan operations

Command target:

```text
php artisan watermark:cleanup
```

Hapus:

- final derivative lebih tua dari TTL;
- orphan overlay/temp lebih tua dari safety threshold;
- `.tmp-*` yang tidak mempunyai active lock;
- cache metadata orphan sesuai retention.

Jangan menghapus canonical artifact, signature evidence, provider response,
COPY-ID mapping, atau access audit.

Schedule hourly dengan `withoutOverlapping()` dan `onOneServer()` bila memakai
shared cache. Catat files deleted, bytes freed, runtime, node, dan error.

Monitor:

- original/watermarked/public delivery count;
- cache hit/miss;
- qpdf/metadata/overlay latency;
- generation failure dan lock timeout;
- verification PENDING age/error/retry;
- storage bytes dan cleanup result;
- original delivery oleh posisi `false` dan acting Admin Super.

## 19. Acceptance dan security tests

### Delivery policy

- posisi flag `true`: view, download, content, signing preview, changed method,
  laporan/export, dokumen SK, attachment, legacy route, dan guessed URL semuanya
  hanya menghasilkan watermark;
- posisi flag `false`: view/download menghasilkan exact current artifact dan
  audit `ORIGINAL`;
- user yang sama berpindah posisi `true <-> false`: response berubah pada
  request berikutnya tanpa reuse Blob/cache lintas posisi;
- flag berubah saat session aktif: endpoint menghormati nilai terbaru;
- posisi dinonaktifkan: akses ditolak;
- Admin Super acting like: delivery original, real actor dan effective context
  keduanya diaudit;
- Admin Super pada posisi bisnis nyata: mengikuti flag posisi nyata;
- guest public: watermark publik;
- guest private: ditolak;
- browser parameter/cookie/path manipulation tidak mengubah delivery decision.

### Original immutability dan leakage

- hash original sebelum/sesudah view, download, verify, watermark, cleanup sama;
- flag `true` dan guest tidak pernah memperoleh byte/hash original dari network,
  Blob, Base64, static URL, API, error, atau fallback;
- qpdf/pdfinfo/overlay failure tidak pernah mengirim original;
- public and authenticated cache tidak tertukar.

### Watermark

- mixed page size, portrait, landscape, short receipt, rotation, non-zero box
  origin, scan, annotation/form, QR/barcode, 1 page, dan 100+ page;
- COPY-ID terlihat, unik, dan resolve ke identity snapshot yang tepat;
- user/position/public cache isolation;
- fixed TTL tidak diperpanjang oleh read;
- concurrent request hanya membuat satu derivative per cache identity;
- partial temp tidak pernah dilayani.

### Verification

- `VALID`, `NO_SIGNATURE`, `INVALID_SIGNATURE`, `VERIFICATION_ERROR`, dan
  `PENDING` tampil dengan wording yang tepat;
- final result dipakai ulang untuk artifact/policy version yang sama;
- SHA/artifact/version berubah memicu verification identity baru;
- provider outage tidak membuat signature dilabel invalid;
- concurrent first-view hanya membuat satu provider verify job/call;
- sign-generated verified output langsung mempunyai reusable summary;
- verify tidak memblokir viewer selama puluhan detik.

### HTTP, worker, dan audit

- no-store/nosniff/content disposition/range behavior;
- VIEW dicatat sekali pada human view, bukan per range request;
- DOWNLOAD dicatat sekali per user action;
- FrankenPHP worker tidak membocorkan user, position, path, COPY-ID, Blob, atau
  delivery decision antarrequest;
- cleanup tidak menghapus file yang sedang dibuat;
- multi-node lock dan derivative storage behavior dibuktikan sesuai deployment.

Original leakage, authorization bypass, wrong-position cache, acting-context
misattribution, fail-open fallback, atau original mutation adalah release
blocker.

## 20. Urutan implementasi lanjutan

Fondasi ORIGINAL R0-R8 sudah tersedia di source, migration flag sudah aktif,
dan preflight R9 untuk LS SPP ORIGINAL sudah lulus. Urutan berikutnya adalah:

1. selesaikan acceptance manual R9 untuk view, download, authorization,
   refresh, multi-tab, dan audit log aplikasi;
2. implementasikan checkbox Management User dan audit before/after flag tanpa
   mengaktifkan nilai `true` terlebih dahulu;
3. buat access audit append-only serta persistent delivery session;
4. pilih/install qpdf dan overlay generator setelah approval dependency;
5. implementasikan COPY-ID, overlay, cache, lock, atomic generation, cleanup,
   dan fail-closed behavior;
6. implementasikan delivery authenticated/public ber-watermark tanpa membuat
   original bypass;
7. tambahkan verification summary serta unique asynchronous job;
8. ubah signing preview agar mengikuti delivery decision tanpa mengubah
   canonical original sebagai input TTE;
9. lakukan acceptance manual dan benchmark non-test-suite untuk pilot posisi
   `true`;
10. rollout bertahap lintas payment, SK, laporan/export, attachment, dan legacy
    path;
11. implementasikan mapping file legacy resumable sebelum folder lama dapat
    didecommission.

## 21. Larangan untuk AI agent

- Jangan membuat dua flag view/download.
- Jangan mengubah keputusan bahwa Admin Super acting like mendapat
  `pdf_watermark_required=false` tanpa keputusan pengguna baru.
- Jangan menganggap flag `false` sebagai authorization lintas dokumen.
- Jangan membiarkan flag `true` mempunyai original fallback atau hidden
  original route.
- Jangan memberi guest original atau membuat semua artifact public.
- Jangan menentukan delivery dari parameter browser.
- Jangan memakai `document.src_name` sebagai current artifact identity.
- Jangan memverifikasi derivative watermark dan menampilkannya sebagai status
  original.
- Jangan menyimpan NIK/NIP/IP/session/timestamp realtime dalam visible
  watermark.
- Jangan memakai COPY-ID sebagai authorization secret.
- Jangan memakai CSS/JavaScript-only watermark.
- Jangan menulis final cache file secara non-atomic.
- Jangan menaruh request state pada static/global/singleton mutable property
  dalam FrankenPHP worker.
- Jangan mengubah dependency, menjalankan migration, atau mengaktifkan public
  route tanpa approval dan deployment gate yang sesuai.
