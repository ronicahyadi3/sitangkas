# R1 — Inventaris Read-only Entry Point TTE dan PDF

Tanggal snapshot: **26 September 2026, Asia/Jakarta**.

Status: **selesai untuk source repository, route aktif, database lokal, dan
snapshot filesystem lokal**. Tidak ada mutation database, pemindahan file,
penghapusan file, perubahan route, atau perubahan perilaku runtime pada tahap
ini.

Dokumen ini adalah keluaran R1 setelah keputusan R0 mengunci bahwa:

- main table payment adalah surface paket/alur;
- modal Detail Dokumen adalah surface action per dokumen;
- general secure viewer adalah surface view/validasi/download;
- editor TTE adalah surface placement/prepare/passphrase/sign.

## 1. Batas dan cara membaca hasil

Inventaris memakai empat sumber bukti yang harus dibedakan:

1. **Source code** menunjukkan jalur yang dapat dipanggil aplikasi.
2. **Route aktif** menunjukkan endpoint Laravel yang benar-benar terdaftar.
3. **Database lokal** menunjukkan populasi metadata pada waktu snapshot.
4. **Filesystem lokal** hanya menunjukkan file yang sedang tersedia pada mesin
   ini ketika audit dilakukan.

Pemilik aplikasi menegaskan bahwa PDF pada folder `public/File_*` **hanya
sebagian**, karena yang disalin ke project ini terutama data sekitar 90 hari.
Karena itu:

- jumlah file lokal bukan total historis;
- file yang tidak ditemukan lokal tidak otomatis berarti data rusak/hilang;
- perbandingan database dengan filesystem bukan parity/missing-file report;
- R1 tidak melakukan hash, membuka isi, menyalin, mengubah, atau menghapus PDF;
- parity final hanya boleh dibuat oleh manifest mapping resumable terhadap
  sumber historis lengkap pada deployment yang berwenang.

Konfigurasi Apache/Nginx production, backup eksternal, dan consumer di luar
repository tidak dapat dibuktikan hanya dari workspace ini. Item tersebut
dicatat sebagai gate deployment, bukan diasumsikan aman.

## 2. Ringkasan utama

| Area | Hasil snapshot |
|---|---:|
| Row `document` | 482.579 |
| Row `document` aktif | 453.835 |
| Tahun metadata | 2024–2026 |
| Payment type | 7 |
| `src_type` unik | 16 |
| Controller payment | 30 |
| Controller payment yang memakai `public_path()` | 30 |
| Controller payment yang menyusun `data-url` langsung | 27 |
| Blade payment | 30 |
| Blade payment yang include modal detail | 30 |
| Blade payment yang include viewer legacy | 30 |
| Blade payment yang include editor legacy | 30 |
| Folder lokal `public/File_*` | 18 |
| PDF lokal dalam `public/File_*` | 148.244, snapshot parsial |
| PDF private canonical/attempt | 24 |
| Row `document_artifacts` | 24 |
| Dokumen yang mempunyai artifact | 11 |
| Workflow canonical | 5 |

Kesimpulan arsitektural:

1. `Data\Detail` memang titik integrasi paling universal untuk action per
   dokumen karena seluruh halaman payment memuat komponen detail bersama.
2. `Data\DetailTbp` adalah adapter kedua yang wajib masuk kontrak universal;
   ia tidak boleh terlupakan ketika memindahkan view/download TBP.
3. Canonical delivery baru mencakup LS SPP, LS SPJ, LS BMD, dan Billing LS
   yang sudah mempunyai attachment artifact.
4. Sebagian besar payment masih menulis dan membaca PDF melalui folder publik.
5. Canonical TTE/action frontend baru hanya tersedia untuk LS SPP.

## 3. Matriks populasi dokumen per payment

Angka berikut adalah row database, bukan jumlah file lokal.

| Payment | Total row | Aktif | Jumlah tipe | Billing terisi | SPJ fungsional terisi |
|---|---:|---:|---:|---:|---:|
| GU_SKPD | 41.869 | 39.499 | 11 | 1.026 | 274 |
| GU_UK | 28.590 | 26.128 | 12 | 1.866 | 582 |
| KKPD | 2.747 | 2.639 | 10 | 0 | 0 |
| LS | 395.124 | 372.299 | 8 | 35.173 | 0 |
| LS_GAJI | 13.265 | 12.353 | 8 | 1.064 | 0 |
| TU | 611 | 571 | 11 | 0 | 0 |
| UP | 373 | 346 | 7 | 0 | 0 |

