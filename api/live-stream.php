<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

if ($method === 'GET') {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'code' => 200,
        'pesan' => 'Data siaran langsung berhasil diambil',
        'data' => [
            'id' => 'live-01',
            'status_siaran' => 'LIVE',
            'judul' => 'Siaran Pidato Kenegaraan Istana Negara',
            'topik' => 'RUU APBN 2027 dan Arah Kebijakan Ekonomi Nasional',
            'pembicara' => 'Presiden Republik Indonesia',
            'lokasi' => 'Gedung Nusantara, Kompleks Parlemen, Jakarta',
            'sumber' => 'TVRI / Sekretariat Presiden',
            'format' => 'Video HD 1080p',
            'url_video' => 'https://www.youtube-nocookie.com/embed/live_stream',
            'waktu_mulai' => date('Y-m-d') . 'T09:00:00+07:00',
            'jumlah_pemirsa' => 14820,
            'indikator_pasar' => [
                'pantau_ihsg' => true,
                'pantau_kurs' => true
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($method === 'POST') {
    try {
        $input = json_decode(
            file_get_contents('php://input'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'code' => 400,
            'pesan' => 'Format JSON tidak valid'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'code' => 400,
            'pesan' => 'Body JSON harus berupa object'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $nama = trim((string)($input['nama'] ?? ''));
    $catatan = trim((string)($input['catatan'] ?? ''));
    $sentimen = strtolower(trim((string)($input['sentimen'] ?? '')));

    $daftarSentimen = ['positif', 'netral', 'negatif'];
    $sentimenValid = in_array($sentimen, $daftarSentimen, true);

    if ($nama === '' || mb_strlen($nama) > 100 || $catatan === '' || !$sentimenValid) {
        http_response_code(422);
        echo json_encode([
            'status' => 'error',
            'code' => 422,
            'pesan' => 'Data tidak valid',
            'aturan' => [
                'nama' => 'Wajib diisi dan maksimal 100 karakter',
                'catatan' => 'Wajib diisi',
                'sentimen' => 'Wajib dipilih antara positif, netral, atau negatif'
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'code' => 201,
        'pesan' => 'Catatan sentimen pidato berhasil disimpan',
        'data' => [
            'id' => random_int(100, 999),
            'nama' => $nama,
            'catatan' => $catatan,
            'sentimen' => $sentimen,
            'disimpan_pada' => date('c')
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

header('Allow: GET, POST, OPTIONS');
http_response_code(405);
echo json_encode([
    'status' => 'error',
    'code' => 405,
    'pesan' => 'Method tidak diizinkan. Gunakan GET atau POST.'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;

