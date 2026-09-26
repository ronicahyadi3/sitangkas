# Rencana Implementasi Payment LS

Tanggal rencana awal: **8 September 2026**. Status diperbarui **26 September
2026**.

Status: **sedang diimplementasikan; Tahap 1 sebagian besar tersedia. Vertical
slice LS SPP jalur BP/BPP sudah mempunyai private canonical artifact, lazy
activation, submit gate, dan assignment/activation saat handoff, tetapi belum
lulus TTE end-to-end**.

Dokumen pendamping: [analisis dan bukti kode](PAYMENT_LS_ANALYSIS.md).
Dokumen ini tidak menetapkan schema atau aturan bisnis baru secara final.

Kondisi kode dan blocker terbaru wajib dibaca pada
[PAYMENT_LS_CURRENT_IMPLEMENTATION.md](PAYMENT_LS_CURRENT_IMPLEMENTATION.md).
Keputusan aktif tentang penundaan migrasi/mapping historis wajib dibaca pada
[PAYMENT_LS_MIGRATION_MAPPING_DECISION.md](PAYMENT_LS_MIGRATION_MAPPING_DECISION.md).

## 0. Kemajuan aktual 26 September 2026

- [x] Model bersama `Document`, `DocumentHistory`, anggaran, `BeforeSign`, dan
  `AfterSign` tersedia.
- [x] Route LS SPP/SPM/SP2D dan menu sidebar tersedia.
- [x] Controller Data shared telah diadaptasi ke encrypted ID, posisi aktif,
  scope organisasi, scope tahun, dan transaksi/histori.
- [x] `StoreSppRequest` tersedia dengan authorization, validasi PDF, rekening,
  total nominal, serta sisa pagu.
- [x] Create/upload file utama SPP menghasilkan canonical `before_sign` artifact
  pada private storage tanpa membuat file baru di `public/File_SPP`.
- [x] Resolver current artifact dan route content/download khusus LS SPP tersedia
  pada source code.
- [x] Formula `storage_path_sha256` disatukan melalui helper shared; 4/4 artifact
  lokal cocok dengan formula `storage_disk:file_path`.
- [x] `UpdateSppRequest` tersedia dengan authorization resource, validasi file,
  rekening/nominal, dan sisa pagu yang mengecualikan SPP aktif.
- [x] Replacement SPP pada `update()` membuat artifact version canonical di
  private storage, parent=current, rebind draft workflow, dan revision cycle
  setelah reject.
- [x] Samakan penguncian dan pemeriksaan ulang pagu di dalam transaksi
  `SPP::store()` dengan protokol yang sudah digunakan `SPP::update()`.
- [x] Provision artifact/workflow/step SPP langsung dari `store()` secara
  transaksional dan idempotent.
- [x] Aktifkan workflow dan step BP/BPP secara lazy saat signer nyata membuka
  signing session; acting Admin Super tetap ditolak untuk TTE.
- [x] Tambahkan submit gate yang mewajibkan proof TTE canonical sebelum
  `document.submit`, `assigned_to`, atau `users_to` berubah.
- [x] Assign dan aktifkan PPTK pada handoff BP/BPP, lalu PA/KPA pada handoff
  PPTK. TTE sukses tidak lagi mengaktifkan step berikutnya secara otomatis.
- [x] Pertahankan projector `document.status` dan event `document_process.TTE`
  transaksional serta idempotent.
- [ ] Jalankan vertical slice BP -> PPTK -> PA sampai handoff final secara
  permanen dan terkontrol.
- [x] Migrasikan create/replacement SPJ ke canonical private artifact dan
  tambahkan authenticated content/download route tanpa mengubah pola row
  `document`.
- [x] Migrasikan create/replacement BMD ke canonical private artifact dan
  tambahkan authenticated content/download route tanpa mengubah pola row
  `document`.
- [x] Migrasikan create/replacement Billing dari public storage melalui mapping attachment
  additive tanpa row atau `src_type` baru.
