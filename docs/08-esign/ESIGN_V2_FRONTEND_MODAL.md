# Rancangan Frontend Modal TTE dan Validasi

Tanggal keputusan awal: **18 September 2026**. Pembaruan terakhir:
**23 September 2026**.

Dokumen ini menetapkan arsitektur frontend Svelte/Vite, perilaku modal, kontrak
UI, keamanan passphrase, PDF viewer, dan integrasi dengan halaman Blade/payment.

Keputusan visible/editor terbaru yang lebih spesifik berada di
`ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md` dan berlaku bila ada perbedaan
dengan asumsi lama dokumen ini. Statusnya adalah desain disetujui tetapi belum
diimplementasikan. Lampiran rancangan UI hanya referensi; keputusan eksplisit
pengguna dalam dokumentasi project adalah source of truth.

Urutan kerja, hasil audit `esign.blade.php`/`signed.js`/`bundle.js`, pemetaan
trigger `.sign`/`.signModal` ke bridge canonical, dan strategi cutover LS SPP
berada di `ESIGN_FRONTEND_IMPLEMENTATION_AND_LEGACY_MIGRATION_PLAN.md`. Agent
frontend wajib membaca dokumen tersebut sebelum mengubah source.

Bentuk visual final, penggabungan prepared preview dengan konfirmasi/passphrase,
penghapusan checkbox afirmasi, progress, result state, notifikasi, responsive
behavior, dan accessibility berada di
`ESIGN_FRONTEND_VISUAL_AND_INTERACTION_DESIGN.md` dan berlaku bila ada asumsi
visual lama yang berbeda.

Halaman publik `/verify/{public_id}` berada di luar Svelte island dokumen ini.
Halaman tersebut server-rendered dengan Blade + Bootstrap 5/custom Argon,
menampilkan metadata minimum. Guest hanya dapat menerima PDF bila public-access
policy lulus dan selalu dalam bentuk public-watermarked; authenticated delivery
tetap membutuhkan policy dokumen. Svelte tetap digunakan untuk modal
TTE/validasi internal. Kontrak rendition lengkap berada di
`PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`.

## 1. Keputusan frontend

1. Gunakan Svelte melalui Vite yang sudah menjadi bundler project.
2. Jangan memakai SvelteKit dan jangan mengubah seluruh aplikasi menjadi SPA.
3. Gunakan Svelte island yang di-mount satu kali pada shell Blade.
4. Pertahankan pengalaman modal project lama untuk sign dan validasi.
5. Gunakan markup, class, komponen, dan lifecycle modal Bootstrap 5 yang telah
   menjadi standar layout. Integrasi memakai Bootstrap JavaScript API, bukan
   plugin modal jQuery.
6. Bootstrap 5 dan custom Argon Dashboard Pro 2 adalah sistem visual utama.
   Svelte mengelola state/interaksi dan tidak membawa design system sendiri.
7. Jangan memakai utility Tailwind pada komponen eSign. Custom CSS hanya untuk
   kebutuhan editor/PDF yang tidak tersedia di Bootstrap/Argon, harus minimal
   dan ter-scope di bawah `.esign-ui`.
8. Jangan membawa jQuery atau global mutable state lama ke modul Svelte.
9. Gunakan TypeScript untuk state, response contract, dan koordinat PDF.
10. Hanya ada mode `SELF_SIGN`; jangan membuat UI `PREPARE_FOR_SIGNER`, pilihan
    target signer, atau handoff Admin Super.
11. Signer yang login menempatkan QR/footer untuk step miliknya sendiri. Modal
    tidak menampilkan input NIK, termasuk untuk Admin Super.
12. Frontend tidak memilih original/watermark dan tidak mengirim nilai
    `pdf_watermark_required`. Semua URL preview/view/download diperoleh dari
    backend setelah authorization serta context resolution.
13. Editor tidak memiliki upload/replace file. PDF berasal dari exact canonical
    artifact paket BP/BPP dan dimuat sebagai authorized binary stream, bukan
    Base64 dalam JSON.
14. Jika backend menyatakan PDF belum mempunyai TTE, QR pertama membuat footer
    di seluruh halaman. Footer dapat diedit untuk text, font whitelist, size,
    bold, italic, underline, dan posisi. PDF signed tidak mendapat footer baru.
