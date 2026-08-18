# Realtime Docs

Cluster ini menjelaskan rekomendasi penggunaan Laravel Reverb untuk fitur realtime SITANGKAS, terutama online monitoring, realtime notification, dan message helper.

## Baca berdasarkan kebutuhan

| Kebutuhan | File |
|---|---|
| Kondisi implementasi saat ini dan rekomendasi next step | `CURRENT_REALTIME_IMPLEMENTATION.md` |
| Gambaran arsitektur Reverb untuk AI agent | `AI_AGENT_REVERB_REALTIME_CONTEXT.md` |
| Definisi online, idle, away, offline, dan session aktif | `ONLINE_PRESENCE_DECISIONS.md` |
| Urutan implementasi fitur realtime | `REALTIME_FEATURE_ROADMAP.md` |
| Checklist production, keamanan, dan operasional Reverb | `PRODUCTION_SECURITY_CHECKLIST.md` |

## Aturan inti

- Reverb adalah transport realtime, bukan sumber kebenaran data.
- Status `online` tidak sama dengan session login yang masih aktif.
- User yang menutup tab tanpa logout harus menjadi `offline` setelah koneksi putus atau heartbeat timeout, walaupun session login masih valid.
- User yang pindah tab masih bisa dianggap terhubung, tetapi status aktivitasnya dapat berubah menjadi `idle` atau `away`.
- Data IP, user-agent, device, browser, platform, session hash, dan detail keamanan hanya boleh tampil di dashboard admin/security yang terotorisasi.
- Payload presence channel harus minimal. Ambil detail sensitif dari endpoint server yang diproteksi, bukan dari data presence publik.
- Fitur realtime harus tetap bekerja dengan aturan `EnsureSingleDeviceSession` yang sudah ada.
- Notifikasi realtime harus tetap disimpan ke database jika notifikasi tidak boleh hilang saat user offline.
- Jangan menyimpan session ID mentah, cookie, token, password, Authorization header, atau secret lain di database, event, payload broadcast, log, atau metadata.
- Jika perubahan menyentuh migration, schema, atau fresh install, baca `../06-migrations/FRESH_INSTALL_READINESS.md`.

## Paket dan teknologi target

Saat dokumen ini dibuat, project menggunakan Laravel 13, PHP 8.4, MySQL, Blade, Vite, dan Tailwind v4. Verifikasi ulang `composer.json`, `package.json`, dan `php artisan about` sebelum implementasi karena paket Reverb/Echo dapat berubah setelah dokumen ini dibuat.

Target implementasi:

- Laravel Reverb sebagai WebSocket server.
- Laravel Echo sebagai client subscription layer.
- Presence channel untuk awareness siapa yang sedang tersambung.
- Private channel untuk notifikasi per user.
- HTTP endpoint yang terproteksi untuk initial state dan detail monitoring.

## Status implementasi

Snapshot terbaru ada di `CURRENT_REALTIME_IMPLEMENTATION.md`.

Ringkasnya, project sekarang sudah memiliki Reverb/Echo foundation, dashboard online monitoring, heartbeat, database-backed presence, audit presence event, cleanup scheduler, database notification, dan foundation message helper. Integrasi notifikasi bisnis nyata dan inbox notifikasi masih menjadi tahap berikutnya.

## Pakai cluster lain bila

- Menyentuh login, logout, session invalidation, atau `login_events`: baca `../01-authentication/README.md`.
- Menyentuh active position, acting context Admin Super, atau konteks jabatan/unit kerja: baca `../03-user-positions/README.md`.
- Menyentuh authorization lintas domain: baca `../05-relationships/README.md`.
- Menyentuh migration atau indeks untuk tabel presence baru: baca `../06-migrations/README.md`.
