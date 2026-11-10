<?php
declare(strict_types=1);

require_once __DIR__ . '/SumberIhsg.php';

final class DataPasar
{
    private const URL_KURS = 'https://api.frankfurter.dev/v1/';
    private const AWAL_RIWAYAT_KURS = '2024-09-01';
    private const DURASI_CACHE_KURS_DETIK = 21600;

    private ?array $tutupIhsg = null;
    private ?array $kursIdr = null;

    public function __construct(private SumberIhsg $sumberIhsg)
    {
    }

    public function hitung(string $tanggal): array
    {
        $ihsg = $this->riwayatIhsg();
        $daftarTanggal = array_keys($ihsg);
        $dicatatPada = $tanggal . ' 16:00:00';

        if ($daftarTanggal === [] || $tanggal > end($daftarTanggal) || $tanggal <= $daftarTanggal[0]) {
            return $this->hasilTanpaAngka('Menunggu', $dicatatPada);
        }

        if (!isset($ihsg[$tanggal])) {
            return $this->hasilTanpaAngka('Libur', $dicatatPada);
        }

        $posisi = array_search($tanggal, $daftarTanggal, true);
        $tanggalSebelumnya = $daftarTanggal[$posisi - 1];
        $persenIhsg = round(($ihsg[$tanggal] - $ihsg[$tanggalSebelumnya]) / $ihsg[$tanggalSebelumnya] * 100, 2);

        $kursHariIni = $this->kursPada($tanggal);
        $kursSebelumnya = $this->kursPada($tanggalSebelumnya);
        $adaKurs = $kursHariIni !== null && $kursSebelumnya !== null;

        return [
            'IHSG' => [
                'perubahan' => $persenIhsg,
                'status_pasar' => 'Buka',
                'dicatat_pada' => $dicatatPada
            ],
            'KURS' => [
                'perubahan' => $adaKurs ? round($kursSebelumnya - $kursHariIni, 2) : null,
                'status_pasar' => $adaKurs ? 'Buka' : 'Menunggu',
                'dicatat_pada' => $dicatatPada
            ]
        ];
    }

    private function hasilTanpaAngka(string $status, string $dicatatPada): array
    {
        $baris = ['perubahan' => null, 'status_pasar' => $status, 'dicatat_pada' => $dicatatPada];
        return ['IHSG' => $baris, 'KURS' => $baris];
    }

    private function riwayatIhsg(): array
    {
        if ($this->tutupIhsg !== null) {
            return $this->tutupIhsg;
        }

        $hasil = $this->sumberIhsg->ambil();
        $this->tutupIhsg = [];

        foreach ($hasil['data']['riwayat'] ?? [] as $titik) {
            $this->tutupIhsg[$titik['tanggal']] = (float) $titik['tutup'];
        }
        ksort($this->tutupIhsg);

        return $this->tutupIhsg;
    }

    private function kursPada(string $tanggal): ?float
    {
        $kurs = $this->riwayatKurs();
        $terdekat = null;

        foreach ($kurs as $tanggalKurs => $nilai) {
            if ($tanggalKurs > $tanggal) {
                break;
            }
            $terdekat = $nilai;
        }

        return $terdekat;
    }

    private function riwayatKurs(): array
    {
        if ($this->kursIdr !== null) {
            return $this->kursIdr;
        }

        $fileCache = sys_get_temp_dir() . '/pidatoku-kurs-idr.json';
        $cache = is_file($fileCache) ? json_decode((string) file_get_contents($fileCache), true) : null;

        if (is_array($cache) && $cache !== [] && time() - filemtime($fileCache) < self::DURASI_CACHE_KURS_DETIK) {
            return $this->kursIdr = $cache;
        }

        $hariIni = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
        $url = self::URL_KURS . self::AWAL_RIWAYAT_KURS . '..' . $hariIni . '?' . http_build_query(['base' => 'USD', 'symbols' => 'IDR']);
        $konteks = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $data = json_decode((string) @file_get_contents($url, false, $konteks), true);

        $kurs = [];
        foreach ($data['rates'] ?? [] as $tanggal => $nilai) {
            if (isset($nilai['IDR'])) {
                $kurs[$tanggal] = (float) $nilai['IDR'];
            }
        }
        ksort($kurs);

        if ($kurs !== []) {
            file_put_contents($fileCache, json_encode($kurs), LOCK_EX);
            return $this->kursIdr = $kurs;
        }

        return $this->kursIdr = is_array($cache) ? $cache : [];
    }
}