15. Satu signer/step dapat menempatkan beberapa QR. Satu confirmation,
    passphrase, dan klik TTE membuat satu attempt dengan N operasi serial;
    progress harus menunjukkan `selesai/total`.
16. Browser hanya mengedit overlay. Review terakhir harus memakai rendition
    dari exact prepared PDF hasil renderer backend dan tetap mengikuti policy
    watermark; perubahan editor menginvalidasi prepared revision lama.
17. Custom CSS editor di-scope di bawah `.esign-editor` atau root eSign yang
    setara dan tidak membuat reset/style global.

Snapshot `package.json` saat rancangan:

- Vite `^8.0.0` sudah tersedia;
- `laravel-vite-plugin` `^3.1` sudah tersedia;
- main layout memuat `public/assets/css/argon-dashboard.css` dan
  `public/assets/js/core/bootstrap.min.js`;
- aset Argon saat snapshot menggunakan cache-buster `2.0.7`, sedangkan header
  asset CSS/JS menyatakan Bootstrap `5.2.0-beta1`;
- font utama layout adalah Open Sans; icon memakai Nucleo dan Font Awesome;
- dark mode aplikasi menggunakan `body.dark-version` serta
  `resources/views/inc/dark-mode-overrides.blade.php`;
- Tailwind CSS `^4.0.0` tersedia sebagai dependency, tetapi bukan main CSS
  dashboard dan bukan basis styling eSign;
- Svelte belum menjadi dependency.

Dokumentasi ini menyetujui pilihan arsitektur Svelte, tetapi pekerjaan docs ini
sendiri tidak memasang dependency. Perubahan dependency dilakukan pada turn
implementasi yang memang mengotorisasi perubahan kode/dependency.

## 2. Perilaku project lama yang dipertahankan

### Modal sign lama

- tombol pada tabel membuka modal besar;
- PDF dimuat dan divalidasi sebelum editor siap;
- pengguna melihat dokumen dan menempatkan visualisasi;
- tombol Sign membuka konfirmasi reason/passphrase;
- button dikunci selama request;
- spinner dan notification sukses/gagal tampil;
- passphrase dibersihkan setelah proses;
- tabel di halaman di-refresh setelah sukses.

### Modal validasi lama

- modal dibuka tanpa meninggalkan halaman;
- PDF viewer dan request validasi berjalan paralel;
- status dan jumlah signature ditampilkan;
- tabel menampilkan signer, waktu, dan keterangan.

## 3. Masalah frontend lama yang tidak boleh disalin

- global mutable variable `_doc`, `_urls`, `_status`, `_ids`, `_location`;
- hardcoded `/esign/sign` dan manipulasi CSRF jQuery di bundle;
- modal bertumpuk (`signModal` lalu `ConfirmSign`);
- client mengirim `OPDF`, `domPDF`, `_location`, NIK bebas, dan banyak array
  koordinat legacy;
- browser mengambil PDF lalu upload blob yang sama kembali untuk validasi;
- response berupa array positional seperti `[message, filename, success]`;
- HTML signer dibangun dengan string concatenation;
- coupling langsung ke global `mainTable`/`tteDocumentTable`;
- object URL/cache blob berpotensi tidak di-revoke;
- bundle PDF monolitik di `public/assets/js/pdf/bundle.js`;
- fungsi global `CreatePdf`, `resetPdfFromUrl`, `SignProcess`, dan sejenisnya;
- validasi akses hanya dari state/tombol frontend.

Svelte meng-escape interpolation secara default; jangan menggantinya dengan
raw HTML untuk payload vendor.

## 4. Struktur file target

```text
resources/js/esign/
|-- main.ts
|-- EsignApp.svelte
|-- components/
|   |-- EsignSigningModal.svelte
|   |-- EsignValidationModal.svelte
|   |-- PdfViewer.svelte
|   |-- PdfToolbar.svelte
|   |-- SignatureOverlay.svelte
|   |-- SignConfirmation.svelte
|   |-- SigningProgress.svelte
|   |-- VerificationSummary.svelte
|   `-- VerificationSignerTable.svelte
|-- api/
|   `-- esign-api.ts
|-- geometry/
|   `-- pdf-coordinates.ts
|-- styles/
|   `-- esign.css
|-- state/
|   `-- esign-state.svelte.ts
`-- types.ts
```

