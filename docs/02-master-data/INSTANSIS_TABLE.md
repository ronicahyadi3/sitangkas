# Table `instansis`

## Fungsi

Menyimpan kelompok/lingkup organisasi yang digunakan untuk mengklasifikasikan `unit_kerjas` dan menjaga konteks `user_positions`.

## Kolom penting

- `kode`: identitas stabil dan unik. Dipakai oleh program dan seeder.
- `nama`: nama resmi/label kelompok instansi.
- `nama_singkat`: label pendek opsional.
- `description`: penjelasan administratif.
- `is_active`: menentukan apakah instansi boleh dipilih untuk konfigurasi baru.
- `sort_order`: urutan tampilan.
- `created_by_user_id`, `updated_by_user_id`, `deleted_by_user_id`: aktor pengelola master.
- `deleted_at`: soft delete.

## Relasi

```text
instansis.id
    ├── unit_kerjas.instansi_id
    └── user_positions.instansi_id (divalidasi melalui composite FK ke unit_kerjas)
```

## Aturan

- `kode` dan `nama` unik.
- Jangan mengubah `kode` setelah dipakai oleh kode aplikasi tanpa migration data dan pembaruan seluruh referensi.
- Gunakan `is_active = false` untuk menghentikan penggunaan baru.
- `restrictOnDelete()` melindungi unit kerja dan histori posisi.
- Soft delete tidak otomatis menghapus unit kerja turunannya.

## Query umum

```php
$instansis = Instansi::query()
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->orderBy('nama')
    ->get();
```
