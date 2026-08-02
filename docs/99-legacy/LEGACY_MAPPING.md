# Pemetaan Data Legacy

Dokumen ini berasal dari dump `sitangkas_old` tanggal 28 Juli 2026.

## Ringkasan

| Tabel legacy | Jumlah |
|---|---:|
| `jabatans` | 13 |
| `instansis` | 9 |
| `unit_kerjas` | 141 |
| `skpds` | 8 |

## Interpretasi `instansis`

| ID legacy | Nama | Jumlah unit |
|---:|---|---:|
| 1 | SKPD | 23 |
| 2 | DIKBUD | 30 |
| 3 | DINKES | 18 |
| 4 | SETDA | 8 |
| 5 | Kecamatan Lowokwaru | 13 |
| 6 | Kecamatan Klojen | 12 |
| 7 | Kecamatan Blimbing | 12 |
| 8 | Kecamatan Sukun | 12 |
| 9 | Kecamatan Kedungkandang | 13 |

## Penghapusan `skpd_id`

Pada data legacy, `skpd_id` mengikuti pengelompokan yang sudah dapat ditentukan dari `instansi_id`:

```text
instansi DIKBUD  → skpd Dikbud
instansi DINKES  → skpd Dinkes
instansi SETDA   → skpd Setda
instansi kecamatan → skpd kecamatan yang sama
```

Untuk instansi `SKPD`, seluruh `skpd_id` bernilai `NULL`.

Karena itu, `skpd_id` tidak memberi informasi independen yang diperlukan oleh `user_positions`. Migration baru menghilangkannya untuk mengurangi redundansi dan risiko ketidaksesuaian.

## Pemetaan `jenis` unit

| Data legacy | `jenis` baru |
|---|---|
| Unit di bawah SKPD | `perangkat_daerah` |
| SMP Negeri | `sekolah` |
| Puskesmas, RSUD, laboratorium | `fasilitas_kesehatan` |
| Bagian Setda | `bagian_setda` |
| Unit bernama Kecamatan | `kecamatan` |
| Unit bernama Kelurahan | `kelurahan` |

## Pemetaan parent kecamatan

Untuk setiap instansi kecamatan, unit yang namanya sama dengan kecamatan menjadi parent bagi kelurahan di bawahnya.

Contoh:

```text
Kecamatan Klojen (legacy unit id 25)
├── Kelurahan Klojen
├── Kelurahan Rampalcelaket
├── Kelurahan Bareng
└── kelurahan lainnya
```

## Pembuatan kode

Data legacy belum memiliki kode. Importer/seeder harus menghasilkan kode deterministik dan kemudian mempertahankannya secara permanen.

Contoh:

```text
Instansi: KEC_KLOJEN
Unit: SKPD_DISKOMINFO
Unit: DIKBUD_SMPN_01
Unit: DINKES_PUSKESMAS_BARENG
Unit: KEC_KLOJEN_KEL_BARENG
Jabatan: PPK_SKPD
```

Jangan mengubah kode setelah dipakai oleh konfigurasi, policy, data integrasi, atau laporan.
