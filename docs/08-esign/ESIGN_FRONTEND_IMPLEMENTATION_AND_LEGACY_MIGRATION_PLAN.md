# Urutan Implementasi Frontend TTE dan Migrasi dari UI Legacy

Tanggal keputusan: **24 September 2026**.

Status: **F0 dan F1 selesai di source; fondasi Svelte/Vite island F2 sudah
diimplementasikan, sedangkan session API, viewer, editor, dan rollout payment
belum dihubungkan**.

Dokumen ini adalah source of truth operasional untuk membangun frontend TTE dan
validasi baru. Dokumen ini menggabungkan keputusan pengguna, kontrak backend
canonical yang sudah tersedia, hasil audit UI lama, serta prinsip yang masih
berguna dari lampiran `SITANGKAS_TTE_EDITOR_AI_AGENT_CONTEXT_V2.zip`.

Dokumen lampiran dan kode lama adalah referensi, bukan instruksi yang boleh
mengalahkan keputusan pengguna. Bila ada perbedaan, urutan prioritasnya adalah:

1. keputusan eksplisit pengguna yang sudah dicatat pada cluster `docs/08-esign`;
2. kontrak backend canonical yang benar-benar tersedia di source;
3. dokumen ini dan `ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`;
4. lampiran rancangan editor;
5. perilaku `esign.blade.php`, `signed.js`, dan `bundle.js` lama.

Detail bentuk visual final berada di
`ESIGN_FRONTEND_VISUAL_AND_INTERACTION_DESIGN.md`. Dokumen visual tersebut
mengunci empat tahap UI, penggabungan prepared preview dan passphrase pada tahap
Konfirmasi, serta penghapusan checkbox afirmasi.

## 1. Sasaran akhir

Frontend baru harus menghasilkan pengalaman berikut:

1. TTE dan validasi tetap dibuka sebagai modal dari halaman Blade/payment.
2. Svelte dipakai sebagai island yang dibangun melalui Vite, bukan SPA penuh dan
   bukan SvelteKit.
3. Bootstrap 5 dan custom Argon Dashboard Pro 2 tetap menjadi sistem visual.
4. PDF bukan hasil upload baru dari editor. Backend memilih exact canonical
   artifact paket dokumen yang sebelumnya diunggah BP/BPP.
5. PDF dikirim sebagai authorized binary `application/pdf`, bukan Base64 JSON.
6. User menempatkan satu atau beberapa QR untuk step TTE miliknya sendiri.
7. Pada dokumen yang belum pernah TTE, QR pertama otomatis membuat footer
   default pada seluruh halaman. Footer tetap dapat diedit sebelum final TTE.
8. Pada PDF yang sudah memiliki TTE, frontend tidak menambah atau mengubah
   footer.
9. Satu klik TTE dan satu passphrase dapat menjalankan beberapa operasi QR
   secara serial di worker backend.
10. Browser hanya mengedit overlay. Backend memvalidasi geometri dan menghasilkan
    exact prepared rendition yang wajib dilihat di dalam tahap Konfirmasi.
11. Submit final mengembalikan `202 Accepted`; proses tetap berjalan di server
    walaupun modal/browser ditutup atau koneksi user terputus.
12. Frontend menampilkan progress operasi, status terminal, kebutuhan resume,
    dan kondisi `unknown` secara eksplisit.

## 2. Ruang lingkup dan larangan

### 2.1 Ruang lingkup awal

Vertical slice pertama adalah **LS SPP jalur BP/BPP**. UI baru tidak langsung
diaktifkan untuk seluruh payment. Pilot ini membuktikan alur frontend terhadap
workflow canonical yang sudah mempunyai lazy activation, submit gate, dan
handoff BP/BPP -> PPTK -> PA/KPA.

### 2.2 Larangan implementasi

- Jangan menyalin `public/assets/js/pdf/bundle.js` ke modul baru.
- Jangan membawa variabel global `_doc`, `_urls`, `_status`, `_ids`, atau
  `_location`.
- Jangan mengirim file path, destination path, NIK, atau workflow state dari
  browser.
- Jangan mengunggah kembali PDF yang baru saja diunduh dari backend.
- Jangan mengirim PDF sebagai Base64 dalam JSON.
- Jangan membentuk final PDF di browser sebagai sumber TTE.
- Jangan memakai modal bertumpuk untuk editor dan konfirmasi.
- Jangan mengandalkan jQuery sebagai state manager atau orchestrator TTE.
- Jangan mengandalkan class `.sign`, `.signModal`, atau `.tte` sebagai kontrak
  bisnis permanen.
- Jangan melakukan panggilan TTE beberapa QR secara paralel.
- Jangan menyimpan passphrase di store persisten, DOM attribute, local storage,
  session storage, log, telemetry, atau payload event.
- Jangan menambahkan Tailwind pada komponen eSign; gunakan Bootstrap/Argon dan
  CSS lokal yang ter-scope.
- Jangan membuat atau menjalankan test suite otomatis. Acceptance untuk scope
  frontend ini dilakukan manual dan terkontrol sampai pengguna mencabut
  keputusan tersebut secara eksplisit.

### 2.3 Temuan audit universalitas integrasi payment

Audit `Data\Detail`, seluruh pemakai komponen detail, registry workflow, action
bridge, dan data canonical pada 24 September 2026 menghasilkan batas berikut:

1. **Editor dan endpoint dapat dipakai ulang.** Kontrak editor hanya menerima
   `step_public_id`, `can_sign`, dan `can_verify`. Signing session kemudian
   menyelesaikan workflow, source artifact, signer, PDF, konfigurasi editor,
   prepared rendition, dan endpoint lanjutan dari backend. Editor tidak perlu
   mengetahui `payment_type`, `src_type`, folder, nama file, NIK, atau urutan
   jabatan.
2. **`Data\Detail` adalah titik integrasi lintas payment yang baik, tetapi masih
   legacy.** Controller tersebut melayani keluarga dokumen UP, GU_SKPD, GU_UK,
   LS, LS_GAJI, TU, dan KKPD. Hampir seluruh halaman payment memuat komponen
   detail yang sama. Namun `generateButtonTTE()` masih menentukan tombol dari
   `id_jabatan`, CSV `assigned_to`/`submit`/`status`, serta path `/File_{TYPE}`,
   kemudian menghasilkan `.signModal`.
3. **Action canonical belum universal.** `LsSppSigningActionResolver` masih
   mengunci `payment_type=LS` dan `document_type=SPP`; tombol canonical juga
   baru dipasang pada controller LS SPP. Payment lain tetap memakai action dan
   storage path legacy.
4. **Definisi workflow sudah luas, runtime workflow belum.**
   `DocumentSigningWorkflowDefinitionRegistry` sudah mendefinisikan dokumen TTE
   untuk UP, GU_SKPD, GU_UK, LS, LS_GAJI, TU, dan KKPD. `BMD`/`SPJ` sengaja
   tidak diprovisikan. Akan tetapi snapshot database saat audit hanya mempunyai
   dua workflow LS SPP dan satu workflow GU_SKPD NPD; dokumen aktif/historis
   lainnya belum otomatis siap dibuka oleh editor canonical.
