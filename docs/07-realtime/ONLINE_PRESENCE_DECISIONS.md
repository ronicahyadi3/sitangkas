# Online Presence Decisions

## 1. Tujuan

Dokumen ini mendefinisikan makna status online untuk SITANGKAS.

Tujuan utamanya adalah mencegah dashboard admin menampilkan user sebagai online hanya karena mereka lupa logout atau masih memiliki session Laravel yang valid.

## 2. Definisi status

| Status | Definisi | Contoh |
|---|---|---|
| `online` | Koneksi Reverb aktif atau heartbeat masih segar | User sedang membuka aplikasi |
| `active` | User online dan ada aktivitas input/klik/navigasi baru-baru ini | User mengisi form atau membuka halaman |
| `idle` | User online, tetapi tidak ada aktivitas dalam batas waktu idle | Tab terbuka tetapi user diam |
| `away` | Tab/browser berada di background atau tidak visible | User pindah tab atau minimize browser |
| `offline` | Koneksi putus atau heartbeat melewati timeout | User menutup tab, koneksi hilang, laptop sleep |
| `session_active` | Session Laravel masih valid di database | User lupa logout tetapi tidak sedang membuka aplikasi |

`session_active` bukan status online realtime. Ini adalah status autentikasi/session.

## 3. Keputusan untuk close tab dan pindah tab

### User menutup tab tanpa logout

Keputusan:

```text
offline setelah disconnect/heartbeat timeout
session_active tetap benar sampai session expired atau dicabut
```

Jangan tampilkan user sebagai `online` terus-menerus hanya karena session masih ada.

### User pindah tab

Keputusan:

```text
tetap online selama koneksi Reverb/heartbeat masih berjalan
status aktivitas boleh berubah menjadi away atau idle
```

Pindah tab bukan otomatis offline.

### Browser sleep, jaringan putus, atau perangkat sleep

Keputusan:

```text
offline setelah heartbeat timeout
```

Jangan bergantung hanya pada event `beforeunload` karena event browser tidak selalu terkirim.

## 4. Timeout yang direkomendasikan

Nilai awal yang direkomendasikan:

| Parameter | Nilai awal | Catatan |
|---|---:|---|
| Heartbeat interval | 30 detik | Browser mengirim sinyal masih hidup |
| Heartbeat timeout | 90-120 detik | Setelah ini status menjadi offline |
| Idle threshold | 5 menit | Tidak ada aktivitas input/klik/scroll/navigasi |
| Away threshold | 30-60 detik | Tab hidden atau browser background |
| Session active window | mengikuti `config('session.lifetime')` | Bukan indikator online |

Nilai dapat disesuaikan setelah melihat perilaku user dan beban server.

## 5. Sinyal dari frontend

Frontend sebaiknya mengirim sinyal berikut:

| Sinyal | Sumber browser | Tujuan |
|---|---|---|
| connected | Echo/Reverb connected | Tandai koneksi realtime aktif |
| disconnected | Echo/Reverb disconnected | Tandai koneksi putus |
| heartbeat | interval timer | Update `last_seen_at` |
| activity | click, keydown, pointer, navigation | Update `last_activity_at` |
| visibility | `document.visibilitychange` | Tandai `visible` atau `hidden` |
| unload best-effort | `sendBeacon` bila tersedia | Menandai close tab secara best effort |

`sendBeacon` hanya optimisasi. Server tetap wajib punya timeout cleanup.

## 6. Multiple tab dan single device

Dalam satu session, user bisa membuka beberapa tab. Reverb dapat melihat beberapa koneksi, walaupun masih satu device/session.

Rekomendasi:

- track `connection_count` per `session_id_hash`;
- status tetap online selama minimal satu koneksi masih hidup atau heartbeat masih segar;
- saat satu tab ditutup, kurangi connection count;
- saat semua tab tertutup atau heartbeat stale, status menjadi offline.

Karena project memiliki single-device policy, dashboard harus berhati-hati dengan label "devices". Lebih akurat menampilkan:

```text
device/session aktif
tab/koneksi aktif
```

bukan menjanjikan multi-device aktif bersamaan.

## 7. Sumber data status

### Online realtime

Sumber:

- presence channel Reverb;
- heartbeat endpoint;
- cache atau tabel `user_presence_sessions`.

### Session aktif

Sumber:

- tabel `sessions`;
- `sessions.last_activity`;
- `config('session.lifetime')`.

### Device, browser, platform, dan IP

Sumber:

- request server terbaru;
- `sessions.ip_address`;
- `sessions.user_agent`;
- latest `login_events` yang relevan;
- tabel presence eksplisit bila sudah dibuat.

Jika parsing user-agent belum tersedia, jangan menambah dependency tanpa approval. Untuk MVP, tampilkan raw summary yang aman atau gunakan parsing yang sudah ada jika tersedia.

## 8. Tampilan dashboard admin

Field yang direkomendasikan:

- nama user;
- account type/status akun;
- posisi aktif atau acting context;
- instansi dan unit kerja;
- status realtime;
- last seen;
- last activity;
- session active sampai estimasi waktu tertentu;
- IP address;
- device type;
- browser;
- platform/OS;
- jumlah tab/koneksi;
- alasan offline terakhir: close, timeout, logout, revoked, unknown.

Field sensitif seperti IP, user-agent lengkap, session hash, dan fingerprint hanya tampil untuk role admin/security yang berwenang.

## 9. Audit event yang disarankan

Untuk monitoring biasa, tidak semua heartbeat perlu masuk audit permanen.

Event penting yang layak dicatat:

- login success;
- logout success;
- session revoked;
- session timeout;
- first connected setelah login;
- disconnected final;
- admin force logout;
- suspicious device/IP change.

Heartbeat rutin lebih cocok disimpan di cache atau tabel current state dengan update berkala.

## 10. Invariant

AI agent wajib mempertahankan invariant berikut:

1. `online` berarti hadir realtime, bukan sekadar punya session.
2. `session_active` tidak boleh disamakan dengan `online`.
3. Close tab tanpa logout harus menjadi offline setelah timeout.
4. Pindah tab boleh menjadi `away` atau `idle`, bukan otomatis offline.
5. Server timeout wajib ada karena browser event tidak selalu terkirim.
6. Detail IP/device hanya untuk admin/security yang terotorisasi.
7. Status harus tetap benar saat user membuka beberapa tab.
8. Fitur harus selaras dengan single-device session yang sudah ada.
