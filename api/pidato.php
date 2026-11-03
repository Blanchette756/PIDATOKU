<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../data/DataPidato.php';

function kirimJson(int $kode, array $isi): never
{
    http_response_code($kode);
    echo json_encode(['code' => $kode] + $isi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
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
    kirimJson(405, [
        'status' => 'error',
        'pesan' => 'Method tidak diizinkan. Gunakan GET.'
    ]);
}

$daftarKategori = ['Ekonomi', 'Geopolitik', 'Sosial', 'Lainnya'];
$kategori = isset($_GET['kategori']) ? trim((string) $_GET['kategori']) : '';

if ($kategori !== '' && !in_array($kategori, $daftarKategori, true)) {
    kirimJson(422, [
        'status' => 'error',
        'pesan' => 'Kategori tidak valid',
        'aturan' => ['kategori' => 'Pilih salah satu: ' . implode(', ', $daftarKategori)]
    ]);
}

try {
    $dataPidato = new DataPidato(db());
    $daftarPidato = $dataPidato->cariSemua($kategori === '' ? null : $kategori);
} catch (Throwable $e) {
    kirimJson(500, [
        'status' => 'error',
        'pesan' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getMessage() : 'Gagal mengambil data pidato'
    ]);
}

kirimJson(200, [
    'status' => 'success',
    'pesan' => 'Data arsip pidato berhasil diambil',
    'filter' => ['kategori' => $kategori === '' ? null : $kategori],
    'jumlah' => count($daftarPidato),
    'data' => $daftarPidato
]);
