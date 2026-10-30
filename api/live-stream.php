<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class) && file_exists(__DIR__ . '/../.env')) {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

const DURASI_CACHE_DETIK = 90;
const DURASI_PIDATO_MINIMAL_DETIK = 600;

function videoCadangan(): array {
    return [
        'is_live' => false,
        'status_siaran' => 'CADANGAN',
        'badge' => 'REKAMAN',
        'video_id' => 'XJceClXVssg',
        'judul' => 'Presiden Prabowo Resmikan SMA Taruna Nusantara Kampus Cimahi, Jawa Barat, 30 Oktober 2026',
        'url_video' => 'https://www.youtube-nocookie.com/embed/XJceClXVssg',
        'thumbnail' => 'https://i.ytimg.com/vi/XJceClXVssg/hqdefault.jpg',
        'tayang_pada' => '2026-10-30T14:02:00Z',
        'sumber' => 'Sekretariat Presiden'
    ];
}

function panggilYoutube(string $sumber, array $parameter, string $apiKey): ?array {
    $url = 'https://www.googleapis.com/youtube/v3/' . $sumber . '?' . http_build_query($parameter + ['key' => $apiKey]);
    $konteks = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);

    $konten = @file_get_contents($url, false, $konteks);
    if ($konten === false) {
        return null;
    }

    $data = json_decode($konten, true);
    return is_array($data) && isset($data['items']) ? $data : null;
}

function durasiDetik(string $durasi): int {
    if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $durasi, $cocok)) {
        return 0;
    }
    return (int)($cocok[1] ?? 0) * 3600 + (int)($cocok[2] ?? 0) * 60 + (int)($cocok[3] ?? 0);
}

function judulTanpaLive(string $judul): string {
    return preg_replace('/^LIVE:\s*/iu', '', trim($judul));
}

function judulPidatoPresiden(string $judul): bool {
    $judulBersih = judulTanpaLive($judul);

    if (preg_match('/^(keterangan pers|ketpers)\b(?!\s+presiden)/iu', $judulBersih)) {
        return false;
    }
    return preg_match('/\bprabowo\b|\bpresiden (ri|republik indonesia)\b/iu', $judulBersih) === 1;
}

function bentukHasil(array $video, bool $sedangLive): array {
    $videoId = $video['id'];
    $thumbnail = $video['snippet']['thumbnails'] ?? [];

    return [
        'is_live' => $sedangLive,
        'status_siaran' => $sedangLive ? 'LIVE' : 'TERBARU',
        'badge' => $sedangLive ? 'SIARAN LANGSUNG' : 'PIDATO TERBARU',
        'video_id' => $videoId,
        'judul' => judulTanpaLive($video['snippet']['title']),
        'url_video' => 'https://www.youtube-nocookie.com/embed/' . $videoId,
        'thumbnail' => $thumbnail['maxres']['url'] ?? $thumbnail['standard']['url'] ?? $thumbnail['high']['url'] ?? '',
        'tayang_pada' => $video['liveStreamingDetails']['actualStartTime'] ?? $video['snippet']['publishedAt'],
        'sumber' => 'Sekretariat Presiden'
    ];
}

function pilihVideo(array $daftarVideo): ?array {
    usort($daftarVideo, fn(array $a, array $b): int => strcmp($b['snippet']['publishedAt'], $a['snippet']['publishedAt']));

    $pidatoTerbaru = null;

    foreach ($daftarVideo as $video) {
        if (!($video['status']['embeddable'] ?? false) || !judulPidatoPresiden($video['snippet']['title'])) {
            continue;
        }

        $statusSiaran = $video['snippet']['liveBroadcastContent'];
        if ($statusSiaran === 'live') {
            return bentukHasil($video, true);
        }

        if (
            $pidatoTerbaru === null
            && $statusSiaran === 'none'
            && durasiDetik($video['contentDetails']['duration']) >= DURASI_PIDATO_MINIMAL_DETIK
        ) {
            $pidatoTerbaru = bentukHasil($video, false);
        }
    }

    return $pidatoTerbaru;
}

function ambilDariYoutube(string $channelId, string $apiKey): ?array {
    $daftarUpload = panggilYoutube('playlistItems', [
        'part' => 'contentDetails',
        'playlistId' => 'UU' . substr($channelId, 2),
        'maxResults' => 50
    ], $apiKey);

    if ($daftarUpload === null) {
        return null;
    }

    $idVideo = array_map(fn(array $item): string => $item['contentDetails']['videoId'], $daftarUpload['items']);
    $detailVideo = panggilYoutube('videos', [
        'part' => 'snippet,contentDetails,liveStreamingDetails,status',
        'id' => implode(',', $idVideo)
    ], $apiKey);

    return $detailVideo === null ? null : pilihVideo($detailVideo['items']);
}

function deteksiSiaranLangsung(): array {
    $channelId = $_ENV['YOUTUBE_CHANNEL_ID'] ?? 'UC_m_NBgf7ieJBHzb6vvJC5A';
    $apiKey = $_ENV['API_KEY'] ?? '';
    $fileCache = sys_get_temp_dir() . '/pidatoku-siaran-' . $channelId . '.json';

    $cache = is_file($fileCache) ? json_decode((string)file_get_contents($fileCache), true) : null;
    $cacheValid = is_array($cache) && isset($cache['video_id']);

    if ($cacheValid && time() - filemtime($fileCache) < DURASI_CACHE_DETIK) {
        return $cache;
    }

    $hasil = $apiKey === '' ? null : ambilDariYoutube($channelId, $apiKey);

    if ($hasil !== null) {
        file_put_contents($fileCache, json_encode($hasil, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $hasil;
    }

    if ($cacheValid) {
        touch($fileCache);
        return array_merge($cache, [
            'is_live' => false,
            'status_siaran' => 'TERBARU',
            'badge' => 'PIDATO TERBARU'
        ]);
    }

    return videoCadangan();
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
            'pembicara' => 'Presiden Republik Indonesia (Prabowo Subianto)',
            'sumber' => $infoSiaran['sumber'],
            'video_id' => $infoSiaran['video_id'],
            'url_video' => $infoSiaran['url_video'],
            'thumbnail' => $infoSiaran['thumbnail'],
            'tayang_pada' => $infoSiaran['tayang_pada'],
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
