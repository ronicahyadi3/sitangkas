# IP Risk Decisions

Dokumen ini adalah decision resmi untuk enrichment IP risk pada
`login_events`.

AI agent wajib membaca dokumen ini sebelum mengubah:

- `App\Services\Auth\IpRiskContext`;
- config `auth.audit.login_events.enrichment.ip_risk`;
- kolom `is_vpn`, `is_proxy`, `is_tor`, atau `risk_score`;
- policy yang berpotensi memblokir login berdasarkan IP risk.

Status: accepted.

Tanggal decision: 2026-08-04.

Last updated: 2026-08-10.

## Current Decision: Provider Di-Hold

Mulai 2026-08-10, enrichment IP risk untuk VPN/proxy/Tor sengaja
**di-hold**.

Alasan:

- belum ada provider gratis yang cukup lengkap, stabil, dan proper untuk
  mengisi `is_vpn`, `is_proxy`, `is_tor`, dan `risk_score`;
- MaxMind Anonymous IP tidak tersedia sebagai GeoLite gratis seperti
  GeoLite2 City/ASN;
- provider seperti IPinfo Privacy Detection, IPQualityScore, AbuseIPDB, atau
  dataset sejenis membutuhkan biaya, token, privacy review, dan persetujuan
  project;
- provider gratis parsial seperti Tor-only atau proxy-lite dapat membuat audit
  terlihat lebih lengkap daripada kenyataannya.

Keputusan resmi saat ini:

```env
AUTH_LOGIN_EVENT_IP_RISK_ENABLED=false
AUTH_LOGIN_EVENT_IP_RISK_PROVIDER=none
AUTH_LOGIN_EVENT_IP_RISK_BLOCKING_ENABLED=false
```

AI agent harus memahami bahwa kolom `is_vpn`, `is_proxy`, `is_tor`, dan
`risk_score` yang bernilai `null` adalah kondisi yang disengaja, bukan bug dan
bukan implementasi yang terlewat.

Service `App\Services\Auth\IpRiskContext` tetap dipertahankan sebagai fondasi
future provider, tetapi harus dormant selama config di atas belum diubah dengan
persetujuan eksplisit.

## 1. Tujuan

IP risk dipakai untuk memberi sinyal audit tambahan pada login event.

Kolom yang ditarget:

- `is_vpn`;
- `is_proxy`;
- `is_tor`;
- `risk_score`.

Tujuan tahap ini adalah audit dan investigasi. Bukan auto-block.

## 2. Mode Resmi

Mode resmi saat ini:

```text
audit
```

Artinya:

- lookup boleh dilakukan untuk mengisi `login_events`;
- login tidak boleh diblokir oleh hasil IP risk;
- provider gagal tidak boleh menggagalkan login;
- hasil IP risk dipakai untuk audit, dashboard, investigasi, dan future alert.

Mode selain `audit` belum didukung.

## 3. Blocking Policy

Blocking berdasarkan IP risk belum boleh dilakukan.

Config wajib default:

```env
AUTH_LOGIN_EVENT_IP_RISK_BLOCKING_ENABLED=false
```

Jika suatu saat ingin blocking, wajib decision baru yang menjawab:

- threshold blocking;
- apakah Admin Super punya threshold berbeda;
- apakah user mendapat pesan khusus;
- bagaimana proses recovery;
- bagaimana allowlist jaringan kantor/VPN resmi;
- bagaimana audit event untuk blocked login.

## 4. Null Semantics

Makna nilai wajib konsisten:

| Nilai | Makna |
|---|---|
| `null` | Belum diperiksa, provider disabled, provider gagal, IP tidak public, allowlist dilewati, atau data tidak tersedia. |
| `false` | Sudah diperiksa dan provider menyatakan tidak terdeteksi. |
| `true` | Sudah diperiksa dan provider menyatakan terdeteksi. |

AI agent tidak boleh mengisi default `false` jika provider tidak benar-benar
memeriksa IP.

## 5. Provider Default

Default resmi:

```env
AUTH_LOGIN_EVENT_IP_RISK_ENABLED=false
AUTH_LOGIN_EVENT_IP_RISK_PROVIDER=none
```

Provider yang pernah disiapkan secara teknis pada tahap awal:

```text
maxmind_anonymous_ip
```

Provider ini membaca local database MaxMind Anonymous IP via package
`geoip2/geoip2`. Tidak ada HTTP request eksternal saat login.

Namun, karena MaxMind Anonymous IP tidak tersedia sebagai GeoLite gratis,
provider ini **tidak dianggap aktif/approved** untuk SITANGKAS saat ini.
Jangan mengaktifkan provider ini kecuali file database resmi tersedia dan user
memberi persetujuan eksplisit.

Provider HTTP eksternal seperti IPinfo, IPQualityScore, AbuseIPDB, atau provider
lain belum boleh diaktifkan tanpa decision tambahan tentang secret, timeout,
pricing, data retention, dan mapping response.

## 6. MaxMind Anonymous IP Mapping Jika Nanti Disetujui

Jika provider `maxmind_anonymous_ip` aktif dan database readable:

