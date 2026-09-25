# Rancangan Visual dan Interaksi Frontend TTE

Tanggal keputusan: **24 September 2026**.

Status: **rancangan visual disetujui; fondasi F2-F4 selesai di source**.

Shell modal responsive, stepper empat tahap, state body/footer, close guard,
focus restoration, dark mode, dan reduced-motion support sudah tersedia.
Isi authoritative masih bertahap: API/session dimulai pada F5, PDF viewer pada
F6, editor QR/footer pada F8-F9, konfirmasi/passphrase pada F10, dan
sign/progress pada F11-F12. Feature flag operasional masih `false`.

Dokumen ini adalah source of truth untuk bentuk tampilan, hierarchy informasi,
interaksi modal, prepared confirmation, progress, notifikasi, hasil TTE, modal
validasi, responsive behavior, dan accessibility frontend TTE SITANGKAS.

Kode frontend project lama hanya menjadi referensi perilaku bisnis. Bentuk
tampilan `esign.blade.php`, `bundle.js`, `#signModal`, dan `#ConfirmSign` tidak
menjadi template visual baru.

Jika dokumen ini berbeda dengan mockup/lampiran lama, keputusan eksplisit pada
dokumen ini berlaku.

## 1. Prinsip desain yang dikunci

1. Frontend TTE memakai Svelte sebagai island melalui Vite.
2. Bootstrap 5 dan custom Argon Dashboard Pro 2 adalah sistem visual utama.
3. Tidak memakai Tailwind, design system kedua, atau CSS reset global.
4. Signing menggunakan satu modal besar dengan beberapa tahap internal.
5. Tidak ada nested modal untuk passphrase.
6. Prepared rendition tetap wajib secara teknis, tetapi tidak mempunyai layar
   review terpisah.
7. Prepared preview, informasi dokumen, signer, dan input passphrase digabung
   pada tahap **Konfirmasi**.
8. Tidak ada checkbox `Saya telah memeriksa dokumen` atau checkbox afirmasi
   sejenis.
9. Klik tombol `Tandatangani Sekarang` adalah afirmasi eksplisit user. Frontend
   mengirim `affirmed=true` hanya dari handler tombol final tersebut.
10. Tidak ada input NIK. Backend menentukan signer/NIK berdasarkan user,
    posisi nyata, assignment, dan workflow step.
11. Notifikasi harus merepresentasikan state backend sebenarnya. Jangan memakai
    countdown sukses palsu.
12. Status `unknown` harus berbeda dari `failed` dan tidak boleh menyediakan
    retry biasa.

## 2. Struktur perjalanan pengguna

Stepper utama dipadatkan menjadi empat tahap:

```text
1. Atur Posisi  ->  2. Konfirmasi  ->  3. Proses  ->  4. Selesai
```

Makna tahap:

- **Atur Posisi**: memuat PDF, menempatkan satu/beberapa QR, dan mengatur footer;
- **Konfirmasi**: backend sudah membuat prepared rendition; user melihat preview
  authoritative, informasi dokumen/signer, lalu memasukkan passphrase;
- **Proses**: attempt sudah diterima dengan HTTP 202 dan worker berjalan;
- **Selesai**: menampilkan sukses atau hasil terminal lain yang memerlukan
  perhatian.

Prepared rendition berada di antara tahap 1 dan 2 sebagai state loading
internal `preparing_rendition`, bukan sebagai langkah yang terlihat di stepper.

## 3. Shell modal utama

### 3.1 Ukuran dan perilaku

- desktop: lebar sekitar `95vw`, tinggi maksimum sekitar `92vh`;
- gunakan struktur Bootstrap `modal-xl modal-dialog-scrollable`;
- tablet/mobile: `modal-fullscreen-lg-down` atau breakpoint yang terbukti lebih
  sesuai saat acceptance;
- header dan action footer tetap terlihat;
- hanya workspace PDF yang melakukan scroll utama;
- satu modal Bootstrap aktif pada satu waktu;
- backdrop dan Escape mengikuti state: boleh menutup saat aman, dicegah saat
  request prepare/submit sedang dikirim;
- setelah HTTP 202, modal boleh ditutup karena worker tetap berjalan.

### 3.2 Header modal

Header menampilkan:

- judul `Tanda Tangan Elektronik`;
- jenis dokumen dan nomor dokumen bila ada;
- posisi signer aktif;
- badge state ringkas;
- sisa waktu session bila mendekati kedaluwarsa;
- tombol tutup.