5. **Activation, assignment, handoff, submit gate, dan compatibility projector
   belum universal.** Implementasi yang membuktikan urutan BP/BPP -> PPTK ->
   PA/KPA baru lengkap untuk vertical slice LS SPP. Editor boleh dipakai ulang,
   tetapi payment lain tidak boleh diaktifkan sebelum backend workflow-nya
   menegakkan urutan, assignment, projection `document`/`document_process`, dan
   kondisi submit masing-masing.
6. **Detail TBP mempunyai jalur terpisah.** `Data\DetailTbp` saat ini hanya
   menghasilkan view/download. Bila TBP ditandatangani dari daftar tersebut,
   response-nya juga harus menerima capability canonical yang sama.
7. **Hak melihat detail bukan hak menandatangani.** Scope organisasi pada
   `Data\Detail` tetap dipakai untuk visibility. Capability tombol harus
   dihitung dari canonical current step, sedangkan endpoint signing session
   tetap mengulang authorization authoritative. Flag dari browser tidak pernah
   menjadi bukti izin.
8. **Query capability lintas dokumen harus batch.** Saat capability dipasang ke
   `Data\Detail`, resolver harus mengambil current signable step untuk seluruh
   `document_id` yang tampil dalam satu query/bounded query, bukan query per row.
9. **Rollout harus allowlist per workflow.** Global frontend flag tidak cukup
   setelah resolver digeneralisasi. Aktivasi disarankan memakai allowlist
   pasangan `payment_type:src_type`; LS:SPP tetap menjadi pilot pertama dan
   payment berikutnya ditambahkan hanya setelah backend workflow terkait lulus.

Arsitektur target universal:

```text
Tabel payment / modal detail / daftar TBP
                  |
                  v
      DocumentSigningActionResolver
                  |
      step_public_id + capability
                  |
                  v
       satu Svelte EsignApp global
                  |
                  v
       signing session API canonical
```

Jangan membuat editor per payment. Perbedaan payment ditempatkan pada workflow
coordinator/policy backend. Resolver action dan Svelte editor tetap generik.

## 3. Hasil audit frontend TTE lama

### 3.1 Entry point lama

Snapshot source saat ini menggunakan dua pemicu:

- `.tte` hanya mengisi context global;
- `.signModal` mengisi context, memuat/validasi PDF, lalu membuka modal.

Pengguna menyebut implementasi lama pernah memakai class `.sign`. Perlakukan
`.sign`, `.signModal`, dan `.tte` sebagai variasi trigger legacy dengan masalah
arsitektur yang sama. Pada source yang diaudit, generator tombol aktif berada di
`Data\Detail::generateButton()` dan menghasilkan `.signModal` dengan atribut:

- `data-doc` berisi URL/path PDF;
- `data-urls` dan `data-location` berisi informasi folder;
- `data-files` berisi nama file;
- `data-status` berisi status lama.

Ini membuat UI mengetahui struktur storage dan ikut menentukan input proses
sign. Pola tersebut tidak boleh dipertahankan pada kontrak canonical.

### 3.2 Alur legacy yang ditemukan

```text
Tombol .sign/.signModal pada tabel
        |
        v
setSignContext() mengisi global _doc/_urls/_location/_ids/_status
        |
        v
resetPdfFromUrl(_doc) mengambil PDF sebagai Blob/File
        |
        +--> browser upload file yang sama ke POST /esign/validate
        |
        v
bundle.js/PDF.js menampilkan editor dan overlay
        |
        +--> POST /qr/generate memakai URL berbasis folder + UUID.pdf
        +--> footer.png ditempel otomatis ke seluruh halaman bila unsigned
        |
        v
CreatePdf() membuat domPDF di browser
        |
        v
Modal kedua #ConfirmSign meminta reason, passphrase, dan NIK Admin
        |
        v
POST /esign/sign berupa multipart FormData dan menunggu hasil sinkron
        |
        v
SignSuccess/SignFailed, countdown, tutup modal, reload DataTable
```

### 3.3 Detail teknis penting dari kode lama

1. `resources/views/components/esign/esign.blade.php` menyediakan tiga modal:
   `#signModal`, `#ConfirmSign`, dan `#ModalViewSignature`.
2. Blade memuat bundle monolitik `/assets/js/pdf/bundle.js` dan menyimpan context
   dokumen pada mutable global.
3. `resetPdfFromUrl()` mengunduh PDF, mengubah Blob menjadi `File`, lalu fungsi
   internal bundle mengunggah file tersebut ke `/esign/validate`.
4. Hasil validasi lama berupa array positional; UI membangun baris signer dengan
   string HTML.
5. QR dibuat melalui `/qr/generate`. URL QR lama disusun dari folder tipe
   dokumen dan UUID file.
6. Footer lama adalah image statis `/assets/img/footer.png`, ditempatkan pada
   seluruh halaman hanya ketika validator menyatakan dokumen belum memiliki
   signature.
7. Bundle mengekspor `domPDF` di browser dan mengirim file, gambar QR/footer,
   array koordinat, ukuran halaman, NIK, passphrase, reason, serta lokasi folder
   ke `/esign/sign`.
8. `signed.js` menggunakan state global tambahan, menunggu AJAX sinkron, lalu
   menjalankan countdown tiga detik dan memanggil langsung
   `mainTable.ajax.reload()` serta `tteDocumentTable.ajax.reload()`.

### 3.4 Hal baik yang dipertahankan

- Pengguna tidak perlu pindah halaman untuk melakukan TTE atau validasi.
- PDF divalidasi/diperiksa sebelum aksi final diaktifkan.
- Pengguna melihat dokumen dan posisi visual sebelum TTE.
- Tombol dikunci selama request untuk mencegah klik ganda.
- Spinner, status proses, pesan sukses, dan pesan gagal terlihat jelas.
- Passphrase dibersihkan saat proses selesai atau modal ditutup.
- Daftar payment diperbarui setelah proses selesai.
- Tampilan mengikuti Bootstrap/Argon yang sudah familier bagi pengguna.

### 3.5 Masalah yang harus dihilangkan

