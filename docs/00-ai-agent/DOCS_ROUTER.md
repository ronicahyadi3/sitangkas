# AI Agent Docs Router

Gunakan file ini untuk menentukan dokumen mana yang perlu dibaca.

## Routing berdasarkan kebutuhan

| Kebutuhan kerja | Baca pertama | Lanjutkan ke |
|---|---|---|
| Login, logout, lock akun, reset password, session invalidation | `../01-authentication/README.md` | `AI_AGENT_DATABASE_CONTEXT.md`, lalu `USERS_TABLE.md` atau `LOGIN_EVENTS_TABLE.md` sesuai tabel |
| Management Users, CRUD akun, encrypted URL user/posisi, DataTables users, keamanan akun, reset password Admin Super, reset MFA browser, lock/unlock dari UI, audit trail | `../01-authentication/README.md` | `MANAGEMENT_USERS_CURRENT_STATE.md`, `USERS_TABLE.md`, `LOGIN_EVENTS_TABLE.md`, lalu `../03-user-positions/README.md` bila menyentuh posisi dan `../04-year-permissions/README.md` bila menyentuh izin tahun historis |
| Login context, post-login, Admin Super acting context, `auth/postLogin.blade.php` | `../01-authentication/README.md` | `AUTH_CONTEXT_DECISIONS.md`, `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`, lalu `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md` dan `../02-master-data/AI_AGENT_MASTER_ORGANIZATION_CONTEXT.md` |
| MFA, Google Authenticator, TOTP, recovery codes, atau step-up authentication | `../01-authentication/README.md` | `MFA_DECISIONS.md`, `AUTH_CONTEXT_DECISIONS.md`, `LOGIN_EVENTS_TABLE.md`, lalu `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` |
| Mengubah tabel `users` | `../01-authentication/README.md` | `USERS_TABLE.md`, lalu `AI_AGENT_DATABASE_CONTEXT.md` |
| Mengubah `login_events` atau audit autentikasi | `../01-authentication/README.md` | `LOGIN_EVENTS_TABLE.md`, `LOGIN_EVENTS_ENRICHMENT_POLICY.md`, `GEOIP_MAXMIND_RUNBOOK.md` bila menyentuh GeoIP/ASN, `IP_RISK_DECISIONS.md` bila menyentuh VPN/proxy/Tor/risk, lalu `AI_AGENT_DATABASE_CONTEXT.md` |
| Master `instansis`, `jabatans`, `unit_kerjas` | `../02-master-data/README.md` | `AI_AGENT_MASTER_DATA_CONTEXT.md`, table doc terkait, dan migration doc terkait |
| Seeder master data | `../02-master-data/README.md` | `AI_AGENT_MASTER_DATA_SEEDERS.md`, `VALIDATION.md`, lalu `../99-legacy/LEGACY_MAPPING.md` bila berasal dari dump lama |
| Posisi pengguna, active position, switching position | `../03-user-positions/README.md` | `AI_AGENT_USER_POSITIONS_CONTEXT.md`, `USER_POSITIONS_DESIGN.md`, `2026_07_28_140100_USER_POSITIONS.md` |
| Dokumen SK posisi pengguna | `../03-user-positions/README.md` | `2026_07_28_140200_USER_POSITION_DOCUMENTS.md`, lalu `USER_POSITIONS_DESIGN.md` |
| Composite foreign key `unit_kerjas` ke `user_positions` | `../03-user-positions/README.md` | `2026_07_28_140000_CANDIDATE_KEY.md`, `../02-master-data/UNIT_KERJAS_TABLE.md`, `../05-relationships/README.md` |
| Izin edit tahun historis | `../04-year-permissions/README.md` | `AI_AGENT_YEAR_PERMISSION_CONTEXT.md`, `2026_07_28_143000_USER_POSITION_YEAR_PERMISSIONS.md` |
| Event permission tahun historis | `../04-year-permissions/README.md` | `2026_07_28_143100_USER_POSITION_YEAR_PERMISSION_EVENTS.md` |
| Relasi lintas domain, FK, cascade/restrict, audit context | `../05-relationships/README.md` | `RELATIONSHIPS.md`, `RELATIONSHIPS_AND_RULES.md` |
| Fresh install Laravel, migration readiness, rollback | `../06-migrations/README.md` | `FRESH_INSTALL_READINESS.md`, lalu cluster domain yang tabelnya disentuh |
| Reverb, WebSocket, broadcasting, Laravel Echo | `../07-realtime/README.md` | `CURRENT_REALTIME_IMPLEMENTATION.md`, lalu `AI_AGENT_REVERB_REALTIME_CONTEXT.md` |
| Monitoring siapa online, idle, away, offline, session aktif | `../07-realtime/README.md` | `CURRENT_REALTIME_IMPLEMENTATION.md`, lalu `ONLINE_PRESENCE_DECISIONS.md` |
| Realtime notification | `../07-realtime/README.md` | `CURRENT_REALTIME_IMPLEMENTATION.md`, lalu `REALTIME_FEATURE_ROADMAP.md` |
| Message helper/helpdesk realtime | `../07-realtime/README.md` | `CURRENT_REALTIME_IMPLEMENTATION.md`, lalu `REALTIME_FEATURE_ROADMAP.md` |
| Import atau mapping data legacy | `../99-legacy/README.md` | `OLD_PROJECT_REFERENCE.md`, `LEGACY_MAPPING.md`, lalu cluster target data |
| Import `users.sql`, distinct akun berdasarkan NIK, mempertahankan ID posisi untuk `document`/`document_process` | `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md` | `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md`, `../01-authentication/USERS_TABLE.md`, lalu `../05-relationships/README.md` |
| Analisis atau implementasi payment LS, alur SPP/SPM/SP2D, dependensi dokumen/anggaran/TTE/bank LS | [PAYMENT_LS_ANALYSIS.md](../99-legacy/PAYMENT_LS_ANALYSIS.md) | [PAYMENT_LS_IMPLEMENTATION_PLAN.md](../99-legacy/PAYMENT_LS_IMPLEMENTATION_PLAN.md), `OLD_PROJECT_REFERENCE.md`, lalu docs auth/posisi/tahun/master sesuai perubahan |
| Membandingkan LS dengan payment lain atau melanjutkan payment setelah LS | [PAYMENT_LS_ANALYSIS.md](../99-legacy/PAYMENT_LS_ANALYSIS.md) | Bagian perbandingan payment dan status LS pada [rencana implementasi](../99-legacy/PAYMENT_LS_IMPLEMENTATION_PLAN.md); payment lain bukan bukti integrasi baru sudah selesai |
| Mencontoh fitur/perilaku project lama SITANGKAS | `../99-legacy/README.md` | `OLD_PROJECT_REFERENCE.md`, lalu baca file terkait di `C:\Apache24\htdocs\sitangkas` |

