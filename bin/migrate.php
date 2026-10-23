<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;
use Noelo\Monitor\Database\Migrator;

$env = new Env(__DIR__ . '/../.env');
$pdo = Connection::fromEnv($env);

$migrator = new Migrator($pdo, __DIR__ . '/../migrations');
$migrator->run();

echo "Migracie uspesne spustene.\n";
