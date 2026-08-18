# Reverb Production Security Checklist

## 1. Dependency dan versi

- Verifikasi versi Laravel dan Reverb sebelum deploy.
- Jangan pin `laravel/reverb` ke versi lama.
- Jika horizontal scaling Redis dipakai, gunakan `laravel/reverb >= 1.7.0`.
- Jalankan dependency audit sesuai proses project.
- Verifikasi `laravel-echo` memenuhi versi minimal yang dibutuhkan broadcaster Reverb.

Catatan keamanan:

Pada 2026 terdapat advisory untuk Reverb versi lama ketika horizontal scaling Redis aktif. Karena itu, jangan mengaktifkan scaling dengan versi Reverb lama atau Redis yang terbuka.

## 2. Environment dan secret

- Simpan `REVERB_APP_ID`, `REVERB_APP_KEY`, dan `REVERB_APP_SECRET` di environment.
- Jangan commit `.env` atau secret ke repository.
- Bedakan `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` dari `REVERB_HOST`/`REVERB_PORT`.
- `REVERB_SERVER_*` adalah tempat proses Reverb listen.
- `REVERB_HOST` dan `REVERB_PORT` adalah alamat yang dipakai aplikasi/client untuk broadcast/connect.
- Gunakan secret berbeda per environment.

## 3. Allowed origins

- Production tidak boleh memakai wildcard `*` kecuali ada keputusan security eksplisit.
- Isi allowed origins hanya dengan domain aplikasi yang sah.
- Pisahkan origin local, staging, dan production.
- Review ulang jika domain frontend berubah.

## 4. TLS dan reverse proxy

- Production harus memakai WSS/TLS.
- TLS biasanya diterminasi di Nginx/Caddy/load balancer.
- Pastikan reverse proxy meneruskan header upgrade WebSocket.
- Pastikan path Reverb `/app` dan `/apps` dapat diproxy.
- Jangan mengekspos port internal Reverb langsung ke publik bila memakai reverse proxy.

## 5. Channel authorization

- Semua private dan presence channel wajib punya authorization callback.
- Dashboard monitoring user online harus admin/security only.
- Detail IP/device/session tidak boleh dikirim ke user biasa.
- Jangan mengandalkan frontend untuk menyembunyikan channel.
- Gunakan policy/gate/service authorization di server.
- Pastikan `/broadcasting/auth` memakai middleware auth yang sesuai.

## 6. Payload security

- Gunakan `broadcastWith()` untuk event broadcast.
- Jangan broadcast model Eloquent penuh.
- Jangan broadcast password, token, cookie, session ID mentah, Authorization header, passphrase TTE, atau secret lain.
- Jangan broadcast raw request payload.
- Jangan broadcast IP/user-agent lengkap kecuali channel admin/security benar-benar terproteksi.
- Pertimbangkan masking IP untuk tampilan non-security.

## 7. Heartbeat dan rate limit

- Heartbeat endpoint harus memakai auth.
- Tambahkan throttle yang wajar.
- Validasi payload heartbeat, visibility, dan activity state.
- Jangan percaya user ID dari JavaScript; ambil dari `auth()->id()`.
- Jangan percaya active position dari request tanpa validasi session/server.

## 8. Process management

- Jalankan Reverb dengan Supervisor, systemd, Docker process manager, atau mekanisme setara.
- Jalankan queue worker terpisah.
- Saat deploy, gunakan `php artisan reverb:restart`.
- Pastikan process manager menghidupkan Reverb kembali setelah restart.
- Atur log agar bisa dibaca saat troubleshooting.

## 9. Queue dan transaksi

- Event broadcast queued membutuhkan queue worker aktif.
- Untuk event yang bergantung pada data baru, gunakan after-commit pattern.
- Broadcast notification harus queued.
- Pisahkan queue broadcast/notifikasi bila traffic besar.
- Jangan memakai queue sync di production tanpa alasan eksplisit.

## 10. Monitoring

- Gunakan Laravel Pulse Reverb recorders bila package tersedia.
- Pantau jumlah koneksi.
- Pantau jumlah message.
- Pantau reconnect storm.
- Pantau queue delay.
- Pantau error `/broadcasting/auth`.
- Pantau memory dan open file limit.

## 11. Resource limit

- WebSocket adalah koneksi long-running.
- Sesuaikan open file limit di OS.
- Sesuaikan limit worker reverse proxy.
- Pertimbangkan `ext-uv` untuk koneksi tinggi.
- Uji kapasitas sesuai jumlah user internal yang realistis.

## 12. Redis scaling

Aktifkan horizontal scaling hanya jika dibutuhkan.

Jika `REVERB_SCALING_ENABLED=true`:

- gunakan Reverb versi aman;
- gunakan Redis private network;
- wajib password Redis;
- jangan expose Redis ke internet;
- batasi security group/firewall;
- gunakan Redis terpusat yang dapat dijangkau semua node Reverb;
- monitor Redis pub/sub dan latency.

Untuk satu node Reverb, scaling boleh tetap `false`.

## 13. Incident response

Siapkan prosedur:

- restart Reverb tanpa deploy penuh;
- revoke semua session bila terjadi kebocoran;
- rotate Reverb app secret;
- matikan realtime sementara dengan fallback HTTP bila diperlukan;
- membaca log Reverb dan queue;
- membedakan error auth, proxy, TLS, queue, dan channel mismatch.

## 14. Definition of done production

Reverb siap production bila:

- WSS berhasil dari browser production;
- allowed origins ketat;
- private/presence channel terotorisasi;
- admin dashboard tidak dapat diakses user biasa;
- queue worker aktif;
- Reverb diawasi process manager;
- deploy menjalankan restart graceful;
- metric koneksi/message tersedia;
- Redis scaling aman bila dipakai;
- tidak ada secret atau data sensitif bocor dalam payload broadcast.
