# AI Agent Entry Point

Folder ini adalah pintu masuk untuk AI agent yang bekerja pada SITANGKAS.

## Urutan baca wajib

1. `DOCS_ROUTER.md` untuk memilih cluster.
2. `PROJECT_INVARIANTS.md` untuk aturan lintas domain.
3. `README.md` pada cluster yang dipilih.
4. File detail yang disebut oleh cluster.
5. `../06-migrations/FRESH_INSTALL_READINESS.md` bila menyentuh migration, schema, install, rollback, atau deployment database.

Untuk auth context, urutan baca wajib tambahan:

1. `../01-authentication/AUTH_CONTEXT_DECISIONS.md`
2. `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`
3. `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md`

Untuk Reverb, WebSocket, online monitoring, realtime notification, atau
message helper realtime, urutan baca wajib tambahan:

1. `../07-realtime/README.md`
2. `../07-realtime/AI_AGENT_REVERB_REALTIME_CONTEXT.md`
3. `../07-realtime/ONLINE_PRESENCE_DECISIONS.md`

## Jangan lakukan

- Jangan membuka semua docs jika pekerjaan hanya menyentuh satu domain.
- Jangan menyimpulkan migration siap hanya dari sintaks PHP.
- Jangan memakai numeric `id` sebagai business rule ketika docs meminta `kode`.
- Jangan membuat state session sebagai kolom master.
- Jangan menghapus histori audit untuk membuat implementasi lebih mudah.
- Jangan membuat atau menjalankan test suite/test command tanpa konfirmasi eksplisit dari user terlebih dahulu.
- Jangan menganggap `CurrentUserContext::activePosition()` selalu real row
  `user_positions`; pada Admin Super acting context, itu adalah effective overlay.
- Jangan menganggap session database yang masih aktif berarti user sedang
  online realtime.
