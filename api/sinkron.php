<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/layanan.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    header('Allow: POST, OPTIONS');
    kirimError(405, 'Method tidak diizinkan. Gunakan POST.');
}

$youtube = sumberYoutube();
if (!$youtube->siap()) {
    kirimError(503, 'API_KEY YouTube belum diatur di file .env');
}

try {
    $hasil = sinkronPidato()->jalankan();
} catch (PDOException $e) {
    catatError($e, 'sinkron arsip');
    kirimError(500, 'Gagal menyimpan arsip pidato');
} catch (RuntimeException $e) {
    catatError($e, 'sinkron arsip');
    kirimError(502, 'Data YouTube sedang tidak dapat diambil, coba lagi nanti');
} catch (Throwable $e) {
    catatError($e, 'sinkron arsip');
    kirimError(500, 'Terjadi kesalahan pada server');
}

kirimJson(200, [
    'status' => 'success',
    'pesan' => 'Sinkronisasi arsip pidato selesai',
    'data' => $hasil
]);
