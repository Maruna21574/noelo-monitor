<?php

declare(strict_types=1);

namespace Noelo\Monitor\StockFeed;

use Noelo\Monitor\StockFeed\Dto\StockItemDto;

/**
 * DOCASNA implementacia, kym nedodame realny skladovy feed. Data su vymyslene
 * fixture udaje (viz resources/stock_fixture.json), nie realny sklad noelo.sk.
 */
final class FixtureStockFeedProvider implements StockFeedProviderInterface
{
    /** @param StockItemDto[] $items */
    public function __construct(private readonly array $items)
    {
    }

    public static function fromJsonFile(string $path): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Fixture subor so skladovymi datami nebol najdeny: {$path}");
        }

        $raw = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $items = array_map(
            static function (array $row): StockItemDto {
                return new StockItemDto(
                    productCode: (string) $row['productCode'],
                    quantityInStock: (int) $row['quantityInStock'],
                    quantityReserved: (int) $row['quantityReserved'],
                    soldOut: (bool) $row['soldOut'],
                    expectedDeliveryDate: isset($row['expectedDeliveryDate'])
                        ? new \DateTimeImmutable($row['expectedDeliveryDate'])
                        : null,
                );
            },
            $raw
        );

        return new self($items);
    }

    public function getStock(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item->productCode] = $item;
        }
        return $result;
    }
}
