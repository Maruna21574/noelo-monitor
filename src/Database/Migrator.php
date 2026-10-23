<?php

declare(strict_types=1);

namespace Noelo\Monitor\Database;

use PDO;

final class Migrator
{
    public function __construct(private readonly PDO $pdo, private readonly string $migrationsPath)
    {
    }

    public function run(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->appliedMigrations();

        $files = glob(rtrim($this->migrationsPath, '/\\') . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Nepodarilo sa precitat migraciu: {$name}");
            }

            try {
                // DDL prikazy (CREATE TABLE a pod.) v MySQL vyvolavaju implicitny commit,
                // preto sa DDL migracie nedaju obalit transakciou.
                $this->pdo->exec($sql);
                $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (:name, :applied_at)');
                $stmt->execute([
                    'name' => $name,
                    'applied_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Migracia zlyhala: {$name}: " . $e->getMessage(), previous: $e);
            }
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return string[] */
    private function appliedMigrations(): array
    {
        $stmt = $this->pdo->query('SELECT migration FROM schema_migrations');
        return $stmt !== false ? array_column($stmt->fetchAll(), 'migration') : [];
    }
}
