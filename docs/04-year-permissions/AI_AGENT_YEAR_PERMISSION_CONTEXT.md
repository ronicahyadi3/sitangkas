# AI Agent Context — Izin Modifikasi Data Tahun Historis SITANGKAS

## 1. Tujuan bisnis

Secara default, data SITANGKAS mengikuti aturan berikut:

- Data tahun berjalan dapat diproses oleh pengguna yang memakai posisi aktif dan memiliki kewenangan jabatan untuk aksi tersebut.
- Data tahun sebelum tahun berjalan hanya dapat dibaca.
- Modifikasi data tahun historis merupakan pengecualian dan harus diberi izin secara khusus, terbatas, dapat dicabut, serta dapat diaudit.

Tabel permission ini dibuat untuk menjawab:

- posisi pengguna mana yang memperoleh pengecualian;
- tahun data mana yang dapat dimodifikasi;
- alasan dan dasar administratif pemberian izin;
- siapa yang meminta, memberikan, memakai, atau mencabut izin;
- kapan izin mulai dan berhenti berlaku;
- data historis apa yang dimodifikasi menggunakan izin tersebut.

## 2. Dependensi domain

Desain ini bergantung pada tabel:

```text
users
user_positions
```

`user_positions` adalah konteks kerja yang dipilih pengguna dalam session. Permission tahun melekat pada `user_position_id`, bukan hanya pada `user_id`.

Alasannya, satu pengguna dapat mempunyai beberapa posisi. Pemberian izin pada satu posisi tidak boleh otomatis berlaku pada posisi lainnya.

## 3. Sumber posisi yang sedang digunakan

Posisi aktif dalam aktivitas aplikasi harus diambil dari session:

```php
session('active_user_position_id')
```

AI agent tidak boleh:

- memilih posisi hanya berdasarkan `user_id`;
- mengambil posisi pertama tanpa validasi;
- menganggap semua posisi milik pengguna memperoleh izin yang sama;
- menyimpan permission tahun langsung pada tabel `users`.

## 4. Sumber tahun berjalan

Gunakan satu sumber kebenaran, misalnya:

```php
$currentYear = app(FiscalYearService::class)->currentYear();
```

Jangan menyebarkan `now()->year` ke banyak controller apabila tahun operasional aplikasi dapat dikonfigurasi.

Kolom `users.tahun_aktif`, bila masih ada, hanya boleh diperlakukan sebagai pilihan tampilan pengguna. Kolom tersebut bukan bukti authorization.

## 5. Matriks keputusan authorization

### Target adalah tahun berjalan

Akses tulis diperbolehkan apabila:

```text
user login
AND active_user_position_id valid dan dimiliki user
AND user_position masih aktif dan berlaku
AND jabatan/role mengizinkan action
```

Tidak diperlukan record pada `user_position_year_permissions`.

### Target adalah tahun historis

Akses tulis diperbolehkan apabila seluruh kondisi berikut terpenuhi:

```text
user login
AND active_user_position_id valid dan dimiliki user
AND user_position masih aktif dan berlaku
AND jabatan/role mengizinkan action
AND terdapat permission historical_write
AND permission.status = active
AND valid_from sudah berlaku, jika diisi
AND valid_until belum terlewati, jika diisi
```

### Target adalah tahun mendatang

Default: tolak, kecuali terdapat aturan bisnis lain yang dibuat secara eksplisit.

## 6. Permission tidak memperluas role

Contoh:

```text
PPTK memiliki hak update dokumen
+ historical_write tahun 2025
= boleh update dokumen tahun 2025.
```

Namun:

```text
PPTK tidak memiliki hak delete dokumen
+ historical_write tahun 2025
= tetap tidak boleh delete dokumen tahun 2025.
```

Permission ini hanya menghapus penguncian read-only berdasarkan tahun. Permission tidak menambahkan action baru.

## 7. Dua lapis penyimpanan

### `user_position_year_permissions`

Menyimpan kondisi permission dan ringkasan penggunaan terkini. Tabel ini dipakai untuk query authorization yang cepat.

### `user_position_year_permission_events`

Menyimpan histori event yang append-only. Tabel ini dipakai untuk audit dan investigasi.

Jangan menghitung authorization dengan memindai seluruh tabel event.

## 8. Alur pemberian izin

Rekomendasi alur:

```text
pending
  ├── active
  ├── rejected
  └── cancelled

active
  ├── revoked
  └── expired
```