| Masalah legacy | Dampak | Pengganti canonical |
|---|---|---|
| Path dan nama file berada di DOM | Browser dapat memengaruhi source/destination | Hanya `step_public_id`; backend resolve artifact |
| Mutable global | Race saat beberapa tombol/modal dipakai | State Svelte per session |
| PDF diunduh lalu di-upload ulang untuk validasi | Bandwidth/memori ganda | Verifikasi artifact di backend |
| Final PDF dibuat browser | Byte/geometri sulit dijamin | Prepared rendition backend |
| QR URL berbasis folder | Tidak immutable dan tergantung layout lama | `/verify/{public_id}` per operasi QR |
| Footer berupa image statis | Tidak dapat diatur sesuai kebutuhan | Footer text/style/position terstruktur |
| NIK dapat diinput Admin | Risiko proxy-sign/identitas salah | NIK di-resolve backend dari signer sah |
| Dua modal bertumpuk | Fokus, backdrop, dan keyboard bermasalah | Satu modal dengan tahap internal |
| Request sign sinkron | Putus koneksi user mengganggu UX | `202 Accepted` + queue + polling |
| Response array positional | Rapuh dan sulit di-maintain | JSON object bertipe |
| Reload DataTable global | Coupling ke halaman tertentu | Custom event completion |
| Worker PDF via CDN | Risiko availability/version drift | Dependency bundling yang terkunci |
| Object URL tidak terkelola jelas | Potensi memory leak | revoke saat ganti/close/unmount |

## 4. Pemetaan legacy ke frontend baru

| Legacy | Rancangan baru |
|---|---|
| `.sign`, `.signModal`, `.tte` | `[data-esign-action="sign"][data-esign-step]` sementara, lalu custom event |
| `setSignContext()` | `openSigning(stepPublicId)` pada Svelte root |
| `_doc`, `_urls`, `_location`, `_ids` | Tidak ada; backend resolve seluruh context |
| `resetPdfFromUrl(_doc)` | `POST signing-sessions`, lalu fetch `preview_url` binary |
| `/esign/validate` dengan file upload | Endpoint verifikasi canonical berbasis artifact/document |
| editor di `bundle.js` | Komponen Svelte + PDF.js + overlay DOM |
| `/qr/generate` dari browser | QR authoritative dihasilkan backend saat prepare rendition |
| `footer.png` | Footer terstruktur dan dirender backend |
| `CreatePdf()` | `POST .../renditions` dengan placement/footer JSON |
| `#ConfirmSign` | Tahap confirmation di modal yang sama |
| input NIK | Dihapus |
| `SignProcess()` + `/esign/sign` | `POST sign_url`, menerima HTTP 202 |
| countdown tiga detik | Progress nyata dari `status_url` |
| retry request sign | Resume hanya jika backend memberi `resume_url` |
| `mainTable.ajax.reload()` | `sitangkas:esign:completed` ditangani adapter halaman |

## 5. Kontrak backend yang sudah dapat dipakai frontend

Route internal aktual berada dalam middleware autentikasi aplikasi:

| Method | Endpoint | Fungsi frontend |
|---|---|---|
| `POST` | `/esign/internal/signing-sessions` | Membuka session dari `step_public_id` |
| `GET` | `/esign/internal/signing-sessions/{session}` | Memulihkan context/prepared rendition |
| `GET` | `/esign/internal/signing-sessions/{session}/preview` | Mengambil source PDF binary |
| `POST` | `/esign/internal/signing-sessions/{session}/renditions` | Mengirim QR/footer plan |
| `GET` | `/esign/internal/signing-sessions/{session}/renditions/{revision}/preview` | Review exact prepared PDF |
| `GET` | `/esign/internal/signing-sessions/{session}/renditions/{revision}/operations/{index}/qr` | Mengambil visual QR authoritative |
| `POST` | `/esign/internal/signing-sessions/{session}/sign` | Membuat attempt, HTTP 202 |
| `DELETE` | `/esign/internal/signing-sessions/{session}` | Menutup persiapan tanpa audit bisnis |
| `GET` | `/esign/internal/attempts/{attempt}` | Poll progress/status |
| `POST` | `/esign/internal/attempts/{attempt}/resume` | Melanjutkan partial failure dengan passphrase baru |

### 5.1 Request yang menjadi boundary frontend

Membuka session:

```json
{
  "step_public_id": "uuid"
}
```

Mempersiapkan rendition:

```json
{
  "placements": [
    {
      "client_id": "uuid",
      "operation_index": 0,
      "page": 1,
      "page_width": 595.28,
      "page_height": 841.89,
      "page_rotation": 0,
      "origin_x": 420,
      "origin_y": 650,
      "width": 90,
      "height": 90
    }
  ],
  "footer": null
}
```

Final sign:

```json
{
  "affirmed": true,
  "idempotency_key": "uuid-yang-dibuat-frontend",
  "passphrase": "dikirim-sekali-melalui-HTTPS",
  "prepared_revision": "uuid",
  "preview_sha256": "sha256-prepared-rendition"
}
```

Resume:

```json
{
  "affirmed": true,
  "passphrase": "passphrase-baru"
}
```

Frontend wajib memakai URL yang dikirim response backend. Endpoint literal di
atas hanya dokumentasi; jangan menyebarkan hardcoded URL pada komponen.

### 5.2 Data editor dari backend

Session mengirim konfigurasi authoritative, antara lain:

- origin koordinat `top_left` dan unit `pt`;
- ukuran minimum/maksimum QR;
- maximum signature count;
- safe margin dan minimum gap;
- page geometry dan aturan rotated page;
- apakah footer boleh/wajib;
- default text, font, size, style, serta placement footer per halaman;
- whitelist font dan batas ukuran font;
- signature state dan prepared rendition terakhir bila ada.

Frontend tidak membuat batas bisnis sendiri bila nilai sudah dikirim backend.

### 5.3 Gap backend sebelum pilot frontend nyata

Backend visible dan multi-operation tersedia, tetapi frontend tidak boleh
menganggap seluruh ekosistem sudah selesai. Dependency berikut harus ditutup
atau dibuat fail-closed pada vertical slice:

1. action LS SPP harus mengirim canonical `step_public_id` dan capability
   `can_sign`/`can_verify`, bukan path file lama;
2. feature flag multi-operation harus diaktifkan secara terkontrol setelah
   preflight environment;
3. endpoint verifikasi internal berbasis canonical artifact/document untuk
   modal validasi belum tersedia dalam kontrak final;
4. `/verify/{public_id}` dan authorized result download/preview final belum
   lengkap;
5. production process manager untuk queue `signatures`, shared cache, dan
   observability tetap menjadi gate deployment;
6. reconciliation untuk outcome `unknown` harus tersedia sebelum rollout luas.

Frontend boleh dibangun dengan adapter/API interface lebih dahulu, tetapi pilot
real tidak boleh menyamarkan gap tersebut dengan fallback path publik lama.

## 6. State machine satu modal

Satu instance modal signing mempunyai state eksplisit:

```text
closed
  -> creating_session
  -> loading_source
  -> editing
  -> preparing_rendition
  -> confirming_prepared
  -> submitting
  -> processing
       -> succeeded
       -> requires_passphrase
       -> unknown
       -> failed
  -> closed
```

Aturan state:

- hanya `editing` yang menerima perubahan QR/footer;
- `preparing_rendition` mengunci editor;
- perubahan setelah prepare membuang prepared revision dari state client;
- `confirming_prepared` menampilkan binary dari
  `prepared_rendition.preview_url`, informasi dokumen/signer, dan passphrase
  dalam satu tampilan;
