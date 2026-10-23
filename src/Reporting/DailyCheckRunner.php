<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Fio\FioCheckStep;
use Noelo\Monitor\Mail\MailerException;
use Noelo\Monitor\OrderFeed\OrderFeedCheckStep;

/**
 * Jeden beh dennej kontroly: feed objednavok -> Fio -> parovanie platieb -> report.
 * Kazdy krok je izolovany - zlyhanie jedneho (napr. nedostupny Fio) nezastavi
 * ostatne kroky ani odoslanie reportu (technicka chyba sa v reporte len ukaze).
 *
 * Pouziva sa rovnako z cron entrypointu (bin/run-daily-check.php) aj z
 * "manualne spustit kontrolu" tlacidla v administracii (Faza 8) - meni sa len
 * `triggeredBy`/`triggeredByUser`.
 */
final class DailyCheckRunner
{
    /**
     * @param ?OrderFeedCheckStep $orderFeedStep null, ked ORDER_FEED_URL nie je v .env nastavene
     * @param ?FioCheckStep $fioStep null, ked FIO_API_TOKEN nie je v .env nastaveny
     * @param ?DailyReportSender $reportSender null, ked SMTP/prijemcovia nie su v .env nastaveni
     */
    public function __construct(
        private readonly CheckRunRepository $checkRuns,
        private readonly ?OrderFeedCheckStep $orderFeedStep,
        private readonly ?FioCheckStep $fioStep,
        private readonly PaymentMatchingService $paymentMatchingService,
        private readonly ?DailyReportSender $reportSender,
        private readonly ?PaymentReminderStep $paymentReminderStep = null,
    ) {
    }

    public function run(string $triggeredBy, ?string $triggeredByUser, \DateTimeImmutable $now): DailyCheckResult
    {
        $checkRunId = $this->checkRuns->start($triggeredBy, $triggeredByUser);

        $orderFeedOk = $this->orderFeedStep === null
            ? null
            : $this->safelyRun(fn () => $this->orderFeedStep->run($checkRunId));

        $fioOk = $this->fioStep === null
            ? null
            : $this->safelyRun(fn () => $this->fioStep->run($checkRunId));

        if ($fioOk === true) {
            $this->safelyRun(fn () => $this->paymentMatchingService->run());
        }

        // Pripomienky len ak mame cerstve data: objednavky nesmu byt zastarane a Fio
        // musi prejst (inak nevieme, ci zakaznik uz nezaplatil).
        $reminderSummary = null;
        if ($this->paymentReminderStep !== null && $fioOk === true && $orderFeedOk !== false) {
            try {
                $reminderSummary = $this->paymentReminderStep->run($checkRunId, $now);
            } catch (\Throwable) {
                // Nesmie zhodit zvysok behu (report).
            }
        }

        $reportResult = null;
        if ($this->reportSender !== null) {
            try {
                $reportResult = $this->reportSender->send($now, $checkRunId);
            } catch (MailerException) {
                // DailyReportSender uz ulozil report ako 'failed' - tu uz nie je co robit,
                // ale nesmie to zhodit cely beh kontroly.
            }
        }

        $this->checkRuns->finish($checkRunId);

        return new DailyCheckResult($checkRunId, $orderFeedOk, $fioOk, $reportResult, $reminderSummary);
    }

    private function safelyRun(callable $step): bool
    {
        try {
            $step();
            return true;
        } catch (\Throwable) {
            // Konkretna chyba je uz zapisana v feed_errors danym krokom (viz
            // OrderFeedCheckStep/FioCheckStep) - tu len zabranime, aby jedno
            // zlyhanie zastavilo zvysok behu.
            return false;
        }
    }
}
