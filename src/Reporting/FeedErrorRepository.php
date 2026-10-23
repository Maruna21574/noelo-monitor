<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use PDO;

final class FeedErrorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param string $feedType 'order_feed'|'stock_feed'|'fio_api'
     * @param string $message sanitizovana sprava (bez PII, bez tokenov) - volajuci je zodpovedny za sanitizaciu
     */
    public function record(int $checkRunId, string $feedType, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO feed_errors (check_run_id, feed_type, message, occurred_at) VALUES (:check_run_id, :feed_type, :message, :occurred_at)'
        );
        $stmt->execute([
            'check_run_id' => $checkRunId,
            'feed_type' => $feedType,
            'message' => substr($message, 0, 500),
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int, array{feed_type: string, message: string, occurred_at: string}> */
    public function findByCheckRun(int $checkRunId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT feed_type, message, occurred_at FROM feed_errors WHERE check_run_id = :check_run_id ORDER BY occurred_at'
        );
        $stmt->execute(['check_run_id' => $checkRunId]);

        return $stmt->fetchAll();
    }
}
