# Master Data Docs

Cluster ini menjelaskan master `instansis`, `jabatans`, `unit_kerjas`, seeders, dan validasi hasil import.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Ringkasan domain master untuk AI agent | `AI_AGENT_MASTER_DATA_CONTEXT.md` |
| Konteks organisasi dan dependency migration | `AI_AGENT_MASTER_ORGANIZATION_CONTEXT.md` |
| Seeder canonical master data | `AI_AGENT_MASTER_DATA_SEEDERS.md` |
| Detail tabel `instansis` | `INSTANSIS_TABLE.md` |
| Detail tabel `jabatans` | `JABATANS_TABLE.md` |
| Detail tabel `unit_kerjas` | `UNIT_KERJAS_TABLE.md` |
| Detail migration `instansis` | `2025_10_23_083719_INSTANSIS.md` |
| Detail migration `unit_kerjas` | `2025_10_23_083817_UNIT_KERJAS.md` |
| Detail migration `jabatans` | `2025_10_23_083845_JABATANS.md` |
| Ringkasan validasi hasil seed/import | `VALIDATION.md` |

## Aturan inti

- `jabatans` adalah kapasitas bisnis aplikasi, bukan jabatan ASN/BKPSDM.
- `instansis` adalah ruang lingkup atau kelompok organisasi.
- `unit_kerjas` adalah unit operasional yang dipilih pada posisi pengguna.
- Gunakan `kode` sebagai identifier stabil untuk policy, konfigurasi, import, dan laporan.
- Jangan menghapus master yang sudah dipakai. Nonaktifkan dengan `is_active = false`.
- Parent unit harus berada dalam instansi yang sama.

## Catatan konsistensi

Beberapa dokumen lama masih menyebut `skpds` dan candidate key terpisah. Sebelum mengubah migration master, baca `../06-migrations/FRESH_INSTALL_READINESS.md` untuk melihat konflik fresh install yang sudah teridentifikasi.

## Pakai cluster lain bila

- Pekerjaan menyangkut posisi pengguna: baca `../03-user-positions/README.md`.
- Pekerjaan menyangkut mapping data lama: baca `../99-legacy/README.md`.
- Pekerjaan menyangkut foreign key lintas domain: baca `../05-relationships/README.md`.
