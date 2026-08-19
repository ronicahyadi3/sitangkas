# Relasi Master Organisasi dengan Tabel SITANGKAS Lain

## Diagram relasi

```text
users
  │
  ├── created/updated/deleted_by pada master
  ├── actor/target pada user_management_audit_events
  │
  └── user_positions
        ├── jabatan_id ───────────────> jabatans.id
        ├── (unit_kerja_id,
        │    instansi_id) ────────────> unit_kerjas(id, instansi_id)
        │                                  ├── instansi_id ─> instansis.id
        │                                  ├── skpd_id ─────> skpds.id
        │                                  └── parent_id ───> unit_kerjas.id
        │
        ├── user_position_documents
        └── user_position_year_permissions
              └── user_position_year_permission_events
```

## Login dan pemilihan posisi

1. Autentikasi dilakukan melalui `users`.
2. Sistem mengambil `user_positions` yang dimiliki user, aktif, belum dihapus, dan masih dalam masa berlaku.
3. Posisi terakhir ditentukan dari `last_used_at`.
4. `active_user_position_id` disimpan pada session.
5. Dari posisi aktif, aplikasi mengetahui `jabatan_id`, `instansi_id`, dan `unit_kerja_id`.
6. Setiap policy dan query data bisnis menggunakan konteks tersebut.

## Dokumen SK

`user_position_documents` berelasi ke `user_positions`, bukan langsung ke `jabatans`, `instansis`, atau `unit_kerjas`. Dokumen tersebut menjadi dasar penetapan kombinasi posisi tertentu.

## Permission tahun historis

`user_position_year_permissions` juga berelasi ke `user_positions`. Artinya izin edit data tahun lama hanya berlaku untuk kombinasi:

```text
user + jabatan + instansi + unit kerja
```

Tidak otomatis berlaku untuk posisi lain milik pengguna yang sama.

## Audit aktivitas

Setiap aktivitas penting minimal mencatat:

```text
user_id
user_position_id
```

Untuk laporan audit jangka panjang, tabel transaksi/audit dapat menyimpan snapshot kode/nama jabatan dan unit pada saat aktivitas. Foreign key menunjukkan master saat ini; snapshot menjaga konteks historis bila nama master berubah.

Audit administrasi Management Users memakai `user_management_audit_events`.
Tabel ini menyimpan aktor user, aktor posisi, target user, target posisi, event
type/result/resource, reason/message, snapshot `before_state` dan
`after_state`, metadata, serta request context. Audit keamanan akun tertentu
tetap juga ditulis ke `login_events` karena berdampak pada autentikasi/session.

## Urutan penghapusan migration

Saat rollback, tabel anak harus dihapus terlebih dahulu:

1. permission events
2. year permissions
3. position documents
4. user positions
5. unit kerjas
6. jabatans
7. instansis

Jangan rollback master ketika tabel anak masih memiliki foreign key.
