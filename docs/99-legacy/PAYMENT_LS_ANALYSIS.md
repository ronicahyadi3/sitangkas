# Analisis Migrasi Payment LS

Tanggal snapshot: **8 September 2026**.

Status: **analisis selesai; implementasi LS belum dilakukan**.

Dokumen ini menyimpan temuan pemeriksaan source code, route, dan struktur
database agar AI agent berikutnya dapat melanjutkan pekerjaan tanpa mengulang
seluruh penelusuran. Nomor baris merujuk snapshot analisis dan dapat bergeser.
Cari juga nama method yang disebut sebelum mengubah kode.

## 1. Permintaan pengguna dan batas pekerjaan

- Pengguna ingin memindahkan fitur payment project lama ke project sekarang.
- Prioritas pertama adalah **LS**, kemudian payment lain setelah LS selesai.
- Permintaan awal adalah analisis dan rekomendasi; permintaan berikutnya adalah
  menyimpan hasil tersebut ke dokumentasi Markdown.
- Dokumentasi ini tidak berarti schema usulan, perubahan alur bisnis, import
  data historis, atau integrasi eksternal sudah diputuskan/dilaksanakan.
- Pada tahap analisis tidak ada perubahan kode aplikasi, migration, import data,
  atau eksekusi test suite/browser test.

Project saat analisis mempunyai banyak perubahan pengguna yang belum di-commit,
termasuk auth, payment, controller Data, route, view, dan docs. Jangan menganggap
perubahan tersebut hasil implementasi LS oleh agent ini; jangan menimpanya.

## 2. Cara membaca dan sumber kebenaran

1. Baca [invariant project](../00-ai-agent/PROJECT_INVARIANTS.md).
2. Baca dokumen ini untuk fakta, dependensi, dan temuan.
3. Baca [rencana implementasi LS](PAYMENT_LS_IMPLEMENTATION_PLAN.md) untuk urutan
   pekerjaan, keputusan terbuka, dan kriteria selesai.
4. Jika menyentuh konteks atau otorisasi, baca:
   - [keputusan auth context](../01-authentication/AUTH_CONTEXT_DECISIONS.md);
   - [implementasi auth context](../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md);
   - [konteks posisi pengguna](../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md);
   - [aturan izin tahun historis](../04-year-permissions/AI_AGENT_YEAR_PERMISSION_CONTEXT.md).
5. Jika menyentuh organisasi atau database, baca:
   - [konteks organisasi](../02-master-data/AI_AGENT_MASTER_ORGANIZATION_CONTEXT.md);
   - [mapping legacy](LEGACY_MAPPING.md);
   - [readiness migration](../06-migrations/FRESH_INSTALL_READINESS.md).

Referensi project lama: `C:\Apache24\htdocs\sitangkas`, sesuai
[OLD_PROJECT_REFERENCE.md](OLD_PROJECT_REFERENCE.md). Baca sebagai referensi
perilaku; jangan mengubah project lama atau menyalin credential/config sensitif.

Fakta dalam dokumen ini adalah snapshot, bukan jaminan keadaan repo berikutnya.
Rekomendasi diberi label sebagai rekomendasi, bukan keputusan arsitektur final.
Alur yang ditemukan dari kode belum dikonfirmasi melalui pengujian runtime atau
validasi ulang aturan bisnis oleh pengguna.

## 3. Kondisi integrasi saat analisis

| Area | Hasil pemeriksaan |
|---|---|
| Controller dan view Payment | Seluruh 30 controller dan 30 view yang dibandingkan identik byte-for-byte dengan referensi project lama |
| Controller `Data/*` | Tujuh file `Detail`, `DetailTbp`, `History`, `Verify`, `Denied`, `Delete`, `Rekening` juga identik dengan referensi lama |
| Route dan menu | `routes/web.php` belum mendaftarkan LS/payment maupun endpoint dokumen/TTE/bank; `config/sidebar_workspaces.php` belum mempunyai menu payment |
| Database aktif | Schema `sitangkas` berisi 22 tabel auth/master/posisi/permission/realtime/infrastruktur; belum ada `document`, histori payment, atau anggaran payment |
| Migration payment | Belum ditemukan pada `database/migrations` |
| Fondasi auth | `CurrentUserContext`, `ActivePositionService`, dan `YearAccessService` sudah tersedia |
| Enkripsi ID | `App\Support\EncryptedId` sudah tersedia; bukan pengganti authorization |
| DataTables | `yajra/laravel-datatables-oracle` sudah ada pada `composer.json` dengan constraint `^13.0` |
| UI | Layout dan aset Bootstrap/Argon, jQuery, DataTables, Select2, SweetAlert, AutoNumeric tersedia |
| Logging | Controller LS memakai channel `payment_ls`; channel itu belum ditemukan di `config/logging.php` |

