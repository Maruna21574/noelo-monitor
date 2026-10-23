<?php

declare(strict_types=1);

/**
 * Jednorazový test spojenia s Fio API. Token sa nikdy nevypisuje ani neloguje.
 * Spustenie: php bin/test-fio-connection.php
 */

function loadEnv(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "Chyba: .env súbor nenájdený.\n");
        exit(1);
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value);
    }

    return $values;
}

$env = loadEnv(__DIR__ . '/../.env');
$token = $env['FIO_API_TOKEN'] ?? '';

if ($token === '') {
    fwrite(STDERR, "Chyba: FIO_API_TOKEN nie je nastavený v .env.\n");
    exit(1);
}

$dateTo = new DateTimeImmutable('today');
$dateFrom = $dateTo->modify('-10 days');

$url = sprintf(
    'https://fioapi.fio.cz/v1/rest/periods/%s/%s/%s/transactions.json',
    $token,
    $dateFrom->format('Y-m-d'),
    $dateTo->format('Y-m-d')
);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($ch);
curl_close($ch);

// URL a token sa nikdy nevypisujú - ani pri chybe.
if ($curlErrno !== 0) {
    fwrite(STDERR, "Chyba spojenia s Fio API (curl errno {$curlErrno}).\n");
    exit(1);
}

if ($httpCode !== 200) {
    fwrite(STDERR, "Fio API vrátilo neočakávaný HTTP status: {$httpCode}\n");
    exit(1);
}

$data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
$transactionList = $data['accountStatement']['transactionList']['transaction'] ?? [];
$count = is_array($transactionList) ? count($transactionList) : 0;

$accountInfo = $data['accountStatement']['info'] ?? [];
$accountId = $accountInfo['accountId'] ?? '(neznáme)';
$currency = $accountInfo['currency'] ?? '(neznáme)';

echo "OK: spojenie s Fio API funguje.\n";
echo "Účet: {$accountId} ({$currency})\n";
echo "Obdobie: {$dateFrom->format('Y-m-d')} - {$dateTo->format('Y-m-d')}\n";
echo "Počet transakcií: {$count}\n";

if ($count > 0) {
    $first = $transactionList[0];
    echo "\nUkážka štruktúry prvej transakcie (kľúče stĺpcov):\n";
    echo implode(', ', array_keys($first)) . "\n";
}
