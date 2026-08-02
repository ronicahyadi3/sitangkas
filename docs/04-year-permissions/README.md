# Year Permissions Docs

Cluster ini menjelaskan izin modifikasi data tahun historis dan event auditnya.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Konteks bisnis dan authorization tahun historis | `AI_AGENT_YEAR_PERMISSION_CONTEXT.md` |
| Detail tabel permission | `2026_07_28_143000_USER_POSITION_YEAR_PERMISSIONS.md` |
| Detail tabel event permission | `2026_07_28_143100_USER_POSITION_YEAR_PERMISSION_EVENTS.md` |

## Aturan inti

- Permission melekat pada `user_position_id`.
- Permission tahun historis tidak mengganti role atau policy dasar.
- Target tahun berjalan memakai aturan normal.
- Target tahun historis butuh permission aktif dan belum kedaluwarsa.
- Target tahun mendatang tidak otomatis boleh.
- Semua grant, use, deny, revoke, expire, reject, dan cancel harus memiliki jejak audit.

## Pakai cluster lain bila

- Pekerjaan membutuhkan active user position: baca `../03-user-positions/README.md`.
- Pekerjaan menyentuh audit lintas tabel: baca `../05-relationships/README.md`.
- Pekerjaan menyentuh migration: baca `../06-migrations/FRESH_INSTALL_READINESS.md`.
