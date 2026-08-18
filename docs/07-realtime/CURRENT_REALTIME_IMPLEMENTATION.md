# Current Realtime Implementation

Dokumen ini adalah snapshot kondisi realtime SITANGKAS saat ini dan panduan implementasi lanjutan untuk AI agent.

Tanggal snapshot: 2026-08-11.

## 1. Status implementasi saat ini

Realtime foundation sudah berada pada tahap:

```text
Reverb setup -> online monitoring MVP -> database-backed presence -> cleanup scheduler -> notification/message foundation
```

Yang sudah tersedia:

- Laravel Reverb sebagai broadcaster.
- Laravel Echo + `pusher-js` di frontend Vite.
- Private channel user: `App.Models.User.{id}`.
- Presence channel admin monitoring: `admin.online-users`.
- Dashboard admin online monitoring.
- Heartbeat browser setiap 30 detik.
- Leave signal best-effort saat tab ditutup.
- Storage current presence di `user_presence_sessions`.
- Audit/history presence di `user_presence_events`.
- Cleanup scheduler `realtime:presence:prune`.
- Database notification table `notifications`.
- Foundation message/helper table `realtime_messages`.
- `RealtimeMessenger` service untuk dipakai modul bisnis.
- Frontend listener private channel untuk toast notification/message.

Yang belum selesai dan tidak boleh diasumsikan sudah ada:

- Inbox notifikasi permanen di UI.
- Badge unread notification.
- Integrasi event bisnis nyata.
- Full helpdesk/chat thread.
- Typing indicator.
- Read receipt/delivered receipt.
- Reverb lifecycle listener untuk menghitung multi-tab connection count secara presisi.
- Browser end-to-end test terotomasi.
- Pest feature tests untuk realtime authorization dan heartbeat.

## 2. File penting

Backend Reverb/Echo:

- `config/broadcasting.php`
- `config/reverb.php`
- `routes/channels.php`
- `bootstrap/app.php`

Presence backend:

- `app/Services/Realtime/OnlinePresence.php`
- `app/Events/Realtime/UserPresenceChanged.php`
- `app/Http/Controllers/Realtime/OnlinePresenceController.php`
- `app/Http/Controllers/Admin/RealtimeOnlineUsersController.php`
- `app/Console/Commands/Realtime/PrunePresenceStateCommand.php`

Notification/message foundation:

- `app/Services/Realtime/RealtimeMessenger.php`
- `app/Notifications/Realtime/RealtimeNotification.php`
- `app/Events/Realtime/RealtimeMessageCreated.php`
- `app/Models/Realtime/RealtimeMessage.php`

Models/storage:

- `app/Models/Realtime/UserPresenceSession.php`
- `app/Models/Realtime/UserPresenceEvent.php`
- `database/migrations/2026_08_11_023146_create_user_presence_sessions_table.php`
- `database/migrations/2026_08_11_023147_create_realtime_messages_table.php`
- `database/migrations/2026_08_11_023148_create_user_presence_events_table.php`
- `database/migrations/2026_08_11_023150_create_notifications_table.php`

Frontend:

- `resources/js/echo.js`
- `resources/js/realtime-presence.js`
- `resources/js/app.js`
- `resources/views/layouts/app.blade.php`
- `resources/views/admin/realtime/online-users.blade.php`
- `resources/views/inc/sidebar.blade.php`

Scheduler:

- `routes/console.php`

## 3. Routes dan channel yang aktif

HTTP routes:

```text
GET  /admin/realtime/online-users
GET  /admin/realtime/online-users/state
POST /realtime/presence/heartbeat
POST /realtime/presence/leave
GET|POST|HEAD /broadcasting/auth
```

Broadcast channels:

```text
App.Models.User.{id}
admin.online-users
```

Catatan penting:

- `App.Models.User.{id}` adalah private channel default untuk Laravel broadcast notifications dan realtime message per user.
- `admin.online-users` dipakai dengan `Echo.join('admin.online-users')`; karena join adalah presence subscription, transport wire name-nya menjadi presence channel.
- Authorization channel wajib tetap di `routes/channels.php`.
- Presence channel admin harus tetap mengembalikan array minimal, bukan `true`.

## 4. Storage presence

### `user_presence_sessions`

Tabel ini adalah current state untuk dashboard online monitoring.

Field penting:

- `presence_id`
- `user_id`
- `active_user_position_id`
- `real_user_position_id`
- `session_id_hash`
- `connection_count`
- `user_name`
- `status`
- `visibility_state`
- `activity_state`
- `ip_address`
- `proxy_ip_address`
- `user_agent`
- `device_type`
- `device_name`
- `browser_name`
- `browser_version`
- `platform_name`
- `platform_version`
- `position_snapshot`
- `connected_at`
- `last_seen_at`
- `last_activity_at`
- `heartbeat_expires_at`
- `disconnected_at`
- `disconnect_reason`