Kesimpulan: payment lain berguna sebagai referensi kode/alur, tetapi belum
merupakan contoh modul yang selesai diintegrasikan dengan project baru.

### Bukti database dan batas interpretasi

Analisis menggunakan pemeriksaan read-only melalui Artisan karena tool Laravel
Boost tidak tersedia pada daftar tool sesi tersebut. `db:show --json` kemudian
difilter ke schema database aktif, dan `db:table sitangkas.document --json`
menghasilkan bahwa tabel tidak ada.

Pada lingkungan ini `db:show` turut menampilkan tabel dari schema lain, dan
`db:table document` tanpa prefix sempat menemukan `sitangkas_legacy.document`.
**Jangan menganggap tabel di schema legacy/testing sebagai tabel project aktif.**
Gunakan schema eksplisit dan hindari menampilkan konfigurasi koneksi/credential
saat memeriksa ulang. Isi transaksi pengguna tidak dibaca untuk analisis ini.

## 4. Peta alur LS dari kode lama

| Tahap | Perilaku yang ditemukan | Referensi pada project sekarang |
|---|---|---|
| Buat SPP | Dokumen SPP, SPJ, billing opsional, BMD kondisional, rincian rekening/anggaran | `app/Http/Controllers/Payment/LS/SPP.php:681`, `store()` |
| Penugasan awal | BP/BPP mengirim ke posisi PPTK terpilih | `SPP.php:1335`, `submit_pptk()` |
| Submit SPP | PPTK -> PA/KPA -> BP/BPP -> PPK-SKPD | `SPP.php:1262`, `submit()` |
| Paket SPM | SP, SPM, SPTJM, SP_PENGAJUAN terkait SPP | `app/Http/Controllers/Payment/LS/SPM.php:527`, `store()` |
| Submit SPM | PPK-SKPD -> PA atau KPA berdasarkan lingkup unit -> Verifikator BUD | `SPM.php:1041`, `submit()` |
| SP2D | Form memilih SPM terverifikasi yang belum dipakai; record SP2D menyimpan reference ke SPP | `app/Http/Controllers/Payment/LS/SP2D.php:447`, `:746`, `:773` |
| TTE/verifikasi/tolak | Mengandalkan komponen dan backend lintas modul | `app/Http/Controllers/Data/*`, shared Blade dan aset e-sign |
| Bank/selesai | Bank menangani SP2D bertanda tangan yang memiliki penerima; penyelesaian memperbarui dokumen LS terkait | Project lama `app/Http/Controllers/Bank/SP2D.php:522`, `:705` |

Catatan penting:

- SPP merupakan anchor keluarga dokumen. Jangan mengasumsikan setiap
  `reference_id` menunjuk tahap tepat sebelumnya hanya karena dropdown memilih SPM.
- Pengulangan submit BP/BPP adalah bagian alur lama. Menghapus nilai duplikat
  dalam CSV `submit` tanpa memahami putaran proses dapat mengubah bisnis.
- `status`, `submit`, `assigned_to`, `verify`, `rejected_by`, dan `finished_at`
  mempunyai fungsi berbeda; jangan digabung menjadi satu status tanpa pemetaan.
- SP2D tersimpan belum berarti pencairan selesai. Backend TTE dan bank belum
  tersedia pada project baru saat analisis.

## 5. Dependensi yang belum tersedia

