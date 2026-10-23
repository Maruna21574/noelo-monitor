<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\OrderFeed\Dto\OrderedItemDto;
use PDO;

final class OrderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Objednavky, ktore XML feed neevidoval ako zaplatene a nie su dobierka -
     * jedine tieto ma smysel skusat parovat s Fio (viz PaymentMatcher).
     *
     * Obmedzene len na aktualne otvorene objednavky (viz `isCurrentlyOpenCondition`)
     * - inak by appka donekonecna nutila k platbe/stornu aj davno vybavene
     * objednavky, ktore uz z feedu zmizli.
     *
     * @return OrderDto[]
     */
    public function findUnpaidNonCashOnDeliveryOrders(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, created_at, status, delivery_name, email, total_price_cents,
                    delivery_type, delivery_price_cents, payed_cents, is_cash_on_delivery, raw_hash
             FROM orders
             WHERE (payed_cents IS NULL OR payed_cents = 0) AND is_cash_on_delivery = 0
               AND ' . $this->isCurrentlyOpenCondition() . '
             ORDER BY created_at, id'
        );

        $orders = [];
        foreach ($stmt->fetchAll() as $row) {
            $orders[] = $this->mapOrderRow($row, []);
        }

        return $orders;
    }

    /**
     * Nacita objednavky (aj s poloziek), ktore boli sucastou POSLEDNEHO uspesneho
     * importu objednavkoveho feedu - teda su realne "aktualne otvorene" podla shopu.
     *
     * Feed vzdy obsahuje len prave otvorene objednavky; ked sa objednavka vybavi
     * alebo zrusi, jednoducho z neho zmizne. Appka si ale objednavky uklada natrvalo
     * (kvoli historii/idempotencii), takze bez tohto filtra by report donekonecna
     * pripocitaval aj davno vybavene objednavky ako "nedostupne"/"nezaplatene" -
     * presne to sposobovalo, ze report ukazoval stovky poloziek namiesto realneho
     * aktualneho stavu.
     *
     * @return OrderDto[]
     */
    public function findCurrentlyOpenOrders(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, created_at, status, delivery_name, email, total_price_cents,
                    delivery_type, delivery_price_cents, payed_cents, is_cash_on_delivery, raw_hash
             FROM orders
             WHERE ' . $this->isCurrentlyOpenCondition() . '
             ORDER BY created_at, id'
        );
        $orderRows = $stmt->fetchAll();

        if ($orderRows === []) {
            return [];
        }

        $itemsByOrderId = $this->loadItemsForOrders(array_column($orderRows, 'id'));

        $orders = [];
        foreach ($orderRows as $row) {
            $orders[] = $this->mapOrderRow($row, $itemsByOrderId[(int) $row['id']] ?? []);
        }

        return $orders;
    }

    /**
     * `updated_at` sa pri kazdom importe nastavi na jeden spolocny cas pre cely beh
     * (viz OrderFeedImporter) - takze "rovna sa najnovsiemu `updated_at` v tabulke"
     * spolahlivo vyberie prave tie objednavky, ktore boli v POSLEDNOM importe.
     */
    private function isCurrentlyOpenCondition(): string
    {
        return 'updated_at = (SELECT MAX(updated_at) FROM orders)';
    }

    /**
     * @param array<int|string> $orderIds
     * @return array<int, OrderedItemDto[]>
     */
    private function loadItemsForOrders(array $orderIds): array
    {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, order_id, product_code, title, count, unit_price_cents, line_total_cents,
                    delivery_info, supplier, is_discount, ready_items
             FROM order_items
             WHERE order_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($orderIds));

        $itemsByOrderId = [];
        foreach ($stmt->fetchAll() as $row) {
            $itemsByOrderId[(int) $row['order_id']][] = new OrderedItemDto(
                id: (int) $row['id'],
                productCode: $row['product_code'],
                title: $row['title'],
                count: (int) $row['count'],
                unitPriceCents: (int) $row['unit_price_cents'],
                lineTotalCents: (int) $row['line_total_cents'],
                deliveryInfo: $row['delivery_info'],
                supplier: $row['supplier'],
                isDiscount: (bool) $row['is_discount'],
                readyItems: $row['ready_items'] === null ? null : (int) $row['ready_items'],
            );
        }

        return $itemsByOrderId;
    }

    /** @param OrderedItemDto[] $items */
    private function mapOrderRow(array $row, array $items): OrderDto
    {
        return new OrderDto(
            id: (int) $row['id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            status: $row['status'],
            deliveryName: $row['delivery_name'],
            email: $row['email'],
            totalPriceCents: (int) $row['total_price_cents'],
            deliveryType: $row['delivery_type'],
            deliveryPriceCents: (int) $row['delivery_price_cents'],
            payedCents: $row['payed_cents'] === null ? null : (int) $row['payed_cents'],
            isCashOnDelivery: (bool) $row['is_cash_on_delivery'],
            rawHash: $row['raw_hash'],
            items: $items,
        );
    }
}
