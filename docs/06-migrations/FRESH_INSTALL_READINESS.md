# Fresh Install Readiness

Dokumen ini merangkum hasil audit readiness migration dan harus dibaca sebelum menjalankan fresh install, `php artisan migrate`, rollback besar, atau deployment schema.

## Status terakhir

Migration berhasil dijalankan pada database MySQL lokal setelah tabel `unit_kerjas` kosong yang tersisa dari percobaan gagal dibersihkan.

Yang sudah baik:

- Laravel dapat boot.
- Semua file migration lolos `php -l`.
- Composer platform requirements terpenuhi.
- Test dasar berjalan lulus.
- `php artisan migrate` selesai dan percobaan berikutnya menampilkan `Nothing to migrate`.

Catatan 2026-07-30:

- Pernyataan test di atas adalah catatan historis dari audit sebelumnya.
- AI agent tidak boleh membuat, memodifikasi, atau menjalankan test suite/test
  command tanpa konfirmasi eksplisit dari user.

Masalah yang sudah diselesaikan:

1. `chk_unit_kerjas_not_self_parent` ditolak MySQL karena memakai `parent_id` yang juga dipakai foreign key composite `fk_unit_kerjas_parent_instansi`.
2. Migration `2026_07_28_140000_add_unit_kerja_instansi_candidate_key.php` duplikat dengan unique key yang sudah dibuat di migration `unit_kerjas`.

Catatan tersisa:

- `.env.example` memakai SQLite, sementara migration memakai fitur MySQL seperti composite foreign key dan raw `ALTER TABLE ... CHECK`.

## Urutan target untuk fresh database

Urutan konseptual yang aman:

1. `users`
2. infrastructure tables: `cache`, `jobs`, `sessions`
3. `instansis`
4. `unit_kerjas`
5. `jabatans`
6. `login_events`
7. candidate-key compatibility migration
8. `user_positions`
9. `user_position_documents`
10. `user_position_year_permissions`
11. `user_position_year_permission_events`
12. `user_management_audit_events`
13. index pendukung Management Users:
    `2026_08_18_044639_add_management_user_filter_indexes.php`
14. migration additive kebijakan PDF pada `user_positions` dan tabel
    watermark/access/verification canonical sesuai
    `../08-esign/PDF_DELIVERY_WATERMARK_AND_VERIFICATION.md` setelah migration
    tersebut benar-benar dibuat; pada snapshot dokumentasi ini belum ada.

Catatan Management Users:

- `2026_08_12_025830_create_user_management_audit_events_table.php` membuat
  audit administrasi user/posisi/security.
- `2026_08_18_044639_add_management_user_filter_indexes.php` menambah index
  filter pada `users` dan `user_positions` untuk endpoint Yajra DataTables.
- Jangan mengedit migration awal `create_user_positions_table` untuk menambahkan
  `pdf_watermark_required` pada database yang mungkin telah berjalan. Buat
  migration additive `BOOLEAN NOT NULL DEFAULT TRUE`, lalu lakukan backfill
  posisi existing secara eksplisit dan teraudit. Pengecualian Admin Super acting
  adalah resolver runtime (`false`), bukan row acting baru atau mutasi flag real.

## Keputusan yang harus dibuat sebelum migrate

Candidate key:

- Gunakan candidate key langsung di migration `unit_kerjas`.
- Pertahankan migration `2026_07_28_140000_add_unit_kerja_instansi_candidate_key.php` sebagai no-op compatibility migration.

Self-parent validation:

- Jangan memakai `CHECK (parent_id IS NULL OR parent_id <> id)` pada MySQL untuk tabel ini selama `parent_id` dipakai oleh composite foreign key.
- Validasi unit tidak boleh menjadi parent dirinya sendiri di layer aplikasi, request validation, service, atau test.

Pilih satu target database default:

- Jika MySQL adalah target utama, selaraskan `.env.example` ke MySQL.
- Jika SQLite harus didukung untuk test/local, ubah migration agar kompatibel SQLite atau buat fallback khusus.

## Instruksi untuk AI agent

- Jangan menyatakan install siap sebelum `php artisan migrate` berhasil pada database kosong.
- Jangan menjalankan migration production jika blocker di atas belum selesai.
- Setelah mengubah migration, jalankan `php artisan migrate:fresh --pretend --no-interaction` dulu untuk memeriksa urutan SQL.
- Setelah urutan benar, uji fresh migration pada database lokal yang memang boleh di-reset.
- Jika PHP file diubah, jalankan `vendor/bin/pint --dirty --format agent`.
