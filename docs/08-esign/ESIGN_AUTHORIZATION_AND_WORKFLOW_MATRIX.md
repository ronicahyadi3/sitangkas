# Matriks Authorization dan Workflow TTE

Tanggal keputusan awal: **18 September 2026**. Pembaruan terakhir:
**23 September 2026**.

Status: **keputusan bisnis target dan matriks workflow versi 1 dikunci sebagai
dasar implementasi; data legacy yang ambigu wajib ditandai `needs_review` dan
tidak boleh diperbaiki atau dihapus otomatis**.

Dokumen ini adalah source of truth untuk kebijakan signer, Admin Super,
penempatan QR/footer, urutan TTE, prasyarat sign, pembatalan/retry, serta akses
view/download. Kebijakan byte PDF original versus watermark berada di
`PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md`. Baca bersama `README.md`,
`ESIGN_V2_CONTRACT_AND_BACKEND.md`, dan `ESIGN_V2_IMPLEMENTATION_PLAN.md`.

## 1. Keputusan yang sudah dikunci

1. Hanya ada mode bisnis **`SELF_SIGN`** pada implementasi awal.
2. Fitur **`PREPARE_FOR_SIGNER` tidak diimplementasikan**. Fitur tersebut hanya
   boleh dibuka kembali melalui keputusan scope baru.
3. Semua pengguna, termasuk Admin Super, mengikuti alur sign yang sama.
4. Signer yang sedang login dan menggunakan posisi aktifnya sendiri menempatkan
   QR/footer, memeriksa preview, mengafirmasi dokumen, lalu memasukkan
   passphrase miliknya sendiri.
5. Frontend tidak meminta NIK dari user biasa maupun Admin Super. Backend
   menyelesaikan NIK dari user terautentikasi dan identitas sertifikat yang
   sah.
6. Admin Super tidak boleh memilih NIK bebas, memakai passphrase orang lain,
   atau melakukan proxy-sign/impersonation.
7. Hak Admin Super untuk melihat atau mengelola dokumen tidak otomatis memberi
   hak menandatangani. Admin Super hanya dapat sign bila dirinya adalah signer
   sah pada langkah workflow aktif.
8. Acting context adalah overlay operasional. Acting context tidak mengubah
   certificate owner menjadi pejabat yang sedang diperankan.
9. Bila user Admin Super mempunyai posisi bisnis nyata lain, misalnya PA, dan
   memilih posisi nyata tersebut, ia beroperasi sebagai pengguna biasa pada
   posisi PA dan **bukan** sedang acting like. Acting like hanya berlaku ketika
   Admin Super memakai posisi efektif yang tidak tercatat sebagai posisi nyata
   miliknya.
10. Admin Super boleh membuat/mengunggah seluruh tipe dokumen melalui posisi
    bisnis nyata atau acting position yang sesuai. Authorization create tetap
    mengikuti role, scope organisasi, dan workflow posisi efektif; tidak ada
    bypass create tanpa context posisi.
11. Audit tindakan persisten menyimpan real authenticated user, posisi efektif,
    serta penanda apakah context tersebut posisi nyata atau acting position.
12. TTE multi-signer selalu berurutan. Output signer sebelumnya menjadi input
   signer berikutnya.
13. Setiap signer menempatkan QR/footer untuk langkahnya sendiri. Placement
    terikat pada signer, workflow step, dan exact source artifact.
14. Satu workflow step dapat mempunyai beberapa QR untuk signer/jabatan yang
    sama. Ini tetap satu step dan satu attempt dengan beberapa operasi serial,
    bukan penambahan step signer. Desain detail berada di
    `ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md`.
15. Passphrase hanya dikirim pada request final sign, lalu disimpan sementara
    dalam secret store/cache private terenkripsi ber-TTL agar dedicated worker
    dapat melanjutkan TTE asynchronous. Passphrase tidak boleh masuk database,
    session, log, event, `failed_jobs`, atau serialized queue payload.