Jangan menampilkan full NIK, storage path, artifact database ID, atau response
provider mentah.

### 3.3 Stepper

Stepper berada tepat di bawah header. Gunakan warna/status berikut melalui
class/theme Argon yang tersedia:

- aktif: primary/info;
- selesai: success;
- belum dilakukan: secondary;
- butuh tindakan: warning;
- gagal terminal: danger.

Stepper harus tetap dapat dipahami tanpa warna, misalnya melalui nomor, label,
icon, dan `aria-current="step"`.

## 4. Tahap Atur Posisi

### 4.1 Layout desktop

Gunakan layout tiga panel:

```text
+----------------------------------------------------------------------------+
| Tanda Tangan Elektronik                                      [status] [x] |
| SPP-LS - No. 900/123/SPP/2026 - Bendahara Pengeluaran                    |
| 1 Atur Posisi ---- 2 Konfirmasi ---- 3 Proses ---- 4 Selesai             |
+--------------+-------------------------------------+-----------------------+
| HALAMAN      | WORKSPACE PDF                       | PENGATURAN            |
|              |                                     |                       |
| [Halaman 1]  |                                     | QR #1                 |
| [Halaman 2]  |            PDF PAGE                 | Halaman: 1            |
| [Halaman 3]  |                                     | Ukuran: 90 pt         |
|              |                  +----------+       | [Pusatkan] [Hapus]    |
|              |                  | QR LOGO  |       |                       |
|              |                  +----------+       | Footer                |
|              |                                     | Teks: [...]           |
|              | ----------------------------------  | Font: Helvetica       |
|              | Dokumen ditandatangani elektronik   | Size: 8 [B] [I] [U]   |
+--------------+-------------------------------------+-----------------------+
| 2 QR - Footer aktif pada 3 halaman                                      |
| [Batal]                 [Tambah QR] [Reset Posisi] [Lanjutkan ->]         |
+----------------------------------------------------------------------------+
```

Ukuran awal yang direkomendasikan:

- thumbnail rail: sekitar 180-220 px;
- inspector: sekitar 300-360 px;
- workspace menggunakan sisa lebar;
- angka tersebut hanya baseline visual dan harus responsif.

### 4.2 Thumbnail rail

Setiap thumbnail menampilkan:

- nomor halaman;
- preview halaman;
- badge jumlah QR;
- indikator footer;
- highlight halaman aktif;
- status error placement pada halaman jika ada.

Thumbnail harus dirender bertahap dan tidak menghambat halaman aktif.

### 4.3 Workspace PDF

Toolbar minimum:

- halaman sebelumnya/berikutnya;
- nomor halaman;
- zoom out/in;
- fit width;
- fit page;
- buka/tutup thumbnail pada viewport kecil.

Workspace menampilkan:

- canvas PDF;
- overlay QR;
- overlay footer;
- safe area bila placement aktif;
- garis bantu sederhana ketika drag;
- error boundary/collision dekat objek terkait.

Overlay aktif menggunakan outline dan handle yang kontras. Perubahan overlay
tidak boleh memicu render ulang canvas PDF.

### 4.4 Interaksi QR

Tombol `Tambah QR`:

1. menambahkan QR pada halaman aktif;
2. memilih posisi default aman di area yang sedang terlihat;
3. memberi client UUID;
4. menomori operasi secara deterministik;
5. memindahkan fokus ke QR baru.

QR dapat:

- dipilih;
- digeser;
- di-resize proporsional;
- dipindahkan dengan keyboard;
- dipusatkan;
- dihapus sebelum final submit.

Inspector QR menampilkan:

- label `QR 1`, `QR 2`, dan seterusnya;
- halaman;
- ukuran;
- tombol pusatkan;
- tombol hapus;
- pesan collision/out-of-bound.

Koordinat point teknis tidak ditonjolkan kepada user umum. Bila diperlukan
untuk support, letakkan di bagian `Detail teknis` yang collapsed.

### 4.5 Interaksi footer

Jika backend menyatakan source unsigned dan footer allowed+required, QR pertama
otomatis membuat footer default pada seluruh halaman.

Inspector footer menyediakan:

- text;
- font dari whitelist backend;
- font size dalam batas backend;
- bold;
- italic;
- underline;
- reset style default;
- reset posisi halaman aktif;
- terapkan posisi halaman aktif ke seluruh halaman.

