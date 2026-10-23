<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{id: int, status: string}|null */
    public function findByDate(\DateTimeImmutable $runDate): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, status FROM reports WHERE run_date = :run_date');
        $stmt->execute(['run_date' => $runDate->format('Y-m-d')]);
        $row = $stmt->fetch();

        return $row === false ? null : ['id' => (int) $row['id'], 'status' => $row['status']];
    }

    public function createPending(\DateTimeImmutable $runDate, int $checkRunId, string $recipients): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (run_date, check_run_id, generated_at, status, recipients)
             VALUES (:run_date, :check_run_id, :generated_at, "pending", :recipients)'
        );
        $stmt->execute([
            'run_date' => $runDate->format('Y-m-d'),
            'check_run_id' => $checkRunId,
            'generated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'recipients' => $recipients,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function markSent(int $reportId): void
    {
        $stmt = $this->pdo->prepare('UPDATE reports SET status = "sent", sent_at = :sent_at WHERE id = :id');
        $stmt->execute(['sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'id' => $reportId]);
    }

    public function markFailed(int $reportId): void
    {
        $stmt = $this->pdo->prepare('UPDATE reports SET status = "failed" WHERE id = :id');
        $stmt->execute(['id' => $reportId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function findRecentHistory(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reports ORDER BY run_date DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
