# PIDATOKU 🇮🇩

Platform Pemantauan Sentimen Pasar Finansial (IHSG & Kurs IDR/USD) terhadap Pidato Kenegaraan.
Proyek ini dikembangkan sebagai luaran Praktikum Modul 2 (Struktur HTML5 & Aksesibilitas) - Mata Kuliah Pemrograman Web (Kurikulum OBE).

## 🪪 Identitas Pengembang
* **Nama:** Rizky Rahmad Dani
* **Program Studi:** Teknik Komputer
* **NPM:** 2440304001
* **Univ:** Universitas Borneo Tarakan

## ✨ Pemenuhan Syarat Modul 2
Proyek ini telah menerapkan standar pembuatan halaman web dasar sesuai modul, meliputi:

**1. Struktur HTML5 Semantik**
Penggunaan elemen semantik yang tepat untuk menstrukturkan halaman:
* `<header>` dan `<nav>` untuk bagian atas dan navigasi utama.
* `<main>` sebagai pembungkus konten utama.
* `<section>` (Terdapat 3 section: Live Monitor, Arsip Pidato, dan Form Kontak).
* `<article>` digunakan di dalam arsip pidato untuk membungkus konten independen.
* `<form>` untuk formulir masukan pengguna.
* `<footer>` untuk penutup halaman.

**2. Checklist Aksesibilitas Dasar (a11y)**
* Pendeklarasian bahasa utama dokumen: `lang="id"`.
* Hierarki *heading* yang logis berurutan (Mulai dari H1 hingga H4 tanpa terputus).
* Teks alternatif (`alt` attribute) pada semua elemen gambar (`<img>`).
* Atribut `for` pada `<label>` yang terhubung tepat dengan atribut `id` pada `<input>`/`<select>`.
* Teks tautan (link) yang deskriptif dan memiliki tujuan jelas.
* Fitur *Skip to content* (Navigasi keyboard untuk melewati menu dan langsung ke konten utama).

## 🗺️ Sitemap / Wireframe Awal
* `/index.html` (Halaman Utama / Landing Page)
  * `#live-monitor` - Area Pemantauan Video & Indikator Pasar (IHSG/USD).
  * `#arsip-pidato` - Area Arsip Berita dan Dampak Pasar.
  * `#kontak` - Area Formulir Interaktif untuk Masukan Fitur.

## 🚀 Cara Menjalankan (Pengujian Localhost)
1. Pastikan server lokal (**Laragon** / XAMPP) sudah berjalan.
2. Tempatkan direktori `PIDATOKU` ini di dalam folder `www` (jika menggunakan Laragon).
3. Buka *browser* (Chrome/Edge/Firefox).
4. Akses proyek melalui URL: `http://localhost/PIDATOKU/`