- [ ] Validasi runtime upload, rollback, delivery, provisioning, dan TTE LS.
- [ ] Review dan selesaikan SPM, SP2D, bank, serta penyelesaian LS.
- [x] Keputusan hold migrasi/mapping massal dan backfill historis sudah dicatat;
  jangan mulai sebelum Payment LS dapat digunakan dan pengguna memberi
  instruksi eksplisit.

## 1. Arah pekerjaan yang diminta pengguna

Implementasikan LS terlebih dahulu. Payment lain dilanjutkan setelah LS selesai.
Pertahankan perilaku bisnis yang masih sesuai, lalu adaptasikan ke konteks akun,
posisi, organisasi, tahun, dan UI project sekarang.

Scope LS mencakup dependensi bersama yang benar-benar diperlukan: anggaran,
dokumen, detail/history, verifikasi, penolakan, file, TTE, pilihan penerima, dan
penyelesaian bank khusus LS. Ini tidak berarti seluruh payment lain harus ikut
diaktifkan atau direfaktor.

## 2. Titik mulai untuk agent berikutnya

1. Baca `AGENTS.md`, [invariant project](../00-ai-agent/PROJECT_INVARIANTS.md),
   [kondisi implementasi aktual](PAYMENT_LS_CURRENT_IMPLEMENTATION.md), lalu
   [keputusan hold migrasi/mapping](PAYMENT_LS_MIGRATION_MAPPING_DECISION.md)
   dan [hasil analisis awal](PAYMENT_LS_ANALYSIS.md).
2. Periksa `git status` dan perubahan terkini tanpa me-reset pekerjaan pengguna.
3. Cocokkan kembali dependensi, route, schema database aktif, dan layanan konteks
   dengan snapshot; jangan mengulang audit seluruh payment bila tidak berubah.
4. Bila permintaan selanjutnya baru berupa pembahasan/desain, lanjutkan desain.
   Bila pengguna sudah meminta implementasi, kerjakan bagian yang terotorisasi;
   jangan memperlakukan rekomendasi dalam dokumen ini sebagai requirement baru
   untuk meminta ulang izin yang sudah diberikan.
5. Sebelum kode PHP, aktifkan skill Laravel dan cari dokumentasi versi project
   melalui `search-docs`. Sebelum model/migration, inspeksi schema dan baca
   [readiness migration](../06-migrations/FRESH_INSTALL_READINESS.md).

Fondasi awal, create/upload SPP, kontrak `storage_path_sha256`, request update,
replacement canonical SPP, locking pagu, lazy activation, submit gate, serta
assignment signer pada handoff sudah dikerjakan. Direct private create,
replacement, dan delivery SPJ/BMD/Billing juga sudah tersedia dengan cutover per
row untuk data historis. Billing memakai artifact type `attachment` tanpa row
atau `src_type` baru. Hasil berikutnya adalah menuntaskan kesiapan runtime dan
backend visible placement/controlled signing LS SPP. Backfill attachment dan
migrasi historis ditunda sampai pengguna memberi instruksi eksplisit setelah
Payment LS dapat digunakan.

## 3. Keputusan yang belum ditetapkan