Kombinasi tipe yang ditemukan:

| Payment | `src_type` database |
|---|---|
| GU_SKPD | BMD, LPJ, NPD, SP, SP2D, SPJ, SPM, SPP, SPTJM, SP_PENGAJUAN, TBP |
| GU_UK | BMD, LPJ, LPJ_BPP, NPD, SP, SP2D, SPJ, SPM, SPP, SPTJM, SP_PENGAJUAN, TBP |
| KKPD | BMD, DPR, DPT, NPD, SP, SP2D, SPM, SPP, SPTJM, SP_PENGAJUAN |
| LS | BMD, SP, SP2D, SPJ, SPM, SPP, SPTJM, SP_PENGAJUAN |
| LS_GAJI | BMD, SP, SP2D, SPJ, SPM, SPP, SPTJM, SP_PENGAJUAN |
| TU | BMD, LPJ, PENGAJUAN, SP, SP2D, SPM, SPP, SPTJM, SP_PENGAJUAN, STS, TBP |
| UP | BMD, SP, SP2D, SPM, SPP, SPTJM, SP_PENGAJUAN |

## 4. Matriks `src_type` database

| `src_type` | Total row | Aktif | Payment pemakai |
|---|---:|---:|---:|
| BMD | 10.916 | 9.722 | 7 |
| DPR | 373 | 315 | 1 |
| DPT | 373 | 373 | 1 |
| LPJ | 1.297 | 1.145 | 3 |
| LPJ_BPP | 1.237 | 896 | 1 |
| NPD | 21.643 | 19.416 | 3 |
| PENGAJUAN | 88 | 64 | 1 |
| SP | 58.045 | 55.178 | 7 |
| SP2D | 56.160 | 54.884 | 7 |
| SPJ | 78.384 | 72.357 | 4 |
| SPM | 57.969 | 55.178 | 7 |
| SPP | 60.605 | 55.189 | 7 |
| SPTJM | 57.969 | 55.178 | 7 |
| SP_PENGAJUAN | 57.968 | 55.178 | 7 |
| STS | 33 | 29 | 1 |
| TBP | 19.519 | 18.733 | 3 |

`Detail::findTypeFiles()` juga mempunyai referensi `SPJ_BPP` menuju
`File_SPJ_BPP`, tetapi snapshot database tidak mempunyai `src_type=SPJ_BPP` dan
filesystem lokal tidak mempunyai folder tersebut. Ini diperlakukan sebagai
mapping legacy/stale yang perlu dikonfirmasi pada R2/R11, bukan dibuat otomatis.

## 5. Surface UI dan consumer di repository

### 5.1 Main table payment

Seluruh 30 controller payment masih membentuk status/action HTML server-side.
Sebanyak 27 controller juga menyusun `data-url` langsung. Main table sering
memakai `.show-document` sebagai pintu ke detail, tetapi beberapa controller
masih membuat `.view-pdf` langsung.

Canonical action khusus LS SPP berada pada
`app/Http/Controllers/Payment/LS/SPP.php`:

- `data-esign-action="verify"` untuk validasi artifact;
- `data-esign-action="sign"` untuk membuka editor canonical.

Sesuai R0, ini adalah adapter transisi. Lokasi final kedua action adalah row
dokumen SPP di modal detail, bukan main table.

### 5.2 Modal Detail Dokumen

Seluruh 30 Blade payment memasukkan
`components.informations.detail`. Endpoint `GET /document/detail`:

- menyelesaikan keluarga dokumen;
- menerapkan scope posisi/tahun/organisasi yang kompleks;
- mengembalikan DataTables;
- mencampur status dengan HTML action pada kolom `status`;
- memakai canonical content/download hanya untuk subset LS;
- fallback ke raw `/File_{TYPE}` untuk tipe lain;
- menambahkan direct download anchor.

Ini adalah adapter utama untuk R2–R7, tetapi authorization/capability harus
diekstrak dari renderer HTML ke service/policy backend.