- tidak ada state review prepared yang terpisah dan tidak ada checkbox
  afirmasi;
- passphrase baru dibuat/diisi hanya di `confirming_prepared` atau
  `requires_passphrase`;
- setelah server menerima final request dan memberi 202, session editor tidak
  lagi menjadi source of truth; attempt adalah source of truth;
- `unknown` bukan `failed` dan tidak menampilkan tombol retry biasa;
- close sebelum 202 menghapus session; close setelah 202 tidak membatalkan job.

Modal validasi memakai state terpisah pada root yang sama:

```text
closed -> loading -> valid | invalid | unavailable -> closed
```

Hanya satu modal Bootstrap boleh aktif pada satu waktu.

## 7. Struktur frontend target

```text
resources/js/esign/
|-- main.ts
|-- EsignApp.svelte
|-- api/
|   |-- esign-api.ts
|   `-- http-client.ts
|-- bridge/
|   |-- blade-event-bridge.ts
|   `-- payment-table-adapter.ts
|-- components/
|   |-- signing/
|   |   |-- EsignSigningModal.svelte
|   |   |-- SigningEditor.svelte
|   |   |-- SignatureInspector.svelte
|   |   |-- FooterInspector.svelte
|   |   |-- PreparedConfirmation.svelte
|   |   `-- SigningProgress.svelte
|   |-- validation/
|   |   |-- EsignValidationModal.svelte
|   |   |-- VerificationSummary.svelte
|   |   `-- VerificationSignerTable.svelte
|   `-- pdf/
|       |-- PdfViewer.svelte
|       |-- PdfPage.svelte
|       |-- PdfToolbar.svelte
|       |-- PdfThumbnails.svelte
|       `-- PlacementOverlay.svelte
|-- geometry/
|   |-- pdf-coordinates.ts
|   `-- placement-validation.ts
|-- state/
|   |-- signing-state.svelte.ts
|   `-- validation-state.svelte.ts
|-- styles/
|   `-- esign.css
`-- types.ts
```

Struktur dapat dipadatkan pada implementasi pertama bila komponen masih kecil,
tetapi boundary `api`, `geometry`, `state`, dan `bridge` harus tetap terpisah.

## 8. Urutan implementasi frontend

### Tahap F0 - Kunci kontrak integrasi dan gap — SELESAI

Tujuan: mencegah frontend dibangun di atas asumsi endpoint atau response lama.

Pekerjaan:

1. snapshot response session, editor configuration, prepared rendition, sign,
   attempt status, dan resume;
2. definisikan type TypeScript sesuai object JSON aktual;
3. klasifikasikan error `401`, `403`, `404`, `409`, `422`, `429`, dan `5xx`;
4. putuskan capability action LS SPP: `step_public_id`, `can_sign`, `can_verify`;
5. catat endpoint validasi canonical dan result delivery yang masih gap;
6. jangan memasukkan file path atau URL storage ke kontrak tombol.

Hasil: kontrak aktual sudah dikunci di
`ESIGN_FRONTEND_BACKEND_CONTRACT_V1.md` dan type map berada di
`resources/js/esign/types.ts`. Audit juga mencatat gap metadata dokumen,
validasi canonical, result delivery, serta recovery active attempt agar tidak
dianggap sebagai endpoint yang sudah tersedia.

Gate selesai pada 24 September 2026: setiap endpoint aktif sudah dipetakan tanpa
array positional dan tanpa `any` untuk field utama. Perbedaan response create
dan show session dipertahankan sebagai dua type berbeda.

### Tahap F1 - Bridge backend LS SPP ke action frontend — SELESAI DI SOURCE

Tujuan: tombol payment membawa identity canonical minimum.

Pekerjaan:

1. action dari controller/JSON DataTable hanya mengekspos
   `data-esign-step="{step_public_id}"` dan jenis action;
2. backend tidak merender tombol TTE bila policy/capability tidak lulus;
3. klik didelegasikan pada document karena row DataTable bersifat dinamis;
4. adapter mengirim event `sitangkas:esign:open` dengan `step_public_id`,
   `can_sign`, dan `can_verify`;
5. pertahankan `.signModal` hanya sementara pada row yang belum dicutover;
6. jangan memasang handler baru dan legacy pada tombol yang sama.

Hasil: LS SPP sekarang mempunyai capability `esign` fail-closed dan button
`data-esign-action="sign"` yang menerbitkan event `sitangkas:esign:open` tanpa
mengetahui path PDF. Resolver memakai correlated subquery agar tidak membuat
N+1, sedangkan create session tetap mengulang authorization authoritative.

Rollout guard: `SIGNATURE_FRONTEND_ENABLED` default `false` dan action juga
mensyaratkan `SIGNATURE_MULTI_OPERATION_ENABLED=true`. Flag frontend baru boleh
diaktifkan setelah shell Svelte F2 terpasang.

Gate source selesai pada 24 September 2026: delegated listener hanya menangkap
button canonical, menghasilkan tepat satu event, dan tidak memanggil
`resetPdfFromUrl()` atau modal legacy. Build Vite production berhasil. Acceptance
klik di browser menunggu lifecycle F3, session state F5, dan aktivasi flag
terkontrol.

### Tahap F2 - Fondasi Svelte/Vite island — SELESAI DI SOURCE

Tujuan: menyediakan satu root UI yang hidup berdampingan dengan Blade.

Pekerjaan:

1. tambah dependency Svelte, plugin Vite Svelte, TypeScript support yang sesuai,
   dan PDF.js setelah perubahan dependency diotorisasi;
2. tambahkan plugin Svelte ke `vite.config.js` tanpa merusak input Laravel;
3. mount satu `EsignApp` dari `resources/js/app.js` atau lazy entry yang dipanggil
   ketika action eSign pertama digunakan;
4. tambahkan satu root element pada layout/component shared;
5. gunakan dynamic import agar PDF.js/editor tidak masuk initial bundle seluruh
   halaman;
6. scope CSS di bawah `.esign-ui`/`.esign-editor`.

Hasil: modal shell dapat dibuka/tutup dari custom event.

Gate selesai: halaman non-eSign tidak memuat engine PDF pada initial load dan
layout Bootstrap/Argon tetap normal.

Realisasi source 24 September 2026:

- Svelte 5, plugin Vite Svelte, TypeScript, dan `pdfjs-dist` sudah tercatat di
  dependency project;
- plugin Svelte dan `vitePreprocess()` sudah dikonfigurasi;
- layout authenticated memiliki tepat satu `data-esign-app-root`;
- `app.js` hanya memasang loader event dan action bridge; komponen Svelte serta
  CSS eSign dimuat melalui dynamic import saat event open pertama;
- `EsignApp.svelte` menyediakan shell modal Bootstrap 5/Argon minimal yang dapat
  dibuka dan ditutup; ini belum memanggil signing-session dan bukan editor F6;