| Topik | Yang sudah diketahui | Yang perlu dipastikan sebelum pekerjaan terkait |
|---|---|---|
| Data awal | Tabel operasional `document`, `document_process`, anggaran, `before_signs`, dan `after_signs` sudah disediakan; model bersama tersedia | Apakah histori LS juga diimpor; periode, sumber, dan high-watermark bila dibutuhkan |
| Struktur dokumen | `document` tetap projection bersama dan `document_artifacts` menyimpan version chain; keluarga LS tetap beranchor SPP; SPJ tetap row `src_type=SPJ`; Billing tetap kolom row SPJ dan dimapping sebagai artifact type `attachment` | Constraint kardinalitas keluarga dan strategi backfill attachment Billing historis |
| Sumber anggaran | SPP wajib mempunyai rekening/pagu dan rincian penggunaan | Sumber resmi, tahun/unit, cara pemuatan, serta aturan perubahan pagu |
| Scope organisasi | `skpd_id` telah dihapus; master memakai instansi/parent/jenis/kode | Pemetaan kewenangan PA/KPA/PPK terhadap sekolah, kesehatan, Setda, kecamatan, dan unit terkait |
| Tahap/revisi | Legacy memakai CSV dan BP/BPP mengajukan lebih dari sekali | Matriks action-stage, kapan revisi/tolak/hapus boleh, serta dampak terhadap persetujuan dan dokumen turunan |
| Identitas posisi | Lookup dan `users_to` lama mengacu posisi; acting overlay mempertahankan ID posisi Admin Super | Kontrak identitas setiap kolom dan perlakuan pergantian pejabat/posisi |
| Tahun historis | Permission melekat pada posisi dan tidak memperluas role | Penegakan pada LS, audit used/denied, tahun mendatang, dan penerapan pengecualian Admin Super |
| TTE | Backend canonical, queue, artifact, attempt, verify, compatibility writer, lazy activation, submit gate, dan assignment handoff LS SPP tersedia | Visible placement, worker/shared cache production, dan acceptance runtime end-to-end |
| Acting dan tanda tangan | Effective context dapat berbeda dari aktor nyata | Jangan menganggap acting otomatis mengizinkan tanda tangan atas identitas orang lain; cocokkan kebijakan dan identitas provider |
| Bank dan billing | Legacy menandai selesai dan memperbarui keluarga LS | Peran bank, arti selesai, aturan penolakan/revisi billing, dan batas integrasi eksternal yang diperlukan |

Gunakan docs/kode dan konteks pengguna untuk menyelesaikan keputusan rutin.
Tanyakan hanya keputusan bisnis yang masih berdampak dan tidak dapat disimpulkan;
lanjutkan pekerjaan independen yang sudah jelas. Jangan menebak sumber anggaran,
credential TTE, aturan penanda tangan, atau data historis yang akan diimpor.

## 4. Rekomendasi desain minimum

### Dokumen dan keluarga LS

- Identitas dokumen, payment type LS, jenis dokumen, tahun anggaran eksplisit,
  lingkup instansi/unit, nomor, uraian, nominal presisi tetap, dan tahap saat ini.
- Relasi keluarga yang tidak ambigu; legacy menyimpan reference SPP juga pada
  SP2D. Mapping tidak boleh disimpulkan dari nama `selected_spm` di form saja.
- Identitas pembuat/editor, posisi penerima, dan histori transisi yang terpisah.
- Lampiran asli, hasil TTE, dan revisi dapat ditelusuri tanpa menimpa bukti lama.
- Rincian rekening/anggaran dengan total server-side dan constraint yang sesuai.

Ini daftar kebutuhan data, bukan instruksi membuat semua tabel dengan nama baru.
Tetapkan schema final setelah inspeksi dan keputusan domain; jangan mengubah
master auth/organisasi hanya untuk mempertahankan query legacy.

### Authorization dan workflow

- Gunakan `CurrentUserContext`/`ActivePositionService` yang sudah ada.
- Scope operasional menggunakan effective jabatan/instansi/unit, dengan
  penerima posisi yang tepat. Audit tetap merekam user/posisi nyata dan acting.
- Gunakan kode jabatan stabil dan satu kebijakan akses per action/resource.
- Tahun resource dan permission dicek sebelum setiap mutasi; tahun session hanya
  pilihan konteks, bukan bukti izin.
- Pisahkan controller HTTP, Form Request, kebijakan akses, action/service bisnis,
  query daftar, dan presenter sesuai struktur project yang sudah digunakan.
- Pusatkan aturan tahap, dokumen wajib, tanda tangan, penolakan, dan revisi.
  View menerima kemampuan aksi dari aturan yang sama.
- Mutasi keluarga dokumen, penggunaan anggaran, dan histori dilakukan secara
  atomik. Lock/constraint melindungi dari submit ganda dan pengajuan bersamaan.
- Pertahankan response 403/404/422 yang jelas; jangan ubah semua penolakan bisnis
  menjadi error 500.

### File dan integrasi

- Storage privat dengan download/preview terotorisasi; validasi PDF dan ukuran.
- Simpan versi TTE terpisah, jangan mewariskan tanda tangan ke konten revisi.
- Tangani cleanup file baru pada kegagalan tanpa menghapus arsip sah.
- Gunakan konfigurasi integrasi project; jangan menyalin secret project lama.
- Pertahankan layout/shared komponen yang kompatibel. Selaraskan named route,
  HTTP method, JSON, error handling, dan refresh tabel bersama backend.
