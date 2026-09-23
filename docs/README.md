# SITANGKAS Docs

Dokumentasi ini disusun sebagai peta konteks untuk manusia dan AI agent. Mulai dari file ini, lalu baca router agent sebelum membuka detail domain.

## Cara pakai cepat

1. Baca `00-ai-agent/README.md` dan gunakan Laravel Boost sesuai prioritas tool
   yang dijelaskan di sana.
2. Baca `00-ai-agent/DOCS_ROUTER.md`.
3. Pilih cluster sesuai kebutuhan pekerjaan.
4. Baca `README.md` di cluster tersebut.
5. Buka file detail table, migration, atau rule yang ditunjuk.
6. Untuk pekerjaan migration atau fresh install, selalu baca `06-migrations/FRESH_INSTALL_READINESS.md`.

Untuk pekerjaan login context, Admin Super acting context, dashboard/navbar
context, atau `CurrentUserContext`, wajib baca:

1. `01-authentication/AUTH_CONTEXT_DECISIONS.md`
2. `01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`
3. `03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md`

Untuk pekerjaan Management Users, CRUD akun, posisi user dari halaman users,
keamanan akun, reset password Admin Super, reset MFA browser, lock/unlock,
force change password, encrypted route key `{user}`/`{position}`, Yajra
DataTables, audit trail Management Users, dokumen SK posisi, atau izin tahun
historis dari modal users, wajib baca:

1. `01-authentication/MANAGEMENT_USERS_CURRENT_STATE.md`
2. `01-authentication/USERS_TABLE.md`
3. `03-user-positions/README.md`
4. `04-year-permissions/README.md` bila menyentuh izin tahun historis

Untuk pekerjaan Reverb, WebSocket, online monitoring, realtime notification,
atau message helper realtime, wajib baca:

1. `07-realtime/README.md`
2. `07-realtime/CURRENT_REALTIME_IMPLEMENTATION.md`
3. `07-realtime/AI_AGENT_REVERB_REALTIME_CONTEXT.md`
4. `07-realtime/ONLINE_PRESENCE_DECISIONS.md`

Untuk pekerjaan BSrE/eSign Client 2.2.0, TTE, verifikasi dokumen, modal
penandatanganan, Svelte, Vite, Bootstrap/Argon, main CSS, atau PDF viewer TTE,
wajib baca:

1. `08-esign/README.md`
2. `08-esign/CURRENT_ESIGN_IMPLEMENTATION.md`
3. `08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md` bila menyentuh
   `pdf_watermark_required`, preview/view/download, guest, COPY-ID, watermark,
   audit delivery, atau cache verifikasi BSrE
4. `08-esign/ESIGN_V2_CONTRACT_AND_BACKEND.md`
5. `08-esign/ESIGN_VISIBLE_EDITOR_AND_MULTI_QR_DESIGN.md` bila menyentuh
   source PDF editor, footer, placement, beberapa QR satu signer, operation
   checkpoint, partial resume, atau worker visible
6. `08-esign/ESIGN_DOCUMENT_LIFECYCLE_AND_REPORTING_COMPATIBILITY.md` bila
   menyentuh lifecycle file, QR, storage, attempt/event, atau kompatibilitas
   `before_signs`/`after_signs`
7. `08-esign/ESIGN_RESUMABLE_MIGRATION_RUNBOOK.md` bila menyentuh mapping
   resumable, zero-downtime, queue/lease, pause/resume, recovery, atau cleanup
8. `08-esign/ESIGN_V2_FRONTEND_MODAL.md`
9. `08-esign/ESIGN_V2_IMPLEMENTATION_PLAN.md`

Dokumen cluster tersebut adalah sumber keputusan integrasi baru. Kode project
lama dan collection Postman hanya menjadi bukti referensi; jangan menyalin
credential, hardcoded signer, atau pola keamanan legacy.

Untuk pekerjaan migrasi payment LS, SPP/SPM/SP2D LS, dokumen pendukung,
anggaran LS, TTE/billing/bank yang diperlukan LS, baca:

1. [Kondisi implementasi Payment LS saat ini](99-legacy/PAYMENT_LS_CURRENT_IMPLEMENTATION.md)
2. [Hasil analisis awal payment LS](99-legacy/PAYMENT_LS_ANALYSIS.md)
3. [Rencana implementasi dan keputusan terbuka](99-legacy/PAYMENT_LS_IMPLEMENTATION_PLAN.md)
4. `99-legacy/OLD_PROJECT_REFERENCE.md`

