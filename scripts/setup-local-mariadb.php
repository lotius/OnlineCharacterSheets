<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '') {
    fwrite(STDERR, "Usage: php scripts/setup-local-mariadb.php <root-password>\n");
    exit(1);
}

$envPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';
$env = [];

foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim(trim($value), "\"'");
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$database = $env['DB_DATABASE'] ?? '';
$username = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';

if ($database === '' || $username === '') {
    fwrite(STDERR, "Missing DB_DATABASE or DB_USERNAME in .env\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    $argv[1],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$identifier = static fn (string $value): string => '`'.str_replace('`', '``', $value).'`';
$literal = static fn (string $value): string => $pdo->quote($value);

$pdo->exec(
    'CREATE DATABASE IF NOT EXISTS '.$identifier($database)
    .' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
);

foreach (['localhost', '127.0.0.1', '%'] as $fromHost) {
    $account = $literal($username).'@'.$literal($fromHost);

    $pdo->exec('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.$literal($password));
    $pdo->exec('ALTER USER '.$account.' IDENTIFIED BY '.$literal($password));
    $pdo->exec('GRANT ALL PRIVILEGES ON '.$identifier($database).'.* TO '.$account);
}

$pdo->exec('FLUSH PRIVILEGES');

echo "Prepared database {$database} and user {$username}.\n";
