<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Reporting\ReservationConflictDismissalRepository;
use Noelo\Monitor\Rules\ReservationConflict;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class ReservationConflictDismissalTest extends DatabaseTestCase
{
    public function testDismissedConflictIsFilteredOutAndOthersRemain(): void
    {
        $repository = new ReservationConflictDismissalRepository($this->pdo);
        $repository->dismiss(2001, 'P1', 'admin');
        $repository->dismiss(2001, 'P1', 'admin'); // opakovane odkliknutie nesmie zlyhat

        $remaining = $repository->withoutDismissed([
            new ReservationConflict('P1', 2001, [1500]),
            new ReservationConflict('P2', 2001, [1500]),
            new ReservationConflict('P1', 2002, [1500]),
        ]);

        self::assertCount(2, $remaining);
        self::assertSame('P2', $remaining[0]->productCode);
        self::assertSame(2002, $remaining[1]->blockedOrderId);
    }
}
