# Phase 1 Sandbox Contract Proof Report

Tanggal pemeriksaan: **17 September 2026**.

Status: **kontrak live production NIK+passphrase, status, certificate chain,
dan verify dasar terbukti; matrix lanjutan masih pending**.

Pemilik sistem menyatakan tidak tersedia API/akun development dan pada 17
September 2026 secara eksplisit mengarahkan pengujian terbatas ke endpoint
production. Config lokal diaktifkan untuk sesi ini. Pengujian dilakukan
bertahap tanpa menyimpan response body mentah. Invisible signing satu PDF
dengan NIK+passphrase sudah berhasil dan hasilnya lolos verifikasi. Phase 1
belum lengkap untuk visible signing, modified/encrypted verification, timeout
outcome, limit, dan multi-file.

Laporan ini tidak memuat NIK, email signer, passphrase, OTP, Basic Auth, isi
PDF, atau response body mentah.

## 1. Gate keamanan dan hasil preflight

Kondisi setelah keputusan eksplisit pemilik:

| Kebutuhan | Kondisi | Keputusan |
|---|---|---|
| `BSRE_ESIGN_BASE_URL` | tersedia di `.env` lokal | host public dapat dijangkau melalui HTTP |
| `BSRE_ESIGN_USERNAME`/`PASSWORD` | tersedia di `.env` lokal | Basic Auth valid; nilai tidak dicatat di laporan |
| `BSRE_ESIGN_ENABLED` | `true` atas arahan pemilik | hanya config lokal; example tetap default `false` |
| TLS | endpoint hanya merespons HTTP; HTTPS/443 tidak tersedia | security gap production masih terbuka |
| Identitas signer | tersedia dan diotorisasi pemilik untuk probe | nilai tidak dicatat di laporan |
| Credential signer | passphrase diberikan untuk satu uji production | dipakai ephemeral; tidak disimpan di config/report |

Credential sempat diberikan melalui chat dan rotasi tetap direkomendasikan.
Nilainya tidak disalin ke source, example, dokumentasi, atau output pengujian.

## 2. Bukti sumber yang diperiksa

Collection pengguna diperiksa secara read-only:

```text
C:\Users\StaDian\Downloads\Esign-Client for User 2.2.0-beta.postman_collection.json
```

- nama: `Esign-Client for User 2.2.0-beta For Development`;
- SHA-256:
  `72E68FE619C2BDBAFD695D28D51FEB5800F362F9DDB164E16A03553B59D8EE23`;
- schema collection: Postman `v2.1.0`, bukan versi server eSign;
- authentication: HTTP Basic memakai referensi variable `username` dan
  `password`; nilai variable collection kosong;
- `baseURL` kosong;
- terdapat event collection `prerequest` dan `test`, tetapi keduanya tidak
  mempunyai baris script nonblank;
- script collection tidak dijalankan;
- request v1 dan fitur seal yang berada di collection tidak dijadikan kontrak
  integrasi TTE user v2.

Collection berlabel beta dan bukan spesifikasi response lengkap. Referensi
resmi BSrE tetap diperlukan untuk proses integrasi, uji penerapan, dan
persetujuan production.

## 3. Kontrak request v2 yang teramati

Tabel berikut hanya membuktikan bentuk contoh request di collection, bukan
menjamin field optional/required atau perilaku server.

| Operasi | Method dan path | Bentuk request teramati |
|---|---|---|
| Request OTP NIK | `POST /api/v2/sign/get/totp` | `nik`, `data` |
| Request OTP email | `POST /api/v2/sign/get/totp` | `email`, `data` |
| Sign NIK + OTP | `POST /api/v2/sign/pdf` | `nik`, `totp`, `signatureProperties[]`, `file[]` |
| Sign email + OTP | `POST /api/v2/sign/pdf` | `email`, `totp`, `signatureProperties[]`, `file[]` |
| Sign email + passphrase | `POST /api/v2/sign/pdf` | `email`, `passphrase`, `signatureProperties[]`, `file[]` |
| Sign NIK + passphrase | `POST /api/v2/sign/pdf` | `nik`, `passphrase`, `signatureProperties[]`, `file[]` |
| Status NIK | `POST /api/v2/user/check/status` | `nik` |
| Status email | `POST /api/v2/user/check/status` | `email` |
| Certificate chain | `GET /api/v2/user/certificate/chain/:id` | identity pada path |
| Verify PDF | `POST /api/v2/verify/pdf` | `file` |
| Verify PDF terenkripsi | `POST /api/v2/verify/pdf` | `file`, `password` |

Contoh visible signing pada varian NIK/email + OTP dan email + passphrase
menampilkan elemen `signatureProperties[]` dengan field:

- `imageBase64`;
- `tampilan`;
- `page`;
- `originX` dan `originY`;
- `width` dan `height`;
- `location`;
- `reason`;
- `contactInfo`.

