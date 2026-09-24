# Kontrak Frontend-Backend eSign V1

Tanggal audit: **24 September 2026**  
Status: **F0 selesai dan dikunci terhadap source backend saat ini**

Dokumen ini adalah boundary implementasi frontend TTE canonical. Kontrak typed
yang dapat diimpor frontend berada di `resources/js/esign/types.ts`. Nama field
dipertahankan sama dengan JSON Laravel (`snake_case`) agar tidak ada mapping
implisit antara response backend dan state frontend.

Audit F0 bersifat read-only terhadap perilaku backend. Tidak ada endpoint,
policy, job, database, migration, dependency, atau test suite yang diubah oleh
tahap ini.

## 1. Prinsip yang dikunci

1. Frontend hanya membawa `step_public_id` untuk membuka proses TTE. Frontend
   tidak boleh membawa NIK, path file, storage disk, document status, signer ID,
   workflow ID, atau destination path.
2. Endpoint awal create session berasal dari bridge aplikasi. Setelah session
   dibuat, frontend wajib memakai `preview_url`, `prepare_rendition_url`,
   `sign_url`, `status_url`, `resume_url`, dan `qr_image_url` yang dikirim
   backend. Frontend tidak menyusun URL tersebut sendiri.
3. Semua JSON request memakai same-origin credentials, CSRF Laravel, dan header
   `Accept: application/json`.
4. PDF dimuat dari authorized binary response `application/pdf`. Gambar QR
   authoritative dimuat sebagai `image/png`. Tidak ada PDF Base64 pada kontrak
   browser.
5. Koordinat memakai PDF point (`pt`) dengan origin `top_left`, bukan pixel
   canvas. Pixel hanya representasi tampilan.
6. `prepared_revision` dan `preview_sha256` adalah pasangan immutable untuk
   intent sign. Perubahan placement/footer membatalkan pasangan lama.
7. Klik `Tandatangani Sekarang` mengirim `affirmed=true`. Tidak ada checkbox
   afirmasi.
8. Final sign dan resume tidak boleh di-retry otomatis oleh browser. Satu intent
   menggunakan satu `idempotency_key` yang tetap.
9. Passphrase hanya hidup pada local state komponen dan request HTTPS. Nilainya
   tidak boleh masuk URL, storage browser, log, analytics, event, atau payload
   completion.
10. Backend tetap validator dan authorization boundary final. Nilai
    `can_sign`/`can_verify` hanya capability untuk merender action, bukan bukti
    otorisasi saat request berjalan.

## 2. Middleware dan rate limit bersama

Seluruh endpoint internal berada di middleware:

- `auth`;
- `account.accessible`;
- `single.device.session`;
- `has.position`;
- `mfa.verified`;
- `active.position`;
- `password.fresh`.

Rate limit per user/context:

| Bucket | Batas | Dipakai untuk |
| --- | ---: | --- |
| `esign-prepare` | 30/menit | create/delete session dan prepare rendition |
| `esign-preview` | 60/menit | source/prepared PDF dan gambar QR |
| `esign-sign` | 5/menit | final sign dan resume |
| `esign-status` | 120/menit | show session dan polling attempt |

Frontend tidak boleh melakukan polling atau prepare pada setiap gerakan drag.

## 3. Matriks endpoint aktif

| Operasi | Method dan path | Sukses | Response |
| --- | --- | ---: | --- |
| Buat session | `POST /esign/internal/signing-sessions` | 201 | JSON `CreateSigningSessionResponse` |
| Baca/pulihkan session | `GET /esign/internal/signing-sessions/{session}` | 200 | JSON `ShowSigningSessionResponse` |
| Tutup temporary session | `DELETE /esign/internal/signing-sessions/{session}` | 204 | Tanpa body |
| Source preview | `GET /esign/internal/signing-sessions/{session}/preview` | 200 | Binary PDF |
| Prepare rendition | `POST /esign/internal/signing-sessions/{session}/renditions` | 201 | JSON `PrepareSigningRenditionResponse` |
| Prepared preview | `GET /esign/internal/signing-sessions/{session}/renditions/{revision}/preview` | 200 | Binary PDF |
| QR authoritative | `GET /esign/internal/signing-sessions/{session}/renditions/{revision}/operations/{index}/qr` | 200 | Binary PNG |
| Final sign | `POST /esign/internal/signing-sessions/{session}/sign` | 202 | JSON `SignDocumentResponse` |
| Poll attempt | `GET /esign/internal/attempts/{attempt}` | 200 | JSON `ShowEsignAttemptResponse` |
| Resume partial | `POST /esign/internal/attempts/{attempt}/resume` | 202 | JSON `ResumeEsignAttemptResponse` |

