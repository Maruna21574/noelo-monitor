<?php

declare(strict_types=1);

namespace Noelo\Monitor\Database;

use Noelo\Monitor\Config\Env;
use PDO;

final class Connection
{
    public static function fromEnv(Env $env, ?string $databaseNameOverride = null): PDO
    {
        $host = $env->require('DB_HOST');
        $port = $env->getInt('DB_PORT', 3306);
        $name = $databaseNameOverride ?? $env->require('DB_NAME');
        $user = $env->require('DB_USER');
        $password = $env->get('DB_PASSWORD', '') ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