Contoh NIK + passphrase hanya menampilkan `tampilan` di dalam
`signatureProperties[]`. Perbedaan ini tidak boleh ditafsirkan sebagai schema
final sebelum diuji. Arti numerik field `data` pada request OTP juga belum
didokumentasikan oleh collection.

## 4. Inkonsistensi dan kekosongan collection

1. Contoh certificate chain berdasarkan email memakai
   `/api/user/certificate/chain/:id`, sedangkan NIK dan ID subscriber memakai
   `/api/v2/user/certificate/chain/:id`. Integrasi baru tidak boleh memakai
   path tanpa `/v2` sebelum vendor mengonfirmasi perilakunya.
2. Seluruh request sign/status/verify v2 tidak mempunyai response example yang
   berisi body. Satu response object pada NIK + passphrase juga tidak mempunyai
   status, code, atau body yang dapat dijadikan kontrak.
3. Collection tidak membuktikan content type response sign: PDF binary,
   base64, atau JSON wrapper.
4. Collection tidak mendefinisikan schema error, application code, retry hint,
   request ID, atau bentuk validation error.
5. Collection tidak membuktikan pasangan indeks `file[]` dengan
   `signatureProperties[]`, partial success, urutan output, atau batas jumlah
   file.
6. Collection tidak menjelaskan satuan, titik asal, rotasi, crop box, atau
   orientasi koordinat visible signature.
7. Collection tidak mendefinisikan ukuran request/PDF, timeout, rate limit,
   certificate/TLS pinning, maupun compatibility production.

Konsekuensinya, response decoder, error mapper, coordinate transformer,
multi-file support, dan retry policy belum boleh dibekukan berdasarkan lampiran
saja.

## 5. Hasil eksekusi live dan matrix tersisa

Pengujian file memakai PDF sintetis tanpa data warga. Evidence hanya menyimpan
HTTP status, content type, nama field tersanitasi, safe application code, ukuran
output, dan latency. Request/response body mentah serta material autentikasi
tidak dicatat.

| ID | Skenario | Bukti minimum | Status |
|---|---|---|---|
| S01 | Transport dan Basic Auth valid pada status user | status, content type, schema | partial: HTTP/Auth lulus; TLS gagal karena HTTPS tidak tersedia |
| S02 | Basic Auth salah | status dan safe error code; tidak ada secret echo | pass: HTTP 401 JSON |
| S03 | Status NIK/email valid, tidak terdaftar, expired, revoked | mapping setiap state | partial: `ISSUE`, `NOT_REGISTERED`, `NO_CERTIFICATE` terbukti; email/expired/revoked pending |
| S04 | Request OTP dan lifecycle OTP | expiry, resend, reuse, wrong OTP | partial: request berhasil; provider mengarahkan TOTP dari BeSign Mobile |
| S05 | Invisible sign satu PDF + passphrase/OTP | response schema, output PDF, latency | pass untuk NIK+passphrase satu file |
| S06 | Passphrase/OTP salah | status, code, retryability | blocked |
| S07 | PDF empty, invalid, encrypted, dan oversize | validation/limit mapping | partial: empty/invalid menghasilkan HTTP 500; encrypted/oversize pending |
| S08 | Visible portrait dan landscape | posisi hasil terhadap expected box | blocked |
| S09 | Halaman rotated dan ukuran halaman berbeda | transform per rotation/page box | blocked |
| S10 | Verify unsigned, valid signed, modified, dan encrypted PDF | schema dan state signature | partial: unsigned dan dua valid signed PDF terbukti; modified/encrypted pending |
| S11 | Timeout sebelum koneksi | transport error dan safe retry rule | blocked |
| S12 | Timeout setelah request sign terkirim | outcome `unknown`; tidak auto-retry | blocked |
| S13 | Satu dan beberapa `file[]`/`signatureProperties[]` | aturan pairing dan output order | partial: satu file/satu property terbukti; multi-file pending |
| S14 | Partial failure multi-file | atomic/partial behavior | blocked |
| S15 | Rate, ukuran, jumlah file, dan durasi maksimum | batas operasional terukur | blocked |
| S16 | Certificate chain NIK/email/ID subscriber | path v2 final dan response schema | partial: NIK valid menghasilkan tiga elemen; invalid ID HTTP 400 dengan body non-JSON |

S01-S05 sudah dijalankan untuk jalur NIK+passphrase. Multi-file tetap dikerjakan
terakhir; default desain tetap satu file per request.

### Evidence live tersanitasi

- Status identity valid: HTTP 200 JSON, keys
  `status_code,message,status`, status `ISSUE`, sekitar 461 ms.
- Basic Auth salah: HTTP 401 JSON dengan schema standar
  `timestamp,status,error,path`.