Route `{attempt}` menggunakan `public_id`, bukan primary key database.

## 4. Kontrak action payment

Action canonical LS SPP F1 telah memberikan object berikut pada field `esign`
row DataTable ketika signer memenuhi seluruh prasyarat:

```ts
interface EsignActionCapabilities {
    step_public_id: string;
    can_sign: boolean;
    can_verify: boolean;
}
```

Aturan:

- `step_public_id` adalah satu-satunya identity untuk create session;
- action sign tidak dirender bila `can_sign=false`;
- `can_verify` tetap `false` sampai endpoint validasi canonical tersedia;
- tidak ada `file`, `path`, `src_name`, NIK, atau URL storage pada data action;
- DataTable/Blade meneruskan data melalui custom event, bukan pemanggilan global
  legacy `.sign`/`.signModal`.

Implementasi berada di `LsSppSigningActionResolver`. Resolver menambahkan
correlated subquery terindeks pada query DataTable, sehingga capability tidak
menjalankan query policy per row. Hasil ini merupakan read-model fail-closed;
endpoint create session tetap mengulang authorization authoritative dan lazy
activation step pertama dalam transaksi.

Button memakai `data-esign-action="sign"` dan `data-esign-step`, kemudian
`resources/js/esign/action-bridge.js` menerbitkan event
`sitangkas:esign:open` dengan detail `step_public_id`, `can_sign`, dan
`can_verify`. Button tidak memakai `.sign` atau `.signModal`.

Action baru dikendalikan oleh dua gate konfigurasi:

- `SIGNATURE_FRONTEND_ENABLED=true`;
- `SIGNATURE_MULTI_OPERATION_ENABLED=true`.

Default frontend tetap `false` sampai shell Svelte F2 siap, sehingga deployment
F1 tidak menghasilkan tombol yang belum mempunyai modal penerima event.

## 5. Create dan show signing session

Request create:

```json
{
  "step_public_id": "uuid"
}
```

Field session yang tersedia untuk frontend:

- identity session: `session_id`;
- signer aman: `signer_name`, `masked_nik`;
- state dokumen: `placement_required`, `signature_state`,
  `verified_signature_count`, `footer_applied`;
- fingerprint artifact: `artifact_version`, `artifact_sha256`;
- geometry: `pages[]`;
- masa berlaku: `expires_at`;
- konfigurasi editor authoritative: `editor`;
- URL: `preview_url`, `prepare_rendition_url`, `sign_url`.

Perbedaan yang wajib dipertahankan:

- response `POST` create session **tidak** mempunyai field
  `prepared_rendition`;
- response `GET` show session **selalu** mempunyai `prepared_rendition`, bernilai
  object atau `null`.

Karena itu source TypeScript memakai dua tipe response, bukan memaksa keduanya
menjadi bentuk yang seolah-olah identik.

`editor.maximum_signature_count` adalah batas runtime. Meskipun FormRequest
menerima maksimal 20 item, frontend harus mengikuti nilai konfigurasi session,
bukan angka 20 yang di-hardcode.

## 6. Prepare visible rendition

Request:

```ts
interface PrepareSigningRenditionRequest {
    placements: SignaturePlacement[];
    footer: FooterPlan | null;
}
```

Setiap placement harus mempunyai `client_id`, `operation_index`, nomor dan
geometry halaman authoritative, serta rectangle dalam point. Indeks operasi
wajib kontigu `0..N-1`.

