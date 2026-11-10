USE pidatoku;

DELETE FROM pidato WHERE video_id IS NULL;

ALTER TABLE pidato
    DROP COLUMN topik,
    MODIFY video_id VARCHAR(11) NOT NULL,
    MODIFY kategori ENUM('Ekonomi','Geopolitik','Kenegaraan','Pendidikan') NULL,
    MODIFY pembicara VARCHAR(150) NOT NULL DEFAULT 'Presiden Republik Indonesia (Prabowo Subianto)',
    ADD COLUMN durasi_detik INT UNSIGNED NULL AFTER thumbnail,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE dampak_pasar
    MODIFY status_pasar ENUM('Buka','Libur','Menunggu') NOT NULL,
    ADD CONSTRAINT uq_dampak_pasar_indikator UNIQUE (pidato_id, indikator);

CREATE TABLE IF NOT EXISTS pidato_diabaikan (
    video_id VARCHAR(11) PRIMARY KEY,
    dihapus_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
