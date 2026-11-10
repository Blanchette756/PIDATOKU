<?php
declare(strict_types=1);

final class SumberIhsg
{
    private const DURASI_CACHE_DETIK = 300;

    public function __construct(private string $urlCsv)
    {
    }

    public function urlValid(): bool
    {
        return str_starts_with($this->urlCsv, 'https://docs.google.com/spreadsheets/');
    }

    public function ambil(): ?array
    {
        $fileCache = sys_get_temp_dir() . '/pidatoku-ihsg-' . md5($this->urlCsv) . '.json';
        $cache = is_file($fileCache) ? json_decode((string) file_get_contents($fileCache), true) : null;
        $cacheValid = is_array($cache) && isset($cache['harga'], $cache['riwayat']);

        if ($cacheValid && time() - filemtime($fileCache) < self::DURASI_CACHE_DETIK) {
            return ['asal' => 'cache', 'data' => $cache];
        }

        $csv = $this->urlValid() ? $this->unduhCsv() : null;
        $data = $csv === null ? null : self::baca($csv);

        if ($data !== null) {
            if ($data['riwayat'] === [] && $cacheValid && $cache['riwayat'] !== []) {
                $data['riwayat'] = $cache['riwayat'];
            }
            $data['diperbarui_pada'] = date(DATE_ATOM);
            file_put_contents($fileCache, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
            return ['asal' => 'baru', 'data' => $data];
        }

        return $cacheValid ? ['asal' => 'cadangan', 'data' => $cache] : null;
    }

    public static function baca(string $csv): ?array
    {
        $aliran = fopen('php://memory', 'r+');
        fwrite($aliran, $csv);
        rewind($aliran);

        $ringkasan = [];
        $riwayat = [];
        $bagianRiwayat = false;

        while (($baris = fgetcsv($aliran, 0, ',', '"', '')) !== false) {
            $kolomA = strtolower(trim((string) ($baris[0] ?? '')));
            $kolomB = trim((string) ($baris[1] ?? ''));

            if ($kolomA === 'date') {
                $bagianRiwayat = true;
                continue;
            }

            if ($bagianRiwayat) {
                $tanggal = self::ubahTanggal((string) ($baris[0] ?? ''), 'Y-m-d');
                $tutup = self::ubahAngka($kolomB);
                if ($tanggal !== null && $tutup !== null) {
                    $riwayat[] = ['tanggal' => $tanggal, 'tutup' => $tutup];
                }
                continue;
            }

            if ($kolomA !== '') {
                $ringkasan[$kolomA] = $kolomB;
            } elseif ($kolomB !== '' && !isset($ringkasan['waktu']) && self::ubahTanggal($kolomB, DATE_ATOM) !== null) {
                $ringkasan['waktu'] = $kolomB;
            }
        }
        fclose($aliran);

        $harga = self::ubahAngka($ringkasan['harga'] ?? '');
        if ($harga === null) {
            return null;
        }

        return [
            'indeks' => 'IHSG',
            'simbol' => $ringkasan['ticker'] ?? 'IDX:COMPOSITE',
            'harga' => $harga,
            'perubahan' => self::ubahAngka($ringkasan['perubahan'] ?? ''),
            'persen' => self::ubahAngka($ringkasan['persen'] ?? ''),
            'waktu_transaksi' => self::ubahTanggal($ringkasan['waktu'] ?? '', DATE_ATOM),
            'riwayat' => $riwayat,
            'sumber' => 'Google Finance',
            'tertunda_menit' => 20
        ];
    }

    private function unduhCsv(): ?string
    {
        $konteks = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $konten = @file_get_contents($this->urlCsv, false, $konteks);

        $daftarHeader = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : ($http_response_header ?? []);
        $daftarStatus = array_values(array_filter($daftarHeader, fn(string $baris): bool => str_starts_with($baris, 'HTTP/')));
        $statusTerakhir = end($daftarStatus) ?: '';

        if ($konten === false || !preg_match('/\s200\b/', $statusTerakhir)) {
            return null;
        }
        return $konten;
    }

    private static function ubahAngka(string $nilai): ?float
    {
        $bersih = str_replace([' ', '%', ','], '', trim($nilai));
        return is_numeric($bersih) ? (float) $bersih : null;
    }

    private static function ubahTanggal(string $nilai, string $format): ?string
    {
        foreach (['!n/j/Y H:i:s', '!n/j/Y'] as $pola) {
            $tanggal = DateTimeImmutable::createFromFormat($pola, trim($nilai), new DateTimeZone('Asia/Jakarta'));
            $galat = DateTimeImmutable::getLastErrors();
            if ($tanggal !== false && ($galat === false || $galat['warning_count'] === 0)) {
                return $tanggal->format($format);
            }
        }
        return null;
    }
}
