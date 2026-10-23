<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use PDO;

final class CheckRunRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function start(string $triggeredBy, ?string $triggeredByUser = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO check_runs (started_at, triggered_by, triggered_by_user) VALUES (:started_at, :triggered_by, :triggered_by_user)'
        );
        $stmt->execute([
            'started_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'triggered_by' => $triggeredBy,
            'triggered_by_user' => $triggeredByUser,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function markFeedStatus(int $checkRunId, string $feedColumn, string $status): void
    {
        $allowedColumns = ['order_feed_status', 'stock_feed_status', 'fio_status'];
        if (!in_array($feedColumn, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Neznamy stlpec stavu feedu: {$feedColumn}");
        }

        $stmt = $this->pdo->prepare("UPDATE check_runs SET {$feedColumn} = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $checkRunId]);
    }

    public function finish(int $checkRunId): void
    {
        $stmt = $this->pdo->prepare('UPDATE check_runs SET finished_at = :finished_at WHERE id = :id');
        $stmt->execute([
            'finished_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $checkRunId,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findLatest(): ?array
    {
        $row = $this->pdo->query('SELECT * FROM check_runs ORDER BY id DESC LIMIT 1')->fetch();

        return $row === false ? null : $row;
    }
}
