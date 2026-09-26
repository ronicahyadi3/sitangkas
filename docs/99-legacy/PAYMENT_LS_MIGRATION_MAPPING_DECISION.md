# Keputusan Penundaan Migrasi dan Mapping Dokumen Payment

Tanggal keputusan: **26 September 2026**.

Status: **ditunda sampai Payment LS dapat digunakan dan pengguna memberi
instruksi eksplisit untuk melanjutkan migrasi/mapping**.

Dokumen ini menetapkan batas pekerjaan aktif bagi AI agent. Rancangan global
pada `../08-esign/ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md` tetap berlaku sebagai
desain masa depan, tetapi keberadaan rancangan tersebut bukan izin untuk
mengimplementasikan atau menjalankannya sekarang.

## 1. Keputusan aktif

Prioritas saat ini adalah menyelesaikan Payment LS agar dapat digunakan pada
alur operasional. Selama keputusan ini masih aktif:

- jangan membuat atau menjalankan runner, command, job, queue, atau batch untuk
  migrasi/mapping file historis;
- jangan melakukan scan, bulk copy, backfill, atau mapping massal dari folder
  `public/File_*` ke private canonical storage;
- jangan mengubah row historis `document`, `document_process`, `before_signs`,
  atau `after_signs` hanya untuk kebutuhan migrasi;
- jangan menghapus, memindahkan, mengganti nama, atau menonaktifkan folder
  legacy di `public/File_*`;
- jangan menjadikan persentase mapping/backfill sebagai syarat agar Payment LS
  baru dapat dipakai;
- jangan memperluas pekerjaan ke payment lain sebelum Payment LS stabil atau
  pengguna mengubah prioritas.

## 2. Scope implementasi yang tetap berjalan

Pekerjaan berikut tetap berada dalam scope karena dibutuhkan agar Payment LS
dapat dipakai:

1. Menstabilkan halaman, JSON, create, update, detail, history, submit, deny,
   verify, dan delete SPP LS.
2. Menjaga validasi akses posisi, unit kerja, tahun, state dokumen, serta
   transaksi dan locking anggaran.
3. Menyimpan upload baru dan replacement SPP, SPJ, BMD, serta Billing ke
   canonical private storage.
4. Melayani file private melalui delivery route yang mempunyai pemeriksaan
   akses.
5. Menyelesaikan workflow dan TTE SPP LS secara end-to-end, termasuk hubungan
   `before_signs`, `after_signs`, artifact, attempt, dan projection legacy.
6. Melanjutkan SPM dan SP2D setelah vertical slice SPP terbukti dapat dipakai.

Implementasi runtime baru boleh membuat row artifact canonical untuk file yang
baru di-upload atau diganti. Hal ini bagian dari penyimpanan transaksi dokumen
aktif, bukan migrasi data historis massal.

## 3. Perlakuan terhadap dokumen historis selama penundaan

- Dokumen historis yang belum mempunyai canonical artifact tetap berada di
  lokasi legacy dan tetap dilayani melalui fallback yang sudah tersedia.
- Tidak ada proses background yang mengubah dokumen historis secara otomatis.
- Bila pengguna mengganti satu file historis melalui fitur update LS, controller
  boleh melakukan provisioning terbatas pada dokumen tersebut untuk menjaga
  version chain dan mencegah riwayat file terputus. Proses ini bersifat
  **on-demand per dokumen**, bukan bulk migration atau backfill.
- File legacy sumber tidak dihapus setelah provisioning terbatas tersebut.

Pemisahan ini penting: fallback dan provisioning per dokumen membuat fitur LS
tetap aman digunakan selama masa transisi, sedangkan migrasi global menangani
seluruh data historis dalam batch dan saat ini ditunda.

## 4. Kapan migrasi/mapping boleh dilanjutkan

Migrasi/mapping global hanya boleh dimulai kembali setelah:

1. pengguna memberi instruksi eksplisit untuk melanjutkannya;
2. Payment LS sudah lolos verifikasi runtime pada alur utama yang disepakati;
3. scope dan wave pertama ditetapkan kembali;
4. dry-run inventory dan rekonsiliasi dilakukan sesuai
   `../08-esign/ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md`;
5. tidak ada penghapusan file public pada wave mapping awal.

Saat pekerjaan dilanjutkan, Billing harus diperlakukan sebagai attachment pada
kolom `document.billing`, bukan sebagai `src_type` atau row `document` baru.
SPP, SPJ, BMD, dan tipe dokumen lain tetap mengikuti pola row legacy yang telah
ada dan memperoleh artifact canonical secara additive.

## 5. Instruksi handoff untuk AI agent

- Baca dokumen ini sebelum mengusulkan backfill atau mapping file Payment LS.
- Anggap migration runner, backfill historis, dan decommission folder public
  sebagai **hold**, bukan sebagai langkah implementasi LS berikutnya.
- Jangan menafsirkan tabel migration control atau runbook yang sudah dirancang
  sebagai otorisasi eksekusi.
- Prioritaskan kesiapan runtime Payment LS dan catat temuan historis sebagai
  input untuk wave migrasi mendatang tanpa menjalankan migrasinya.
- Bila kebutuhan runtime dapat dipenuhi dengan fallback aman, pertahankan
  fallback sampai keputusan ini dicabut secara eksplisit.
