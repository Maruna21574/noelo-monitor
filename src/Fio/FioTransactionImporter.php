<?php

declare(strict_types=1);

namespace Noelo\Monitor\Fio;

use Noelo\Monitor\Fio\Dto\FioTransactionDto;
use PDO;

/**
 * Idempotentny import pohybov z Fio, dedup podla ID pohybu (column22).
 * Uz existujuce riadky sa needituju (napr. rucne potvrdeny/zamietnuty match
 * by sa nemal prepisat opakovanym importom), okrem zakladnych udajov pohybu.
 */
final class FioTransactionImporter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param FioTransactionDto[] $transactions */
    public function import(array $transactions): int
    {
        $imported = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($transactions as $transaction) {
                $this->upsert($transaction);
                $imported++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException('Import Fio transakcii zlyhal, zmeny boli vratene spat.', previous: $e);
        }

        return $imported;
    }

    private function upsert(FioTransactionDto $t): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fio_transactions (
                id, transaction_date, amount_cents, currency, variable_symbol,
                counter_account_name, message_for_recipient, user_identification, comment,
                match_status, imported_at
            ) VALUES (
                :id, :transaction_date, :amount_cents, :currency, :variable_symbol,
                :counter_account_name, :message_for_recipient, :user_identification, :comment,
                :match_status, :imported_at
            )
            ON DUPLICATE KEY UPDATE
                transaction_date = VALUES(transaction_date),
                amount_cents = VALUES(amount_cents),
                currency = VALUES(currency),
                variable_symbol = VALUES(variable_symbol),
                counter_account_name = VALUES(counter_account_name),
                message_for_recipient = VALUES(message_for_recipient),
                user_identification = VALUES(user_identification),
                comment = VALUES(comment)'
        );

        $stmt->execute([
            'id' => $t->id,
            'transaction_date' => $t->date->format('Y-m-d'),
            'amount_cents' => $t->amountCents,
            'currency' => $t->currency,
            'variable_symbol' => $t->variableSymbol,
            'counter_account_name' => $t->counterAccountName,
            'message_for_recipient' => $t->messageForRecipient,
            'user_identification' => $t->userIdentification,
            'comment' => $t->comment,
            'match_status' => 'unmatched',
            'imported_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
