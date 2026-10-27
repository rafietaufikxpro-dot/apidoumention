# Bitcoin Tracker API: Jalankan, Dokumentasikan, dan Publish di Postman

## 1. Menjalankan API secara lokal

Persyaratan: **PHP 8.4.1 atau lebih baru** (dependensi di `composer.lock` meminta ini, walau `composer.json` menulis `^8.3`), Composer, dan database (MySQL sesuai `.env.example`, atau SQLite).

1. `composer install`
2. `cp .env.example .env` lalu `php artisan key:generate` (lewati bila `.env` sudah ada).
3. Isi di `.env`:
   - `TRACKER_API_KEY` : kunci bebas buatan Anda (dikirim klien lewat header `X-API-Key`).
   - `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM` : kredensial Twilio. Untuk uji coba pakai kredensial *test* Twilio.
   - `BITCOIN_PRICE_URL` : opsional, default CoinGecko.
4. `php artisan migrate`
5. `php artisan serve` (API di `http://localhost:8000`)
6. Jalankan tes: `php artisan test`

Catatan: API harga CoinDesk (BPI) yang dipakai di video sudah tidak tersedia, jadi proyek ini memakai CoinGecko.

## 2. Endpoint

Semua endpoint berawalan `/api/v1`, wajib header `X-API-Key`, dibatasi 60 permintaan per menit.

| Method | Path | Fungsi |
|---|---|---|
| GET | `/rate` | Harga Bitcoin saat ini (USD) |
| GET | `/alerts` | Daftar alert |
| POST | `/alerts` | Buat alert (`phone_number` format E.164, `target_rate`) |
| DELETE | `/alerts/{id}` | Hapus alert |
| POST | `/alerts/check` | Cek harga dan kirim SMS untuk alert yang targetnya di atas harga sekarang (jeda 1 jam per alert) |

Kode status: 200/201/204 berhasil, 401 API key salah, 422 validasi gagal, 502 penyedia harga bermasalah, 503 `TRACKER_API_KEY` belum diatur di server.

## 3. Impor ke Postman

1. Buka Postman, pilih workspace, klik **Import**.
2. Pilih **File** dan unggah dua file di folder `postman/`:
   - `Bitcoin Tracker API.postman_collection.json`
   - `Bitcoin Tracker.postman_environment.json`
3. Pilih environment **Bitcoin Tracker** (pojok kanan atas) lalu ubah `baseUrl` dan `apiKey` sesuai server Anda.
4. Jalankan **Create Alert**, lalu **Get Bitcoin Rate**. `alertId` terisi otomatis dan dipakai oleh **Delete Alert**.

Kredensial Twilio tidak ada di Postman; semuanya tetap di `.env` server.

## 4. Dokumentasi di Postman

Koleksi sudah berisi deskripsi collection (pengantar, disclaimer, Quickstart, tabel variabel), deskripsi tiap request, dan contoh respons (sukses dan gagal). Yang perlu Anda cek:

1. Buka collection, panel **Documentation**: baca dan sesuaikan teksnya.
2. Buka tiap request, tab **Examples**: pastikan contoh respons sesuai.
3. Ubah `baseUrl` di environment yang akan dipublikasikan ke URL server publik Anda (bukan `localhost`), kalau pembaca perlu menjalankannya.
4. Opsional: jalankan **Get Bitcoin Rate** dan **Create Alert** lalu **Save as example** untuk memperbarui contoh dengan respons asli.

## 5. Publish

1. Klik menu tiga titik pada collection, pilih **Publish Docs** (atau tombol **Publish**).
2. **Content**: pilih versi **Current** dan environment **Bitcoin Tracker**.
3. **URL**: pilih custom domain bila ada.
4. **Styling**: pilih layout dan warna, gunakan **Preview Documentation**.
5. **Collection sharing**: pilih public workspace bila ingin tampil di Public API Network.
6. Klik **Publish Collection**, lalu salin URL publik.
7. Buka URL itu tanpa login dan periksa navigasi, bahasa contoh kode, dan tombol **Run in Postman**.

## 6. Daftar periksa keamanan sebelum publish

- [ ] `apiKey` di environment yang dipublikasikan hanya berisi placeholder (`your-api-key`), bukan kunci asli.
- [ ] Tidak ada SID/token Twilio, nomor telepon asli, atau kunci lain di collection, environment, atau contoh respons.
- [ ] Nilai *current value* variabel tidak berisi rahasia (nilai itu ikut terpublikasi).
- [ ] Endpoint `/alerts/check` mengirim SMS asli dan berbiaya; kunci API server hanya dibagikan ke orang tepercaya.
- [ ] Pertahankan disclaimer finansial di deskripsi collection.