Style berlaku konsisten untuk seluruh footer, tetapi posisi dapat berbeda per
halaman. Footer required tidak dapat dihapus. Pada PDF yang sudah mempunyai TTE,
inspector footer tidak ditampilkan dan frontend mengirim `footer: null`.

### 4.6 Action footer tahap editor

- `Batal`: tutup dan cleanup temporary session tanpa audit bisnis;
- `Tambah QR`: secondary action;
- `Reset Posisi`: outline secondary dan meminta konfirmasi ringan bila perubahan
  signifikan;
- `Lanjutkan`: primary action.

`Lanjutkan` hanya aktif bila placement lokal minimum valid. Saat ditekan:

- tampilkan state `Menyiapkan dokumen final...`;
- kunci perubahan editor;
- POST plan ke backend;
- bila gagal, kembali ke editor tanpa menghilangkan placement;
- bila berhasil, langsung masuk tahap Konfirmasi.

## 5. Tahap Konfirmasi terpadu

Tidak ada tahap `Tinjau prepared rendition` yang berdiri sendiri. Tahap
Konfirmasi sekaligus menjadi tempat melihat exact prepared preview dan mengisi
passphrase.

### 5.1 Layout desktop

```text
+----------------------------------------------------------------------------+
| Konfirmasi Tanda Tangan Elektronik                                   [x] |
| 1 Atur Posisi ---- 2 Konfirmasi ---- 3 Proses ---- 4 Selesai             |
+-------------------------------------------+--------------------------------+
| PREVIEW FINAL DARI BACKEND                | INFORMASI DOKUMEN              |
|                                           |                                |
|                                           | SPP-LS                         |
|               PDF                         | No. 900/123/SPP/2026           |
|                                           | Unit Kerja                     |
|             [QR 1]                        | 3 halaman - 2 posisi QR        |
|                                           | Footer pada 3 halaman           |
|                         [QR 2]            |                                |
|                                           | PENANDATANGAN                   |
|                                           | Nama signer                    |
|                                           | Bendahara Pengeluaran           |
|                                           | NIK ************0004            |
|                                           |                                |
|                                           | Passphrase                     |
|                                           | [.................] [lihat]    |
|                                           |                                |
|                                           | Satu klik memproses 2 QR       |
+-------------------------------------------+--------------------------------+
| [<- Kembali Edit]                            [Tandatangani Sekarang]       |
+----------------------------------------------------------------------------+
```

### 5.2 Prepared preview

- PDF berasal dari `prepared_rendition.preview_url`;
- QR authoritative ditampilkan dari `qr_image_url` sebagai overlay pada
  koordinat final;
- preview client/editor sebelum prepare tidak boleh digunakan di tahap ini;
- passphrase dan tombol final tetap disabled sampai prepared PDF berhasil
  dimuat;
- bila revision kedaluwarsa/stale, kembali ke editor dan lakukan prepare baru;
- `Kembali Edit` menginvalidasi prepared revision di state client; perubahan
  berikutnya wajib menghasilkan revision baru.

Prepared PDF adalah exact input sebelum operasi provider, bukan PDF yang sudah
ditandatangani. QR akan menjadi image signature melalui operasi BSrE.

### 5.3 Informasi dokumen

Tampilkan informasi yang membantu keputusan user:

- jenis dan nomor dokumen;
- uraian singkat bila tersedia;
- unit kerja/instansi;
- jumlah halaman;
- jumlah signature existing;
- jumlah QR baru;
- halaman setiap QR;
- status footer dan jumlah halaman footer;
- nama signer;
- jabatan signer;
- masked NIK.

Jangan menampilkan full NIK, internal database ID, secret, atau file path.

### 5.4 Passphrase dan final action

Input passphrase:

- `type="password"`;
- `autocomplete="off"`;
- `autocapitalize="none"`;
- `spellcheck="false"`;
- mempunyai toggle visibility;
- visibility otomatis kembali tersembunyi setelah timeout singkat;
- nilainya hanya berada di local component variable;
- dibersihkan segera setelah response 202, error terminal, close, atau unmount.

Tidak ada checkbox afirmasi. Tombol `Tandatangani Sekarang` hanya aktif bila:

- prepared rendition berhasil dimuat dan masih valid;
- jumlah QR valid;
- passphrase tidak kosong;
- tidak ada request lain yang sedang berjalan.