PDF renderer/editor harus modular dan lazy-loaded. Jangan menyalin bundle lama
ke `resources/js`.

## 5. Kontrak visual dan kepemilikan CSS

Urutan sumber tampilan eSign adalah:

1. Bootstrap 5 untuk struktur, responsive grid, modal, form, button, table,
   alert, badge, spinner, dan utility umum;
2. Argon Dashboard Pro 2 untuk skin, warna, radius, shadow, typography, dan
   perilaku visual dashboard;
3. override dark mode project yang sudah ada;
4. `resources/js/esign/styles/esign.css` hanya untuk kebutuhan khusus PDF,
   overlay tanda tangan, sticky action, atau ukuran area kerja.

Gunakan class existing seperti `modal`, `modal-dialog`, `modal-xl`,
`modal-dialog-centered`, `modal-dialog-scrollable`, `modal-content`,
`modal-header`, `modal-body`, `modal-footer`, `btn`, `btn-primary`,
`btn-outline-secondary`, `form-control`, `form-label`, `row`, `col-*`, `card`,
`badge`, `alert`, dan `spinner-border`. Periksa komponen Blade sibling sebelum
menambah pola baru.

Shell modal canonical:

```html
<div class="modal fade esign-modal esign-ui" tabindex="-1"
    aria-labelledby="esignSigningModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable
        modal-xl modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="esignSigningModalTitle">
                    Tanda Tangan Elektronik
                </h5>
                <button type="button" class="btn-close"
                    aria-label="Tutup"></button>
            </div>
            <div class="modal-body"><!-- state Svelte --></div>
            <div class="modal-footer"><!-- action Svelte --></div>
        </div>
    </div>
</div>
```

Svelte boleh memecah isi menjadi komponen, tetapi struktur akhir DOM harus
tetap mengikuti pola Bootstrap tersebut. Tombol tutup hanya memanggil
`Modal.hide()` saat state aman; jangan memasang `data-bs-dismiss="modal"` pada
tombol yang tetap tampil ketika proses sign sedang berlangsung.

Aturan CSS:

- root komponen memakai class `.esign-ui` dan modal memakai `.esign-modal`;
- semua selector custom dimulai dari `.esign-ui` atau `.esign-modal` agar tidak
  mengubah Blade, DataTables, Select2, maupun modal lain;
- gunakan CSS custom properties Bootstrap/Argon seperti `--bs-primary`,
  `--bs-border-color`, dan `--bs-body-color` ketika tersedia; jangan menyalin
  hex color Argon tanpa alasan;
- inherit font dari layout; jangan memuat font baru untuk eSign;
- breakpoint mengikuti Bootstrap/Argon, bukan breakpoint Tailwind;
- dark mode mengikuti `body.dark-version .esign-ui ...`; jangan membuat state,
  toggle, atau palette dark mode khusus eSign;
- style dalam komponen Svelte tetap scoped. Pemakaian `:global(...)` hanya
  diizinkan untuk selector Bootstrap/Argon yang berada di bawah root eSign;
- jangan mengedit langsung file vendor/generated
  `public/assets/css/argon-dashboard.css` untuk kebutuhan eSign;
- hindari inline CSS; pengecualian hanya nilai dinamis seperti transform/ukuran
  overlay yang dihitung dari koordinat PDF dan tidak dapat dinyatakan sebagai
  class statis;
- jangan memasukkan kembali Bootstrap atau Argon ke bundle Vite karena layout
  sudah memuat keduanya;
- custom CSS eSign di-import dari entry eSign agar Vite memuatnya bersama island
  dan sesudah main CSS Argon.

`resources/css/app.css` berisi entry Tailwind, tetapi layout
`resources/views/layouts/app.blade.php` saat snapshot hanya memanggil
`@vite('resources/js/app.js')` dan `app.js` tidak mengimpor `app.css`. Agent
tidak boleh berasumsi utility Tailwind aktif atau menjadikan Tailwind fondasi UI
eSign tanpa keputusan arsitektur baru.

## 6. Mounting dan integrasi Blade

