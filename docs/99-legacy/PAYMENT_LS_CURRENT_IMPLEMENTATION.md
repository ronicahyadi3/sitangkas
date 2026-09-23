# Kondisi Implementasi Payment LS Saat Ini

Tanggal snapshot: **22 September 2026**.

Status: **SPP LS sedang diintegrasikan; create/upload dan replacement file utama
SPP sudah memakai canonical private artifact. Keseluruhan Payment LS belum
selesai dan belum dinyatakan lulus runtime end-to-end**.

Dokumen ini adalah handoff utama untuk AI agent yang melanjutkan Payment LS.
Dokumen analisis dan rencana lama tetap berguna sebagai baseline, tetapi status
aktual harus dibaca dari file ini dan diverifikasi terhadap working tree.

## 1. Urutan baca wajib

1. `../00-ai-agent/PROJECT_INVARIANTS.md`.
2. Dokumen ini.
3. `PAYMENT_LS_ANALYSIS.md` untuk temuan awal dan risiko LS-01 sampai LS-08.
4. `PAYMENT_LS_IMPLEMENTATION_PLAN.md` untuk urutan implementasi keseluruhan.
5. `../08-esign/CURRENT_ESIGN_IMPLEMENTATION.md` bila menyentuh artifact, TTE,
   delivery PDF, atau compatibility ledger.
6. `../08-esign/LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md` bila menyentuh
   `document`, `document_process`, anggaran, `before_signs`, atau `after_signs`.
7. `../08-esign/ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md` bila
   menyentuh file sebelum/sesudah TTE, version chain, atau migrasi file legacy.
8. `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`,
   `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md`, dan
   `../04-year-permissions/AI_AGENT_YEAR_PERMISSION_CONTEXT.md` bila menyentuh
   authorization, posisi, acting context, atau tahun.

Project lama di `C:\Apache24\htdocs\sitangkas` hanya menjadi referensi perilaku.
Jangan menyalin credential, URL publik, atau aturan akses lama tanpa adaptasi.

## 2. Batas scope dan sumber kebenaran

- Prioritas pengguna tetap Payment LS, dimulai dari SPP. Payment lain menunggu
  LS stabil.
- `document` tetap operational projection bersama untuk semua payment. Modelnya
  tidak dibuat khusus LS.
- `document_process` tetap histori kompatibilitas bersama untuk semua payment.
- `anggaran_kegiatan_temp` adalah katalog rekening/pagu dan
  `anggaran_kegiatan` adalah alokasi per SPP.
- File/version chain canonical berada pada `document_artifacts`.
- Workflow dan percobaan TTE canonical berada pada tabel workflow, step,
  attempt, provider response, signature, certificate, dan event e-sign.
- `before_signs` dan `after_signs` tetap ledger kompatibilitas append-only.
- Tabel legacy operasional yang sudah disediakan pengguna tidak boleh dianggap
  perlu diganti dengan tabel khusus LS.

## 3. Komponen yang sudah tersedia

### 3.1 Model bersama

Model berikut sudah disesuaikan dengan project sekarang:

- `App\Models\Document`:
  - memakai tabel `document` dan `SoftDeletes`;
  - konstanta tipe SPP, SPJ, BMD, SPM, SP, SPTJM, SP Pengajuan, dan SP2D;
  - relasi unit kerja, posisi pengunggah/penerima, histori, anggaran, artifact,
    workflow, attempt, `before_signs`, dan `after_signs`;
  - helper parsing CSV legacy `status`, `submit`, dan `assigned_to`;
  - model ini tetap dipakai semua payment.
- `App\Models\DocumentHistory` memakai tabel `document_process`, menyimpan
  action `UPLOAD`, `EDITED`, `SUBMIT`, `VERIFY`, `REJECT`, `DELETE`, dan `TTE`.
- `App\Models\AnggaranKegiatanTemp` dan `App\Models\AnggaranKegiatan` mempunyai
  scope tahun, unit, rekening, dan SPP serta cast nominal/pagu decimal.
- `App\Models\BeforeSign` dan `App\Models\AfterSign` mewakili ledger legacy;
  keduanya bukan penyimpanan byte PDF.

### 3.2 Service konteks dan dokumen