Klik tombol tersebut adalah tindakan afirmasi. Handler tombol mengirim:

```json
{
  "affirmed": true,
  "idempotency_key": "uuid",
  "passphrase": "secret",
  "prepared_revision": "uuid",
  "preview_sha256": "sha256"
}
```

Di dekat tombol tampilkan teks informatif, bukan checkbox:

> Dengan menekan tombol ini, dokumen akan ditandatangani secara elektronik pada
> seluruh posisi QR yang ditampilkan.

Jika terdapat N QR, tampilkan juga:

> Sistem akan memproses N tanda tangan secara berurutan dengan satu passphrase.

### 5.5 Keyboard dan submit

- Enter pada input passphrase tidak langsung submit kecuali implementasi dapat
  menjamin tidak terjadi submit tidak sengaja;
- rekomendasi awal: final submit hanya dari klik/tap tombol utama;
- disable tombol segera ketika request dimulai;
- satu idempotency key dipakai untuk satu intent final;
- jangan membuat key baru karena user melakukan double-click atau retry network
  dari request yang sama.

## 6. Tahap Proses

Tahap ini dimulai setelah backend mengembalikan HTTP 202.

```text
+--------------------------------------------------------------------------+
| Tanda Tangan Sedang Diproses                                             |
| 1 Atur Posisi -- 2 Konfirmasi -- 3 Proses -- 4 Selesai                  |
+--------------------------------------------------------------------------+
|                         1 dari 2 selesai                                  |
|                   [===========---------] 50%                              |
|                                                                          |
| [selesai] QR 1 - Halaman 1                                               |
| [proses ] QR 2 - Halaman 3                                               |
|                                                                          |
| Dokumen diproses di server. Modal boleh ditutup dan proses tetap berjalan.|
+--------------------------------------------------------------------------+
|                                                   [Tutup dan Lanjutkan]   |
+--------------------------------------------------------------------------+
```

Tampilkan:

- `completed/planned`;
- progress bar dengan nilai nyata;
- current operation;
- status tiap QR;
- status signing atau validating;
- pesan bahwa proses tetap berjalan di server;
- tombol tutup setelah attempt terbentuk.

Status operasi yang dipakai:

- Menunggu;
- Sedang diproses;
- Memverifikasi;
- Selesai;
- Membutuhkan passphrase baru;
- Menunggu pemeriksaan sistem;
- Gagal.

Jangan menampilkan countdown waktu selesai yang tidak berasal dari backend.

## 7. Hasil sukses

Gunakan full result state dalam modal, bukan toast yang cepat hilang:

```text
+--------------------------------------------------------------------------+
|                                  [icon success]                           |
|                    Dokumen berhasil ditandatangani                       |
|                                                                          |
| 2 posisi QR berhasil diproses dan dokumen telah diverifikasi.            |
|                                                                          |
| Penandatangan : Nama signer                                              |
| Waktu          : 24 September 2026, 14:32 WIB                            |
| Dokumen        : SPP-LS 900/123/SPP/2026                                 |
+--------------------------------------------------------------------------+
|                              [Lihat Dokumen] [Selesai]                    |
+--------------------------------------------------------------------------+
```

Aturan:

- `Lihat Dokumen` hanya muncul bila backend memberi URL/capability yang sah;
- download hanya muncul bila authorization backend mengizinkan;
- `Selesai` menutup modal dan mengirim `sitangkas:esign:completed`;
- adapter halaman memutuskan tabel/detail mana yang perlu diperbarui;
- toast sukses boleh muncul setelah modal ditutup sebagai feedback sekunder.

## 8. Hasil gagal dan kondisi khusus

### 8.1 Passphrase salah atau resume diperbolehkan

Gunakan warning/action state:

```text
Passphrase tidak sesuai

1 dari 2 posisi QR telah selesai. Masukkan passphrase kembali untuk
melanjutkan dari QR 2. QR yang sudah selesai tidak akan diulang.

[Masukkan Passphrase Kembali] [Tutup]
```

Tombol resume hanya muncul bila backend memberi `requires_passphrase=true` dan
`resume_url`.

### 8.2 Gagal teknis retryable

Tampilkan pesan aman, operation yang terpengaruh, dan petunjuk. Jangan membuat
retry otomatis. Aksi hanya tersedia sesuai capability backend.

