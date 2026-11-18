<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Http\Auth\SessionAuth;
use Noelo\Monitor\Reporting\OrderNoteRepository;
use PDO;

final class OrderNoteController
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

        $orderId = (int) ($post['order_id'] ?? 0);
        if ($orderId > 0) {
            (new OrderNoteRepository($this->pdo))->save(
                $orderId,
                (string) ($post['note'] ?? ''),
                $auth->currentUsername($session) ?? 'admin'
            );
        }

        header('Location: ?page=dashboard#sec-payments');
    }
}
