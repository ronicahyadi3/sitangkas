# Legacy Docs

Cluster ini menjelaskan pemetaan data lama ke struktur SITANGKAS baru.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Lokasi project lama yang harus dicontoh | `OLD_PROJECT_REFERENCE.md` |
| Mapping dump lama ke master baru | `LEGACY_MAPPING.md` |
| Import `users.sql` menjadi akun distinct NIK dan posisi dengan ID legacy | [LEGACY_USERS_IMPORT_DECISIONS.md](LEGACY_USERS_IMPORT_DECISIONS.md) |
| Kondisi kode, private artifact, delivery, TTE, blocker, dan handoff Payment LS terbaru | [PAYMENT_LS_CURRENT_IMPLEMENTATION.md](PAYMENT_LS_CURRENT_IMPLEMENTATION.md) |
| Analisis migrasi payment LS, dependensi, alur, dan temuan kode | [PAYMENT_LS_ANALYSIS.md](PAYMENT_LS_ANALYSIS.md) |
| Melanjutkan implementasi LS, keputusan terbuka, dan kriteria selesai | [PAYMENT_LS_IMPLEMENTATION_PLAN.md](PAYMENT_LS_IMPLEMENTATION_PLAN.md) |
| Seeder master data hasil mapping | `../02-master-data/AI_AGENT_MASTER_DATA_SEEDERS.md` |
| Validasi jumlah dan konsistensi hasil import | `../02-master-data/VALIDATION.md` |

## Aturan inti

- Kode canonical harus deterministik dan stabil.
- Jangan mengubah kode setelah dipakai policy, konfigurasi, integrasi, atau laporan.
- Jangan mengembalikan `skpd_id` tanpa keputusan schema yang eksplisit.
- Data legacy dipakai sebagai sumber bootstrap, bukan sinkronisasi harian otomatis.
- Import tabel `users` legacy wajib mengikuti
  [LEGACY_USERS_IMPORT_DECISIONS.md](LEGACY_USERS_IMPORT_DECISIONS.md): `uuid`
  dan `access` tidak digunakan, sedangkan `legacy users.id` dipertahankan sebagai
  `user_positions.id`.
- Project lama referensi utama berada di `C:\Apache24\htdocs\sitangkas`.
- Jangan mengubah project lama kecuali user meminta secara eksplisit.

## Status import users saat ini

Per 2026-09-14, pipeline read-only sudah tersedia sampai validator preflight dan
validator pasca-insert. Dry-run terakhir menghasilkan 1.053 akun, 1.143 posisi
canonical, 371 alias, dan 0 blocker; tabel target `users` serta
`user_positions` tetap kosong. Per 2026-09-15, action transaksional sudah dibuat
dan per 2026-09-16 sudah terhubung ke entry point command, structured log, serta
writer laporan commit. Safety gate diaktifkan sementara oleh operator pada
maintenance attempt 2026-09-16.
Strategi password produksi sudah dikunci ke
`preserve_legacy_hash_force_change`; override shared password hanya boleh untuk
environment `local`, default nonaktif, dan tidak boleh tercatat dalam laporan.
Resolver, validator, config, dan ringkasan dry-run status akun sudah selesai:
930 akun diproyeksikan active dan 123 inactive dengan checksum resolusi yang
dikunci. Strategi file SK sudah dikunci ke `defer`; users importer menunda SK
ke importer dokumen terpisah yang hanya
mengambil file fisik valid. Analyzer SK read-only dan command
`legacy:import-user-position-documents --dry-run` sudah tersedia serta lulus
dengan 0 blocker. Snapshot `public/SuratKeterangan` mencocokkan 522 dari 1.514
path dan hanya 518 file lolos pemeriksaan ketat; source tetap harus dibekukan
dan hasil import kelak disimpan private.
Safety gate `legacy_import.execution.enabled` saat ini `true` untuk maintenance
attempt, bukan sebagai konfigurasi permanen.
Karena import users hanya dilakukan satu kali, tidak akan dibuat tabel audit
batch. Bukti eksekusi akan memakai laporan commit JSON private, structured log
yang disanitasi, fingerprint sumber, backup target, dan transaksi rollback.
Writer laporan commit private untuk status `completed` dan `failed` sudah
tersedia. Opsi `--commit` dan `--fingerprint=` sudah terdaftar, tetapi mode
commit hanya menjalankan analyzer read-only terbaru, membandingkan fingerprint,
memastikan 0 blocker, dan meminta konfirmasi ketik yang terikat pada fingerprint.
Commit pertama pada 2026-09-16 05:23 UTC menerima fingerprint dan konfirmasi,
kemudian di-rollback pada stage `post_import` karena dua false negative saat
membaca counter `information_schema.TABLES.AUTO_INCREMENT` sebelum commit.
Target tetap 0 `users` dan 0 `user_positions`. Validator sudah diperbaiki untuk
memeriksa ID maksimum dan atribut `AUTO_INCREMENT` kolom `id`; counter yang
dapat stale tidak lagi menjadi blocker. Retry commit oleh operator masih
pending dan gate wajib dikembalikan ke `false` setelah berhasil atau dibatalkan.
Validator pasca-import sudah lengkap dan tetap dijalankan di dalam transaksi:
jumlah row, fingerprint deterministik akun/posisi/target gabungan, proyeksi
password tanpa mengekspos hash, status dan canonical/alias, cakupan referensi
`document`/`document_process`, ID maksimum, serta atribut `AUTO_INCREMENT`
kolom primary key diverifikasi sebelum commit. Counter metadata tabel tidak
dibaca sebagai blocker selama transaksi karena dapat stale sebelum commit.
Kegagalan invariant menyebabkan rollback.
Mulai setiap pekerjaan lanjutan dari bagian
"Handoff cepat untuk AI agent" pada
[LEGACY_USERS_IMPORT_DECISIONS.md](LEGACY_USERS_IMPORT_DECISIONS.md).

Jangan membuat, mengubah, atau menjalankan test suite tanpa permintaan eksplisit
pengguna.

## Payment LS

Pengguna memprioritaskan implementasi LS sebelum payment lain. Snapshot analisis
8 September 2026 tetap menjadi baseline historis. Kondisi 22 September 2026
sudah bergerak ke implementasi SPP: route/menu/model/request store/controller
shared tersedia, upload SPP utama sudah menjadi canonical private artifact, dan
delivery route khusus LS SPP sudah dibuat. Mulai dari
[kondisi implementasi aktual](PAYMENT_LS_CURRENT_IMPLEMENTATION.md), lalu baca
[hasil analisis awal](PAYMENT_LS_ANALYSIS.md) dan
[rencana implementasi](PAYMENT_LS_IMPLEMENTATION_PLAN.md). Jangan menganggap
SPP/LS siap produksi; dokumen aktual mencatat missing `UpdateSppRequest`,
storage public yang tersisa, dan validasi runtime yang belum dilakukan. Blocker
formula integrity hash sudah diperbaiki melalui helper shared.
