<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Jalankan lewat terminal: php database/setup.php\n");
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

echo "Menyiapkan database toko_osing_db...\n";

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$pdo->exec('DROP DATABASE IF EXISTS toko_osing_db');

function runSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Tidak dapat membaca ' . $file);
    }

    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

runSqlFile($pdo, __DIR__ . '/schema.sql');
runSqlFile($pdo, __DIR__ . '/seed.sql');

$pdo->exec('USE toko_osing_db');

$counts = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'sellers' => (int) $pdo->query('SELECT COUNT(*) FROM sellers')->fetchColumn(),
    'categories' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
];

echo "Selesai.\n";
foreach ($counts as $table => $count) {
    echo sprintf("  %-11s %d baris\n", $table, $count);
}
echo "\nAkun demo:\n";
echo "  admin  : admin@tokoosing.test / admin123\n";
echo "  seller : batik.kemiren@tokoosing.test / seller123 (dan akun seller lainnya)\n";
echo "  buyer  : buyer@tokoosing.test / buyer123\n";