- `ActivePositionService` menyelesaikan posisi efektif dan posisi nyata.
- `PositionIdentityResolver` menyatukan ID posisi canonical dan alias legacy.
- `DocumentOrganizationScope` mengganti asumsi lama `skpd_id` dengan mapping
  unit/instansi project sekarang.
- `DocumentHistoryService` menulis `document_process` dan, setelah commit,
  mengantrekan `ProvisionCanonicalDocument`.
- `DocumentArtifactPersistenceService` menulis file canonical ke disk private,
  menghitung hash/size, dan membuat version chain artifact.

### 3.3 Request SPP

`StoreSppRequest` sudah tersedia dan menangani:

- authorization posisi BP/BPP;
- izin tulis tahun aktif dan penolakan tahun mendatang;
- PDF SPP/SPJ wajib, Billing opsional, dan BMD kondisional;
- batas ukuran 5 MiB per file;
- normalisasi jenis belanja dan nilai opsional `null`/`undefined`;
- rekening distinct, maksimal 100;
- rekening harus sesuai sub-kegiatan, unit, dan tahun;
- total nominal rekening harus sama dengan nominal SPP;
- validasi sisa pagu.

`UpdateSppRequest` sekarang tersedia. Request ini:

- membatasi aktor ke posisi BP/BPP yang memiliki akses tulis pada tahun aktif;
- membuka encrypted route ID dan memastikan resource adalah SPP untuk payment,
  unit kerja, serta tahun aktif yang benar;
- hanya mengizinkan update ketika dokumen belum ditandatangani/selesai dan
  masih draft atau sudah ditolak;
- membuat seluruh file replacement opsional, tetapi tetap mewajibkan BMD untuk
  Belanja Modal/Persediaan apabila BMD lama belum tersedia;
- memakai aturan rekening, total nominal, sub-kegiatan, unit, tahun, dan sisa
  pagu yang setara dengan create;
- mengecualikan alokasi SPP yang sedang diedit saat menghitung realisasi agar
  nominal lamanya tidak dihitung dua kali.

Request yang sama masih dapat mengenali controller LS Gaji dan memakai
`payment_type=LS_GAJI`. Aktivasi penuh LS Gaji tetap berada di luar fokus
vertical slice LS saat ini.

## 4. Route dan menu yang aktif

Route LS yang saat ini terdaftar:

- SPP: index, JSON, store, edit, update, submit, dan submit ke PPTK;
- SPM: index, JSON, pilihan SPP, store, edit, update, dan submit;
- SP2D: index, JSON, pilihan SPM, store, edit, update, dan submit;
- endpoint shared detail, history, delete, denied, verify, sub-kegiatan,
  rekening, dan detail rekening;
- endpoint pilihan PPTK khusus payment;
- delivery SPP LS:
  - `document.ls.spp.content`;
  - `document.ls.spp.download`.

Sidebar mempunyai grup **Pencairan Langsung** dan memetakan SPP/SPM/SP2D sesuai
kode jabatan. Keberadaan route/menu SPM dan SP2D tidak berarti kedua tahap itu
sudah direview dan dinyatakan siap. Fokus implementasi aktif masih SPP.

## 5. Controller Data shared

Controller `Data/Delete`, `Denied`, `Detail`, `DetailTbp`, `History`, `Rekening`,
dan `Verify` telah diadaptasi dari project lama. Pola penyesuaian yang harus
dipertahankan saat melanjutkan:

- ID request didecode dengan `EncryptedId`;
- posisi aktif berasal dari `ActivePositionService`;
- alias/canonical position diselesaikan melalui `PositionIdentityResolver`;
- scope organisasi menggunakan `DocumentOrganizationScope`;
- scope tahun memakai tahun terpilih dan `YearAccessService` pada mutasi;
- query detail/history dan mutasi membatasi dokumen berdasarkan akses keluarga,
  unit, peran, payment type, dan document type;
- mutasi keluarga dokumen dibungkus transaksi dan menulis histori;
- logging memakai channel `module_document_data` tanpa menyimpan secret TTE.

`Detail` mempunyai pengecualian khusus **LS + SPP**: URL preview dan download
dibentuk dari named delivery route canonical. Payment/type lain masih memakai
path `public/File_*` legacy.

## 6. Alur create/upload SPP LS saat ini

Entry point: `Payment\LS\SPP::store()`.

### 6.1 Validasi awal

