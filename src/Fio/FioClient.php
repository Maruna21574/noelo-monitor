<?php

declare(strict_types=1);

namespace Noelo\Monitor\Fio;

use Noelo\Monitor\Fio\Dto\FioTransactionDto;
use Noelo\Monitor\Http\HttpClientInterface;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\Support\Money;

/**
 * Read-only klient pre Fio Bank API (periods/transactions.json).
 * Token sa pouziva iba na zostavenie URL, nikdy sa nevypisuje do vynimiek ani logov.
 */
final class FioClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiToken,
        private readonly int $timeoutSeconds = 20,
        private readonly int $maxAttempts = 3,
        private readonly int $retryDelayMs = 2000,
    ) {
    }

    /**
     * @return FioTransactionDto[]
     * @throws FioApiException po vycerpani vsetkych pokusov alebo pri neplatnej odpovedi
     */
    public function fetchTransactions(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $url = sprintf(
            'https://fioapi.fio.cz/v1/rest/periods/%s/%s/%s/transactions.json',
            $this->apiToken,
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        $body = $this->fetchWithRetry($url);

        return $this->mapResponseToTransactions($body);
    }

    private function fetchWithRetry(string $url): string
    {
        $lastError = 'neznama chyba.';

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $response = $this->httpClient->get($url, $this->timeoutSeconds);
            } catch (HttpTransportException $e) {
                $lastError = $e->getMessage();
                $this->waitBeforeRetry($attempt);
                continue;
            }

            if ($response->statusCode === 200) {
                return $response->body;
            }

            $lastError = "neocakavany HTTP status {$response->statusCode}.";
            $this->waitBeforeRetry($attempt);
        }

        // URL obsahuje token - nikdy sa nesmie objavit v chybovej sprave.
        throw new FioApiException("Stiahnutie dat z Fio API zlyhalo po {$this->maxAttempts} pokusoch: {$lastError}");
    }

    private function waitBeforeRetry(int $attemptJustFinished): void
    {
        if ($attemptJustFinished >= $this->maxAttempts || $this->retryDelayMs <= 0) {
            return;
        }
        usleep($this->retryDelayMs * 1000);
    }

    /** @return FioTransactionDto[] */
    private function mapResponseToTransactions(string $body): array
    {
        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new FioApiException('Odpoved Fio API sa neda rozparsovat ako JSON.', previous: $e);
        }

        $rows = $data['accountStatement']['transactionList']['transaction'] ?? [];
        if (!is_array($rows)) {
            throw new FioApiException('Odpoved Fio API ma neocakavanu strukturu.');
        }

        $transactions = [];
        foreach ($rows as $row) {
            $transactions[] = $this->mapRow($row);
        }

        return $transactions;
    }

    private function mapRow(array $row): FioTransactionDto
    {
        $id = $row['column22']['value'] ?? null;
        $dateRaw = $row['column0']['value'] ?? null;
        $amountRaw = $row['column1']['value'] ?? null;
        $currency = $row['column14']['value'] ?? 'EUR';

        if ($id === null || $dateRaw === null || $amountRaw === null) {
            throw new FioApiException('Pohyb z Fio API neobsahuje povinne pole (ID/datum/suma).');
        }

        return new FioTransactionDto(
            id: (int) $id,
            date: $this->parseDate((string) $dateRaw),
            amountCents: Money::toCents(sprintf('%.2f', (float) $amountRaw)),
            currency: (string) $currency,
            variableSymbol: $this->nullableString($row['column5']['value'] ?? null),
            counterAccountName: $this->nullableString($row['column10']['value'] ?? null),
            messageForRecipient: $this->nullableString($row['column16']['value'] ?? null),
            userIdentification: $this->nullableString($row['column7']['value'] ?? null),
            comment: $this->nullableString($row['column25']['value'] ?? null),
        );
    }

    private function parseDate(string $raw): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-dO', $raw);
        if ($date !== false) {
            return $date;
        }

        // Fallback: zober iba prvych 10 znakov (Y-m-d), ak timezone suffix chyba/je iny.
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', substr($raw, 0, 10));
        if ($date === false) {
            throw new FioApiException('Datum pohybu z Fio API sa neda rozparsovat.');
        }

        return $date;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
