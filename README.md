# PIDATOKU 🇮🇩

Platform pemantauan sentimen pasar finansial (IHSG dan kurs IDR/USD) terhadap pidato kenegaraan. Dikembangkan untuk praktikum Pemrograman Web, Teknik Komputer, Universitas Borneo Tarakan.

## Identitas Mahasiswa
* **Nama:** Rizky Rahmad Dani
* **NPM:** 2440304001
* **Program Studi:** Teknik Komputer
* **Kampus:** Universitas Borneo Tarakan

## Fitur Halaman Web
Halaman depan dibangun menggunakan HTML5 semantik dan standar aksesibilitas:
* Struktur semantik: `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<figure>`, `<aside>`, `<form>`, dan `<footer>`.
* Aksesibilitas: atribut `lang="id"`, urutan heading rapi (H1 sampai H4), teks `alt` pada gambar, dan label form yang terhubung ke input.
* Mode pemutar video otomatis dengan layout responsif.

## Backend & REST API
Layanan backend dibangun menggunakan PHP di Laragon 5:
* **PHP:** Berjalan di PHP 8.4 / 8.3 dengan `declare(strict_types=1);`.
* **Package Manager:** Composer dengan package `vlucas/phpdotenv`.
* **Environment:** Pengaturan tersimpan di file `.env` dan `.env.example` (diamankan lewat `.gitignore`).
* **Endpoint API:**
  * `GET /api/index.php` : Informasi dasar API (200 OK).
  * `GET /api/health.php` : Cek status kesehatan server (200 OK).
  * `GET /api/live-stream.php` : Siaran langsung atau pidato terbaru presiden dari channel Sekretariat Presiden lewat YouTube Data API v3 (200 OK).
  * `POST /api/live-stream.php` : Input catatan sentimen (201 Created, 400 Bad Request, 422 Unprocessable Content, 405 Method Not Allowed).

## Struktur Halaman
* `/index.html` : Halaman utama pemantauan pidato dan data pasar.
* `/api/` : Layanan REST API backend.

## Cara Menjalankan
1. Jalankan Laragon 5 (klik Start All untuk menjalankan Apache dan PHP).
2. Pastikan folder berada di `C:\laragon\www\pidatoku`.
3. Jalankan `composer install` jika folder `vendor` belum terpasang.
4. Salin file setting: `copy .env.example .env`.
5. Isi `API_KEY` di `.env` dengan API key YouTube Data API v3.
6. Buka browser dan akses `http://localhost/pidatoku/`.
