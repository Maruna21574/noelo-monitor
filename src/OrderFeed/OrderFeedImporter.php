<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use PDO;

/**
 * Idempotentny import: objednavky su identifikovane cez orders.id (order.id z feedu),
 * polozky cez order_items.id (orderedItem.id z feedu). Opakovany import toho isteho
 * feedu neduplikuje riadky, iba aktualizuje zmenene udaje (INSERT ... ON DUPLICATE KEY UPDATE).
 */
final class OrderFeedImporter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function import(OrderFeedParseResult $result): OrderFeedImportSummary
    {
        // Jeden spolocny casovy odtlacok pre cely beh - nie `new DateTimeImmutable()` per
        // objednavku - inak by pri velkom feede (stovky objednavok, beh trva niekolko sekund)
        // mal kazdy riadok mierne iny `updated_at` a nedalo by sa spolahlivo zistit,
        // ktore objednavky su z toho isteho (posledneho) importu = stale "aktualne otvorene".
        $importedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $itemsProcessed = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($result->orders as $order) {
                $this->upsertOrder($order, $importedAt);
                foreach ($order->items as $item) {
                    $this->upsertItem($order->id, $item);
                    $itemsProcessed++;
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new \RuntimeException('Import objednavok zlyhal, zmeny boli vratene spat.', previous: $e);
        }

        return new OrderFeedImportSummary(
            ordersProcessed: count($result->orders),
            itemsProcessed: $itemsProcessed,
            skippedOrderErrors: $result->skippedOrderErrors,
        );
    }

    private function upsertOrder(OrderDto $order, string $importedAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (
                id, created_at, status, delivery_name, email,
                total_price_cents, delivery_type, delivery_price_cents,
                payed_cents, is_cash_on_delivery, raw_hash, imported_at, updated_at
            ) VALUES (
                :id, :created_at, :status, :delivery_name, :email,
                :total_price_cents, :delivery_type, :delivery_price_cents,
                :payed_cents, :is_cash_on_delivery, :raw_hash, :imported_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                created_at = VALUES(created_at),
                status = VALUES(status),
                delivery_name = VALUES(delivery_name),
                email = VALUES(email),
                total_price_cents = VALUES(total_price_cents),
                delivery_type = VALUES(delivery_type),
                delivery_price_cents = VALUES(delivery_price_cents),
                payed_cents = VALUES(payed_cents),
                is_cash_on_delivery = VALUES(is_cash_on_delivery),
                raw_hash = VALUES(raw_hash),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            'id' => $order->id,
            'created_at' => $order->createdAt->format('Y-m-d H:i:s'),
            'status' => $order->status,
            'delivery_name' => $order->deliveryName,
            'email' => $order->email,
            'total_price_cents' => $order->totalPriceCents,
            'delivery_type' => $order->deliveryType,
            'delivery_price_cents' => $order->deliveryPriceCents,
            'payed_cents' => $order->payedCents,
            'is_cash_on_delivery' => (int) $order->isCashOnDelivery,
            'raw_hash' => $order->rawHash,
            'imported_at' => $importedAt,
            'updated_at' => $importedAt,
        ]);
    }

    private function upsertItem(int $orderId, \Noelo\Monitor\OrderFeed\Dto\OrderedItemDto $item): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (
                id, order_id, product_code, title, count,
                unit_price_cents, line_total_cents, delivery_info, supplier, is_discount, ready_items
            ) VALUES (
                :id, :order_id, :product_code, :title, :count,
                :unit_price_cents, :line_total_cents, :delivery_info, :supplier, :is_discount, :ready_items
            )
            ON DUPLICATE KEY UPDATE
                order_id = VALUES(order_id),
                product_code = VALUES(product_code),
                title = VALUES(title),
                count = VALUES(count),
                unit_price_cents = VALUES(unit_price_cents),
                line_total_cents = VALUES(line_total_cents),
                delivery_info = VALUES(delivery_info),
                supplier = VALUES(supplier),
                is_discount = VALUES(is_discount),
                ready_items = VALUES(ready_items)'
        );

        $stmt->execute([
            'id' => $item->id,
            'order_id' => $orderId,
            'product_code' => $item->productCode,
            'title' => $item->title,
            'count' => $item->count,
            'unit_price_cents' => $item->unitPriceCents,
            'line_total_cents' => $item->lineTotalCents,
            'delivery_info' => $item->deliveryInfo,
            'supplier' => $item->supplier,
            'is_discount' => (int) $item->isDiscount,
            'ready_items' => $item->readyItems,
        ]);
    }
}
