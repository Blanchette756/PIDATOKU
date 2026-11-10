<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once dirname(__DIR__) . '/data/DataPidato.php';
require_once dirname(__DIR__) . '/data/DataPasar.php';
require_once dirname(__DIR__) . '/data/SumberIhsg.php';
require_once dirname(__DIR__) . '/data/SumberYoutube.php';
require_once dirname(__DIR__) . '/data/SinkronPidato.php';

date_default_timezone_set('Asia/Jakarta');

function dataPidato(): DataPidato
{
    return new DataPidato(db());
}

function sumberYoutube(): SumberYoutube
{
    return new SumberYoutube(
        trim((string) ($_ENV['API_KEY'] ?? '')),
        trim((string) ($_ENV['YOUTUBE_CHANNEL_ID'] ?? '')) ?: 'UC_m_NBgf7ieJBHzb6vvJC5A'
    );
}

function dataPasar(): DataPasar
{
    return new DataPasar(new SumberIhsg(trim((string) ($_ENV['GOOGLE_SHEET_IHSG_CSV'] ?? ''))));
}

function sinkronPidato(): SinkronPidato
{
    return new SinkronPidato(
        dataPidato(),
        sumberYoutube(),
        dataPasar(),
        sys_get_temp_dir() . '/pidatoku-sinkron-arsip.json'
    );
}

function kirimJson(int $kode, array $isi): never
{
    http_response_code($kode);
    echo json_encode(['code' => $kode] + $isi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function kirimError(int $kode, string $pesan, array $aturan = []): never
{
    $isi = ['status' => 'error', 'pesan' => $pesan];
    if ($aturan !== []) {
        $isi['aturan'] = $aturan;
    }
    kirimJson($kode, $isi);
}

function catatError(Throwable $e, string $konteks): void
{
    error_log(sprintf('[pidatoku] %s: %s di %s:%d', $konteks, $e->getMessage(), $e->getFile(), $e->getLine()));
}
