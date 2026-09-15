# Project Invariants For AI Agents

Ini ringkasan aturan yang tidak boleh dilanggar lintas domain.

## Akun dan autentikasi

- `users` menyimpan state akun saat ini, bukan histori lengkap.
- Histori autentikasi ditulis ke `login_events`.
- `login_events` bersifat append-only: tidak ada update/delete alur normal.
- Route Management Users untuk parameter `{user}` dan nested `{position}`
  memakai encrypted route key; numeric route param polos tidak boleh diterima.
- Encrypted route key hanya obfuscation URL. Authorization dan scope tetap wajib
  dicek pada setiap aksi.
- Enrichment `login_events` mengikuti
  `../01-authentication/LOGIN_EVENTS_ENRICHMENT_POLICY.md`; `null` pada kolom
  device, GeoIP, ASN, VPN/proxy/Tor, risk, timezone, fingerprint, integrity,
  atau retention berarti data belum tersedia atau belum diperiksa, bukan bug.
- IP risk enrichment untuk VPN/proxy/Tor sedang di-hold; jangan mengaktifkan
  provider berbayar, free-tier, atau provider parsial tanpa persetujuan user
  dan decision baru.
- Password, token, cookie, session id mentah, passphrase, dan credential tidak boleh disimpan di metadata, notes, atau log.

## Master data

- `jabatans`, `instansis`, dan `unit_kerjas` adalah master konteks kerja.
- Business logic memakai `kode` yang stabil, bukan numeric `id` atau `nama`.
- Master yang sudah dipakai tidak dihapus fisik. Gunakan `is_active = false` atau soft delete administratif bila perlu.
- `unit_kerjas.instansi_id` harus konsisten dengan `user_positions.instansi_id`.

## Posisi pengguna

- `user_positions.is_active` berarti posisi tersedia, bukan posisi session yang sedang dipakai.
- Posisi aktif request disimpan di session sebagai `active_user_position_id`.
- Satu user boleh memiliki banyak posisi aktif.
- Kombinasi operasional `user_id`, `jabatan_id`, `instansi_id`,
  `unit_kerja_id` harus memiliki tepat satu posisi canonical. Import legacy
  yang disetujui dapat mempertahankan row duplikat sebagai alias non-selectable
  setelah forward migration canonical/alias tersedia.
- Pada import legacy, satu NIK tidak boleh mempunyai dua posisi aktif pada
  kombinasi jabatan, instansi, dan unit kerja yang sama. Setelah organisasi
  dinormalisasi, row aktif dengan `created_at` terbaru menjadi canonical; bila
  sama, legacy ID terbesar menang. Row lama tetap ada sebagai alias
  soft-deleted agar referensi dokumen lama tidak terputus.
- Untuk import `dump-keuangan-202609090855.sql`, kontrak identitas yang disetujui
  adalah `legacy users.id = target user_positions.id`, sedangkan
  `user_positions.user_id` tetap menunjuk akun target hasil distinct NIK.
- Kolom legacy `uuid` dan `access` tidak digunakan. `access` tidak boleh
  dipetakan ke `tahun_aktif` atau permission tahun historis.
- Sejak reset 2026-09-09, tabel `users` dan `user_positions` sengaja kosong agar
  seluruh akun dan posisi berikutnya berasal dari import legacy. Jangan jalankan
  seeder akun/posisi atau membuat data manual sebelum import selesai.
- Detail dan blocker import users legacy berada di
  `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md`. Pipeline read-only sudah
  mencakup source reader, organization resolver, account aggregator, position
  classifier, analyzer `--dry-run`, dan `LegacyUserImportValidator`.
- Action write legacy `ImportLegacyUsers` sudah tersedia, tetapi safety gate
  `legacy_import.execution.enabled` wajib tetap `false` sampai keputusan
  transformasi dikunci. Opsi `--commit` dan audit batch belum dibuat.
- Action import legacy tidak boleh dipanggil dari route, controller, scheduler,
  job, Tinker, atau command lain sebelum entry point resmi disetujui. Keberadaan
  class action bukan izin untuk menjalankan import.
- Sebelum import user legacy, target `users` dan `user_positions` harus tetap
  kosong. Validator preflight harus lulus dan fingerprint sumber harus sama
  dengan snapshot yang disetujui.
- Dokumen SK disimpan di `user_position_documents`, bukan di `user_positions`.
- Flow Management Users membuat akun terlebih dahulu, lalu menambahkan posisi.
  Jangan membuat posisi awal otomatis tanpa decision baru.
- Posisi pertama user baru dari Management Users harus lewat
  `UserManagementAccessService::canAttachPositionToUser()`.

## Login context dan Admin Super

- `/positions` adalah route canonical untuk memilih posisi nyata dari
  `user_positions`; `/login/context` hanya legacy redirect/compatibility.
- `login.post` adalah flow resmi Admin Super untuk memilih acting context manual.
- MFA memakai TOTP kompatibel Google Authenticator; wajib untuk real active
  position Admin Super dan optional/enrollable untuk user non-Admin Super.
- Policy MFA resmi berada di `config('auth.mfa')`.
- Admin Super tidak boleh masuk `login.post`, dashboard, atau route internal
  sensitif sebelum MFA session valid.
- Admin Super acting context adalah overlay session sementara, bukan record `user_positions`.
- Modul internal tidak boleh membaca session `acting_*` langsung; gunakan service context resmi.
- `CurrentUserContext::realActivePosition()` berarti posisi asli dari `user_positions`.
- `CurrentUserContext::activePosition()` berarti effective context yang dipakai modul.
- Untuk Admin Super acting context, `activePosition()->id` tetap id real Admin
  Super; scope operasional harus memakai `jabatan_id`, `instansi_id`, dan
  `unit_kerja_id` effective dari `activePosition()`.
- Saat real active position berubah dari atau ke Admin Super, acting context harus dibersihkan sesuai `../01-authentication/AUTH_CONTEXT_DECISIONS.md`.
- Secret TOTP, kode OTP, recovery code mentah, QR provisioning URI, dan payload
  MFA mentah tidak boleh disimpan di audit/log.
- Lock/unlock akun dari Management Users adalah Admin Super only.
- Reset password dari Management Users adalah Admin Super only; password
  sementara hanya boleh ditampilkan satu kali dan tidak boleh masuk audit/log.
- Reset MFA dari Management Users adalah Admin Super only; PA/KPA tidak boleh
  reset MFA user lain.
- Remember-me default 24 jam dan harus configurable; sumber policy adalah
  `config('auth.remember_me.duration_minutes')`, guard `web.remember`, dan
  `users.remember_token_expires_at`.

## Izin tahun historis

- Izin tahun historis melekat pada `user_position_id`, bukan hanya `user_id`.
- Permission tahun historis tidak memperluas role dasar.
- Semua pemberian, penggunaan, penolakan, pencabutan, dan kedaluwarsa permission harus dapat diaudit.

## Migration dan install

- Fresh install harus divalidasi dari urutan migration dan foreign key, bukan hanya dari `php -l`.
- Jika migration menyentuh tabel master atau posisi pengguna, baca `../06-migrations/FRESH_INSTALL_READINESS.md`.
- Jangan menjalankan migration production sebelum konflik order, missing table, dan duplicate constraint selesai.

## Testing permission

- AI agent tidak boleh membuat, menambah, memodifikasi, scaffold, atau menjalankan test suite/test file/test command tanpa konfirmasi eksplisit dari user terlebih dahulu.
- Aturan ini mencakup Pest, PHPUnit, browser tests, smoke tests, `php artisan test`, dan filtered test command.
