# AI Agent Docs Router

Gunakan file ini untuk menentukan dokumen mana yang perlu dibaca.

## Routing berdasarkan kebutuhan

| Kebutuhan kerja | Baca pertama | Lanjutkan ke |
|---|---|---|
| Login, logout, lock akun, reset password, session invalidation | `../01-authentication/README.md` | `AI_AGENT_DATABASE_CONTEXT.md`, lalu `USERS_TABLE.md` atau `LOGIN_EVENTS_TABLE.md` sesuai tabel |
| Login context, post-login, Admin Super acting context, `auth/postLogin.blade.php` | `../01-authentication/README.md` | `AUTH_CONTEXT_DECISIONS.md`, `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`, lalu `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md` dan `../02-master-data/AI_AGENT_MASTER_ORGANIZATION_CONTEXT.md` |
| MFA, Google Authenticator, TOTP, recovery codes, atau step-up authentication | `../01-authentication/README.md` | `MFA_DECISIONS.md`, `AUTH_CONTEXT_DECISIONS.md`, `LOGIN_EVENTS_TABLE.md`, lalu `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` |
| Mengubah tabel `users` | `../01-authentication/README.md` | `USERS_TABLE.md`, lalu `AI_AGENT_DATABASE_CONTEXT.md` |
| Mengubah `login_events` atau audit autentikasi | `../01-authentication/README.md` | `LOGIN_EVENTS_TABLE.md`, lalu `AI_AGENT_DATABASE_CONTEXT.md` |
| Master `instansis`, `jabatans`, `unit_kerjas` | `../02-master-data/README.md` | `AI_AGENT_MASTER_DATA_CONTEXT.md`, table doc terkait, dan migration doc terkait |
| Seeder master data | `../02-master-data/README.md` | `AI_AGENT_MASTER_DATA_SEEDERS.md`, `VALIDATION.md`, lalu `../99-legacy/LEGACY_MAPPING.md` bila berasal dari dump lama |
| Posisi pengguna, active position, switching position | `../03-user-positions/README.md` | `AI_AGENT_USER_POSITIONS_CONTEXT.md`, `USER_POSITIONS_DESIGN.md`, `2026_07_28_140100_USER_POSITIONS.md` |
| Dokumen SK posisi pengguna | `../03-user-positions/README.md` | `2026_07_28_140200_USER_POSITION_DOCUMENTS.md`, lalu `USER_POSITIONS_DESIGN.md` |
| Composite foreign key `unit_kerjas` ke `user_positions` | `../03-user-positions/README.md` | `2026_07_28_140000_CANDIDATE_KEY.md`, `../02-master-data/UNIT_KERJAS_TABLE.md`, `../05-relationships/README.md` |
| Izin edit tahun historis | `../04-year-permissions/README.md` | `AI_AGENT_YEAR_PERMISSION_CONTEXT.md`, `2026_07_28_143000_USER_POSITION_YEAR_PERMISSIONS.md` |
| Event permission tahun historis | `../04-year-permissions/README.md` | `2026_07_28_143100_USER_POSITION_YEAR_PERMISSION_EVENTS.md` |
| Relasi lintas domain, FK, cascade/restrict, audit context | `../05-relationships/README.md` | `RELATIONSHIPS.md`, `RELATIONSHIPS_AND_RULES.md` |
| Fresh install Laravel, migration readiness, rollback | `../06-migrations/README.md` | `FRESH_INSTALL_READINESS.md`, lalu cluster domain yang tabelnya disentuh |
| Import atau mapping data legacy | `../99-legacy/README.md` | `OLD_PROJECT_REFERENCE.md`, `LEGACY_MAPPING.md`, lalu cluster target data |
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
| Auth controllers, guards, login actions | `../01-authentication/AI_AGENT_DATABASE_CONTEXT.md`; jika menyentuh context baca `../01-authentication/AUTH_CONTEXT_DECISIONS.md` dan `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` |
| MFA controllers, MFA middleware, TOTP setup/challenge | `../01-authentication/MFA_DECISIONS.md`, `../01-authentication/AUTH_CONTEXT_DECISIONS.md`, dan `../01-authentication/LOGIN_EVENTS_TABLE.md` |
| `resources/views/auth/postLogin.blade.php`, `resources/views/auth/context.blade.php` | `../01-authentication/AUTH_CONTEXT_DECISIONS.md` dan `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` |
| `app/Services/Auth/CurrentUserContext.php` | `../01-authentication/AUTH_CONTEXT_DECISIONS.md`, `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`, dan `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md` |
| Position selector middleware/controllers | `../03-user-positions/AI_AGENT_USER_POSITIONS_CONTEXT.md` |
| Policy or authorization for historical year writes | `../04-year-permissions/AI_AGENT_YEAR_PERMISSION_CONTEXT.md` |

## Bila ragu

1. Baca cluster README yang paling dekat dengan tabel utama.
2. Baca `../05-relationships/README.md` jika ada foreign key lintas domain.
3. Baca `../06-migrations/FRESH_INSTALL_READINESS.md` jika menyentuh urutan migration.