16. Menutup atau membatalkan modal pada tahap penempatan/preview sebelum tombol
    sign ditekan tidak membuat audit event, signing attempt, atau record bisnis.
    Temporary preview dibersihkan segera atau melalui TTL.
17. Authorization wajib ditegakkan backend. Visibilitas tombol Blade/Svelte
    bukan security boundary.

## 2. Alur `SELF_SIGN`

```text
User login
  -> memilih/memakai posisi aktif
  -> membuka dokumen yang dapat diakses
  -> backend membuktikan user adalah signer langkah aktif
  -> signer menempatkan QR/footer miliknya
  -> backend membuat preview rendition sesuai kebijakan delivery PDF
  -> signer memeriksa dan mengafirmasi preview
  -> signer memasukkan passphrase miliknya
  -> backend memvalidasi ulang authorization, workflow, dan artifact hash
  -> backend membuat attempt dan mengantrekan job, lalu mengembalikan 202
  -> worker backend memanggil BSrE walau browser/modal sudah ditutup
  -> output diverifikasi di background
  -> artifact versi baru diaktifkan
  -> step current diselesaikan
  -> step berikutnya baru di-assign/diaktifkan saat handoff, atau workflow selesai
```

Frontend hanya mengirim identifier opaque untuk signing session, placement yang
tervalidasi, reason yang diizinkan, afirmasi, dan passphrase ephemeral. Frontend
tidak mengirim NIK, private path, filename authoritative, unit, role, signer,
atau workflow state.

## 3. Peta jabatan legacy

ID berikut berasal dari project lama dan hanya dipakai untuk ekstraksi/mapping:

| ID legacy | Jabatan |
|---:|---|
| 1 | Admin Super |
| 2 | BUD |
| 3 | Kuasa BUD |
| 4 | Verifikator BUD |
| 5 | PA |
| 6 | KPA |
| 7 | PPK-SKPD |
| 8 | PPTK |
| 9 | BP |
| 10 | BPP |
| 11 | BANK |
| 12 | Pimpinan |
| 13 | Auditor |

Implementasi baru tidak boleh menyebarkan numeric ID ini sebagai business rule
baru. Gunakan kode jabatan/role yang stabil dan map ID legacy saat migrasi.

## 4. Sumber bukti project lama

Referensi utama read-only:

- `app/Http/Controllers/Data/Detail.php` untuk akses detail dan kandidat jabatan
  TTE;
- `app/Http/Controllers/Data/DetailTbp.php` untuk scope detail/download TBP;
- `app/Http/Controllers/Data/Verify.php` untuk authorization dan prasyarat
  verifikasi;
- `app/Http/Controllers/Data/Denied.php` untuk aturan tolak;
- controller per payment di `app/Http/Controllers/Payment/*` untuk create,
  update, submit, dan cabang workflow;
- Blade per payment di `resources/views/Payment/*` untuk perilaku UI lama;
- `app/Services/Tte/TteService.php` dan
  `app/Http/Controllers/Esign/TteController.php` hanya sebagai bukti perilaku
  lama, bukan pola keamanan yang boleh disalin;
- `document_process` sebagai bukti urutan aktual `UPLOAD`, `SUBMIT`, `VERIFY`,
  `REJECT`, `TTE`, dan aktivitas lain.

Aturan lama tersebar dan dapat berbeda antara Blade dan backend. Contoh yang
sudah ditemukan: Blade TBP GU SKPD mencantumkan akses tambah `[9, 10, 1]`,
sedangkan backend `canManageCrud()` hanya menerima jabatan `9`. Karena itu,
matrix final tidak boleh dibentuk hanya dari `jabatanAccessAdd` atau tombol UI.

## 5. Dimensi authorization target

Setiap keputusan authorization harus mengevaluasi kombinasi berikut:

- user terautentikasi;
- real user dan real position;
- posisi aktif/effective context serta penanda real-position atau acting;
- kode jabatan;
- unit kerja langsung;
- root SKPD/instansi bila tipe dokumen memakai scope induk;
- tahun aktif dan izin tahun historis;
- `payment_type`;
- `src_type`;
- workflow variant, misalnya jalur BP atau BPP;
- document owner/uploader dan target user bila relevan;
- langkah workflow aktif;
- signer yang ditugaskan pada langkah tersebut;
- kondisi submit, verify, reject, dan signature sebelumnya;
- exact source artifact dan checksum-nya.

Authorization dianjurkan dipisahkan menjadi capability eksplisit:

```text
view
download
create
update
delete_or_withdraw
submit
verify
reject
place_signature
sign
retry_sign
```

## 6. Matriks pembuat/pengunggah versi 1

Tabel berikut adalah aturan target hasil gabungan controller backend dan histori
`UPLOAD` pada `document_process`. Dokumen satu paket yang dibuat bersamaan tetap
harus mengikuti role pembuat paketnya.

| Payment | Dokumen | Kandidat pembuat legacy |
|---|---|---|
| UP | SPP | BP |
| UP | SPM | PPK-SKPD |
| UP | SP2D | Verifikator BUD |
| LS / LS_GAJI | SPP | BP atau BPP |
| LS / LS_GAJI | SPM | PPK-SKPD |
| LS / LS_GAJI | SP2D | Verifikator BUD |
| GU_SKPD | NPD | PPTK |
| GU_SKPD | TBP | BP |
| GU_SKPD | LPJ / SPP / SPJ / BMD | BP |
| GU_SKPD | SPM | PPK-SKPD |
| GU_SKPD | SP2D | Verifikator BUD |
| GU_UK | NPD | PPTK |
| GU_UK | TBP / LPJ_BPP / SPJ | BPP |
| GU_UK | LPJ / SPP / BMD | BP |
| GU_UK | SPM | PPK-SKPD |
| GU_UK | SP2D | Verifikator BUD |
| TU | PENGAJUAN | PPTK |
| TU | SPP / TBP / LPJ / STS | BP atau BPP sesuai variant paket |
| TU | SPM | PPK-SKPD |
| TU | SP2D | Verifikator BUD |
| KKPD | DPR / DPT / NPD | PPTK |
| KKPD | SPP / BMD | BP |
| KKPD | SPM | PPK-SKPD |
| KKPD | SP2D | Verifikator BUD |

Admin Super dapat menjalankan create/upload di atas melalui dua context yang
harus dibedakan:

1. **posisi nyata**: bila user juga mempunyai posisi PA/PPTK/BP/dan seterusnya,
   ia memilih posisi tersebut dan diperlakukan sebagai pengguna biasa; atau
2. **acting position**: bila posisi target bukan posisi nyata miliknya, fitur
   acting like memberi effective role/scope untuk operasi dokumen.

Kedua context tidak memberi proxy-sign. TTE selalu memakai identitas sertifikat
real authenticated user dan tetap harus memenuhi assignment signer step aktif.

## 7. Matriks signer versi 1

Semua urutan berikut sequential. Tanda `/` pada satu step berarti workflow
memilih tepat satu actor sesuai variant atau assignment, bukan signer paralel.

