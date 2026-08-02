# AI Agent Context — Master Jabatan, Instansi, dan Unit Kerja

## Tujuan domain

Ketiga tabel ini membentuk master konteks kerja yang digunakan oleh `user_positions`.

```text
users
  └── user_positions
        ├── jabatans
        └── unit_kerjas
              └── instansis
```

Satu baris `user_positions` berarti:

> Pengguna tertentu dapat beraktivitas sebagai jabatan tertentu pada unit kerja tertentu yang berada di bawah instansi tertentu.

## Arti masing-masing master

### `jabatans`

Menyimpan **posisi/kapasitas bisnis dalam aplikasi**, misalnya Admin Super, BUD, KPA, PPTK, BPP, Pimpinan, dan Auditor.

Tabel ini bukan master jabatan ASN/BKPSDM seperti Kepala Bidang, Pranata Komputer, atau pangkat/golongan.

### `instansis`

Menyimpan **ruang lingkup atau kelompok organisasi tingkat atas** yang digunakan SITANGKAS.

Berdasarkan data legacy, contoh instansi adalah:

- SKPD
- DIKBUD
- DINKES
- SETDA
- Kecamatan Lowokwaru
- Kecamatan Klojen
- Kecamatan Blimbing
- Kecamatan Sukun
- Kecamatan Kedungkandang

### `unit_kerjas`

Menyimpan **unit operasional konkret** yang dapat dipilih pada `user_positions`, misalnya:

- Diskominfo dan BKAD di bawah instansi `SKPD`
- SMP Negeri di bawah `DIKBUD`
- Puskesmas di bawah `DINKES`
- Bagian Setda di bawah `SETDA`
- Kecamatan dan kelurahan di bawah instansi kecamatan masing-masing

## Aturan inti yang tidak boleh dilanggar

1. Business logic memakai `kode`, bukan numeric `id` dan bukan `nama`.
2. Master yang sudah pernah dipakai tidak dihapus fisik.
3. Menonaktifkan master menggunakan `is_active = false`.
4. `softDeletes()` hanya untuk data salah, duplikat, atau penghentian administratif yang memang perlu disembunyikan.
5. Foreign key pada `user_positions` memakai `restrictOnDelete()` agar histori aktivitas tidak hilang.
6. `unit_kerjas.instansi_id` wajib sesuai dengan `user_positions.instansi_id`.
7. Parent unit harus berada dalam instansi yang sama.
8. `jabatans.is_active = false` tidak membatalkan histori posisi lama; hanya mencegah penugasan baru.
9. `instansis.is_active = false` dan `unit_kerjas.is_active = false` mencegah pemilihan/penugasan baru, tetapi data lama tetap dapat dibaca.
10. Setiap perubahan master penting harus mencatat aktor pada `created_by_user_id`, `updated_by_user_id`, atau `deleted_by_user_id` dan idealnya juga menghasilkan `audit_logs`.

## Batas tanggung jawab

Tabel ini tidak menyimpan:

- posisi yang sedang dipakai dalam session;
- dokumen SK penetapan pengguna;
- izin edit tahun historis;
- histori login;
- permission aksi terperinci.

Data tersebut berada pada tabel/service lain:

```text
session.active_user_position_id
user_position_documents
user_position_year_permissions
login_events
audit_logs / policy authorization
```
