CREATE DATABASE IF NOT EXISTS pidatoku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pidatoku;

CREATE TABLE IF NOT EXISTS pidato (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(11) NOT NULL UNIQUE,
    judul VARCHAR(255) NOT NULL,
    kategori ENUM('Ekonomi','Geopolitik','Kenegaraan','Pendidikan') NULL,
    deskripsi TEXT NULL,
    pembicara VARCHAR(150) NOT NULL DEFAULT 'Presiden Republik Indonesia (Prabowo Subianto)',
    sumber VARCHAR(100) NOT NULL DEFAULT 'Sekretariat Presiden',
    thumbnail VARCHAR(255) NULL,
    durasi_detik INT UNSIGNED NULL,
    tayang_pada DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pidato_kategori (kategori),
    INDEX idx_pidato_tayang_pada (tayang_pada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dampak_pasar (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pidato_id BIGINT UNSIGNED NOT NULL,
    indikator ENUM('IHSG','KURS') NOT NULL,
    kode_mata_uang CHAR(3) NULL,
    perubahan DECIMAL(10,2) NULL,
    satuan ENUM('persen','poin') NOT NULL,
    status_pasar ENUM('Buka','Libur','Menunggu') NOT NULL,
    dicatat_pada DATETIME NOT NULL,
    CONSTRAINT fk_dampak_pasar_pidato FOREIGN KEY (pidato_id) REFERENCES pidato(id) ON DELETE CASCADE,
    CONSTRAINT uq_dampak_pasar_indikator UNIQUE (pidato_id, indikator),
    CONSTRAINT chk_dampak_pasar_mata_uang CHECK (
        (indikator = 'IHSG' AND kode_mata_uang IS NULL)
        OR (indikator = 'KURS' AND kode_mata_uang IS NOT NULL)
    ),
    CONSTRAINT chk_dampak_pasar_libur CHECK (status_pasar = 'Buka' OR perubahan IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pidato_diabaikan (
    video_id VARCHAR(11) PRIMARY KEY,
    dihapus_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS masukan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    kategori ENUM('fitur','bug','kerjasama') NOT NULL,
    detail_pesan TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_masukan_kategori (kategori)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
