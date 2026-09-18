# Kontrak Tabel Operasional Legacy dan Audit Canonical

Tanggal keputusan: **18 September 2026**.

Status: **keputusan arsitektur yang dikunci pengguna; migration canonical sudah
dibuat, dilengkapi direct document index/checkpoint, dan lolos simulasi SQL,
tetapi belum diterapkan ke database. PHP enum domain sudah dibuat; model/cast,
writer, transition service, reconciliation runner, dan cutover belum
diimplementasikan**.

Dokumen ini adalah sumber keputusan utama untuk enam tabel operasional berikut:

```text
after_signs
anggaran_kegiatan
anggaran_kegiatan_temp
before_signs
document
document_process
```

Agent yang mengubah controller payment/data, model dokumen/anggaran, proses TTE,
laporan, migration, atau compatibility writer wajib membaca dokumen ini bersama
`ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md` dan
`ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md`.

## 1. Keputusan final yang berlaku

1. Keenam tabel tetap dipertahankan dengan nama dan semantik kolom yang sekarang.
2. Tidak ada rename, drop, perubahan tipe kolom, atau perubahan arti status
   tanpa keputusan pengguna baru dan analisis seluruh consumer.
3. `before_signs`, `after_signs`, dan `document_process` bersifat append-only
   pada alur normal: histori lama tidak di-update atau dihapus.
4. `document`, `anggaran_kegiatan`, dan `anggaran_kegiatan_temp` tetap melayani
   controller, Blade, serta proses operasional yang telah dimigrasikan dari
   project lama.
5. Tabel canonical baru tidak menggantikan kontrak enam tabel secara mendadak.
   Tabel canonical menambahkan workflow, version chain, attempt, response BSrE,
   dan audit terstruktur yang tidak dapat direpresentasikan dengan aman pada
   tabel legacy.
6. `before_signs` dan `after_signs` dipertahankan sebagai compatibility ledger
   append-only untuk laporan/aplikasi eksternal. Keduanya bukan state machine
   utama TTE.
7. Tidak ada migration drop untuk `before_signs` atau `after_signs` dalam scope
   implementasi saat ini.
8. Penghentian write, perubahan menjadi read-only, archive, atau drop di masa
   depan hanya boleh dilakukan setelah seluruh gate pada bagian 12 lulus dan
   pengguna membuat keputusan eksplisit baru. Mapping selesai tidak otomatis
   memberi izin decommission.
9. Record invalid, zero-byte, orphan, ambigu, atau tidak lengkap adalah bukti
   historis. Jangan dihapus atau diperbaiki otomatis; gunakan `needs_review`.
10. File canonical tetap berada di private storage. Mempertahankan tabel legacy
    tidak berarti mempertahankan public storage sebagai desain akhir.

Keputusan ini menggantikan pernyataan dokumentasi terdahulu yang menetapkan
target akhir otomatis berupa drop `before_signs` dan `after_signs`.

## 2. Snapshot bukti aktif

Snapshot read-only 18 September 2026:

| Tabel | Jumlah row | Peran aktif |
|---|---:|---|
| `document` | 482.568 | Header, paket, assignment, dan state operasional |
| `document_process` | 2.302.162 | Histori ringkas UI/laporan legacy |
| `anggaran_kegiatan` | 34.787 | Rincian rekening/nominal per SPP |
| `anggaran_kegiatan_temp` | 11.863 | Katalog pagu dan rekening |

Snapshot sebelumnya mencatat sekitar 590 ribu row pada masing-masing
`before_signs` dan `after_signs`. Volume tersebut serta penggunaan aplikasi
eksternal membuat perubahan destruktif berisiko tinggi.

Temuan kualitas data:

- tidak ada duplikasi aktif pada kombinasi
  `(tahun,id_spp,id_rekening)` di `anggaran_kegiatan`;
- tidak ada duplikasi aktif pada kombinasi
  `(tahun,id_unit_kerja,id_rekening)` di `anggaran_kegiatan_temp`;
