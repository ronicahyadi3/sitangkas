# Project Invariants For AI Agents

Ini ringkasan aturan yang tidak boleh dilanggar lintas domain.

## Akun dan autentikasi

- `users` menyimpan state akun saat ini, bukan histori lengkap.
- Histori autentikasi ditulis ke `login_events`.
- `login_events` bersifat append-only: tidak ada update/delete alur normal.
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
- Kombinasi `user_id`, `jabatan_id`, `instansi_id`, `unit_kerja_id` harus unik.
- Dokumen SK disimpan di `user_position_documents`, bukan di `user_positions`.

## Login context dan Admin Super

- `login.context` hanya untuk memilih posisi nyata dari `user_positions`.
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
