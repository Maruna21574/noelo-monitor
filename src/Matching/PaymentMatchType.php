<?php

declare(strict_types=1);

namespace Noelo\Monitor\Matching;

enum PaymentMatchType: string
{
    case EXACT_VS = 'exact_vs';
    case AMOUNT_DATE_CANDIDATE = 'amount_date_candidate';
    case NAME_MESSAGE_CANDIDATE = 'name_message_candidate';

    /** Slovensky popis typu zhody pre dashboard a e-mail; neznama hodnota sa vrati nezmenena. */
    public static function labelFor(string $value): string
    {
        return match (self::tryFrom($value)) {
            self::EXACT_VS => 'presná zhoda (VS + suma)',
            self::AMOUNT_DATE_CANDIDATE => 'rovnaká suma a blízky dátum',
            self::NAME_MESSAGE_CANDIDATE => 'rovnaká suma + meno/správa',
            null => $value,
        };
    }
}