Status yang dipakai:

```text
active
idle
away
offline
```

Catatan:

- `session_id_hash` adalah hash/HMAC dari session ID, bukan session ID mentah.
- Dashboard tidak boleh menampilkan `session_id_hash`.
- `connection_count` saat ini masih minimal heartbeat-based: `1` saat online dan `0` saat offline. Jangan menganggap ini sudah presisi untuk jumlah tab sampai ada Reverb lifecycle tracking.

### `user_presence_events`

Tabel ini adalah audit/history event penting presence.

Event penting:

```text
connected
status_changed
disconnected
heartbeat_timeout
pruned
```

Heartbeat rutin tidak dicatat sebagai audit event permanen agar tabel tidak cepat membesar.

## 5. Scheduler cleanup

Command:

```bash
php artisan realtime:presence:prune
```

Schedule:

```text
everyMinute()
withoutOverlapping(5)
onOneServer()
```

Fungsi cleanup:

- menandai presence stale sebagai `offline`;
- mencatat event `heartbeat_timeout`;
- menghapus current session offline yang sudah melewati retention pendek;
- menghapus audit event yang melewati `retention_until`.

Production wajib menjalankan Laravel scheduler:

```bash
php artisan schedule:run
```

atau mekanisme scheduler platform yang setara.

## 6. Frontend behavior

Frontend realtime di `resources/js/realtime-presence.js` melakukan:

- heartbeat setiap 30 detik;
- kirim `visibility_state`;
- hitung `idle_seconds`;
- kirim `X-CSRF-TOKEN`;
- kirim `X-Socket-ID` untuk mendukung `toOthers()`;
- kirim leave signal via `navigator.sendBeacon` saat tab ditutup;
- subscribe dashboard admin ke `admin.online-users`;
- subscribe user login ke private channel `App.Models.User.{id}`;
- memunculkan toast jika menerima broadcast notification atau realtime message;
- dispatch browser events:

```text
sitangkas:realtime-notification
sitangkas:realtime-message
```

Komponen UI masa depan dapat mendengar event tersebut untuk update badge/inbox tanpa mengganti fondasi Reverb.

## 7. Realtime notification foundation

Class utama:

```php
App\Notifications\Realtime\RealtimeNotification
App\Services\Realtime\RealtimeMessenger
```

Notification memakai channel:

```text
database
broadcast
```

Contoh pemakaian untuk event bisnis:

```php
app(\App\Services\Realtime\RealtimeMessenger::class)->notify(
    recipient: $recipientUser,
    title: 'Dokumen masuk',
    body: 'Ada dokumen yang menunggu verifikasi.',
    severity: 'info',
    actionUrl: route('dashboard'),
    data: [
        'module' => 'payment',
        'document_id' => $documentId,
    ],
    actor: auth()->user(),
);
```

Aturan:

- notifikasi penting harus persistent via database;
- broadcast payload harus minimal;
- jangan kirim model penuh;
- jangan kirim data rahasia;
- gunakan `afterCommit()` saat dipicu dari transaksi database.

## 8. Message helper foundation

Class utama:

```php
App\Models\Realtime\RealtimeMessage
App\Events\Realtime\RealtimeMessageCreated
App\Services\Realtime\RealtimeMessenger
```

Contoh pemakaian:

```php
app(\App\Services\Realtime\RealtimeMessenger::class)->sendMessage(
    recipient: $recipientUser,
    title: 'Butuh tindak lanjut',
    body: 'Silakan cek dokumen berikut.',
    severity: 'warning',
    actionUrl: route('dashboard'),
    data: [
        'module' => 'helper',
        'reference_id' => $referenceId,
    ],
    sender: auth()->user(),
);
```

Saat ini message helper masih foundation, bukan full chat. Full chat/helpdesk nanti harus punya:

- conversation/thread table;
- participant table;
- message table per thread;
- private channel per thread;
- presence channel per thread;
- typing indicator TTL;
- read receipt idempotent;
- attachment upload endpoint terotorisasi.

## 9. Rekomendasi implementasi proper berikutnya

### Prioritas 1: browser verification

Lakukan verifikasi manual dengan user nyata:

1. Login Admin Super dan buka dashboard Online Monitoring.
2. Login user lain di browser/incognito lain.
3. Pastikan user muncul `active`.
4. Pindah tab dan pastikan status berubah `away`.
5. Diamkan sampai idle threshold dan pastikan status `idle`.
6. Tutup tab tanpa logout dan pastikan status menjadi `offline` setelah timeout.
7. Pastikan user biasa tidak bisa membuka dashboard admin.
8. Pastikan private notification hanya diterima user penerima.

