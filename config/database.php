<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load(); 

function envWajib(string $kunci): string
{
    $nilai = $_ENV[$kunci] ?? '';
    if ($nilai === '') {
        throw new RuntimeException("Konfigurasi {$kunci} belum diisi pada .env");
    }
    return $nilai;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = envWajib('DB_HOST');
    $port = envWajib('DB_PORT');
    $nama = envWajib('DB_NAME');
    $user = envWajib('DB_USER');

    if (!array_key_exists('DB_PASS', $_ENV)) {
        throw new RuntimeException('Konfigurasi DB_PASS belum didefinisikan pada .env');
    }
    $pass = (string) $_ENV['DB_PASS'];

    $dsn = "mysql:host={$host};port={$port};dbname={$nama};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      
        PDO::ATTR_EMULATE_PREPARES   => false,                  
    ]);

    return $pdo;
}
