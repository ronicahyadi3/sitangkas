# Realtime Feature Roadmap

## 1. Prinsip roadmap

Roadmap ini memecah Reverb menjadi fondasi kecil yang bisa diuji bertahap.

Snapshot implementasi terbaru dan rekomendasi next step ada di `CURRENT_REALTIME_IMPLEMENTATION.md`.

Urutan yang direkomendasikan:

1. siapkan broadcasting dan Echo;
2. buat online monitoring MVP;
3. perkuat state presence;
4. tambahkan realtime notification;
5. lanjutkan ke message helper realtime;
6. rapikan production monitoring dan scaling.

## 2. Phase 1: Reverb foundation

Tujuan:

- install Laravel Reverb;
- install Laravel Echo dan `pusher-js`;
- konfigurasi `.env`, `config/broadcasting.php`, dan `config/reverb.php`;
- pastikan `/broadcasting/auth` tersedia;
- buat `routes/channels.php` bila belum ada;
- inisialisasi Echo di frontend Vite.

Catatan:

- Gunakan `php artisan install:broadcasting --reverb` bila sesuai kondisi project.
- Jika instalasi otomatis terlalu banyak mengubah file, lakukan manual dengan tetap mengikuti docs Laravel versi project.
- Jangan commit secret Reverb.
- Jangan memakai `allowed_origins = *` selain untuk local development sementara.

Definition of done:

- Reverb server bisa berjalan lokal.
- Browser berhasil connect melalui Echo.
- Private/presence channel bisa authorize user login.
- Build frontend berhasil.

## 3. Phase 2: Online monitoring MVP

Tujuan:

- dashboard admin menampilkan siapa yang sedang online;
- status online tidak bergantung hanya pada tabel `sessions`;
- close tab tanpa logout berubah menjadi offline setelah timeout.

Komponen:

- endpoint initial state admin;
- presence channel `admin.online-users`;
- heartbeat endpoint;
- event broadcast `UserPresenceChanged`;
- policy/gate untuk akses dashboard monitoring.

Data awal bisa berasal dari:

- `sessions`;
- latest `login_events`;
- `users`;
- active position context.

Definition of done:

- user login terlihat online saat membuka aplikasi;
- user pindah tab menjadi `away` atau `idle` sesuai threshold;
- user menutup tab menjadi offline setelah heartbeat timeout;
- admin bisa melihat IP/device/browser sesuai authorization;
- user biasa tidak bisa mengakses data monitoring.

## 4. Phase 3: Presence state eksplisit

Tujuan:

- mengurangi ketergantungan pada `sessions` dan `login_events` untuk current realtime state;
- menyediakan query dashboard yang cepat dan jelas;
- menangani multiple tab dengan benar.

Rekomendasi tabel:

```text
user_presence_sessions
```

Field minimal:

```text
user_id
session_id_hash
active_user_position_id
connection_count
status
visibility_state
ip_address
user_agent
device_type
browser_name
platform_name
connected_at
last_seen_at
last_activity_at
heartbeat_expires_at
disconnected_at
disconnect_reason
```

Index yang disarankan:

```text
user_id
session_id_hash
status
last_seen_at
heartbeat_expires_at
```

Jika migration dibuat, baca `../06-migrations/FRESH_INSTALL_READINESS.md`.

Definition of done:

- query online dashboard tidak perlu memindai payload session besar;
- status stale bisa dibersihkan oleh scheduled command;
- session revoked menandai presence sebagai offline/revoked;
- data current state tidak dicampur dengan audit event permanen.

## 5. Phase 4: Realtime notification foundation

Tujuan:

- user menerima notifikasi tanpa refresh;
- notifikasi penting tetap tersimpan saat user offline.

Komponen:

- private channel per user;
- Laravel notification dengan channel `broadcast`;
- database notification untuk persistence bila notifikasi penting;
- frontend listener untuk badge/toast/inbox;
- queue untuk broadcast notification.