Mount satu root:

```html
<div id="esign-app"></div>
```

`resources/js/app.js` dapat melakukan dynamic import hanya bila terdapat tombol
TTE/validasi atau root yang diaktifkan pada halaman. Alternatifnya gunakan entry
Vite terpisah yang dimuat hanya pada halaman terkait. Pilih pola yang paling
sesuai layout aktual dan hindari mengirim PDF library ke semua halaman.

Svelte boleh merender shell markup Bootstrap, kemudian membuka/menutupnya lewat
`bootstrap.Modal.getOrCreateInstance(...)`. Pembagian ownership wajib jelas:

- Bootstrap mengelola backdrop, focus trap, keyboard event, scroll lock, dan
  lifecycle `show.bs.modal`/`hidden.bs.modal`;
- Svelte mengelola tahap proses, form state, PDF state, request API, dan isi
  modal;
- saat `hidden.bs.modal`, Svelte membersihkan passphrase, object URL, PDF worker,
  dan seluruh state dokumen;
- instance Bootstrap di-dispose ketika root Svelte dihancurkan;
- jangan menjalankan plugin modal jQuery dan Bootstrap JS API pada elemen yang
  sama;
- selama request sign atau outcome belum pasti, cegah event hide dan gunakan
  backdrop statis agar modal tidak tertutup tanpa sengaja.

Tombol Blade signing hanya membawa canonical step public ID dan capability
non-sensitif:

```html
<button
    type="button"
    data-esign-action="sign"
    data-esign-step="<step-public-uuid>"
    data-esign-can-sign="true"
    data-esign-can-verify="false"
>
    Tanda Tangani
</button>
```

Jangan taruh NIK lengkap, path storage, credential, passphrase, raw policy/
authorization context, atau base64 PDF dalam `data-*`. Boolean capability yang
sudah disanitasi hanya bersifat petunjuk UI; endpoint tetap mengulang
authorization authoritative.

## 7. Event boundary dengan halaman payment

Halaman/adapter membuka Svelte melalui browser event:

```javascript
window.dispatchEvent(new CustomEvent('sitangkas:esign:open', {
    detail: {
        step_public_id: stepPublicId,
        can_sign: true,
        can_verify: false,
    },
}));
```

Event action yang didukung:

- `sitangkas:esign:open` untuk signing berdasarkan `step_public_id`;
- `sitangkas:esign:validation-open` untuk validasi berdasarkan
  `artifact_public_id`. Event validasi belum mempunyai producer aktif sampai
  endpoint validasi canonical tersedia.

Setelah selesai, Svelte mengirim event, bukan memanggil DataTable global:

```javascript
window.dispatchEvent(new CustomEvent('sitangkas:esign:completed', {
    detail: {
        step_public_id: stepPublicId,
        attempt_id: attemptId,
        result_artifact_id: resultArtifactId,
        result: 'succeeded',
    },
}));
```

Adapter halaman memutuskan apakah perlu reload DataTable, update badge, refresh
detail, atau menampilkan notifikasi. Seluruh identity pada completion adalah
UUID publik; raw integer `document.id` tidak diekspos ke frontend. Event detail
tidak boleh memuat secret.

## 8. Modal signing

Gunakan satu modal dengan tahap internal:

```text
loading -> editing -> confirming -> signing -> validating -> succeeded
                                            |              `-> failed
                                            `-> unknown
```

Jangan membuka modal passphrase di atas modal editor. Pada tahap confirming,
konten modal yang sama berubah menjadi panel konfirmasi dan menyediakan tombol
kembali ke editor. Backend membuat prepared rendition ketika user meninggalkan
editor; prepared preview, informasi dokumen/signer, dan passphrase ditampilkan
bersama pada tahap confirming. Tidak ada layar review prepared terpisah.

### Layout desktop

```text
+---------------------------------------------------------------+
| Tanda Tangan Elektronik                              [Tutup]  |
+--------------------------------------+------------------------+
|                                      | Informasi dokumen      |
|                                      | Nama / jenis / halaman |
|            PDF viewer                | Signer / jabatan       |
|                                      | Mode / reason          |
|        Signature overlay             | Panduan                |
|                                      |                        |
+--------------------------------------+------------------------+
| [Batal]                              [Lanjutkan Tanda Tangan] |
+---------------------------------------------------------------+
```

