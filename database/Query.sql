USE pidatoku;

INSERT IGNORE INTO pidato (id, video_id, judul, topik, kategori, deskripsi, pembicara, sumber, thumbnail, tayang_pada) VALUES
(1, NULL, 'Pidato RUU APBN 2027', 'APBN 2027', 'Ekonomi',
 'Fokus anggaran belanja pemerintah diarahkan ke sektor kedaulatan digital, hilirisasi industri, dan ketahanan energi.',
 'Presiden Republik Indonesia (Prabowo Subianto)', 'Sekretariat Presiden', 'assets/thumbnail-pidato1.jpg', '2026-08-16 00:00:00'),
(2, NULL, 'Pidato Kenegaraan HUT RI', 'HUT RI', 'Geopolitik',
 'Penekanan arah geopolitik nasional, perlindungan rantai pasok ekonomi mikro, dan stabilitas cadangan devisa.',
 'Presiden Republik Indonesia (Prabowo Subianto)', 'Sekretariat Presiden', 'assets/thumbnail-pidato2.jpg', '2026-08-17 00:00:00');

INSERT IGNORE INTO dampak_pasar (id, pidato_id, indikator, kode_mata_uang, perubahan, satuan, status_pasar, dicatat_pada) VALUES
(1, 1, 'IHSG', NULL, 0.80, 'persen', 'Buka', '2026-08-16 00:00:00'),
(2, 1, 'KURS', 'USD', 25.00, 'poin', 'Buka', '2026-08-16 00:00:00'),
(3, 2, 'IHSG', NULL, NULL, 'persen', 'Libur', '2026-08-17 00:00:00'),
(4, 2, 'KURS', 'USD', NULL, 'poin', 'Libur', '2026-08-17 00:00:00');