Footer:

- wajib untuk source unsigned ketika `editor.footer.required=true`;
- harus mempunyai tepat satu placement untuk setiap halaman;
- harus `null` bila PDF sudah signed atau footer sebelumnya sudah applied;
- font hanya boleh berasal dari `editor.footer.allowed_fonts`;
- backend tetap memvalidasi safe margin, ukuran, wrapping, collision, rotation,
  serta kesamaan geometry halaman.

Response prepared berisi:

- `revision`, `sha256`, `request_fingerprint`, dan `renderer_version`;
- `signature_count` serta `signature_operations[]`;
- public ID dan URL verify yang berbeda untuk setiap QR;
- `qr_image_url` authoritative untuk setiap operasi;
- footer yang sudah dinormalisasi beserta `configuration_sha256`;
- `preview_url` exact prepared PDF dan `expires_at`.

Frontend tidak boleh memakai placeholder QR untuk konfirmasi final.

## 7. Final sign, polling, dan resume

Final visible sign:

```ts
interface SignDocumentRequest {
    affirmed: true;
    idempotency_key: string;
    passphrase: string;
    prepared_revision: string;
    preview_sha256: string;
}
```

Untuk frontend visible, `prepared_revision` selalu UUID. Nilai nullable pada
FormRequest backend hanya mengakomodasi alur invisible yang bukan scope editor
ini.

Response `202 Accepted` memberikan `attempt_id`, status awal, dan `status_url`.
Setelah menerima 202, frontend wajib mengosongkan passphrase dan beralih ke
polling. Modal/browser tidak perlu tetap terbuka agar worker melanjutkan proses.

Status attempt:

- attempt: `prepared`, `signing`, `partially_signed`, `validating`,
  `succeeded`, `failed`, `unknown`;
- operation: `pending`, `signing`, `output_received`, `completed`, `failed`,
  `unknown`;
- progress berasal dari `planned`, `completed`, `current_index`, dan
  `operations[]`;
- polling hanya berjalan ketika `next_poll_after_ms` berupa angka;
- resume hanya ditampilkan ketika `requires_passphrase=true` dan
  `resume_url` tidak null;
- `requires_reconciliation=true` melarang retry/resume biasa;
- `result_artifact_id` baru dipakai ketika tidak null dan delivery endpoint
  yang sesuai sudah tersedia.

Resume selalu meminta passphrase baru dan mengirim:

```json
{
  "affirmed": true,
  "passphrase": "..."
}
```

## 8. Kontrak binary dan cache

Source PDF, prepared PDF, dan QR memakai header:

- `Cache-Control: private, no-store, max-age=0, must-revalidate`;
- `Pragma: no-cache`;
- `X-Content-Type-Options: nosniff`;
- `Content-Type: application/pdf` untuk PDF;
- `Content-Type: image/png` untuk QR.

Frontend harus memeriksa status HTTP dan media type sebelum memberikan body ke
PDF.js atau elemen image. Object URL dan PDF render task dilepas saat session
berganti, modal ditutup, atau component unmount.

## 9. Normalisasi error frontend

`resources/js/esign/types.ts` mengunci satu bentuk internal
`NormalizedEsignError`. Implementasi normalizer dilakukan pada F5.

| HTTP/kondisi | Category | Bentuk backend | Perilaku UI |
| --- | --- | --- | --- |
| 401 | `authentication` | `{ message }` | hentikan proses, arahkan login/reload context |
| 403 | `authorization` | `{ message }` | tutup action, jangan retry otomatis |
| 404 | `not_found` | `{ message }` | anggap resource/session/rendition stale; buka ulang bila relevan |
| 409 | `conflict` | `{ message, error: { code } }` | petakan `error.code`, jangan menebak dari message |
| 422 | `validation` | `{ message, errors }` | tampilkan field/placement error aman |
| 429 | `rate_limited` | `{ message }` + `Retry-After` | hormati header; tidak ada auto-retry sign |
| 5xx | `server` | hanya message generik yang boleh diasumsikan | jangan tampilkan raw exception/provider body |
| fetch gagal | `network` | tidak ada response tepercaya | outcome final sign dianggap belum jelas; jangan membuat intent baru otomatis |
| JSON/media type salah | `invalid_response` | tidak tepercaya | fail closed dan catat telemetry aman |