Tombol Batal atau Tutup pada tahap placement/preview hanya menutup modal,
membersihkan state frontend, melepaskan PDF resource, dan meminta cleanup
temporary preview bila ada. Jangan membuat audit event, history, signing
attempt, atau record bisnis. Attempt baru ada setelah pengguna menekan aksi
final sign.

### Layout mobile

- viewer di bagian atas;
- detail dan kontrol di bawah viewer;
- footer action sticky;
- target sentuh cukup besar;
- tidak ada horizontal overflow pada dialog;
- toolbar dapat collapse;
- placement tetap presisi walaupun canvas diskalakan.

Gunakan Bootstrap grid dan responsive modal. Rekomendasi awal adalah
`modal-xl modal-dialog-centered modal-dialog-scrollable` pada desktop dan
`modal-fullscreen-lg-down` atau breakpoint yang dibuktikan paling nyaman pada
perangkat target. Ikuti `body.dark-version`; jangan memperkenalkan dark mode
terpisah yang tidak sinkron dengan aplikasi.

### Tahap konfirmasi

Tampilkan:

- nama dan jabatan signer;
- NIK masked bila perlu;
- nama/nomor dokumen;
- mode visible/invisible;
- jumlah placement;
- reason;
- input passphrase;
- text informatif bahwa tombol final akan menandatangani seluruh QR yang
  ditampilkan.

Jangan menampilkan checkbox `Saya telah memeriksa dokumen` atau checkbox
afirmasi lain. Klik tombol `Tandatangani Sekarang` adalah afirmasi eksplisit;
handler final mengirim `affirmed=true` bersama revision/hash/idempotency key dan
passphrase.

Jangan meminta NIK dari user biasa maupun Admin Super. Backend menyelesaikan
NIK dari real authenticated user/certificate owner dan memvalidasi posisi aktif
serta workflow step. Hak administratif Admin Super tidak boleh menghasilkan
tombol sign bila Admin Super bukan signer sah pada step aktif.

### Tahap proses

- cegah submit ganda;
- disable input dan tombol aksi;
- tampilkan state aktual: signing atau validating;
- jangan tampilkan countdown palsu bila backend belum selesai;
- penutupan modal selama external call harus dikontrol;
- browser navigation/unload dapat diberi peringatan, tetapi backend tetap harus
  aman bila browser hilang;
- `unknown` harus diberi pesan untuk tidak mencoba ulang sembarangan.

### Tahap hasil

Sukses menampilkan ringkasan signer, waktu, dan link unduh/preview terotorisasi.
Link tersebut wajib melalui resolver delivery yang sama; UI tidak boleh
menyediakan tombol original khusus atau menebak mode dari jabatan.
Failure menampilkan safe application message. Passphrase salah dapat dicoba
ulang setelah input dibersihkan; timeout/unknown tidak boleh menawarkan retry
otomatis.

## 9. Modal validasi

Gunakan modal terpisah `EsignValidationModal.svelte`:

```text
+---------------------------------------------------------------+
| Validasi Tanda Tangan Elektronik                     [Tutup]  |
+--------------------------------------+------------------------+
|                                      | Status VALID/INVALID   |
|            PDF viewer                | Jumlah signature       |
|                                      | Signer list            |
|                                      | Waktu / reason / cert  |
+--------------------------------------+------------------------+
|                                                   [Tutup]     |
+---------------------------------------------------------------+
```

State minimum:

- loading PDF;
- validating;
- valid;
- invalid;
- no signatures;
- provider unavailable;
- malformed result.

PDF load dan validation request boleh berjalan paralel. Backend menerima
document ID/artifact reference, bukan blob yang baru saja diunduh browser.

Cache validasi menggunakan immutable artifact version/SHA-256, bukan hanya URL.
Invalidate cache ketika versi artifact berubah. Revoke object URL dan release
PDF worker/document saat modal ditutup.

PDF viewer menampilkan rendition yang diputuskan server. Untuk posisi nyata
berflag `true` rendition selalu watermarked; flag `false` boleh original; Admin
Super acting efektif `false`; guest public selalu public-watermarked. Status
verifikasi berasal dari original artifact melalui cache/job asynchronous dan
tidak boleh disimpulkan dari watermark derivative yang diterima browser.