- Jangan melonggarkan akses Management Users untuk menyediakan pilihan PPTK/BUD;
  sediakan scope lookup yang cocok dengan payment.

## 5. Tahapan implementasi dan hasil yang harus dapat ditinjau

### Tahap 1 — Fondasi LS

Hasil:

- Pemetaan schema dan migration untuk dokumen, keluarga, rincian anggaran,
  lampiran/versi, dan audit sesuai keputusan final.
- Sumber anggaran siap untuk unit/tahun target.
- Model, Form Request, kebijakan akses, dan layanan konteks/identitas terhubung.
- Aturan tahun, logging LS, storage, dan kontrak endpoint disepakati dalam kode.

Jangan mendaftarkan endpoint mutasi yang belum mempunyai pemeriksaan akses.
Fresh install dan kompatibilitas tipe FK harus diperiksa, bukan hanya sintaks PHP.

### Tahap 2 — SPP end-to-end

Hasil:

- Daftar/detail sesuai konteks, tambah/revisi SPP, SPJ/BMD/billing sesuai kebutuhan.
- Rekening sesuai unit/tahun, total benar, dan pagu aman terhadap konkurensi.
- Penugasan PPTK aktif yang benar; BP/BPP -> PPTK -> PA/KPA -> BP/BPP -> PPK-SKPD
  mengikuti matriks bisnis yang sudah dipastikan.
- Prasyarat TTE, verifikasi, penolakan, revisi, file, dan histori tersedia sejauh
  diperlukan tahap SPP. Jangan menyebut tahap ini selesai jika masih hanya CRUD.

### Tahap 3 — SPM end-to-end

Hasil:

- Hanya SPP yang memenuhi syarat dapat dipilih.
- Paket SP/SPM/SPTJM/SP Pengajuan tersimpan dan diproses konsisten.
- Giliran PPK-SKPD, PA/KPA, dan Verifikator BUD ditegakkan backend.
- Dokumen wajib, TTE, revisi, dan pencegahan paket ganda ditangani.

### Tahap 4 — SP2D dan penyelesaian LS

Hasil:

- Hanya pengajuan sah/terverifikasi yang dapat dibuatkan SP2D.
- Penerima BUD/Kuasa BUD berasal dari posisi valid dan lingkup yang benar.
- TTE dan proses bank khusus LS berfungsi sesuai kontrak yang dipastikan.
- Penolakan billing/SP2D dan penandaan selesai memperbarui keluarga yang tepat
  tanpa memengaruhi payment lain.

SP2D tersimpan atau halaman bank tampil belum cukup untuk menyatakan pencairan
selesai. Gunakan makna status sesuai proses bisnis yang sudah dikonfirmasi.

### Tahap 5 — Validasi dan pembaruan dokumentasi

Hasil:

- Validasi perilaku yang benar-benar dilakukan dicatat beserta keterbatasannya.
- Temuan LS-01 sampai LS-08 pada dokumen analisis ditandai resolved atau masih
  terbuka, dengan referensi implementasi dan bukti pemeriksaan.
- Snapshot kondisi terbaru, schema final, route, dan keputusan bisnis diperbarui.
- Baru setelah LS stabil, tentukan komponen yang dipakai payment berikutnya.

## 6. Matriks validasi yang direkomendasikan

Matriks ini adalah rencana, **bukan test yang sudah ditulis atau dijalankan**.
Ikuti aturan konfirmasi test di instruksi project dan otorisasi percakapan yang
berlaku; jangan meminta ulang bila izin tersebut sudah diberikan.