1. `StoreSppRequest` mengotorisasi posisi dan tahun.
2. Field bisnis, file, rekening, total nominal, dan pagu divalidasi.
3. Controller mengambil posisi efektif untuk `uploaded_by` dan unit kerja.
4. Pembuat artifact memakai user dari posisi nyata; fallback ke authenticated
   request user.

### 6.2 Penyimpanan file SPP

1. Stream `file_spp` dibuka dari temporary upload.
2. `stagePdfStream()` menulis file ke:

   ```text
   storage/app/private/documents/staging/{artifact-uuid}.pdf.part
   ```

3. Persistence service menghitung header PDF, size, dan SHA-256.
4. `document.src_name` diset ke `{artifact-uuid}.pdf` untuk kompatibilitas
   kolom/nama legacy; nilai ini bukan sumber lokasi file.
5. Setelah row `document` SPP dibuat, `finalizeSourceArtifact()` memindahkan
   file ke:

   ```text
   storage/app/private/documents/source/{YYYY}/{MM}/{uuid-prefix}/{uuid}.pdf
   ```

6. Dibuat `document_artifacts` dengan:
   - `artifact_type = before_sign`;
   - `version = 1` untuk upload pertama;
   - `is_current = true`;
   - disk `private`;
   - original/stored filename, size, SHA-256, tahun, bulan;
   - reference ke `document.id` dan user pembuat;
   - metadata `document_type=SPP`, `payment_type=LS`, dan
     `storage_strategy=canonical_private_upload`.
7. `DocumentHistoryService::upload()` menulis histori `UPLOAD`.
8. Hook after-commit provisioning tetap berjalan, tetapi menemukan source
   artifact current yang sudah ada sehingga tidak perlu membaca
   `public/File_SPP`.

`SPP::store()` tidak membuat file SPP baru di `public/File_SPP`.

### 6.3 Dokumen pendamping yang masih legacy

Pada create SPP saat ini:

- SPJ masih ditulis ke `public/File_SPJ`;
- Billing masih ditulis ke `public/File_Billing`;
- BMD masih ditulis ke `public/File_BMD`;
- row SPJ/BMD tetap dibuat pada `document` dan histori upload tetap ditulis;
- provisioning after-commit dokumen pendamping masih bergantung pada locator
  file legacy sampai migrasi private-nya dikerjakan.

### 6.4 Anggaran dan transaksi

- SPP, SPJ/BMD, histori, dan snapshot `anggaran_kegiatan` dibuat dalam transaksi
  controller yang sama.
- `SPP::store()` mengambil tahun dari `YearAccessService` dan hanya memakai
  payload hasil `validated()` untuk field bisnis serta rekening.
- Di awal transaksi, row sumber pagu `anggaran_kegiatan_temp` untuk tahun, unit
  anggaran, sub-kegiatan, dan rekening yang diajukan dikunci secara deterministik
  dengan `orderBy('id')->lockForUpdate()`.
- Setelah lock diperoleh, controller memeriksa ulang duplikasi rekening,
  keberadaan rekening, kecocokan total nominal, serta sisa pagu.
- Row realisasi `anggaran_kegiatan` yang relevan juga dibaca dengan
  `lockForUpdate()`. Lock row sumber pagu menjadi mutex bersama ketika row
  realisasi belum ada, sehingga dua create pada rekening yang sama tidak dapat
  sama-sama memakai snapshot sisa pagu lama.
- Create dan update memakai helper `lockAndValidateSppBudget()` yang sama.
  Update mengecualikan alokasi SPP yang sedang diedit, sedangkan create
  menghitung seluruh realisasi yang sudah ada.
- Nominal dibandingkan sebagai integer dua desimal melalui `decimalToCents()`;
  data alokasi tidak lagi dibentuk dari cast `float` di controller.
- Rekening yang hilang tidak lagi dilewati diam-diam. Kondisi tersebut
  menghasilkan `ValidationException`, transaksi rollback, dan respons validasi
  `422`.
- Jika transaksi gagal, file pendamping public yang baru dibuat dibersihkan.
- Source SPP private dibersihkan melalui
  `discardUnpersistedSourceArtifact()` hanya jika tidak ada row
  `document_artifacts` untuk public ID tersebut.
- Cleanup memeriksa integritas staging/final sebelum menghapus sehingga tidak
  menghapus artifact sah yang sudah persisten.