### 5.3 Modal detail TBP

`GET /document/detail-tbp` dipakai oleh GU_SKPD/GU_UK untuk daftar TBP turunan.
Controller sudah memeriksa posisi, tahun, unit, dan assignment, tetapi action
masih mengirim:

- raw `/File_TBP/{file}` atau `/File_TBP/signs/{file}`;
- `.view-pdf` dengan arbitrary `data-url`;
- direct `<a download>`.

`Data\DetailTbp` wajib menjadi adapter resmi kedua pada kontrak R2.

### 5.4 History dokumen

`POST /document/history` membentuk tombol `.view-pdf` dari
`document_process.src_type/src_name` dan menebak folder `signs` berdasarkan
jenis action. Jalur ini:

- penting untuk data historis dan file invalid yang tidak boleh dihapus;
- belum menggunakan immutable artifact identity;
- masih mengekspos raw public URL;
- harus mendapat legacy source adapter, bukan dipaksa memakai current artifact.

### 5.5 Viewer legacy

Seluruh 30 Blade payment memasukkan
`components.informations.pdfview`. Implementasinya:

- menerima arbitrary URL dari `data-url`;
- melakukan fetch PDF dari browser;
- membuat object URL dan `<embed>`;
- menyimpan Blob/object URL dalam global `Map`;
- mengunggah Blob yang sama untuk validasi;
- tidak mencabut seluruh object URL saat modal ditutup.

Komponen memanggil named route `esign.validate`, tetapi route tersebut tidak
terdaftar pada `php artisan route:list`. Viewer canonical F13 mempunyai endpoint
artifact verification yang berbeda dan belum menjadi replacement viewer umum.

### 5.6 Editor legacy

Seluruh 30 Blade payment masih memasukkan `components.esign.esign`, yang memuat
`public/assets/js/pdf/bundle.js`. Bundle tersebut memanggil:

- `POST /esign/validate`;
- `POST /qr/generate`;
- `POST /esign/sign`.

Ketiga route legacy tidak terdaftar pada route aktif. Bundle juga memuat
`downloadjs` dari CDN eksternal. Source ini dipertahankan sebagai compatibility
reference sampai cutover, tetapi tidak boleh dianggap endpoint TTE aktif yang
terbukti.

## 6. Matriks endpoint PDF/TTE aktif

| Endpoint/surface | Scope | Authorization/source | Status R1 |
|---|---|---|---|
| `/document/ls/spp/{document}/content` dan `/download` | LS SPP | encrypted ID, current artifact, Gate, integrity check, private stream | canonical tersedia |
| `/document/ls/spj/{document}/content` dan `/download` | LS SPJ anak SPP | parent constraint, current artifact, Gate, integrity check | canonical tersedia |
| `/document/ls/bmd/{document}/content` dan `/download` | LS BMD anak SPP | parent constraint, current artifact, Gate, integrity check | canonical tersedia |
| `/document/ls/billing/{document}/content` dan `/download` | Billing attachment LS SPJ | attachment artifact, Gate, integrity check | kode tersedia; snapshot DB belum mempunyai attachment artifact |
| `/esign/internal/signing-sessions/.../preview` | source editor TTE | session + policy + exact artifact | canonical tersedia |
| `/esign/internal/.../renditions/.../preview` | prepared preview | session/revision binding | canonical tersedia |
| `/esign/internal/artifacts/{artifact}/verification[/preview]` | validasi exact artifact | UUID artifact + policy | canonical tersedia, LS SPP action pertama |
| `/document/detail` | keluarga dokumen | controller scope; response HTML/campuran | aktif, perlu refactor R2–R5 |
| `/document/detail-tbp` | TBP turunan | controller scope; raw public action | aktif, perlu adapter R2–R5 |
| `/document/history` | riwayat keluarga | controller scope; raw public action | aktif, perlu legacy history adapter |
| `/File_{TYPE}/...` | sebagian besar legacy | static webroot, tidak melalui Laravel policy | potential original bypass |
| `/esign/validate` | viewer/editor legacy | route tidak ditemukan | stale/broken contract |
| `/qr/generate` | editor legacy | route tidak ditemukan | stale/broken contract |
| `/esign/sign` | editor legacy | route tidak ditemukan | stale/broken contract |
| `/verify/{public_id}` | public verification | belum ada | backlog |