| Dependensi | Penggunaan |
|---|---|
| `App\Models\Document` | Persistensi utama seluruh dokumen payment |
| `App\Models\DocumentHistory` dan `App\Services\Document\DocumentHistoryService` | Histori upload, edit, submit, verifikasi, penolakan, TTE, hapus |
| `App\Models\AnggaranKegiatanTemp` | Sumber rekening/pagu untuk form dan validasi SPP |
| `App\Models\AnggaranKegiatan` | Rincian penggunaan/snapshot anggaran SPP |
| `App\Http\Requests\LS\StoreSppRequest`, `UpdateSppRequest` | Validasi/otorisasi SPP |
| `App\Http\Requests\LS\StoreSpmRequest`, `UpdateSpmRequest` | Validasi/otorisasi paket SPM |
| `App\Http\Requests\LS\StoreSp2dRequest`, `UpdateSp2dRequest` | Validasi/otorisasi SP2D |
| Backend e-sign/PDF/bank | TTE, validasi file, pembacaan PDF, billing dan penyelesaian pencairan |
| Schema dokumen, histori, anggaran | Fondasi persistensi seluruh dependensi tersebut |

Import yang menunjukkan ketergantungan: `SPP.php:8-18` serta import pada SPM/SP2D.
Class `App\Models\Payment\LS` sudah ada, tetapi merupakan helper query statis,
bukan pengganti model Eloquent `Document`.

Anggaran adalah prasyarat LS. `SPP::store()` pada `:656` membaca
`AnggaranKegiatanTemp::tahunAktif()`, lalu `:717-752` menulis rincian ke
`AnggaranKegiatan`. Form Request lama mewajibkan minimal satu rekening dan
memeriksa pagu. Sumber/pemuatan anggaran baru belum diputuskan.

## 6. Temuan backend berdasarkan prioritas

Risiko berikut berlaku **ketika kode legacy diaktifkan**. Karena endpoint payment
belum didaftarkan saat analisis, ini bukan klaim eksploitasi endpoint aktif.

### LS-01 — Update SPP belum membatasi resource yang boleh diubah

`SPP::updateDocumentData()` pada `:59` memperbarui berdasarkan ID saja.
`SPP::update()` pada `:946-967` menimpa unit, pengunggah, dan state pengajuan tanpa
pemeriksaan lengkap jenis LS/SPP, lingkup organisasi, tahun, atau tahap revisi.

Form Request yang belum ada tidak boleh diasumsikan menutup masalah ini.
`UpdateSppRequest` project lama pada `:16-18` mengembalikan `authorize = true`;
query `:73-78` hanya membatasi ID, jenis SPP, dan soft delete.

Rekomendasi: otorisasi resource sebelum mutasi; jangan mengubah kepemilikan/unit
atau identitas pembuat sebagai efek samping edit. Catat aktor editor terpisah.

### LS-02 — Scope submit dan penerima tugas belum konsisten

- `SPP::canAccessForSubmit()` pada `:1512-1535` dan versi SPM pada `:257-280`
  mempunyai fallback izin berdasarkan kesamaan jabatan `assigned_to` meskipun
  unit berbeda.
- Query `SPP::submit()` pada `:1167-1173` belum membatasi `payment_type = LS` dan
  tahun resource; select juga tidak mengambil `users_to` atau `status`.
- `SPP::submit_pptk()` pada `:1346` decode penerima tanpa memastikan posisi PPTK
  aktif dan lingkup penerima sah.

Rekomendasi: periksa jenis payment, jenis dokumen, organisasi, posisi penerima,
role, tahun, dan state pada server. Scope organisasi tidak boleh dilewati hanya
karena dua orang memiliki jabatan yang sama.

### LS-03 — Transisi dan revisi belum menjaga prasyarat secara lengkap

`SPM::submit()` pada `:1015-1075` memeriksa akses, penolakan, dan submit berulang,
tetapi belum memastikan giliran `assigned_to`, tanda tangan, kelengkapan keluarga
dokumen, dan tahap pendahulu. PA/KPA dalam lingkup yang bisa diakses berpotensi
memajukan dokumen terlalu awal.

Update SPP `:946-965`, SPM `:704-711`, dan SP2D `:613-667` mereset sebagian state,
sementara tanda tangan hanya direset bila file diganti. Aturan terhadap
`verify`/`finished_at` dan perubahan data yang sudah ditandatangani belum utuh.
SPM `:739-741` juga melewati jenis dokumen yang belum ada, sehingga file opsional
baru saat edit dapat terabaikan.

