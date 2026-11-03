<?php
declare(strict_types=1);

final class DataPidato
{
    private const KOLOM = 'p.id, p.video_id, p.judul, p.topik, p.kategori, p.deskripsi, p.pembicara, p.sumber,
                           p.thumbnail, p.tayang_pada,
                           d.indikator, d.kode_mata_uang, d.perubahan, d.satuan, d.status_pasar, d.dicatat_pada';

    public function __construct(private PDO $pdo)
    {
    }

    public function cariSemua(?string $kategori = null): array
    {
        $sql = 'SELECT ' . self::KOLOM . '
                FROM pidato p
                LEFT JOIN dampak_pasar d ON d.pidato_id = p.id';
        $parameter = [];

        if ($kategori !== null) {
            $sql .= ' WHERE p.kategori = :kategori';
            $parameter['kategori'] = $kategori;
        }

        $sql .= ' ORDER BY p.tayang_pada DESC, d.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameter);

        return $this->kelompokkan($stmt->fetchAll());
    }

    private function kelompokkan(array $baris): array
    {
        $daftarPidato = [];

        foreach ($baris as $b) {
            $id = (int) $b['id'];

            if (!isset($daftarPidato[$id])) {
                $daftarPidato[$id] = [
                    'id' => $id,
                    'video_id' => $b['video_id'],
                    'judul' => $b['judul'],
                    'topik' => $b['topik'],
                    'kategori' => $b['kategori'],
                    'deskripsi' => $b['deskripsi'],
                    'pembicara' => $b['pembicara'],
                    'sumber' => $b['sumber'],
                    'thumbnail' => $b['thumbnail'],
                    'tayang_pada' => $b['tayang_pada'],
                    'dampak_pasar' => []
                ];
            }

            if ($b['indikator'] !== null) {
                $daftarPidato[$id]['dampak_pasar'][] = [
                    'indikator' => $b['indikator'],
                    'kode_mata_uang' => $b['kode_mata_uang'],
                    'perubahan' => $b['perubahan'] === null ? null : (float) $b['perubahan'],
                    'satuan' => $b['satuan'],
                    'status_pasar' => $b['status_pasar'],
                    'dicatat_pada' => $b['dicatat_pada']
                ];
            }
        }

        return array_values($daftarPidato);
    }
}