| Payment | Dokumen | Urutan signer canonical |
|---|---|---|
| UP | SPP | BP -> PA |
| UP | SPM, SPTJM, SP_PENGAJUAN | PA |
| UP | SP | PPK-SKPD |
| UP | SP2D | assigned BUD/Kuasa BUD |
| GU_SKPD | NPD | PPTK -> PA |
| GU_SKPD | TBP | BP -> PA |
| GU_SKPD | LPJ | BP |
| GU_SKPD | SPP | BP -> PA |
| GU_SKPD | SPM, SPTJM, SP_PENGAJUAN | PA |
| GU_SKPD | SP | PPK-SKPD |
| GU_SKPD | SP2D | assigned BUD/Kuasa BUD |
| GU_UK | NPD | PPTK -> KPA |
| GU_UK | TBP | BPP -> KPA |
| GU_UK | LPJ_BPP | BPP |
| GU_UK | LPJ | BP |
| GU_UK | SPP | BP -> PA |
| GU_UK | SPM, SPTJM, SP_PENGAJUAN | PA |
| GU_UK | SP | PPK-SKPD |
| GU_UK | SP2D | assigned BUD/Kuasa BUD |
| LS / LS_GAJI jalur BP | SPP | BP -> PPTK -> PA |
| LS / LS_GAJI jalur BPP | SPP | BPP -> PPTK -> KPA |
| LS / LS_GAJI jalur BP | SPM, SPTJM, SP_PENGAJUAN | PA |
| LS / LS_GAJI jalur BPP | SPM, SPTJM, SP_PENGAJUAN | KPA |
| LS / LS_GAJI | SP | PPK-SKPD |
| LS / LS_GAJI | SP2D | assigned BUD/Kuasa BUD |
| TU jalur BP | PENGAJUAN | PPTK -> PA -> BUD |
| TU jalur BPP | PENGAJUAN | PPTK -> KPA -> BUD |
| TU jalur BP | SPP | BP -> PPTK -> PA |
| TU jalur BPP | SPP | BPP -> PPTK -> KPA |
| TU jalur BP | TBP, LPJ, STS | BP -> PA |
| TU jalur BPP | TBP, LPJ, STS | BPP -> KPA |
| TU | SPM, SPTJM, SP_PENGAJUAN | PA/KPA menurut variant |
| TU | SP | PPK-SKPD |
| TU | SP2D | assigned BUD/Kuasa BUD |
| KKPD | DPR | PPTK |
| KKPD | DPT | PA/KPA menurut assignment |
| KKPD | NPD | PPTK -> PA/KPA menurut assignment |
| KKPD | SPP | BP -> PA |
| KKPD | SPM, SPTJM, SP_PENGAJUAN | PA |
| KKPD | SP | PPK-SKPD |
| KKPD | SP2D | assigned BUD/Kuasa BUD |

`BMD` dan `SPJ` adalah dokumen pendukung tanpa langkah TTE berdasarkan histori
aktif. Dokumen bertanda tangan mempunyai satu sampai tiga signer; maksimum yang
ditemukan pada workflow versi 1 adalah tiga signer.

### Status implementasi LS SPP per 23 September 2026

Matrix `BP -> PPTK -> PA` dan `BPP -> PPTK -> KPA` sudah dikodekan sebagai
vertical slice pertama. Workflow upload tetap `draft`; BP/BPP nyata
mengaktifkan step pertama secara lazy ketika membuka signing session. Setelah
TTE sukses, step berikutnya tetap `pending`. Submit/handoff baru menetapkan
signer tujuan, menyalin current result artifact sebagai source step tujuan,
menulis event `step_assigned`, dan mengaktifkan step tersebut. Dengan demikian,
urutan bisnis **TTE lalu SUBMIT** ditegakkan tanpa menebak signer berikutnya.

Submit gate LS SPP wajib membuktikan completed step, succeeded attempt,
after-sign artifact current, compatibility projection `TTE`, dan assignment
aktor sebelum projection legacy berubah. Handoff canonical dan legacy commit
atau rollback dalam satu transaksi. Implementasi ini belum menjadi bukti
end-to-end sampai controlled signing jalur BP selesai.

Untuk SP2D, Verifikator BUD memilih tepat satu penerima penugasan: BUD atau
Kuasa BUD. Simpan assigned position/user secara spesifik. Hanya penerima tersebut
yang boleh TTE. Assignment boleh diubah sebelum signature sukses dengan event
audit; setelah sukses, perubahan memerlukan penolakan atau siklus workflow baru.

### Checkpoint verifikasi

