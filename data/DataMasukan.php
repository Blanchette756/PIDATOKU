<?php
declare(strict_types=1);

final class DataMasukan
{
    public function __construct(private PDO $pdo)
    {
    }

    public function simpan(string $namaLengkap, string $email, string $kategori, string $detailPesan): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO masukan (nama_lengkap, email, kategori, detail_pesan)
             VALUES (:nama_lengkap, :email, :kategori, :detail_pesan)'
        );
        $stmt->execute([
            'nama_lengkap' => $namaLengkap,
            'email' => $email,
            'kategori' => $kategori,
            'detail_pesan' => $detailPesan
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
