# Table `jabatans`

## Fungsi

Menyimpan peran/posisi bisnis yang dapat diberikan kepada pengguna melalui `user_positions`.

Contoh legacy:

- Admin Super
- BUD
- Kuasa BUD
- Verifikator BUD
- PA
- KPA
- PPK-SKPD
- PPTK
- BP
- BPP
- BANK
- Pimpinan
- Auditor

## Kolom penting

- `kode`: identifier program yang stabil, misalnya `KPA`, `PPTK`, `AUDITOR`.
- `nama`: label yang tampil pada UI.
- `nama_singkat`: label pendek opsional.
- `description`: definisi dan tanggung jawab role.
- `level`: tingkatan untuk pengurutan/hierarki UI, bukan izin otomatis.
- `sort_order`: urutan tampilan.
- `is_active`: jabatan masih dapat diberikan/dipilih.
- kolom aktor dan soft delete: audit pengelolaan master.

## Relasi

```text
jabatans.id ─────< user_positions.jabatan_id
```

Jabatan memperoleh konteks pengguna dan organisasi hanya setelah dipasang pada `user_positions`.

## Aturan authorization

Jangan melakukan:

```php
if ($jabatan->nama === 'Admin Super') { ... }
```

Gunakan kode/enumerasi atau policy:

```php
if ($jabatan->kode === JabatanCode::ADMIN_SUPER->value) { ... }
```

Lebih baik lagi, seluruh aksi bisnis diperiksa melalui Laravel Policy/Gate.

`level` tidak boleh dianggap sebagai “jabatan level tinggi otomatis boleh semuanya”.

## Penghapusan

Jabatan yang sudah pernah digunakan tidak boleh dihapus fisik. Nonaktifkan dengan `is_active = false`. Foreign key `restrictOnDelete()` pada `user_positions` menjaga histori.
