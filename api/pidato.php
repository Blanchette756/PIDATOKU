<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/layanan.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$methodDiizinkan = 'GET, POST, PATCH, DELETE, OPTIONS';

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . $methodDiizinkan);
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

function ambilAngkaQuery(string $nama, int $bawaan, int $minimal, int $maksimal): int
{
    if (!isset($_GET[$nama]) || $_GET[$nama] === '') {
        return $bawaan;
    }

    $nilai = filter_var($_GET[$nama], FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimal, 'max_range' => $maksimal]]);
    if ($nilai === false) {
        kirimError(400, 'Parameter tidak valid', [$nama => "Harus bilangan bulat {$minimal} sampai {$maksimal}"]);
    }
    return $nilai;
}

function ambilIdPidato(): int
{
    if (!isset($_GET['id'])) {
        kirimError(400, 'Parameter id wajib diisi', ['id' => 'Contoh: /api/pidato.php?id=1']);
    }

    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        kirimError(400, 'Parameter tidak valid', ['id' => 'Harus bilangan bulat positif']);
    }
    return $id;
}

function bacaBodyJson(): array
{
    try {
        $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        kirimError(400, 'Format JSON tidak valid');
    }

    if (!is_array($input) || array_is_list($input) && $input !== []) {
        kirimError(400, 'Body JSON harus berupa object');
    }
    return $input;
}

function validasiKategori(mixed $nilai, array &$errors): ?string
{
    if ($nilai === null || $nilai === '') {
        return null;
    }
    if (!is_string($nilai) || !in_array($nilai, DataPidato::DAFTAR_KATEGORI, true)) {
        $errors['kategori'] = 'Kosongkan atau pilih salah satu: ' . implode(', ', DataPidato::DAFTAR_KATEGORI);
        return null;
    }
    return $nilai;
}

function validasiTanggalTayang(mixed $nilai, array &$errors): ?string
{
    if (!is_string($nilai)) {
        $errors['tayang_pada'] = 'Wajib berupa teks tanggal, contoh 2026-10-30 atau 2026-10-30T14:00';
        return null;
    }

    $zona = new DateTimeZone('Asia/Jakarta');
    foreach (['!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d H:i:s', '!Y-m-d'] as $pola) {
        $tanggal = DateTimeImmutable::createFromFormat($pola, trim($nilai), $zona);
        $galat = DateTimeImmutable::getLastErrors();
        if ($tanggal !== false && ($galat === false || $galat['warning_count'] === 0)) {
            if ($tanggal->format('Y-m-d') < SinkronPidato::AWAL_ARSIP || $tanggal > new DateTimeImmutable('now', $zona)) {
                $errors['tayang_pada'] = 'Tanggal harus antara ' . SinkronPidato::AWAL_ARSIP . ' dan hari ini';
                return null;
            }
            return $tanggal->format('Y-m-d H:i:s');
        }
    }

    $errors['tayang_pada'] = 'Format tanggal tidak valid, contoh 2026-10-30 atau 2026-10-30T14:00';
    return null;
}

function daftarPidato(): never
{
    $kataKunci = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($kataKunci) > 100) {
        kirimError(400, 'Parameter tidak valid', ['q' => 'Maksimal 100 karakter']);
    }

    $kategori = trim((string) ($_GET['kategori'] ?? ''));
    if ($kategori !== '' && !in_array($kategori, DataPidato::DAFTAR_KATEGORI, true)) {
        kirimError(400, 'Parameter tidak valid', ['kategori' => 'Pilih salah satu: ' . implode(', ', DataPidato::DAFTAR_KATEGORI)]);
    }

    $urutan = trim((string) ($_GET['urutan'] ?? 'terbaru'));
    if (!in_array($urutan, DataPidato::DAFTAR_URUTAN, true)) {
        kirimError(400, 'Parameter tidak valid', ['urutan' => 'Pilih salah satu: ' . implode(', ', DataPidato::DAFTAR_URUTAN)]);
    }

    $filter = [
        'q' => $kataKunci === '' ? null : $kataKunci,
        'tahun' => isset($_GET['tahun']) && $_GET['tahun'] !== '' ? ambilAngkaQuery('tahun', 0, 2024, (int) date('Y')) : null,
        'kategori' => $kategori === '' ? null : $kategori,
        'urutan' => $urutan,
        'halaman' => ambilAngkaQuery('halaman', 1, 1, 100000),
        'per_halaman' => ambilAngkaQuery('per_halaman', 9, 1, 50)
    ];

    try {
        $dataPidato = dataPidato();
        $hasil = $dataPidato->cariSemua($filter);
        $ringkasan = $dataPidato->ringkasan();
    } catch (Throwable $e) {
        catatError($e, 'daftar pidato');
        kirimError(500, 'Gagal mengambil data pidato');
    }

    kirimJson(200, [
        'status' => 'success',
        'pesan' => 'Data arsip pidato berhasil diambil',
        'filter' => [
            'q' => $filter['q'],
            'tahun' => $filter['tahun'],
            'kategori' => $filter['kategori'],
            'urutan' => $filter['urutan']
        ],
        'halaman' => [
            'sekarang' => $filter['halaman'],
            'per_halaman' => $filter['per_halaman'],
            'total_data' => $hasil['total'],
            'total_halaman' => (int) ceil($hasil['total'] / $filter['per_halaman'])
        ],
        'ringkasan' => $ringkasan,
        'data' => $hasil['data']
    ]);
}