| Area | Skenario minimum |
|---|---|
| Hak akses | Setiap role yang relevan; role tidak dikenal ditolak; Auditor/Pimpinan tidak dapat mutasi |
| Organisasi | ID dokumen unit/instansi lain ditolak pada daftar, detail, aksi, histori, dan file |
| Penerima | Dua PPTK dalam unit sama; hanya posisi tujuan yang sah memproses penugasan |
| Pergantian konteks | Ganti posisi, posisi tidak aktif, dan Admin Super acting tanpa salah memakai ID posisi nyata sebagai penerima |
| Tahun | Tahun berjalan, historis tanpa izin, izin aktif, revoked/expired, dan tahun mendatang |
| Workflow | Alur normal, lompat tahap, dokumen belum ditandatangani, dokumen wajib kurang, submit ulang dan submit bersamaan |
| Revisi | Penolakan, edit setelah submit/TTE/verifikasi/selesai, versi file lama, dan dampak pada dokumen turunan |
| Anggaran | Total tidak cocok, ID rekening duplikat/tidak sah, rekening lintas unit/tahun, pagu habis, dan dua request memakai sisa pagu yang sama |
| File/TTE | PDF tidak sah/terlalu besar, unduh tanpa izin, upload gagal, rollback, TTE gagal/berulang sesuai kontrak integrasi |
| Bank | Pemilihan dokumen sah, billing ditolak, status selesai berulang, dan propagasi hanya ke keluarga LS yang benar |
| UI | Named route tersedia, opsi PPTK/BUD bagi pelaku payment, error validasi, refresh tabel, modal, dan mode baca |
| Schema | Fresh install, foreign key, indeks, uniqueness, soft delete, dan import jika termasuk scope |

## 7. Kriteria LS selesai

- Alur yang disepakati berjalan dari SPP sampai penyelesaian SP2D/bank, termasuk
  penolakan dan revisi; tidak berhenti pada keberhasilan CRUD.
- Tidak ada dependensi class/route/directive yang belum tersedia pada alur aktif.
- Akses role, organisasi, posisi penerima, dan tahun konsisten di backend/UI/file.
- Nominal dan penggunaan anggaran konsisten dan terlindungi dari proses bersamaan.
- Bukti file/TTE dan audit dapat ditelusuri ke dokumen, aktor, posisi, dan versi.
- Pemeriksaan yang diwajibkan dan terotorisasi selesai; keterbatasan yang masih
  tersisa dinyatakan jelas, bukan dianggap lolos tanpa pengujian.
- Dokumentasi membedakan hasil implementasi aktual dari rekomendasi yang belum
  dikerjakan. Payment lain tetap menjadi pekerjaan setelah LS selesai.

## 8. Status pekerjaan terhadap rencana

- [x] Analisis controller/model/view LS dan dependensi shared.
- [x] Perbandingan dengan payment lain dan project referensi lama.
- [x] Pemeriksaan route dan schema aktif secara read-only.
- [x] Dokumentasi temuan, rekomendasi, dan keputusan terbuka.
- [ ] Keputusan schema dan matriks bisnis LS final.
- [x] Fondasi model dokumen/anggaran bersama tersedia; review konkurensi dan
  workflow masih terbuka.
- [x] Route, menu, dan request create SPP tersedia.
- [x] Upload utama SPP memakai private canonical artifact.
- [x] Perbaikan blocker delivery hash.
- [ ] Validasi runtime delivery.
- [x] Replacement file utama SPP pada update memakai private canonical artifact.
- [x] Lock dan pemeriksaan ulang pagu pada create SPP di dalam transaksi.
- [x] Lazy activation first signer BP/BPP melalui signing session.
- [x] Submit gate canonical dengan fallback hanya untuk dokumen murni legacy.
- [x] Assignment dan activation PPTK/PA/KPA pada handoff secara transaksional.
- [x] Next step tetap pending setelah TTE sampai handoff dilakukan.
- [ ] Visible placement QR/footer backend.
- [ ] Controlled signing/handoff LS SPP end-to-end.
- [x] Private artifact create/replacement dan delivery SPJ LS.
- [x] Private artifact create/replacement dan delivery BMD LS.
- [x] Private artifact dan delivery Billing melalui mapping attachment additive.
- [ ] Implementasi SPP lengkap.
- [ ] Implementasi SPM lengkap.
- [ ] Implementasi SP2D, TTE, billing, dan penyelesaian bank khusus LS.
- [ ] Validasi perilaku sesuai otorisasi pengujian.
- [ ] Evaluasi kesiapan payment berikutnya.
