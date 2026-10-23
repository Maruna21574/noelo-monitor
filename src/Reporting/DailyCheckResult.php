<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

final class DailyCheckResult
{
    /**
     * @param bool|null $orderFeedOk null = tento feed nie je nakonfigurovany (chyba URL v .env), nie chyba behu
     * @param bool|null $fioOk null = Fio API nie je nakonfigurovane (chyba token v .env), nie chyba behu
     */
    public function __construct(
        public readonly int $checkRunId,
        public readonly ?bool $orderFeedOk,
        public readonly ?bool $fioOk,
        public readonly ?ReportSendResult $reportResult,
        /** null = automaticke pripomienky su vypnute alebo sa nespustili */
        public readonly ?PaymentReminderSummary $reminderSummary = null,
    ) {
    }
}