### Prioritas 2: test terarah

Jika user menyetujui penambahan test, buat Pest feature tests untuk:

- admin dapat membuka dashboard monitoring;
- user biasa ditolak dari dashboard monitoring;
- heartbeat membuat atau update `user_presence_sessions`;
- leave menandai session offline;
- cleanup command menandai stale session offline;
- channel `App.Models.User.{id}` menolak user lain;
- channel `admin.online-users` hanya menerima Admin Super terverifikasi MFA.

### Prioritas 3: integrasi notifikasi bisnis pertama

Pilih satu event bisnis bernilai tinggi, paling disarankan:

```text
dokumen masuk untuk diverifikasi atau ditandatangani
```

Implementasi harus:

- terjadi setelah perubahan status dokumen berhasil disimpan;
- menentukan penerima dari relasi bisnis, bukan hardcode user ID;
- memakai `RealtimeMessenger::notify()` atau `sendMessage()`;
- menyimpan notifikasi supaya user offline tidak kehilangan informasi;
- membawa `action_url` ke halaman detail dokumen;
- memakai `data` minimal seperti `module`, `document_type`, `document_id`, `status`.

### Prioritas 4: notification inbox

Tambahkan UI:

- badge unread di navbar/sidebar;
- dropdown notifikasi terbaru;
- halaman inbox notifikasi;
- endpoint mark as read;
- fallback load unread dari tabel `notifications`.

### Prioritas 5: audit dashboard

Tambahkan halaman/detail audit presence:

- filter user;
- filter status/event type;
- filter IP/device;
- rentang waktu;
- link dari dashboard online monitoring ke audit detail user.

### Prioritas 6: connection count presisi

Jika jumlah tab/koneksi harus akurat:

- gunakan Reverb internal lifecycle events atau client join/leave tracking yang idempotent;
- bedakan `session_id_hash`, `presence_id`, dan connection ID;
- update `connection_count` per connection, bukan hanya per heartbeat;
- tetap gunakan heartbeat timeout sebagai fallback.

### Prioritas 7: production hardening

Pastikan:

- Reverb berjalan via Supervisor/systemd/process manager;
- queue worker berjalan untuk broadcast notification;
- `php artisan schedule:run` aktif;
- `php artisan reverb:restart` masuk deployment;
- `REVERB_ALLOWED_ORIGINS` ketat;
- HTTPS/WSS aktif di production;
- reverse proxy meneruskan `/app` dan `/apps` ke Reverb.

## 10. Known local notes

Di workspace lokal ini pernah ditemukan port `8080` dipakai `httpd.exe`. Karena itu `.env` lokal dapat memakai:

```text
REVERB_PORT=8081
REVERB_SERVER_PORT=8081
VITE_REVERB_PORT="${REVERB_PORT}"
```

Ini catatan local development, bukan aturan production. Production boleh tetap memakai public port `443` dengan reverse proxy ke server Reverb internal.

## 11. Commands verifikasi cepat

Gunakan command berikut setelah mengubah realtime:

```bash
php artisan channel:list
php artisan route:list --path=broadcasting
php artisan route:list --name=realtime --except-vendor
php artisan schedule:list
php artisan realtime:presence:prune
php artisan migrate --pretend --no-interaction
npm run build
vendor/bin/pint --dirty --format agent
```

Untuk smoke test broadcast server-side:

```bash
php artisan tinker --execute "broadcast(new App\Events\Realtime\UserPresenceChanged(['presence_id' => 'audit', 'status' => 'active'])); echo 'broadcast ok';"
```

Jangan jalankan Pest/test suite tanpa konfirmasi eksplisit jika instruksi project masih mensyaratkan approval.

## 12. Invariants untuk AI agent

AI agent wajib mempertahankan:

1. `online` bukan sinonim dari session login aktif.
2. Close tab tanpa logout harus menjadi offline setelah timeout.
3. Pindah tab menjadi `away` atau `idle`, bukan otomatis offline.
4. Detail IP/device hanya untuk admin/security yang terotorisasi.
5. Payload broadcast harus minimal dan eksplisit.
6. Notifikasi penting harus persistent.
7. Authorization channel wajib di server.
8. Jangan menyimpan session ID mentah, cookie, token, Authorization header, atau secret.
9. Jangan broadcast model Eloquent penuh.
10. Jangan mengabaikan `EnsureSingleDeviceSession`, MFA, dan active position context.