- seluruh CSS eSign berada di bawah `.esign-ui`; tidak ada utility Tailwind pada
  komponen;
- `pdfjs-dist` belum diimpor oleh entry awal dan baru boleh dimuat lazy pada
  tahap viewer;
- `SIGNATURE_FRONTEND_ENABLED` tetap default `false`; source-ready F2 bukan izin
  rollout operasional.

Build produksi Vite adalah verifikasi F2 yang diizinkan. Tidak ada test suite
yang dibuat atau dijalankan.

### Tahap F3 - Event bridge Blade/DataTable/Svelte — SELESAI DI SOURCE

Tujuan: menghilangkan coupling global UI.

Event minimal:

```text
sitangkas:esign:open
sitangkas:esign:validation-open
sitangkas:esign:completed
sitangkas:esign:closed
```

Pekerjaan:

1. event open hanya membawa `step_public_id` serta boolean capability, tanpa
   path file atau data sensitif;
2. Svelte menangani session/modal state;
3. event completed membawa identity canonical publik yang aman, bukan
   passphrase atau integer primary key;
4. adapter halaman memutuskan tabel mana yang direload;
5. bila DataTable tidak ada, completion tetap tidak error;
6. listener dibersihkan saat unmount/HMR.

Hasil: Svelte tidak mengenal global `mainTable` atau `tteDocumentTable`.

Realisasi source 25 September 2026:

- kontrak dan validator runtime tersedia untuk `open`, `validation-open`,
  `completed`, dan `closed`;
- event signing hanya menerima `step_public_id` serta capability boolean;
- event validation hanya menerima `artifact_public_id` dan `can_verify=true`;
- event completion dikunci ke hasil `succeeded` dan membawa
  `step_public_id`, `attempt_id`, serta `result_artifact_id`, semuanya UUID;
- raw `document.id` tidak dimasukkan karena client signing-session sengaja
  tidak mengekspos integer tersebut. Adapter tidak membutuhkan document ID
  untuk me-refresh read model halaman;
- action bridge dapat menerbitkan sign/verify event tanpa path, nama file,
  NIK, passphrase, atau response provider;
- island loader mengubah event menjadi discriminated state `signing` atau
  `validation`, dan melepaskan listener serta instance Svelte ketika HMR;
- page adapter hanya me-reload DataTable yang secara eksplisit mempunyai
  `data-esign-refresh-on-complete`. LS SPP main table dan modal detail menjadi
  opt-in pertama;
- adapter menggunakan API DataTable dari elemen DOM, bukan global
  `mainTable`/`tteDocumentTable`. Bila DataTable belum dibuat atau library tidak
  tersedia, event completion berakhir tanpa error;
- `validation-open` baru merupakan boundary event. Belum ada tombol canonical
  yang mengaktifkannya karena endpoint validasi artifact adalah pekerjaan F13;
- helper dispatch completion sudah tersedia untuk F11/F12, tetapi F3 tidak
  membuat completion palsu sebelum attempt benar-benar sukses.

Build Vite dan pemeriksaan TypeScript adalah verifikasi source F3 yang
diizinkan. Tidak ada test suite yang dibuat atau dijalankan. Feature flag tetap
`false` sampai F4/F5 siap dan acceptance manual dilakukan.

### Tahap F4 - Shell modal Bootstrap 5/Argon — SELESAI DI SOURCE

Tujuan: mempertahankan UX familiar tanpa nested modal.

Pekerjaan:

1. gunakan Bootstrap Modal JavaScript API;
2. satu modal besar `modal-xl`, scrollable, dan fullscreen pada layar kecil;
3. header menunjukkan dokumen, signer, step, status, dan close;
4. body berganti antara loading, editor, prepared confirmation, progress, dan
   result;
5. footer mempunyai tombol sesuai state, bukan tombol global statis;
6. fokus awal, Escape, backdrop, `aria-*`, dan focus restoration harus benar;
7. close saat request prepare/sign berlangsung meminta pengguna menunggu;
8. close setelah 202 diperbolehkan dengan pesan bahwa proses tetap berjalan.

Hasil: tidak ada lagi `#ConfirmSign` yang ditumpuk di atas `#signModal`.

Realisasi source 25 September 2026:

- `EsignApp.svelte` sekarang memakai satu modal Bootstrap
  `modal-xl modal-dialog-scrollable modal-fullscreen-lg-down` untuk seluruh
  alur signing dan boundary validasi;
- empat tahap visual `Atur Posisi`, `Konfirmasi`, `Proses`, dan `Selesai`
  tersedia melalui stepper semantik dengan `aria-current`;
- shell body mencakup state struktural `loading`, `editing`,
  `preparing_rendition`, `confirming_prepared`, `submitting`, `processing`,
  `succeeded`, `requires_passphrase`, `unknown`, dan `failed`;
- footer berubah menurut state. Kontrol editor/submit masih sengaja disabled
  sampai session state, geometry, prepared rendition, dan sign handler pada
  tahap berikutnya tersedia;
- Escape, backdrop, dan tombol close hanya diblokir ketika request prepare
  atau submit sedang dikirim. Setelah attempt diterima dan masuk state
  `processing`, modal boleh ditutup karena queue server tetap berjalan;
- fokus diarahkan ke judul saat modal tampil dan dikembalikan ke trigger asal
  setelah modal ditutup;
- layout desktop, tablet, mobile, dark mode, dan reduced motion berada di CSS
  scoped `.esign-ui`, memakai Bootstrap 5/Argon tanpa utility Tailwind;
- tidak ada nested modal dan tidak ada checkbox afirmasi. Konfirmasi dan
  passphrase tetap menjadi satu tahap saat F10 dihubungkan;
- data dokumen/signer, PDF, QR/footer, passphrase, serta request API belum
  difabrikasi di F4. Semua tetap menunggu F5-F12 sebagai source authoritative.

Type-check TypeScript dan build production Vite berhasil. Tidak ada test suite
yang dibuat atau dijalankan. `SIGNATURE_FRONTEND_ENABLED` tetap `false` sampai
F5 selesai dan acceptance manual terkontrol dilakukan.

### Tahap F5 - Typed API client dan error normalization

Tujuan: seluruh request melalui satu boundary.

Pekerjaan:

1. ambil CSRF token dari meta layout;
2. selalu kirim `Accept: application/json` pada JSON request;
3. gunakan response `data` object bertipe;
4. normalisasi validation errors tanpa memasukkan raw provider response ke UI;
5. tangani session expired, authorization, conflict/stale revision, rate limit,
   dan service unavailable dengan pesan berbeda;
6. gunakan `AbortController` untuk fetch preview yang sudah tidak relevan;
7. URL request sesudah create session harus berasal dari response backend;
8. jangan retry otomatis request sign/resume.

Hasil: komponen tidak memanggil `fetch` tersebar dan tidak mempunyai endpoint
hardcoded sendiri.

### Tahap F6 - PDF viewer binary yang efisien

