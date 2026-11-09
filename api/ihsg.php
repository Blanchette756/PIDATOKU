<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class) && file_exists(__DIR__ . '/../.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($method !== 'GET') {
    header('Allow: GET, OPTIONS');
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'code' => 405,
        'pesan' => 'Method tidak diizinkan. Gunakan GET untuk mengambil data IHSG.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

const DURASI_CACHE_IHSG_DETIK = 300;

function kirimJson(int $kode, array $isi): never {
    http_response_code($kode);
    echo json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function ubahAngka(string $nilai): ?float {
    $bersih = str_replace([' ', '%', ','], '', trim($nilai));
    return is_numeric($bersih) ? (float)$bersih : null;
}

function ubahTanggal(string $nilai, string $format): ?string {
    foreach (['!n/j/Y H:i:s', '!n/j/Y'] as $pola) {
        $tanggal = DateTimeImmutable::createFromFormat($pola, trim($nilai), new DateTimeZone('Asia/Jakarta'));
        if ($tanggal !== false) {
            return $tanggal->format($format);
        }
    }
    return null;
}

function ambilCsvGoogleSheet(string $url): ?string {
    $konteks = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $konten = @file_get_contents($url, false, $konteks);

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

function bacaDataIhsg(string $csv): ?array {
    $aliran = fopen('php://memory', 'r+');
    fwrite($aliran, $csv);
    rewind($aliran);

    $ringkasan = [];
    $riwayat = [];
    $bagianRiwayat = false;

    while (($baris = fgetcsv($aliran, 0, ',', '"', '')) !== false) {
        $kolomA = strtolower(trim((string)($baris[0] ?? '')));
        $kolomB = trim((string)($baris[1] ?? ''));

        if ($kolomA === 'date') {
            $bagianRiwayat = true;
            continue;
        }

        if ($bagianRiwayat) {
            $tanggal = ubahTanggal((string)($baris[0] ?? ''), 'Y-m-d');
            $tutup = ubahAngka($kolomB);
            if ($tanggal !== null && $tutup !== null) {
                $riwayat[] = ['tanggal' => $tanggal, 'tutup' => $tutup];
            }
            continue;
        }

        if ($kolomA !== '') {
            $ringkasan[$kolomA] = $kolomB;
        }
    }
    fclose($aliran);

    $harga = ubahAngka($ringkasan['harga'] ?? '');
    if ($harga === null) {
        return null;
    }

    return [
        'indeks' => 'IHSG',
        'simbol' => $ringkasan['ticker'] ?? 'IDX:COMPOSITE',
        'harga' => $harga,
        'perubahan' => ubahAngka($ringkasan['perubahan'] ?? ''),
        'persen' => ubahAngka($ringkasan['persen'] ?? ''),
        'waktu_transaksi' => ubahTanggal($ringkasan['waktu'] ?? '', DATE_ATOM),
        'riwayat' => $riwayat,
        'sumber' => 'Google Finance',
        'tertunda_menit' => 20
    ];
}

$urlSheet = trim((string)($_ENV['GOOGLE_SHEET_IHSG_CSV'] ?? ''));

if (!str_starts_with($urlSheet, 'https://docs.google.com/spreadsheets/')) {
    kirimJson(503, [
        'status' => 'error',
        'code' => 503,
        'pesan' => 'URL CSV Google Sheet IHSG belum diatur di file .env (GOOGLE_SHEET_IHSG_CSV).'
    ]);
}

$fileCache = sys_get_temp_dir() . '/pidatoku-ihsg-' . md5($urlSheet) . '.json';
$cache = is_file($fileCache) ? json_decode((string)file_get_contents($fileCache), true) : null;
$cacheValid = is_array($cache) && isset($cache['harga']);

if ($cacheValid && time() - filemtime($fileCache) < DURASI_CACHE_IHSG_DETIK) {
    kirimJson(200, [
        'status' => 'success',
        'code' => 200,
        'pesan' => 'Data IHSG diambil dari cache',
        'data' => $cache
    ]);
}

$csv = ambilCsvGoogleSheet($urlSheet);
$data = $csv === null ? null : bacaDataIhsg($csv);

if ($data !== null) {
    $data['diperbarui_pada'] = date(DATE_ATOM);
    file_put_contents($fileCache, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);

    kirimJson(200, [
        'status' => 'success',
        'code' => 200,
        'pesan' => 'Data IHSG berhasil diperbarui dari Google Finance',
        'data' => $data
    ]);
}

if ($cacheValid) {
    kirimJson(200, [
        'status' => 'success',
        'code' => 200,
        'pesan' => 'Google Sheet tidak dapat dihubungi, menampilkan data IHSG terakhir yang tersimpan',
        'data' => $cache
    ]);
}

kirimJson(502, [
    'status' => 'error',
    'code' => 502,
    'pesan' => 'Data IHSG dari Google Sheet gagal dibaca. Periksa link CSV dan isi sheet.'
]);
