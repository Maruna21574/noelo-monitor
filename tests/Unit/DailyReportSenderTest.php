<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Mail\Mailer;
use Noelo\Monitor\Mail\MailerException;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\DailyReportSender;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\ReportBuilder;
use Noelo\Monitor\Reporting\ReportRepository;
use Noelo\Monitor\Reporting\ReportSendResult;
use Noelo\Monitor\Reporting\ReportTextRenderer;
use Noelo\Monitor\Rules\CancellationSuggestionRule;
use Noelo\Monitor\Rules\OrderAvailabilityEvaluator;
use Noelo\Monitor\Rules\OrderShippabilityEvaluator;
use Noelo\Monitor\Rules\PaymentReminderRule;
use Noelo\Monitor\Rules\ReservationConflictDetector;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;
use Noelo\Monitor\Tests\Unit\Fakes\FakeMailTransport;

final class DailyReportSenderTest extends DatabaseTestCase
{
    private function buildSender(FakeMailTransport $transport): DailyReportSender
    {
        $availability = new OrderAvailabilityEvaluator();

        $reportBuilder = new ReportBuilder(
            orderRepository: new OrderRepository($this->pdo),
            shippabilityEvaluator: new OrderShippabilityEvaluator($availability),
            reservationConflictDetector: new ReservationConflictDetector(),
            paymentMatchRepository: new PaymentMatchRepository($this->pdo),
            feedErrorRepository: new FeedErrorRepository($this->pdo),
            paymentReminderRule: new PaymentReminderRule(),
            cancellationSuggestionRule: new CancellationSuggestionRule(),
        );

        return new DailyReportSender(
            reportBuilder: $reportBuilder,
            reportRepository: new ReportRepository($this->pdo),
            renderer: new ReportTextRenderer(),
            mailer: new Mailer($transport, 'noreply@noelo.sk', 'Noelo Monitor'),
            recipients: ['admin@example.com'],
        );
    }

    public function testFirstRunOfTheDaySendsEmailAndMarksReportSent(): void
    {
        $checkRunId = (new CheckRunRepository($this->pdo))->start('manual');
        $transport = new FakeMailTransport();
        $sender = $this->buildSender($transport);

        $result = $sender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId);

        self::assertSame(ReportSendResult::SENT, $result);
        self::assertSame(1, $transport->callCount());

        $status = $this->pdo->query("SELECT status FROM reports WHERE run_date = '2026-09-17'")->fetch()['status'];
        self::assertSame('sent', $status);
    }

    public function testSecondRunSameDayDoesNotSendEmailAgain(): void
    {
        $checkRunId = (new CheckRunRepository($this->pdo))->start('manual');
        $transport = new FakeMailTransport();
        $sender = $this->buildSender($transport);

        $sender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId);
        $result = $sender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId);

        self::assertSame(ReportSendResult::ALREADY_SENT_TODAY, $result);
        self::assertSame(1, $transport->callCount());

        $reportCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM reports')->fetch()['c'];
        self::assertSame(1, $reportCount);
    }

    public function testSmtpFailureMarksReportAsFailedAndPropagatesException(): void
    {
        $checkRunId = (new CheckRunRepository($this->pdo))->start('manual');
        $transport = new FakeMailTransport(shouldFail: true);
        $sender = $this->buildSender($transport);

        try {
            $sender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId);
            self::fail('Ocakavana MailerException nebola vyhodena.');
        } catch (MailerException) {
            // ocakavane
        }

        $status = $this->pdo->query("SELECT status FROM reports WHERE run_date = '2026-09-17'")->fetch()['status'];
        self::assertSame('failed', $status);
    }

    public function testAfterSmtpFailureRetryOnSameDayReusesReportRowAndCanSucceed(): void
    {
        $checkRunId = (new CheckRunRepository($this->pdo))->start('manual');
        $failingSender = $this->buildSender(new FakeMailTransport(shouldFail: true));

        try {
            $failingSender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId);
        } catch (MailerException) {
            // ocakavane, skusame znova
        }

        $workingTransport = new FakeMailTransport();
        $retrySender = $this->buildSender($workingTransport);
        $result = $retrySender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId);

        self::assertSame(ReportSendResult::SENT, $result);
        self::assertSame(1, $workingTransport->callCount());

        $reportCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM reports')->fetch()['c'];
        self::assertSame(1, $reportCount);
    }

    public function testForceResendsEvenWhenAlreadySentToday(): void
    {
        $checkRunId = (new CheckRunRepository($this->pdo))->start('manual');
        $transport = new FakeMailTransport();
        $sender = $this->buildSender($transport);

        $sender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId);
        $result = $sender->send(new \DateTimeImmutable('2026-09-17'), $checkRunId, force: true);

        self::assertSame(ReportSendResult::SENT, $result);
        self::assertSame(2, $transport->callCount());

        // stale len jeden riadok v reports (updol sa, nie duplicitny insert)
        $reportCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM reports')->fetch()['c'];
        self::assertSame(1, $reportCount);
    }
}
