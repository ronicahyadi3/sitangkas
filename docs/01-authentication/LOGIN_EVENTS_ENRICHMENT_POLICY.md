# Login Events Enrichment Policy

Dokumen ini adalah decision resmi untuk enrichment data audit pada tabel
`login_events`.

AI agent wajib membaca dokumen ini sebelum:

- mengubah `App\Actions\Auth\RecordAuthenticationEvent`;
- menambah data device, browser, platform, timezone, fingerprint, GeoIP, ASN,
  VPN, proxy, Tor, atau risk score ke `login_events`;
- memasang library user-agent parser, GeoIP, ASN, atau IP reputation;
- membuat job, service, middleware, atau JavaScript collector yang mengisi
  kolom audit autentikasi;
- mengubah migration atau model `LoginEvent`.

Status: accepted.

Tanggal decision: 2026-08-03.

Last updated: 2026-08-10.

## 1. Tujuan policy

Tabel `login_events` disiapkan sebagai audit trail autentikasi yang lengkap,
tetapi tidak semua kolom bisa diisi hanya dari request Laravel biasa.

Policy ini memastikan AI agent memahami bahwa:

- kolom nullable bukan selalu bug;
- `null` adalah nilai benar ketika data belum tersedia atau belum diperiksa;
- nilai boolean security tidak boleh ditebak;
- enrichment tidak boleh memperlambat atau menggagalkan login;
- data sensitif tidak boleh disimpan mentah;
- `RecordAuthenticationEvent` tetap ramping sebagai action pencatat event,
  bukan tempat semua parsing, lookup, dan scoring.

## 2. Prinsip utama

### 2.1 Jangan isi data palsu

AI agent dilarang mengisi kolom audit dengan dummy value hanya supaya terlihat
lengkap.

Contoh yang dilarang:

- mengisi `is_vpn = false` jika belum ada provider VPN detection;
- mengisi `country_code = ID` hanya karena aplikasi dipakai di Indonesia;
- mengisi `risk_score = 0` tanpa scoring policy;
- mengisi `device_type = desktop` tanpa parser atau aturan valid;
- mengisi `device_fingerprint_hash` dari string asal.

Audit yang kosong tetapi jujur lebih baik daripada audit penuh tetapi salah.

### 2.2 Makna `null`, `false`, dan `true`

Untuk kolom enrichment:

| Nilai | Makna |
|---|---|
| `null` | Data tidak tersedia, belum dikumpulkan, provider gagal, atau belum diperiksa |
| `false` | Sistem sudah memeriksa dan hasilnya negatif |
| `true` | Sistem sudah memeriksa dan hasilnya positif |

Aturan ini terutama berlaku untuk:

- `is_vpn`;
- `is_proxy`;
- `is_tor`.

### 2.3 Login tidak boleh bergantung penuh pada enrichment

Pencatatan event autentikasi wajib tetap terjadi walaupun enrichment gagal.

Jika enrichment gagal:

- tetap simpan event dasar;
- isi kolom enrichment terkait dengan `null`;
- boleh catat ringkasan error non-sensitive di `metadata`;
- jangan bocorkan secret, token, raw response provider, atau credential.

### 2.4 Enrichment eksternal harus fail-safe

Provider eksternal seperti GeoIP API, ASN API, atau IP reputation API tidak
boleh membuat proses login menunggu lama.

Jika memakai HTTP API:

- gunakan timeout pendek;
- gunakan connect timeout pendek;
- gunakan retry terbatas hanya untuk error transient;
- gunakan cache untuk IP yang sama;
- jangan memblokir login jika provider down.

Untuk performa dan privacy, local database lebih disarankan untuk GeoIP/ASN
dibanding API eksternal.

### 2.5 Data sensitif harus diminimalkan

Jangan menyimpan data berikut pada `login_events`, `metadata`, atau log:

- password;
- passphrase;
- TOTP code;
- recovery code mentah;
- TOTP secret;
- MFA QR provisioning URI;
- session id mentah;
- remember token mentah;
- cookie mentah;
- bearer token;
- raw fingerprint detail yang bisa melacak user secara invasif.

Jika data perlu dikorelasikan, simpan hash HMAC dengan key audit resmi.

## 3. Current implementation snapshot

Saat decision ini ditulis, action utama adalah:

`App\Actions\Auth\RecordAuthenticationEvent`

Action ini sudah mengisi data dasar seperti:

- `user_id`;
- `login_identifier_type`;
- `login_identifier`;
- `login_identifier_hash`;
- `user_context`;
- `event_type`;
- `result`;
- `failure_code`;
- `message`;
- `attempt_number`;
- `auth_guard`;
- `auth_method`;
- `auth_provider`;
- `remember_me`;
- `mfa_method`;
- `mfa_result`;
- `request_id` jika header UUID valid tersedia;
- `correlation_id` jika header UUID valid tersedia;
- `session_id_hash` jika diberikan oleh caller;
- `source_channel`;
- `route_name`;
- `request_path`;
- `http_method`;
- `http_status`;
- `ip_address`;
- `forwarded_for` dari `RequestNetworkContext` jika request berasal dari
  trusted proxy atau config audit mengizinkan capture untrusted header;
