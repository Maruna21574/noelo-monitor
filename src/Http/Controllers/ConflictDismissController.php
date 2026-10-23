<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Http\Auth\SessionAuth;
use Noelo\Monitor\Reporting\ReservationConflictDismissalRepository;
use PDO;

final class ConflictDismissController
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
        $productCode = trim((string) ($post['product_code'] ?? ''));

        if ($orderId > 0 && $productCode !== '') {
            (new ReservationConflictDismissalRepository($this->pdo))
                ->dismiss($orderId, $productCode, $auth->currentUsername($session) ?? 'admin');
        }

        header('Location: ?page=dashboard#sec-conflicts');
    }
}