- Identity kosong/malformed: HTTP 200 dengan application status
  `NOT_REGISTERED` dan code `2011`.
- Identity sintetis 16 digit: HTTP 200 `NO_CERTIFICATE` code `2021`; server
  tidak boleh diasumsikan memvalidasi kualitas NIK hanya dari panjangnya.
- Certificate chain NIK valid: HTTP 200 JSON, `success=true`, `data[]` berisi
  tiga elemen; material certificate tidak dicatat.
- Certificate chain ID invalid: HTTP 400 dengan header JSON tetapi body tidak
  dapat di-decode sebagai JSON.
- Verify PDF sintetis unsigned: HTTP 200 JSON, `signatureCount=0`, conclusion
  `NO_SIGNATURE`, dan `signatureInformations=[]`.
- Verify file kosong/invalid: HTTP 500 JSON. Client harus memetakannya sebagai
  invalid provider input dan tidak mengekspos generic server error mentah.
- Request TOTP NIK: HTTP 200 JSON, `success=true`; provider menyatakan TOTP
  dihasilkan melalui BeSign Mobile.

### Evidence dua sample PDF dan hasil sign

| Artifact | Ukuran | SHA-256 | Hasil verify | Durasi |
|---|---:|---|---|---:|
| `sample belum tte.pdf` | 102.106 byte | `436F1F3BCE1CFA1F591D78B0A6DE5C6F3DE24A086D931EFC36CA1490E17F70F1` | `NO_SIGNATURE`, 0 signature | 1.393 ms |
| `sample telah tte.pdf` | 3.989.901 byte | `D4B05B053AE2004D0C25A34B80A222C972F0CF0464A58EA75C5D1682F6C64CF0` | `VALID`, 8 signature | 40.081-42.505 ms |
| hasil sign sample pertama | 168.558 byte | `028988E6B52BBF782A86EC68B6E9018CCE5E5BA6F895211FDCADEE3A36C7AA96` | `VALID`, 1 signature | verify 2.244 ms |

Source `sample belum tte.pdf` tidak ditimpa dan hash tetap sama. Hasil sign
disimpan private pada:

```text
storage/app/private/esign-contract-tests/sample-belum-tte-signed-20260917-174149.pdf
```

Request sign dikirim tepat satu kali, tanpa retry, memakai satu `file[]`, satu
`signatureProperties[]`, dan `tampilan=INVISIBLE`. Hasilnya:

- HTTP 200 dan `Content-Type: application/json`;
- durasi sekitar 862 ms;
- schema response `{time: int, file: string[]}`;
- `file[0]` adalah Base64 PDF;
- PDF output mempertahankan 2 halaman, ukuran Letter, PDF 1.5, dan tidak
  terenkripsi;
- passphrase tidak muncul pada response dan tidak disimpan ke artifact/report.

Verifikasi output menghasilkan:

- `conclusion=VALID`;
- `signatureCount=1`;
- `integrityValid=true`;
- `certificateTrusted=true`;
- `signatureFormat=PAdES-BASELINE-T`;
- `ltv=false`;
- certificate chain berisi tiga elemen;
- timestamp signer tersedia.

Sample yang sudah TTE menghasilkan delapan signature. Seluruhnya mempunyai
`integrityValid=true`, `certificateTrusted=true`, format
`PAdES-BASELINE-LT`, `ltv=true`, dan masing-masing certificate chain tiga
elemen.

### Temuan response yang wajib diakomodasi mapper

1. Response sign berupa JSON wrapper, bukan PDF binary langsung.
2. `file` tetap array walaupun request hanya membawa satu PDF.
3. Response verify memakai keys
   `signatureCount`, `description`, `conclusion`, dan
   `signatureInformations`.
4. Elemen `signatureInformations` memakai keys `location`, `id`, `fieldName`,
   `certificateDetails`, `signatureDate`, `certLevelCode`, `integrityValid`,
   `signatureFormat`, `lastSignature`, `ltv`, `timestampInfomation`,
   `certificateTrusted`, `digestAlgorithm`, `signatureAlgorithm`, `signerName`,
   dan `reason`.
5. Typo vendor `timestampInfomation` dan `signatureAlgoritm` pada certificate
   detail harus dipetakan persis, lalu dinormalisasi di DTO internal.
6. `lastSignature=false` ditemukan pada seluruh delapan signature sample dan
   juga pada hasil sign yang hanya memiliki satu signature. Field ini tidak
   boleh dipakai sebagai satu-satunya cara menentukan signature terbaru.
7. `digestAlgorithm` dan `signatureAlgorithm` kosong pada response yang diuji;
   mapper harus menerima empty/null.
8. Hasil sign langsung berformat Baseline-T, sedangkan sample lama berformat
   Baseline-LT. Jangan mengklaim LTV tersedia pada output sign baru tanpa
   proses/vendor capability tambahan.
