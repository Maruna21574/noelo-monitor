<?php

declare(strict_types=1);

// Nikdy nezobrazovat surove chyby (stack trace, systemove cesty) koncovemu
// pouzivatelovi - vzdy len bezpecna hlaska, detail ide len do server logu.
ini_set('display_errors', '0');
set_exception_handler(static function (\Throwable $e): void {
    error_log('[noelo-monitor] Neosetrena vynimka: ' . $e->getMessage());
    http_response_code(500);
    echo 'Nastala technicka chyba. Skus to znova neskor, alebo kontaktuj administratora.';
});

// Session cookie hardening - PHP default (session.cookie_httponly/secure/samesite)
// je vypnute/nenastavene, co by pri buducej XSS/CSRF diere umoznilo ukradnutie
// session cookie. Nastavuje sa tu (nie v php.ini), aby to neovplyvnilo ine projekty
// na tom istom Laragon PHP.
$isHttps = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');

session_start();

require __DIR__ . '/../vendor/autoload.php';

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;
use Noelo\Monitor\Http\Auth\SessionAuth;
use Noelo\Monitor\Http\Controllers\ConflictDismissController;
use Noelo\Monitor\Http\Controllers\DashboardController;
use Noelo\Monitor\Http\Controllers\LoginController;
use Noelo\Monitor\Http\Controllers\LogoutController;
use Noelo\Monitor\Http\Controllers\HelpController;
use Noelo\Monitor\Http\Controllers\OrderFeedPreviewController;
use Noelo\Monitor\Http\Controllers\PaymentReviewController;
use Noelo\Monitor\Http\Controllers\ReminderActionController;
use Noelo\Monitor\Http\Controllers\RunCheckController;
use Noelo\Monitor\Http\Controllers\SendReportController;

$env = new Env(__DIR__ . '/../.env');
$pdo = Connection::fromEnv($env);
$auth = new SessionAuth($pdo);

$page = (string) ($_GET['page'] ?? 'dashboard');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($page === 'login') {
    (new LoginController($auth))->handle($method, $_POST, $_SESSION);
    exit;
}

if (!$auth->isAuthenticated($_SESSION)) {
    header('Location: ?page=login');
    exit;
}

if ($page === 'logout') {
    (new LogoutController($auth))->handle($_SESSION);
    exit;
}

if ($page === 'run-check' && $method === 'POST') {
    (new RunCheckController($pdo, $env))->handle($_POST, $_SESSION, $auth);
    exit;
}

if ($page === 'payment-review' && $method === 'POST') {
    (new PaymentReviewController($pdo))->handle($_POST, $_SESSION, $auth);
    exit;
}

if ($page === 'dismiss-conflict' && $method === 'POST') {
    (new ConflictDismissController($pdo))->handle($_POST, $_SESSION, $auth);
    exit;
}

if ($page === 'reminder-action' && $method === 'POST') {
    (new ReminderActionController($pdo, $env))->handle($_POST, $_SESSION);
    exit;
}

if ($page === 'send-report' && $method === 'POST') {
    (new SendReportController($pdo, $env))->handle($_POST, $_SESSION);
    exit;
}

if ($page === 'order-feed') {
    (new OrderFeedPreviewController($env))->handle();
    exit;
}

if ($page === 'help') {
    (new HelpController())->handle($_SESSION);
    exit;
}

(new DashboardController($pdo, $env))->handle($_SESSION);
