# User Positions Docs

Cluster ini menjelaskan posisi pengguna, dokumen SK, dan aturan pemilihan posisi aktif pada session.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Konteks utama AI agent untuk posisi pengguna dan dokumen | `AI_AGENT_USER_POSITIONS_CONTEXT.md` |
| Desain bisnis `user_positions` dan dokumen SK | `USER_POSITIONS_DESIGN.md` |
| Candidate key `(unit_kerjas.id, unit_kerjas.instansi_id)` | `2026_07_28_140000_CANDIDATE_KEY.md` |
| Detail migration `user_positions` | `2026_07_28_140100_USER_POSITIONS.md` |
| Detail migration `user_position_documents` | `2026_07_28_140200_USER_POSITION_DOCUMENTS.md` |
| Snapshot integrasi Management Users dengan posisi, SK, dan security modal | `../01-authentication/MANAGEMENT_USERS_CURRENT_STATE.md` |

## Aturan inti

- `is_active` berarti posisi tersedia untuk dipilih.
- Posisi yang sedang dipakai disimpan di session, bukan di tabel `user_positions`.
- Satu user dapat memiliki banyak posisi aktif.
- Saat switch posisi, validasi ownership, active state, soft delete, dan masa berlaku.
- `instansi_id` tidak boleh bebas dari request; ambil dari posisi valid.
- Untuk Admin Super, acting context bukan row `user_positions`; lihat
  `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` sebelum memakai
  `CurrentUserContext::activePosition()->id`.
- Dokumen SK berada di `user_position_documents`.
- Perubahan dokumen utama harus dikelola dalam transaksi.
- Halaman Management Users membuat akun terlebih dahulu, lalu menambahkan
  posisi melalui modal posisi. Jangan membuat posisi awal otomatis saat create
  user tanpa decision baru.

## Pakai cluster lain bila

- Pekerjaan menyangkut master `jabatans`, `instansis`, atau `unit_kerjas`: baca `../02-master-data/README.md`.
- Pekerjaan menyangkut permission tahun historis: baca `../04-year-permissions/README.md`.
- Pekerjaan menyangkut migration order: baca `../06-migrations/FRESH_INSTALL_READINESS.md`.