- ada dua row `anggaran_kegiatan` yang tidak menemukan pasangan `document`;
  keduanya wajib dipertahankan dan masuk `needs_review`;
- tidak ditemukan `document_process.id_dokumen` yatim pada snapshot;
- 97.811 dokumen memakai nilai CSV pada `status`;
- 339.677 dokumen memakai nilai CSV pada `submit`;
- 1.576 dokumen memakai nilai CSV pada `assigned_to`.

Angka CSV membuktikan bahwa normalisasi tidak boleh dilakukan sebagai perubahan
serentak. Projection kompatibilitas tetap diperlukan.

## 3. Sumber kebenaran per concern

| Concern | Sumber kebenaran |
|---|---|
| Header, nomor, tipe, relasi paket, dan state operasional UI lama | `document` |
| Katalog pagu/rekening | `anggaran_kegiatan_temp` |
| Alokasi rekening dan nominal per SPP | `anggaran_kegiatan` |
| Histori ringkas untuk UI/laporan lama | `document_process` |
| Compatibility input sebelum provider | `before_signs` |
| Compatibility hasil provider | `after_signs` |
| Byte/path/version chain file | `document_artifacts` |
| Workflow dan urutan signer canonical | `document_signing_workflows`, `document_signing_steps` |
| Audit perubahan workflow bisnis | `document_signing_workflow_events` |
| Satu percobaan TTE | `esign_attempts` |
| Snapshot placement/request aman | `esign_attempt_signature_properties` |
| Response BSrE tersanitasi | `esign_provider_responses` |
| Timeline teknis attempt | `esign_attempt_events` |

Tidak ada satu tabel yang boleh dianggap menggantikan seluruh concern lain.

## 4. Kontrak `anggaran_kegiatan_temp`

Tabel ini tetap menjadi katalog/master anggaran yang dibaca form dan endpoint
rekening. TTE tidak boleh menambahkan workflow atau response BSrE ke tabel ini.

Aturan:

- pertahankan nama, kolom, soft delete, dan cara baca existing;
- perubahan master dilakukan melalui proses import/sinkronisasi terkontrol;
- jangan menghapus data karena tidak ditemukan pada satu dokumen;
- pertimbangkan audit import terpisah seperti `budget_import_runs` dan
  `budget_import_items`, bukan JSON histori pada row master;
- kandidat indeks hanya diterapkan setelah `EXPLAIN`, capacity, dan lock review:
  `(tahun,id_unit_kerja,kode_sub_kegiatan,deleted_at)` serta
  `(tahun,id_unit_kerja,id_rekening,deleted_at)`.

## 5. Kontrak `anggaran_kegiatan`

Tabel ini tetap menjadi rincian rekening dan nominal yang melekat pada SPP.
Domain anggaran tidak digabung ke tabel attempt TTE.

Aturan:

- pertahankan hubungan legacy `id_spp` ke `document.id`;
- edit/sinkronisasi rekening harus berada dalam transaksi bersama perubahan
  dokumen yang terkait;
- dua orphan yang telah ditemukan tidak boleh dihapus atau diberi pasangan
  hasil tebakan;
- audit perubahan nominal kelak menggunakan event append-only terpisah, misalnya
  `anggaran_kegiatan_events`, dengan before/after state, changed fields, actor,
  posisi, acting context, correlation ID, timestamp, dan hash snapshot;
- kandidat indeks setelah review query:
  `(tahun,id_spp,deleted_at)` dan
  `(tahun,id_unit_kerja,id_rekening,deleted_at)`.

## 6. Kontrak `document`

`document` tetap menjadi operational projection utama untuk controller dan
Blade lama. Kolom berikut tetap dipertahankan:

```text
src_name
src_type
payment_type
reference_id
parent_id
uploaded_by
users_to
verify
status
submit
assigned_to
rejected_by
signed_at
finished_at
```

Aturan:

