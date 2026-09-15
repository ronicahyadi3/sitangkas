# Legacy Docs

Cluster ini menjelaskan pemetaan data lama ke struktur SITANGKAS baru.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Lokasi project lama yang harus dicontoh | `OLD_PROJECT_REFERENCE.md` |
| Mapping dump lama ke master baru | `LEGACY_MAPPING.md` |
| Import `users.sql` menjadi akun distinct NIK dan posisi dengan ID legacy | [LEGACY_USERS_IMPORT_DECISIONS.md](LEGACY_USERS_IMPORT_DECISIONS.md) |
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
tetapi safety gate masih disabled dan action belum memiliki entry point.
`--commit` belum tersedia. Mulai setiap pekerjaan lanjutan dari bagian
"Handoff cepat untuk AI agent" pada
[LEGACY_USERS_IMPORT_DECISIONS.md](LEGACY_USERS_IMPORT_DECISIONS.md).

Jangan membuat, mengubah, atau menjalankan test suite tanpa permintaan eksplisit
pengguna.

## Payment LS

Pengguna memprioritaskan implementasi LS sebelum payment lain. Snapshot analisis
8 September 2026 menyatakan controller/view payment masih salinan legacy dan LS
belum terintegrasi. Mulai dari [hasil analisis](PAYMENT_LS_ANALYSIS.md), lalu
[rencana implementasi](PAYMENT_LS_IMPLEMENTATION_PLAN.md). Bedakan temuan snapshot,
rekomendasi, dan keputusan bisnis yang masih terbuka sebelum melanjutkan kode.
