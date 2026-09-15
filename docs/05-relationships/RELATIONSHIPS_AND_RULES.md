# Relasi dan Aturan Integritas

## Diagram relasi utama

```text
users
  │ 1
  │
  │ N
user_positions
  ├────────────── N : 1 ────────────── jabatans
  │
  └──── N : 1 ─── unit_kerjas ─── N : 1 ─── instansis
                       │
                       └── self-reference parent/children
```

## Relasi `instansis → unit_kerjas`

Fungsi relasi:

- menentukan ruang lingkup pemilik unit;
- mempercepat filter unit menurut instansi;
- membatasi pilihan unit pada form;
- menjadi bagian pemeriksaan konsistensi `user_positions`.

## Relasi `unit_kerjas → unit_kerjas`

Fungsi relasi:

- menyimpan struktur kecamatan–kelurahan;
- memungkinkan pengembangan bidang–subbagian atau organisasi lain;
- menghindari tabel khusus untuk setiap jenis hierarki.

Parent wajib berada di instansi yang sama.

## Relasi `jabatans → user_positions`

Fungsi relasi:

- menentukan kapasitas pengguna saat melakukan aktivitas;
- menjadi input authorization;
- memungkinkan satu pengguna memiliki banyak posisi.

## Relasi komposit `unit_kerjas → user_positions`

```text
user_positions(unit_kerja_id, instansi_id)
    → unit_kerjas(id, instansi_id)
```

Fungsi relasi:

- mencegah pasangan instansi dan unit yang tidak konsisten;
- memastikan unit selalu milik instansi yang dipilih;
- menjaga integritas meskipun validasi aplikasi terlewati.

Contoh yang harus ditolak database:

```text
instansi_id   = SKPD
unit_kerja_id = Puskesmas Bareng yang berada pada DINKES
```

## Aturan penghapusan

Master tidak boleh dihapus fisik apabila telah direferensikan.

Gunakan urutan berikut:

1. `is_active = false` untuk menghentikan penggunaan baru;
2. isi periode akhir jika relevan;
3. soft delete hanya bila perlu disembunyikan dari UI;
4. pertahankan record untuk histori dan audit.

## Aturan audit

Saat membuat/mengubah/menghapus master:

- isi kolom aktor yang sesuai;
- jangan memperbarui `updated_at` hanya karena record dibaca;
- catat perubahan penting ke audit log terpisah;
- jangan menyimpan password, token, cookie, atau credential dalam catatan/deskripsi.

Untuk Management Users, audit log terpisah yang aktif adalah
`user_management_audit_events`. Aksi keamanan akun seperti force password
change, reset password Admin Super, lock/unlock, dan reset MFA tetap juga
mencatat event keamanan ke `login_events`.

## Aturan cache

Master ini cocok di-cache karena relatif jarang berubah. Cache wajib di-invalidasi setelah create, update, activate/deactivate, restore, atau soft delete.

Contoh key:

```text
master:jabatans:active
master:instansis:active
master:unit_kerjas:instansi:{instansi_id}
```