Tujuan: membuka PDF canonical tanpa upload ulang/Base64.

Pekerjaan:

1. fetch `preview_url` sebagai `ArrayBuffer` atau Blob authorized;
2. load PDF.js worker dari dependency lokal yang version-locked;
3. render halaman aktif dan halaman sekitar, bukan seluruh dokumen resolusi penuh
   secara bersamaan;
4. thumbnail dirender bertahap/idle;
5. canvas PDF dan overlay placement dipisahkan;
6. perubahan overlay tidak memicu render ulang canvas PDF;
7. kelola zoom, rotation metadata, resize, dan device pixel ratio;
8. revoke object URL serta destroy PDF loading/render task saat ganti dokumen,
   close modal, atau unmount.

Hasil: file besar tidak diduplikasi sebagai Base64 dan memory dilepas dengan
jelas.

### Tahap F7 - Geometry canonical

Tujuan: posisi browser sama dengan posisi backend/BSrE.

Pekerjaan:

1. koordinat payload memakai PDF point, origin top-left;
2. pixel DOM hanya untuk rendering interaktif;
3. fungsi transform mempertimbangkan scale dan page bounding box;
4. page, width, height, rotation, origin, dan size dikirim eksplisit;
5. snap/keyboard movement tidak boleh melewati safe margin;
6. minimum gap dan collision divalidasi lokal untuk feedback cepat;
7. backend tetap validator final;
8. halaman rotated ditolak bila `rotated_pages_supported=false`.

Hasil: transform adalah pure function yang dipakai drag, resize, preview, dan
serialization.

### Tahap F8 - Editor multi-QR

Tujuan: satu signer dapat menempatkan N QR dalam satu klik TTE.

Pekerjaan:

1. tombol `Tambah QR` membuat placement dengan UUID client;
2. QR default ditempatkan di center viewport/page aktif yang aman;
3. placement dapat dipilih, dipindah, resize proporsional, dan dihapus;
4. nomor operasi terlihat pada overlay/inspector;
5. `operation_index` selalu dinormalisasi `0..N-1` secara deterministik;
6. minimal satu QR dan maksimal mengikuti backend
   `maximum_signature_count`;
7. satu QR mempunyai URL/public ID berbeda, tetapi seluruh QR berada dalam satu
   attempt dan milik signer/step yang sama;
8. visual QR final diambil dari URL authoritative backend setelah rendition
   dipersiapkan; preview awal boleh memakai placeholder yang jelas.

Hasil: beberapa QR dapat disusun tanpa meminta passphrase berulang kali.

### Tahap F9 - Footer editor bersyarat

Tujuan: footer otomatis tetapi tetap dapat disesuaikan pada PDF unsigned.

Pekerjaan:

1. saat QR pertama dibuat dan backend menyatakan footer allowed+required, buat
   footer default pada seluruh halaman dari configuration;
2. editor menyediakan text, font whitelist, font size, bold, italic, underline;
3. setiap halaman mempunyai placement yang dapat digeser;
4. perubahan style global diterapkan konsisten pada seluruh placement footer;
5. posisi per halaman dapat berbeda;
6. footer tidak dapat dihapus bila backend menyatakannya required;
7. bila dokumen signed atau `footer.allowed=false`, jangan menampilkan editor
   footer dan kirim `footer: null`;
8. jangan membaca keberadaan signature hanya dari canvas; gunakan session state
   backend.

Hasil: footer baru tidak lagi berupa image statis dan kebijakan signed/unsigned
tetap fail-closed.

### Tahap F10 - Prepare dan konfirmasi authoritative terpadu

Tujuan: backend mengunci byte/geometri lalu menampilkan prepared preview,
informasi dokumen/signer, dan passphrase dalam satu tahap Konfirmasi.

Pekerjaan:

1. serialisasi placement/footer ke canonical JSON;
2. POST ke `prepare_rendition_url`;
3. tampilkan validation error pada placement terkait bila memungkinkan;
4. load `prepared_rendition.preview_url` sebagai PDF binary;
5. gunakan `signature_operations[*].qr_image_url` untuk visual authoritative;
6. tampilkan prepared preview di sisi utama serta jumlah QR, halaman, informasi
   dokumen/signer, dan ringkasan footer di panel konfirmasi;
7. setiap kembali ke editor dan melakukan perubahan menghapus reference prepared
   revision/hash dari state;
8. jangan aktifkan passphrase/tombol final sebelum exact prepared preview
   selesai dimuat;
9. jangan membuat layar `Tinjau prepared rendition` terpisah.

Hasil: tidak ada final sign terhadap preview client yang stale.

### Tahap F11 - Final action dan submit asynchronous

Tujuan: satu tindakan final yang aman dan idempotent.

Pekerjaan:

1. gunakan tahap `confirming_prepared` dari F10 pada modal yang sama;
2. tampilkan signer, masked NIK bila diberikan backend, jumlah operasi, dan
   konsekuensi tombol final sebagai text informatif;
3. jangan menampilkan input NIK;
4. passphrase hanya berada dalam local component variable;
5. buat UUID idempotency sekali untuk satu tindakan submit;
6. jangan menampilkan checkbox `Saya telah memeriksa dokumen` atau checkbox
   afirmasi lain;
7. klik `Tandatangani Sekarang` menjadi afirmasi eksplisit dan handler mengirim
   `affirmed=true`, idempotency key, passphrase, prepared revision, serta
   SHA-256;
8. setelah response 202, kosongkan passphrase segera;
9. simpan attempt ID/status URL di memory agar polling dapat dimulai;
10. tombol submit tidak boleh bisa diklik ulang selama outcome belum jelas.

Hasil: koneksi browser tidak perlu tetap hidup selama panggilan provider.

### Tahap F12 - Progress, partial resume, dan unknown

Tujuan: status background dapat dipahami dan tidak memicu duplicate sign.

Pekerjaan:

1. polling memakai `next_poll_after_ms` dari backend;
2. tampilkan `completed/planned`, current index, serta status tiap operation;
3. hentikan polling ketika terminal atau modal ditutup;
4. bila modal dibuka lagi untuk attempt aktif, pulihkan status dari backend;
5. `requires_passphrase=true` menampilkan form passphrase baru dan hanya memakai
   `resume_url` dari backend;
6. resume tidak mengulang operation yang sudah completed;
7. `requires_reconciliation=true` menampilkan status tertahan dan melarang
   tombol retry;
8. sukses mengirim `sitangkas:esign:completed` untuk refresh halaman;
9. gagal teknis yang retryable tidak otomatis diulang oleh browser.

Hasil: progress merepresentasikan worker nyata, bukan countdown buatan.

### Tahap F13 - Modal validasi canonical

Tujuan: mengganti `/esign/validate` yang mengharuskan upload ulang blob.

Pekerjaan backend sebelum UI:

1. sediakan endpoint verification berdasarkan artifact/document public ID;
2. lakukan authorization dan resolve exact canonical artifact;
3. kembalikan status valid/invalid/unavailable, jumlah signature, signer, waktu,
   reason, dan metadata aman sebagai object;
4. sediakan authorized binary preview URL secara terpisah;
5. cache/retry verifikasi read-only sesuai kebijakan backend.

Pekerjaan frontend:

1. viewer dan summary dimuat paralel bila endpoint sudah tersedia;
2. gunakan table Bootstrap/Argon, bukan raw HTML string;
3. bedakan `invalid` dari `service unavailable`;
4. jangan mengizinkan TTE ketika status prasyarat tidak dapat dipastikan;
5. tidak ada upload lokal pada modal validasi workflow ini.

Hasil: verifikasi tidak menggandakan transfer file browser-server.

### Tahap F14 - Pilot manual LS SPP BP/BPP

Tujuan: membuktikan satu vertical slice nyata sebelum rollout.

Urutan acceptance manual:

1. BP/BPP login pada posisi bisnis sah;
2. buka LS SPP yang canonical step pertamanya aktif/signable;
3. buat session dan pastikan artifact yang tampil tepat;
4. coba satu QR pada satu halaman;
5. coba dua QR pada halaman berbeda dengan satu klik TTE;
6. periksa footer unsigned dibuat di semua halaman dan dapat diedit;
7. review exact prepared rendition;
8. submit dan pastikan response 202;
9. tutup modal/koneksi browser setelah 202 dan pastikan worker tetap selesai;
10. lihat progress dan hasil terminal;
11. pastikan compatibility projection dan handoff berikutnya baru dapat berjalan
    setelah canonical step completed;
12. pastikan PPTK/PA menerima output step sebelumnya sebagai source;
13. pastikan dokumen yang sudah TTE tidak menerima footer baru;
14. lakukan skenario passphrase salah/retryable, partial resume, dan unknown hanya
    dengan kondisi terkontrol yang tidak menghasilkan duplicate signature;
15. periksa responsive, dark mode, keyboard, memory, dan file historis besar.

Acceptance ini manual. Agent dilarang membuat test suite sebagai pengganti.

### Tahap F15 - Public verify dan result delivery

Tujuan: QR hasil TTE mempunyai tujuan yang stabil dan aman.

Pekerjaan:

1. setiap QR memakai public ID unik dan URL
   `https://sitangkas.malangkota.go.id/verify/{public_id}`;
2. public ID baru aktif hanya setelah seluruh attempt sukses/final verify;
3. halaman publik tetap Blade + Bootstrap/Argon, bukan Svelte island editor;
4. tampilkan status, nomor dokumen bila ada, nama signer, dan tanggal TTE;
5. guest tidak mendapat tombol download kecuali public-access policy memang
   mengizinkan derivative public-watermarked;
6. user login tetap melalui authorization dokumen sebelum download;
7. legacy QR URL di-resolve exact lalu redirect ke public ID yang benar.

Hasil: layout folder tidak lagi menjadi identitas dokumen.

### Tahap F16 - Rollout dan retirement legacy

Tujuan: perluasan bertahap tanpa menghentikan layanan.

Pekerjaan:

1. aktifkan feature flag per payment/workflow;
2. perluas dari LS SPP ke LS SPM/SP2D setelah matrix dan backend masing-masing
   siap;
3. monitor attempt, latency, partial, unknown, queue depth, dan error rate;
4. pertahankan legacy component hanya untuk area yang belum cutover;
5. jangan memuat bundle lama pada halaman yang sudah sepenuhnya canonical;
6. setelah seluruh payment parity, hapus include/handler legacy melalui perubahan
   terpisah dan ter-review;
7. folder/tabel compatibility tidak ikut dihapus oleh rollout frontend.

Hasil: tidak ada big-bang deployment dan rollback dapat dilakukan per workflow.

## 9. Alur TTE baru end-to-end

### 9.1 Membuka editor

1. User menekan tombol TTE pada LS SPP.
2. Tombol hanya membawa `step_public_id`.
3. Adapter mengirim `sitangkas:esign:open`.
4. Svelte membuka modal pada state `creating_session`.
5. Frontend POST `step_public_id` ke endpoint session.
6. Backend memeriksa user, posisi nyata, assignment, urutan step, artifact, tahun,
   dan capability.
7. Frontend menerima session/editor config/preview URL.
8. PDF source dimuat sebagai binary dan modal masuk state `editing`.

### 9.2 Mengatur QR dan footer

1. User memilih halaman dan menekan `Tambah QR`.
2. QR ditempatkan pada posisi default aman dan dapat digeser/resize.
3. Jika dokumen unsigned dan ini QR pertama, footer default dibuat pada semua
   halaman.
4. User dapat mengubah text/font/size/bold/italic/underline serta menggeser footer
   tiap halaman.
5. User dapat menambah QR lain untuk jabatan/step yang sama tanpa melakukan TTE
   kedua kali.
6. Urutan operasi terlihat dan disimpan deterministik.

### 9.3 Menyiapkan rendition dan membuka konfirmasi

1. User menekan `Lanjutkan`.
2. Frontend mengirim JSON placement/footer, bukan PDF.
3. Backend memvalidasi koordinat, collision, safe area, jumlah QR, footer, dan
   source fingerprint.
4. Backend membuat QR berlogo Malang, exact prepared PDF, revision, dan SHA-256.
5. Frontend memuat prepared PDF dari backend dan menampilkan QR authoritative
   sebagai overlay pada koordinat yang akan dikirim ke provider. Prepared PDF
   adalah exact input sebelum operasi TTE, bukan PDF hasil signature.
6. Frontend langsung menampilkan tahap Konfirmasi yang berisi prepared preview,
   informasi dokumen/signer, dan input passphrase.
7. Jika user kembali mengedit, revision lama tidak dapat dipakai untuk sign.

### 9.4 Konfirmasi dan background signing

1. User memasukkan passphrase pada tahap Konfirmasi.
2. Tidak ada checkbox afirmasi. Klik `Tandatangani Sekarang` menjadi afirmasi
   eksplisit dan frontend mengirim `affirmed=true`.
3. Frontend mengirim final request dengan idempotency key/revision/hash.
4. Backend membuat attempt dan memberi `202 Accepted`.
5. Passphrase dibersihkan dari frontend.
6. Worker `signatures` memproses operasi secara serial:
   output QR 1 menjadi input QR 2, dan seterusnya.
7. Jika seluruh operasi berhasil dan verifikasi final lulus, final artifact
   diaktifkan, public IDs diaktifkan, step completed, lalu compatibility
   projection ditulis satu kali.
8. Frontend polling status dan menampilkan progress sebenarnya.

### 9.5 Hasil tidak normal

- Jika request belum mencapai server, tidak ada attempt; user dapat mengirim
  ulang dengan idempotency key yang sama selama context masih relevan.
- Jika operasi belum pernah terkirim dan backend menyatakan retryable, status
  dapat meminta passphrase baru melalui resume.