- `user_agent`;
- `accept_language`;
- `captcha_*` jika tersedia dari login request;
- `environment`;
- `server_node`;
- `metadata`;
- `occurred_at`.

Action ini sudah mengambil enrichment lanjutan secara otomatis melalui service
context. Kolom nullable hanya terisi bila config aktif, provider tersedia, dan
data sumber valid:

- `proxy_ip_address`;
- `network_asn`;
- `network_organization`;
- `country_code`;
- `region`;
- `city`;
- `is_vpn`;
- `is_proxy`;
- `is_tor`;
- `risk_score`;
- `device_type`;
- `device_name`;
- `browser_name`;
- `browser_version`;
- `platform_name`;
- `platform_version`;
- `client_timezone`;
- `event_hash`;
- `retention_until`.

Kolom yang masih belum otomatis diisi:

- `device_fingerprint_hash`.

Kondisi ini bukan migration error. Itu berarti service enrichment terkait belum
diimplementasikan, config belum diaktifkan, atau data sumbernya belum tersedia.

Update 2026-08-03:

- config policy nested `config('auth.audit.login_events.enrichment')` sudah
  tersedia;
- `matomo/device-detector` sudah terpasang;
- `AuthenticationEventContext` sudah menjadi aggregator awal;
- `RequestNetworkContext` sudah mengisi `request_id`, `correlation_id`,
  `ip_address`, `forwarded_for`, dan `proxy_ip_address` dengan validasi aman;
- `config/trustedproxy.php` sudah tersedia untuk menghubungkan env
  `TRUSTED_PROXIES` ke middleware `TrustProxies` Laravel;
- `UserAgentContext` sudah mengisi enrichment user-agent;
- `ClientSignalContext` sudah mengisi `client_timezone` dari form login atau
  header `X-Client-Timezone`;
- `ApplicationVersionContext` sudah mengisi `application_version` dari
  metadata deploy melalui config;
- kolom `device_type`, `device_name`, `browser_name`, `browser_version`,
  `platform_name`, `platform_version`, `client_timezone`, dan
  `application_version` mulai bisa terisi saat data sumber tersedia dan valid.

Update 2026-08-04:

- `geoip2/geoip2` sudah terpasang sebagai library official MaxMind PHP API;
- `IpGeolocationContext` sudah tersedia untuk lookup GeoIP City dan ASN dari
  local database;
- `AuthenticationEventContext` sudah memanggil `IpGeolocationContext` setelah
  `RequestNetworkContext`;
- `RecordAuthenticationEvent` sudah mengambil `network_asn`,
  `network_organization`, `country_code`, `region`, dan `city` dari context
  enrichment;
- runbook `GEOIP_MAXMIND_RUNBOOK.md` sudah tersedia;
- command `php artisan auth:geoip-status` sudah tersedia untuk memeriksa
  config, readability database, dan sample lookup;
- default config GeoIP tetap disabled sampai database MaxMind disiapkan dan
  env diaktifkan.

Update 2026-08-04, integrity dan retention:

- `LoginEventIntegrity` sudah tersedia untuk menghitung `event_hash`;
- `LoginEventRetention` sudah tersedia untuk menghitung `retention_until`;
- `RecordAuthenticationEvent` sudah membangun atribut final terlebih dahulu,
  lalu menghitung retention dan hash sebelum insert;
- `event_hash` memakai HMAC-SHA256 dan audit hash key;
- `retention_until` hanya diisi jika `AUTH_LOGIN_EVENT_RETENTION_DAYS` bernilai
  angka positif;

Update 2026-08-04, IP risk:

- decision resmi berada di `IP_RISK_DECISIONS.md`;
- `IpRiskContext` sudah tersedia untuk lookup VPN/proxy/Tor;
- `AuthenticationEventContext` sudah memanggil `IpRiskContext` setelah GeoIP;
- `RecordAuthenticationEvent` sudah mengambil `is_vpn`, `is_proxy`, `is_tor`,
  dan `risk_score` dari context enrichment;
- mode resmi saat ini adalah audit-only;
- default config tetap disabled;
- provider teknis yang disiapkan saat itu adalah `maxmind_anonymous_ip`;
- `risk_score` tetap `null` untuk provider `maxmind_anonymous_ip` karena
  provider ini tidak memberi score resmi 0-100;
- belum ada command/job penghapusan otomatis.

Update 2026-08-10, IP risk hold:

- provider IP risk sengaja di-hold;
- config resmi tetap `AUTH_LOGIN_EVENT_IP_RISK_ENABLED=false`,
  `AUTH_LOGIN_EVENT_IP_RISK_PROVIDER=none`, dan
  `AUTH_LOGIN_EVENT_IP_RISK_BLOCKING_ENABLED=false`;
