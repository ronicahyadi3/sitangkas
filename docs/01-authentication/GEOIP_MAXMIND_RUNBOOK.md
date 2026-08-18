# GeoIP MaxMind Runbook

Dokumen ini adalah panduan operator/admin teknis untuk menyiapkan dan
memeriksa enrichment GeoIP/ASN `login_events` SITANGKAS.

AI agent wajib membaca dokumen ini sebelum mengubah config GeoIP, command
GeoIP, atau service `App\Services\Auth\IpGeolocationContext`.

## Tujuan

GeoIP/ASN dipakai untuk memperkaya audit login dengan konteks jaringan.

Kolom yang ditarget:

- `country_code`;
- `region`;
- `city`;
- `network_asn`;
- `network_organization`.

GeoIP/ASN tidak boleh dianggap lokasi presisi user. Data ini hanya sinyal audit
dan risk analysis awal.

## Implementasi Saat Ini

Package:

```text
geoip2/geoip2
```

Service:

```text
app/Services/Auth/IpGeolocationContext.php
```

Aggregator:

```text
app/Services/Auth/AuthenticationEventContext.php
```

Command status:

```bash
php artisan auth:geoip-status
```

Config resmi:

```text
config('auth.audit.login_events.enrichment.geoip')
```

## Provider Resmi

Provider yang didukung saat ini:

```text
maxmind
```

Database yang dipakai:

- MaxMind GeoLite2 City untuk `country_code`, `region`, dan `city`;
- MaxMind GeoLite2 ASN untuk `network_asn` dan `network_organization`.

Referensi resmi:

- `https://dev.maxmind.com/geoip/geolite2-free-geolocation-data/`;
- `https://dev.maxmind.com/geoip/updating-databases/`;
- `https://github.com/maxmind/GeoIP2-php`.

## Lokasi File Database

Lokasi rekomendasi project:

```text
storage/app/geoip/GeoLite2-City.mmdb
storage/app/geoip/GeoLite2-ASN.mmdb
```

File `.mmdb` tidak boleh masuk git.

Pastikan storage server:

- readable oleh user proses PHP/web server;
- tidak berada di public web root;
- punya prosedur update berkala;
- tidak dihapus saat deploy.

## Env Yang Perlu Diset

Default aman adalah disabled:

```env
AUTH_LOGIN_EVENT_GEOIP_ENABLED=false
AUTH_LOGIN_EVENT_GEOIP_PROVIDER=maxmind
AUTH_LOGIN_EVENT_GEOIP_DATABASE_PATH=
AUTH_LOGIN_EVENT_GEOIP_CITY_DATABASE_PATH=
AUTH_LOGIN_EVENT_GEOIP_ASN_DATABASE_PATH=
AUTH_LOGIN_EVENT_GEOIP_CACHE_TTL_SECONDS=86400
```

Contoh production ketika file sudah tersedia:

```env
AUTH_LOGIN_EVENT_GEOIP_ENABLED=true
AUTH_LOGIN_EVENT_GEOIP_PROVIDER=maxmind
AUTH_LOGIN_EVENT_GEOIP_CITY_DATABASE_PATH=storage/app/geoip/GeoLite2-City.mmdb
AUTH_LOGIN_EVENT_GEOIP_ASN_DATABASE_PATH=storage/app/geoip/GeoLite2-ASN.mmdb
AUTH_LOGIN_EVENT_GEOIP_CACHE_TTL_SECONDS=86400
```

`AUTH_LOGIN_EVENT_GEOIP_DATABASE_PATH` hanya legacy fallback untuk City DB.
Untuk config baru, pakai `AUTH_LOGIN_EVENT_GEOIP_CITY_DATABASE_PATH` dan
`AUTH_LOGIN_EVENT_GEOIP_ASN_DATABASE_PATH`.

Setelah mengubah env di production, jalankan proses deploy/config cache sesuai
prosedur server:

