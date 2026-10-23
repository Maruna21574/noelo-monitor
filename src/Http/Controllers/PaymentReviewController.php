<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Http\Auth\SessionAuth;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use PDO;

final class PaymentReviewController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     */
    public function handle(array $post, array $session, SessionAuth $auth): void
    {
        if (!Csrf::isValid($session, $post['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Neplatny CSRF token.';
            return;
        }

        $matchId = (int) ($post['match_id'] ?? 0);
        $action = (string) ($post['action'] ?? '');
        $reviewedBy = $auth->currentUsername($session) ?? 'admin';

        $repository = new PaymentMatchRepository($this->pdo);

        if ($action === 'confirm') {
            $repository->confirm($matchId, $reviewedBy);
        } elseif ($action === 'reject') {
            $repository->reject($matchId, $reviewedBy);
        }

        header('Location: ?page=dashboard');
    }
}