- MaxMind Anonymous IP tidak tersedia sebagai GeoLite gratis seperti City/ASN;
- provider eksternal/privacy detection berbayar atau free-tier belum boleh
  dipakai tanpa persetujuan user;
- `is_vpn`, `is_proxy`, `is_tor`, dan `risk_score` bernilai `null` adalah
  kondisi by design selama provider di-hold, bukan bug.

## 4. Klasifikasi kolom berdasarkan sumber data

### 4.1 Data langsung dari Laravel request

Kolom:

- `ip_address`;
- `user_agent`;
- `accept_language`;
- `route_name`;
- `request_path`;
- `http_method`;
- `http_status`.

Sumber:

- `Request::ip()`;
- `Request::userAgent()`;
- header `Accept-Language`;
- route dan HTTP request Laravel.

Status:

- boleh diisi langsung saat event dibuat;
- tidak membutuhkan library tambahan;
- tetap boleh `null` jika request tidak memiliki data tersebut.

Catatan:

- `user_agent` adalah string mentah dari browser/client;
- `user_agent` bukan hasil parsing device/browser/platform;
- `accept_language` berasal dari header client dan tidak boleh dianggap
  sebagai locale aplikasi yang sudah divalidasi.

### 4.2 Data dari reverse proxy terpercaya

Kolom:

- `proxy_ip_address`;
- `forwarded_for`;
- `request_id`;
- `correlation_id`.

Sumber:

- `X-Forwarded-For`;
- `X-Forwarded-Host`;
- `X-Forwarded-Proto`;
- `X-Real-IP`;
- `X-Request-Id`;
- `X-Correlation-Id`;
- header lain dari reverse proxy/load balancer resmi.

Status:

- hanya boleh dipercaya setelah trusted proxy dikonfigurasi benar;
- `forwarded_for` boleh `null` pada local development atau deployment tanpa
  reverse proxy;
- `request_id` dan `correlation_id` hanya boleh disimpan jika formatnya valid,
  misalnya UUID.

Update 2026-08-03:

- `RequestNetworkContext` sudah menjadi service resmi untuk network audit;
- `request_id` dibaca dari header `X-Request-Id` hanya jika UUID valid;
- `correlation_id` dibaca dari header `X-Correlation-Id` hanya jika UUID valid;
- `ip_address` dibaca dari Laravel `Request::ip()` dan divalidasi sebagai IP;
- `forwarded_for` hanya disimpan jika request berasal dari trusted proxy, atau
  jika config `auth.audit.login_events.enrichment.network.capture_untrusted_forwarded_for`
  diaktifkan secara eksplisit;
- `proxy_ip_address` hanya disimpan dari `REMOTE_ADDR` jika request berasal dari
  trusted proxy dan `forwarded_for` valid.
- production yang berada di balik reverse proxy/load balancer harus mengisi
  `TRUSTED_PROXIES` dengan IP/CIDR proxy resmi, `REMOTE_ADDR`, atau `*` hanya
  bila deployment memang membutuhkan trust-all proxy.

Larangan:

- jangan percaya header proxy dari public client langsung;
- jangan mengisi `proxy_ip_address` dari header tanpa daftar trusted proxy;
- jangan menyimpan request ID sembarang string panjang tanpa validasi.

Rekomendasi implementasi:

- buat service `RequestNetworkContext`;
- ambil IP efektif dari Laravel setelah trusted proxy aktif;
- simpan chain `X-Forwarded-For` yang sudah dinormalisasi;
- validasi setiap item sebagai IP address;
- batasi jumlah IP chain yang disimpan;
- batasi panjang header;
- simpan `proxy_ip_address` hanya jika proxy terakhir valid dan dipercaya.

### 4.3 Data hasil parsing user-agent

Kolom:

- `device_type`;
- `device_name`;
- `browser_name`;
- `browser_version`;
- `platform_name`;
- `platform_version`.

Sumber:

- `user_agent`;
- optional client hints jika nanti ditambahkan.

Status:

- belum boleh diisi manual tanpa parser atau aturan resmi;
- boleh `null` jika parser gagal atau user-agent kosong;
- parsing boleh dilakukan sync karena ringan.

Rekomendasi library:

- pilihan utama: `matomo/device-detector`;
- alternatif: `whichbrowser/parser`.

Alasan rekomendasi:

- user-agent parsing berubah terus mengikuti browser/device;
- membuat parser manual akan rapuh dan cepat usang;
- library mengurangi risiko salah klasifikasi.

Rekomendasi implementasi:

- buat service `UserAgentContext`;
- input utama: raw user-agent string;
- output: array dengan key sesuai kolom `login_events`;
- normalisasi nilai menjadi string pendek yang aman;
- potong versi yang terlalu panjang;
- jangan throw exception ke login flow jika parsing gagal.

Contoh mapping:

| Output parser | Kolom |
|---|---|
| device type | `device_type` |
| device brand/model | `device_name` |
| client/browser name | `browser_name` |
| client/browser version | `browser_version` |
| OS/platform name | `platform_name` |
| OS/platform version | `platform_version` |

### 4.4 Data GeoIP dan ASN

Kolom:

- `network_asn`;
- `network_organization`;
- `country_code`;
- `region`;
- `city`.

Sumber:

- IP address efektif;
- database GeoIP/ASN lokal;
- provider API GeoIP/ASN.

Status:

- belum boleh diisi tanpa provider resmi;
- boleh `null` jika IP private/local, provider tidak tersedia, lookup gagal,
  atau data tidak ditemukan;
- tidak boleh dianggap presisi lokasi user.

Rekomendasi provider:

- pilihan utama: MaxMind GeoLite2 City dan ASN local database;
- alternatif: IPinfo, IP2Location, atau provider resmi lain.

Alasan local database disarankan:

- lebih cepat;
- tidak membuat login tergantung HTTP eksternal;
- lebih baik untuk privacy karena IP user tidak harus dikirim ke pihak ketiga;
- bisa dicache dan diupdate berkala.

Rekomendasi implementasi:

- buat service `IpGeolocationContext`;
- input: IP efektif dari request;
- skip IP private, loopback, link-local, dan reserved;
- cache hasil per IP atau prefix dengan TTL wajar;
- simpan data seperlunya, bukan raw response provider;
- sediakan command/scheduler update database GeoIP jika memakai local DB.

Catatan privacy:

- `country_code`, `region`, dan `city` hanya perkiraan;
- jangan gunakan GeoIP sebagai satu-satunya dasar blokir akun;
- gunakan GeoIP sebagai sinyal audit/risk, bukan kebenaran mutlak.

### 4.5 Data VPN, proxy, Tor, dan risk score

Kolom:

- `is_vpn`;
- `is_proxy`;
- `is_tor`;
- `risk_score`.

Sumber:

- IP reputation provider;
- anonymous IP database;
- Tor exit node list;
- sinyal internal seperti failed attempt, captcha, MFA, dan device baru.

Status:

- policy resmi berada di `IP_RISK_DECISIONS.md`;
- `IpRiskContext` sudah tersedia;
- provider IP risk sengaja di-hold;
- default tetap disabled;
- mode resmi saat ini adalah audit-only;
- provider teknis yang pernah disiapkan adalah `maxmind_anonymous_ip`, tetapi
  belum approved untuk diaktifkan karena tidak tersedia sebagai GeoLite gratis;
- `null` berarti belum diperiksa, provider disabled, provider gagal, IP tidak
  public, allowlist dilewati, atau data tidak tersedia;
- `risk_score` tetap `null` untuk provider `maxmind_anonymous_ip` karena belum
  ada score resmi.

Provider yang pernah dipertimbangkan:

- MaxMind Anonymous IP local database, tetapi tidak tersedia sebagai GeoLite
  gratis dan belum approved;
- minFraud perlu decision baru;
- IPinfo privacy flags;
- IPQualityScore;
- provider resmi lain yang disetujui project.

Status 2026-08-10: tidak ada provider IP risk yang aktif. Daftar di atas
hanya referensi opsi masa depan dan tidak boleh diimplementasikan tanpa
persetujuan user.

Rekomendasi implementasi:

- service resmi adalah `IpRiskContext`;
- input: IP efektif, user, login identifier, captcha score, MFA result, dan
  context lain yang tidak sensitif;
- output: boolean nullable dan score nullable;
- pakai timeout pendek jika HTTP API;
- cache hasil lookup IP;
- log error provider secara aman;
- jangan blokir login hanya karena provider gagal.

Rekomendasi `risk_score`:

- skala 0 sampai 100;
- `0` berarti risiko sangat rendah setelah dihitung;
- `100` berarti risiko sangat tinggi setelah dihitung;
- `null` berarti belum dihitung;
- scoring rule harus ditulis di docs sebelum implementasi blocking policy.

Contoh sinyal yang boleh menjadi komponen risk score:

- IP terdeteksi VPN/proxy/Tor;
- banyak failed attempts dari identifier atau IP yang sama;
- captcha score rendah;
- MFA gagal berulang;
- login dari negara baru untuk user yang sama;
- session lama direvoke karena single-device policy;
- user mencoba akses Admin Super tanpa MFA valid.

Larangan:

- jangan langsung memblokir semua VPN/proxy tanpa policy bisnis;
- jangan menghukum user hanya karena GeoIP berbeda;
- jangan menyimpan raw provider response berisi data berlebihan.

### 4.6 Data dari browser/client

Kolom:

- `client_timezone`;
- `device_fingerprint_hash`.

Sumber:

- JavaScript pada form login atau request internal;
- hidden input;
- custom header aplikasi;
- first-party device token jika nanti dibuat.

Status:

- belum tersedia dari server murni;
- harus dikirim oleh browser/client;
- harus divalidasi dan dibatasi panjangnya;
- `device_fingerprint_hash` membutuhkan decision privacy lebih ketat sebelum
  implementasi penuh.