Hanya konflik `409` yang saat ini mempunyai application error code terstruktur
dan stabil. Frontend tidak boleh mengasumsikan `error.code` tersedia pada
401/403/404/422/429/5xx.

Kelompok konflik:

- session harus dibuka ulang: `esign.signing_session_expired`,
  `esign.signing_session_invalid`, `esign.signing_session_context_changed`,
  `esign.signing_session_role_changed`, `esign.source_artifact_changed`;
- prepare dapat dicoba ulang secara sadar: `esign.prepared_rendition_busy`;
- kembali ke editor dan prepare ulang: `esign.prepared_rendition_not_found`,
  `esign.prepared_rendition_changed`,
  `esign.prepared_rendition_context_mismatch`,
  `esign.preview_hash_mismatch`;
- konfigurasi/alur tidak sesuai: `esign.visible_placement_not_required`,
  `esign.prepared_revision_not_allowed`,
  `esign.prepared_revision_required`, `esign.visible_worker_not_ready`;
- intent tidak boleh diganti diam-diam:
  `esign.idempotency_payload_mismatch`.

Daftar literal code yang diaudit tersedia sebagai union type, tetapi UI tetap
harus mempunyai fallback untuk code baru yang belum dikenal.

## 10. Gap backend/frontend yang tercatat

F0 tidak menutup gap dengan asumsi. Item berikut menjadi pekerjaan tahap
berikutnya:

1. Session belum membawa informasi dokumen aman untuk panel Konfirmasi seperti
   nomor, jenis, atau label paket. Metadata tersebut harus ditambah dari backend
   canonical atau action bridge yang terauthorisasi; frontend tidak boleh
   menyimpulkannya dari nama file/path.
2. Endpoint validasi canonical beserta summary signer/signature dan authorized
   preview belum tersedia. `can_verify` harus tetap false sampai gap ini ditutup.
3. Delivery final berdasarkan `result_artifact_id` belum mempunyai URL pada
   response attempt. Frontend tidak boleh membangun URL download sendiri.
4. Belum ada endpoint recovery attempt aktif berdasarkan step/session bila
   browser kehilangan `status_url` setelah refresh penuh. F12 memerlukan
   keputusan backend untuk active-attempt discovery.
5. Error envelope selain konflik 409 belum mempunyai application code seragam.
   F5 harus menormalisasi berdasarkan HTTP status dan tidak bergantung pada
   kalimat message.
6. Backend belum mengirim versi schema kontrak. Perubahan field di endpoint ini
   wajib disertai update file type dan dokumen kontrak pada perubahan yang sama.

Gap tersebut tidak menghalangi F1-F10, tetapi item 2 perlu selesai sebelum panel
Konfirmasi final, item 5 sebelum recovery penuh, dan item 3-4 sebelum modal
validasi/result delivery dinyatakan selesai.

## 11. Gate F0

F0 dinyatakan selesai karena:

- seluruh endpoint aktif, middleware, rate limit, status sukses, JSON, dan
  binary response telah diaudit dari source;
- request/response utama sudah mempunyai type TypeScript tanpa `any`;
- perbedaan create/show session dikunci secara eksplisit;
- status attempt/operation dan aturan polling/resume sudah dipetakan;
- error 401/403/404/409/422/429/5xx/network sudah diklasifikasikan;
- action contract F1 hanya membawa identity/capability canonical;
- gap validasi, metadata dokumen, recovery attempt, dan result delivery tidak
  disamarkan sebagai fitur yang sudah tersedia;
- tidak ada test suite yang dibuat atau dijalankan.

Langkah berikutnya adalah F2: memasang fondasi Svelte/Vite island dan shell
modal yang menerima event bridge F1. Feature flag frontend baru diaktifkan
setelah listener/modal tersebut siap.