- jangan menjadikan `src_name` sebagai satu-satunya bukti file/version;
- `document_artifacts` menjadi sumber version chain file;
- `status`, `submit`, dan `assigned_to` CSV tetap ditulis sebagai projection
  kompatibilitas, tetapi workflow baru membaca state terstruktur dari workflow
  dan step canonical;
- perubahan canonical dan projection legacy wajib direkonsiliasi;
- relasi model ke artifact/workflow boleh ditambahkan tanpa mengubah kontrak
  fisik tabel;
- `public_id` verifikasi berada pada artifact canonical; tidak perlu memaksa
  penambahan public ID ke `document` hanya untuk QR.

## 7. Kontrak `document_process`

`document_process` tetap menjadi histori ringkas append-only bagi UI dan laporan
legacy. Action legacy tetap dipertahankan:

```text
UPLOAD
EDITED
SUBMIT
VERIFY
REJECT
DELETE
TTE
```

Aturan:

- jangan update/delete row historis pada alur normal;
- setiap tindakan bisnis baru tetap menulis action kompatibel;
- detail state sebelum/sesudah, acting context, response BSrE, retry, dan
  workflow step disimpan pada event canonical;
- `document_process` bukan pengganti `document_signing_workflow_events` atau
  `esign_attempt_events`;
- indeks mapping yang sudah disiapkan:
  `(id_dokumen,action,created_at,id)` dan `(action,created_at,id)`.

## 8. Kontrak `before_signs`

`before_signs` menjadi ledger kompatibilitas input TTE yang append-only.

Record baru hanya dibuat setelah:

1. user menekan tombol sign final;
2. authorization, assignment, dan artifact hash divalidasi ulang;
3. attempt persisten dibuat;
4. proses benar-benar akan memasuki vendor-call flow.

Menutup modal, membatalkan placement, atau membatalkan preview sebelum tombol
sign final tidak membuat `before_signs`, attempt, history, atau audit.

Aturan:

- satu attempt baru dapat menghasilkan satu row kompatibel baru;
- retry adalah attempt baru, bukan update row lama;
- jangan menyimpan passphrase, Basic Auth, NIK lengkap, PDF Base64, atau image
  Base64;
- link ke canonical disimpan melalui `esign_attempt_legacy_links`, bukan
  foreign key baru yang memutus consumer;
- indeks mapping yang disiapkan:
  `(id_data,created_at,id)` dan `(src_name,created_at,id)`.

## 9. Kontrak `after_signs`

`after_signs` menjadi ledger kompatibilitas hasil setiap attempt dan tetap
append-only untuk success, provider rejection, timeout/unknown, output invalid,
atau kegagalan teknis lain sesuai contract laporan legacy.

Aturan:

- success merujuk hasil yang sudah dipersist dan diverifikasi sebelum dokumen
  ditandai selesai;
- failure tetap memiliki record kompatibel walaupun tidak ada output artifact;
- response/message harus disanitasi dan tidak boleh berisi passphrase,
  credential, NIK lengkap, request/response mentah, atau Base64;
- retry membuat row baru; jangan menimpa hasil sebelumnya;
- file invalid/zero-byte tetap diregistrasikan sebagai evidence non-current;
- link canonical memakai `esign_attempt_legacy_links`;
- indeks mapping yang disiapkan:
  `(id_data,created_at,id)` dan `(src_name,created_at,id)`.

## 10. Pola dual-write dan transaksi

Canonical adalah state/audit terstruktur; legacy adalah kontrak operasional dan
compatibility projection. Keduanya harus ditulis melalui Action/Service yang
sama, bukan tersebar di controller.

```text
Final sign confirmation
  -> short transaction: lock + attempt + properties + before_signs + legacy link
  -> commit
  -> call BSrE without an open database transaction
  -> validate/stage/verify output
  -> short transaction: provider response + artifact + attempt/events
                        + workflow/step/events + after_signs
                        + document projection + document_process
  -> commit
```