### 8.3 Status unknown

Gunakan visual warning, bukan danger:

```text
Status tanda tangan sedang diperiksa

Sistem belum dapat memastikan hasil operasi terakhir. Untuk mencegah tanda
tangan ganda, proses tidak dapat diulang sampai pemeriksaan selesai.

[Perbarui Status] [Tutup]
```

Tidak ada tombol retry/resume biasa.

### 8.4 Gagal terminal

Gunakan result state danger yang menampilkan:

- pesan aman;
- operasi yang gagal bila diketahui;
- kode referensi/correlation yang aman;
- tombol `Hubungi Admin` bila sesuai;
- tombol tutup.

Jangan menampilkan raw response BSrE, stack trace, NIK penuh, atau path file.

### 8.5 Session/authentication expired

- sebelum 202: user kembali ke halaman dan membuka session baru;
- sesudah 202: jangan menyatakan proses batal; attempt tetap dapat berjalan dan
  status diperiksa kembali setelah login;
- passphrase selalu dibersihkan.

## 9. Sistem notifikasi

Gunakan tiga level.

### 9.1 Toast

Untuk informasi ringan dan non-blocking:

- QR ditambahkan/dihapus;
- footer di-reset;
- posisi diterapkan ke seluruh halaman;
- status berhasil diperbarui;
- modal ditutup sementara proses server tetap berjalan.

Toast diletakkan konsisten pada area notification aplikasi dan tidak menutupi
action utama.

### 9.2 Inline alert

Untuk masalah dalam tahap aktif:

- QR keluar safe area;
- QR bertabrakan dengan QR/footer lain;
- footer tidak muat;
- session hampir kedaluwarsa;
- prepared revision stale;
- koneksi browser terputus;
- rate limit sementara.

Alert ditempatkan dekat workspace atau field terkait. Jika error dapat dipetakan
ke QR/footer tertentu, beri highlight pada objek tersebut.

### 9.3 Full result state

Untuk:

- sukses;
- partial/resume;
- unknown;
- gagal terminal;
- session/authentication conflict yang menghentikan proses.

Toast tidak boleh menjadi satu-satunya pemberitahuan untuk outcome TTE.

## 10. Modal validasi dokumen

Validasi mempunyai modal tersendiri, tetapi tidak pernah ditumpuk di atas modal
signing.

Layout desktop:

```text
+----------------------------------------------------------------------------+
| Validasi Dokumen                                                      [x] |
+------------------------------------------+---------------------------------+
|                                          | STATUS DOKUMEN                  |
|              PDF VIEWER                  | Valid - 3 signature             |
|                                          |                                 |
|                                          | PENANDATANGAN                   |
|                                          | 1. Nama - Jabatan - Tanggal     |
|                                          | 2. Nama - Jabatan - Tanggal     |
|                                          | 3. Nama - Jabatan - Tanggal     |
+------------------------------------------+---------------------------------+
|                                                              [Tutup]      |
+----------------------------------------------------------------------------+
```

Status visual:

- valid: success;
- invalid: danger;
- tidak memiliki signature: secondary;
- sedang diverifikasi: info + spinner;
- tidak dapat dipastikan/service unavailable: warning.

Bedakan invalid dari service unavailable. Jangan menyimpulkan dokumen invalid
hanya karena BSrE tidak dapat dihubungi.

## 11. Responsive behavior

### Desktop

- tiga panel editor;
- confirmation dua kolom: preview dan informasi/passphrase;
- thumbnail/inspector selalu terlihat bila ruang cukup.

### Tablet

- thumbnail menjadi drawer/collapsible rail;
- inspector menjadi offcanvas atau panel yang dapat disembunyikan;
- workspace mendapat prioritas lebar;
- confirmation tetap dua kolom bila cukup, lalu stack bila sempit.

### Mobile

- modal fullscreen;
- workspace satu kolom;
- thumbnail dibuka dari tombol `Halaman`;
- inspector menjadi bottom sheet/panel bawah;
- action bar sticky;
- target sentuh minimal nyaman;
- tidak ada horizontal overflow.

Pilot operasional memprioritaskan desktop/tablet. Mobile tetap harus dapat
membuka, memeriksa, dan menyelesaikan flow, tetapi penempatan presisi perlu
dibuktikan sebelum dinyatakan setara desktop.

## 12. Accessibility