Semua route Laravel document/eSign di atas berada di dalam middleware auth,
account-accessible, single-device, active-position, MFA, dan password-fresh
sesuai nesting `routes/web.php`. Static `/File_*` tidak melewati middleware
Laravel ketika web server melayaninya langsung.

## 7. Snapshot filesystem publik yang parsial

Snapshot lokal memiliki **148.244 PDF** pada 18 folder dengan total sekitar
**85,93 GiB**. Angka ini tidak menunjukkan total historis.

| Folder | Total PDF | Di bawah `signs/` | Di luar `signs/` |
|---|---:|---:|---:|
| File_Billing | 6.374 | 0 | 6.374 |
| File_BMD | 2.827 | 0 | 2.827 |
| File_DPR | 187 | 94 | 93 |
| File_DPT | 182 | 88 | 94 |
| File_LPJ | 581 | 287 | 294 |
| File_LPJ_BPP | 381 | 187 | 194 |
| File_NPD | 10.771 | 7.067 | 3.704 |
| File_PENGAJUAN | 66 | 49 | 17 |
| File_SP | 15.043 | 7.459 | 7.584 |
| File_SP_PENGAJUAN | 15.103 | 7.431 | 7.672 |
| File_SP2D | 14.275 | 7.075 | 7.200 |
| File_SPJ | 11.110 | 0 | 11.110 |
| File_spj_fungsional | 141 | 0 | 141 |
| File_SPM | 15.041 | 7.431 | 7.610 |
| File_SPP | 31.073 | 22.931 | 8.142 |
| File_SPTJM | 14.986 | 7.408 | 7.578 |
| File_STS | 12 | 8 | 4 |
| File_TBP | 10.091 | 6.672 | 3.419 |

Jumlah database dan filesystem sengaja tidak diberi persentase coverage karena:

- snapshot file hanya sebagian;
- satu nama dapat memiliki source dan signed copy;
- row deleted/history tetap sensitif;
- Billing dan SPJ fungsional merupakan kolom attachment, bukan selalu row
  `src_type` tersendiri;
- mapping belum mempunyai manifest source lengkap.

## 8. Snapshot canonical private

| Jenis | File/row | Keterangan |
|---|---:|---|
| PDF private total | 24 | 17 source, 2 signed, 4 intermediate, 1 failed-output |
| Visual QR PNG | 7 | private signature visual, bukan PDF artifact |
| `document_artifacts` | 24 | seluruhnya disk `private`, tahun 2026 |
| Dokumen dengan artifact | 11 | 4 LS SPP, 4 LS SPJ, 2 LS BMD, 1 GU_SKPD NPD |
| Current artifact | 11 | 9 before-sign, 2 after-sign |
| Workflow | 5 | 4 LS SPP active, 1 GU_SKPD NPD draft |
| Attempt | 6 | 2 succeeded, 3 failed, 1 partially-signed |

Tidak ada row artifact bertipe `attachment` pada snapshot database meskipun
kolom Billing berisi puluhan ribu referensi legacy. Ini menunjukkan backfill
attachment belum berlangsung dan route Billing canonical hanya dapat melayani
upload yang benar-benar sudah mempunyai attachment artifact.

## 9. Non-payment dan consumer eksternal

- `user_position_documents` mendukung PDF/JPG/PNG private, tetapi tabel lokal
  kosong dan belum ditemukan content/download route pada route aktif.
- Tidak ditemukan generator PDF report/export aktif berbasis Dompdf/mPDF/TCPDF
  pada repository ini.
- Export Excel DataTables bukan bagian delivery PDF.
- Aplikasi eksternal yang membaca `before_signs`/`after_signs` sudah diketahui
  dari keputusan sebelumnya, tetapi konfigurasi/jalur akses aplikasi tersebut
  berada di luar repository dan tidak dapat diaudit dari workspace ini.
- Web-server alias, reverse proxy, backup mount, scheduled copy, dan URL yang
  disimpan aplikasi eksternal harus diinventarisasi sebelum tahap penutupan
  bypass/decommission, tetapi tidak menghalangi desain kontrak R2 yang additive.

