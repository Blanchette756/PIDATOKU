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

http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'code' => 200,
    'aplikasi' => 'Pidatoku',
    'layanan' => 'Pusat Pemantau Pidato Kenegaraan & Sentimen Finansial',
    'kondisi' => 'Server beroperasi normal',
    'php' => PHP_VERSION,
    'waktu' => date('c')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;