SPP LS sedang diintegrasikan. Create/upload SPP utama sudah menulis source
artifact canonical ke private storage dan delivery route khusus LS SPP sudah
tersedia di source code. Update SPP, dokumen pendamping, runtime delivery, SPM,
SP2D, serta alur TTE LS end-to-end masih mempunyai blocker yang dicatat pada
dokumen kondisi aktual. Prioritas pengguna tetap LS dahulu, lalu payment lain.

Untuk import `dump-keuangan-202609090855.sql` menjadi akun dan posisi, wajib
baca `99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md`. Dokumen tersebut menetapkan
bahwa `uuid` dan `access` tidak digunakan, akun dibuat berdasarkan NIK unik,
dan ID row legacy dipertahankan sebagai `user_positions.id` untuk kompatibilitas
`document` dan `document_process`. Pipeline read-only beserta validator sudah
lulus. Action transaksional, entry point command, structured log, dan writer
laporan commit sudah terhubung. Safety gate sedang aktif sementara untuk
maintenance attempt 2026-09-16. Attempt pertama di-rollback penuh karena false
negative metadata counter `AUTO_INCREMENT`; target tetap kosong dan validator
sudah diperbaiki memakai ID maksimum serta atribut schema. Retry tetap harus
dijalankan operator, lalu gate dikembalikan ke `false`. Strategi password
produksi mempertahankan hash legacy canonical dan mewajibkan perubahan
password; shared password hanya boleh sebagai override development `local`.
Status akun final diturunkan dari posisi canonical aktif. SK legacy ditunda ke
importer terpisah dan hanya file fisik valid yang
boleh disalin dari staging `public/SuratKeterangan` ke storage private. Opsi
`--commit` dan `--fingerprint=` sudah terdaftar. Mode commit menghitung ulang
analyzer read-only, memeriksa fingerprint, meminta konfirmasi operator, lalu
memanggil action.

## Cluster

| Folder | Isi | Kapan dibaca |
|---|---|---|
| `00-ai-agent` | Router, aturan baca, dan ringkasan invariant lintas domain | Selalu dibaca pertama oleh AI agent |
| `01-authentication` | `users`, `login_events`, login/logout, lock, session audit, Management Users | Saat mengubah autentikasi, akun, security state, Management Users, atau audit login |
| `02-master-data` | `instansis`, `jabatans`, `unit_kerjas`, seeder master, validasi master | Saat mengubah master organisasi, seeders, atau pilihan unit/jabatan |
| `03-user-positions` | `user_positions`, dokumen SK, active position session, dan flag kebijakan watermark PDF | Saat mengubah posisi pengguna, switching posisi, SK, dokumen posisi, atau `pdf_watermark_required` |
| `04-year-permissions` | Izin modifikasi data tahun historis dan event auditnya | Saat mengubah akses tahun lama atau audit penggunaan permission |
| `05-relationships` | Relasi lintas tabel, integrity rules, rollback dependency | Saat mengecek foreign key, dependency, audit lintas domain |
| `06-migrations` | Readiness fresh install dan catatan masalah migration | Saat review migration, install baru, rollback, atau deployment database |
| `07-realtime` | Reverb, presence channel, online monitoring, realtime notification, message helper | Saat mengubah WebSocket, broadcasting, Echo, online status, atau notifikasi realtime |
| `08-esign` | Arsitektur BSrE/eSign Client 2.2.0, delivery PDF/watermark, editor visible dan multi-QR serial, kontrak backend, modal Svelte/Vite dengan Bootstrap/Argon, keamanan, performa, dan rollout | Saat mengubah TTE, preview/view/download PDF, watermark/footer, validasi PDF, operasi signature, sertifikat signer, UI modal, atau koneksi eSign |
| `99-legacy` | Referensi project lama, pemetaan data legacy, aturan import, analisis dan rencana payment LS | Saat migrasi/import data lama, mencontoh fitur lama, atau melanjutkan implementasi LS |

## Prinsip umum

- Jangan membaca semua file secara default. Baca router, cluster README, lalu detail yang relevan.
- Jika ada konflik antara docs dan migration aktual, verifikasi migration aktual dan catat konflik sebelum mengubah kode.
- Jangan mengubah keputusan desain besar tanpa membaca konteks AI agent domain terkait.
- Jangan menyimpan secret, password, token, cookie, atau passphrase dalam metadata, notes, docs operasional, atau log.
- Jangan membuat, memodifikasi, atau menjalankan test suite tanpa konfirmasi eksplisit dari user.