## Routing berdasarkan file kode

| File kode yang disentuh | Docs yang harus dibaca |
|---|---|
| `database/migrations/*users*` | `../01-authentication/README.md` |
| `database/migrations/*login_events*` | `../01-authentication/README.md` |
| `database/migrations/*instansis*`, `*jabatans*`, `*unit_kerjas*` | `../02-master-data/README.md` dan `../06-migrations/FRESH_INSTALL_READINESS.md` |
| `database/migrations/*user_positions*` | `../03-user-positions/README.md` dan `../06-migrations/FRESH_INSTALL_READINESS.md` |
| `database/migrations/*year_permission*` | `../04-year-permissions/README.md` dan `../06-migrations/FRESH_INSTALL_READINESS.md` |
| `database/seeders/*Master*`, `*Instansi*`, `*Jabatan*`, `*UnitKerja*` | `../02-master-data/AI_AGENT_MASTER_DATA_SEEDERS.md` |
| Auth controllers, guards, login actions | `../01-authentication/AI_AGENT_DATABASE_CONTEXT.md`; jika menyentuh `login_events` enrichment baca `../01-authentication/LOGIN_EVENTS_ENRICHMENT_POLICY.md`; jika menyentuh context baca `../01-authentication/AUTH_CONTEXT_DECISIONS.md` dan `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` |
| MFA controllers, MFA middleware, TOTP setup/challenge | `../01-authentication/MFA_DECISIONS.md`, `../01-authentication/AUTH_CONTEXT_DECISIONS.md`, dan `../01-authentication/LOGIN_EVENTS_TABLE.md` |
| `app/Http/Controllers/Users/*`, `app/Actions/UserManagement/*`, `app/Actions/UserSecurity/*`, `app/Services/User/*`, `app/Support/UserManagement/*`, `resources/views/users/*` | `../01-authentication/MANAGEMENT_USERS_CURRENT_STATE.md`; tambah `../03-user-positions/README.md` untuk posisi/SK dan `../04-year-permissions/README.md` untuk izin tahun historis |
| `app/Models/User.php`, `app/Models/UserPosition.php` route binding Management Users | `../01-authentication/MANAGEMENT_USERS_CURRENT_STATE.md` dan `../03-user-positions/README.md` |
| `app/Actions/LegacyImport/*`, `app/Services/LegacyImport/*`, `app/Data/LegacyImport/*`, importer, migration, atau command yang membaca dump legacy `users` | Mulai dari bagian handoff `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md`, lalu `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md` dan `../06-migrations/FRESH_INSTALL_READINESS.md` |
| `resources/views/auth/postLogin.blade.php`, `resources/views/users/positions-switch.blade.php` | `../01-authentication/AUTH_CONTEXT_DECISIONS.md` dan `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` |
| `app/Services/Auth/CurrentUserContext.php` | `../01-authentication/AUTH_CONTEXT_DECISIONS.md`, `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`, dan `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md` |
| Position selector middleware/controllers | `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md` |
| Policy or authorization for historical year writes | `../04-year-permissions/AI_AGENT_YEAR_PERMISSION_CONTEXT.md` |
| `app/Http/Controllers/Payment/LS/*`, `app/Models/Payment/LS.php`, `resources/views/Payment/LS/*` | [Analisis LS](../99-legacy/PAYMENT_LS_ANALYSIS.md) dan [rencana LS](../99-legacy/PAYMENT_LS_IMPLEMENTATION_PLAN.md); tambah docs konteks aktif, organisasi, dan izin tahun sesuai perubahan |
| `app/Http/Controllers/Data/*`, shared components payment, route dokumen/TTE/bank untuk LS | [Analisis LS](../99-legacy/PAYMENT_LS_ANALYSIS.md), terutama dependensi endpoint dan scope; [rencana LS](../99-legacy/PAYMENT_LS_IMPLEMENTATION_PLAN.md) untuk batas implementasi |
| `routes/channels.php`, `config/reverb.php`, broadcasting config | `../07-realtime/README.md` dan `../07-realtime/CURRENT_REALTIME_IMPLEMENTATION.md` |
| `resources/js/app.js`, `resources/js/bootstrap.js`, Echo/Reverb client setup | `../07-realtime/README.md` dan `../07-realtime/CURRENT_REALTIME_IMPLEMENTATION.md` |
| Online presence controllers, events, listeners, middleware, atau views | `../07-realtime/CURRENT_REALTIME_IMPLEMENTATION.md` dan `../07-realtime/ONLINE_PRESENCE_DECISIONS.md` |
| `database/migrations/*presence*`, `*online*`, `*realtime*` | `../07-realtime/CURRENT_REALTIME_IMPLEMENTATION.md` dan `../06-migrations/FRESH_INSTALL_READINESS.md` |
| Broadcast notifications atau realtime notification UI | `../07-realtime/CURRENT_REALTIME_IMPLEMENTATION.md` dan `../07-realtime/REALTIME_FEATURE_ROADMAP.md` |

## Bila ragu

1. Baca cluster README yang paling dekat dengan tabel utama.
2. Baca `../05-relationships/README.md` jika ada foreign key lintas domain.
3. Baca `../06-migrations/FRESH_INSTALL_READINESS.md` jika menyentuh urutan migration.