| Kelompok | Dokumen | Verifikator utama |
|---|---|---|
| UP | SPP | PPK-SKPD |
| UP | SP/SPM/SPTJM/SP_PENGAJUAN | Verifikator BUD |
| GU_SKPD / GU_UK | LPJ/SPP/BMD | PPK-SKPD |
| GU_SKPD / GU_UK | SP/SPM/SPTJM/SP_PENGAJUAN | Verifikator BUD |
| LS / LS_GAJI | SPP/SPJ/BMD menurut paket | PPK-SKPD |
| LS / LS_GAJI | SP/SPM/SPTJM/SP_PENGAJUAN | Verifikator BUD |
| TU | PENGAJUAN | Verifikator BUD setelah PA/KPA dan sebelum BUD |
| TU | SPP | PPK-SKPD |
| TU | SP/SPM/SPTJM/SP_PENGAJUAN | Verifikator BUD |
| KKPD | SPP | PPK-SKPD |
| KKPD | SP/SPM/SPTJM/SP_PENGAJUAN | Verifikator BUD |

Checkpoint verify adalah action workflow tersendiri dan tidak dihitung sebagai
signature. Prasyarat tepatnya tetap mengikuti keluarga dokumen dan dependency
paket pada controller payment.

## 8. Model urutan dan jumlah signer

TTE selalu sequential. Jangan menyimpan aturan hanya sebagai `min_signers` dan
`max_signers` bila alurnya sebenarnya mempunyai langkah yang pasti.

Contoh konseptual:

```text
GU_SKPD + TBP + default:
  step 1 BP
  step 2 PA

GU_UK + TBP + default:
  step 1 BPP
  step 2 KPA
```

Jika satu tipe memiliki cabang sah, gunakan `workflow_variant`, misalnya jalur
BP dan jalur BPP. Setiap step menyimpan minimal:

- sequence;
- required role/position code;
- assigned signer user;
- organization scope rule;
- source artifact;
- result artifact;
- placement dan preview hash;
- status serta timestamp;
- actor/certificate-owner snapshot.

Setelah satu step sukses, result artifact menjadi current artifact workflow.
Pada flow yang memakai handoff seperti LS SPP, artifact tersebut baru diikat
sebagai source step berikutnya ketika assignment/handoff berhasil. Kegagalan
step berikutnya tidak menghapus hasil step sebelumnya.

## 9. Prasyarat sebelum TTE

Backend hanya boleh membuat atau memakai signing session bila:

1. dokumen ada, aktif, dan tidak sedang ditolak/dibatalkan;
2. user dan posisi aktif valid;
3. user memiliki akses unit/instansi/tahun terhadap dokumen;
4. user adalah signer yang ditugaskan pada step aktif;
5. role/position user cocok dengan definisi step;
6. seluruh step sebelumnya sudah `succeeded`;
7. submit/verify yang diwajibkan tipe dokumen sudah selesai;
8. document family/dependency yang diwajibkan lengkap;
9. exact source artifact tersedia, readable, non-empty, PDF valid, dan current;
10. SHA-256 artifact sama dengan snapshot preview;
11. QR/footer placement valid terhadap halaman/rotation/bounds;
12. tidak ada attempt aktif atau sukses duplikat untuk step tersebut;
13. identitas signer BSrE dapat diselesaikan dan masih layak digunakan;
14. signer telah melihat preview dan memberikan afirmasi;
15. passphrase diterima hanya pada request sign terakhir.

Perubahan artifact, signer, workflow, posisi aktif, atau scope organisasi setelah
preview membuat signing session kedaluwarsa dan wajib dibuat ulang.

## 10. Penempatan QR/footer

- Signer step aktif menempatkan QR/footer sendiri.
- Signer dapat menempatkan lebih dari satu QR pada halaman/lokasi berbeda untuk
  step yang sama. Satu klik/passphrase mengeksekusi seluruh QR secara serial;
  authorization tidak dievaluasi sebagai signer baru untuk setiap QR, tetapi
  wajib direvalidasi sebelum attempt dan resume.
- Placement tidak boleh digunakan ulang lintas signer tanpa validasi baru.
- QR menggunakan `/verify/{public_id}` dan menunjuk exact immutable result
  artifact dari step tersebut.