Rekomendasi: satu aturan transisi LS untuk semua endpoint dan kemampuan UI;
definisikan revisi/versi, pembatalan persetujuan terkait, larangan perubahan pada
tahap final, dan penanganan penolakan. Jangan mempertahankan bukti TTE lama untuk
isi dokumen yang sudah berubah tanpa aturan versi yang benar.

### LS-04 — Daftar, detail, dan mutasi perlu scope yang sama

`app/Models/Payment/LS.php:277-299` (`applySpmJsonScope`) membatasi sebagian role,
tetapi belum menerapkan scope unit untuk PPTK/BP/BPP pada method tersebut.
`SP2D.php:521-525` mengambil detail dengan ID dan tahun; daftar `:228-256` hanya
membatasi penerima secara khusus untuk BUD/Kuasa BUD.

Rekomendasi: terapkan kebijakan akses secara konsisten pada JSON, form pilihan,
detail, histori, file, dan mutasi. Route/middleware saja tidak menggantikan scope
per dokumen; default tolak role yang belum mempunyai aturan eksplisit.

### LS-05 — Kontrak organisasi dan acting context berubah

Referensi `unit_kerjas.skpd_id` terdapat pada model LS `:65`, SPP `:342`/`:1528`,
dan SPM `:273`/`:860`/`:1043`. Kolom ini sengaja dihapus dalam master baru.
Master kini memakai `instansi_id`, `parent_id`, `jenis`, dan `kode`.
Mapping `skpd_id` tidak otomatis sama dengan `parent_id`; baca mapping legacy dan
aturan organisasi terutama sekolah, fasilitas kesehatan, Setda, dan kecamatan.

`SPP.php:289` membaca `actingPptkUser`; `SP2D.php:221` membaca `actingBudUser`.
`CurrentUserContext.php:415`/`:419` menyediakan relasi
`actingPptkUserPosition`/`actingBudUserPosition`. Tidak ditemukan alias lama pada
`UserPosition`. Filter dapat fallback ke ID posisi asli Admin Super dan salah
memilih daftar penugasan.

Rekomendasi: pertahankan adaptor `ActivePositionService::get()` yang sudah benar;
perbaiki konsumen. Scope operasional memakai effective context. Audit menyimpan
aktor nyata dan snapshot konteks efektif. ID overlay tetap ID posisi nyata Admin
Super, bukan bukti identitas PPTK/BUD yang sedang diperankan.

Aturan role numeric perlu dipetakan ke `jabatan.kode`. ID legacy yang dipertahankan
seeder hanya membantu mapping, bukan kontrak bisnis permanen.

### LS-06 — Tahun anggaran dan permission belum ditegakkan dalam mutasi LS

`LS::rootQueryAlias()` pada `:12-20` memfilter tahun `created_at`.
`SPP.php:1025`/`:1039` memakai `session('tahun_aktif')` untuk rincian anggaran.
Belum ditemukan penggunaan `YearAccessService` pada mutasi controller LS.

Layanan tahun sudah ada, tetapi perlu diperhatikan sebelum reuse:

- `YearAccessService::canWrite()` pada `:52` menerima semua tahun yang bukan
  historis, termasuk tahun mendatang; docs tahun historis menetapkan default
  penolakan tahun mendatang. Jangan mengandalkan service ini sendirian.
- Layanan mempunyai pengecualian original Admin Super; evaluasi bersama keputusan
  auth/acting, jangan diam-diam menghapus atau memperluas pengecualian itu.
- `recordHistoricalWriteUsage()` pada `:106-126` menaikkan penghitung penggunaan,
  tetapi belum menulis event `used` pada metode tersebut.

Rekomendasi: tahun anggaran eksplisit pada resource, scope tahun server-side,
permission berbasis posisi, dan audit penggunaan/penolakan sesuai aturan domain.
Permission tahun tidak boleh memperluas action role dasar.

### LS-07 — Anggaran dan pengajuan bersamaan perlu integritas transaksi