Aturan:

- gunakan `toBroadcast()` untuk payload eksplisit;
- gunakan `broadcastType()` bila UI butuh tipe stabil;
- gunakan `afterCommit()` bila notifikasi dibuat dari transaksi;
- jangan kirim data rahasia atau model penuh ke payload notification.

Definition of done:

- user menerima notifikasi realtime saat online;
- notifikasi tetap ada saat user offline dan muncul setelah login;
- jumlah unread konsisten antara database dan UI;
- channel private tidak bisa dibaca user lain.

## 6. Phase 5: Message helper realtime

Tujuan:

- menyediakan helper/helpdesk realtime di masa depan;
- mendukung chat, typing indicator, read receipt, dan helper availability.

Komponen:

- tabel conversation/thread;
- tabel messages;
- private channel per thread;
- presence channel per thread;
- event `MessageCreated`, `MessageRead`, `TypingStarted`, `TypingStopped`;
- endpoint upload attachment bila diperlukan.

Aturan:

- pesan disimpan ke database sebelum broadcast;
- typing indicator cukup cache/TTL pendek;
- read receipt harus idempotent;
- attachment tidak dikirim sebagai payload broadcast;
- authorization thread wajib di server.

Definition of done:

- hanya peserta yang bisa membaca thread;
- pesan tidak hilang saat reconnect;
- typing indicator hilang otomatis setelah TTL;
- read receipt tidak membuat duplicate state.

## 7. Phase 6: Production hardening

Tujuan:

- Reverb stabil di production;
- koneksi dan jumlah message bisa dimonitor;
- deployment tidak memutus fitur realtime secara kasar.

Komponen:

- process manager untuk Reverb;
- queue worker;
- `reverb:restart` saat deploy;
- Laravel Pulse Reverb recorders bila package tersedia;
- reverse proxy WSS/TLS;
- allowed origins yang ketat;
- Redis private jika horizontal scaling dipakai.

Checklist detail ada di `PRODUCTION_SECURITY_CHECKLIST.md`.

## 8. Urutan prioritas rekomendasi

Status saat ini:

- Phase 1 sudah dikerjakan: Reverb/Echo foundation.
- Phase 2 sudah dikerjakan: online monitoring MVP.
- Phase 3 sudah dikerjakan sebagian besar: database-backed presence, audit table, cleanup scheduler.
- Phase 4 sudah dikerjakan sebagai foundation: private channel, database notification table, broadcast notification class, frontend listener.
- Phase 5 baru foundation: `realtime_messages`, `RealtimeMessenger`, dan private message broadcast per user.
- Phase 6 belum production-ready penuh.

Prioritas implementasi berikutnya untuk SITANGKAS:

1. Browser verification end-to-end dengan Admin Super dan user biasa.
2. Tambahkan test terarah setelah user menyetujui penambahan test.
3. Integrasikan notifikasi bisnis pertama, disarankan event "dokumen masuk untuk diverifikasi/ditandatangani".
4. Tambahkan notification inbox dan unread badge.
5. Tambahkan dashboard audit presence event.
6. Perbaiki `connection_count` agar presisi untuk multi-tab via Reverb lifecycle tracking.
7. Lanjutkan full message helper/helpdesk jika proses bisnisnya sudah diputuskan.
8. Production hardening: process manager Reverb, queue worker, scheduler, WSS/TLS, allowed origins, deployment restart.

## 9. Catatan testing

Perubahan realtime perlu pengujian untuk:

- channel authorization;
- user biasa tidak bisa membaca dashboard admin;
- close tab/heartbeat timeout;
- multiple tab;
- session revoked oleh single-device policy;
- notification private channel;
- reconnect setelah jaringan putus.

Ikuti aturan project: jangan membuat, memodifikasi, atau menjalankan test suite/test command tanpa konfirmasi eksplisit dari user bila aturan docs project masih menyatakan demikian.
