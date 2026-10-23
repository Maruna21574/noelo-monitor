<?php

declare(strict_types=1);

namespace Noelo\Monitor\Support;

final class TextNormalizer
{
    /**
     * Odstrani diakritiku a prevedie na male pismena, aby sa dalo
     * porovnavat "Ján Novák" s "JAN NOVAK" a podobne.
     *
     * iconv('UTF-8','ASCII//TRANSLIT',...) sa zamerne nepouziva - jeho spravanie
     * je zavisle na systemovej C kniznici (na Windows/nekterych libc vklada
     * napr. apostrofy namiesto ciseho odstranenia diakritiky), preto pouzivame
     * vlastnu pevnu mapu znakov pre spolahlive spravanie naprac platform.
     */
    public static function normalize(string $text): string
    {
        static $diacriticsMap = [
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'í' => 'i',
            'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ŕ' => 'r',
            'š' => 's', 'ť' => 't', 'ú' => 'u', 'ý' => 'y', 'ž' => 'z',
            'ë' => 'e', 'ö' => 'o', 'ü' => 'u', 'ř' => 'r', 'ě' => 'e', 'ů' => 'u',
        ];

        $lower = mb_strtolower(trim($text), 'UTF-8');

        return strtr($lower, $diacriticsMap);
    }

    public static function containsNormalized(?string $haystack, ?string $needle): bool
    {
        if ($haystack === null || $needle === null || trim($needle) === '') {
            return false;
        }

        return str_contains(self::normalize($haystack), self::normalize($needle));
    }
}
