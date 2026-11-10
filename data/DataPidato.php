<?php
declare(strict_types=1);

final class DataPidato
{
    public const DAFTAR_KATEGORI = ['Ekonomi', 'Geopolitik', 'Kenegaraan', 'Pendidikan'];
    public const DAFTAR_URUTAN = ['terbaru', 'terlama', 'ihsg_tertinggi', 'ihsg_terendah'];

    private const KOLOM_PIDATO = 'p.id, p.video_id, p.judul, p.kategori, p.deskripsi, p.pembicara, p.sumber,
                                  p.thumbnail, p.durasi_detik, p.tayang_pada, p.updated_at';

    private const URUTAN_SQL = [
        'terbaru' => 'p.tayang_pada DESC, p.id DESC',
        'terlama' => 'p.tayang_pada ASC, p.id ASC',
        'ihsg_tertinggi' => 'i.perubahan IS NULL, i.perubahan DESC, p.tayang_pada DESC',
        'ihsg_terendah' => 'i.perubahan IS NULL, i.perubahan ASC, p.tayang_pada DESC'
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function cariSemua(array $filter): array
    {
        [$where, $parameter] = $this->susunFilter($filter);
        $joinIhsg = "LEFT JOIN dampak_pasar i ON i.pidato_id = p.id AND i.indikator = 'IHSG'";

        $stmtJumlah = $this->pdo->prepare("SELECT COUNT(*) FROM pidato p {$where}");
        $stmtJumlah->execute($parameter);
        $total = (int) $stmtJumlah->fetchColumn();

        $perHalaman = $filter['per_halaman'];
        $offset = ($filter['halaman'] - 1) * $perHalaman;
        $urutan = self::URUTAN_SQL[$filter['urutan']];

        $stmt = $this->pdo->prepare(
            'SELECT ' . self::KOLOM_PIDATO . " FROM pidato p {$joinIhsg} {$where}
             ORDER BY {$urutan} LIMIT :batas OFFSET :lewati"
        );
        foreach ($parameter as $kunci => $nilai) {
            $stmt->bindValue($kunci, $nilai);
        }
        $stmt->bindValue('batas', $perHalaman, PDO::PARAM_INT);
        $stmt->bindValue('lewati', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $this->lengkapiDampak($stmt->fetchAll()),
            'total' => $total
        ];
    }

    public function cariSatu(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::KOLOM_PIDATO . ' FROM pidato p WHERE p.id = :id');
        $stmt->execute(['id' => $id]);
        $baris = $stmt->fetch();

        return $baris === false ? null : $this->lengkapiDampak([$baris])[0];
    }