Validasi pagu pada `StoreSppRequest` tetap dijalankan sebagai pemeriksaan awal,
tetapi hasilnya bukan keputusan commit. Keputusan akhir selalu dihitung ulang di
dalam transaksi setelah lock diperoleh.

Schema aktif baru mempunyai indeks `anggaran_kegiatan_temp.id_unit_kerja` dan
belum mempunyai indeks komposit tahun/unit/sub-kegiatan/rekening. Correctness
locking sudah tersedia, tetapi query dapat memindai dan mengunci lebih banyak
row pada volume besar. Penambahan indeks perlu dirancang sebagai migration wave
terpisah setelah review kapasitas dan metadata lock.

## 7. Delivery route dan current artifact resolver

### 7.1 Resolve dokumen

`LsSppDocumentDeliveryController`:

1. menerima encrypted document ID;
2. mengembalikan 404 untuk ID tidak valid;
3. hanya menerima `payment_type=LS` dan `src_type=SPP`;
4. meminta `CurrentDocumentArtifactResolver` memilih artifact current;
5. menjalankan policy `view` atau `download` melalui Gate;
6. mengirim stream melalui `DocumentArtifactIntegrityService`.

### 7.2 Resolve artifact

Resolver menuntut tepat satu row `is_current=true` untuk dokumen. Kondisi:

- tidak ada current artifact: 404;
- lebih dari satu current artifact: invariant violation;
- `document_id` tidak cocok: invariant violation;
- tipe current hanya boleh `before_sign` atau `after_sign`;
- `failed_output` tidak dapat dilayani.

Tidak ada fallback ke `public/File_SPP`. Dokumen historis LS SPP harus mempunyai
mapping/provisioning canonical sebelum route ini dapat melayaninya.

### 7.3 Response file

- `content` memakai disposition `inline`;
- `download` memakai disposition `attachment`;
- filename memakai nama asli jika ada;
- metadata dan byte diperiksa kembali: disk/path, ukuran, header `%PDF-`, dan
  SHA-256;
- response memakai `private, no-store, max-age=0, must-revalidate`, `nosniff`,
  dan content type PDF;
- private filesystem path tidak dikirim ke browser.

### 7.4 Kontrak hash path yang sudah disatukan

Persistence service menyimpan:

```text
storage_path_sha256 = SHA-256(storage_disk + ":" + file_path)
```

`DocumentArtifactIntegrityService` sekarang menghitung formula yang sama melalui
`DocumentArtifactStoragePath::checksum()`. Persistence dan pembaca tidak lagi
mempunyai implementasi hash terpisah.

Pemeriksaan database read-only setelah perbaikan menemukan 4 dari 4 artifact
aktif cocok dengan formula `storage_disk:file_path` dan 0 cocok dengan formula
lama `file_path` saja. Tidak diperlukan migrasi nilai hash untuk data yang ada.
Preview/download tetap memerlukan validasi request runtime sebelum dinyatakan
lulus end-to-end.

## 8. Arti `before_signs`, `after_signs`, dan canonical artifact

Nama tabel legacy dan tipe artifact canonical mirip, tetapi fungsinya berbeda:

| Komponen | Waktu dibuat | Isi/fungsi |
|---|---|---|
| `document_artifacts.before_sign` | Saat upload/provision source PDF | File PDF asli/belum TTE di private storage |
| `before_signs` | Saat user menekan sign final dan attempt persisten dibuat | Ledger metadata input attempt: NIK masked, document ID, source name, MD5, size |
| `document_artifacts.after_sign` | Setelah provider memberi output dan verifikasi valid | File PDF hasil TTE yang valid di private storage |
| `document_artifacts.failed_output` | Provider memberi output tetapi verifikasi gagal | Evidence output gagal, tidak current |
| `after_signs` | Saat worker menutup outcome attempt | Ledger outcome success, failed, atau unknown |
| `document_process` action `TTE` | Hanya setelah TTE dan verifikasi sukses | Histori sukses untuk UI/laporan legacy |

Tabel `before_signs` dan `after_signs` tidak menyimpan byte PDF.

## 9. Alur TTE yang berlaku

1. Upload SPP membentuk current artifact `before_sign`. Upload belum membuat
   row `before_signs`.
2. Membuka modal, preview, atau membatalkan sebelum sign final belum membuat
   attempt maupun compatibility ledger.
