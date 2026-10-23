<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Bootstrap\ReportBuilderFactory;
use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Http\Auth\SessionAuth;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\PaymentReminderLogRepository;
use Noelo\Monitor\Reporting\ReportRepository;
use PDO;

final class DashboardController
{
    public function __construct(private readonly PDO $pdo, private readonly Env $env)
    {
    }

    /** @param array<string, mixed> $session */
    public function handle(array &$session): void
    {
        $checkRunRepository = new CheckRunRepository($this->pdo);
        $latestRun = $checkRunRepository->findLatest();

        $report = ReportBuilderFactory::create($this->pdo)
            ->build(new \DateTimeImmutable('now'), (int) ($latestRun['id'] ?? 0));

        $pendingPayments = (new PaymentMatchRepository($this->pdo))->findPendingReviewForAdmin();
        $recentReminders = (new PaymentReminderLogRepository($this->pdo))->findRecent();
        $reminderMode = $this->env->get('PAYMENT_REMINDER_URL') === null
            ? 'off'
            : (string) $this->env->get('PAYMENT_REMINDER_MODE', 'off');
        $reportHistory = (new ReportRepository($this->pdo))->findRecentHistory();

        $csrfToken = Csrf::token($session);
        $username = (new SessionAuth($this->pdo))->currentUsername($session);

        require __DIR__ . '/../Views/dashboard.php';
    }
}
