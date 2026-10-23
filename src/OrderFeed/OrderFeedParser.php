<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\OrderFeed\Dto\OrderedItemDto;
use Noelo\Monitor\Support\Money;
use SimpleXMLElement;

final class OrderFeedParser
{
    /**
     * @throws OrderFeedParseException ked je XML fatalne necitatelne (nedá sa importovat vobec nic)
     */
    public function parse(string $xml): OrderFeedParseResult
    {
        $this->assertNoDangerousMarkup($xml);

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);

        if ($root === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new OrderFeedParseException(
                'XML feed objednavok sa neda spracovat: ' . $this->summarizeLibxmlErrors($errors)
            );
        }

        $orders = [];
        $skippedErrors = [];

        $index = 0;
        foreach ($root->order as $orderNode) {
            $index++;
            try {
                $orders[] = $this->parseOrder($orderNode);
            } catch (\InvalidArgumentException $e) {
                $orderIdForLog = $this->tryReadOrderId($orderNode) ?? "index {$index}";
                $skippedErrors[] = "Objednavka ({$orderIdForLog}) preskocena: " . $e->getMessage();
            }
        }

        return new OrderFeedParseResult($orders, $skippedErrors);
    }

    private function assertNoDangerousMarkup(string $xml): void
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new OrderFeedParseException('XML feed objednavok obsahuje zakazanu DOCTYPE/ENTITY deklaraciu.');
        }
    }

    /** @param \LibXMLError[] $errors */
    private function summarizeLibxmlErrors(array $errors): string
    {
        if ($errors === []) {
            return 'neznama chyba parsovania.';
        }
        $first = $errors[0];
        return sprintf('riadok %d, stlpec %d, kod %d.', $first->line, $first->column, $first->code);
    }

    private function tryReadOrderId(SimpleXMLElement $orderNode): ?string
    {
        $raw = (string) $orderNode->id;
        return $raw !== '' ? $raw : null;
    }

    private function parseOrder(SimpleXMLElement $node): OrderDto
    {
        $id = $this->requireInt($node->id, 'id');
        $createdAt = $this->requireDateTime((string) $node->created, 'created');
        $totalPriceCents = $this->requireMoney((string) $node->totalPrice, 'totalPrice');
        $deliveryPriceCents = $this->requireMoney((string) $node->deliveryPrice, 'deliveryPrice');

        $status = (string) $node->status;
        $deliveryName = (string) $node->deliveryName;
        $email = (string) $node->email;
        $deliveryType = (string) $node->deliveryType;

        $payedRaw = trim((string) $node->payed);
        $payedCents = $payedRaw === '' ? null : Money::toCents($payedRaw);

        // Dobierka, platba hotovostou pri osobnom odbere a prazdny deliveryType (feed
        // niekedy sposob dodania neposle - ide o dobierky) sa nikdy nepovazuju za
        // "nezaplatenu platbu vopred", aby sa na ne nevztahovalo pripomenutie/storno.
        $isCashOnDelivery = trim($deliveryType) === ''
            || stripos($deliveryType, 'dobierka') !== false
            || stripos($deliveryType, 'hotovosť') !== false
            || stripos($deliveryType, 'hotovost') !== false;

        $items = [];
        if (isset($node->orderedItems->orderedItem)) {
            foreach ($node->orderedItems->orderedItem as $itemNode) {
                $item = $this->tryParseItem($itemNode);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        $rawHash = hash('sha256', (string) $node->asXML());

        return new OrderDto(
            id: $id,
            createdAt: $createdAt,
            status: $status,
            deliveryName: $deliveryName,
            email: $email,
            totalPriceCents: $totalPriceCents,
            deliveryType: $deliveryType,
            deliveryPriceCents: $deliveryPriceCents,
            payedCents: $payedCents,
            isCashOnDelivery: $isCashOnDelivery,
            rawHash: $rawHash,
            items: $items,
        );
    }

    private function tryParseItem(SimpleXMLElement $node): ?OrderedItemDto
    {
        $idRaw = trim((string) $node->id);
        $priceRaw = trim((string) $node->price);

        if ($idRaw === '' || !ctype_digit($idRaw) || $priceRaw === '') {
            return null;
        }

        $countRaw = trim((string) $node->count);
        $count = $countRaw === '' ? 1 : (int) $countRaw;

        $unitPriceCents = Money::toCents($priceRaw);
        $lineTotalCents = $unitPriceCents * $count;

        $productCodeRaw = trim((string) $node->idProduct);
        $deliveryInfoRaw = trim((string) $node->deliveryInfo);
        $supplierRaw = trim((string) $node->supplier);
        $readyItemsRaw = trim((string) $node->readyItems);

        return new OrderedItemDto(
            id: (int) $idRaw,
            productCode: $productCodeRaw === '' ? null : $productCodeRaw,
            title: (string) $node->title,
            count: $count,
            unitPriceCents: $unitPriceCents,
            lineTotalCents: $lineTotalCents,
            deliveryInfo: $deliveryInfoRaw === '' ? null : $deliveryInfoRaw,
            supplier: $supplierRaw === '' ? null : $supplierRaw,
            isDiscount: $unitPriceCents < 0,
            readyItems: ctype_digit($readyItemsRaw) ? (int) $readyItemsRaw : null,
        );
    }

    private function requireInt(SimpleXMLElement $node, string $field): int
    {
        $raw = trim((string) $node);
        if ($raw === '' || !ctype_digit($raw)) {
            throw new \InvalidArgumentException("chybajuce alebo neplatne pole '{$field}'.");
        }
        return (int) $raw;
    }

    private function requireDateTime(string $raw, string $field): \DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \InvalidArgumentException("chybajuce pole '{$field}'.");
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);
        if ($dt === false) {
            throw new \InvalidArgumentException("neplatny format datumu v poli '{$field}'.");
        }
        return $dt;
    }

    private function requireMoney(string $raw, string $field): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \InvalidArgumentException("chybajuce pole '{$field}'.");
        }
        try {
            return Money::toCents($raw);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException("neplatna suma v poli '{$field}'.");
        }
    }
}
