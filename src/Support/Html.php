<?php

declare(strict_types=1);

namespace Noelo\Monitor\Support;

final class Html
{
    public static function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function statusBadge(?string $status): string
    {
        if ($status === null) {
            return '<span class="empty">-</span>';
        }

        $class = $status === 'ok' ? 'pill pill-green' : 'pill pill-red';

        return '<span class="' . $class . '">' . self::e(strtoupper($status)) . '</span>';
    }
}
