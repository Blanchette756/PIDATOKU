<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../data/DataMasukan.php';

function kirimJson(int $kode, array $isi): never
{
    http_response_code($kode);
    echo json_encode(['code' => $kode] + $isi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

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
    kirimJson(405, [
        'status' => 'error',
        'pesan' => 'Method tidak diizinkan. Gunakan POST.'
    ]);
}

try {
    $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    kirimJson(400, [
        'status' => 'error',
        'pesan' => 'Format JSON tidak valid'
    ]);
}

if (!is_array($input)) {
    kirimJson(400, [
        'status' => 'error',
        'pesan' => 'Body JSON harus berupa object'
    ]);
}

$namaLengkap = trim((string) ($input['nama_lengkap'] ?? ''));
$email = strtolower(trim((string) ($input['email'] ?? '')));
$kategori = trim((string) ($input['kategori'] ?? ''));
$detailPesan = trim((string) ($input['detail_pesan'] ?? ''));

$daftarKategori = ['fitur', 'bug', 'kerjasama'];
$errors = [];

if (mb_strlen($namaLengkap) < 3 || mb_strlen($namaLengkap) > 100) {
    $errors['nama_lengkap'] = 'Wajib diisi, 3 sampai 100 karakter';
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
    $errors['email'] = 'Wajib diisi dengan format email yang valid';
}
if (!in_array($kategori, $daftarKategori, true)) {
    $errors['kategori'] = 'Pilih salah satu: ' . implode(', ', $daftarKategori);
}
if (mb_strlen($detailPesan) < 10) {
    $errors['detail_pesan'] = 'Wajib diisi, minimal 10 karakter';
}

if ($errors !== []) {
    kirimJson(422, [
        'status' => 'error',
        'pesan' => 'Data tidak valid',
        'aturan' => $errors
    ]);
}

try {
    $dataMasukan = new DataMasukan(db());
    $idBaru = $dataMasukan->simpan($namaLengkap, $email, $kategori, $detailPesan);
} catch (Throwable $e) {
    kirimJson(500, [
        'status' => 'error',
        'pesan' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getMessage() : 'Gagal menyimpan masukan'
    ]);
}

kirimJson(201, [
    'status' => 'success',
    'pesan' => 'Masukan berhasil disimpan',
    'data' => [
        'id' => $idBaru,
        'nama_lengkap' => $namaLengkap,
        'email' => $email,
        'kategori' => $kategori,
        'detail_pesan' => $detailPesan
    ]
]);
