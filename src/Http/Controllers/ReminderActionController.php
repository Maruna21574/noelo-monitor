<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Bootstrap\PaymentReminderStepFactory;
use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Reporting\PaymentReminderStep;
use PDO;

/**
 * Tlacidla "Poslat mail" / "Neposielat" pri nezaplatenych objednavkach v dashboarde.
 * Mail zakaznikovi odide az po kliknuti na "Poslat mail".
 */
final class ReminderActionController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Env $env,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     */
    public function handle(array $post, array $session): void
    {
        if (!Csrf::isValid($session, $post['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Neplatny CSRF token.';
            return;
        }

        $step = PaymentReminderStepFactory::create($this->env, $this->pdo, dryRun: false);
        if ($step === null) {
            $this->redirect('not-configured');
            return;
        }

        $orderId = (int) ($post['order_id'] ?? 0);
        $action = (string) ($post['action'] ?? '');
        $now = new \DateTimeImmutable('now');

        if ($orderId <= 0) {
            $this->redirect(PaymentReminderStep::OUTCOME_NOT_DUE);
            return;
        }

        if ($action === 'send') {
            $this->redirect($step->sendManually($orderId, $now));
        } elseif ($action === 'skip') {
            $step->skip($orderId, $now);
            $this->redirect('skipped');
        } else {
            $this->redirect(PaymentReminderStep::OUTCOME_NOT_DUE);
        }
    }

    private function redirect(string $result): void
    {
        header('Location: ?page=dashboard&reminder=' . rawurlencode($result) . '#sec-reminders');
    }
}