- `public_id` dapat direservasi saat session dibuat, tetapi baru diaktifkan
  setelah output berhasil diverifikasi dan artifact difinalisasi.
- Browser mengirim koordinat dalam coordinate system canonical; backend
  memvalidasi dan mentransformasikannya ke kontrak BSrE.
- Backend tidak menerima path image, PDF, atau output filename bebas.

## 11. Pembatalan, penolakan, dan retry

Ketiganya adalah operasi berbeda.

### Membatalkan persiapan TTE sebelum sign

- Pengguna dapat menutup modal atau menekan Batal ketika baru menempatkan
  QR/footer atau melihat preview.
- Pembatalan ini bukan reject, withdraw, atau pembatalan workflow.
- Jangan membuat `esign_attempts`, `esign_attempt_events`, `document_process`,
  atau audit event untuk tindakan ini.
- Jangan membuat record bisnis persisten sebelum pengguna menekan tombol sign.
- Temporary preview/context dibersihkan saat modal ditutup atau oleh TTL.
- Setelah request mungkin terkirim, jangan menandai attempt sebagai cancelled
  atau failed secara spekulatif. Gunakan `unknown` dan reconciliation.

### Menolak langkah workflow

- Reject hanya tersedia setelah paket disubmit ke langkah berikutnya.
- Hanya actor penerima pada langkah aktif yang boleh reject.
- Alasan reject wajib disimpan.
- Seluruh paket kembali kepada pembuat/pengunggah awal untuk diperbaiki,
  mengganti file, soft delete paket, lalu submit ulang.
- Reject satu paket harus atomik agar seluruh dokumen keluarga konsisten.
- Revisi/resubmit membuat cycle baru. Event, artifact, dan signature lama tetap
  immutable dan tidak dihapus.

State konseptual:

```text
DRAFT -> SUBMITTED -> IN_REVIEW -> REJECTED -> REVISION -> RESUBMITTED
```

### Menolak seluruh paket oleh BUD/Kuasa BUD

- BUD atau Kuasa BUD yang menerima assignment SP2D dapat menolak seluruh paket,
  baik SP2D belum maupun sudah TTE.
- Aksi hanya boleh dilakukan selama BANK belum menetapkan `finished_at`.
- Pemeriksaan assignment dan `finished_at` dilakukan ulang di dalam transaksi
  dengan lock.
- SP2D signed dan artifact lain yang sudah ada tidak dihapus; paket kembali ke
  pembuat sebagai cycle revisi dengan alasan wajib.

### Retry TTE

- Hanya signer yang tetap memiliki step tersebut yang boleh retry.
- Retry baru hanya untuk kegagalan yang terbukti deterministik/tidak mencapai
  provider. Timeout, connection reset, atau HTTP 5xx setelah request mungkin
  diterima wajib menjadi `unknown`, bukan langsung retryable.
- Passphrase salah, sertifikat tidak layak, dan PDF invalid memerlukan koreksi
  pengguna/data dan bukan automatic retry.
- Setiap retry membuat `esign_attempts` baru dan event lama tetap append-only.
- Attempt `unknown` tidak boleh diulang otomatis atau melalui tombol biasa
  sebelum reconciliation.

## 12. View dan download

Pengguna dapat melihat/mengunduh dokumen bila Policy membuktikan salah satu
scope yang diizinkan matrix, misalnya:

- owner/uploader dokumen;
- target user atau peserta workflow;
- unit kerja yang sama;
- root SKPD/instansi yang sama jika tipe dokumen mengizinkannya;
- role lintas unit yang memang ditugaskan, misalnya BUD/Kuasa BUD;
- hak administratif/audit eksplisit.

Aturan organisasi target:

- selama workflow masih berada pada bagian/unit, dokumen hanya terlihat oleh
  unit pemilik dan actor yang ditugaskan;
- setelah paket disubmit ke induk SKPD, induk dapat melihat paket dari seluruh
  unit turunannya;