## 10. Register temuan dan risiko

| ID | Severity | Temuan | Dampak/penanganan |
|---|---|---|---|
| R1-01 | Critical sebelum enforcement watermark | PDF legacy berada di webroot `public/File_*` | Jika dapat diakses langsung, policy/watermark dapat dilewati. Jangan aktifkan enforcement final sebelum route/static bypass ditutup per scope. |
| R1-02 | High | Viewer legacy memanggil named route `esign.validate` yang tidak terdaftar | Kontrak viewer legacy tidak konsisten dengan route aktif; replacement viewer tidak boleh bergantung padanya. |
| R1-03 | High | Editor legacy memanggil tiga endpoint yang tidak terdaftar | Jangan menganggap `.signModal` sebagai fallback operasional yang sehat; rollout canonical harus fail-closed per tipe. |
| R1-04 | High | `Detail.php` mencampur status, authorization candidate, HTML action, path, dan download | R2 harus membuat contract data; R3 resolver; R5 controller tipis. |
| R1-05 | High | `DetailTbp.php` dan `History.php` tetap memakai raw public URL | Keduanya wajib masuk source/delivery adapter, bukan hanya modal detail utama. |
| R1-06 | High | Canonical artifact hanya mencakup 11 dari 482.579 row dokumen | Rollout global dilarang; gunakan allowlist `payment_type:src_type`. |
| R1-07 | High | Billing legacy banyak, attachment artifact snapshot masih 0 | Viewer harus mempunyai explicit `legacy_attachment_pending` state/fallback terotorisasi sampai mapping selesai. |
| R1-08 | Medium | Semua Blade payment memuat viewer/editor legacy walau action tidak selalu ada | Setelah cutover per wave, include legacy harus dilepas hanya pada halaman yang tidak lagi memerlukannya. |
| R1-09 | Medium | `findTypeFiles()` memakai numeric role candidate dan mapping stale `SPJ_BPP` | Jangan menjadikannya final authorization; exact canonical assignment tetap authoritative. |
| R1-10 | Deployment gate | Reachability static file, external consumer, dan source historis lengkap belum diverifikasi | Wajib sebelum P14/decommission; tidak boleh disimpulkan dari snapshot lokal parsial. |

## 11. Keputusan input untuk R2

R2 harus merancang satu response data per row dokumen dengan minimal:

- opaque `document_id`;
- `payment_type` dan `document_type` untuk display/diagnostic, bukan authorization
  frontend;
- status terstruktur (`code`, `label`, `tone`);
- capability `view`, `sign`, dan `download_available_in_viewer`;
- `step_public_id` hanya saat exact canonical step boleh ditandatangani;
- source state `canonical`, `legacy_private_pending`,
  `legacy_public_pending`, atau `unavailable`;
- mode action `canonical`, `legacy_transition`, atau `none`;
- attachment list terstruktur untuk Billing/SPJ fungsional;
- alasan disabled yang aman untuk UX tanpa membocorkan path/data sensitif.

Response final tidak boleh berisi raw filesystem path, arbitrary `data-url`,
NIK, passphrase, credential, atau vendor response mentah.

R2 juga harus mempertahankan pemisahan:

- `verify_data` = verifikasi proses bisnis paket;
- verification summary = validasi kriptografis artifact di viewer;
- `view` = authorization membaca dokumen;
- `download` = capability terpisah yang dieksekusi dari viewer dan diperiksa
  kembali pada endpoint delivery.

## 12. Gate R1 dan langkah berikutnya

R1 dinyatakan selesai untuk scope repository/local snapshot karena:

- seluruh route document/eSign aktif telah dicatat;
- seluruh controller dan Blade payment telah dipetakan;
- `Detail`, `DetailTbp`, `History`, viewer legacy, dan editor legacy telah
  diperiksa;
- populasi database payment/src type/canonical telah dihitung read-only;
- snapshot public/private telah dihitung tanpa mengubah file;
- keterbatasan snapshot public parsial dicatat eksplisit;
- bypass dan unknown deployment/external consumer tidak disembunyikan.