`SPP.php:652-659` menggunakan map ID rekening dan `float`; `:717-720` melewati
rekening yang tidak ditemukan. Controller belum memastikan total rincian sama
dengan nominal dokumen, rekening sesuai unit/tahun, atau pagu dicek dalam transaksi.
Request lama memang mengecek pagu, tetapi sebelum transaksi.

Rekomendasi: validasi rekening distinct dan lingkupnya; nilai uang presisi tetap;
total dihitung/diperiksa di server; kunci sumber pagu yang sama saat memeriksa dan
mencatat penggunaan. Validasi input saja tidak mencegah dua request serentak.
Pemilihan SPM/SP2D yang belum digunakan juga perlu constraint/lock sesuai
kardinalitas bisnis, bukan hanya filter dropdown.

### LS-08 — Penyimpanan file dan audit perlu adaptasi

`storeFile()` pada SPP `:26`, SPM `:22`, dan SP2D `:22` menulis ke
`public_path('/File_*')`. Tombol membuka file bertanda tangan dengan URL publik.

Rekomendasi: storage privat, akses file terotorisasi, validasi PDF/ukuran, dan
versi terpisah untuk file asli/hasil TTE. Cleanup file baru saat rollback sudah
ada pada controller dan merupakan perilaku yang dapat dipertahankan.

Histori payment perlu mencatat aktor nyata, posisi, konteks efektif, dokumen,
perubahan tahap, dan alasan yang relevan. Audit payment tidak boleh dianggap
sudah tercakup oleh `login_events` atau audit Management Users. Jangan menyimpan
passphrase, credential TTE, token, atau payload rahasia.

## 7. Temuan view dan endpoint pendukung

### Layout yang dapat dipertahankan

LS memakai `layouts.app` dengan section `content` dan `additionals`, sesuai layout
saat ini. Aset tersedia di `resources/views/layouts/app.blade.php:175-195`, dan
section tambahan dirender pada `:256`. Tidak perlu mengganti framework UI untuk
memulai implementasi LS.

### Route yang dievaluasi shared Blade

| Referensi di bawah `resources/views/components/` | Named route yang dibutuhkan |
|---|---|
| `informations/detail.blade.php:55` | `document.detail` |
| `informations/history.blade.php:56` | `document.history` |
| `informations/pdfview.blade.php:46` | `esign.validate` |
| `confirmations/denied.blade.php:149` | `document.denied` |
| `confirmations/delete.blade.php:57` | `document.delete` |
| `confirmations/verify.blade.php:58` | `document.verify` |
| `form/rekeningSubKegiatan.blade.php:578`, `:710`, `:770` | `document.sub_kegiatan`, `document.rekening`, `document.rekening.detail` |

Menambahkan `ls.*` saja belum cukup: pemanggilan `route()` terhadap nama yang
belum terdaftar dapat menggagalkan render halaman sebelum tombol digunakan.

### URL dan kontrak tambahan

| Pemanggil | Endpoint/kontrak lama |
|---|---|
| `components/confirmations/submitPPTK.blade.php:80` | `/users/pptk`, array objek `id`, `nama` |
| `Payment/LS/sp2d.blade.php:311` | `/users/bud`, array objek `id`, `nama` |
| `public/assets/js/additionals.js:311` | `/pdf/read` |
| `components/form/editBilling.blade.php:65` | `/olah_dokumen/update-billing` |
| `public/assets/js/pdf/bundle.js:3175`, `:4122` | `/esign/validate`, `/esign/sign` |

PPTK/BUD lookup saat ini berada di grup `users` dengan middleware
`user.management` (`routes/web.php:120-129`). Service akses Management Users
mengizinkan Admin Super/PA/KPA, sehingga tidak cocok untuk semua aktor payment.
Rekomendasi: endpoint pilihan penerima dengan scope payment tersendiri; jangan
melonggarkan seluruh modul Management Users demi dropdown payment.

Nilai `id` lookup lama adalah ID posisi terenkripsi, bukan selalu ID akun.
Validasi kontrak `users_to`, pembuat dokumen, penerima, dan penanda tangan sebelum
merancang foreign key/import.

### Otorisasi UI dan bug komponen

- `@accessJabatan` dipakai SPP `:149`, SPM `:204`, SP2D `:203`, dan shared
  `informations/Table.blade.php:132`; pendaftarannya belum ditemukan dalam
  `app`, `bootstrap`, atau `config`.