9. Terdapat indikasi clock skew sekitar 5 menit 47 detik antara
   `signatureDate` output baru dan timestamp authority. Sinkronisasi NTP eSign
   Client/server perlu diperiksa sebelum audit production mengandalkan waktu
   tersebut.
10. Verify file 3,99 MB dengan delapan signature memerlukan sekitar 40-43
    detik. Timeout verify 120 detik masuk akal; request UI tidak boleh memakai
    timeout singkat yang sama dengan status user.

## 6. Scope metode signing awal

Implementasi awal dikunci ke **NIK + passphrase** sesuai arahan pemilik.

Metode berikut dicatat sebagai pengembangan lanjutan dan tidak dimasukkan ke
vertical slice pertama:

- NIK + TOTP;
- email + passphrase;
- email + TOTP.

Request TOTP sudah terbukti dapat dipanggil, tetapi sign dengan TOTP belum
menjadi scope aktif. Kontrak internal harus tetap dapat diperluas tanpa
mencampurkan OTP/passphrase ke database atau serialized queue payload. Untuk
TTE asynchronous, passphrase hanya boleh berada sementara pada secret
store/cache private terenkripsi ber-TTL dan worker menerima opaque reference.

## 7. Keputusan sementara untuk implementasi

Sampai matrix live selesai:

- source/example tetap default `BSRE_ESIGN_ENABLED=false`; `.env` lokal saat
  ini `true` atas instruksi eksplisit pemilik untuk pengujian production;
- endpoint baru hanya `/api/v2/*`;
- `BsreClient` harus fail closed bila config tidak lengkap;
- sign hanya satu PDF per provider request;
- sign tidak boleh di-auto-retry;
- timeout setelah request terkirim diperlakukan sebagai outcome `unknown`;
- response tidak boleh diasumsikan selalu JSON;
- decoder harus memeriksa HTTP status dan `Content-Type` sebelum membaca body;
- visible coordinate transformation belum boleh dianggap final;
- browser tidak pernah menerima Basic Auth atau memanggil BSrE langsung;
- passphrase/OTP hanya hidup selama request sinkron dan tidak masuk log,
  database, session, cache, queue, atau audit.

## 8. Gate lanjutan tanpa membocorkan secret

Operator perlu menyelesaikan berikut ini:

1. rotate credential yang pernah dibagikan melalui chat;
2. sediakan HTTPS atau compensating control jaringan internal yang disetujui;
3. masukkan TOTP/passphrase hanya secara ephemeral pada proses sign;
4. siapkan PDF signed, modified, encrypted, portrait, landscape, rotated, dan
   oversize yang sintetis;
5. konfirmasi secara eksplisit sebelum membuat tanda tangan production;
6. lanjutkan timeout, limit, coordinate, dan multi-file paling akhir.

`BSRE_ESIGN_ENABLED=true` hanya membuktikan feature flag aktif, bukan bukti
kesiapan production atau kelulusan Phase 1.

## 9. Exit criteria Phase 1

Phase 1 baru **complete** bila:

- seluruh skenario kritis S01-S12 memiliki hasil tersanitasi;
- path v2 final dikonfirmasi dan risiko/compensating control transport
  production diselesaikan;
- response sign/status/verify dapat di-decode deterministik;
- error mapping dan retryability disepakati;
- timeout setelah dispatch mempunyai prosedur reconciliation;
- coordinate transform portrait/landscape/rotation terbukti;
- batas ukuran, rate, timeout, dan jumlah file tercatat;
- keputusan single/multi-file dan partial failure tercatat;
- tidak ada secret atau PDF base64 pada log/evidence;
- hasil dapat diterjemahkan menjadi kontrak `BsreClient` untuk Phase 2.

Pada snapshot ini, kontrak minimum vertical slice NIK+passphrase invisible
signing tersedia sehingga fondasi Phase 2 boleh mengimplementasikan scope
tersebut. Phase 2 tidak boleh mengaktifkan visible signing, encrypted verify,
metode identitas/credential lain, atau multi-file berdasarkan asumsi.

## 10. Referensi resmi

- [Integrasi eSign BSrE](https://bsre.bssn.go.id/product/integrasi-esign-bsre/)
- [Pedoman Kriteria Integrasi Sistem](https://bsre.bssn.go.id/doc/juknis/PEDOMAN-KRITERIA-INTEGRASI-SISTEM.pdf)
- [Repository petunjuk teknis BSrE](https://bsre.bssn.go.id/repository/juknis/)
- [Verifikasi Dokumen BSrE](https://bsre.bssn.go.id/product/verifikasi-dokumen/)
- [Layanan Konsultasi BSrE](https://bsre.bssn.go.id/product/layanan-konsultasi/)
