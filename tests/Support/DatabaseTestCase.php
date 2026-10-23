<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Support;

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;
use Noelo\Monitor\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $env = new Env(dirname(__DIR__, 2) . '/.env');
        $testDbName = $env->require('DB_TEST_NAME');

        $this->pdo = Connection::fromEnv($env, $testDbName);

        (new Migrator($this->pdo, dirname(__DIR__, 2) . '/migrations'))->run();

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('TRUNCATE TABLE payment_matches');
        $this->pdo->exec('TRUNCATE TABLE fio_transactions');
        $this->pdo->exec('TRUNCATE TABLE order_items');
        $this->pdo->exec('TRUNCATE TABLE orders');
        $this->pdo->exec('TRUNCATE TABLE feed_errors');
        $this->pdo->exec('TRUNCATE TABLE reports');
        $this->pdo->exec('TRUNCATE TABLE check_runs');
        $this->pdo->exec('TRUNCATE TABLE reservation_conflict_dismissals');
        $this->pdo->exec('TRUNCATE TABLE payment_reminders_sent');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