- View LS membedakan auditor menggunakan ID `13`. Shared Table memakai layanan
  tahun untuk tombol Tambah, tetapi modal aksi masih tersedia bagi non-auditor.
- Definisi `window.readOnlyUI` belum ditemukan pada source yang diperiksa;
  `window.readOnlyUI?.guardAction()` dapat menjadi no-op. Backend tetap wajib
  memeriksa izin.
- `form/editBilling.blade.php` memakai `data-dismiss` lama pada `:7`/`:39`,
  `datatables.ajax.reload()` pada `:76` sementara LS memakai `mainTable`, dan
  `notification(response)` pada error callback `:90` yang tidak memiliki variabel
  `response` tersebut.
- SPP menambahkan file ke FormData meskipun tidak dipilih (`spp.blade.php:156-164`);
  samakan penanganan file opsional agar edit tidak mengirim string `undefined`.
- Shared `formCrud.blade.php:59`/`:95` menggunakan POST untuk store dan update.
  Perubahan ke PUT/PATCH harus disertai penyesuaian form/JS.

### Kontrak form LS yang perlu dimigrasikan bersama backend

| Form | Field utama saat analisis |
|---|---|
| SPP | `nomor_spp`, `uraian`, `belanja`, `nominal`, `sub_kegiatan_id`, `rekening[index][id/uraian/rekening/nominal]`, file SPP/SPJ/Billing/BMD |
| SPM | `nomor_spm`, `nomor_sptjm`, `selected_spp`, file SPM/SPTJM/SP/SP Pengajuan |
| SP2D | `nomor_sp2d`, `rekening`, `uraian`, `user`, `selected_spm`, `nominal`, file SP2D |

Rekomendasi: kirim named route dan kemampuan aksi dari Blade/presenter ke JS;
gunakan satu kontrak error/refresh yang konsisten dengan komponen project baru.

## 8. Pelajaran dari payment lain

- UP, TU, KKPD, GU_SKPD, dan GU_UK mempunyai `actionRules()` pada model payment
  (sekitar baris `44-46`) untuk keluarga dokumen yang diproses bersama.
- LS/LS_Gaji masih memiliki aturan keluarga yang berulang di controller Data:
  `Verify.php:301`, `Denied.php:172`, `Delete.php:302`.
- `Payment/UP/SPP.php:584-623` dan `canSubmitByFlow()` pada `:788` memberikan
  referensi pembatasan payment/jenis dokumen, locking, tanda tangan, dan urutan
  submit yang lebih eksplisit.
- LS_Gaji memiliki banyak persamaan view dengan LS. Shared Table, CRUD, PDF,
  detail/history, dan konfirmasi dapat diperbaiki pada kebutuhan LS dahulu.

Rekomendasi: reuse pola yang terbukti berguna, tetapi jangan menyalin angka role,
scope organisasi lama, atau mengaktifkan seluruh payment bersamaan. Jangan
membangun abstraksi besar lintas semua payment sebelum LS berjalan.

## 9. Batas verifikasi dan referensi teknis

Sudah dilakukan: baca source project sekarang/lama, perbandingan file, pemeriksaan
route, pencarian class/dependensi, pemeriksaan migration, dan inspeksi schema
read-only. Tidak dilakukan: pengujian runtime alur LS, test suite/browser test,
migration, pengajuan dokumen, import transaksi, atau panggilan TTE/bank.

Saat implementasi, ikuti `AGENTS.md`: baca skill yang relevan dan gunakan
`search-docs` sebelum perubahan kode. Gunakan Boost untuk schema/query bila
tersedia. Hormati otorisasi pengujian dalam instruksi project dan percakapan;
permintaan dokumentasi ini bukan izin menjalankan test suite.

Referensi resmi yang diperiksa saat analisis:

- [Laravel 13 query locking dan transaksi](https://laravel.com/framework/docs/13.x/queries#pessimistic-locking)
- [Laravel 13 file storage](https://laravel.com/framework/docs/filesystem)

Langkah berikutnya: baca [rencana implementasi dan keputusan terbuka](PAYMENT_LS_IMPLEMENTATION_PLAN.md).
