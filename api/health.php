<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

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
        'pesan' => 'Method tidak diizinkan. Gunakan GET untuk memeriksa status server.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$database = ['terhubung' => false];

try {
    require_once __DIR__ . '/../config/database.php';

    $info = db()->query('SELECT DATABASE() AS nama_db, VERSION() AS versi')->fetch();
    $database = [
        'terhubung' => true,
        'driver' => db()->getAttribute(PDO::ATTR_DRIVER_NAME),
        'nama_db' => $info['nama_db'],
        'versi_mysql' => $info['versi']
    ];
} catch (Throwable $e) {
    $database['pesan'] = ($_ENV['APP_DEBUG'] ?? 'false') === 'true'
        ? $e->getMessage()
        : 'Koneksi basis data gagal';
}

$sehat = $database['terhubung'];

http_response_code($sehat ? 200 : 503);
echo json_encode([
    'status' => $sehat ? 'ok' : 'error',
    'code' => $sehat ? 200 : 503,
    'aplikasi' => 'Pidatoku',
    'layanan' => 'Pusat Pemantau Pidato Kenegaraan & Sentimen Finansial',
    'kondisi' => $sehat ? 'Server dan basis data beroperasi normal' : 'Basis data tidak dapat dihubungi',
    'php' => PHP_VERSION,
    'database' => $database,
    'waktu' => date('c')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;

