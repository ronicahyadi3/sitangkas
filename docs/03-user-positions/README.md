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
| Kebijakan `pdf_watermark_required`, Admin Super acting, guest, dan delivery PDF | `../08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md` |
| Snapshot integrasi Management Users dengan posisi, SK, dan security modal | `../01-authentication/MANAGEMENT_USERS_CURRENT_STATE.md` |
| Keputusan import users legacy dan preservasi `user_positions.id` | `../99-legacy/LEGACY_USERS_IMPORT_DECISIONS.md` |

## Aturan inti

- `is_active` berarti posisi tersedia untuk dipilih.
- Posisi yang sedang dipakai disimpan di session, bukan di tabel `user_positions`.
- Satu user dapat memiliki banyak posisi aktif.
- Saat switch posisi, validasi ownership, active state, soft delete, dan masa berlaku.
- Halaman switch posisi canonical adalah `/positions`; `/login/context` hanya
  legacy redirect/compatibility.
- `instansi_id` tidak boleh bebas dari request; ambil dari posisi valid.
- Untuk Admin Super, acting context bukan row `user_positions`; lihat
  `../01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md` sebelum memakai
  `CurrentUserContext::activePosition()->id`.
- Target schema menambahkan satu boolean `pdf_watermark_required` pada
  `user_positions`, default `false` untuk posisi existing maupun posisi baru.
  Kolom ini belum terdapat pada migration awal dan harus dibuat melalui
  migration additive baru saat implementasi. Nilai `true` hanya diaktifkan
  manual melalui Management User untuk posisi terpilih.
- Flag tersebut berlaku sama untuk seluruh preview/view/download PDF setelah
  authorization lulus: `true` selalu watermark server-side, `false` boleh exact
  original canonical. Jangan membuat flag view dan download terpisah.
- Admin Super saat **acting like** tidak memakai nilai flag row real-nya dan
  selalu diperlakukan sebagai `pdf_watermark_required=false`. Saat memilih
  posisi bisnis nyata miliknya, gunakan flag posisi nyata. Guest bukan posisi
  dan selalu menerima public watermark bila dokumennya memang public-access.
- Perubahan flag melalui Management Users harus diotorisasi dan diaudit dengan
  before/after, aktor, alasan, serta waktu. Flag tidak memberikan hak akses PDF.
- Dokumen SK berada di `user_position_documents`.
- Perubahan dokumen utama harus dikelola dalam transaksi.
- Route binding Management Users untuk `UserPosition` memakai encrypted route
  key. URL numeric polos untuk parameter `{position}` tidak boleh diterima.
- Payload frontend Management Users memakai `id_enc` dan URL aksi terenkripsi
  untuk posisi; jangan mengirim numeric `position_id` ke Blade/JavaScript.
- Halaman Management Users membuat akun terlebih dahulu, lalu menambahkan
  posisi melalui modal posisi. Jangan membuat posisi awal otomatis saat create
  user tanpa decision baru.
- Posisi pertama user baru boleh dibuat oleh Admin Super, PA, atau KPA hanya
  jika posisi yang akan dibuat berada dalam scope kewenangan aktor. Gunakan
  `UserManagementAccessService::canAttachPositionToUser()` untuk flow create
  posisi dari Management Users.
- Audit create posisi menyimpan metadata `is_initial_position` untuk membedakan
  posisi pertama dan posisi tambahan.
- Nonaktifkan posisi dari Management Users wajib memakai modal reason dan
  mengisi `deactivation_reason`.
- Dokumen SK pada Management Users saat ini hanya upload dan metadata; belum ada
  workflow verifikasi dokumen.
- Import `dump-keuangan-202609090855.sql` mempunyai pengecualian desain yang
  sudah disetujui tetapi belum diimplementasikan: seluruh ID row legacy akan
  menjadi `user_positions.id`, akun dibentuk berdasarkan NIK unik, dan row
  konteks duplikat harus menjadi alias non-selectable. Baca dokumen keputusan
  legacy sebelum mengubah unique constraint posisi.

## Pakai cluster lain bila

- Pekerjaan menyangkut master `jabatans`, `instansis`, atau `unit_kerjas`: baca `../02-master-data/README.md`.
- Pekerjaan menyangkut permission tahun historis: baca `../04-year-permissions/README.md`.
- Pekerjaan menyangkut migration order: baca `../06-migrations/FRESH_INSTALL_READINESS.md`.
