<?php

declare(strict_types=1);

namespace Noelo\Monitor\Support;

/**
 * Bezpecna konverzia desatinnych sum na centy bez pouzitia float aritmetiky.
 */
final class Money
{
    public static function toCents(string $decimal): int
    {
        $decimal = trim($decimal);
        if ($decimal === '') {
            throw new \InvalidArgumentException('Prazdny retazec sa neda previest na centy.');
        }

        $negative = false;
        if ($decimal[0] === '-') {
            $negative = true;
            $decimal = substr($decimal, 1);
        } elseif ($decimal[0] === '+') {
            $decimal = substr($decimal, 1);
        }

        if (!preg_match('/^\d+(\.\d+)?$/', $decimal)) {
            throw new \InvalidArgumentException("Neplatny format sumy: '{$decimal}'.");
        }

        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0', STR_PAD_RIGHT);

        $cents = ((int) $whole) * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function toDecimalString(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $whole = intdiv($cents, 100);
        $fraction = $cents % 100;

        return ($negative ? '-' : '') . $whole . '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