    public function cariIdDariVideo(string $videoId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM pidato WHERE video_id = :video_id');
        $stmt->execute(['video_id' => $videoId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function videoSudahDikenal(array $daftarVideoId): array
    {
        if ($daftarVideoId === []) {
            return [];
        }

        $tanda = implode(',', array_fill(0, count($daftarVideoId), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT video_id FROM pidato WHERE video_id IN ({$tanda})
             UNION SELECT video_id FROM pidato_diabaikan WHERE video_id IN ({$tanda})"
        );
        $stmt->execute([...$daftarVideoId, ...$daftarVideoId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function tambah(array $pidato, array $dampak): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO pidato (video_id, judul, kategori, deskripsi, thumbnail, durasi_detik, tayang_pada)
                 VALUES (:video_id, :judul, :kategori, :deskripsi, :thumbnail, :durasi_detik, :tayang_pada)'
            );
            $stmt->execute([
                'video_id' => $pidato['video_id'],
                'judul' => $pidato['judul'],
                'kategori' => $pidato['kategori'] ?? null,
                'deskripsi' => $pidato['deskripsi'],
                'thumbnail' => $pidato['thumbnail'],
                'durasi_detik' => $pidato['durasi_detik'],
                'tayang_pada' => $pidato['tayang_pada']
            ]);
            $idBaru = (int) $this->pdo->lastInsertId();

            $this->simpanDampak($idBaru, $dampak);

            $hapusAbaikan = $this->pdo->prepare('DELETE FROM pidato_diabaikan WHERE video_id = :video_id');
            $hapusAbaikan->execute(['video_id' => $pidato['video_id']]);

            $this->pdo->commit();
            return $idBaru;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function ubah(int $id, array $perubahan, ?array $dampak): void
    {
        $kolomBoleh = ['judul', 'kategori', 'deskripsi', 'tayang_pada'];
        $bagianSet = [];
        $parameter = ['id' => $id];

        foreach ($kolomBoleh as $kolom) {
            if (array_key_exists($kolom, $perubahan)) {
                $bagianSet[] = "{$kolom} = :{$kolom}";
                $parameter[$kolom] = $perubahan[$kolom];
            }
        }

        $this->pdo->beginTransaction();

        try {
            if ($bagianSet !== []) {
                $stmt = $this->pdo->prepare('UPDATE pidato SET ' . implode(', ', $bagianSet) . ' WHERE id = :id');
                $stmt->execute($parameter);
            }

            if ($dampak !== null) {
                $this->simpanDampak($id, $dampak);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function hapus(int $id): bool
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('SELECT video_id FROM pidato WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $videoId = $stmt->fetchColumn();

            if ($videoId === false) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->prepare('DELETE FROM pidato WHERE id = :id')->execute(['id' => $id]);
            $this->pdo->prepare('INSERT IGNORE INTO pidato_diabaikan (video_id) VALUES (:video_id)')
                ->execute(['video_id' => $videoId]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function perbaruiDampak(int $id, array $dampak): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->simpanDampak($id, $dampak);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function menungguDataPasar(int $batas): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id, DATE(p.tayang_pada) AS tanggal
             FROM pidato p
             LEFT JOIN dampak_pasar d ON d.pidato_id = p.id
             GROUP BY p.id, p.tayang_pada
             HAVING COUNT(d.id) < 2 OR SUM(d.status_pasar = 'Menunggu') > 0
             ORDER BY p.tayang_pada DESC
             LIMIT :batas"
        );
        $stmt->bindValue('batas', $batas, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function ringkasan(): array
    {
        $umum = $this->pdo->query(
            "SELECT COUNT(*) AS total, MIN(tayang_pada) AS sejak FROM pidato"
        )->fetch();

        $rataRata = $this->pdo->query(
            "SELECT AVG(perubahan) AS rata_rata, COUNT(*) AS jumlah
             FROM dampak_pasar WHERE indikator = 'IHSG' AND status_pasar = 'Buka'"
        )->fetch();

        $terbesar = $this->pdo->query(
            "SELECT p.id, p.judul, p.tayang_pada, d.perubahan
             FROM dampak_pasar d JOIN pidato p ON p.id = d.pidato_id
             WHERE d.indikator = 'IHSG' AND d.status_pasar = 'Buka'
             ORDER BY d.perubahan DESC, p.tayang_pada DESC
             LIMIT 1"
        )->fetch();

        $daftarTahun = $this->pdo->query(
            'SELECT DISTINCT YEAR(tayang_pada) FROM pidato ORDER BY 1 DESC'
        )->fetchAll(PDO::FETCH_COLUMN);

        return [
            'total_pidato' => (int) $umum['total'],
            'sejak' => $umum['sejak'],
            'rata_rata_ihsg' => $rataRata['rata_rata'] === null ? null : round((float) $rataRata['rata_rata'], 2),
            'jumlah_hari_bursa' => (int) $rataRata['jumlah'],
            'kenaikan_ihsg_terbesar' => $terbesar === false ? null : [
                'id' => (int) $terbesar['id'],
                'judul' => $terbesar['judul'],
                'tayang_pada' => $terbesar['tayang_pada'],
                'perubahan' => (float) $terbesar['perubahan']
            ],
            'daftar_tahun' => array_map('intval', $daftarTahun)
        ];
    }

    private function susunFilter(array $filter): array
    {
        $syarat = [];
        $parameter = [];

        if ($filter['q'] !== null) {
            $syarat[] = 'p.judul LIKE :kata_kunci';
            $parameter['kata_kunci'] = '%' . addcslashes($filter['q'], '%_\\') . '%';
        }
        if ($filter['tahun'] !== null) {
            $syarat[] = 'p.tayang_pada >= :awal_tahun AND p.tayang_pada < :akhir_tahun';
            $parameter['awal_tahun'] = $filter['tahun'] . '-01-01 00:00:00';
            $parameter['akhir_tahun'] = ($filter['tahun'] + 1) . '-01-01 00:00:00';
        }
        if ($filter['kategori'] !== null) {
            $syarat[] = 'p.kategori = :kategori';
            $parameter['kategori'] = $filter['kategori'];
        }

        return [$syarat === [] ? '' : 'WHERE ' . implode(' AND ', $syarat), $parameter];
    }

    private function simpanDampak(int $pidatoId, array $dampak): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO dampak_pasar (pidato_id, indikator, kode_mata_uang, perubahan, satuan, status_pasar, dicatat_pada)
             VALUES (:pidato_id, :indikator, :kode_mata_uang, :perubahan, :satuan, :status_pasar, :dicatat_pada) AS baru
             ON DUPLICATE KEY UPDATE
                perubahan = baru.perubahan,
                status_pasar = baru.status_pasar,
                dicatat_pada = baru.dicatat_pada'
        );

        foreach (['IHSG' => ['kode' => null, 'satuan' => 'persen'], 'KURS' => ['kode' => 'USD', 'satuan' => 'poin']] as $indikator => $info) {
            $stmt->execute([
                'pidato_id' => $pidatoId,
                'indikator' => $indikator,
                'kode_mata_uang' => $info['kode'],
                'perubahan' => $dampak[$indikator]['perubahan'],
                'satuan' => $info['satuan'],
                'status_pasar' => $dampak[$indikator]['status_pasar'],
                'dicatat_pada' => $dampak[$indikator]['dicatat_pada']
            ]);
        }
    }

    private function lengkapiDampak(array $daftarBaris): array
    {
        if ($daftarBaris === []) {
            return [];
        }

        $daftarId = array_map(fn(array $baris): int => (int) $baris['id'], $daftarBaris);
        $tanda = implode(',', array_fill(0, count($daftarId), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT pidato_id, indikator, kode_mata_uang, perubahan, satuan, status_pasar, dicatat_pada
             FROM dampak_pasar WHERE pidato_id IN ({$tanda})"
        );
        $stmt->execute($daftarId);

        $dampakPerPidato = [];
        foreach ($stmt->fetchAll() as $d) {
            $dampakPerPidato[(int) $d['pidato_id']][$d['indikator']] = [
                'kode_mata_uang' => $d['kode_mata_uang'],
                'perubahan' => $d['perubahan'] === null ? null : (float) $d['perubahan'],
                'satuan' => $d['satuan'],
                'status_pasar' => $d['status_pasar'],
                'dicatat_pada' => $d['dicatat_pada']
            ];
        }

        return array_map(function (array $b) use ($dampakPerPidato): array {
            $id = (int) $b['id'];
            return [
                'id' => $id,
                'video_id' => $b['video_id'],
                'judul' => $b['judul'],
                'kategori' => $b['kategori'],
                'deskripsi' => $b['deskripsi'],
                'pembicara' => $b['pembicara'],
                'sumber' => $b['sumber'],
                'thumbnail' => $b['thumbnail'],
                'durasi_detik' => $b['durasi_detik'] === null ? null : (int) $b['durasi_detik'],
                'tayang_pada' => $b['tayang_pada'],
                'url_video' => 'https://www.youtube-nocookie.com/embed/' . $b['video_id'],
                'diperbarui_pada' => $b['updated_at'],
                'dampak_pasar' => [
                    'ihsg' => $dampakPerPidato[$id]['IHSG'] ?? null,
                    'kurs' => $dampakPerPidato[$id]['KURS'] ?? null
                ]
            ];
        }, $daftarBaris);
    }
}
