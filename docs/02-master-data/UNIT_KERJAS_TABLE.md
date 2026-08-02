# Table `unit_kerjas`

## Fungsi

Menyimpan unit organisasi operasional yang menjadi scope aktivitas pengguna. Satu unit kerja selalu berada dalam satu `instansi` dan dapat dikaitkan dengan satu `skpd` legacy.

## Kolom relasi

- `instansi_id`: wajib; kelompok instansi pemilik unit.
- `skpd_id`: opsional; relasi kompatibilitas dengan master `skpds` legacy.
- `parent_id`: opsional; membentuk unit bertingkat.

## Kolom identitas

- `kode`: identifier stabil dan unik.
- `nama`: nama unit kerja.
- `nama_singkat`: label pendek.
- `jenis`: klasifikasi unit, misalnya `skpd`, `sekolah`, `puskesmas`, `kecamatan`, `kelurahan`, `bagian`, atau `unit`.
- `description`: penjelasan opsional.

## Status dan masa berlaku

- `is_active`: unit tersedia untuk konfigurasi/posisi baru.
- `effective_from`: tanggal struktur mulai berlaku.
- `effective_until`: tanggal struktur berhenti berlaku.
- check constraint memastikan tanggal akhir tidak mendahului tanggal mulai.

## Candidate key

```text
UNIQUE (id, instansi_id)
```

Walaupun `id` sudah primary key, kombinasi ini sengaja dibuat agar MySQL dapat menjadi target composite foreign key dari `user_positions`:

```text
user_positions(unit_kerja_id, instansi_id)
  → unit_kerjas(id, instansi_id)
```

Jangan menghapus constraint ini selama relasi tersebut digunakan.

## Unique scope

Kolom generated `skpd_scope_id` berisi:

```text
COALESCE(skpd_id, 0)
```

Unique index menggunakan:

```text
UNIQUE (instansi_id, skpd_scope_id, nama)
```

Tujuannya mencegah unit dengan nama sama dalam scope organisasi yang sama, termasuk ketika `skpd_id` bernilai `NULL`. Aplikasi tidak boleh menulis langsung ke `skpd_scope_id` karena nilainya dihitung oleh MySQL.

## Hierarki

`parent_id` memungkinkan struktur seperti:

```text
Dinas Pendidikan
└── Sekolah A

Kecamatan Klojen
└── Kelurahan Bareng
```

Database mencegah parent langsung ke dirinya sendiri, tetapi cycle lebih panjang tetap harus dicegah oleh service aplikasi.

## Penghapusan

- Instansi: `restrictOnDelete()`.
- SKPD: `nullOnDelete()` untuk mempertahankan unit legacy.
- Parent unit: `restrictOnDelete()` agar anak tidak menjadi orphan secara tidak sengaja.
- User positions: menggunakan `restrictOnDelete()` melalui composite FK.

Gunakan nonaktif/soft delete, bukan hard delete, ketika sudah memiliki histori.
