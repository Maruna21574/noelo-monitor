<?php

declare(strict_types=1);

/**
 * Vytvori/aktualizuje admin ucet pre prihlasenie do administracie.
 * Pouzitie: php bin/create-admin-user.php <username> <heslo>
 *
 * Spusti lokalne na serveri - heslo ako argument prikazoveho riadku sa moze
 * ulozit do shell historie, preto po pouziti zvaz vycistenie historie.
 */

require __DIR__ . '/../vendor/autoload.php';

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;

if (count($argv) !== 3) {
    fwrite(STDERR, "Pouzitie: php bin/create-admin-user.php <username> <heslo>\n");
    exit(1);
}

[, $username, $password] = $argv;

if (strlen($password) < 10) {
    fwrite(STDERR, "Heslo musi mat aspon 10 znakov.\n");
    exit(1);
}

$env = new Env(__DIR__ . '/../.env');
$pdo = Connection::fromEnv($env);

$stmt = $pdo->prepare(
    'INSERT INTO admin_users (username, password_hash, created_at)
     VALUES (:username, :hash, :created_at)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
);
$stmt->execute([
    'username' => $username,
    'hash' => password_hash($password, PASSWORD_DEFAULT),
    'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
]);

echo "Admin ucet '{$username}' bol vytvoreny/aktualizovany.\n";
