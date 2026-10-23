<?php

declare(strict_types=1);

namespace Noelo\Monitor\StockFeed;

use Noelo\Monitor\StockFeed\Dto\StockItemDto;

/**
 * Rozhranie pre zdroj skladovych dat. Realnu strukturu skladoveho feedu
 * (XML/JSON/CSV, nazvy poli) este nepoznáme - dočasne pouzivame
 * FixtureStockFeedProvider s vymyslenymi datami, kym nedodá klient realny feed.
 */
interface StockFeedProviderInterface
{
    /**
     * @return array<string, StockItemDto> indexovane podla product code
     */
    public function getStock(): array;
}
