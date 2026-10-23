<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

use Noelo\Monitor\Http\HttpClientInterface;
use Noelo\Monitor\Http\HttpTransportException;

final class OrderFeedFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $url,
        private readonly int $timeoutSeconds = 15,
        private readonly int $maxAttempts = 3,
        private readonly int $retryDelayMs = 500,
        private readonly ?string $basicAuthUsername = null,
        private readonly ?string $basicAuthPassword = null,
    ) {
    }

    /**
     * @throws OrderFeedFetchException po vycerpani vsetkych pokusov
     */
    public function fetch(): string
    {
        $lastError = 'neznama chyba.';

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $response = $this->httpClient->get(
                    $this->url,
                    $this->timeoutSeconds,
                    $this->basicAuthUsername,
                    $this->basicAuthPassword,
                );
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

        throw new OrderFeedFetchException(
            "Stiahnutie objednavkoveho feedu zlyhalo po {$this->maxAttempts} pokusoch: {$lastError}"
        );
    }

    private function waitBeforeRetry(int $attemptJustFinished): void
    {
        if ($attemptJustFinished >= $this->maxAttempts || $this->retryDelayMs <= 0) {
            return;
        }
        usleep($this->retryDelayMs * 1000);
    }
}
