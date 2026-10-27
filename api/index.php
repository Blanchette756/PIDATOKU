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
        'pesan' => 'Method tidak diizinkan. Gunakan GET untuk mengakses endpoint ini.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'code' => 200,
    'pesan' => 'Layanan API Pidatoku aktif',
    'aplikasi' => [
        'nama' => 'Pidatoku',
        'versi' => '1.0.0',
        'php' => PHP_VERSION
    ],
    'endpoint' => [
        'root' => '/api/index.php',
        'health' => '/api/health.php',
        'siaran_langsung' => '/api/live-stream.php'
    ],
    'waktu' => date('c')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
