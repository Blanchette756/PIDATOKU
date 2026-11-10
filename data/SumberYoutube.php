<?php
declare(strict_types=1);

final class SumberYoutube
{
    private const URL_API = 'https://www.googleapis.com/youtube/v3/';
    private const DURASI_PIDATO_MINIMAL_DETIK = 600;
    private const PANJANG_DESKRIPSI_MAKS = 500;
    private const BULAN = [
        'januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6, 'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'ags' => 8, 'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10, 'november' => 11, 'nov' => 11, 'desember' => 12, 'des' => 12
    ];

    public function __construct(private string $apiKey, private string $channelId)
    {
    }

    public function siap(): bool
    {
        return $this->apiKey !== '' && str_starts_with($this->channelId, 'UC');
    }

    public static function ambilIdVideo(string $tautan): ?string
    {
        $tautan = trim($tautan);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $tautan)) {
            return $tautan;
        }

        $pola = '~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|live/|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~';
        return preg_match($pola, $tautan, $cocok) ? $cocok[1] : null;
    }

    public function halamanUpload(?string $tokenHalaman): ?array
    {
        $parameter = [
            'part' => 'contentDetails',
            'playlistId' => 'UU' . substr($this->channelId, 2),
            'maxResults' => 50
        ];
        if ($tokenHalaman !== null) {
            $parameter['pageToken'] = $tokenHalaman;
        }

        $data = $this->panggil('playlistItems', $parameter);
        if ($data === null) {
            return null;
        }

        $daftarId = [];
        $terbitTertua = null;
        foreach ($data['items'] as $item) {
            $daftarId[] = $item['contentDetails']['videoId'];
            $terbit = $item['contentDetails']['videoPublishedAt'] ?? null;
            if ($terbit !== null && ($terbitTertua === null || $terbit < $terbitTertua)) {
                $terbitTertua = $terbit;
            }
        }

        return [
            'video_id' => $daftarId,
            'token_berikutnya' => $data['nextPageToken'] ?? null,
            'terbit_tertua' => $terbitTertua
        ];
    }

    public function detailVideo(array $daftarId): ?array
    {
        if ($daftarId === []) {
            return [];
        }

        $data = $this->panggil('videos', [
            'part' => 'snippet,contentDetails,liveStreamingDetails,status',
            'id' => implode(',', $daftarId)
        ]);

        return $data === null ? null : $data['items'];
    }

    public function satuVideo(string $videoId): array
    {
        $daftar = $this->detailVideo([$videoId]);

        if ($daftar === null) {
            throw new RuntimeException('YouTube tidak dapat dihubungi');
        }
        if ($daftar === []) {
            return ['ditemukan' => false];
        }

        $video = $daftar[0];
        $siapDitampilkan = ($video['status']['embeddable'] ?? false)
            && ($video['snippet']['liveBroadcastContent'] ?? '') === 'none';

        return [
            'ditemukan' => true,
            'siap_ditampilkan' => $siapDitampilkan,
            'pidato' => $this->bentukPidato($video)
        ];
    }

    public function saringPidato(array $video): ?array
    {
        $judul = $video['snippet']['title'] ?? '';

        if (
            !($video['status']['embeddable'] ?? false)
            || ($video['snippet']['liveBroadcastContent'] ?? '') !== 'none'
            || !self::judulPidatoPresiden($judul)
            || self::durasiDetik($video['contentDetails']['duration'] ?? '') < self::DURASI_PIDATO_MINIMAL_DETIK
        ) {
            return null;
        }

        return $this->bentukPidato($video);
    }

    private function bentukPidato(array $video): array
    {
        $snippet = $video['snippet'];
        $thumbnail = $snippet['thumbnails'] ?? [];
        $judul = self::judulTanpaLive($snippet['title']);

        return [
            'video_id' => $video['id'],
            'judul' => mb_substr($judul, 0, 255),
            'deskripsi' => self::ringkasDeskripsi($snippet['description'] ?? ''),
            'thumbnail' => $thumbnail['maxres']['url'] ?? $thumbnail['standard']['url'] ?? $thumbnail['high']['url'] ?? null,
            'durasi_detik' => self::durasiDetik($video['contentDetails']['duration'] ?? '') ?: null,
            'tayang_pada' => self::tentukanWaktuTayang(
                $judul,
                $video['liveStreamingDetails']['actualStartTime'] ?? $snippet['publishedAt']
            )
        ];
    }

    private function panggil(string $sumber, array $parameter): ?array
    {
        $url = self::URL_API . $sumber . '?' . http_build_query($parameter + ['key' => $this->apiKey]);
        $konteks = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);

        $konten = @file_get_contents($url, false, $konteks);
        if ($konten === false) {
            return null;
        }

        $data = json_decode($konten, true);
        return is_array($data) && isset($data['items']) ? $data : null;
    }

    private static function judulTanpaLive(string $judul): string
    {
        return preg_replace('/^LIVE:\s*/iu', '', trim($judul));
    }

    private static function judulPidatoPresiden(string $judul): bool
    {
        $judulBersih = self::judulTanpaLive($judul);

        if (preg_match('/^(keterangan pers|ketpers)\b(?!\s+presiden)/iu', $judulBersih)) {
            return false;
        }
        return preg_match('/\bprabowo\b|\bpresiden (ri|republik indonesia)\b/iu', $judulBersih) === 1;
    }

    private static function durasiDetik(string $durasi): int
    {
        if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $durasi, $cocok)) {
            return 0;
        }
        return (int) ($cocok[1] ?? 0) * 3600 + (int) ($cocok[2] ?? 0) * 60 + (int) ($cocok[3] ?? 0);
    }

    private static function ringkasDeskripsi(string $deskripsi): ?string
    {
        $paragraf = trim((string) preg_split('/\R\s*\R/u', trim($deskripsi))[0]);
        $paragraf = trim((string) preg_replace('/\s+/u', ' ', $paragraf));

        if ($paragraf === '') {
            return null;
        }
        if (mb_strlen($paragraf) <= self::PANJANG_DESKRIPSI_MAKS) {
            return $paragraf;
        }
        return rtrim(mb_substr($paragraf, 0, self::PANJANG_DESKRIPSI_MAKS - 1)) . '…';
    }

    private static function tentukanWaktuTayang(string $judul, string $waktuYoutube): string
    {
        $zonaJakarta = new DateTimeZone('Asia/Jakarta');
        $waktu = (new DateTimeImmutable($waktuYoutube))->setTimezone($zonaJakarta);
        $tanggalJudul = self::tanggalDariJudul($judul);

        if ($tanggalJudul === null || $tanggalJudul === $waktu->format('Y-m-d')) {
            return $waktu->format('Y-m-d H:i:s');
        }

        return $tanggalJudul . ' 00:00:00';
    }

    private static function tanggalDariJudul(string $judul): ?string
    {
        $namaBulan = implode('|', array_keys(self::BULAN));

        if (preg_match('/\b(\d{1,2})\s+(' . $namaBulan . ')\.?\s+(\d{4})\b/iu', $judul, $cocok)) {
            $hari = (int) $cocok[1];
            $bulan = self::BULAN[strtolower($cocok[2])];
            $tahun = (int) $cocok[3];
        } elseif (preg_match('/\b(\d{1,2})-(\d{1,2})-(\d{4})\b/', $judul, $cocok)) {
            $hari = (int) $cocok[1];
            $bulan = (int) $cocok[2];
            $tahun = (int) $cocok[3];
        } else {
            return null;
        }

        return checkdate($bulan, $hari, $tahun) ? sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari) : null;
    }
}
