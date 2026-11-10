<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/layanan.php';

const HARI_BURSA_SEBELUM = 30;
const HARI_BURSA_SESUDAH = 10;

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
    kirimError(405, 'Method tidak diizinkan. Gunakan GET.');
}

$tanggal = trim((string) ($_GET['tanggal'] ?? ''));
$objekTanggal = DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal, new DateTimeZone('Asia/Jakarta'));
$galat = DateTimeImmutable::getLastErrors();

if ($objekTanggal === false || ($galat !== false && $galat['warning_count'] > 0)) {
    kirimError(400, 'Parameter tidak valid', ['tanggal' => 'Wajib diisi dengan format YYYY-MM-DD, contoh 2026-10-30']);
}

try {
    $data = dataPasar()->sekitarTanggal($objekTanggal->format('Y-m-d'), HARI_BURSA_SEBELUM, HARI_BURSA_SESUDAH);
} catch (Throwable $e) {
    catatError($e, 'data pasar per tanggal');
    kirimError(500, 'Gagal mengambil data pasar');
}

kirimJson(200, [
    'status' => 'success',
    'pesan' => 'Data pasar di sekitar tanggal pidato berhasil diambil',
    'sumber' => ['ihsg' => 'Google Finance melalui Google Sheets', 'kurs' => 'Frankfurter (kurs acuan ECB)'],
    'data' => $data
]);
