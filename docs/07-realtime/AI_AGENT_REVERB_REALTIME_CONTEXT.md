# AI Agent Context: Reverb Realtime SITANGKAS

## 1. Tujuan

Dokumen ini adalah konteks wajib bagi AI agent yang mengerjakan fitur realtime di SITANGKAS.

Fitur yang dicakup:

- monitoring siapa saja yang sedang online;
- status aktivitas user secara realtime;
- informasi device, browser, platform, IP, dan session aktif;
- realtime notification;
- message helper atau helpdesk realtime di masa depan.

## 2. Snapshot project saat dokumen dibuat

Snapshot historis awal dokumen ini sudah berubah. Untuk kondisi implementasi terbaru, AI agent wajib membaca:

```text
CURRENT_REALTIME_IMPLEMENTATION.md
```

Kondisi terbaru per 2026-08-11:

- Laravel berada di versi 13.x.
- PHP berada di versi 8.4.
- Database menggunakan MySQL.
- Frontend memakai Blade dan Vite.
- `laravel/reverb` sudah terpasang.
- `laravel-echo` dan `pusher-js` sudah terpasang.
- `resources/js/app.js` memuat Echo dan realtime presence client.
- Tabel `sessions` sudah tersedia dengan `user_id`, `ip_address`, `user_agent`, dan `last_activity`.
- Tabel `login_events` sudah tersedia dan berisi banyak field audit autentikasi, termasuk IP, user-agent, device, browser, platform, session hash, dan metadata.
- Tabel `user_presence_sessions`, `user_presence_events`, `realtime_messages`, dan `notifications` sudah tersedia.
- Dashboard admin online monitoring sudah tersedia.
- Cleanup scheduler `realtime:presence:prune` sudah tersedia.
- Private notification/message foundation sudah tersedia.
- Middleware `EnsureSingleDeviceSession` sudah ada dan harus dihormati oleh fitur realtime.

Agent wajib memverifikasi ulang snapshot ini sebelum implementasi karena dependency atau struktur kode bisa berubah.

## 3. Prinsip arsitektur

Reverb harus diperlakukan sebagai transport realtime.

Sumber kebenaran tetap berada di server:

- database untuk state penting dan audit;
- cache untuk state volatile berumur pendek;
- session database untuk session login;
- `login_events` untuk histori autentikasi.

Jangan menjadikan daftar subscriber Reverb sebagai satu-satunya sumber kebenaran. Gunakan Reverb untuk mengirim perubahan ke UI, bukan untuk menyimpan state bisnis.

Pola yang direkomendasikan:

```text
Browser
  -> login/session Laravel
  -> Echo connect ke Reverb
  -> join presence/private channel
  -> kirim heartbeat/visibility activity ke Laravel

Laravel App
  -> validasi auth/channel
  -> simpan/update presence state
  -> broadcast perubahan status
  -> sediakan endpoint initial state untuk dashboard admin

Admin Dashboard
  -> load initial state via HTTP
  -> subscribe ke channel realtime
  -> merge event realtime ke tabel monitoring
```

## 4. Perbedaan online dan session aktif

AI agent wajib membedakan:

| Istilah | Makna |
|---|---|
| Online realtime | Browser/tab user masih terhubung ke Reverb atau heartbeat masih segar |
| Idle | User masih terhubung, tetapi tidak ada aktivitas input dalam batas waktu tertentu |
| Away/background | Tab tersembunyi atau browser pindah ke background |
| Offline | Koneksi terputus atau heartbeat melewati timeout |
| Session aktif | Session Laravel masih valid di tabel `sessions`, tetapi user belum tentu sedang membuka aplikasi |

User yang menutup tab tanpa logout tidak boleh terus dianggap online. Mereka hanya mempunyai session aktif sampai session expired atau dicabut.

Detail status ada di `ONLINE_PRESENCE_DECISIONS.md`.

## 5. Channel yang direkomendasikan

### `admin.online-users`

Dipakai oleh dashboard admin/security untuk mengetahui perubahan user online.

Catatan implementasi:

- frontend memakai `Echo.join('admin.online-users')`;
- Laravel event memakai `new PresenceChannel('admin.online-users')`;
- wire/protocol channel akan menjadi presence channel.

Authorization:

- hanya user yang sudah login;
- harus lolos single-device session;
- harus lolos MFA bila dashboard internal mensyaratkan MFA;
- harus memiliki posisi/role yang boleh melihat monitoring user;
- harus memakai policy/gate, bukan hanya pengecekan frontend.

Payload presence harus minimal, misalnya:

```text
id
nama
status
```

Jangan mengirim IP, raw user-agent, session hash lengkap, token, atau detail sensitif lain melalui payload presence. Detail tersebut diambil dari endpoint admin yang terproteksi.

### `App.Models.User.{id}`

Dipakai untuk notifikasi realtime per user.

Authorization:

```text
auth user id == {userId}
```

atau role khusus admin bila ada kebutuhan operator melihat notifikasi user tertentu. Default harus private untuk user pemilik.

Channel ini mengikuti konvensi Laravel broadcast notification default untuk model `App\Models\User`.

### `presence.helpdesk.{threadId}`

Dipakai di masa depan untuk helper/message realtime.

Authorization:

- user adalah peserta thread;
- helper/admin punya hak menangani thread;
- jangan masukkan isi pesan ke presence payload.

### `private.helpdesk.{threadId}`

Dipakai untuk event pesan baru, update status pesan, read receipt, atau typing indicator.

Pesan tetap harus disimpan di database terlebih dahulu. Broadcast hanya mengirim event bahwa state berubah.

