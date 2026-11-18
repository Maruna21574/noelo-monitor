<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use PDO;

/** Rucne poznamky timu k objednavkam (napr. v akom stave je overenie platby). */
final class OrderNoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int, array{note: string, updated_by: string, updated_at: string}> indexovane podla order_id */
    public function findAll(): array
    {
        $result = [];
        foreach ($this->pdo->query('SELECT order_id, note, updated_by, updated_at FROM order_notes')->fetchAll() as $row) {
            $result[(int) $row['order_id']] = [
                'note' => (string) $row['note'],
                'updated_by' => (string) $row['updated_by'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }

        return $result;
    }

    /** Prazdna poznamka zaznam zmaze. */
    public function save(int $orderId, string $note, string $updatedBy): void
    {
        $note = trim(mb_substr($note, 0, 500));

        if ($note === '') {
            $stmt = $this->pdo->prepare('DELETE FROM order_notes WHERE order_id = :id');
            $stmt->execute(['id' => $orderId]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO order_notes (order_id, note, updated_by, updated_at)
             VALUES (:id, :note, :by, :at)
             ON DUPLICATE KEY UPDATE note = VALUES(note), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $orderId,
            'note' => $note,
            'by' => $updatedBy,
            'at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