Rekomendasi `client_timezone`:

- ambil dari browser dengan:
  `Intl.DateTimeFormat().resolvedOptions().timeZone`;
- kirim sebagai hidden input di form login;
- batasi sebagai string nullable maksimal 100 karakter pada Form Request;
- validasi nilai terhadap daftar timezone PHP di service enrichment;
- batasi panjang maksimal sesuai kolom;
- jika kosong atau invalid, simpan `null`.

Rekomendasi `device_fingerprint_hash`:

- jangan memakai fingerprint invasif berbasis canvas/audio/font probing tanpa
  persetujuan policy baru;
- lebih baik gunakan first-party device id/token yang dibuat aplikasi;
- simpan hanya hash HMAC, bukan raw token;
- gunakan audit hash key resmi;
- rotasi atau reset jika user logout semua device, reset MFA, atau insiden
  keamanan.

Larangan:

- jangan menyimpan daftar font, canvas fingerprint, plugin browser, screen
  detail mentah, atau atribut browser invasif;
- jangan menjadikan fingerprint sebagai satu-satunya faktor autentikasi;
- jangan menampilkan fingerprint hash ke user biasa.

### 4.7 Data sistem dan deployment

Kolom:

- `application_version`;
- `environment`;
- `server_node`.

Sumber:

- config aplikasi;
- environment deploy;
- build metadata;
- hostname server.

Status:

- `environment` dan `server_node` sudah bisa diisi saat ini;
- `application_version` sudah diisi melalui `ApplicationVersionContext` jika
  metadata deploy tersedia.

Rekomendasi implementasi:

- sumber resmi adalah
  `config('auth.audit.login_events.enrichment.application')`;
- env yang tersedia: `APP_VERSION`, `APP_BUILD_NUMBER`, dan
  `APP_BUILD_COMMIT`;
- format akhir harus tetap maksimal 50 karakter karena kolom database
  `application_version` terbatas;
- isi env dari release/deploy pipeline, bukan hardcode di controller, action,
  request, migration, seeder, atau view;
- jika metadata deploy belum tersedia, simpan `null`.

### 4.8 Data integrity dan retention

Kolom:

- `event_hash`;
- `retention_until`.

Status:

- sudah diimplementasikan melalui `LoginEventIntegrity` dan
  `LoginEventRetention`;
- `event_hash` aktif bila
  `config('auth.audit.login_events.enrichment.integrity.event_hash_enabled')`
  bernilai true;
- `retention_until` hanya diisi bila
  `config('auth.audit.login_events.enrichment.integrity.retention_days')`
  berisi angka positif;
- tidak ada job penghapusan otomatis pada tahap ini.

Rekomendasi `event_hash`:

- service resmi adalah `LoginEventIntegrity`;
- hash payload canonical setelah field event final siap dan sebelum insert;
- gunakan HMAC-SHA256 dengan audit hash key;
- payload hash menyertakan `event_hash_payload_version`;
- field `event_hash` sendiri wajib dikecualikan;
- field `login_identifier` dikecualikan agar payload canonical tidak bergantung
  pada plaintext identifier yang disimpan encrypted cast;
- gunakan `login_identifier_hash` untuk bukti identifier di payload;
- jangan memakai hash biasa tanpa secret jika tujuannya tamper evidence;
- jika event hash disabled, kolom boleh `null`.

Rekomendasi `retention_until`:

- service resmi adalah `LoginEventRetention`;
- `retention_until` dihitung dari `occurred_at + retention_days`;
- `retention_days` wajib config-based melalui `AUTH_LOGIN_EVENT_RETENTION_DAYS`;
- nilai kosong, nol, negatif, atau invalid berarti retention marker tidak
  diisi;
- jangan membuat job penghapusan otomatis sebelum policy retensi operasional
  disetujui;
- event security penting dapat dipindahkan ke archive sebelum dihapus.

## 5. Boundary arsitektur kode

`RecordAuthenticationEvent` harus tetap menjadi action pencatat event.

Action ini boleh:

- menerima `Request`;
- menerima `User`;
- menerima `UserPosition`;
- menerima `overrides`;
- menggabungkan payload final;
- membuat row `LoginEvent`.

Action ini tidak boleh menjadi tempat utama untuk:

- parsing user-agent;
- lookup GeoIP;
- lookup ASN;
- lookup VPN/proxy/Tor;
- menghitung risk score kompleks;
- membaca JavaScript/client fingerprint;
- memanggil HTTP API eksternal secara langsung;
- membuat policy blocking login.

Service yang direkomendasikan:

| Service | Tugas |
|---|---|
| `AuthenticationEventContext` | Menggabungkan semua context sebelum event disimpan |
| `RequestNetworkContext` | Normalisasi IP, proxy, forwarded chain, request ID |
| `UserAgentContext` | Parse user-agent menjadi device/browser/platform |
| `ApplicationVersionContext` | Bentuk `application_version` dari version/build/commit deploy metadata |
| `IpGeolocationContext` | Lookup GeoIP dan ASN |
| `IpRiskContext` | Lookup VPN/proxy/Tor; `risk_score` hanya diisi bila ada provider/scoring resmi |
| `ClientSignalContext` | Validasi timezone dan fingerprint hash dari client |
| `LoginEventIntegrity` | Hitung `event_hash` |
| `LoginEventRetention` | Hitung `retention_until` |

AI agent boleh menyesuaikan nama class agar lebih sesuai dengan struktur app,
tetapi boundary tanggung jawab di atas harus dipertahankan.

## 6. Sync, deferred, queue, dan cache

### 6.1 Boleh sync

Enrichment berikut boleh sync di request login:

- raw request fields;
- session hash;
- user-agent parsing lokal;
- validation client timezone;
- application version;
- environment;
- server node.

Alasan:

- cepat;
- tidak membutuhkan network;
- failure mudah dibuat `null`.

### 6.2 Sebaiknya deferred atau queue

Enrichment berikut sebaiknya tidak memblokir login:

- HTTP API GeoIP;
- HTTP API IP reputation;
- risk intelligence provider;
- heavy fingerprint analysis;
- event integrity backfill;
- archival/retention processing.

Strategi:

- simpan event dasar dulu;
- jalankan enrichment setelah response jika ringan;
- gunakan queue/job jika perlu retry;
- gunakan cache untuk hasil provider;
- jangan update event append-only tanpa policy.

Catatan append-only:

Karena `login_events` adalah audit append-only, enrichment idealnya tersedia
sebelum insert. Jika enrichment harus menyusul setelah insert, project harus
membuat decision tambahan apakah update enrichment pada event audit diizinkan,
atau apakah enrichment lanjutan disimpan sebagai event baru/metadata terpisah.

Untuk kondisi sekarang, rekomendasi default adalah:

- enrichment murah dilakukan sebelum insert;
- enrichment eksternal ditunda sampai ada decision apakah update event audit
  pasca-insert diizinkan.

## 7. Policy provider dan config

Semua provider harus dikonfigurasi melalui config file, bukan `env()` langsung
di service.

Contoh struktur config yang direkomendasikan:

```php
'audit' => [
    'hash_key' => env('AUDIT_HASH_KEY'),
    'login_events' => [
        'enrichment' => [
            'user_agent' => [
                'enabled' => true,
                'provider' => 'matomo_device_detector',
            ],
            'geoip' => [
                'enabled' => false,
                'provider' => 'maxmind',
                'database_path' => env('AUTH_LOGIN_EVENT_GEOIP_DATABASE_PATH'),
                'city_database_path' => env('AUTH_LOGIN_EVENT_GEOIP_CITY_DATABASE_PATH'),
                'asn_database_path' => env('AUTH_LOGIN_EVENT_GEOIP_ASN_DATABASE_PATH'),
                'cache_ttl_seconds' => 86400,
            ],
            'ip_risk' => [
                'enabled' => false,
                'provider' => null,
                'timeout_seconds' => 2,
                'connect_timeout_seconds' => 1,
                'cache_ttl_seconds' => 86400,
            ],
            'client_timezone' => [
                'enabled' => true,
            ],
            'device_fingerprint' => [
                'enabled' => false,
                'strategy' => 'first_party_device_token',
            ],
        ],
    ],
],
```

Catatan:

- struktur final boleh ditempatkan di `config/auth.php` agar selaras dengan
  policy auth saat ini;
- AI agent tidak boleh menaruh secret provider langsung di source code;
- provider eksternal harus bisa dimatikan lewat config;
- default production yang aman adalah tidak mengaktifkan provider eksternal
  sampai credential dan policy tersedia.

## 8. Urutan implementasi resmi yang disarankan

### Tahap 1 - Decision dan config

Tujuan:

- membuat policy eksplisit;
- menghindari AI agent salah mengisi kolom nullable;
- menyiapkan config sebelum kode service.

Yang dilakukan:

- tulis/update policy ini;
- tambahkan config enrichment;
- dokumentasikan default enabled/disabled;
- pastikan tidak ada secret di source code.

### Tahap 2 - User-agent parser

Tujuan:

- mengisi device/browser/platform dari `user_agent`;
- memberi nilai audit yang berguna tanpa network call.

Yang dibutuhkan:

- approval dependency jika belum ada;
- package `matomo/device-detector` atau alternatif resmi;
- service `UserAgentContext`.

Kolom yang ditarget:

- `device_type`;
- `device_name`;
- `browser_name`;
- `browser_version`;
- `platform_name`;
- `platform_version`.

### Tahap 3 - Proxy dan request correlation

Tujuan:

- membuat IP audit benar ketika aplikasi berada di balik reverse proxy;
- menjaga header tidak dipalsukan.

Yang dibutuhkan:

