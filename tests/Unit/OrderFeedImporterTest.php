<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\OrderFeed\OrderFeedImporter;
use Noelo\Monitor\OrderFeed\OrderFeedParser;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class OrderFeedImporterTest extends DatabaseTestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }

    public function testImportInsertsOrdersAndItems(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));
        $summary = (new OrderFeedImporter($this->pdo))->import($result);

        self::assertSame(2, $summary->ordersProcessed);
        self::assertSame(4, $summary->itemsProcessed);

        $orderCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'];
        $itemCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM order_items')->fetch()['c'];

        self::assertSame(2, $orderCount);
        self::assertSame(4, $itemCount);
    }

    public function testImportStoresReadyItemsAndOrderRepositoryReadsThemBack(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));
        (new OrderFeedImporter($this->pdo))->import($result);

        $readyByItemId = $this->pdo->query('SELECT id, ready_items FROM order_items')->fetchAll(\PDO::FETCH_KEY_PAIR);
        self::assertSame(1, (int) $readyByItemId[1108729]);
        self::assertSame(0, (int) $readyByItemId[1108734]);
        self::assertNull($readyByItemId[1109191]);

        $orders = (new OrderRepository($this->pdo))->findCurrentlyOpenOrders();
        $items = array_merge(...array_map(static fn ($o) => $o->items, $orders));
        $readyByDtoId = array_column(array_map(static fn ($i) => ['id' => $i->id, 'r' => $i->readyItems], $items), 'r', 'id');
        self::assertSame(1, $readyByDtoId[1108729]);
        self::assertSame(0, $readyByDtoId[1108734]);
        self::assertNull($readyByDtoId[1109191]);
    }

    public function testRepeatedImportOfSameFeedIsIdempotent(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));
        $importer = new OrderFeedImporter($this->pdo);

        $importer->import($result);
        $importer->import($result);

        $orderCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'];
        $itemCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM order_items')->fetch()['c'];

        self::assertSame(2, $orderCount);
        self::assertSame(4, $itemCount);
    }

    public function testReimportWithChangedStatusUpdatesExistingRowInPlace(): void
    {
        $importer = new OrderFeedImporter($this->pdo);

        $first = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));
        $importer->import($first);

        $updatedXml = str_replace('spracováva sa', 'expedovaná', $this->fixture('orders_valid.xml'));
        $second = (new OrderFeedParser())->parse($updatedXml);
        $importer->import($second);

        $orderCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'];
        self::assertSame(2, $orderCount);

        $status = $this->pdo->query('SELECT status FROM orders WHERE id = 3014234346')->fetch()['status'];
        self::assertSame('expedovaná', $status);
    }

    public function testAllOrdersInSameImportBatchShareIdenticalUpdatedAtTimestamp(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));
        (new OrderFeedImporter($this->pdo))->import($result);

        $timestamps = $this->pdo->query('SELECT DISTINCT updated_at FROM orders')->fetchAll(\PDO::FETCH_COLUMN);

        self::assertCount(1, $timestamps, 'Vsetky objednavky z jedneho behu importu musia mat presne ten isty updated_at.');
    }

    public function testOrdersFromAnOlderImportAreNoLongerCurrentlyOpenAfterANewerImportRuns(): void
    {
        $importer = new OrderFeedImporter($this->pdo);

        $first = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));
        $importer->import($first);

        // Simuluje, ze predchadzajuci import prebehol davno (davne objednavky uz
        // v aktualnom feede nie su - boli vybavene/zrusene, appka si ich ale
        // stale pamata).
        $this->pdo->exec("UPDATE orders SET updated_at = '2020-01-01 00:00:00'");

        $second = (new OrderFeedParser())->parse($this->fixture('orders_missing_field.xml'));
        $importer->import($second);

        $openOrderIds = array_map(
            static fn ($order) => $order->id,
            (new OrderRepository($this->pdo))->findCurrentlyOpenOrders()
        );

        self::assertSame([3014235000], $openOrderIds);
        self::assertNotContains(3014234346, $openOrderIds, 'Objednavka zo stareho importu uz nema byt "aktualne otvorena".');

        $totalOrdersEverSeen = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'];
        self::assertSame(3, $totalOrdersEverSeen, 'Stare objednavky sa nemazu, len sa vylucia z "aktualne otvorenych".');
    }

    public function testCashOnDeliveryOrderIsStoredWithNullPayedCents(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));
        (new OrderFeedImporter($this->pdo))->import($result);

        $row = $this->pdo->query('SELECT payed_cents, is_cash_on_delivery FROM orders WHERE id = 3014234334')->fetch();

        self::assertNull($row['payed_cents']);
        self::assertSame(1, (int) $row['is_cash_on_delivery']);
    }
}
