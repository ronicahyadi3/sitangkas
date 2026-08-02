# AI Agent Context — Master Jabatan, Instansi, dan Unit Kerja

## Tujuan

Dokumen ini menjadi instruksi wajib bagi AI agent ketika membaca atau mengubah migration/model/service yang berkaitan dengan:

- `instansis`
- `jabatans`
- `unit_kerjas`
- `user_positions`
- `user_position_documents`
- `user_position_year_permissions`
- tabel transaksi dan audit yang menyimpan `user_position_id`

## Makna bisnis tiga master

### `instansis`

`instansis` adalah **kelompok atau lingkup organisasi** yang digunakan untuk mengelompokkan unit kerja SITANGKAS. Berdasarkan data legacy, contohnya adalah `SKPD`, `DIKBUD`, `DINKES`, `SETDA`, dan masing-masing kecamatan.

`instansis` bukan akun pengguna dan bukan jabatan.

### `unit_kerjas`

`unit_kerjas` adalah **unit organisasi operasional** yang menjadi konteks kerja suatu posisi pengguna. Contohnya BKAD, Diskominfo, sekolah, puskesmas, bagian Setda, kecamatan, dan kelurahan.

### `jabatans`

`jabatans` adalah **peran/posisi bisnis di SITANGKAS**, misalnya Admin Super, BUD, KPA, PPTK, BP, BPP, Pimpinan, dan Auditor.

`jabatans` pada desain ini bukan master jabatan ASN. Jangan menambahkan data pangkat, golongan, eselon, atau nomenklatur kepegawaian ke tabel ini kecuali kebutuhan bisnis berubah secara eksplisit.

## Relasi utama

```text
instansis (1) ───────< unit_kerjas (N)
                           │
                           ├── optional belongsTo skpds
                           └── optional belongsTo parent unit_kerjas

users (1) ───────────< user_positions (N) >────────── (1) jabatans
                              │
                              ├── belongsTo instansis
                              └── belongsTo unit_kerjas

user_positions (1) ──< user_position_documents (N)
user_positions (1) ──< user_position_year_permissions (N)
```

## Constraint konsistensi kritis

`unit_kerjas` mempunyai candidate key:

```text
(id, instansi_id)
```

`user_positions` harus menggunakan composite foreign key:

```text
(user_positions.unit_kerja_id, user_positions.instansi_id)
    → unit_kerjas(id, instansi_id)
```

Tujuannya mencegah posisi menggabungkan unit kerja dari satu instansi dengan `instansi_id` dari instansi lain.

## Aturan penggunaan

1. Gunakan `kode`, bukan `nama`, untuk kondisi program, seed, policy, dan integrasi.
2. Nama adalah label tampilan dan dapat berubah.
3. `is_active = false` berarti master tidak boleh dipilih untuk record baru.
4. Master yang telah direferensikan tidak boleh dihapus fisik.
5. Gunakan soft delete hanya untuk data master yang benar-benar salah/tidak lagi digunakan.
6. Menonaktifkan master tidak otomatis menghapus atau mengubah histori `user_positions`.
7. Query posisi aktif harus memeriksa status aktif pada `user_positions`; master juga harus aktif ketika posisi baru dibuat atau dipilih.
8. `jabatans.level` hanya untuk pengurutan/hierarki tampilan. Jangan menggunakannya sebagai satu-satunya authorization.
9. Authorization tetap harus menggunakan policy/permission aplikasi.
10. Setiap audit aktivitas bisnis harus merekam `user_id` dan `user_position_id`.

## Urutan migration

Urutan minimal untuk fresh database:

1. `users`
2. `skpds`
3. `instansis`
4. `jabatans`
5. `unit_kerjas`
6. `user_positions`
7. `user_position_documents`
8. `user_position_year_permissions`
9. `user_position_year_permission_events`

Jika migration candidate key lama `2026_07_28_140000_add_unit_kerja_instansi_candidate_key` masih ada, jangan jalankan bersama migration `unit_kerjas` baru karena candidate key sudah dibuat langsung pada saat tabel dibuat.

## Larangan

AI agent tidak boleh:

- Menghapus candidate key `(id, instansi_id)` dari `unit_kerjas` selama composite FK `user_positions` masih digunakan.
- Mengubah foreign key master menjadi `cascadeOnDelete()` karena dapat menghapus histori posisi.
- Menentukan authorization hanya dari nama jabatan.
- Menyimpan posisi yang sedang digunakan sebagai `is_current` pada master atau `user_positions`; posisi aktif request berada di session.
- Menggunakan `updated_at` sebagai bukti lengkap audit; gunakan audit log dan kolom aktor.
- Menghapus record master yang sudah direferensikan hanya agar form tidak menampilkannya; gunakan `is_active = false`.
