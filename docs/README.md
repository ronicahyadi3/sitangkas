# SITANGKAS Docs

Dokumentasi ini disusun sebagai peta konteks untuk manusia dan AI agent. Mulai dari file ini, lalu baca router agent sebelum membuka detail domain.

## Cara pakai cepat

1. Baca `00-ai-agent/DOCS_ROUTER.md`.
2. Pilih cluster sesuai kebutuhan pekerjaan.
3. Baca `README.md` di cluster tersebut.
4. Buka file detail table, migration, atau rule yang ditunjuk.
5. Untuk pekerjaan migration atau fresh install, selalu baca `06-migrations/FRESH_INSTALL_READINESS.md`.

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

Untuk pekerjaan migrasi payment LS, SPP/SPM/SP2D LS, dokumen pendukung,
anggaran LS, TTE/billing/bank yang diperlukan LS, baca:

1. [Hasil analisis payment LS](99-legacy/PAYMENT_LS_ANALYSIS.md)
2. [Rencana implementasi dan keputusan terbuka](99-legacy/PAYMENT_LS_IMPLEMENTATION_PLAN.md)
3. `99-legacy/OLD_PROJECT_REFERENCE.md`

Snapshot ini masih berupa analisis, bukan implementasi yang selesai. Prioritas
pengguna adalah LS dahulu, lalu payment lain setelah LS terimplementasi.

Untuk import `dump-keuangan-202609090855.sql` menjadi akun dan posisi, wajib
baca `99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md`. Dokumen tersebut menetapkan
bahwa `uuid` dan `access` tidak digunakan, akun dibuat berdasarkan NIK unik,
dan ID row legacy dipertahankan sebagai `user_positions.id` untuk kompatibilitas
`document` dan `document_process`. Pipeline read-only beserta validator sudah
lulus. Action transaksional sudah dibuat dalam kondisi disabled dan belum
terhubung ke entry point; opsi `--commit` belum tersedia.

## Cluster

| Folder | Isi | Kapan dibaca |
|---|---|---|
| `00-ai-agent` | Router, aturan baca, dan ringkasan invariant lintas domain | Selalu dibaca pertama oleh AI agent |
| `01-authentication` | `users`, `login_events`, login/logout, lock, session audit, Management Users | Saat mengubah autentikasi, akun, security state, Management Users, atau audit login |
| `02-master-data` | `instansis`, `jabatans`, `unit_kerjas`, seeder master, validasi master | Saat mengubah master organisasi, seeders, atau pilihan unit/jabatan |
| `03-user-positions` | `user_positions`, dokumen SK, active position session | Saat mengubah posisi pengguna, switching posisi, SK, dokumen posisi |
| `04-year-permissions` | Izin modifikasi data tahun historis dan event auditnya | Saat mengubah akses tahun lama atau audit penggunaan permission |
| `05-relationships` | Relasi lintas tabel, integrity rules, rollback dependency | Saat mengecek foreign key, dependency, audit lintas domain |
| `06-migrations` | Readiness fresh install dan catatan masalah migration | Saat review migration, install baru, rollback, atau deployment database |
| `07-realtime` | Reverb, presence channel, online monitoring, realtime notification, message helper | Saat mengubah WebSocket, broadcasting, Echo, online status, atau notifikasi realtime |
| `99-legacy` | Referensi project lama, pemetaan data legacy, aturan import, analisis dan rencana payment LS | Saat migrasi/import data lama, mencontoh fitur lama, atau melanjutkan implementasi LS |

## Prinsip umum

- Jangan membaca semua file secara default. Baca router, cluster README, lalu detail yang relevan.
- Jika ada konflik antara docs dan migration aktual, verifikasi migration aktual dan catat konflik sebelum mengubah kode.
- Jangan mengubah keputusan desain besar tanpa membaca konteks AI agent domain terkait.
- Jangan menyimpan secret, password, token, cookie, atau passphrase dalam metadata, notes, docs operasional, atau log.
- Jangan membuat, memodifikasi, atau menjalankan test suite tanpa konfirmasi eksplisit dari user.