Ketika izin diberikan:

1. Validasi posisi penerima masih tersedia.
2. Pastikan tidak ada permission aktif lain untuk posisi, tahun, dan tipe yang sama.
3. Isi `status = active`.
4. Isi `granted_by_user_id`, `granted_by_position_id`, dan `granted_at`.
5. Isi `valid_from` dan `valid_until` sesuai keputusan.
6. Simpan event `granted` dalam transaksi database yang sama.

## 9. Alur penggunaan izin

Pada setiap operasi tulis terhadap data historis:

1. Tentukan tahun data dari resource yang sebenarnya, bukan dari input tersembunyi yang tidak tervalidasi.
2. Validasi posisi session.
3. Validasi authorization jabatan.
4. Cari permission aktif untuk posisi dan tahun target.
5. Validasi masa berlaku permission.
6. Jalankan perubahan data.
7. Tambahkan `usage_count`.
8. Perbarui `last_used_at`, `last_used_by_user_id`, dan `last_used_by_position_id`.
9. Simpan event `used` dengan resource dan konteks request.

Langkah perubahan data, pembaruan ringkasan permission, dan insert event sebaiknya dilakukan dalam satu transaksi.

## 10. Alur penolakan

Jika permission record ada tetapi tidak sah, misalnya sudah revoked atau expired, event `denied` dapat dicatat pada permission tersebut.

Apabila permission sama sekali tidak ada, tabel event ini tidak dapat digunakan karena `user_position_year_permission_id` wajib diisi. Penolakan tanpa permission harus dicatat pada audit log authorization umum, kecuali desain tabel event diubah secara eksplisit di masa depan.

## 11. Kedaluwarsa

Permission yang melewati `valid_until` dianggap tidak sah walaupun kolom `status` belum diperbarui.

Authorization wajib selalu memeriksa waktu. Scheduled command dapat memperbarui:

```text
status = expired
```

serta menambahkan event `expired`, tetapi keamanan tidak boleh bergantung hanya pada scheduled command tersebut.

## 12. Pencabutan

Ketika permission dicabut:

- ubah `status` menjadi `revoked`;
- isi `revoked_by_user_id`;
- isi `revoked_by_position_id`;
- isi `revoked_at`;
- isi `revocation_reason`;
- tambahkan event `revoked` dalam transaksi yang sama.

Record lama tidak boleh dihapus karena merupakan bukti audit.

## 13. Dasar administratif

Gunakan:

- `reason` untuk uraian alasan;
- `reference_number` untuk nomor surat, nota dinas, disposisi, tiket, atau dasar resmi lain;
- `reference_date` untuk tanggal dokumen referensi.

Keduanya nullable pada database, tetapi aplikasi dapat mewajibkannya sesuai kebijakan organisasi.

Jika `reference_number` diisi, `reference_date` sebaiknya diwajibkan, dan sebaliknya.

## 14. Data yang tidak boleh disimpan

Dilarang menyimpan pada kedua tabel:

- password;
- passphrase TTE;
- access token atau refresh token;
- API key;
- cookie mentah;
- session ID mentah;
- header Authorization;
- payload request rahasia;
- isi lengkap dokumen keuangan yang tidak diperlukan untuk audit permission.

Session ID hanya boleh disimpan sebagai SHA-256 pada `session_id_hash`.

## 15. Kolom yang sengaja dihapus

Desain ini tidak menggunakan:

```text
source_system
external_id
last_synced_at
metadata
```

Alasannya:

- permission dikelola langsung oleh SITANGKAS;
- tidak ada kebutuhan sinkronisasi berkala;
- data penting harus mempunyai kolom terstruktur;
- mencegah JSON menjadi tempat data bebas yang sulit divalidasi dan dilaporkan.

AI agent tidak boleh menambahkan kembali kolom tersebut hanya untuk kenyamanan implementasi.

## 16. Invariant utama

AI agent wajib mempertahankan invariant berikut:

1. Permission melekat pada posisi pengguna.
2. Tahun berjalan tidak membutuhkan historical permission.
3. Tahun historis read-only tanpa permission aktif.
4. Permission tidak memperluas action role.
5. Maksimal satu permission aktif untuk kombinasi posisi, tahun, dan tipe.
6. Event audit bersifat append-only.
7. Penghapusan user/posisi tidak boleh menghapus histori permission secara otomatis.
8. Seluruh keputusan penting mencatat user dan posisi aktor.
