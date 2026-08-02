# AI Agent Instructions — SITANGKAS Master Data Seeders

## Tujuan

Keempat seeder ini mengisi master canonical yang digunakan oleh `user_positions`:

```text
instansis
  └── unit_kerjas

jabatans
  └── user_positions
```

Satu `user_positions` menghubungkan `users`, `jabatans`, `instansis`, dan `unit_kerjas`.

## Aturan yang wajib dipatuhi AI agent

1. Jangan mengubah kode canonical setelah dipakai oleh policy, konfigurasi, atau data transaksi.
2. Jangan mengganti business rule menjadi berbasis ID numerik. ID dipertahankan hanya untuk kompatibilitas import legacy.
3. Jangan membuat `skpd_id` kembali pada `unit_kerjas`.
4. `unit_kerjas.instansi_id` adalah relasi organisasi utama.
5. `unit_kerjas.parent_id` hanya boleh menunjuk unit dalam instansi yang sama.
6. Jangan menghapus master yang sudah pernah dipakai. Gunakan `is_active = false`.
7. Seeder adalah bootstrap, bukan mekanisme sinkronisasi harian.
8. Jangan menambahkan permission aksi ke tabel `jabatans`; permission aksi dikelola oleh policy/permission layer.
9. Jangan mencatat posisi session pada master ini. Posisi aktif session berada di `session.active_user_position_id`.
10. Jika menambah master baru, berikan kode stabil, sort order, dan jenis unit yang tervalidasi.

## Relasi

### `instansis` → `unit_kerjas`

Satu instansi mempunyai banyak unit kerja. Penghapusan instansi dibatasi selama masih dipakai unit kerja.

### `unit_kerjas` → `unit_kerjas`

Self-reference digunakan untuk hierarki. Dalam baseline saat ini, unit kecamatan menjadi parent kelurahan.

### `jabatans` → `user_positions`

Satu jabatan aplikasi dapat digunakan oleh banyak posisi pengguna. Jabatan adalah kapasitas bisnis seperti KPA/PPTK, bukan nomenklatur ASN.

### Konsistensi `user_positions`

Composite foreign key harus memastikan:

```text
user_positions(unit_kerja_id, instansi_id)
  → unit_kerjas(id, instansi_id)
```

AI agent tidak boleh menulis pasangan instansi dan unit kerja yang tidak cocok.

## Urutan eksekusi

1. `InstansiSeeder`
2. `JabatanSeeder`
3. `UnitKerjaSeeder`

Gunakan `SitangkasMasterDataSeeder` untuk menjalankan urutan tersebut.