**R2 — kontrak respons Detail Dokumen** selesai di source pada 26 September
2026. Endpoint `/document/detail` dan `/document/detail-tbp` sekarang menambah
field `document_contract` versi 1 yang berisi opaque document ID, tipe payment
dan dokumen, status terstruktur, capability, canonical step/artifact public ID,
source state, action mode, disabled reason aman, serta attachment Billing/SPJ
Fungsional tanpa nama/path file.

Kontrak ini additive. Kolom HTML/path legacy pada envelope DataTables masih
dipertahankan untuk UI lama dan belum boleh dianggap bagian kontrak baru.
Canonical step LS SPP diselesaikan secara batch sehingga tidak membuat query
per row. Artifact canonical ambigu dibuat fail-closed; metadata legacy tetap
ditandai `legacy_public_pending` tanpa menganggap snapshot filesystem lokal
lengkap.

Fondasi pertama **R3 — source resolver universal** selesai di source pada 26
September 2026: DTO internal immutable, registry mapping legacy terkontrol,
dan resolver current canonical artifact sudah tersedia. Registry tidak
mengaktifkan `SPJ_BPP` sampai sumber aktifnya selesai direview. Resolver belum
dipakai oleh endpoint runtime dan belum mempunyai adapter legacy/composite.
Cutover UI dan penghapusan action/download legacy baru dilakukan setelah
R3–R9 lulus.

### Status lanjutan R3 — fallback source

Pada 26 September 2026 resolver telah dihubungkan ke `Data\Detail` dan
`Data\DetailTbp` dengan urutan canonical, legacy-private, lalu legacy-public.
Billing, SPJ Fungsional, dan TBP termasuk dalam resource mapping terkontrol.
`document_contract.source_state` sekarang berasal dari source yang benar-benar
dapat di-resolve, bukan hanya keberadaan nama file pada row database. Path dan
nama disk tidak diserialisasi ke contract.

Source yang belum dapat diselesaikan atau belum dapat dibuktikan penuh:

- snapshot lokal tidak mempunyai layout legacy `File_*` di bawah
  `storage/app/private/documents`, sehingga cabang legacy-private belum
  mempunyai acceptance file nyata pada mesin ini;
- snapshot `public/File_*` hanya sebagian; file yang tidak ditemukan diberi
  state `unavailable`, tetapi tidak dianggap orphan dan tidak dihapus;
- belum ada row artifact canonical bertipe attachment pada snapshot database,
  sehingga Billing dan SPJ Fungsional yang ditemukan masih berasal dari
  fallback legacy-public;
- `SPJ_BPP` tetap `needs_review` dan tidak dimapping otomatis;
- filename tidak aman, ekstensi non-PDF, header bukan `%PDF-`, zero-byte, file
  hilang, dan file tidak terbaca diselesaikan secara fail-closed;
- `Data\History`, report/export, dan consumer eksternal belum memakai resolver;
- URL static `/File_*` masih dapat menjadi bypass sampai cutover delivery dan
  konfigurasi web server selesai.

Action HTML legacy pada Detail dan Detail TBP sengaja tidak diubah dalam tahap
ini. Karena itu layanan lama tetap berjalan sambil contract canonical mulai
menampilkan state source yang akurat.

### Status setelah endpoint delivery ORIGINAL universal

Pada 26 September 2026 resolver R3 sudah mempunyai consumer HTTP universal:

- `GET /document/pdf/{opaque-document}/{resource}/content` untuk inline view;
- `GET /document/pdf/{opaque-document}/{resource}/download` untuk attachment;
- resource dibatasi ke `document`, `billing`, dan `spj_fungsional`;
- ID database numerik mentah ditolak;
- authentication, konteks posisi/tahun, organizational scope/assignment, serta
  policy canonical diperiksa kembali pada endpoint;
- binary response tidak memuat storage disk atau path;
- contract Detail Dokumen menerbitkan URL delivery aman secara additive;
- action HTML dan static URL legacy belum dicabut agar layanan yang belum
  tercakup mapping tetap berjalan.

Endpoint ini baru bridge ORIGINAL. Ia belum menggantikan desain persistent
delivery session, audit access append-only, watermark decision/derivative,
viewer Svelte umum, atau kontrol web-server untuk menutup `/File_*`.