| MaxMind field | Kolom SITANGKAS |
|---|---|
| `isAnonymousVpn` | `is_vpn` |
| `isPublicProxy` atau `isResidentialProxy` | `is_proxy` |
| `isTorExitNode` | `is_tor` |

`isHostingProvider` tidak otomatis disamakan dengan `is_proxy`, karena hosting
provider tidak selalu proxy.

`risk_score` tetap `null` untuk provider ini karena database Anonymous IP tidak
memberi score 0-100 resmi.

## 7. Risk Score

Skala resmi:

```text
0 sampai 100
```

Makna:

- `0` berarti risiko sangat rendah setelah provider/scoring resmi menghitung;
- `100` berarti risiko sangat tinggi setelah provider/scoring resmi menghitung;
- `null` berarti belum dihitung atau provider tidak memberi score.

Saat ini `risk_score` tidak boleh dibuat dari formula manual.

Formula gabungan seperti VPN + failed attempts + captcha score rendah wajib
decision baru sebelum diimplementasikan.

## 8. Timeout Dan Cache

Policy config wajib tersedia walaupun provider tahap ini local DB:

```env
AUTH_LOGIN_EVENT_IP_RISK_TIMEOUT_SECONDS=2
AUTH_LOGIN_EVENT_IP_RISK_CONNECT_TIMEOUT_SECONDS=1
AUTH_LOGIN_EVENT_IP_RISK_CACHE_TTL_SECONDS=86400
```

Jika nanti provider HTTP dibuat:

- connect timeout maksimal 1 detik;
- total timeout maksimal 2 detik;
- hasil lookup dicache per IP minimal 24 jam;
- provider failure menghasilkan `null`, bukan exception ke login flow.

## 9. IP Yang Dilewati

`IpRiskContext` wajib menghasilkan `null` untuk:

- IP private;
- loopback;
- reserved IP;
- IP invalid;
- provider disabled;
- provider unsupported;
- database tidak readable;
- IP yang masuk allowlist internal.

## 10. Allowlist

Allowlist dipakai untuk melewati lookup provider pada IP/CIDR resmi, misalnya:

- reverse proxy resmi;
- jaringan kantor;
- VPN internal yang memang dipercaya.

Config:

```env
AUTH_LOGIN_EVENT_IP_RISK_ALLOWLIST_CIDRS=
```

Jika IP masuk allowlist, kolom `is_vpn`, `is_proxy`, `is_tor`, dan `risk_score`
tetap `null`, karena provider tidak diperiksa.

## 11. Metadata Dan Secret

IP risk tidak boleh menyimpan:

- token provider;
- license key;
- raw response besar;
- data yang tidak perlu untuk audit.

Jika nanti metadata ditambahkan, hanya boleh menyimpan ringkasan non-secret
seperti provider name, lookup status, atau alasan skip.

## 12. Implementasi Saat Ini

Service resmi:

```text
app/Services/Auth/IpRiskContext.php
```

Aggregator:

```text
app/Services/Auth/AuthenticationEventContext.php
```

Action pencatat:

```text
app/Actions/Auth/RecordAuthenticationEvent.php
```

Default masih disabled. Provider IP risk sedang di-hold secara sengaja.

Implikasi:

- `IpRiskContext` akan mengembalikan `null` untuk `is_vpn`, `is_proxy`,
  `is_tor`, dan `risk_score`;
- `RecordAuthenticationEvent` tetap aman karena mengambil nilai dari context
  nullable;
- kondisi ini bukan pekerjaan belum selesai;
- AI agent tidak boleh mencoba "melengkapi" kolom tersebut dengan provider
  gratis parsial atau formula manual tanpa decision baru.

## 13. Larangan

AI agent tidak boleh:

1. Mengisi `is_vpn=false`, `is_proxy=false`, atau `is_tor=false` saat provider
   belum berjalan.
2. Mengisi `risk_score=0` saat score belum dihitung.
3. Memblokir login berdasarkan IP risk pada tahap ini.
4. Membuat HTTP call provider eksternal tanpa decision baru.
5. Menyimpan API key/provider secret di source code atau docs repo.
6. Menyamakan `isHostingProvider` dengan proxy tanpa decision baru.
7. Mengubah event audit lama untuk backfill risk tanpa decision append-only baru.
8. Mengaktifkan provider IP risk berbayar atau free-tier tanpa persetujuan
   eksplisit dari user.
9. Menganggap `null` pada kolom IP risk sebagai bug selama provider masih
   di-hold.

## 14. Tahapan Lanjutan

Urutan lanjutan yang aman:

1. Minta persetujuan user jika kebutuhan IP risk sudah benar-benar muncul.
2. Tentukan provider resmi, biaya, limit, privacy, dan legal/data-retention
   requirement.
3. Tulis decision provider baru yang menjelaskan mapping response ke
   `is_vpn`, `is_proxy`, `is_tor`, dan `risk_score`.
4. Baru implement provider di `IpRiskContext`.
5. Buat command/status provider jika dibutuhkan operator.
6. Jalankan observasi audit-only terlebih dahulu.
7. Baru bahas alerting dashboard.
8. Blocking policy hanya boleh dibahas setelah ada decision baru dan data
   produksi membuktikan sinyalnya cukup akurat.