- gunakan lifecycle dan markup Bootstrap Modal;
- modal mempunyai label yang jelas;
- focus masuk ke heading/action relevan ketika tahap berubah;
- focus kembali ke trigger ketika modal ditutup;
- semua action dapat digunakan dengan keyboard;
- QR aktif mempunyai label, nomor, halaman, dan ukuran yang dapat dibaca screen
  reader;
- drag mempunyai alternatif keyboard;
- icon selalu mempunyai label atau text pendamping;
- status tidak disampaikan hanya dengan warna;
- progress memakai `aria-valuenow`, `aria-valuemin`, dan `aria-valuemax`;
- notification penting memakai live region yang sesuai tanpa membacakan setiap
  perubahan pointer/drag.

## 13. Arah visual Bootstrap/Argon

Gunakan:

- typography Open Sans aplikasi;
- button, form-control, card, badge, alert, spinner, progress, grid, dan modal
  Bootstrap existing;
- color/radius/shadow Argon yang sudah dimuat layout;
- Font Awesome/Nucleo yang sudah tersedia;
- background workspace PDF netral;
- CSS editor minimal di bawah `.esign-ui`/`.esign-editor`;
- dark mode mengikuti `body.dark-version` dan override project.

Hindari:

- gradient berlebihan;
- toolbar editor generik yang tidak berkaitan dengan TTE;
- banyak floating action button;
- animasi panjang;
- custom modal engine;
- inline style berulang;
- perubahan selector global Bootstrap/Argon;
- raw HTML dari response backend/provider.

## 14. Komponen Svelte yang disarankan

```text
EsignApp
|-- EsignSigningModal
|   |-- SigningHeader
|   |-- SigningStepper
|   |-- SigningEditor
|   |   |-- PageThumbnailRail
|   |   |-- PdfWorkspace
|   |   |   |-- PdfPage
|   |   |   `-- PlacementOverlay
|   |   `-- PlacementInspector
|   |       |-- QrInspector
|   |       `-- FooterInspector
|   |-- PreparedConfirmation
|   |   |-- PreparedPdfPreview
|   |   |-- DocumentSummary
|   |   `-- PassphraseForm
|   |-- SigningProgress
|   `-- SigningResult
`-- EsignValidationModal
    |-- ValidationPdfPreview
    |-- VerificationSummary
    `-- VerificationSignerTable
```

`PreparedConfirmation` menggantikan pemisahan `PreparedReview` dan
`SignConfirmation` agar tidak ada layar review tambahan.

## 15. State machine UI final

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

Aturan:

- hanya `editing` yang dapat mengubah placement/footer;
- `preparing_rendition` menampilkan loading dan mengunci editor;
- `confirming_prepared` menampilkan prepared preview dan passphrase bersamaan;
- tidak ada `reviewing_prepared` terpisah;
- tidak ada checkbox afirmasi;
- `submitting` berlangsung hanya sampai response request final diketahui;
- setelah 202, `processing` memakai attempt sebagai source of truth;
- close sebelum 202 membersihkan session; close setelah 202 tidak membatalkan
  job;
- `unknown` tidak dapat masuk ke retry/resume tanpa keputusan backend.

## 16. Definition of Done visual

- [ ] Tampilan tidak meniru layout editor legacy.
- [ ] Satu modal signing dengan empat tahap terlihat.
- [ ] Editor desktop menggunakan thumbnail, workspace, dan inspector.
- [ ] Prepared preview dan passphrase berada pada satu tahap Konfirmasi.
- [ ] Tidak ada layar review prepared terpisah.
- [ ] Tidak ada checkbox `Saya telah memeriksa dokumen`.
- [ ] Klik `Tandatangani Sekarang` menjadi afirmasi eksplisit dan mengirim
      `affirmed=true`.
- [ ] Multi-QR dijelaskan sebelum final submit dan progress per QR terlihat.
- [ ] Sukses/gagal/partial/unknown memakai full result state yang berbeda.
- [ ] Toast hanya dipakai untuk feedback ringan.
- [ ] Modal validation membedakan invalid dan unavailable.
- [ ] Desktop, tablet, mobile, keyboard, screen reader, dan dark mode ditangani.
- [ ] CSS tetap scoped dan mengikuti Bootstrap 5/custom Argon.
- [ ] Passphrase tidak tersimpan pada state persisten atau telemetry.
- [ ] Tidak ada test suite otomatis yang dibuat atau dijalankan.
