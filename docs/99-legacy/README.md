# Legacy Docs

Cluster ini menjelaskan pemetaan data lama ke struktur SITANGKAS baru.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Lokasi project lama yang harus dicontoh | `OLD_PROJECT_REFERENCE.md` |
| Mapping dump lama ke master baru | `LEGACY_MAPPING.md` |
| Seeder master data hasil mapping | `../02-master-data/AI_AGENT_MASTER_DATA_SEEDERS.md` |
| Validasi jumlah dan konsistensi hasil import | `../02-master-data/VALIDATION.md` |

## Aturan inti

- Kode canonical harus deterministik dan stabil.
- Jangan mengubah kode setelah dipakai policy, konfigurasi, integrasi, atau laporan.
- Jangan mengembalikan `skpd_id` tanpa keputusan schema yang eksplisit.
- Data legacy dipakai sebagai sumber bootstrap, bukan sinkronisasi harian otomatis.
- Project lama referensi utama berada di `C:\Apache24\htdocs\sitangkas`.
- Jangan mengubah project lama kecuali user meminta secara eksplisit.