function detailPidato(int $id): never
{
    try {
        $pidato = dataPidato()->cariSatu($id);
    } catch (Throwable $e) {
        catatError($e, 'detail pidato');
        kirimError(500, 'Gagal mengambil data pidato');
    }

    if ($pidato === null) {
        kirimError(404, "Pidato dengan id {$id} tidak ditemukan");
    }

    kirimJson(200, [
        'status' => 'success',
        'pesan' => 'Data pidato berhasil diambil',
        'data' => $pidato
    ]);
}

function tambahPidato(): never
{
    $input = bacaBodyJson();
    $errors = [];

    $tautan = $input['tautan_video'] ?? null;
    $videoId = is_string($tautan) ? SumberYoutube::ambilIdVideo($tautan) : null;
    if ($videoId === null) {
        $errors['tautan_video'] = 'Wajib diisi dengan link YouTube yang valid, contoh https://www.youtube.com/watch?v=XJceClXVssg';
    }

    $kategori = validasiKategori($input['kategori'] ?? null, $errors);

    if ($errors !== []) {
        kirimError(422, 'Data tidak valid', $errors);
    }

    try {
        $dataPidato = dataPidato();
        $idLama = $dataPidato->cariIdDariVideo($videoId);
    } catch (Throwable $e) {
        catatError($e, 'cek video ganda');
        kirimError(500, 'Gagal menyimpan pidato');
    }

    if ($idLama !== null) {
        kirimError(409, "Video ini sudah ada di arsip dengan id {$idLama}", ['tautan_video' => 'Video sudah tersimpan']);
    }

    $youtube = sumberYoutube();
    if (!$youtube->siap()) {
        kirimError(503, 'API_KEY YouTube belum diatur di file .env');
    }

    try {
        $video = $youtube->satuVideo($videoId);
    } catch (RuntimeException $e) {
        catatError($e, 'ambil video youtube');
        kirimError(502, 'Data YouTube sedang tidak dapat diambil, coba lagi nanti');
    }

    if (!$video['ditemukan']) {
        kirimError(422, 'Data tidak valid', ['tautan_video' => 'Video tidak ditemukan di YouTube']);
    }
    if (!$video['siap_ditampilkan']) {
        kirimError(422, 'Data tidak valid', ['tautan_video' => 'Video masih siaran langsung, terjadwal, atau tidak boleh disematkan']);
    }

    $pidato = $video['pidato'] + ['kategori' => $kategori];

    try {
        $dampak = dataPasar()->hitung(substr($pidato['tayang_pada'], 0, 10));
        $idBaru = $dataPidato->tambah($pidato, $dampak);
        $pidatoBaru = $dataPidato->cariSatu($idBaru);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            kirimError(409, 'Video ini sudah ada di arsip', ['tautan_video' => 'Video sudah tersimpan']);
        }
        catatError($e, 'tambah pidato');
        kirimError(500, 'Gagal menyimpan pidato');
    } catch (Throwable $e) {
        catatError($e, 'tambah pidato');
        kirimError(500, 'Gagal menyimpan pidato');
    }

    header('Location: ./pidato.php?id=' . $idBaru);
    kirimJson(201, [
        'status' => 'success',
        'pesan' => 'Pidato berhasil ditambahkan ke arsip',
        'data' => $pidatoBaru
    ]);
}

