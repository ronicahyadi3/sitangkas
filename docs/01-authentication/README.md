# Authentication Docs

Cluster ini menjelaskan akun, autentikasi, dan audit login.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Gambaran domain autentikasi untuk AI agent | `AI_AGENT_DATABASE_CONTEXT.md` |
| Keputusan arsitektur login context, Admin Super acting context, dan `postLogin` | `AUTH_CONTEXT_DECISIONS.md` |
| Keputusan MFA TOTP/Google Authenticator untuk Admin Super dan non-admin | `MFA_DECISIONS.md` |
| Rekomendasi implementasi lanjutan MFA untuk AI agent | `MFA_RECOMMENDATIONS.md` |
| Runbook operator untuk reset MFA via Artisan | `MFA_RESET_COMMAND_RUNBOOK.md` |
| Snapshot Management Users, keamanan akun, posisi, dan rekomendasi lanjut | `MANAGEMENT_USERS_CURRENT_STATE.md` |
| Keputusan halaman keamanan akun dan recovery-code regeneration | `PROFILE_SECURITY_DECISIONS.md` |
| Policy enrichment data audit `login_events` | `LOGIN_EVENTS_ENRICHMENT_POLICY.md` |
| Runbook GeoIP/ASN MaxMind untuk enrichment `login_events` | `GEOIP_MAXMIND_RUNBOOK.md` |
| Decision IP risk/VPN/proxy/Tor audit-only dan hold provider | `IP_RISK_DECISIONS.md` |
| Snapshot implementasi auth context saat ini | `CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` |
| Detail tabel akun dan state keamanan | `USERS_TABLE.md` |
| Detail histori login/logout/lock/session event | `LOGIN_EVENTS_TABLE.md` |

## Aturan inti

- `users` adalah current account state.
- `login_events` adalah histori autentikasi append-only.
- Enrichment `login_events` wajib mengikuti `LOGIN_EVENTS_ENRICHMENT_POLICY.md`;
  nilai `null` pada kolom device, GeoIP, ASN, VPN/proxy/Tor, risk, timezone,
  fingerprint, integrity, dan retention dapat berarti data belum tersedia atau
  belum diperiksa.
- Setup GeoIP/ASN MaxMind wajib mengikuti `GEOIP_MAXMIND_RUNBOOK.md`; command
  status resmi adalah `php artisan auth:geoip-status`.
- IP risk/VPN/proxy/Tor wajib mengikuti `IP_RISK_DECISIONS.md`; mode resmi
  saat ini adalah audit-only, provider sengaja di-hold, dan blocking login
  belum boleh dibuat.
- Jangan menambah histori login langsung ke `users`.
- Jangan menyimpan secret, token, cookie, passphrase, password asli, atau session id mentah.
- Login berhasil memperbarui ringkasan di `users` dan menulis event.
- Login gagal menulis event; jika user ditemukan, update failed count sesuai policy.
- `login.context` hanya untuk memilih `UserPosition` nyata.
- `login.post` adalah flow resmi Admin Super untuk memilih acting context manual.
- MFA memakai TOTP kompatibel Google Authenticator; wajib untuk real active
  position Admin Super dan optional/enrollable untuk non-Admin Super.
- Rekomendasi lanjutan MFA, recovery code, reset MFA Artisan, dan alternatif
  WebAuthn/security key dibaca dari `MFA_RECOMMENDATIONS.md`.
- Halaman pengelolaan keamanan akun setelah login wajib mengikuti
  `PROFILE_SECURITY_DECISIONS.md`.
- Reset MFA resmi saat ini tersedia melalui Management Users untuk Admin Super
  dan command operator `php artisan auth:mfa-reset`; cara command ada di
  `MFA_RESET_COMMAND_RUNBOOK.md`.
- Management Users mengikuti snapshot implementasi
  `MANAGEMENT_USERS_CURRENT_STATE.md`.
- Management Users memakai encrypted route binding untuk `User` dan
  `UserPosition`; URL numeric polos untuk `{user}` atau `{position}` harus
  ditolak sebelum masuk aksi controller.
- Endpoint users DataTable memakai server-side Yajra dengan filter penting dan
  presenter payload agar controller tetap fokus pada query dan authorization.
- `CurrentUserContext::realActivePosition()` adalah posisi asli dari `user_positions`.
- `CurrentUserContext::activePosition()` adalah effective context untuk modul,
  dashboard, navbar, dan sidebar.
- Modul internal wajib membaca context melalui service, bukan langsung dari session `acting_*`.
- Untuk Admin Super acting context, `activePosition()->id` tetap id real
  Admin Super, sedangkan `jabatan_id`, `instansi_id`, dan `unit_kerja_id`
  mengikuti pilihan `login.post`.

## Pakai cluster lain bila

- Pekerjaan menyangkut posisi setelah login: baca `../03-user-positions/README.md`.
- Pekerjaan menyangkut audit aktivitas bisnis lintas domain: baca `../05-relationships/README.md`.
- Pekerjaan menyentuh migration: baca `../06-migrations/FRESH_INSTALL_READINESS.md`.
- Pekerjaan menyangkut Reverb, online monitoring, atau status session realtime:
  baca `../07-realtime/README.md`.
