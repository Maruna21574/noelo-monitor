<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

/**
 * Sprava vynimky obsahuje len technicky popis chyby XML (typ, riadok, stlpec),
 * nikdy obsah dat (mena, e-maily, adresy).
 */
final class OrderFeedParseException extends \RuntimeException
{
}