```bash
php artisan config:cache
```

Di local development boleh membersihkan cache config jika env belum terbaca:

```bash
php artisan config:clear
```

## Command Status

Command utama:

```bash
php artisan auth:geoip-status
```

Command dengan lookup IP contoh:

```bash
php artisan auth:geoip-status --ip=8.8.8.8
```

Lewati lookup sample:

```bash
php artisan auth:geoip-status --no-lookup
```

Gagal jika GeoIP enabled tetapi salah satu database belum readable:

```bash
php artisan auth:geoip-status --fail-on-missing
```

Gunakan `--fail-on-missing` pada health check deployment production.

## Interpretasi Output

Jika GeoIP disabled:

- command tetap sukses;
- lookup akan dilewati;
- kolom GeoIP/ASN pada login event tetap `null`.

Jika GeoIP enabled dan City DB readable:

- `country_code`, `region`, dan `city` mulai bisa terisi.

Jika GeoIP enabled dan ASN DB readable:

- `network_asn` dan `network_organization` mulai bisa terisi.

Jika hanya salah satu DB readable:

- command memberi warning;
- kolom dari DB yang tidak tersedia tetap `null`;
- pakai `--fail-on-missing` jika production harus mensyaratkan keduanya.

Jika provider bukan `maxmind`:

- command gagal saat GeoIP enabled;
- service tidak melakukan lookup.

Jika IP private, loopback, atau reserved:

- service menghasilkan `null`;
- ini expected behavior.

## Checklist Setup Production

1. Buat akun MaxMind dan siapkan license key sesuai prosedur resmi MaxMind.
2. Unduh database GeoLite2 City dan GeoLite2 ASN.
3. Taruh file `.mmdb` di folder storage non-public.
4. Pastikan permission file readable oleh proses PHP.
5. Set env `AUTH_LOGIN_EVENT_GEOIP_*`.
6. Jalankan `php artisan config:cache`.
7. Jalankan `php artisan auth:geoip-status --ip=8.8.8.8 --fail-on-missing`.
8. Login sekali dari browser, lalu periksa row `login_events` terbaru.
9. Pastikan kolom GeoIP/ASN terisi sesuai data provider, atau `null` jika IP
   tidak ada di database.

## Batasan Dan Larangan

AI agent tidak boleh:

1. Mengisi `country_code`, `region`, atau `city` dengan default `ID`,
   `Jawa Timur`, atau `Malang`.
2. Memblokir login hanya karena GeoIP berbeda.
3. Memakai GeoIP sebagai bukti lokasi presisi user.
4. Menaruh MaxMind license key di source code atau docs repo.
5. Menyimpan file `.mmdb` di folder public.
6. Melakukan HTTP request eksternal saat login tanpa decision baru.
7. Mengubah event audit lama untuk mengisi GeoIP pasca-insert tanpa decision
   append-only baru.

## Troubleshooting

`enabled=false`:

- set `AUTH_LOGIN_EVENT_GEOIP_ENABLED=true`;
- pastikan config cache sudah diperbarui.

Path tidak readable:

- cek env path;
- cek apakah path relative terhadap root project;
- cek permission file;
- cek file tidak hilang saat deploy.

Lookup tetap `null`:

- pastikan IP adalah public IP;
- pastikan database City/ASN sesuai;
- coba IP sample lain;
- ingat bahwa tidak semua IP punya detail city/region.

Composer class tidak ditemukan:

- jalankan `composer install`;
- pastikan `geoip2/geoip2` ada di `composer.json` dan `composer.lock`.

## Tahapan Lanjutan

Setelah status command dan database operasional stabil, tahapan berikutnya:

1. Buat command update MaxMind DB jika license/account sudah tersedia.
2. Baru bahas IP risk/VPN/proxy/Tor provider.
3. Baru bahas `event_hash`, `retention_until`, dan fingerprint minimal.