function ubahPidato(int $id): never
{
    try {
        $dataPidato = dataPidato();
        $pidatoLama = $dataPidato->cariSatu($id);
    } catch (Throwable $e) {
        catatError($e, 'cek pidato sebelum ubah');
        kirimError(500, 'Gagal mengubah pidato');
    }

    if ($pidatoLama === null) {
        kirimError(404, "Pidato dengan id {$id} tidak ditemukan");
    }

    $input = bacaBodyJson();
    $kolomBoleh = ['judul', 'kategori', 'deskripsi', 'tayang_pada'];
    $errors = [];
    $perubahan = [];

    foreach (array_diff(array_keys($input), $kolomBoleh) as $kolomAsing) {
        $errors[$kolomAsing] = 'Kolom ini tidak bisa diubah. Kolom yang boleh: ' . implode(', ', $kolomBoleh);
    }

    if (array_key_exists('judul', $input)) {
        $judul = is_string($input['judul']) ? trim($input['judul']) : '';
        if (mb_strlen($judul) < 5 || mb_strlen($judul) > 255) {
            $errors['judul'] = 'Wajib diisi, 5 sampai 255 karakter';
        } else {
            $perubahan['judul'] = $judul;
        }
    }

    if (array_key_exists('kategori', $input)) {
        $perubahan['kategori'] = validasiKategori($input['kategori'], $errors);
    }

    if (array_key_exists('deskripsi', $input)) {
        $deskripsi = $input['deskripsi'];
        if ($deskripsi !== null && !is_string($deskripsi)) {
            $errors['deskripsi'] = 'Harus berupa teks';
        } elseif (is_string($deskripsi) && mb_strlen(trim($deskripsi)) > 2000) {
            $errors['deskripsi'] = 'Maksimal 2000 karakter';
        } else {
            $perubahan['deskripsi'] = is_string($deskripsi) && trim($deskripsi) !== '' ? trim($deskripsi) : null;
        }
    }

    if (array_key_exists('tayang_pada', $input)) {
        $tayangPada = validasiTanggalTayang($input['tayang_pada'], $errors);
        if ($tayangPada !== null) {
            $perubahan['tayang_pada'] = $tayangPada;
        }
    }

    if ($errors === [] && $perubahan === []) {
        $errors['body'] = 'Kirim minimal satu kolom: ' . implode(', ', $kolomBoleh);
    }

    if ($errors !== []) {
        kirimError(422, 'Data tidak valid', $errors);
    }

    $tanggalBaru = isset($perubahan['tayang_pada']) ? substr($perubahan['tayang_pada'], 0, 10) : null;
    $tanggalBerubah = $tanggalBaru !== null && $tanggalBaru !== substr($pidatoLama['tayang_pada'], 0, 10);

    try {
        $dampak = $tanggalBerubah ? dataPasar()->hitung($tanggalBaru) : null;
        $dataPidato->ubah($id, $perubahan, $dampak);
        $pidatoBaru = $dataPidato->cariSatu($id);
    } catch (Throwable $e) {
        catatError($e, 'ubah pidato');
        kirimError(500, 'Gagal mengubah pidato');
    }

    kirimJson(200, [
        'status' => 'success',
        'pesan' => $tanggalBerubah
            ? 'Pidato berhasil diubah dan dampak pasar dihitung ulang'
            : 'Pidato berhasil diubah',
        'data' => $pidatoBaru
    ]);
}

function hapusPidato(int $id): never
{
    try {
        $terhapus = dataPidato()->hapus($id);
    } catch (Throwable $e) {
        catatError($e, 'hapus pidato');
        kirimError(500, 'Gagal menghapus pidato');
    }

    if (!$terhapus) {
        kirimError(404, "Pidato dengan id {$id} tidak ditemukan");
    }

    http_response_code(204);
    exit;
}

match ($method) {
    'GET' => isset($_GET['id']) ? detailPidato(ambilIdPidato()) : daftarPidato(),
    'POST' => tambahPidato(),
    'PATCH' => ubahPidato(ambilIdPidato()),
    'DELETE' => hapusPidato(ambilIdPidato()),
    default => (function () use ($methodDiizinkan): never {
        header('Allow: ' . $methodDiizinkan);
        kirimError(405, 'Method tidak diizinkan. Gunakan GET, POST, PATCH, atau DELETE.');
    })()
};
