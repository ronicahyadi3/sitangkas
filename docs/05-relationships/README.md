# Relationships Docs

Cluster ini menjelaskan relasi lintas domain, foreign key, dan aturan integritas.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Diagram relasi master organisasi dengan tabel lain | `RELATIONSHIPS.md` |
| Aturan integritas, penghapusan, audit, dan cache | `RELATIONSHIPS_AND_RULES.md` |

## Aturan inti

- Tabel anak harus dihapus atau dilepas relasinya sebelum tabel induk saat rollback.
- Master tidak boleh dihapus fisik jika sudah direferensikan.
- Composite foreign key `user_positions(unit_kerja_id, instansi_id)` harus menjaga konsistensi unit dan instansi.
- Audit aktivitas bisnis minimal harus menyimpan konteks user dan posisi.

## Pakai cluster lain bila

- Relasi menyentuh master data: baca `../02-master-data/README.md`.
- Relasi menyentuh posisi pengguna: baca `../03-user-positions/README.md`.
- Relasi menyentuh migration order atau fresh install: baca `../06-migrations/FRESH_INSTALL_READINESS.md`.
