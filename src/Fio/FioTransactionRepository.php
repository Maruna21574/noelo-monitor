<?php

declare(strict_types=1);

namespace Noelo\Monitor\Fio;

use Noelo\Monitor\Fio\Dto\FioTransactionDto;
use PDO;

final class FioTransactionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return FioTransactionDto[] */
    public function findUnmatched(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, transaction_date, amount_cents, currency, variable_symbol,
                    counter_account_name, message_for_recipient, user_identification, comment
             FROM fio_transactions
             WHERE match_status = 'unmatched'"
        );

        $transactions = [];
        foreach ($stmt->fetchAll() as $row) {
            $transactions[] = new FioTransactionDto(
                id: (int) $row['id'],
                date: new \DateTimeImmutable($row['transaction_date']),
                amountCents: (int) $row['amount_cents'],
                currency: $row['currency'],
                variableSymbol: $row['variable_symbol'],
                counterAccountName: $row['counter_account_name'],
                messageForRecipient: $row['message_for_recipient'],
                userIdentification: $row['user_identification'],
                comment: $row['comment'],
            );
        }

        return $transactions;
    }
}
