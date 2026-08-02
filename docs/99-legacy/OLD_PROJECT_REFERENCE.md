# Old SITANGKAS Project Reference

Dokumen ini mencatat lokasi project lama SITANGKAS yang harus dijadikan referensi oleh AI agent.

## Path referensi utama

```text
C:\Apache24\htdocs\sitangkas
```

Path ini adalah project lama SITANGKAS dan harus dipakai sebagai contoh utama ketika AI agent perlu memahami:

- struktur fitur lama;
- controller, model, route, view, dan service lama;
- alur bisnis yang sudah berjalan;
- nama field, istilah domain, dan kebiasaan UI;
- cara data lama dipakai sebelum dimigrasikan ke project baru;
- perilaku legacy yang perlu dipertahankan atau dipetakan ulang.

## Aturan penggunaan untuk AI agent

- Baca project lama hanya sebagai referensi perilaku dan struktur, bukan sebagai sumber yang langsung disalin mentah.
- Jangan menyalin credential, secret, isi `.env`, token, password, cookie, atau konfigurasi sensitif dari project lama.
- Jika perilaku project lama bertentangan dengan docs project baru, catat konfliknya dan ikuti keputusan desain project baru kecuali user meminta kompatibilitas legacy secara eksplisit.
- Saat memigrasikan fitur, cocokkan minimal: route lama, controller/action, model/table yang dipakai, view/form, validasi, dan alur database.
- Jangan menjalankan test suite di project lama atau project baru tanpa konfirmasi eksplisit dari user.
- Jangan mengubah file di path lama kecuali user secara eksplisit meminta perubahan pada project lama.

## Referensi lain yang pernah ditemukan

Path berikut pernah ditemukan dan boleh dipakai sebagai konteks tambahan jika diperlukan, tetapi bukan referensi utama:

```text
D:\failed project\sitangkas_old
D:\failed project\sitangkas_last_rebuild
D:\failed project\sitangkas_last_rebuild\dump-sitangkas-202607231138.sql
```

Gunakan `C:\Apache24\htdocs\sitangkas` terlebih dahulu untuk memahami project lama.