## 10. PDF coordinate model

Koordinat browser tidak dikirim sebagai pixel final vendor.

Frontend menyimpan placement sebagai:

```typescript
type SignaturePlacement = {
    page: number;
    x: number;
    y: number;
    width: number;
    height: number;
    pageRotation: 0 | 90 | 180 | 270;
};
```

Nilai `x`, `y`, `width`, dan `height` direkomendasikan sebagai koordinat PDF
canonical atau rasio `0..1`. Render scale, CSS pixel, device pixel ratio, zoom,
scroll, dan rotation tidak boleh mengubah nilai canonical.

Backend wajib:

- membaca metadata halaman canonical;
- memvalidasi page dan bounds;
- menolak NaN, negatif, overflow, ukuran nol/terlalu kecil;
- melakukan transformasi final ke koordinat BSrE;
- tidak percaya `max_height`/`max_width` dari browser.

Orientasi `originY` v2 wajib dibuktikan dengan matrix sandbox untuk portrait,
landscape, rotated pages, page size berbeda, zoom, dan mobile.

## 11. Pemisahan editing dan signing

TTE tidak boleh diam-diam mengubah konten PDF.

- Jika pengguna menambah teks/elemen dokumen, simpan sebagai artifact/version
  baru sebelum signing session dibuat.
- Signing session mengunci hash/version yang sedang dilihat.
- Bila artifact berubah setelah modal dibuka, backend menolak sign dengan
  conflict dan meminta reload.
- Signature overlay adalah metadata visualisasi vendor, bukan alasan mengirim
  ulang `domPDF` hasil edit browser.

## 12. Passphrase frontend

Passphrase:

- hanya berada pada state lokal komponen confirmation;
- tidak masuk global store/module state;
- tidak masuk URL, event detail, localStorage, sessionStorage, IndexedDB,
  analytics, console, source map annotation, atau crash reporting;
- tidak di-cache oleh API client;
- dikirim hanya pada request sign melalui HTTPS + CSRF;
- dibersihkan segera setelah backend menerima request final/`202`, serta pada
  cancel, close, dan failure sebelum request diterima;
- memakai password input dengan autocomplete yang sesuai kebijakan keamanan;
- tidak dimasukkan kembali melalui `old()` atau validation response.

Svelte state/debug inspection tidak boleh menampilkan passphrase pada build
production.

## 13. API internal frontend

Baseline route di dalam middleware `web`/session auth, bukan public vendor API:

| Method | Route konseptual | Fungsi |
|---|---|---|
| `POST` | `/esign/signing-sessions` | authorize dan siapkan context/artifact |
| `GET` | `/esign/signing-sessions/{uuid}` | metadata/capability aman |
| `GET` | `/esign/signing-sessions/{uuid}/preview` | preview private terotorisasi |
| `POST` | `/esign/signing-sessions/{uuid}/renditions` | validasi placement/footer dan buat exact prepared preview revision |
| `POST` | `/esign/signing-sessions/{uuid}/sign` | validasi final, buat attempt, simpan secret terenkripsi ber-TTL, enqueue sign, respons `202` |
| `GET` | `/esign/attempts/{uuid}` | status/reconciliation polling |
| `POST` | `/esign/attempts/{uuid}/resume` | passphrase baru untuk melanjutkan attempt `partially_signed` dari checkpoint |
| `DELETE` | `/esign/signing-sessions/{uuid}` | bersihkan session/temporary sebelum attempt tanpa audit bisnis |
| `POST` | `/esign/documents/{document}/verify` | validasi artifact server-side |
| `GET` | route delivery artifact canonical | stream inline/attachment sesuai Policy dan resolver watermark yang sama |

Nama final mengikuti convention route project, tetapi responsibility tidak
boleh digabung menjadi satu endpoint lama `/esign/sign` berbasis FormData.
Signing session sebelum final sign adalah context ephemeral, bukan
`esign_attempts` persisten.

Route rendition dan resume pada tabel adalah kontrak target, bukan route yang
sudah tersedia pada snapshot. Request placement harus berisi ordered geometry
dan style yang tervalidasi, tidak boleh berisi PDF/Base64, NIK, path storage,
atau URL provider.

