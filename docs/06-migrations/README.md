# Migration Docs

Cluster ini bukan tempat semua detail migration. Detail migration tetap berada di cluster domain masing-masing agar mudah dibaca sesuai kebutuhan.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Fresh install, readiness Laravel, migration blockers | `FRESH_INSTALL_READINESS.md` |
| Migration auth | `../01-authentication/README.md` |
| Migration audit/filter Management Users | `../01-authentication/MANAGEMENT_USERS_CURRENT_STATE.md` dan `FRESH_INSTALL_READINESS.md` |
| Migration master data | `../02-master-data/README.md` |
| Migration posisi pengguna | `../03-user-positions/README.md` |
| Migration permission tahun historis | `../04-year-permissions/README.md` |
| Rollback lintas tabel | `../05-relationships/README.md` |

## Aturan inti

- Urutan file migration Laravel mengikuti timestamp filename.
- Foreign key ke tabel lain hanya aman jika tabel target sudah dibuat lebih dulu.
- Constraint duplicate harus dihapus atau digabung sebelum fresh install.
- SQL spesifik MySQL harus dicocokkan dengan `DB_CONNECTION` pada `.env` dan `.env.example`.
