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

require_once __DIR__ . '/../data/SumberIhsg.php';

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

const JUMLAH_HARI_GRAFIK = 120;

function kirimJson(int $kode, array $isi): never {
    http_response_code($kode);
    echo json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function potongRiwayatGrafik(array $data): array {
    $batas = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))
        ->modify('-' . JUMLAH_HARI_GRAFIK . ' days')
        ->format('Y-m-d');

    $data['riwayat'] = array_values(array_filter(
        $data['riwayat'],
        fn(array $titik): bool => $titik['tanggal'] >= $batas
    ));
    return $data;
}

$sumberIhsg = new SumberIhsg(trim((string)($_ENV['GOOGLE_SHEET_IHSG_CSV'] ?? '')));

if (!$sumberIhsg->urlValid()) {
    kirimJson(503, [
        'status' => 'error',
        'code' => 503,
        'pesan' => 'URL CSV Google Sheet IHSG belum diatur di file .env (GOOGLE_SHEET_IHSG_CSV).'
    ]);
}

$hasil = $sumberIhsg->ambil();

if ($hasil === null) {
    kirimJson(502, [
        'status' => 'error',
        'code' => 502,
        'pesan' => 'Data IHSG dari Google Sheet gagal dibaca. Periksa link CSV dan isi sheet.'
    ]);
}

$daftarPesan = [
    'cache' => 'Data IHSG diambil dari cache',
    'baru' => 'Data IHSG berhasil diperbarui dari Google Finance',
    'cadangan' => 'Google Sheet tidak dapat dihubungi, menampilkan data IHSG terakhir yang tersimpan'
];

kirimJson(200, [
    'status' => 'success',
    'code' => 200,
    'pesan' => $daftarPesan[$hasil['asal']],
    'data' => potongRiwayatGrafik($hasil['data'])
]);
