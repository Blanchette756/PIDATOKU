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

function deteksiSiaranLangsung(): array {
    $cadanganId = 'V5dWnSPEruY';
    $cadanganJudul = 'Pidato Presiden Prabowo Subianto: Sidang Kabinet Paripurna 2026';
    $channelId = 'UC_m_NBgf7ieJBHzb6vvJC5A';

    $hasil = [
        'is_live' => false,
        'status_siaran' => 'REKAMAN',
        'badge' => 'REKAMAN TERKINI',
        'video_id' => $cadanganId,
        'judul' => $cadanganJudul,
        'url_video' => 'https://www.youtube-nocookie.com/embed/' . $cadanganId,
        'topik' => 'Evaluasi Kinerja Pemerintah & Ketahanan Ekonomi Nasional',
        'sumber' => 'Kompas TV / Sekretariat Presiden'
    ];

    $konteks = stream_context_create([
        'http' => [
            'timeout' => 3,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);

    $konten = @file_get_contents('https://www.youtube.com/channel/' . $channelId . '/live', false, $konteks);

    if ($konten !== false) {
        $sedangLive = str_contains($konten, '"isLive":true') || str_contains($konten, '"status":"LIVE"');
        if ($sedangLive) {
            if (preg_match('/"videoId":"([a-zA-Z0-9_-]{11})"/', $konten, $cocokVideo)) {
                $hasil['is_live'] = true;
                $hasil['status_siaran'] = 'LIVE';
                $hasil['badge'] = 'SIARAN LANGSUNG';
                $hasil['video_id'] = $cocokVideo[1];
                $hasil['url_video'] = 'https://www.youtube-nocookie.com/embed/' . $cocokVideo[1];
                $hasil['sumber'] = 'Sekretariat Presiden (Live Stream)';
                $hasil['topik'] = 'Pidato Kenegaraan / Arahan Presiden Terkini';

                if (preg_match('/<title>(.*?) - YouTube<\/title>/', $konten, $cocokJudul)) {
                    $hasil['judul'] = html_entity_decode(trim($cocokJudul[1]), ENT_QUOTES, 'UTF-8');
                } else {
                    $hasil['judul'] = 'Siaran Langsung Pidato Presiden Republik Indonesia';
                }
            }
        }
    }

    return $hasil;
}

if ($method === 'GET') {
    $infoSiaran = deteksiSiaranLangsung();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'code' => 200,
        'pesan' => 'Data siaran berhasil diperbarui secara otomatis',
        'data' => [
            'id' => 'stream-presiden',
            'is_live' => $infoSiaran['is_live'],
            'status_siaran' => $infoSiaran['status_siaran'],
            'badge' => $infoSiaran['badge'],
            'judul' => $infoSiaran['judul'],
            'topik' => $infoSiaran['topik'],
            'pembicara' => 'Presiden Republik Indonesia (Prabowo Subianto)',
            'lokasi' => 'Istana Negara, Jakarta',
            'sumber' => $infoSiaran['sumber'],
            'format' => 'Video HD 1080p',
            'video_id' => $infoSiaran['video_id'],
            'url_video' => $infoSiaran['url_video'],
            'waktu_cek' => date('c'),
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
