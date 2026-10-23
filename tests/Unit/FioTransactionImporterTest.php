<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Fio\Dto\FioTransactionDto;
use Noelo\Monitor\Fio\FioTransactionImporter;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class FioTransactionImporterTest extends DatabaseTestCase
{
    private function transaction(int $id, int $amountCents = 4425, ?string $vs = '3014234346'): FioTransactionDto
    {
        return new FioTransactionDto(
            id: $id,
            date: new \DateTimeImmutable('2026-09-10'),
            amountCents: $amountCents,
            currency: 'EUR',
            variableSymbol: $vs,
            counterAccountName: 'Test Zakaznik',
            messageForRecipient: null,
            userIdentification: null,
            comment: null,
        );
    }

    public function testImportInsertsNewTransactionAsUnmatched(): void
    {
        (new FioTransactionImporter($this->pdo))->import([$this->transaction(1)]);

        $row = $this->pdo->query('SELECT match_status, amount_cents FROM fio_transactions WHERE id = 1')->fetch();
        self::assertSame('unmatched', $row['match_status']);
        self::assertSame(4425, (int) $row['amount_cents']);
    }

    public function testReimportIsIdempotentAndDoesNotDuplicateRows(): void
    {
        $importer = new FioTransactionImporter($this->pdo);
        $importer->import([$this->transaction(1)]);
        $importer->import([$this->transaction(1)]);

        $count = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM fio_transactions')->fetch()['c'];
        self::assertSame(1, $count);
    }

    public function testReimportDoesNotResetMatchStatusThatWasAlreadySet(): void
    {
        $importer = new FioTransactionImporter($this->pdo);
        $importer->import([$this->transaction(1)]);

        $this->pdo->exec("UPDATE fio_transactions SET match_status = 'manual_confirmed' WHERE id = 1");

        $importer->import([$this->transaction(1)]);

        $status = $this->pdo->query('SELECT match_status FROM fio_transactions WHERE id = 1')->fetch()['match_status'];
        self::assertSame('manual_confirmed', $status);
    }
}
