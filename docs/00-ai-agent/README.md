# AI Agent Entry Point

Folder ini adalah pintu masuk untuk AI agent yang bekerja pada SITANGKAS.

## Urutan baca wajib

1. Gunakan Laravel Boost sesuai bagian "Prioritas Laravel Boost" di bawah.
2. `DOCS_ROUTER.md` untuk memilih cluster.
3. `PROJECT_INVARIANTS.md` untuk aturan lintas domain.
4. `README.md` pada cluster yang dipilih.
5. File detail yang disebut oleh cluster.
6. `../06-migrations/FRESH_INSTALL_READINESS.md` bila menyentuh migration, schema, install, rollback, atau deployment database.

## Prioritas Laravel Boost

Laravel Boost adalah sumber konteks Laravel utama untuk AI agent, tetapi tidak
menggantikan invariant, keputusan domain, atau implementasi aktual proyek.
Gunakan urutan berikut saat tool-nya relevan dan tersedia:

1. Panggil `application-info` pada awal sesi kerja Laravel untuk membaca versi
   PHP, Laravel, database, dan paket yang benar-benar terpasang.
2. Sebelum mengubah kode Laravel atau ekosistemnya, gunakan `search-docs`
   dengan beberapa query topik yang ringkas. Batasi `packages` bila paket yang
   dituju sudah diketahui agar hasil sesuai versi proyek.
3. Sebelum membuat atau mengubah model dan migration, gunakan
   `database-schema`. Mulai dari mode ringkas, lalu filter tabel yang relevan
   untuk detail kolom, index, dan foreign key.
4. Untuk inspeksi data, prioritaskan `database-query` dengan query read-only.
   Jangan memakai tool ini untuk write, import, migration, atau operasi
   destruktif, dan jangan menggantinya dengan Tinker bila query read-only sudah
   cukup.
5. Gunakan `last-error` atau `read-log-entries` untuk error backend,
   `browser-logs` untuk error frontend terbaru, dan `get-absolute-url` sebelum
   membagikan URL proyek.

Hasil Boost harus dicocokkan dengan `PROJECT_INVARIANTS.md`, cluster domain
yang dipilih, kode terkait, migration, dan schema aktual. Bila ada konflik,
jangan mengambil keputusan desain besar secara otomatis; laporkan konflik dan
ikuti sumber proyek yang paling spesifik setelah verifikasi.

Jika server MCP Boost tidak tersedia pada sesi agent:

- nyatakan dengan jelas bahwa Boost tidak tersedia; jangan mengklaim telah
  menjalankan tool;
- periksa registrasi dengan `codex mcp list` dan konfigurasi
  `.codex/config.toml`;
- verifikasi server dengan `php artisan boost:mcp`, lalu mulai ulang sesi agent
  agar tool MCP dimuat;
- selama menunggu, gunakan inspeksi read-only terhadap kode dan command Artisan
  yang ada, serta catat bahwa `search-docs` belum dapat dijalankan.

Untuk auth context, urutan baca wajib tambahan:

1. `../01-authentication/AUTH_CONTEXT_DECISIONS.md`
2. `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`
3. `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md`

Untuk Management Users, urutan baca wajib tambahan:

1. `../01-authentication/MANAGEMENT_USERS_CURRENT_STATE.md`
2. `../01-authentication/USERS_TABLE.md`
3. `../03-user-positions/README.md`
4. `../04-year-permissions/README.md` bila menyentuh izin tahun historis

Untuk Reverb, WebSocket, online monitoring, realtime notification, atau
message helper realtime, urutan baca wajib tambahan:

1. `../07-realtime/README.md`
2. `../07-realtime/AI_AGENT_REVERB_REALTIME_CONTEXT.md`
3. `../07-realtime/ONLINE_PRESENCE_DECISIONS.md`

Untuk BSrE/eSign Client 2.2.0, TTE, validasi PDF, atau modal Svelte/Vite yang
terintegrasi dengan Bootstrap/Argon, urutan baca wajib tambahan:

1. `../08-esign/README.md`
2. `../08-esign/CURRENT_ESIGN_IMPLEMENTATION.md`
3. `../08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md` bila menyentuh
   `pdf_watermark_required`, preview/view/download PDF, Admin Super acting,
   guest, watermark/COPY-ID, audit delivery, atau cache verifikasi BSrE
4. `../08-esign/ESIGN_V2_CONTRACT_AND_BACKEND.md`
5. `../08-esign/ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md` bila menyentuh
   PDF editor, footer editable, multi-QR satu signer, operation checkpoint,
   partial signing/resume, atau worker visible
6. `../08-esign/ESIGN_AUTHORIZATION_AND_WORKFLOW_MATRIX.md` bila menyentuh
   signer, Admin Super, QR/footer, workflow, pembatalan/retry, atau download
7. `../08-esign/ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md` bila
   menyentuh artifact, QR, storage, history attempt, tabel sign legacy, atau
   aplikasi laporan
8. `../08-esign/ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md` bila menyentuh mapping,
   backfill, queue/lease, file migration, pause/resume, atau decommission
9. `../08-esign/ESIGN_V2_FRONTEND_MODAL.md`
10. `../08-esign/ESIGN_V2_IMPLEMENTATION_PLAN.md`

Nama concrete client yang telah diputuskan adalah `BsreClient`. Jangan
menghidupkan kembali nama `BsreV22Client`, credential legacy, atau request sign
queued yang membawa passphrase.

Kebijakan PDF memakai tepat satu flag posisi `pdf_watermark_required`: `true`
selalu watermark untuk preview/view/download, `false` boleh original setelah
authorization. Admin Super acting selalu efektif `false`; posisi bisnis nyata
Admin Super mengikuti flag posisi tersebut; guest public selalu watermark.
Flag ini bukan permission dan fitur masih berupa rancangan dokumentasi.

UI eSign wajib mengikuti main CSS Bootstrap 5 dan custom Argon Dashboard Pro 2.
Jangan menggunakan Tailwind sebagai basis komponen eSign meskipun dependency
Tailwind tersedia di repository.

## Jangan lakukan

- Jangan membuka semua docs jika pekerjaan hanya menyentuh satu domain.
- Jangan menyimpulkan migration siap hanya dari sintaks PHP.
- Jangan memakai numeric `id` sebagai business rule ketika docs meminta `kode`.
- Jangan mengembalikan route Management Users ke numeric `{user}` atau
  `{position}`; binding publik memakai encrypted route key, tetapi authorization
  tetap wajib dicek.
- Jangan membuat state session sebagai kolom master.
- Jangan menghapus histori audit untuk membuat implementasi lebih mudah.
- Jangan membuat atau menjalankan test suite/test command tanpa konfirmasi eksplisit dari user terlebih dahulu.
- Jangan menganggap `CurrentUserContext::activePosition()` selalu real row
  `user_positions`; pada Admin Super acting context, itu adalah effective overlay.
- Jangan menganggap session database yang masih aktif berarti user sedang
  online realtime.
- Jangan menyimpan passphrase TTE atau mengirimnya melalui queue; baca cluster
  `08-esign` sebelum mengubah alur sign/verify.
- Jangan mengimplementasikan `PREPARE_FOR_SIGNER`, input NIK manual, atau
  proxy-sign Admin Super. Scope awal hanya `SELF_SIGN` untuk real authenticated
  signer yang sah pada workflow step aktif.