Atomic lock, request fingerprint, idempotency key, dan unique constraint wajib
mencegah double sign serta duplicate compatibility row.

Jika post-call transaction gagal, jangan menganggap dokumen sukses. Simpan atau
rekonsiliasi outcome menurut state `unknown`/failure policy; jangan mengulang
vendor sign otomatis.

## 11. Mapping, reconciliation, dan file

- Mapping historis tetap resumable, idempotent, pause/resume, dan zero-downtime.
- Setiap legacy row memperoleh link/status mapping tanpa mengubah row sumber.
- Data setelah high-watermark masuk dual-write; caller legacy yang belum masuk
  dual-write diproses melalui catch-up.
- Parity membandingkan jumlah attempt, success/failure/unknown, timestamp,
  filename, size, checksum, output, dan workflow event.
- Perbedaan masuk `needs_review`; jangan melakukan koreksi otomatis.
- File canonical mempunyai satu salinan utama di private storage dan memakai
  SHA-256. Metadata legacy dapat tetap menunjuk nama kompatibel.
- Consumer eksternal harus diinventarisasi apakah membaca database, filesystem,
  replica, export, atau API. Pemindahan file tidak boleh memutusnya diam-diam.
- Target jangka panjang akses file consumer adalah API/reporting contract yang
  terautentikasi, bukan public path langsung.

## 12. Gate sebelum perubahan lifecycle tabel legacy

Keputusan saat ini adalah tetap menulis keenam tabel sesuai kontraknya. Jika di
masa depan diusulkan freeze/read-only/archive/drop untuk `before_signs` atau
`after_signs`, seluruh syarat berikut wajib lulus lebih dahulu:

1. mapping dan catch-up selesai serta dapat di-resume;
2. seluruh row mempunyai status/link atau exception `needs_review` yang jelas;
3. parity row, status, file, size, hash, signature, route, dan laporan lulus;
4. seluruh consumer sudah diinventarisasi dan pemiliknya menyetujui perubahan;
5. reporting API/view pengganti tervalidasi;
6. tidak ada read/write legacy yang tidak diketahui selama observation window;
7. final backup, checksum manifest, restore drill, dan rollback plan lulus;
8. retention dan kewajiban audit disetujui;
9. proposal perubahan dibuat sebagai phase/migration terpisah;
10. pengguna memberikan keputusan eksplisit baru.

Tanpa seluruh syarat tersebut, agent dilarang menghentikan dual-write, rename,
archive, truncate, atau drop tabel.

## 13. Status implementasi saat dokumen dibuat

- migration canonical dan indeks mapping legacy telah dibuat;
- PHP lint, Pint, dan `php artisan migrate --pretend` lulus;
- migration belum dijalankan ke database;
- tidak ada row legacy yang diubah/dihapus;
- model canonical, compatibility writer, reconciliation service, dan mapping
  runner belum dibuat;
- kandidat indeks anggaran belum dibuat karena harus melalui query/lock review;
- audit event anggaran belum dibuat dan menjadi pekerjaan lanjutan terpisah.

## 14. Larangan untuk AI agent

- Jangan menghapus atau mengganti nama keenam tabel.
- Jangan mengubah arti status/action legacy untuk menyesuaikan enum baru.
- Jangan menjadikan Blade/controller lama sebagai satu-satunya bukti policy.
- Jangan menulis canonical dan legacy melalui dua code path bisnis terpisah.
- Jangan membuat database trigger sebagai pengganti Action/Service tanpa
  keputusan baru; trigger menyembunyikan alur dan menyulitkan audit/retry.
- Jangan menaruh external BSrE call di dalam transaksi database panjang.
- Jangan menyimpan passphrase, credential, NIK lengkap, atau Base64 di tabel
  legacy maupun canonical audit.
- Jangan menghapus orphan/invalid/zero-byte untuk mengejar parity 100%.
- Jangan menganggap mapping selesai sebagai izin decommission.