- Jika beberapa QR sudah sukses, resume dimulai dari operasi belum selesai.
- Jika outcome provider ambigu, attempt menjadi `unknown`; user tidak diberi
  tombol retry sampai reconciliation menentukan hasil.
- Menutup modal setelah 202 tidak membatalkan attempt.

## 10. Rekomendasi performa

1. Lazy-load Svelte editor/PDF.js hanya ketika modal pertama kali diminta.
2. Gunakan binary stream/ArrayBuffer; Base64 menambah ukuran dan duplikasi memori.
3. Render canvas berdasarkan viewport dengan buffer satu halaman di depan dan
   belakang.
4. Render thumbnail secara bertahap ketika browser idle.
5. Simpan QR/footer sebagai overlay DOM; drag tidak boleh merender ulang PDF.
6. Batasi update state saat pointer move dengan `requestAnimationFrame`.
7. Jangan POST prepare pada setiap drag. Prepare hanya saat user menekan review;
   autosave metadata, bila kelak diperlukan, harus debounce dan tidak merender
   PDF setiap perubahan.
8. Batalkan fetch/render lama menggunakan `AbortController` dan PDF.js cancel
   task.
9. Revoke seluruh object URL dan lepas canvas besar saat modal ditutup.
10. Ikuti `next_poll_after_ms`; jangan melakukan polling interval agresif.
11. Berhenti polling ketika tab hidden dan lanjutkan dengan status refresh saat
    visible, kecuali kebutuhan operasional menentukan lain.
12. Jangan mengunduh QR untuk seluruh operation berulang kali; cache per
    `revision + operation_index` selama session aktif.
13. Gunakan satu Svelte root dan satu instance modal, bukan mount per row tabel.
14. Muat detail inspector hanya untuk placement aktif agar DOM tetap ringan.

## 11. Rekomendasi keamanan dan audit

- Authorization selalu dilakukan backend pada create/show/preview/prepare/sign,
  bukan berdasarkan tombol visible.
- NIK resolved backend dan tidak pernah dapat diedit frontend.
- Passphrase memakai `autocomplete="off"`, `autocapitalize="none"`, dan
  `spellcheck="false"`; visibility toggle otomatis kembali tersembunyi.
- Passphrase dibersihkan pada 202, error terminal, close, timeout, dan unmount.
- Jangan memasukkan payload passphrase atau raw vendor error ke monitoring.
- Gunakan CSRF aplikasi dan same-origin credential.
- Semua text response dirender sebagai text yang di-escape; jangan memakai raw
  HTML dari provider.
- `preview_sha256` dan prepared revision wajib dikirim kembali untuk mencegah
  stale signing.
- Idempotency key dibuat per intent, bukan per retry HTTP acak.
- Cancel sebelum final sign tidak membuat event audit bisnis; backend hanya
  membersihkan temporary session/rendition.

## 12. Coexistence dan cutover dari UI lama

Selama rollout, legacy dan canonical boleh hidup pada halaman/payment berbeda,
tetapi tidak boleh menangani tombol yang sama.

Aturan:

1. row canonical memakai `data-esign-action` dan `data-esign-step`;
2. row legacy tetap memakai `.signModal` sampai payment tersebut dicutover;
3. component legacy `components.esign.esign` hanya di-include bila halaman masih
   mempunyai action legacy;
4. halaman canonical memuat satu Svelte root, bukan `bundle.js`;
5. feature flag menentukan renderer action backend, bukan JavaScript menebak
   jenis workflow;
6. rollback mengembalikan flag/payment adapter, bukan mengubah data attempt yang
   sudah tercatat;
7. attempt yang sudah 202 tetap diselesaikan/reconcile meskipun UI feature flag
   kemudian dimatikan.

## 13. Checklist Definition of Done frontend LS SPP

- [x] Action LS SPP mempunyai `step_public_id` dan capability canonical.
- [x] Svelte island satu kali mount melalui Vite.
- [ ] Modal Bootstrap/Argon responsif dan tidak bertumpuk.
- [ ] Source PDF dimuat binary tanpa Base64/upload ulang.
- [ ] Geometry top-left point konsisten dengan backend.
- [ ] Satu hingga batas maksimum QR dapat diatur.
- [ ] QR final berasal dari backend dan memakai logo Malang.
- [ ] Footer unsigned otomatis dibuat dan dapat diedit sesuai kontrak.
- [ ] PDF signed tidak mendapatkan footer baru.
- [ ] Exact prepared rendition tampil bersama informasi signer dan passphrase
      pada tahap Konfirmasi.
- [ ] Tidak ada checkbox afirmasi; tombol final mengirim `affirmed=true`.
- [ ] NIK tidak ada di form; passphrase tidak dipersistensikan.
- [ ] Final submit menerima 202 dan progress memakai status attempt.
- [ ] Multi-QR tampil sebagai `completed/planned` dan dieksekusi serial.
- [ ] Partial resume hanya muncul dari capability backend.
- [ ] `unknown` melarang retry biasa.
- [ ] Completion me-refresh halaman melalui custom event.
- [ ] Close modal membersihkan resource PDF/object URL/listener.
- [ ] Modal validasi memakai artifact canonical tanpa upload ulang.
- [ ] Acceptance manual LS SPP lulus untuk BP/BPP -> PPTK -> PA/KPA.
- [x] Tidak ada test suite otomatis yang dibuat atau dijalankan.

## 14. Langkah implementasi paling tepat berikutnya

Langkah pertama bukan menyalin editor lampiran atau memasang handler `.sign`
baru. Urutannya:

1. [SELESAI] Tahap F0: typed contract dan gap register dikunci terhadap source
   backend aktual;
2. [SELESAI DI SOURCE] Tahap F1: action LS SPP mengirim `step_public_id` tanpa
   path file melalui event bridge;
3. [SELESAI DI SOURCE] Tahap F2: fondasi Svelte/Vite island, root global, lazy
   loader, dan shell Bootstrap/Argon;
4. [SELESAI DI SOURCE] Tahap F3: lifecycle event dan adapter refresh DataTable
   tanpa ketergantungan pada global halaman;
5. [SELESAI DI SOURCE] Tahap F4: shell modal lengkap, state visual, stepper,
   accessibility dasar, dan responsive layout Bootstrap/Argon;
6. lanjutkan Tahap F5: typed API client, error normalization, dan
   signing-session state; feature flag tetap `false` sampai rangkaian ini siap;
7. bangun viewer/geometry/editor secara berurutan pada Tahap F6-F10;
8. sambungkan final sign/progress pada Tahap F11-F12;
9. tutup gap backend validasi/public delivery sebelum Tahap F13-F15;
10. lakukan pilot manual sebelum rollout payment lain.

Dengan urutan ini, komponen frontend dibangun langsung di atas boundary
canonical dan tidak perlu dirombak kedua kali untuk membuang path, upload PDF,
atau proses sign sinkron dari implementasi lama.
