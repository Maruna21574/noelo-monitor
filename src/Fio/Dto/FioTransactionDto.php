<?php

declare(strict_types=1);

namespace Noelo\Monitor\Fio\Dto;

final class FioTransactionDto
{
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $date,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly ?string $variableSymbol,
        public readonly ?string $counterAccountName,
        public readonly ?string $messageForRecipient,
        public readonly ?string $userIdentification,
        public readonly ?string $comment,
    ) {
    }
}