Setelah endpoint sign mengembalikan `202 Accepted`, frontend harus langsung
menghapus passphrase dan menyimpan hanya attempt UUID/status aman. Modal boleh
ditutup; background job tidak dibatalkan. Ketika modal/page dibuka kembali,
frontend membaca `GET /esign/attempts/{uuid}`. Polling adalah baseline; Echo atau
Reverb hanya optimasi notifikasi dan database tetap source of truth.

Response selalu object bernama, bukan positional array.

## 14. Accessibility modal

- gunakan markup modal Bootstrap 5 dan JavaScript API yang sudah tersedia;
- modal root memiliki `tabindex="-1"`, `aria-labelledby`, dan bila perlu
  `aria-describedby`; Bootstrap memberikan dialog semantics dan focus trap;
- title terhubung melalui label/heading yang jelas;
- focus masuk ke modal dan kembali ke trigger saat tutup;
- focus tidak keluar dari modal selama terbuka;
- Escape/backdrop mengikuti state; jangan menutup saat result belum pasti;
- semua tombol dapat dipakai keyboard;
- status async memakai `aria-live` yang tidak membocorkan secret;
- loading mempunyai teks, bukan spinner saja;
- warna bukan satu-satunya indikator valid/invalid;
- hormati reduced motion;
- tabel signer dapat dibaca screen reader;
- mobile dan zoom browser tetap usable.

## 15. Error UX

UI memetakan application error code, bukan raw vendor response:

- passphrase salah: bersihkan input dan izinkan retry terkendali;
- certificate expired/revoked: arahkan pengguna ke prosedur administrasi;
- unauthorized/conflict: tutup editor atau minta reload;
- provider unavailable: informasikan layanan sementara tidak tersedia;
- unknown outcome: larang retry cepat dan tampilkan attempt reference;
- partially signed: tampilkan jumlah QR yang sudah selesai, minta passphrase
  baru, dan resume attempt yang sama tanpa mengulang QR completed;
- validation malformed: tampilkan pesan aman dan catat correlation ID server.

Raw stack trace, raw JSON vendor, credential, NIK lengkap, atau base64 tidak
boleh tampil di modal.

Jika koneksi user putus setelah commit tetapi sebelum response `202` diterima,
frontend boleh mengirim ulang request dengan idempotency key yang sama. Backend
wajib mengembalikan attempt yang sama, bukan membuat vendor call kedua.

## 16. Performa frontend

- lazy-load entry TTE dan PDF renderer;
- jalankan PDF rendering pada worker;
- gunakan authorized URL/stream dan HTTP range bila storage/server mendukung;
- jangan encode PDF base64 di browser;
- jangan download-upload kembali file untuk verify;
- render halaman PDF secara virtual/lazy bila dokumen panjang;
- render halaman aktif dan halaman sekitar pada resolusi viewer; gunakan
  thumbnail resolusi rendah dan DOM overlay saat drag QR/footer;
- batasi cache berdasarkan artifact version dan release resource ketika tutup;
- response PDF harus `no-store` pada browser/proxy; cache 12 jam adalah cache
  private server-side. Revoke setiap object URL saat modal tutup, context/posisi
  berganti, artifact berubah, atau komponen di-unmount;
- frontend tidak boleh fallback ke URL legacy/public/original ketika render
  watermark gagal;
- jangan memasukkan seluruh editor ke bundle utama aplikasi;
- jangan membundel ulang Bootstrap, Popper, Argon, Open Sans, atau icon font
  yang telah disediakan layout;
- ukur bundle size, time-to-modal-ready, memory, dan render page latency.

## 17. Kompatibilitas transisi

Selama rollout:

- tombol/class legacy dapat dihubungkan ke adapter event sementara;
- jangan menjalankan handler jQuery legacy dan Svelte untuk klik yang sama;
- pilot dimulai dari satu flow payment;
- route lama tidak boleh menunjuk dua implementasi sign sekaligus;
- setelah semua pemanggil berpindah, hapus include component legacy, bundle lama,
  dan fungsi global;
- jangan menghapus file legacy sebelum pencarian seluruh referensi dan rollback
  plan selesai.
