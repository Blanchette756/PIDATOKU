# PIDATOKU 🇮🇩

Platform Pemantauan Sentimen Pasar Finansial (IHSG & Kurs IDR/USD) terhadap Pidato Kenegaraan.
Proyek ini dikembangkan sebagai luaran Praktikum Pemrograman Web (Kurikulum OBE) - Program Studi Sarjana Teknik Komputer, Universitas Borneo Tarakan.

## 🪪 Identitas Pengembang
* **Nama:** Rizky Rahmad Dani
* **Program Studi:** Teknik Komputer
* **NPM:** 2440304001
* **Univ:** Universitas Borneo Tarakan

## ✨ Pemenuhan Syarat Modul 2
Proyek ini telah menerapkan standar pembuatan halaman web dasar sesuai modul, meliputi:

**1. Struktur HTML5 Semantik**
* `<header>` dan `<nav>` untuk bagian atas dan navigasi utama.
* `<main>` sebagai pembungkus konten utama.
* `<section>` (Terdapat 3 section: Live Monitor, Arsip Pidato, dan Form Kontak).
* `<article>` digunakan di dalam arsip pidato dan kartu saluran monitor.
* `<figure>` untuk media siaran langsung dan bingkai grafik.
* `<aside>` untuk ticker bar dan ringkasan pesan error.
* `<form>` untuk formulir masukan pengguna.
* `<footer>` untuk penutup halaman.

**2. Checklist Aksesibilitas Dasar (a11y)**
* Pendeklarasian bahasa utama dokumen: `lang="id"`.
* Hierarki heading yang logis berurutan (H1 hingga H4 tanpa terputus).
* Teks alternatif (`alt` attribute) pada semua elemen gambar (`<img>`).
* Atribut `for` pada `<label>` yang terhubung tepat dengan atribut `id` pada `<input>`/`<select>`.
* Teks tautan (link) yang deskriptif dan memiliki tujuan jelas.

## ✨ Pemenuhan Syarat Modul 9 (Back-End PHP & REST API)
Layanan server-side backend dibangun dengan standar berikut:

* **Versi PHP:** PHP 8.4 / 8.3 pada Laragon 5 dengan `declare(strict_types=1);`.
* **Kebutuhan Composer:** Pengelolaan dependensi via `composer.json` dan `composer.lock` menggunakan pustaka `vlucas/phpdotenv`.
* **Konfigurasi Environment:** Konfigurasi tersimpan pada `.env`, template publik pada `.env.example`, dan diamankan melalui `.gitignore`.
* **Daftar Endpoint REST API:**
  * `GET /api/index.php` : Informasi umum API Pidatoku (HTTP 200).
  * `GET /api/health.php` : Pemeriksaan status kesehatan server (HTTP 200).
  * `GET /api/live-stream.php` : Deteksi otomatis siaran langsung pidato presiden (HTTP 200).
  * `POST /api/live-stream.php` : Input catatan sentimen pidato (HTTP 201 Created, HTTP 400 Bad Request, HTTP 422 Unprocessable Content, HTTP 405 Method Not Allowed).

## 🗺️ Sitemap / Struktur Halaman
* `/index.html` (Halaman Utama / Landing Page)
  * `#live-monitor` : Pemantauan Siaran Langsung & Data Pasar (IHSG/Kurs).
  * `#arsip-pidato` : Arsip Berita Pidato Historis dan Dampak Pasar.
  * `#kontak` : Formulir Interaktif untuk Masukan Pengguna.
* `/api/` (Layanan REST API Backend)

## 🚀 Cara Menjalankan (Pengujian Localhost)
1. Pastikan server lokal (**Laragon 5**) sudah berjalan (klik **Start All**).
2. Tempatkan direktori `pidatoku` ini di dalam folder `C:\laragon\www\`.
3. Jalankan `composer install` jika folder `vendor` belum terpasang.
4. Salin konfigurasi dengan perintah `copy .env.example .env`.
5. Buka browser dan akses URL: `http://localhost/pidatoku/`.
