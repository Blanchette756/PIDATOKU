<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';

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

$sql = 'SELECT p.id, p.video_id, p.judul, p.topik, p.kategori, p.deskripsi, p.pembicara, p.sumber,
               p.thumbnail, p.tayang_pada,
               d.indikator, d.kode_mata_uang, d.perubahan, d.satuan, d.status_pasar, d.dicatat_pada
        FROM pidato p
        LEFT JOIN dampak_pasar d ON d.pidato_id = p.id';

try {
    if ($kategori !== '') {
        $stmt = db()->prepare($sql . ' WHERE p.kategori = :kategori ORDER BY p.tayang_pada DESC, d.id ASC');
        $stmt->execute(['kategori' => $kategori]);
    } else {
        $stmt = db()->prepare($sql . ' ORDER BY p.tayang_pada DESC, d.id ASC');
        $stmt->execute();
    }
    $baris = $stmt->fetchAll();
} catch (Throwable $e) {
    kirimJson(500, [
        'status' => 'error',
        'pesan' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true' ? $e->getMessage() : 'Gagal mengambil data pidato'
    ]);
}

$daftarPidato = [];

foreach ($baris as $b) {
    $id = (int) $b['id'];

    if (!isset($daftarPidato[$id])) {
        $daftarPidato[$id] = [
            'id' => $id,
            'video_id' => $b['video_id'],
            'judul' => $b['judul'],
            'topik' => $b['topik'],
            'kategori' => $b['kategori'],
            'deskripsi' => $b['deskripsi'],
            'pembicara' => $b['pembicara'],
            'sumber' => $b['sumber'],
            'thumbnail' => $b['thumbnail'],
            'tayang_pada' => $b['tayang_pada'],
            'dampak_pasar' => []
        ];
    }

    if ($b['indikator'] !== null) {
        $daftarPidato[$id]['dampak_pasar'][] = [
            'indikator' => $b['indikator'],
            'kode_mata_uang' => $b['kode_mata_uang'],
            'perubahan' => $b['perubahan'] === null ? null : (float) $b['perubahan'],
            'satuan' => $b['satuan'],
            'status_pasar' => $b['status_pasar'],
            'dicatat_pada' => $b['dicatat_pada']
        ];
    }
}

kirimJson(200, [
    'status' => 'success',
    'pesan' => 'Data arsip pidato berhasil diambil',
    'filter' => ['kategori' => $kategori === '' ? null : $kategori],
    'jumlah' => count($daftarPidato),
    'data' => array_values($daftarPidato)
]);