- unit anak tidak boleh melihat paket milik unit saudara;
- Admin Super mengikuti scope posisi nyata atau acting position yang sedang
   aktif, kecuali capability administratif global yang didefinisikan eksplisit.

Istilah "instansi yang sama" harus diselesaikan menjadi scope ID canonical,
bukan perbandingan nama. Capability view dan download dapat tetap berbeda pada
Policy, tetapi **mode byte PDF tidak boleh dibedakan per aksi**. Setelah
authorization aksi lulus, satu resolver menentukan delivery mode:

- posisi nyata aktif dengan `pdf_watermark_required=true`: semua
  preview/view/download memakai derivative watermark server-side dan tidak ada
  original bypass;
- posisi nyata aktif dengan `pdf_watermark_required=false`: exact current
  canonical artifact boleh dikirim;
- Admin Super saat **acting like**: selalu diperlakukan sebagai
  `pdf_watermark_required=false`; scope posisi efektif tetap berlaku;
- Admin Super pada posisi bisnis nyata miliknya: mengikuti nilai flag posisi
  nyata tersebut;
- guest: hanya jika public-access policy dokumen lulus dan selalu public
  watermark; dokumen nonpublik tetap meminta login;
- authenticated user tanpa posisi valid: fail-closed.

Flag watermark tidak memberikan akses, tidak mengubah signer/certificate owner,
dan tidak menggantikan Policy. File selalu di-stream dari private storage
melalui controller dan setiap delivery diaudit. Auditor tetap read-only; hak
download auditor harus ditentukan eksplisit sebelum diaktifkan. Backend TTE dan
verifikasi BSrE selalu memakai exact original canonical artifact, tidak pernah
watermark derivative.

## 13. State UI dan attempt

State pengalaman signer:

```text
awaiting_signer
  -> previewing
  -> ready_to_sign
  -> queued
  -> signing
  -> validating
  -> succeeded
```

Alternatif akhir/operasional:

```text
failed
unknown
rejected
needs_review
```

Backend dapat tetap memakai state attempt teknis `prepared`. Istilah tersebut
berarti authorization, artifact snapshot, dan lock context telah disiapkan;
istilah itu **bukan** fitur `PREPARE_FOR_SIGNER` dan bukan handoff dari Admin
Super ke orang lain.

Namun `prepared` tidak boleh dibuat sebagai record `esign_attempts` persisten
saat pengguna baru membuka modal. Attempt persisten dibuat saat tombol sign
ditekan dan request final berhasil di-commit, sebelum job queue didispatch.
Context preview sebelumnya boleh berupa context teknis ephemeral dan tidak
menjadi audit/history. Menutup modal sebelum submit final cukup membersihkan
state frontend dan temporary resource tanpa record. Menutup modal setelah
respons `202` tidak membatalkan attempt/background job.

## 14. Data legacy anomali dan `needs_review`

Anomali berarti data lama tidak dapat dipetakan secara deterministik ke
workflow baru; istilah ini tidak otomatis berarti pelanggaran atau kecurangan.
Contohnya:

- role yang sama tercatat TTE berulang, misalnya `9,9,5` atau `3,3`;
- event `TTE` ada tetapi snapshot `document.status` kosong, atau sebaliknya;
- urutan signer terbalik atau mencampur jalur BP dan BPP;
- signer tidak cocok dengan assignment/role canonical;
- TTE muncul setelah reject tanpa batas cycle revisi yang dapat dibuktikan;
- file hasil tidak ditemukan atau tidak dapat dipasangkan dengan attempt/event;
- assignment BUD/Kuasa BUD tidak konsisten dengan signer historis.

Klasifikasi mapping:

- `mapped`: dapat dipetakan secara deterministik;
- `partial`: workflow aktif memang belum selesai, bukan anomali;
- `technical_retry`: pengulangan teknis dapat dikenali;
- `legacy_valid`: pola lama berbeda tetapi dapat dijelaskan;
- `needs_review`: tidak aman diputuskan otomatis.