## 6. State presence yang disarankan

SITANGKAS sekarang sudah memiliki tabel eksplisit `user_presence_sessions` untuk current state dan `user_presence_events` untuk audit/history presence.

Field yang disarankan:

```text
id
presence_id
user_id
session_id_hash
active_user_position_id
real_user_position_id
connection_count
user_name
status
visibility_state
activity_state
ip_address
proxy_ip_address
user_agent
device_type
device_name
browser_name
browser_version
platform_name
platform_version
position_snapshot
connected_at
last_seen_at
last_activity_at
heartbeat_expires_at
disconnected_at
disconnect_reason
created_at
updated_at
```

Catatan:

- `session_id_hash` wajib hash/HMAC, bukan session ID mentah.
- `connection_count` saat ini masih heartbeat-based minimal `1` atau `0`; jangan menganggap jumlah tab sudah presisi sebelum ada Reverb lifecycle tracking.
- `ip_address` dan `user_agent` boleh disimpan untuk admin/security, tetapi jangan ditampilkan ke user biasa.
- Data yang sering difilter harus menjadi kolom terstruktur, bukan disimpan di `metadata`.
- Jika `metadata` dipakai, jangan simpan secret atau payload request mentah.

## 7. Sumber data dashboard monitoring

Initial state dashboard admin sebaiknya berasal dari endpoint HTTP:

```text
GET /admin/realtime/online-users
GET /admin/realtime/online-users/state
POST /realtime/presence/heartbeat
POST /realtime/presence/leave
```

Endpoint tersebut dapat menggabungkan:

- `user_presence_sessions` atau cache presence;
- `sessions` untuk session aktif;
- latest `login_events` untuk waktu login, device, browser, IP, dan audit context;
- `users`;
- `CurrentUserContext` atau snapshot position bila tersedia.

Event realtime setelah initial load dikirim melalui Reverb, misalnya:

```text
UserPresenceChanged
RealtimeMessageCreated
RealtimeNotification
```

Setiap event broadcast harus memakai `broadcastWith()` agar payload eksplisit dan tidak membocorkan model Eloquent penuh.

## 8. Interaksi dengan single-device session

Project sudah memiliki `EnsureSingleDeviceSession`.

Implikasi untuk realtime:

- login baru dapat mencabut session lama;
- presence session lama harus ditandai `offline` atau `revoked`;
- dashboard admin harus membedakan `offline` karena close tab, timeout, logout, dan revoked;
- beberapa tab dalam satu browser bisa menghasilkan beberapa koneksi WebSocket walaupun masih satu session;
- monitoring "device apa saja" harus selaras dengan aturan single-device, sehingga istilah yang lebih akurat adalah "session/device aktif terakhir" dan "tab/koneksi aktif".

Jika single-device policy tetap aktif, jangan membuat fitur yang mengasumsikan satu user dapat memiliki banyak device login aktif bersamaan tanpa keputusan arsitektur baru.

## 9. Realtime notification

Untuk notifikasi:

- gunakan Laravel notification channel `broadcast`;
- simpan notifikasi penting ke database agar tidak hilang saat user offline;
- broadcast ke private channel user;
- gunakan queue untuk notifikasi;
- gunakan `afterCommit()` bila notifikasi dipicu dari transaksi database;
- definisikan payload dengan `toBroadcast()` dan tipe dengan `broadcastType()` bila diperlukan.

Nama channel yang dipakai saat ini mengikuti default Laravel:

```text
App.Models.User.{id}
```

Jangan mengganti channel ini tanpa memperbarui `routes/channels.php`, `resources/js/realtime-presence.js`, dan dokumentasi ini.

## 10. Message helper realtime

Untuk message helper di masa depan:

- database tetap menjadi sumber kebenaran pesan;
- WebSocket hanya mengirim event pesan baru atau status berubah;
- presence channel hanya untuk daftar peserta, typing, dan helper availability;
- private channel per thread untuk isi event;
- read receipt dan delivered receipt harus idempotent;
- typing indicator tidak perlu disimpan permanen, cukup cache/TTL pendek;
- attachment harus melalui controller upload yang terotorisasi, bukan payload broadcast.

## 11. Production dan observability

Sebelum production:

- batasi `allowed_origins`;
- gunakan WSS/TLS;
- jalankan Reverb dengan Supervisor/systemd atau process manager setara;
- jalankan queue worker;
- gunakan `reverb:restart` saat deploy;
- aktifkan Laravel Pulse untuk metrik koneksi dan message bila package tersedia;
- jika horizontal scaling Redis dipakai, gunakan versi Reverb yang aman dan Redis private dengan password.

Checklist detail ada di `PRODUCTION_SECURITY_CHECKLIST.md`.

## 12. Larangan desain

AI agent dilarang melakukan hal berikut tanpa keputusan arsitektur eksplisit:

1. Menganggap row di `sessions` otomatis berarti user online.
2. Menganggap close tab sama dengan logout.
3. Mengirim IP, user-agent mentah, session hash lengkap, atau detail device sensitif ke channel yang bisa diakses user biasa.
4. Membuka presence channel monitoring untuk semua user.
5. Menyimpan session ID mentah, cookie, token, atau Authorization header.
6. Menyerialisasi model Eloquent penuh sebagai payload broadcast.
7. Mengabaikan `EnsureSingleDeviceSession`.
8. Membuat fitur multi-device aktif tanpa meninjau kebijakan single-device.
9. Mengandalkan JavaScript saja untuk authorization dashboard.
10. Membuat notifikasi realtime tanpa fallback database bila notifikasi bersifat penting.