3. Saat sign final:
   - session, authorization, assignment, preview SHA-256, dan integritas source
     divalidasi ulang;
   - dibuat `esign_attempt` berstatus `prepared`;
   - dibuat satu row `before_signs` dan link
     `esign_attempt_legacy_links`;
   - job signing dikirim setelah commit.
4. Worker membaca artifact source, mengambil passphrase ephemeral sekali pakai,
   lalu memanggil e-sign client tanpa transaksi DB terbuka.
5. Output provider disimpan ke staging dan diverifikasi.
6. Jika valid:
   - dibuat artifact `after_sign`;
   - attempt menjadi `succeeded`;
   - source lama menjadi `is_current=false`;
   - hasil menjadi `is_current=true`;
   - dibuat `after_signs.status=true`;
   - dibuat `document_process.action=TTE`;
   - `document.signed_at` diisi hanya ketika workflow selesai.
7. Jika output diterima tetapi invalid:
   - dibuat `failed_output` sebagai evidence non-current;
   - source lama tetap current;
   - dibuat `after_signs.status=false`;
   - tidak dibuat histori sukses `TTE`.
8. Jika provider menolak, timeout/unknown, atau proses gagal sebelum ada output:
   - artifact hasil dapat tidak ada;
   - `after_signs.status=false` tetap dibuat dengan response aman;
   - state canonical membedakan `failed` dan `unknown`.
9. Retry adalah attempt baru dan menambah row `before_signs`/`after_signs` baru.
   Row lama tidak ditimpa atau dihapus.

Untuk multi-signer, artifact `after_sign` signer sebelumnya menjadi source
signer berikutnya. Karena itu row `before_signs` untuk attempt berikutnya dapat
merujuk input yang secara tipe canonical adalah `after_sign`.

Keberadaan row `after_signs` saja bukan bukti sukses. Bukti sukses minimum:

- `after_signs.status=true`;
- attempt `succeeded`;
- artifact canonical `after_sign` tersedia;
- histori `document_process` action `TTE` tersedia.

## 10. Kondisi update SPP

`SPP::update()` sekarang menangani replacement file utama SPP secara canonical:

- file pengganti di-stage dan difinalisasi pada private storage;
- dokumen SPP, workflow, artifact current, row pagu, dan alokasi anggaran yang
  relevan dikunci dengan `lockForUpdate()` di dalam transaksi;
- scope payment `LS`, tipe `SPP`, unit, tahun, state dokumen, rekening,
  sub-kegiatan, total nominal, dan sisa pagu diperiksa ulang setelah lock;
- replacement membentuk `before_sign` artifact versi berikutnya dengan
  `parent_artifact_id` menunjuk artifact current lama;
- artifact lama dipertahankan sebagai histori dan `is_current` dipindahkan ke
  versi baru;
- `document.src_name` menyimpan identifier UUID kompatibilitas, sedangkan
  `document_artifacts.original_name` mengambil nama file dari
  `UploadedFile::getClientOriginalName()`;
- draft workflow yang belum dimulai dan belum mempunyai attempt diikat ulang ke
  artifact baru, `lock_version` dinaikkan, dan event
  `source_artifact_replaced` ditambahkan;
- replacement setelah workflow `rejected` membentuk cycle workflow berikutnya;
- workflow aktif/needs-review, dokumen signed/finished, dan artifact current
  yang hilang/ambigu ditolak;
- bila transaksi gagal, staging/final canonical yang belum mempunyai row
  artifact dibersihkan dengan aman.

SPJ, Billing, dan BMD replacement masih memakai folder public. SPP historis
yang belum mempunyai current canonical artifact harus melalui provisioning atau
backfill sebelum file utamanya dapat diganti.

UUID pada `original_name` row hasil provisioning legacy bukan nama asli yang
dibuat oleh storage canonical. Project lama hanya menyimpan nama fisik UUID pada
`document.src_name`; nama file yang dipilih pengguna sudah tidak tersedia untuk
dipulihkan. Provisioning legacy baru sekarang menyimpan `original_name=null`
ketika `src_name` terdeteksi sebagai UUID dan mempertahankan nama tersebut pada
metadata `legacy_stored_name`. Row lama tidak diubah karena artifact bersifat
immutable. Upload SPP langsung dan replacement baru menyimpan nama client yang
sebenarnya, sedangkan `stored_name` tetap UUID agar aman dan unik.