- review deployment topology;
- konfigurasi trusted proxy;
- service `RequestNetworkContext`;
- validasi IP chain;
- validasi UUID untuk request/correlation ID.

Kolom yang ditarget:

- `proxy_ip_address`;
- `forwarded_for`;
- `request_id`;
- `correlation_id`.

### Tahap 4 - Client timezone

Tujuan:

- menyimpan timezone browser untuk investigasi UX/security ringan.

Yang dibutuhkan:

- hidden input atau header dari browser;
- JavaScript kecil di form login;
- validasi request.

Kolom yang ditarget:

- `client_timezone`.

### Tahap 5 - Application version

Tujuan:

- mengetahui versi aplikasi saat event terjadi.

Status:

- sudah diimplementasikan melalui `ApplicationVersionContext`;
- sumber resmi berada di
  `config('auth.audit.login_events.enrichment.application')`;
- env yang dibaca: `APP_VERSION`, `APP_BUILD_NUMBER`, dan
  `APP_BUILD_COMMIT`.

Yang dibutuhkan:

- production/deploy pipeline perlu mengisi minimal `APP_VERSION` atau
  `APP_BUILD_COMMIT`;
- fallback tetap `null` jika metadata belum tersedia.

Kolom yang ditarget:

- `application_version`.

### Tahap 6 - GeoIP dan ASN

Tujuan:

- menambah konteks lokasi dan jaringan.

Status:

- sudah diimplementasikan melalui `IpGeolocationContext`;
- package `geoip2/geoip2` sudah terpasang;
- runbook setup berada di `GEOIP_MAXMIND_RUNBOOK.md`;
- command status resmi adalah `php artisan auth:geoip-status`;
- lookup hanya aktif jika
  `config('auth.audit.login_events.enrichment.geoip.enabled')` bernilai true;
- provider yang didukung saat ini adalah `maxmind`;
- service hanya membaca local database dan tidak melakukan HTTP request
  eksternal saat login.

Yang dibutuhkan:

- file MaxMind GeoLite2 City `.mmdb`;
- file MaxMind GeoLite2 ASN `.mmdb`;
- env `AUTH_LOGIN_EVENT_GEOIP_CITY_DATABASE_PATH`;
- env `AUTH_LOGIN_EVENT_GEOIP_ASN_DATABASE_PATH`;
- command/scheduler update database belum dibuat;
- cache lookup;
- handling IP private/local.

Kolom yang ditarget:

- `network_asn`;
- `network_organization`;
- `country_code`;
- `region`;
- `city`.

### Tahap 7 - IP risk intelligence

Tujuan:

- mendeteksi VPN/proxy/Tor dan memberi sinyal risiko.

Status:

- fondasi sudah diimplementasikan melalui `IpRiskContext`;
- decision resmi berada di `IP_RISK_DECISIONS.md`;
- provider enrichment sengaja di-hold sejak 2026-08-10;
- mode resmi saat ini adalah audit-only;
- default config tetap disabled;
- provider resmi saat ini adalah `none`;
- `risk_score` belum dihitung karena belum ada provider/scoring resmi 0-100.

Yang dibutuhkan:

- persetujuan user sebelum memilih provider IP risk;
- decision provider baru yang membahas biaya, token, privacy, limit, timeout,
  cache, dan mapping response;
- timeout pendek;
- cache;
- error handling;
- decision baru jika ingin provider HTTP eksternal, provider berbayar, free
  tier, provider parsial, atau blocking policy.

Kolom yang ditarget:

- `is_vpn`;
- `is_proxy`;
- `is_tor`;
- `risk_score`.

### Tahap 8 - Event integrity dan retention

Tujuan:

- mendukung tamper evidence;
- menyiapkan lifecycle audit data.

Status:

- sudah diimplementasikan melalui `LoginEventIntegrity` dan
  `LoginEventRetention`;
- `event_hash` dihitung sebelum insert bila
  `AUTH_LOGIN_EVENT_HASH_ENABLED=true`;
- `retention_until` dihitung sebelum insert bila
  `AUTH_LOGIN_EVENT_RETENTION_DAYS` berisi angka positif;
- belum ada command/job penghapusan data audit.

Yang dibutuhkan:

- `AUDIT_HASH_KEY`;
- payload canonical versi `v1`;
- policy jumlah hari retensi jika `retention_until` ingin diisi;
- keputusan terpisah sebelum membuat job penghapusan otomatis.

Kolom yang ditarget:

- `event_hash`;
- `retention_until`.

### Tahap 9 - Device fingerprint minimal

Tujuan:

- membantu deteksi device baru tanpa fingerprint invasif.

Yang dibutuhkan:

- privacy decision tambahan;
- first-party device token;
- hash HMAC;
- UI/security wording jika diperlukan;
- reset/rotate policy.

Kolom yang ditarget:

- `device_fingerprint_hash`.

## 9. Rules per event type

### Login success

Wajib dicatat:

- user yang berhasil login;
- real active position jika sudah tersedia;
- remember-me decision;
- session hash jika session tersedia;
- MFA state jika relevan;
- captcha state jika relevan;
- request context dasar.

Enrichment boleh ditambahkan sesuai service yang aktif.

### Login failed

Wajib dicatat:

- identifier type dan identifier yang dipakai;
- hash identifier;
- failure code;
- attempt number;
- IP dan request context dasar;
- captcha state jika relevan.

Jika user tidak ditemukan, `user_id` tetap `null`.

### Logout

Wajib dicatat:

- user;
- session hash jika tersedia;
- active/effective context jika tersedia;
- reason jika logout otomatis.

Logout normal tidak harus memiliki risk score.

### MFA challenge

Wajib dicatat:

- event `mfa_challenge`;
- method seperti `totp` atau `recovery_code`;
- result seperti `success`, `failed`, atau `blocked`;
- failure code jika gagal.

Larangan:

- jangan menyimpan OTP;
- jangan menyimpan recovery code mentah;
- jangan menyimpan TOTP secret;
- jangan menyimpan QR provisioning URI.

### Remember-me expired atau revoked

Wajib dicatat:

- event session revoke/expired sesuai flow;
- user jika bisa diidentifikasi;
- session hash jika tersedia;
- reason seperti `remember_me_expired`;
- metadata non-sensitive jika dibutuhkan.

### Admin impersonation masa depan

Jika fitur impersonation dibuat:

- `user_id` adalah user yang menjadi subjek;
- `actor_user_id` adalah admin/operator yang melakukan tindakan;
- event harus jelas membedakan start, stop, success, failed, atau revoked;
- jangan mencampur actor dengan subject.

## 10. Security dan privacy checklist

Sebelum mengaktifkan enrichment baru, AI agent harus memastikan:

- provider bisa dimatikan lewat config;
- secret provider hanya dibaca dari config;
- tidak ada `env()` langsung di service;
- timeout HTTP eksplisit;
- cache tidak menyimpan data sensitif berlebihan;
- raw provider response tidak disimpan ke `metadata`;
- IP private/local tidak dikirim ke provider eksternal;
- boolean risk tidak diisi jika lookup gagal;
- login tetap berjalan ketika provider gagal;
- field string dipotong sesuai ukuran kolom;
- device fingerprint tidak invasif;
- tidak ada secret MFA/auth yang masuk audit;
- tidak membuat atau menjalankan test suite tanpa konfirmasi user.

## 11. Acceptance criteria untuk implementasi enrichment

Implementasi dianggap proper jika:

- `RecordAuthenticationEvent` tetap ramping;
- service enrichment memiliki tanggung jawab tunggal;
- kolom yang belum bisa dipercaya tetap `null`;
- parser/provider failure tidak menggagalkan login;
- config menjadi sumber policy;
- dependency baru sudah mendapat persetujuan user;
- dokumentasi diperbarui;
- formatting PHP dijalankan jika ada file PHP diubah;
- test suite hanya dibuat/dijalankan setelah user mengonfirmasi.

## 12. Larangan eksplisit untuk AI agent

AI agent tidak boleh:

1. Mengubah makna `null` menjadi `false` pada kolom risk.
2. Menyimpan data rahasia atau credential di `login_events`.
3. Menaruh semua logic enrichment di `RecordAuthenticationEvent`.
4. Menggunakan GeoIP sebagai kebenaran lokasi presisi.
5. Mengaktifkan provider eksternal production tanpa config dan secret resmi.
6. Membuat scoring risiko tanpa decision rumus.
7. Memblokir login hanya karena provider enrichment down.
8. Membuat fingerprint browser invasif tanpa decision privacy baru.
9. Mengupdate event append-only pasca-insert tanpa decision tambahan.
10. Membuat, memodifikasi, atau menjalankan test suite tanpa konfirmasi user.

## 13. Referensi docs internal

AI agent yang mengerjakan area ini juga wajib membaca:

- `docs/01-authentication/README.md`;
- `docs/01-authentication/AI_AGENT_DATABASE_CONTEXT.md`;
- `docs/01-authentication/LOGIN_EVENTS_TABLE.md`;
- `docs/01-authentication/AUTH_CONTEXT_DECISIONS.md`;
- `docs/01-authentication/CURRENT_AUTH_CONTEXT_IMPLEMENTATION.md`;
- `docs/00-ai-agent/PROJECT_INVARIANTS.md`;
- `docs/06-migrations/FRESH_INSTALL_READINESS.md` jika menyentuh migration.

## 14. Rekomendasi implementasi terdekat

Urutan yang paling aman setelah policy ini:

1. Buat command update MaxMind `.mmdb` jika account/license resmi tersedia.
2. Hold IP risk provider sampai ada persetujuan user dan decision provider.
3. Baru bahas fingerprint minimal setelah ada decision privacy tambahan.

Tahap berikutnya harus dilakukan bertahap agar audit tetap akurat dan tidak
membuat login flow menjadi berat.