Data `needs_review` tidak dihapus atau diperbaiki otomatis. History tetap dapat
dibaca. Dokumen aktif hanya diblokir bila ambiguitas memengaruhi signer, cycle,
atau source artifact aktif; resolusi manual wajib mencatat actor, alasan, waktu,
dan nilai sebelum/sesudah.

## 15. Struktur implementasi yang direkomendasikan

Gunakan dua lapisan yang berhubungan:

1. definisi workflow per `payment_type + src_type + workflow_variant`;
2. instance workflow dan step signer per dokumen.

Nama konseptual:

- `document_signing_workflows`;
- `document_signing_steps`;
- `DocumentPolicy`/policy per capability;
- `SignerIdentityResolver`;
- `CreateSigningSession`;
- `SignDocument`;
- `FinalizeSignedDocument`.

Aturan stabil dapat berupa enum/config atau definition table yang terversi.
Assignment signer, snapshot organisasi, status, artifact, dan audit harus berada
di database. Jangan mempertahankan CSV `assigned_to`, `submit`, dan `status`
sebagai model canonical baru; tetap tulis compatibility projection sesuai
`LEGACY_OPERATIONAL_TABLES_COMPATIBILITY.md` agar controller/Blade lama berjalan.

Index minimal mengikuti pola query aktual, antara lain workflow/document,
step/status/sequence, assigned signer/status, artifact/current version, serta
organization scope. Gunakan Policy/service yang sama untuk menghasilkan
capability UI dan menegakkan endpoint backend.

## 16. Status keputusan dan pekerjaan lanjutan

Keputusan creator, signer sequential, SP2D assignment, Admin Super/multi-position,
reject/revision, technical retry, scope unit-induk, dan `needs_review` sudah
dikunci. Pekerjaan berikutnya:

1. bentuk definition data `document_signing_workflows` dan step versi 1 dari
   matriks dokumen ini;
2. definisikan exact rejector dan dependency per family dari controller payment;
3. putuskan capability download Auditor secara eksplisit; bila diizinkan,
   delivery mode tetap mengikuti satu flag watermark di atas;
4. definisikan resolver assignment BUD/Kuasa BUD dan variant BP/BPP;
5. segmentasikan histori ke cycle agar retry/revisi tidak dianggap signer baru;
6. implementasikan Policy/capability dan compatibility projection CSV;
7. migrasikan data secara resumable; tandai kasus ambigu `needs_review`.

## 17. Larangan untuk AI agent

- Jangan mengimplementasikan `PREPARE_FOR_SIGNER` tanpa keputusan scope baru.
- Jangan menampilkan input NIK pada modal sign, termasuk untuk Admin Super.
- Jangan memberi Admin Super hak sign universal karena role administratif.
- Jangan menganggap acting context sebagai certificate owner.
- Jangan menganggap posisi bisnis nyata milik Admin Super sebagai acting like;
  bila posisi itu benar-benar assigned kepada user, perlakukan sebagai posisi
  pengguna biasa.
- Jangan membuat record audit/attempt ketika pengguna hanya membatalkan modal
  sebelum tombol sign ditekan.
- Jangan menentukan signer hanya dari tombol, `assigned_to`, atau daftar role
  pada `Detail.php`.
- Jangan mengizinkan parallel signing.
- Jangan menghapus artifact/history ketika reject, cancel, atau retry.
- Jangan auto-retry attempt sign `unknown`.
- Jangan membuka download dengan direct public path.
- Jangan membuat `pdf_view_watermark_required` dan
  `pdf_download_watermark_required`; hanya gunakan
  `user_positions.pdf_watermark_required` untuk semua bentuk delivery PDF.
- Jangan memakai flag watermark sebagai authorization dan jangan memberi
  original kepada guest atau posisi berflag `true` melalui endpoint alternatif.
- Jangan menerapkan flag posisi acting kepada Admin Super; keputusan bisnis
  final menetapkan mode acting sebagai `pdf_watermark_required=false`.