## 11. Status SPM dan SP2D

Route, sidebar, controller, dan Blade SPM/SP2D tersedia karena hasil adaptasi
bertahap dari project lama. Keduanya belum menjadi fokus private artifact
vertical slice ini. Jangan menganggap siap hanya karena halaman atau route
terdaftar. Sebelum mengaktifkan operasional:

- periksa seluruh Form Request yang di-import benar-benar tersedia;
- migrasikan semua file terkait ke canonical private artifact;
- pastikan family reference dan pilihan dokumen pendahulu benar;
- tegakkan scope role/unit/tahun dan state transition;
- hubungkan TTE, delivery, penolakan, verifikasi, serta bank sesuai matrix;
- verifikasi endpoint pilihan BUD dan kontrak penerima posisi.

## 12. Known gaps dan risiko aktif

Urutan prioritas blocker saat snapshot:

1. Migrasikan SPJ, Billing, dan BMD create/update dari public storage.
2. Tutup seluruh URL langsung `public/File_*` untuk LS setelah setiap tipe
   mempunyai delivery resolver/policy canonical.
3. Validasi runtime create/update SPP, termasuk konkurensi dua request pada
   rekening yang sama, rollback, preview, download,
   provisioning, dan
   TTE setelah ada izin test dari pengguna.
4. Review kebutuhan indeks komposit anggaran berdasarkan query plan dan volume
   produksi sebelum membuat migration indeks.
5. Review SPM lalu SP2D sebagai vertical slice terpisah.

Risiko tambahan:

- resolver current sengaja tidak memakai fallback file legacy; backfill
  diperlukan untuk dokumen historis;
- delivery SPP saat ini belum menerapkan watermark/COPY-ID/audit delivery yang
  dirancang untuk sistem e-sign umum;
- migration folder legacy tetap harus copy-verify-activate dan tidak boleh
  langsung dipindah/dihapus;
- perubahan dalam snapshot ini masih berada di working tree dan belum boleh
  dianggap deployed hanya karena route/class tersedia.

## 13. Urutan implementasi berikutnya

1. Migrasikan SPJ, Billing, dan BMD ke private storage dengan pemodelan artifact
   yang eksplisit; Billing saat ini masih nama file pada row SPJ sehingga perlu
   keputusan mapping yang tidak ambigu.
2. Tambahkan resolver dan delivery route untuk artifact pendamping yang sudah
   dimigrasikan, lalu hentikan URL langsung ke folder public untuk tipe itu.
3. Selesaikan delivery policy LS SPP, termasuk watermark/audit bila scope fase
   tersebut sudah diaktifkan.
4. Validasi runtime transaksi/locking anggaran dan workflow TTE SPP LS dari
   current source sampai current signed output.
5. Baru lanjutkan SPM dan SP2D, lalu bank/penyelesaian LS.

## 14. Verifikasi yang sudah dan belum dilakukan

Pada perubahan upload/delivery terakhir sudah dilakukan:

- Laravel Pint untuk file PHP yang berubah;
- pemeriksaan sintaks PHP file terkait;
- `php artisan route:list --path=ls/spp --except-vendor`;
- `git diff --check`;
- query database read-only membuktikan 4/4 artifact memakai checksum
  `storage_disk:file_path`;
- inspeksi statis alur store, persistence, resolver, delivery, TTE, dan
  compatibility writer.
- pemeriksaan sintaks `UpdateSppRequest`, resolve route `ls.spp.update`, Laravel
  Pint, serta `git diff --check` setelah request update ditambahkan.
- pemeriksaan sintaks controller/persistence/provisioning/enum setelah canonical
  replacement update, resolve route update, Laravel Pint, dan `git diff --check`.
- pemeriksaan sintaks controller, resolve seluruh route `ls/spp`, dan Laravel
  Pint setelah locking create disatukan dengan update.

Belum dilakukan:

- test suite/Pest/PHPUnit/browser/smoke test karena invariant project meminta
  konfirmasi eksplisit pengguna;
- request upload SPP runtime dengan data nyata;
- preview/download canonical runtime;
- rollback terkontrol;
- TTE LS end-to-end;
- validasi queue/process manager production;
- migration/backfill dokumen historis.

Jangan menulis bahwa LS SPP siap produksi sampai blocker dan validasi tersebut
selesai.
