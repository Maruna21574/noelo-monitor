<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Mail\Mailer;
use Noelo\Monitor\Mail\MailerException;

/**
 * Zabezpecuje, ze denny report sa odosle najviac raz za den (idempotencia
 * podla UNIQUE run_date v tabulke `reports`), aj pri opakovanom spusteni.
 */
final class DailyReportSender
{
    /** @param string[] $recipients */
    public function __construct(
        private readonly ReportBuilder $reportBuilder,
        private readonly ReportRepository $reportRepository,
        private readonly ReportTextRenderer $renderer,
        private readonly Mailer $mailer,
        private readonly array $recipients,
    ) {
    }

    /**
     * @param bool $force true = posli aj ked uz bol dnesny report odoslany (rucne "poslat znova"
     *                    z administracie) - inak sa dodrzi "najviac raz za den" automaticky.
     * @throws MailerException ked odoslanie zlyha (report ostane v stave 'failed', da sa neskor zopakovat)
     */
    public function send(\DateTimeImmutable $runDate, int $checkRunId, bool $force = false): ReportSendResult
    {
        $existing = $this->reportRepository->findByDate($runDate);
        if (!$force && $existing !== null && $existing['status'] === 'sent') {
            return ReportSendResult::ALREADY_SENT_TODAY;
        }

        $reportId = $existing['id']
            ?? $this->reportRepository->createPending($runDate, $checkRunId, implode(',', $this->recipients));

        $report = $this->reportBuilder->build($runDate, $checkRunId);
        $body = $this->renderer->render($report);
        $subject = 'Noelo Monitor - denny report ' . $runDate->format('Y-m-d');

        try {
            $this->mailer->send($this->recipients, $subject, $body);
        } catch (MailerException $e) {
            $this->reportRepository->markFailed($reportId);
            throw $e;
        }

        $this->reportRepository->markSent($reportId);

        return ReportSendResult::SENT;
    }
}
