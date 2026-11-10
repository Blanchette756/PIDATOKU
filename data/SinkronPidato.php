<?php
declare(strict_types=1);

require_once __DIR__ . '/DataPidato.php';
require_once __DIR__ . '/DataPasar.php';
require_once __DIR__ . '/SumberYoutube.php';

final class SinkronPidato
{
    public const AWAL_ARSIP = '2024-10-01';
    private const JEDA_SINKRON_DETIK = 600;
    private const HALAMAN_ARSIP_PER_SINKRON = 4;
    private const BATAS_PERBARUI_PASAR = 40;

    public function __construct(
        private DataPidato $dataPidato,
        private SumberYoutube $youtube,
        private DataPasar $dataPasar,
        private string $fileStatus
    ) {
    }

    public function jalankan(): array
    {
        $kunci = fopen($this->fileStatus . '.lock', 'c');
        if ($kunci === false || !flock($kunci, LOCK_EX | LOCK_NB)) {
            return $this->hasil('sedang_berjalan', 0, 0, $this->bacaStatus());
        }

        try {
            $status = $this->bacaStatus();

            if ($status['arsip_lengkap'] && time() - $status['terakhir'] < self::JEDA_SINKRON_DETIK) {
                return $this->hasil('dilewati', 0, 0, $status);
            }

            $ditambahkan = $this->ambilHalaman(null)['ditambahkan'];

            if (!$status['arsip_lengkap']) {
                for ($i = 0; $i < self::HALAMAN_ARSIP_PER_SINKRON; $i++) {
                    $halaman = $this->ambilHalaman($status['token_arsip']);
                    $ditambahkan += $halaman['ditambahkan'];
                    $status['token_arsip'] = $halaman['token_berikutnya'];

                    if ($halaman['token_berikutnya'] === null || $halaman['melewati_awal_arsip']) {
                        $status['arsip_lengkap'] = true;
                        $status['token_arsip'] = null;
                        break;
                    }
                }
            }

            $pasarDiperbarui = $this->perbaruiDataPasar();

            $status['terakhir'] = time();
            $this->simpanStatus($status);

            return $this->hasil('selesai', $ditambahkan, $pasarDiperbarui, $status);
        } finally {
            flock($kunci, LOCK_UN);
            fclose($kunci);
        }
    }

    private function ambilHalaman(?string $token): array
    {
        $halaman = $this->youtube->halamanUpload($token);
        if ($halaman === null) {
            throw new RuntimeException('Daftar video YouTube gagal diambil');
        }

        $dikenal = $this->dataPidato->videoSudahDikenal($halaman['video_id']);
        $baru = array_values(array_diff($halaman['video_id'], $dikenal));
        $detail = $this->youtube->detailVideo($baru);
        if ($detail === null) {
            throw new RuntimeException('Detail video YouTube gagal diambil');
        }

        $ditambahkan = 0;
        foreach ($detail as $video) {
            $pidato = $this->youtube->saringPidato($video);
            if ($pidato === null || $pidato['tayang_pada'] < self::AWAL_ARSIP) {
                continue;
            }

            $dampak = $this->dataPasar->hitung(substr($pidato['tayang_pada'], 0, 10));
            $this->dataPidato->tambah($pidato, $dampak);
            $ditambahkan++;
        }

        return [
            'ditambahkan' => $ditambahkan,
            'token_berikutnya' => $halaman['token_berikutnya'],
            'melewati_awal_arsip' => $halaman['terbit_tertua'] !== null && $halaman['terbit_tertua'] < self::AWAL_ARSIP
        ];
    }

    private function perbaruiDataPasar(): int
    {
        $jumlah = 0;

        foreach ($this->dataPidato->menungguDataPasar(self::BATAS_PERBARUI_PASAR) as $pidato) {
            $dampak = $this->dataPasar->hitung($pidato['tanggal']);
            $this->dataPidato->perbaruiDampak((int) $pidato['id'], $dampak);

            if ($dampak['IHSG']['status_pasar'] !== 'Menunggu') {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    private function bacaStatus(): array
    {
        $status = is_file($this->fileStatus) ? json_decode((string) file_get_contents($this->fileStatus), true) : null;

        return [
            'terakhir' => (int) ($status['terakhir'] ?? 0),
            'arsip_lengkap' => (bool) ($status['arsip_lengkap'] ?? false),
            'token_arsip' => $status['token_arsip'] ?? null
        ];
    }

    private function simpanStatus(array $status): void
    {
        file_put_contents($this->fileStatus, json_encode($status), LOCK_EX);
    }

    private function hasil(string $keadaan, int $ditambahkan, int $pasarDiperbarui, array $status): array
    {
        return [
            'keadaan' => $keadaan,
            'pidato_ditambahkan' => $ditambahkan,
            'dampak_pasar_diperbarui' => $pasarDiperbarui,
            'arsip_lengkap' => $status['arsip_lengkap'],
            'sinkron_terakhir' => $status['terakhir'] > 0 ? date(DATE_ATOM, $status['terakhir']) : null
        ];
    }
}
